-- W2 POSSE課題：index.html の区画7〜11と同じ結果を返す SQL を書く
-- 書いた SQL は db コンテナの psql（docker compose exec db psql -U posse -d timetable）で実行し、
-- 表示が index.html の区画と一致することを確かめてから書くこと
-- 曜日（day）は数字のまま返してよい

-- 区画7：すべての授業と教室の情報（科目コードの順）
SELECT courses.code,courses.name,courses.room,rooms.building,rooms.capacity
FROM courses
LEFT JOIN rooms
ON courses.room=rooms.code
ORDER BY courses.code ASC;

-- 区画8：曜日ごとの授業数（曜日の順）
SELECT courses.day,COUNT(name) AS courses_count
FROM courses
GROUP BY day
ORDER BY day ASC;

-- 区画9：建物ごとの授業数と合計単位（建物名の順）
SELECT rooms.building,COUNT(courses.name) AS course_count,SUM(courses.credits) AS credit_count
FROM courses
INNER JOIN rooms
ON courses.room=rooms.code
GROUP BY rooms.building
ORDER BY rooms.building;

-- 区画10：授業を3つ以上担当している教員（授業数の多い順、同数なら名前の順）
SELECT courses.teacher,COUNT(courses.name)AS name_count
FROM courses
GROUP BY courses.teacher
HAVING COUNT(courses.name)>=3
ORDER BY name_count DESC,courses.teacher ASC;

-- 区画11：授業が1つも入っていない教室（教室コードの順）
SELECT courses.room,rooms.building,rooms.capacity
FROM rooms
LEFT JOIN courses
ON courses.room=rooms.code
WHERE room IS NULL
ORDER BY rooms.code;
