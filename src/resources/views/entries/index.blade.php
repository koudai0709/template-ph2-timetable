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
                <th>教室</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($entries as $entry)
                <tr>
                    <td>{{ $entry->day }}</td>
                    <td>{{ $entry->period }}</td>
                    <td>{{ $entry->title }}</td>
                    <td>{{ $entry->room }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif