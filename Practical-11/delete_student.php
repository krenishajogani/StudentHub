<?php

require_once 'auth.php';
require_once 'db_mysqli.php';


// Allow POST request only

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    $conn->close();

    header("Location: students.php");
    exit;
}


// Validate student ID

$id = filter_input(
    INPUT_POST,
    'student_id',
    FILTER_VALIDATE_INT
);

if (!$id || $id <= 0) {

    $conn->close();

    header("Location: students.php?invalid=1");
    exit;
}


// Check whether student exists

$check = $conn->prepare(
    "SELECT student_id
     FROM students
     WHERE student_id = ?"
);

$check->bind_param("i", $id);

$check->execute();

$check->store_result();


if ($check->num_rows === 0) {

    $check->close();
    $conn->close();

    header("Location: students.php?notfound=1");
    exit;
}

$check->close();


// Delete student

$stmt = $conn->prepare(
    "DELETE FROM students
     WHERE student_id = ?"
);

$stmt->bind_param("i", $id);


if ($stmt->execute() && $stmt->affected_rows === 1) {

    $stmt->close();
    $conn->close();

    header("Location: students.php?deleted=1");
    exit;

}


// If deletion fails

$stmt->close();
$conn->close();

header("Location: students.php?deletefailed=1");
exit;

?> 