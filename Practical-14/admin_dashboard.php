<?php
declare(strict_types=1);

require_once __DIR__ . "/admin_auth.php";
require_once __DIR__ . "/db_mysqli.php";
require_once __DIR__ . "/audit_log.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$adminId = (int) $_SESSION["student_id"];
$adminName = (string) ($_SESSION["student_name"] ?? "Administrator");
$role = (string) ($_SESSION["student_role"] ?? "");

if ($role !== "admin") {
    http_response_code(403);
    exit("Access denied. Administrator access is required.");
}

function dashboard_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

try {
    $auditCheck = $conn->query("SHOW TABLES LIKE 'admin_audit_log'");
    if ($auditCheck->num_rows === 0) {
        http_response_code(503);
        exit("Audit logging is not configured. Run practical14_migration.sql in the studenthub database.");
    }
    $auditCheck->free();

    $counts = $conn->query(
        "SELECT
            (SELECT COUNT(*) FROM students WHERE role = 'student') AS student_count,
            (SELECT COUNT(*) FROM events) AS event_count,
            (SELECT COUNT(*) FROM registrations) AS registration_count"
    )->fetch_assoc();

    $registrationColumns = [];
    $columnResult = $conn->query("SHOW COLUMNS FROM registrations");
    while ($column = $columnResult->fetch_assoc()) {
        $registrationColumns[] = $column["Field"];
    }
    $columnResult->free();

    $registrationDateColumn = null;
    foreach (["registered_at", "registration_date", "created_at"] as $candidateDateColumn) {
        if (in_array($candidateDateColumn, $registrationColumns, true)) {
            $registrationDateColumn = $candidateDateColumn;
            break;
        }
    }
    if ($registrationDateColumn === null) {
        http_response_code(503);
        exit("Registration dates are not configured. Run practical14_migration.sql in the studenthub database.");
    }

    $registrationOrder = "r." . $registrationDateColumn . " DESC";
    foreach (["registration_id", "id"] as $candidateIdColumn) {
        if (in_array($candidateIdColumn, $registrationColumns, true)) {
            $registrationOrder .= ", r." . $candidateIdColumn . " DESC";
            break;
        }
    }

    $latestRegistrations = $conn->query(
        "SELECT r." . $registrationDateColumn . " AS registered_at,
                s.name AS student_name, s.email AS student_email,
                e.event_name
         FROM registrations r
         LEFT JOIN students s ON s.student_id = r.student_id
         LEFT JOIN events e ON e.event_id = r.event_id
         ORDER BY " . $registrationOrder . "
         LIMIT 10"
    )->fetch_all(MYSQLI_ASSOC);

    write_admin_audit($conn, "VIEW_DASHBOARD", "Admin opened the dashboard");

    $auditLogs = $conn->query(
        "SELECT admin_name, action, details, created_at
         FROM admin_audit_log
         ORDER BY created_at DESC, audit_id DESC
         LIMIT 10"
    )->fetch_all(MYSQLI_ASSOC);
} catch (mysqli_sql_exception $exception) {
    error_log("Admin dashboard query failed: " . $exception->getMessage());
    http_response_code(503);
    exit("Dashboard data could not be loaded. Check the database setup and run practical14_migration.sql.");
}

