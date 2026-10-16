
<?php
require_once "db_mysqli.php";

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["fullName"] ?? $_POST["name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirmPassword"] ?? $_POST["confirm_password"] ?? "";
    $course = trim($_POST["course"] ?? "");
    $semester = trim($_POST["semester"] ?? "");

    if (
        $name === "" ||
        $username === "" ||
        $email === "" ||
        $password === "" ||
        $confirmPassword === "" ||
        $course === "" ||
        $semester === ""
    ) {
        $message = "Please fill in all fields, including course and semester.";
        $messageType = "error";

    } elseif (
        strlen($name) > 100 ||
        strlen($username) < 3 ||
        strlen($username) > 50 ||
        strlen($email) > 150
    ) {
        $message = "Please check the length of your details.";
        $messageType = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageType = "error";

    } elseif (strlen($password) < 8) {
        $message = "Password must contain at least 8 characters.";
        $messageType = "error";

    } elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match.";
        $messageType = "error";

    } else {

        $check = $conn->prepare(
            "SELECT student_id FROM students
             WHERE email = ? OR username = ?"
        );

        $check->bind_param("ss", $email, $username);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = "Email or username is already registered.";
            $messageType = "error";

        } else {

            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $conn->prepare(
                "INSERT INTO students
                 (name, username, email, password, course, semester)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "ssssss",
                $name,
                $username,
                $email,
                $hashedPassword,
                $course,
                $semester
            );

            if ($stmt->execute()) {
                $stmt->close();
                $check->close();

                header("Location: dashboard.html");
                exit();

            } elseif ($stmt->errno === 1062) {
                $message = "Email or username is already registered.";
                $messageType = "error";

            } else {
                $message = "Registration failed: " . $stmt->error;
                $messageType = "error";
            }

            if (isset($stmt) && $stmt instanceof mysqli_stmt) {
                $stmt->close();
            }
        }

        $check->close();
    }
}
?>
