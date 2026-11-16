<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>授業一覧</title>
</head>
<body>
    <h1>授業一覧</h1>
    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif
    <form method="GET" action="{{ url('/courses') }}">
    <label for="q">授業名・担当教員名</label>

    <input
        type="search"
        id="q"
        name="q"
        value="{{ $keyword }}"
        placeholder="キーワードを入力"
    >
    <label for="tag">タグ</label>

    <select name="tag" id="tag">
        <option value="">すべてのタグ</option>

        @foreach ($tags as $tag)
            <option value="{{ $tag->id }}"
                @selected((string) $tagId === (string) $tag->id)>
                {{ $tag->name }}
            </option>
        @endforeach
    </select>
    <button type="submit">検索</button>
    <a href="{{ url('/courses') }}">検索を解除</a>
</form>

@if ($courses->isEmpty())
    <p>該当する授業はありません。</p>
@endif
    <table>
        <thead>
            <tr>
                <th>曜日</th>
                <th>時限</th>
                <th>授業名</th>
                <th>担当教員</th>
                <th>教室</th>
                <th>受講者数 / 定員</th>
                <th>タグ</th>
                <th>気になる☆</th>
                <th>登録状況</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($courses as $course)
                <tr>
                    <td>{{ ['月', '火', '水', '木', '金'][$course->day - 1] }}</td>
                    <td>{{ $course->period }}</td>
                    <td>
                        <a href="{{ url('/courses/' . $course->id) }}">
                            {{ $course->name }}
                        </a>
                    </td>
                    <td>{{ $course->teacher }}</td>
                    <td>{{ $course->room }}</td>
                    <td>{{ $course->students()->count() }} / {{ $course->capacity }}人</td>
                    <td>
                        @foreach ($course->tags as $tag)
                            <a href="{{ url('/courses') . '?' . http_build_query(['q' => $keyword, 'tag' => $tag->id]) }}">
                                {{ $tag->name }}
                            </a>
                        @endforeach
                    </td>

                    <td>
                        <form method="POST" action="{{ route('courses.favorite', $course) }}">
                            @csrf

                            <button type="submit">
                                {{ $course->favoritedByUsers->isNotEmpty() ? '★' : '☆' }}
                            </button>

                            <span>{{ $course->favorited_by_users_count }}人</span>
                        </form>
                    </td>
                    <td>
                        @if ($course->entries->isEmpty())
                            <form method="POST" action="{{ url('/entries') }}">
                                @csrf
                                <input type="hidden" name="course_id" value="{{ $course->id }}">
                                <button type="submit">申請する</button>
                            </form>
                        @else
                            @switch($course->entries->first()->status)
                                @case('pending')
                                    申請中
                                    @break
                                @case('approved')
                                    承認済み
                                    @break
                                @case('rejected')
                                    却下
                                    @break
                            @endswitch
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
