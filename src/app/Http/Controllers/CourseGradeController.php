<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Throwable;

class CourseGradeController extends Controller
{
    private const CSV_HEADER = ['name', 'email', 'grade'];

    private const IMPORT_ERROR_BAG = 'gradeImport';

    public function download(Course $course)
    {
        Gate::authorize('manage-course-grades');

        $entries = $course->entries()
            ->where('status', 'approved')
            ->with('user')
            ->orderBy('id')
            ->get();

        return response()->streamDownload(
            function () use ($entries) {
                $handle = fopen('php://output', 'w');

                fwrite($handle, "\xEF\xBB\xBF");

                fputcsv(
                    $handle,
                    self::CSV_HEADER,
                    ',',
                    '"',
                    ''
                );

                foreach ($entries as $entry) {
                    fputcsv(
                        $handle,
                        [
                            $this->safeCsvValue(
                                $entry->user->name,
                                'name:'
                            ),
                            $this->safeCsvValue(
                                $entry->user->email,
                                'email:'
                            ),
                            $entry->grade ?? '',
                        ],
                        ',',
                        '"',
                        ''
                    );
                }

                fclose($handle);
            },
            "course-{$course->id}-roster.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function import(Request $request, Course $course): RedirectResponse
    {
        Gate::authorize('manage-course-grades');

        $validated = $request->validateWithBag(self::IMPORT_ERROR_BAG, [
            'grade_csv' => ['required', 'file', 'max:5120'],
        ], [
            'grade_csv.required' => '成績CSVを選択してください。',
            'grade_csv.file' => '成績CSVファイルを選択してください。',
            'grade_csv.max' => '成績CSVは5MB以下にしてください。',
            'grade_csv.uploaded' => '成績CSVをアップロードできませんでした。5MB以下のファイルを選択してください。',
        ]);

        [$rows, $errors] = $this->readGradeCsv($validated['grade_csv']->getPathname(), $course);
        if ($errors !== []) {
            return $this->importErrors($course, $errors);
        }

        $currentLine = $rows[0]['line'];
        try {
            $errors = DB::transaction(function () use ($course, $rows, &$currentLine) {
                $entries = $course->entries()
                    ->whereIn('id', array_column($rows, 'entry_id'))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                $users = User::query()
                    ->whereKey($entries->pluck('user_id')->all())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                // 読み取り後に履修状態やメールが変更されていないか、ロック中に全件確認する。
                $errors = [];
                foreach ($rows as $row) {
                    $entry = $entries->get($row['entry_id']);
                    if ($entry === null || $entry->status !== 'approved'
                        || $entry->user_id !== $row['user_id']
                        || $users->get($entry->user_id)?->email !== $row['email']) {
                        $errors[] = "{$row['line']}行目：履修状態またはメールアドレスが変更されました。";
                    }
                }
                if ($errors !== []) {
                    return $errors;
                }

                foreach ($rows as $row) {
                    $currentLine = $row['line'];
                    $entry = $entries->get($row['entry_id']);
                    $entry->grade = $row['grade'];
                    if (! $entry->save()) {
                        throw new RuntimeException('成績を保存できませんでした。');
                    }
                }

                return [];
            });
        } catch (Throwable $exception) {
            report($exception);

            return $this->importErrors($course, ["{$currentLine}行目付近：DB更新に失敗したため、成績を登録しませんでした。"]);
        }

        if ($errors !== []) {
            return $this->importErrors($course, $errors);
        }

        return redirect()->action([CourseController::class, 'show'], $course)
            ->with('grade_import_count', count($rows));
    }

    private function readGradeCsv(string $path, Course $course): array
    {
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            return [[], ['1行目：CSVファイルを読み込めませんでした。']];
        }

        try {
            $contents = stream_get_contents($stream);
            if ($contents === false) {
                return [[], ['1行目：CSVファイルを読み込めませんでした。']];
            }

            $offset = str_starts_with($contents, "\xEF\xBB\xBF") ? 3 : 0;
            fseek($stream, $offset);
            if (strlen($contents) === $offset) {
                return [[], ['1行目：CSVファイルが空です。']];
            }

            $eligible = $course->entries()->where('status', 'approved')->with('user')->get()
                ->keyBy(fn ($entry) => $entry->user->email);
            $line = 1;
            $seen = [];
            $rows = [];
            $errors = [];

            while (($fields = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $startLine = $line;
                $end = ftell($stream);
                $record = substr($contents, $offset, $end - $offset);
                $offset = $end;
                $line += substr_count($record, "\n");

                if (! mb_check_encoding($record, 'UTF-8')) {
                    $errors[] = "{$startLine}行目：文字コードはUTF-8で保存してください。";
                    break;
                }
                // fgetcsvが受理する閉じ忘れや、非引用フィールド内の引用符も拒否する。
                $field = '(?:"[^"]*+(?:""[^"]*+)*+"|[^",\r\n]*+)';
                if (! preg_match('/\A' . $field . '(?:,' . $field . ')*+\r?\n?\z/', $record)) {
                    $errors[] = "{$startLine}行目：CSVの引用符または形式が不正です。";
                    break;
                }

                if ($startLine === 1) {
                    if ($fields !== self::CSV_HEADER) {
                        $errors[] = '1行目：ヘッダーは name,email,grade の順で指定してください。';
                        break;
                    }
                    continue;
                }
                if (count($fields) !== 3) {
                    $errors[] = "{$startLine}行目：列数は3列にしてください。";
                    continue;
                }

                [, $csvEmail, $grade] = $fields;
                $email = $this->restoreCsvEmail($csvEmail);
                if (trim($email) === '') {
                    $errors[] = "{$startLine}行目：メールアドレスが空欄です。";
                    continue;
                }
                if (isset($seen[$email])) {
                    $errors[] = "{$startLine}行目：メールアドレスが{$seen[$email]}行目と重複しています。";
                    continue;
                }
                $seen[$email] = $startLine;
                if (! in_array($grade, ['A', 'B', 'C', 'D', 'F'], true)) {
                    $errors[] = $grade === ''
                        ? "{$startLine}行目：成績が空欄です。"
                        : "{$startLine}行目：成績はA・B・C・D・Fのいずれかを指定してください。";
                    continue;
                }
                $entry = $eligible->get($email);
                if ($entry === null) {
                    $errors[] = "{$startLine}行目：対象授業の承認済み履修者に一致するメールアドレスがありません。";
                    continue;
                }

                $rows[] = [
                    'line' => $startLine,
                    'entry_id' => $entry->id,
                    'user_id' => $entry->user_id,
                    'email' => $email,
                    'grade' => $grade,
                ];
            }

            if ($errors === [] && $rows === []) {
                $errors[] = '2行目：登録する成績のデータ行がありません。';
            }

            return [$rows, $errors];
        } finally {
            fclose($stream);
        }
    }

    private function restoreCsvEmail(string $value): string
    {
        $prefix = 'email:';
        if (str_starts_with($value, $prefix)) {
            $original = substr($value, strlen($prefix));
            if ($this->safeCsvValue($original, $prefix) === $value) {
                return $original;
            }
        }

        return $value;
    }

    private function importErrors(Course $course, array $errors): RedirectResponse
    {
        return redirect()->action([CourseController::class, 'show'], $course)
            ->withErrors(['grade_csv' => $errors], self::IMPORT_ERROR_BAG);
    }

    private function safeCsvValue(
        string $value,
        string $prefix
    ): string {
        if (
            str_starts_with($value, $prefix) ||
            preg_match(
                '/^(?:[\s\p{Z}\p{C}]*[=+\-@＝＋－＠]|[\t\r\n])/u',
                $value
            )
        ) {
            return $prefix . $value;
        }

        return $value;
    }
}
