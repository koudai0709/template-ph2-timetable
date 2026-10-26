<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} | POSSE大学</title>
</head>
<body>
    <main>
        <!-- 区画1：月曜日の授業 -->
        <section>
            <header>
                <h2><span>区画1</span>月曜日の授業</h2>
                <p>時限の早い順に並べています。</p>
            </header>
            <div>
                <table>
                    <thead>
                        <tr>
                            <th scope="col">時限</th>
                            <th scope="col">授業名</th>
                            <th scope="col">担当教員</th>
                            <th scope="col">教室</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($courses as $course)
                            <tr>
                                <td>{{ $course->period }}</td>
                                <td>{{ $course->name }}</td>
                                <td>{{ $course->teacher }}</td>
                                <td>{{ $course->room }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <!-- 区画2：必修科目 -->
        <section>
            <header>
                <h2><span>区画2</span>必修科目</h2>
                <p>科目コードの順に並べています。</p>
            </header>
            <div>
                <table>
                    <thead>
                        <tr>
                            <th scope="col">授業名</th>
                            <th scope="col">単位数</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requiredCourses as $course)
                            <tr>
                                <td>{{ $course->name }}</td>
                                <td>{{ $course->credits }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
