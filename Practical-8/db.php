<?php

$host = "localhost";
$dbname = "studenthub";
$username = "root";
$password = "";

try {
    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname",
        $username,
        $password
    );

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Database connected successfully!";

} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}

?> 