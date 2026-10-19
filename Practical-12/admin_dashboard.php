<?php
require_once __DIR__ . "/admin_auth.php";
$adminName = htmlspecialchars($_SESSION["student_name"] ?? "Administrator", ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub | Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-dashboard { max-width: 1100px; margin: 56px auto; padding: 24px; }
        .admin-dashboard h1 { margin-bottom: 8px; }
        .admin-dashboard .admin-links { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 28px; }
        .admin-dashboard .admin-links a { display: inline-block; padding: 14px 18px; border-radius: 6px; background: #176b55; color: #fff; text-decoration: none; }
        .admin-dashboard .admin-links a:hover { background: #10503f; }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo"><img src="aca.png" width="50" height="50" alt="StudentHub Logo"><h2>STUDENTHUB ADMIN</h2></div>
        <a class="logout-btn" href="logout.php">Logout</a>
    </header>
    <main class="admin-dashboard">
        <h1>Admin Dashboard</h1>
        <p>Welcome, <?= $adminName ?>. Manage portal records from here.</p>
        <nav class="admin-links" aria-label="Admin tools">
            <a href="students.php">Manage Students</a>
            <a href="events.php">Manage Events</a>
            <a href="studentstatus.html">Student Status</a>
        </nav>
    </main>
    <script src="script.js"></script>
</body>
</html>