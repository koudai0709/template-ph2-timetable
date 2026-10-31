<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ユーザー登録</title>
</head>
<body>
    <h1>ユーザー登録</h1>
    @if ($errors->any())
        <ul role="alert">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif
    <form method="POST" action="{{ url('/register') }}" novalidate>
        @csrf
        <p><label for="name">名前</label>
            <input id="name" name="name" value="{{ old('name') }}" autocomplete="name" required></p>
        <p><label for="email">メールアドレス</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required></p>
        <p><label for="password">パスワード</label>
            <input id="password" type="password" name="password" autocomplete="new-password" required></p>
        <p><label for="password_confirmation">パスワード確認用</label>
            <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required></p>
        <button type="submit">登録する</button>
    </form>
    <p><a href="{{ url('/login') }}">ログイン画面へ</a></p>
</body>
</html>
