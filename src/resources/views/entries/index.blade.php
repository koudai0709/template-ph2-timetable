<h1>{{ auth()->user()->name }}さんの時間割</h1>

<form method="POST" action="{{ url('/logout') }}">
    @csrf
    <button type="submit">ログアウト</button>
</form>

@if ($entries->isEmpty())
    <p>まだ授業が登録されていません</p>
@else
    <table>
        <thead>
            <tr>
                <th>曜日</th>
                <th>時限</th>
                <th>授業名</th>
                <th>担当教員</th>
                <th>教室</th>
                <th>受講者数 / 定員</th>
                <th>申請状態</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($entries as $entry)
                <tr>
                    <td>{{ $entry->course->day }}</td>
                    <td>{{ $entry->course->period }}</td>
                    <td>
                        <a href="{{ url('/courses/' . $entry->course->id) }}">
                            {{ $entry->course->name }}
                        </a>
                    </td>
                    <td>{{ $entry->course->teacher }}</td>
                    <td>{{ $entry->course->room }}</td>
                    <td>{{ $entry->course->students()->count() }} / {{ $entry->course->capacity }}人</td>

                    <td>
                        @if ($entry->status === 'pending')
                            申請中
                        @elseif ($entry->status === 'approved')
                            承認済み
                        @elseif ($entry->status === 'rejected')
                            却下
                        @endif
                    </td>
                    
                    <td>
                        @if ($entry->status === 'pending')
                            <form method="POST" action="{{ url('/entries/' . $entry->id) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit">申請を取り消す</button>
                            </form>
                        @else
                            取り消し不可
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
