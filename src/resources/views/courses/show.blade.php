<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>授業詳細</title>
</head>
<body>
    <h1>{{ $course->name }}</h1>
    @if (session('success'))
    <p role="status">{{ session('success') }}</p>
@endif

@can('post-announcements')
    <section style="max-width: 24rem; margin-left: auto;">
        <h2>お知らせを投稿する</h2>

        <form method="POST" action="{{ url('/courses/' . $course->id . '/announcements') }}">
            @csrf

            <label for="announcement-title">タイトル</label>
            <input id="announcement-title" name="title" value="{{ old('title') }}" maxlength="50" required>
            @error('title')
                <p>{{ $message }}</p>
            @enderror

            <label for="announcement-body" style="display: block;">本文</label>
            <textarea id="announcement-body" name="body" required>{{ old('body') }}</textarea>
            @error('body')
                <p>{{ $message }}</p>
            @enderror

            <div style="text-align: right;">
                <button type="submit">投稿する</button>
            </div>
        </form>
    </section>
@endcan

<h2>授業のお知らせ</h2>

@forelse ($course->announcements as $announcement)
    <article>
        <h3>{{ $announcement->title }}</h3>
        <p>投稿日時 {{ $announcement->created_at->format('Y/m/d H:i') }}</p>
        <p style="white-space: pre-wrap;">{{ $announcement->body }}</p>
    </article>
@empty
    <p>お知らせはまだありません。</p>
@endforelse
    <p>担当教員：{{ $course->teacher }}</p>
    <p>曜日：{{ ['月', '火', '水', '木', '金'][$course->day - 1] }}</p>
    <p>時限：{{ $course->period }}</p>
    <p>教室：{{ $course->room }}</p>

    @can('download-course-material', $course)
        <section>
            <h2>PDF教材</h2>
            @if ($course->material_path)
                <a href="{{ route('courses.material.download', $course) }}">PDFをダウンロード</a>
            @else
                <p>PDF教材は未登録です。</p>
            @endif

            @can('upload-course-material')
                <form method="POST" action="{{ route('courses.material.store', $course) }}" enctype="multipart/form-data">
                    @csrf
                    <label for="material">PDF教材（5MB以下）</label>
                    <input type="file" id="material" name="material" accept=".pdf,application/pdf" required>
                    @error('material')
                        <p>{{ $message }}</p>
                    @enderror
                    <button type="submit">{{ $course->material_path ? '差し替える' : 'アップロードする' }}</button>
                </form>
            @endcan
        </section>
    @endcan

    <h2>タグ</h2>

    @forelse ($course->tags as $tag)
        <span>{{ $tag->name }}</span>
    @empty
        <p>タグはありません。</p>
    @endforelse

    <h2>受講者情報</h2>

    <p>受講者数：{{ $course->students->count() }} / {{ $course->capacity }}人</p>

    @if (\Illuminate\Support\Facades\Route::has('courses.roster.download'))
        @can('manage-course-grades')
            <form method="GET" action="{{ route('courses.roster.download', $course) }}">
                <button type="submit">履修者名簿CSVをダウンロード</button>
            </form>

            <form method="POST" action="{{ route('courses.grades.import', $course) }}" enctype="multipart/form-data">
                @csrf
                <label for="grade_csv">成績CSV（5MB以下）</label>
                <input type="file" id="grade_csv" name="grade_csv" accept=".csv,text/csv" required>
                <button type="submit">成績を登録する</button>
            </form>
            <p>Excelでは「CSV UTF-8（コンマ区切り）」で保存してください。</p>
            <p>登録対象外の学生の行はCSVから削除してください。不正な行が1行でもあれば、全件更新しません。</p>
            @if (session()->has('grade_import_count'))
                <p role="status">成績を{{ session('grade_import_count') }}件登録しました。</p>
            @endif
            @if ($errors->gradeImport->any())
                <ul role="alert">
                    @foreach ($errors->gradeImport->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        @endcan
    @endif

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
