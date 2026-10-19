
<?php
session_start();

$timeout = 15 * 60; // 15 minutes

if (!isset($_SESSION["student_id"])) {
    header("Location: login.html");
    exit();
}

if (
    isset($_SESSION["last_activity"]) &&
    (time() - $_SESSION["last_activity"]) > $timeout
) {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            "",
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    header("Location: login.html");
    exit();
}

$_SESSION["last_activity"] = time();
?>
 