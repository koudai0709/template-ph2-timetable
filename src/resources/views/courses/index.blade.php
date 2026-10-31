<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>授業一覧</title>
</head>
<body>
    <h1>授業一覧</h1>

    <table>
        <thead>
            <tr>
                <th>曜日</th>
                <th>時限</th>
                <th>授業名</th>
                <th>担当教員</th>
                <th>教室</th>
                <th>登録状況</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($courses as $course)
                <tr>
                    <td>{{ ['月', '火', '水', '木', '金'][$course->day - 1] }}</td>
                    <td>{{ $course->period }}</td>
                    <td>{{ $course->name }}</td>
                    <td>{{ $course->teacher }}</td>
                    <td>{{ $course->room }}</td>
                    <td>
                        @if ($course->entries->isNotEmpty())
                            <span>登録済み</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p>
        <a href="{{ url('/entries') }}">自分の時間割へ戻る</a>
    </p>
</body>
</html>