$adminMenus = [
    "admin" => [
        ["students.php", "Manage Students"],
        ["events.php", "Manage Events"],
    ],
];
$menuItems = $adminMenus[$role] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub | Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-dashboard { max-width: 1180px; margin: 42px auto; padding: 24px; }
        .admin-dashboard h1 { margin-bottom: 8px; }
        .admin-role { display: inline-block; padding: 5px 10px; border-radius: 20px; background: #e5f4ee; color: #10503f; font-size: 13px; font-weight: 700; text-transform: capitalize; }
        .admin-header { height: auto; min-height: 108px; flex-direction: column; align-items: stretch; gap: 4px; padding: 12px 30px; }
        .admin-header .logo { flex-shrink: 0; }
        .admin-header-actions { display: flex; align-items: center; justify-content: flex-end; gap: 24px; width: 100%; margin: 0; }
        .admin-header nav.admin-links { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 24px; margin: 0; }
        .admin-links a,
        .admin-header #themeToggle { display: inline-block; padding: 8px 12px; border: 0; border-radius: 0; background: transparent; color: #1f4fb5; font-size: 18px; text-decoration: none; white-space: nowrap; cursor: pointer; }
        .admin-links a:hover,
        .admin-header #themeToggle:hover { background: transparent; color: #12377f; text-decoration: underline; }
        .admin-header .logout-btn { display: inline-block; margin: 0; padding: 8px 18px; border: 1px solid red; border-radius: 8px; background: #fff; color: red; text-decoration: none; transform: none; }
        .admin-header .logout-btn:hover { background: #fff5f5; color: #c00; transform: none; }
        .admin-header #themeToggle { padding: 2px 6px; border: 1px solid #888; border-radius: 2px; background: #f2f2f2; color: #111; font-size: 16px; line-height: normal; text-decoration: none; }
        .admin-header #themeToggle:hover { background: #e6e6e6; color: #111; text-decoration: none; }
        .dashboard-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin: 24px 0; }
        .stat-card, .dashboard-section { padding: 20px; border: 1px solid #d8e7ed; border-radius: 12px; background: #fff; box-shadow: 0 4px 14px rgba(0, 0, 0, .07); }
        .stat-card h2 { margin: 0 0 8px; color: #526171; font-size: 15px; }
        .stat-number { margin: 0; color: #176b55; font-size: 32px; font-weight: 700; }
        .dashboard-section { margin-top: 20px; }
        .dashboard-section h2 { margin-top: 0; }
        .dashboard-table-wrap { overflow-x: auto; }
        .admin-dashboard table { width: 100%; border-collapse: collapse; }
        .admin-dashboard th, .admin-dashboard td { padding: 11px 10px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
        .admin-dashboard th { background: #f2f6f8; color: #243746; }
        .dashboard-empty { color: #64748b; text-align: center !important; }
        body.dark-mode .admin-dashboard { color: #f3f4f6; }
        body.dark-mode .admin-dashboard .stat-card,
        body.dark-mode .admin-dashboard .dashboard-section { border-color: #374151; background: #1f2937; }
        body.dark-mode .admin-dashboard .stat-card h2 { color: #d1d5db; }
        body.dark-mode .admin-dashboard .admin-role { background: #164e3b; color: #d1fae5; }
        body.dark-mode .admin-dashboard th { background: #374151; color: #f3f4f6; }
        body.dark-mode .admin-dashboard td { color: #f3f4f6; }
        body.dark-mode .admin-dashboard td,
        body.dark-mode .admin-dashboard th { border-color: #4b5563; }
        @media (max-width: 700px) {
            .admin-header { height: auto; padding: 14px 16px; gap: 8px; }
            .admin-header .logo h2 { font-size: 17px; }
            .admin-header-actions { flex-wrap: wrap; justify-content: flex-end; gap: 8px; }
            .admin-header nav.admin-links { gap: 8px; }
            .admin-links a,
            .admin-header #themeToggle { padding: 2px 6px; font-size: 15px; }
            .admin-header .logout-btn { padding: 7px 12px; }
            .dashboard-stats { grid-template-columns: 1fr; }
            .admin-dashboard { margin: 24px auto; padding: 16px; }
        }
    </style>
</head>
<body>
    <header class="header admin-header">
        <div class="logo"><img src="aca.png" width="50" height="50" alt="StudentHub Logo"><h2>STUDENTHUB ADMIN</h2></div>
        <div class="admin-header-actions">
            <nav class="admin-links" aria-label="Admin tools">
                <?php foreach ($menuItems as [$href, $label]): ?>
                    <a href="<?= dashboard_escape($href) ?>"><?= dashboard_escape($label) ?></a>
                <?php endforeach; ?>
            </nav>
            <a class="logout-btn" href="logout.php">Logout</a>
            <button id="themeToggle" type="button">🌙 Dark Mode</button>
        </div>
    </header>
    <main class="admin-dashboard">
        <h1>Admin Dashboard</h1>
        <p>Welcome, <?= dashboard_escape($adminName) ?>. Manage portal records from here.</p>
        <span class="admin-role">Role: <?= dashboard_escape($role) ?></span>

        <section class="dashboard-stats" aria-label="Portal statistics">
            <article class="stat-card"><h2>Total Students</h2><p class="stat-number"><?= (int) $counts["student_count"] ?></p></article>
            <article class="stat-card"><h2>Total Events</h2><p class="stat-number"><?= (int) $counts["event_count"] ?></p></article>
            <article class="stat-card"><h2>Total Registrations</h2><p class="stat-number"><?= (int) $counts["registration_count"] ?></p></article>
        </section>

        <section class="dashboard-section">
            <h2>Latest Registrations</h2>
            <div class="dashboard-table-wrap">
                <table>
                    <thead><tr><th>Student</th><th>Email</th><th>Event</th><th>Registered At</th></tr></thead>
                    <tbody>
                    <?php if ($latestRegistrations === []): ?>
                        <tr><td class="dashboard-empty" colspan="4">No registrations yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($latestRegistrations as $registration): ?>
                            <tr>
                                <td><?= dashboard_escape((string) ($registration["student_name"] ?? "Unknown student")) ?></td>
                                <td><?= dashboard_escape((string) ($registration["student_email"] ?? "")) ?></td>
                                <td><?= dashboard_escape((string) ($registration["event_name"] ?? "Deleted event")) ?></td>
                                <td><?= dashboard_escape((string) $registration["registered_at"]) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="dashboard-section">
            <h2>Recent Audit Log</h2>
            <div class="dashboard-table-wrap">
                <table>
                    <thead><tr><th>Admin</th><th>Action</th><th>Details</th><th>Date and Time</th></tr></thead>
                    <tbody>
                    <?php if ($auditLogs === []): ?>
                        <tr><td class="dashboard-empty" colspan="4">No audit records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($auditLogs as $audit): ?>
                            <tr>
                                <td><?= dashboard_escape((string) $audit["admin_name"]) ?></td>
                                <td><?= dashboard_escape((string) $audit["action"]) ?></td>
                                <td><?= dashboard_escape((string) $audit["details"]) ?></td>
                                <td><?= dashboard_escape((string) $audit["created_at"]) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <script src="script.js"></script>
</body>
</html>
