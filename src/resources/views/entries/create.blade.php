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
            <label for="day">曜日</label>
            <select id="day" name="day" required>
                @foreach ([1 => '月', 2 => '火', 3 => '水', 4 => '木', 5 => '金'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('day', 1) == $value)>{{ $label }}曜日</option>
                @endforeach
            </select>
        </p>
        <p>
            <label for="period">時限</label>
            <select id="period" name="period" required>
                @foreach (range(1, 6) as $period)
                    <option value="{{ $period }}" @selected(old('period', 1) == $period)>{{ $period }}限</option>
                @endforeach
            </select>
        </p>
        <p>
            <label for="title">授業名</label>
            <input id="title" name="title" type="text" value="{{ old('title') }}" maxlength="255" required>
        </p>
        <p>
            <label for="room">教室（任意）</label>
            <input id="room" name="room" type="text" value="{{ old('room') }}" maxlength="255">
        </p>
        <button type="submit">登録する</button>
    </form>

    <p><a href="{{ url('/entries') }}">一覧へ戻る</a></p>
</body>
</html>
