<?php
declare(strict_types=1);

function write_admin_audit(mysqli $conn, string $action, string $details): void
{
    $adminId = (int) ($_SESSION["student_id"] ?? 0);
    $adminName = (string) ($_SESSION["student_name"] ?? "Administrator");
    $statement = $conn->prepare(
        "INSERT INTO admin_audit_log (admin_id, admin_name, action, details)
         VALUES (?, ?, ?, ?)"
    );
    $statement->bind_param("isss", $adminId, $adminName, $action, $details);
    $statement->execute();
    $statement->close();
}
