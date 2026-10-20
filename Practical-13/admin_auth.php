<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/db_mysqli.php";

$roleColumn = $conn->query("SHOW COLUMNS FROM students LIKE 'role'");
if ($roleColumn->num_rows === 0) {
    http_response_code(503);
    exit("Admin roles are not configured. Run the Practical-12 migration first.");
}

$studentId = (int) ($_SESSION["student_id"] ?? 0);
$stmt = $conn->prepare("SELECT role FROM students WHERE student_id = ? LIMIT 1");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$account || $account["role"] !== "admin") {
    http_response_code(403);
    exit("Access denied. Administrator access is required.");
}

$_SESSION["student_role"] = "admin";