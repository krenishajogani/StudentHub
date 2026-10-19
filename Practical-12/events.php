<?php
declare(strict_types=1);

require_once __DIR__ . "/admin_auth.php";
require_once __DIR__ . "/db_mysqli.php";

$requiredPosterColumns = ["poster_data", "poster_mime", "poster_name"];
$columnResult = $conn->query("SHOW COLUMNS FROM events");
$eventColumns = [];
while ($column = $columnResult->fetch_assoc()) {
    $eventColumns[] = $column["Field"];
}
if (array_diff($requiredPosterColumns, $eventColumns)) {
    http_response_code(503);
    exit("Poster storage is not configured. Run events_migration.sql in the studenthub database.");
}

const MAX_POSTER_BYTES = 5242880;
const ALLOWED_POSTER_TYPES = ["image/jpeg", "image/png", "image/webp"];

function event_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

function read_event_poster(array $file): ?array
{
    if (($file["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file["error"] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Poster upload failed. Please try again.");
    }

    if (!isset($file["tmp_name"], $file["size"]) || $file["size"] > MAX_POSTER_BYTES) {
        throw new RuntimeException("Poster must be 5 MB or smaller.");
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file["tmp_name"]);
    if (!is_string($mime) || !in_array($mime, ALLOWED_POSTER_TYPES, true)
        || @getimagesize($file["tmp_name"]) === false) {
        throw new RuntimeException("Upload a valid JPEG, PNG, or WebP image.");
    }

    $data = file_get_contents($file["tmp_name"]);
    if ($data === false) {
        throw new RuntimeException("Could not read the uploaded poster.");
    }

    return [
        "data" => $data,
        "mime" => $mime,
        "name" => basename((string) ($file["name"] ?? "poster")),
    ];
}

if (isset($_GET["poster"])) {
    $posterId = filter_input(INPUT_GET, "poster", FILTER_VALIDATE_INT);
    if (!$posterId || $posterId < 1) {
        http_response_code(404);
        exit;
    }

    $posterQuery = $conn->prepare("SELECT poster_data, poster_mime FROM events WHERE event_id = ?");
    $posterQuery->bind_param("i", $posterId);
    $posterQuery->execute();
    $poster = $posterQuery->get_result()->fetch_assoc();
    $posterQuery->close();

    if (!$poster || !$poster["poster_data"] || !in_array($poster["poster_mime"], ALLOWED_POSTER_TYPES, true)) {
        http_response_code(404);
        exit;
    }

    header("Content-Type: " . $poster["poster_mime"]);
    header("X-Content-Type-Options: nosniff");
    header("Cache-Control: private, max-age=3600");
    echo $poster["poster_data"];
    exit;
}

if (!isset($_SESSION["events_csrf"])) {
    $_SESSION["events_csrf"] = bin2hex(random_bytes(32));
}

$errorMessage = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $submittedToken = $_POST["csrf_token"] ?? "";
    if (!is_string($submittedToken) || !hash_equals($_SESSION["events_csrf"], $submittedToken)) {
        http_response_code(400);
        $errorMessage = "Form expired. Refresh the page and try again.";
    } else {
        $action = $_POST["action"] ?? "";
        try {
            if ($action === "delete") {
                $eventId = filter_var($_POST["event_id"] ?? null, FILTER_VALIDATE_INT);
                if (!$eventId || $eventId < 1) {
                    throw new RuntimeException("Invalid event ID.");
                }

                $conn->begin_transaction();
                try {
                    $deleteRegistrations = $conn->prepare("DELETE FROM registrations WHERE event_id = ?");
                    $deleteRegistrations->bind_param("i", $eventId);
                    $deleteRegistrations->execute();
                    $deleteRegistrations->close();

                    $deleteEvent = $conn->prepare("DELETE FROM events WHERE event_id = ?");
                    $deleteEvent->bind_param("i", $eventId);
                    $deleteEvent->execute();
                    $deleted = $deleteEvent->affected_rows;
                    $deleteEvent->close();
                    if ($deleted !== 1) {
                        throw new RuntimeException("Event not found.");
                    }
                    $conn->commit();
                } catch (Throwable $exception) {
                    $conn->rollback();
                    throw $exception;
                }

                header("Location: events.php?status=deleted");
                exit;
            }

            if (!in_array($action, ["create", "update"], true)) {
                throw new RuntimeException("Invalid event action.");
            }

            $eventName = trim((string) ($_POST["event_name"] ?? ""));
            $eventDate = trim((string) ($_POST["event_date"] ?? ""));
            $location = trim((string) ($_POST["location"] ?? ""));
            $description = trim((string) ($_POST["description"] ?? ""));
            $validDate = DateTime::createFromFormat("!Y-m-d", $eventDate);

            if ($eventName === "" || strlen($eventName) > 150) {
                throw new RuntimeException("Event name is required and must be at most 150 characters.");
            }
            if (!$validDate || $validDate->format("Y-m-d") !== $eventDate) {
                throw new RuntimeException("Enter a valid event date.");
            }
            if (strlen($location) > 150) {
                throw new RuntimeException("Location must be at most 150 characters.");
            }

            $uploadedPoster = read_event_poster($_FILES["poster"] ?? []);
            if ($action === "create") {
                $posterData = $uploadedPoster["data"] ?? null;
                $posterMime = $uploadedPoster["mime"] ?? null;
                $posterName = $uploadedPoster["name"] ?? null;
                $insert = $conn->prepare(
                    "INSERT INTO events (event_name, event_date, location, description, poster_data, poster_mime, poster_name)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $insert->bind_param("sssssss", $eventName, $eventDate, $location, $description, $posterData, $posterMime, $posterName);
                $insert->execute();
                $insert->close();
                header("Location: events.php?status=created");
                exit;
            }

            $eventId = filter_var($_POST["event_id"] ?? null, FILTER_VALIDATE_INT);
            if (!$eventId || $eventId < 1) {
                throw new RuntimeException("Invalid event ID.");
            }

            $existingQuery = $conn->prepare("SELECT event_id, poster_data, poster_mime, poster_name FROM events WHERE event_id = ?");
            $existingQuery->bind_param("i", $eventId);
            $existingQuery->execute();
            $existing = $existingQuery->get_result()->fetch_assoc();
            $existingQuery->close();
            if (!$existing) {
                throw new RuntimeException("Event not found.");
            }

            $posterData = $uploadedPoster["data"] ?? $existing["poster_data"];
            $posterMime = $uploadedPoster["mime"] ?? $existing["poster_mime"];
            $posterName = $uploadedPoster["name"] ?? $existing["poster_name"];
            $update = $conn->prepare(
                "UPDATE events
                 SET event_name = ?, event_date = ?, location = ?, description = ?,
                     poster_data = ?, poster_mime = ?, poster_name = ?
                 WHERE event_id = ?"
            );
            $update->bind_param("sssssssi", $eventName, $eventDate, $location, $description, $posterData, $posterMime, $posterName, $eventId);
            $update->execute();
            $update->close();
            header("Location: events.php?status=updated");
            exit;
        } catch (Throwable $exception) {
            $errorMessage = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : "Could not save the event. Check the database setup and try again.";
        }
    }
}

$editingEvent = null;
$editId = filter_var($_GET["edit"] ?? ($_POST["event_id"] ?? null), FILTER_VALIDATE_INT);
if ($editId && $editId > 0 && (($_GET["edit"] ?? null) !== null || ($_POST["action"] ?? "") === "update")) {
    $editQuery = $conn->prepare("SELECT event_id, event_name, event_date, location, description, poster_name FROM events WHERE event_id = ?");
    $editQuery->bind_param("i", $editId);
    $editQuery->execute();
    $editingEvent = $editQuery->get_result()->fetch_assoc();
    $editQuery->close();
}

$formName = (string) ($_POST["event_name"] ?? ($editingEvent["event_name"] ?? ""));
$formDate = (string) ($_POST["event_date"] ?? ($editingEvent["event_date"] ?? ""));
$formLocation = (string) ($_POST["location"] ?? ($editingEvent["location"] ?? ""));
$formDescription = (string) ($_POST["description"] ?? ($editingEvent["description"] ?? ""));
$events = $conn->query("SELECT event_id, event_name, event_date, location, description, poster_name FROM events ORDER BY event_date DESC, event_id DESC");
$messages = ["created" => "Event added successfully.", "updated" => "Event updated successfully.", "deleted" => "Event deleted successfully."];
$status = (string) ($_GET["status"] ?? "");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Manage Events</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="studenthub-crud.css">
    <style>
        .header > .logout-btn { margin-left: auto; transform: none; }
        .header > .logout-btn:hover { transform: none; }
        .event-form { display: grid; gap: 14px; max-width: 760px; }
        .event-form label { display: grid; gap: 6px; font-weight: 600; }
        .event-form input, .event-form textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 5px; font: inherit; }
        .event-form textarea { resize: vertical; }
        .event-poster { display: block; width: 120px; height: 82px; margin-top: 8px; object-fit: cover; }
        .event-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .event-actions form { margin: 0; }
        .event-actions a { text-decoration: none; }
        .event-description { max-width: 320px; white-space: pre-wrap; }
        .page-back { display: flex; justify-content: center; margin-top: 24px; }
        @media (max-width: 700px) { .event-form { width: 100%; } }
    </style>
