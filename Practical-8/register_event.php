<?php

require "db.php";

$student_id = 1;
$event_id = 1;

$sql = "INSERT INTO registrations
        (student_id, event_id)
        VALUES (?, ?)";

$stmt = $conn->prepare($sql);

$stmt->execute([
    $student_id,
    $event_id
]);

echo "Event registration successful!";

?>  