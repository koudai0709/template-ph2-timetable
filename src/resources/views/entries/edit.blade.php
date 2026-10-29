<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>授業の編集</title>
</head>
<body>
    <h1>授業の編集</h1>

    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ url('/entries/' . $entry->id) }}">
        @csrf
        @method('PUT')

        <p>
            <label for="title">授業名</label>
            <input id="title" name="title" type="text" value="{{ old('title', $entry->title) }}" maxlength="255" required>
        </p>
        <p>
            <label for="room">教室（任意）</label>
            <input id="room" name="room" type="text" value="{{ old('room', $entry->room) }}" maxlength="255">
        </p>
        <button type="submit">更新する</button>
    </form>

    <p><a href="{{ url('/entries') }}">一覧へ戻る</a></p>
</body>
</html>