</head>
<body>
<header class="header">
    <div class="logo-section">
        <div class="logo"><img src="aca.png" width="50" height="50" alt="StudentHub Logo"><h2>STUDENTHUB</h2></div>
    </div>
    <a href="logout.php" class="logout-btn">Logout</a>
    <button id="themeToggle" type="button">🌙 Dark Mode</button>
</header>

<main class="crud-page">
    <div class="crud-container">
        <h1>Manage Events</h1>
        <p class="crud-subtitle">Add, update, and manage StudentHub events.</p>

        <?php if (isset($messages[$status])): ?>
            <p class="crud-message crud-success" role="status"><?= event_escape($messages[$status]) ?></p>
        <?php endif; ?>
        <?php if ($errorMessage !== ""): ?>
            <p class="crud-message crud-error" role="alert"><?= event_escape($errorMessage) ?></p>
        <?php endif; ?>

        <section class="crud-card form-card">
            <h2><?= $editingEvent ? "Edit Event" : "Add New Event" ?></h2>
            <form class="event-form" method="post" action="events.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= event_escape($_SESSION["events_csrf"]) ?>">
                <input type="hidden" name="action" value="<?= $editingEvent ? "update" : "create" ?>">
                <?php if ($editingEvent): ?>
                    <input type="hidden" name="event_id" value="<?= (int) $editingEvent["event_id"] ?>">
                <?php endif; ?>
                <label>Event name
                    <input name="event_name" maxlength="150" required value="<?= event_escape($formName) ?>">
                </label>
                <label>Date
                    <input type="date" name="event_date" required value="<?= event_escape($formDate) ?>">
                </label>
                <label>Location
                    <input name="location" maxlength="150" value="<?= event_escape($formLocation) ?>">
                </label>
                <label>Description
                    <textarea name="description" rows="4"><?= event_escape($formDescription) ?></textarea>
                </label>
                <label>Poster <span>JPEG, PNG, or WebP; maximum 5 MB</span>
                    <input type="file" name="poster" accept="image/jpeg,image/png,image/webp">
                </label>
                <?php if (!empty($editingEvent["poster_name"])): ?>
                    <p>Current poster: <?= event_escape($editingEvent["poster_name"]) ?>
                        <img class="event-poster" src="events.php?poster=<?= (int) $editingEvent["event_id"] ?>" alt="Current event poster">
                    </p>
                <?php endif; ?>
                <div class="form-buttons">
                    <button type="submit" class="crud-btn"><?= $editingEvent ? "Update Event" : "Add Event" ?></button>
                    <?php if ($editingEvent): ?><a class="secondary-btn" href="events.php">Cancel</a><?php endif; ?>
                </div>
            </form>
        </section>

        <section class="crud-card" style="margin-top: 24px;">
            <div class="student-toolbar"><h2>Event Records</h2></div>
            <div class="crud-table-wrapper">
                <table>
                    <thead><tr><th>ID</th><th>Event</th><th>Date</th><th>Location</th><th>Description</th><th>Poster</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if ($events->num_rows === 0): ?>
                        <tr><td class="empty-row" colspan="7">No events found.</td></tr>
                    <?php else: ?>
                        <?php while ($event = $events->fetch_assoc()): ?>
                            <tr>
                                <td><?= (int) $event["event_id"] ?></td>
                                <td><?= event_escape($event["event_name"]) ?></td>
                                <td><?= event_escape($event["event_date"]) ?></td>
                                <td><?= event_escape((string) ($event["location"] ?? "")) ?></td>
                                <td class="event-description"><?= event_escape((string) ($event["description"] ?? "")) ?></td>
                                <td><?php if ($event["poster_name"] !== null): ?><a href="events.php?poster=<?= (int) $event["event_id"] ?>" target="_blank" rel="noopener">View poster</a><?php else: ?>None<?php endif; ?></td>
                                <td><div class="event-actions">
                                    <a class="crud-btn" href="events.php?edit=<?= (int) $event["event_id"] ?>">Edit</a>
                                    <form method="post" action="events.php" onsubmit="return confirm('Delete this event and its registrations?');">
                                        <input type="hidden" name="csrf_token" value="<?= event_escape($_SESSION["events_csrf"]) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="event_id" value="<?= (int) $event["event_id"] ?>">
                                        <button class="delete-btn small-btn" type="submit">Delete</button>
                                    </form>
                                </div></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <div class="page-back"><a class="crud-btn" href="admin_dashboard.php">Back to Admin Dashboard</a></div>
    </div>
</main>
<footer class="footer"><p>© 2026 StudentHub. All Rights Reserved.</p></footer>
<script src="script.js"></script>
</body>
</html>