<?php

require_once 'auth.php';
require_once 'db_mysqli.php';

$message = '';
$messageType = '';

$name = '';
$email = '';
$username = '';
$course = '';
$semester = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $semester = trim($_POST['semester'] ?? '');
    $password = $_POST['password'] ?? '';

    if (
        $name === '' ||
        $email === '' ||
        $username === '' ||
        $course === '' ||
        $semester === '' ||
        $password === ''
    ) {

        $message = 'Please fill in all fields.';
        $messageType = 'error';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = 'Please enter a valid email address.';
        $messageType = 'error';

    } elseif (
        !filter_var($semester, FILTER_VALIDATE_INT) ||
        (int)$semester < 1 ||
        (int)$semester > 12
    ) {

        $message = 'Please enter a semester between 1 and 12.';
        $messageType = 'error';

    } else {

        // Check duplicate email or username

        $check = $conn->prepare(
            "SELECT student_id
             FROM students
             WHERE email = ? OR username = ?"
        );

        $check->bind_param(
            "ss",
            $email,
            $username
        );

        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {

            $message = 'Email or username already exists.';
            $messageType = 'error';

            $check->close();

        } else {

            $check->close();

            // Hash password

            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $semesterValue = (int)$semester;

            // Insert student

            $stmt = $conn->prepare(
                "INSERT INTO students
                (name, email, username, password, course, semester)
                VALUES (?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "sssssi",
                $name,
                $email,
                $username,
                $hashedPassword,
                $course,
                $semesterValue
            );

            if ($stmt->execute()) {

                $stmt->close();
                $conn->close();

                header("Location: students.php?added=1");
                exit;

            } else {

                $message = 'Unable to add student.';
                $messageType = 'error';

            }

            $stmt->close();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>StudentHub - Add Student</title>

    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="studenthub-crud.css">

</head>

<body>

<header class="header">

    <div class="logo-section">

        <div class="logo">

            <img src="aca.png"
                 width="50"
                 height="50"
                 alt="StudentHub Logo">

            <h2>STUDENTHUB</h2>

        </div>

        <button id="menuToggle"
                class="hamburger"
                type="button">
            ☰
        </button>

        <div id="hamburgerMenu" class="hamburger-menu">

            <a href="profile.html">👤 Profile</a>
            <a href="logout.php">🚪 Logout</a>
            <a href="faq.html">❓ FAQ Questions</a>

        </div>

    </div>

    <nav class="nav">

        <a href="dashboard.php" class="nav-btn">
            Dashboard
        </a>

        <a href="studentstatus.html" class="nav-btn">
            Student Status
        </a>

        <a href="contact.html" class="nav-btn">
            Contact Us
        </a>

        <a href="students.php" class="nav-btn active">
            Manage Students
        </a>

    </nav>

    <a href="logout.php" class="logout-btn">
        Logout
    </a>

    <button id="themeToggle" type="button">
        🌙 Dark Mode
    </button>

</header>


<main class="crud-page">

    <div class="crud-container form-container">

        <h1>Add New Student</h1>

        <p class="crud-subtitle">
            Enter student details to register a new student.
        </p>

        <section class="crud-card form-card">

            <?php if ($message !== '') { ?>

                <p class="crud-message crud-<?= htmlspecialchars($messageType) ?>">

                    <?= htmlspecialchars($message) ?>

                </p>

            <?php } ?>


            <form method="POST"
                  action="add_student.php"
                  class="student-form">

                <label for="name">
                    Full Name
                </label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    value="<?= htmlspecialchars($name) ?>"
                    required
                >


                <label for="email">
                    Email
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= htmlspecialchars($email) ?>"
                    required
                >


                <label for="username">
                    Username
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?= htmlspecialchars($username) ?>"
                    required
                >


                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    required
                >


                <label for="course">
                    Course
                </label>

                <input
                    type="text"
                    id="course"
                    name="course"
                    value="<?= htmlspecialchars($course) ?>"
                    required
                >


                <label for="semester">
                    Semester
                </label>

                <input
                    type="number"
                    id="semester"
                    name="semester"
                    min="1"
                    max="12"
                    value="<?= htmlspecialchars($semester) ?>"
                    required
                >


                <div class="form-buttons">

                    <button type="submit" class="crud-btn">
                        Add Student
                    </button>

                    <a href="students.php" class="secondary-btn">
                        Back to Students
                    </a>

                </div>

            </form>

        </section>

    </div>

</main>


<footer class="footer">

    <p>
        © 2026 StudentHub. All Rights Reserved.
    </p>

</footer>

<script src="script.js"></script>

</body>
</html>

<?php

$conn->close();

?> 