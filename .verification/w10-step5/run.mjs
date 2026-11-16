import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { spawnSync, spawn } from 'node:child_process';

// 検証専用コンテナだけを操作し、開発用DBは読み取り専用で照合する。
const root = process.cwd();
const out = path.join(root, '.verification', 'w10-step5');
const tag = `w10-step5-${Date.now()}`;
const network = `${tag}-net`, db = `${tag}-db`, app = `${tag}-app`;
const base = 'http://127.0.0.1:8000';
const results = [], created = [];
let devBefore, sourceBefore;
fs.mkdirSync(out, { recursive: true });
function command(binary, args, input) {
  const r = spawnSync(binary, args, { cwd: root, input, encoding: 'utf8', timeout: 120000, maxBuffer: 64 * 1024 * 1024, windowsHide: true });
  if (r.error || r.status !== 0) throw new Error(`${binary} ${args.slice(0, 3).join(' ')}: ${r.error?.message ?? r.stderr ?? r.stdout}`);
  return r.stdout;
}
const docker = (args, input) => command('docker', args, input);
const logs = name => {
  const r=spawnSync('docker',['logs',name],{encoding:'utf8',windowsHide:true});
  return (r.stdout??'')+(r.stderr??'');
};
async function httpRequest(url, method='GET', headers={}, body='') {
  const payload=JSON.stringify({url,method,headers,body});
  const output=await new Promise((resolve,reject)=>{
    const child=spawn('docker',['exec','-i',app,'php','/tmp/w10-http.php'],{windowsHide:true});
    let stdout='',stderr='';child.stdout.on('data',d=>stdout+=d);child.stderr.on('data',d=>stderr+=d);
    child.on('error',reject);child.on('close',code=>code===0?resolve(stdout):reject(new Error(`HTTP client ${code}: ${stderr} ${stdout}`)));
    child.stdin.end(payload);
  });
  const r=JSON.parse(output);
  return {status:r.status,headers:r.headers,body:Buffer.from(r.body,'base64').toString('utf8')};
}
const sql = query => docker(['exec', '-i', db, 'psql', '-U', 'w10test', '-d', 'w10_step5', '-v', 'ON_ERROR_STOP=1', '-At'], query);
const wait = ms => new Promise(resolve => setTimeout(resolve, ms));
function sourceManifest() {
  const result = {};
  function scan(dir) {
    for (const file of fs.readdirSync(dir, { withFileTypes: true })) {
      if (file.name === 'cache' || file.name.startsWith('.env')) continue;
      const full = path.join(dir, file.name);
      if (file.isDirectory()) scan(full);
      else if (file.isFile()) result[path.relative(root, full)] = crypto.createHash('sha256').update(fs.readFileSync(full)).digest('hex');
    }
  }
  for (const dir of ['app', 'bootstrap', 'config', 'routes', 'resources', 'public']) scan(path.join(root, 'src', dir));
  return result;
}
function devFingerprint() {
  const tables = ['users', 'courses', 'timetable_entries', 'reviews', 'tags', 'course_tag', 'favorite_courses'];
  const query = tables.map(t => `SELECT '${t}',count(*),md5(coalesce(jsonb_agg(to_jsonb(t) ORDER BY to_jsonb(t)::text)::text,'[]')) FROM public.${t} t`).join(';') + ';';
  return docker(['exec', '-i', 'template-ph2-timetable-db-1', 'psql', '-U', 'posse', '-d', 'timetable', '-v', 'ON_ERROR_STOP=1', '-At'], `BEGIN READ ONLY;\n${query}\nCOMMIT;`);
}
const files = {
  admin: 'app/Http/Controllers/AdminEntryController.php',
  student: 'app/Http/Controllers/UserTimetableEntryController.php',
  cancel: 'app/Http/Controllers/TimetableEntryController.php',
  routes: 'routes/web.php',
};
const original = Object.fromEntries(Object.entries(files).map(([k, f]) => [k, fs.readFileSync(path.join(root, 'src', f), 'utf8')]));
function replaceExactly(text, from, to, label) {
  if (!text.includes(from)) throw new Error(`変更対象が見つかりません: ${label}`);
  return text.replace(from, to);
}
function variant(mode) {
  const copy = { ...original };
  if (mode === 'roles-off') {
    copy.routes = replaceExactly(copy.routes, "Route::middleware('can:decide-enrollments')->group(function () {", 'Route::group([], function () {', 'Gate');
    copy.admin = copy.admin.replace(/^\s*abort_unless\(auth\(\)->user\(\)->is_admin, 403\);\r?\n/gm, '\n');
    if (copy.admin.includes('abort_unless(auth()->user()->is_admin')) throw new Error('権限チェックの除去失敗');
  }
  if (mode === 'state-off') {
    copy.admin = replaceExactly(copy.admin, "abort_unless($entry->status === 'pending', 403);", '// 検証専用：状態チェックを外す', '承認状態');
    copy.cancel = replaceExactly(copy.cancel, "->where('status', 'pending')", '', '取り消し状態');
    copy.student = copy.student.replace(/^\s*Rule::unique\('timetable_entries', 'course_id'\).*\r?\n/m, '\n');
    if (copy.student.includes("Rule::unique('timetable_entries'")) throw new Error('再申請チェックの除去失敗');
  }
  if (mode.startsWith('race-')) {
    if (mode === 'race-unlocked') copy.admin = copy.admin.replace(/->lockForUpdate\(\)/g, '');
    copy.admin = replaceExactly(copy.admin, '// 定員に達していたら承認できない', `// 検証専用：SQLごとの観測記録（ログイン情報は含めない）
            file_put_contents('/tmp/w10-approval.jsonl', json_encode(['pid'=>getmypid(),'entry_id'=>$id,'count'=>$approvedCount,'time'=>microtime(true)]) . PHP_EOL, FILE_APPEND | LOCK_EX);
        // 定員に達していたら承認できない`, '観測');
    copy.admin = replaceExactly(copy.admin, "$entry->status = 'approved';", "sleep(3); // 検証専用：人数確認後に処理を重ねる\n            $entry->status = 'approved';", '待機');
  }
  const dir = path.join(out, 'variants', mode);
  fs.mkdirSync(dir, { recursive: true });
  for (const [key, content] of Object.entries(copy)) {
    const local = path.join(dir, `${key}.php`);
    fs.writeFileSync(local, content);
    docker(['cp', local, `${app}:/var/www/html/${files[key]}`]);
    docker(['exec', app, 'php', '-l', `/var/www/html/${files[key]}`]);
  }
  console.log(`検証モード: ${mode}`);
}
let passwordHash;
function reset() {
  sql(`TRUNCATE TABLE timetable_entries, courses, users RESTART IDENTITY CASCADE;
INSERT INTO users(id,name,email,password,is_admin,created_at,updated_at) VALUES
 (1,'検証管理者A','admin-a@test.invalid','${passwordHash}',true,now(),now()),
 (2,'検証管理者B','admin-b@test.invalid','${passwordHash}',true,now(),now()),
 (3,'検証学生A','student-a@test.invalid','${passwordHash}',false,now(),now()),
 (4,'検証学生B','student-b@test.invalid','${passwordHash}',false,now(),now()),
 (5,'検証学生C','student-c@test.invalid','${passwordHash}',false,now(),now()),
 (6,'検証学生D','student-d@test.invalid','${passwordHash}',false,now(),now());
INSERT INTO courses(id,code,name,teacher,day,period,room,credits,required,capacity) VALUES
 (1,'SE300','検証ゼミ','検証教員',1,1,'検証教室',2,false,3),
 (2,'TEST200','検証授業','検証教員',2,2,'検証教室',2,false,40);
INSERT INTO timetable_entries(id,user_id,course_id,status,decided_at,created_at,updated_at) VALUES
 (1,3,1,'approved',now(),now(),now()),(2,4,1,'approved',now(),now(),now()),
 (3,5,1,'pending',NULL,now(),now()),(4,6,1,'pending',NULL,now(),now());
SELECT setval(pg_get_serial_sequence('users','id'),6);
SELECT setval(pg_get_serial_sequence('timetable_entries','id'),100);`);
}
class Browser {
  cookies = new Map();
  token;
  async request(url, fields) {
    const started = Date.now();
    const headers = { Accept: fields ? 'application/json' : 'text/html', Cookie: [...this.cookies].map(([k,v]) => `${k}=${v}`).join('; ') };
    if (fields) headers['Content-Type'] = 'application/x-www-form-urlencoded';
    const r = await httpRequest(base+url,fields?'POST':'GET',headers,fields?new URLSearchParams({_token:this.token??'',...fields}).toString():'');
    for (const line of r.headers.filter(h=>h.toLowerCase().startsWith('set-cookie:'))) {
      const value=line.slice(line.indexOf(':')+1).trim();
      const item = value.split(';')[0], index = item.indexOf('=');
      this.cookies.set(item.slice(0,index), item.slice(index+1));
    }
    const body = r.body;
    const token = body.match(/name="_token"\s+value="([^"]+)"/);
    if (token) this.token = token[1];
    return { status: r.status, location: r.headers.find(h=>h.toLowerCase().startsWith('location:'))?.slice(9).trim()??null, elapsed_ms: Date.now()-started, body };
  }
  async login(email) {
    const form = await this.request('/login');
    if (form.status !== 200 || !this.token) throw new Error(`ログイン画面取得失敗: ${form.status}`);
    const login = await this.request('/login', {email, password:'password123'});
    if (login.status !== 302) throw new Error(`ログイン失敗: ${login.status} ${login.body.slice(0,300)}`);
    const page = await this.request('/entries');
    if (page.status !== 200) throw new Error(`時間割取得失敗: ${page.status}`);
    return this;
  }
}
const login = email => new Browser().login(email);
function entry(id) { return JSON.parse(sql(`SELECT row_to_json(e) FROM (SELECT id,user_id,course_id,status,decided_at FROM timetable_entries WHERE id=${id}) e;`).trim() || 'null'); }
function record(name, mode, response, final) {
  const row = { name, mode, http: response.status, location: response.location, elapsed_ms: response.elapsed_ms, final };
  if (response.status >= 400 && response.body.startsWith('{')) { try { row.error = JSON.parse(response.body); } catch {} }
  results.push(row); console.log(JSON.stringify(row));
  fs.writeFileSync(path.join(out,'results.json'), JSON.stringify(results,null,2));
}
async function roleChecks(mode) {
  variant(mode); reset();
  const student = await login('student-c@test.invalid');
  record('一般学生の管理者一覧GET',mode,await student.request('/admin/entries'),{});
  record('一般学生の承認POST',mode,await student.request('/admin/entries/3/approve',{}),entry(3));
}
async function stateChecks(mode) {
  variant(mode); reset();
  sql("UPDATE timetable_entries SET status='rejected',decided_at=now() WHERE id=3;");
  const admin = await login('admin-a@test.invalid');
  record('却下済み申請を承認',mode,await admin.request('/admin/entries/3/approve',{}),entry(3));
  reset();
  const studentA = await login('student-a@test.invalid');
  record('承認済み申請を学生が取り消し',mode,await studentA.request('/entries/1',{_method:'DELETE'}),entry(1));
  reset(); sql("UPDATE timetable_entries SET status='rejected',decided_at=now() WHERE id=3;");
  const studentC = await login('student-c@test.invalid');
  const response = await studentC.request('/entries',{course_id:'1'});
  record('却下された授業へ再申請',mode,response,{ entry:entry(3), rows:Number(sql('SELECT count(*) FROM timetable_entries WHERE user_id=5 AND course_id=1;').trim()) });
}
async function raceCheck(mode) {
  variant(mode); reset();
  docker(['exec',app,'php','-r',"file_put_contents('/tmp/w10-approval.jsonl','');"]);
  const a = await login('admin-a@test.invalid'), b = await login('admin-b@test.invalid');
  const responses = await Promise.all([a.request('/admin/entries/3/approve',{}),b.request('/admin/entries/4/approve',{})]);
  const observations = docker(['exec',app,'cat','/tmp/w10-approval.jsonl']).trim().split('\n').filter(Boolean).map(JSON.parse);
  const final = { approved_count:Number(sql("SELECT count(*) FROM timetable_entries WHERE course_id=1 AND status='approved';").trim()), entries:[entry(3),entry(4)], observations };
  results.push({name:'同時承認',mode,http:responses.map(r=>r.status),elapsed_ms:responses.map(r=>r.elapsed_ms),final});
  console.log(JSON.stringify(results.at(-1)));fs.writeFileSync(path.join(out,'results.json'),JSON.stringify(results,null,2));
}
let failure;
try {
  sourceBefore=sourceManifest();devBefore=devFingerprint();
  fs.writeFileSync(path.join(out,'source-before.json'),JSON.stringify(sourceBefore,null,2));
  fs.writeFileSync(path.join(out,'development-before.txt'),devBefore);
  const archive=path.join(out,'application.tar');
  command('tar',['-cf',archive,'--exclude=.env','--exclude=.env.*','--exclude=bootstrap/cache','-C','src','app','bootstrap/app.php','bootstrap/providers.php','config','routes','resources','public','vendor','composer.json','composer.lock','artisan']);
  docker(['network','create','--internal',network]);created.push('network');
  docker(['run','-d','--name',db,'--network',network,'--network-alias','w10-step5-db','--tmpfs','/var/lib/postgresql:rw','-e','POSTGRES_DB=w10_step5','-e','POSTGRES_USER=w10test','-e','POSTGRES_PASSWORD=w10-local-only','postgres:18-alpine']);created.push('db');
  let ready=false;
  for(let n=0;n<30;n++){try{sql('SELECT 1;');ready=true;break;}catch{await wait(500);}}
  if(!ready)throw new Error('検証PostgreSQLを起動できません');
  const schema=docker(['exec','template-ph2-timetable-db-1','pg_dump','-U','posse','-d','timetable','--schema-only','--no-owner','--no-privileges']);
  fs.writeFileSync(path.join(out,'schema.sql'),schema);sql(schema);
  const env=['APP_ENV=local','APP_DEBUG=false',`APP_KEY=base64:${crypto.randomBytes(32).toString('base64')}`,'APP_URL='+base,'DB_CONNECTION=pgsql','DB_HOST=w10-step5-db','DB_PORT=5432','DB_DATABASE=w10_step5','DB_USERNAME=w10test','DB_PASSWORD=w10-local-only','DB_URL=','SESSION_DRIVER=file','CACHE_STORE=array','QUEUE_CONNECTION=sync','MAIL_MAILER=log','LOG_CHANNEL=single','PHP_CLI_SERVER_WORKERS=4','APP_CONFIG_CACHE=/tmp/no-w10-config.php','APP_ROUTES_CACHE=/tmp/no-w10-routes.php'];
  docker(['run','-d','--name',app,'--user','0','--network',network,...env.flatMap(e=>['-e',e]),'template-ph2-timetable-app:latest','sh','-c','while [ ! -f /tmp/w10-ready ]; do sleep 1; done; cd /var/www/html; exec php -d opcache.enable=0 -S 0.0.0.0:8000 -t public public/index.php']);created.push('app');
  docker(['cp',archive,`${app}:/tmp/application.tar`]);
  docker(['exec',app,'sh','-c','tar -xf /tmp/application.tar -C /var/www/html && mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data storage/logs bootstrap/cache && touch /tmp/w10-ready']);
  const httpClient=`<?php
$d=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$headers=[];foreach($d['headers'] as $k=>$v)$headers[]=$k.': '.$v;
$context=stream_context_create(['http'=>['method'=>$d['method'],'header'=>implode("\\r\\n",$headers),'content'=>$d['body'],'ignore_errors'=>true,'follow_location'=>0,'timeout'=>60]]);
$body=@file_get_contents($d['url'],false,$context);
if($body===false){fwrite(STDERR,json_encode(error_get_last()));exit(1);}
preg_match('/HTTP\\/\\S+ (\\d+)/',$http_response_header[0],$m);
echo json_encode(['status'=>(int)$m[1],'headers'=>$http_response_header,'body'=>base64_encode($body)],JSON_THROW_ON_ERROR);
`;
  fs.writeFileSync(path.join(out,'http-client.php'),httpClient);
  docker(['cp',path.join(out,'http-client.php'),`${app}:/tmp/w10-http.php`]);
  passwordHash=docker(['exec',app,'php','-r',"echo password_hash('password123', PASSWORD_BCRYPT, ['cost'=>4]);"]).trim();
  for(let n=0;n<30;n++){try{const r=await httpRequest(base+'/login');if(r.status===200)break;if(n===29)throw new Error('HTTP '+r.status);}catch(e){if(n===29)throw e;}await wait(500);}
  const environment={network,db,app,base,postgres:sql('SELECT version(); SHOW default_transaction_isolation;').trim(),host_mounts:JSON.parse(docker(['inspect','--format','{{json .Mounts}}',app])),db_mounts:JSON.parse(docker(['inspect','--format','{{json .Mounts}}',db]))};
  fs.writeFileSync(path.join(out,'environment.json'),JSON.stringify(environment,null,2));
  await roleChecks('roles-off');await roleChecks('guarded');
  await stateChecks('state-off');await stateChecks('guarded');
  await raceCheck('race-unlocked');await raceCheck('race-locked');
  fs.writeFileSync(path.join(out,'server.log'),logs(app));
  const log=docker(['exec',app,'cat','/var/www/html/storage/logs/laravel.log']);
  fs.writeFileSync(path.join(out,'laravel.log'),log);
  console.log('全ケースの実行が完了しました');
} catch(e) {failure=e;console.error(e.message);fs.writeFileSync(path.join(out,'failure.txt'),e.stack??e.message);if(created.includes('app')){try{fs.writeFileSync(path.join(out,'server.log'),logs(app));fs.writeFileSync(path.join(out,'laravel.log'),docker(['exec',app,'cat','/var/www/html/storage/logs/laravel.log']));}catch{}}}
finally {
  const cleanup=[];
  for(const kind of ['app','db'])if(created.includes(kind)){const name=kind==='app'?app:db;try{docker(['rm','-f',name]);cleanup.push(`${name}: removed`);}catch(e){cleanup.push(e.message);}}
  if(created.includes('network'))try{docker(['network','rm',network]);cleanup.push(`${network}: removed`);}catch(e){cleanup.push(e.message);}
  if(sourceBefore){const after=sourceManifest();fs.writeFileSync(path.join(out,'source-after.json'),JSON.stringify(after,null,2));cleanup.push(`original_source_unchanged=${JSON.stringify(sourceBefore)===JSON.stringify(after)}`);}
  if(devBefore){try{const after=devFingerprint();fs.writeFileSync(path.join(out,'development-after.txt'),after);cleanup.push(`development_data_unchanged=${devBefore===after}`);}catch(e){cleanup.push(e.message);}}
  fs.writeFileSync(path.join(out,'cleanup.txt'),cleanup.join('\n'));console.log(cleanup.join('\n'));
}
if(failure)process.exitCode=1;
