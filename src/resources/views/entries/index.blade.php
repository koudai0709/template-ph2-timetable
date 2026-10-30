
<h1>自分の時間割</h1>

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
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($entries as $entry)
                <tr>
                    <td>{{ $entry->course->day }}</td>
                    <td>{{ $entry->course->period }}</td>
                    <td>{{ $entry->course->name }}</td>
                    <td>{{ $entry->course->teacher }}</td>
                    <td>{{ $entry->course->room }}</td>
                    <td>
                        <form method="POST" action="{{ url('/entries/' . $entry->id) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit">削除</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
