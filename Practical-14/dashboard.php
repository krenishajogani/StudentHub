<?php
require_once "auth.php";
$role = (string) ($_SESSION["student_role"] ?? "student");
if ($role === "admin") {
    header("Location: admin_dashboard.php");
    exit;
}
$menus = [
    "admin" => [
        ["admin_dashboard.php", "Admin Dashboard"],
        ["students.php", "Manage Students"],
        ["events.php", "Manage Events"],
    ],
    "student" => [
        ["dashboard.php", "Dashboard"],
        ["studentstatus.html", "Student Status"],
        ["contact.html", "Contact Us"],
    ],
];
$menuItems = $menus[$role] ?? $menus["student"];
?> 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>StudentHub Dashboard</title>

    <link rel="stylesheet" href="style.css">
</head>


<body>

    <!-- Header -->

    <header class="header">

    <div class="logo-section">

        <div class="logo">
            <img src="aca.png" width="50" height="50">
            <h2>STUDENTHUB</h2>
        </div>

        <!-- Hamburger -->
        <button id="menuToggle" class="hamburger">☰</button>

        <!-- Hamburger Menu -->
        <div id="hamburgerMenu" class="hamburger-menu">

            <a href="profile.html">
                👤 &nbsp; Profile
            </a>

         <a href="logout.php" class="logout-btn">
    Logout
</a>

            <a href="faq.html">
                ❓ &nbsp; FAQ Questions
            </a>

        </div>

    </div>


    <!-- Navigation -->
    <nav class="nav">
        <?php foreach ($menuItems as [$href, $label]): ?>
            <a href="<?= htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?>"
               class="nav-btn<?= $href === "dashboard.php" ? " active" : "" ?>">
                <?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?>
            </a>
        <?php endforeach; ?>
    </nav>


  <a href="logout.php" class="logout-btn">
    Logout
</a> 

    <!-- Theme -->
    <button id="themeToggle">
        🌙 Dark Mode
    </button>

</header>

     <!-- Notification Popup -->
<div id="notificationBanner" class="notification-popup">

    <div class="notification-icon">
        🔔
    </div>
<div class="notification-buttons"> 
    <div class="notification-message">
        <strong>New Notification</strong>
        <p id="notificationText">
            Your attendance report has been updated.
        </p>  
    </div>  
 
    <button class="notification-view" onclick="viewNotification()">
        View
    </button>

    <button class="notification-view" onclick="viewNotification()">
        View All Notifications 
    </button> 
     </div> 

    <button class="notification-close" onclick="closeNotification()">
        &times;
    </button>  
     

</div>

    <!-- Welcome -->

    <div class="title">

        <h1>
            Welcome to StudentHub Dashboard
        </h1>

        <p>
            Manage your academic information from one place.
        </p>
        <!-- Image / Content Slider -->

<div class="slider">

    <!-- Slide 1 -->
    <div class="slide active">

        <img src="img1.jpg" alt="StudentHub">

    </div>


    <!-- Slide 2 -->
    <div class="slide">

        <img src="img2.jpg" alt="Academic Information">

       

    </div>


    <!-- Slide 3 -->
    <div class="slide">

        <img src="img3.jpg" alt="Student Profile">

    </div>


    <!-- Buttons -->

    <button class="prev" onclick="changeSlide(-1)">
        ❮
    </button>

    <button class="next" onclick="changeSlide(1)">
        ❯
    </button>


    <!-- Dots -->

    <div class="dots">

        <span class="dot active-dot" onclick="currentSlide(1)"></span>

        <span class="dot" onclick="currentSlide(2)"></span>

        <span class="dot" onclick="currentSlide(3)"></span>

    </div>

</div>

    </div>


    <!-- Dashboard Cards -->

    <div class="card-container">


        <!-- Student Status Card -->

        <div class="card">

            <h3>
                👤 Student Status
            </h3>

            <p>
                View your profile, attendance,
                results, fees and more.
            </p>

            <br>

            <a href="studentstatus.html"
            class="open-btn">

                Open

            </a>

        </div>


        <!-- Contact Us Card -->

        <div class="card">

            <h3>
                📞 Contact Us
            </h3>

            <p>
                Contact the college
                administration.
            </p>

            <br>

            <a href="contact.html"
            class="open-btn">

                Open

            </a>

        </div>


        <!-- Logout Card -->

        <div class="card">

            <h3>
                🚪 Logout
            </h3>

            <p>
                Sign out from your
                StudentHub account.
            </p>

            <br>

            <a href="logout.html"
            class="logout-btn">

                Logout

            </a>

        </div>


    </div>


    <!-- Footer -->

    <div class="footer">

        <br>

        <p>
            © 2026 StudentHub.All Rights Reserved
        </p>

    </div>
   <script src="script.js"></script>
</body>

</html>