
<?php
session_start();

require_once "db_mysqli.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.html");
    exit();
}

$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

if ($email === "" || $password === "") {
    die("Please enter both email and password. <a href='login.html'>Try again</a>");
}

$sql = "SELECT student_id, name, email, password
        FROM students
        WHERE email = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Something went wrong. Please try again later.");
}

$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();
$student = $result->fetch_assoc();

if ($student && password_verify($password, $student["password"])) {

    // Create a new session ID after successful login
    session_regenerate_id(true);

    $_SESSION["student_id"] = $student["student_id"];
    $_SESSION["student_name"] = $student["name"];
    $_SESSION["student_email"] = $student["email"];
    $_SESSION["last_activity"] = time();

    header("Location: dashboard.php");
    exit();

} else {
    echo "<script>
        alert('Invalid email or password. Please try again.');
        window.location.href = 'login.html';
    </script>";
    exit();
} 

$stmt->close();
$conn->close();
?>
