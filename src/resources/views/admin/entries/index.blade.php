<h1>申請の承認管理</h1>

@if ($entries->isEmpty())
    <p>申請中の授業はありません。</p>
@else
    <table>
        <thead>
            <tr>
                <th>学生名</th>
                <th>授業名</th>
                <th>申請日時</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($entries as $entry)
                <tr>
                    <td>{{ $entry->user->name }}</td>
                    <td>{{ $entry->course->name }}</td>
                    <td>{{ $entry->created_at }}</td>
                    
                    <td>
                        <form method="POST" action="{{ url('/admin/entries/' . $entry->id . '/approve') }}">
                            @csrf
                            <button type="submit">承認</button>
                        </form>

                        <form method="POST" action="{{ url('/admin/entries/' . $entry->id . '/reject') }}">
                            @csrf
                            <button type="submit">却下</button>
                        </form>
                    </td>

                </tr>
            @endforeach
        </tbody>
    </table>
@endif
