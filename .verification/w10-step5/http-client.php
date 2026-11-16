<?php
$d=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$headers=[];foreach($d['headers'] as $k=>$v)$headers[]=$k.': '.$v;
$context=stream_context_create(['http'=>['method'=>$d['method'],'header'=>implode("\r\n",$headers),'content'=>$d['body'],'ignore_errors'=>true,'follow_location'=>0,'timeout'=>60]]);
$body=@file_get_contents($d['url'],false,$context);
if($body===false){fwrite(STDERR,json_encode(error_get_last()));exit(1);}
preg_match('/HTTP\/\S+ (\d+)/',$http_response_header[0],$m);
echo json_encode(['status'=>(int)$m[1],'headers'=>$http_response_header,'body'=>base64_encode($body)],JSON_THROW_ON_ERROR);
