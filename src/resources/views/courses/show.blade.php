<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>授業詳細</title>
</head>
<body>
    <h1>{{ $course->name }}</h1>

    <p>担当教員：{{ $course->teacher }}</p>
    <p>曜日：{{ ['月', '火', '水', '木', '金'][$course->day - 1] }}</p>
    <p>時限：{{ $course->period }}</p>
    <p>教室：{{ $course->room }}</p>

    <h2>タグ</h2>

    @forelse ($course->tags as $tag)
        <span>{{ $tag->name }}</span>
    @empty
        <p>タグはありません。</p>
    @endforelse

    <h2>受講者情報</h2>

    <p>受講者数：{{ $course->students->count() }}人</p>

    <h3>受講者一覧</h3>

    @forelse ($course->students as $student)
        <p>{{ $student->name }}</p>
    @empty
        <p>受講者はいません。</p>
    @endforelse

    <a href="{{ url('/courses') }}">授業一覧に戻る</a>
    <h2>レビュー一覧</h2>

    @forelse ($course->reviews as $review)
    <div>
        <p>投稿者：{{ $review->user->name }}</p>
        <p>{{ $review->body }}</p>

        @if ($review->user_id === auth()->id())
            <form method="POST" action="{{ url('/reviews/' . $review->id) }}">
                @csrf
                @method('DELETE')

                <button type="submit">削除</button>
            </form>
        @endif
    </div>
@empty
    <p>まだレビューはありません。</p>
@endforelse
    <h2>レビューを投稿する</h2>

    <form method="POST" action="{{ url('/courses/' . $course->id . '/reviews') }}">
        @csrf

        <label for="body">レビュー本文</label>
        <textarea id="body" name="body" rows="5">{{ old('body') }}</textarea>

        @error('body')
            <p>{{ $message }}</p>
        @enderror

        <button type="submit">投稿する</button>
    </form>
</body>
</html>
