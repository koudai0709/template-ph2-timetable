<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>授業の登録</title>
</head>
<body>
    <h1>授業の登録</h1>

    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ url('/entries') }}">
        @csrf

        <p>
            <label for="course_id">授業を選択</label>
            <select id="course_id" name="course_id" required>
                <option value="">選択してください</option>

                @foreach ($courses as $course)
                    <option value="{{ $course->id }}"
                        @selected(old('course_id') == $course->id)>
                        {{ ['月', '火', '水', '木', '金'][$course->day - 1] }}
                        {{ $course->period }}限
                        {{ $course->name }}
                        （{{ $course->teacher }}）
                    </option>
                @endforeach
            </select>
        </p>

        <button type="submit">登録する</button>
    </form>

    <p><a href="{{ url('/entries') }}">一覧へ戻る</a></p>
</body>
</html>

