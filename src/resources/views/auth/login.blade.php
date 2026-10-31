<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン</title>
</head>
<body>
    <h1>ログイン</h1>
    @if ($errors->any())
        <ul role="alert">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif
    <form method="POST" action="{{ url('/login') }}" novalidate>
        @csrf
        <p><label for="email">メールアドレス</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required></p>
        <p><label for="password">パスワード</label>
            <input id="password" type="password" name="password" autocomplete="current-password" required></p>
        <button type="submit">ログイン</button>
    </form>
    <p><a href="{{ url('/register') }}">ユーザー登録画面へ</a></p>
</body>
</html>
