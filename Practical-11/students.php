<?php
require_once 'auth.php';
require_once 'db_mysqli.php';

$search = trim($_GET['search'] ?? '');
$course = trim($_GET['course'] ?? '');

// Available courses for the dropdown
$courses = ['CSE', 'CSE(AI/ML)', 'CE', 'IT'];

// Search students
$sql = "SELECT student_id, name, email, username, course, semester
        FROM students
        WHERE (name LIKE ? OR email LIKE ? OR username LIKE ?)";

if ($course !== '') {
    $sql .= " AND course = ?";
}

$sql .= " ORDER BY student_id DESC";

$stmt = $conn->prepare($sql);

$searchValue = "%{$search}%";

if ($course !== '') {
    $stmt->bind_param(
        "ssss",
        $searchValue,
        $searchValue,
        $searchValue,
        $course
    );
} else {
    $stmt->bind_param(
        "sss",
        $searchValue,
        $searchValue,
        $searchValue
    );
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>StudentHub - Manage Students</title>

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

    <div class="crud-container">

        <h1>Manage Students</h1>

        <p class="crud-subtitle">
            Manage student records from one place.
        </p>

        <section class="crud-card">

            <div class="student-toolbar">

                <h2>Student Records</h2>

                <a href="add_student.php" class="crud-btn">
                    + Add New Student
                </a>

            </div>


            <?php

            $messages = [
                'added' => [
                    'Student added successfully!',
                    'success'
                ],

                'updated' => [
                    'Student updated successfully!',
                    'success'
                ],

                'deleted' => [
                    'Student deleted successfully!',
                    'success'
                ],

                'invalid' => [
                    'Invalid student ID.',
                    'error'
                ],

                'notfound' => [
                    'Student not found.',
                    'error'
                ],

                'deletefailed' => [
                    'Unable to delete student.',
                    'error'
                ]
            ];

            foreach ($messages as $key => $data) {

                if (isset($_GET[$key])) {

                    echo '<p class="crud-message crud-' .
                         $data[1] . '">' .
                         htmlspecialchars($data[0]) .
                         '</p>';

                    break;
                }
            }

            ?>


            <!-- Search and Course Filter -->

            <form method="GET"
                  action="students.php"
                  class="crud-search-form">

                <input
                    type="search"
                    name="search"
                    placeholder="Search name, email or username"
                    value="<?= htmlspecialchars($search) ?>"
                >

                <select name="course">

                    <option value=""
                        <?= $course === '' ? 'selected' : '' ?>>
                        All Courses
                    </option>

                    <?php foreach ($courses as $courseOption) { ?>

                        <option
                            value="<?= htmlspecialchars($courseOption) ?>"
                            <?= $course === $courseOption ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($courseOption) ?>
                        </option>

                    <?php } ?>

                </select>

                <button type="submit">
                    Search / Filter
                </button>

                <a href="students.php">
                    Reset
                </a>

            </form>


            <!-- Student Records Table -->

            <div class="crud-table-wrapper">

                <table>

                    <thead>

                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Username</th>
                            <th>Course</th>
                            <th>Semester</th>
                            <th>Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($result->num_rows > 0) { ?>

                        <?php while ($student = $result->fetch_assoc()) { ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars(
                                        (string)$student['student_id']
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $student['name'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $student['email'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $student['username'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $student['course'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        (string)($student['semester'] ?? '')
                                    ) ?>
                                </td>

                                <td>

                                    <div class="crud-actions">

                                        <a
                                            class="crud-btn small-btn"
                                            href="edit_student.php?id=<?= (int)$student['student_id'] ?>"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            action="delete_student.php"
                                            onsubmit="return confirm('Are you sure you want to delete this student?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="student_id"
                                                value="<?= (int)$student['student_id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="delete-btn small-btn"
                                            >
                                                Delete
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php } ?>

                    <?php } else { ?>

                        <tr>

                            <td colspan="7" class="empty-row">
                                No students found.
                            </td>

                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

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

$stmt->close();
$conn->close();

?> 