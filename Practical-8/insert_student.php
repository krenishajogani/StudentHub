<?php

require "db.php";

$name = "Krenisha";
$email = "krenisha@gmail.com";
$password = "12345";
$course = "CSE";
$semester = 3;

$sql = "INSERT INTO students
        (name, email, password, course, semester)
        VALUES (?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

$stmt->execute([
    $name,
    $email,
    $password,
    $course,
    $semester
]);

echo "Student inserted successfully!";

?> 