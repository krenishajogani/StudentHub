<?php
declare(strict_types=1);

require_once __DIR__ . "/admin_auth.php";
require_once __DIR__ . "/db_mysqli.php";
require_once __DIR__ . "/audit_log.php";

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

function event_json_response(int $status, array $payload): void
{
    http_response_code($status);
    header("Content-Type: application/json; charset=utf-8");
    header("X-Content-Type-Options: nosniff");
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
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

$isApiRequest = ($_GET["api"] ?? "") === "1";
if ($isApiRequest && ($_SERVER["REQUEST_METHOD"] ?? "GET") === "GET") {
    if (isset($_GET["id"])) {
        $eventId = filter_var($_GET["id"], FILTER_VALIDATE_INT);
        if (!$eventId || $eventId < 1) {
            event_json_response(400, ["error" => "Event ID must be a positive integer."]);
        }

        $query = $conn->prepare(
            "SELECT event_id, event_name, event_date, location, description, poster_name
             FROM events
             WHERE event_id = ?"
        );
        $query->bind_param("i", $eventId);
        $query->execute();
        $eventRecord = $query->get_result()->fetch_assoc();
        $query->close();
        if (!$eventRecord) {
            event_json_response(404, ["error" => "Event not found."]);
        }
        $eventRecord["poster_url"] = $eventRecord["poster_name"] !== null
            ? "events.php?poster=" . (int) $eventRecord["event_id"]
            : null;
        event_json_response(200, ["data" => $eventRecord]);
    }

    $result = $conn->query(
        "SELECT event_id, event_name, event_date, location, description, poster_name
         FROM events
         ORDER BY event_date DESC, event_id DESC"
    );
    $eventRecords = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();

    foreach ($eventRecords as &$eventRecord) {
        $eventRecord["poster_url"] = $eventRecord["poster_name"] !== null
            ? "events.php?poster=" . (int) $eventRecord["event_id"]
            : null;
    }
    unset($eventRecord);

    event_json_response(200, ["data" => $eventRecords]);
}

if ($isApiRequest && ($_SERVER["REQUEST_METHOD"] ?? "") === "POST"
    && str_contains(strtolower($_SERVER["CONTENT_TYPE"] ?? ""), "application/json")) {
    try {
        $requestBody = json_decode((string) file_get_contents("php://input"), false, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        event_json_response(400, ["error" => "Request body must contain valid JSON."]);
    }
    if (!$requestBody instanceof stdClass) {
        event_json_response(400, ["error" => "Request body must be a JSON object."]);
    }
    $_POST = get_object_vars($requestBody);
}

$errorMessage = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $submittedToken = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? ($_POST["csrf_token"] ?? "");
    if (!is_string($submittedToken) || !hash_equals($_SESSION["events_csrf"], $submittedToken)) {
        if ($isApiRequest) {
            event_json_response(400, ["error" => "Form expired. Refresh the page and try again."]);
        }
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
                    write_admin_audit($conn, "DELETE_EVENT", "Deleted event #" . $eventId);
                    $conn->commit();
                } catch (Throwable $exception) {
                    $conn->rollback();
                    throw $exception;
                }

                if ($isApiRequest) {
                    event_json_response(200, ["message" => "Event deleted successfully."]);
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
                $conn->begin_transaction();
                try {
                    $insert->bind_param("sssssss", $eventName, $eventDate, $location, $description, $posterData, $posterMime, $posterName);
                    $insert->execute();
                    $eventId = $conn->insert_id;
                    $insert->close();
                    write_admin_audit($conn, "CREATE_EVENT", "Created event #" . $eventId . ": " . $eventName);
                    $conn->commit();
                } catch (Throwable $exception) {
                    $conn->rollback();
                    throw $exception;
                }
                if ($isApiRequest) {
                    event_json_response(201, [
                        "message" => "Event added successfully.",
                        "data" => [
                            "event_id" => $eventId,
                            "event_name" => $eventName,
                            "event_date" => $eventDate,
                            "location" => $location,
                            "description" => $description,
                            "poster_name" => $posterName,
                            "poster_url" => $posterName !== null ? "events.php?poster=" . $eventId : null,
                        ],
                    ]);
                }
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
            $conn->begin_transaction();
            try {
                $update->bind_param("sssssssi", $eventName, $eventDate, $location, $description, $posterData, $posterMime, $posterName, $eventId);
                $update->execute();
                $update->close();
                write_admin_audit($conn, "UPDATE_EVENT", "Updated event #" . $eventId . ": " . $eventName);
                $conn->commit();
            } catch (Throwable $exception) {
                $conn->rollback();
                throw $exception;
            }
            if ($isApiRequest) {
                event_json_response(200, [
                    "message" => "Event updated successfully.",
                    "data" => [
                        "event_id" => $eventId,
                        "event_name" => $eventName,
                        "event_date" => $eventDate,
                        "location" => $location,
                        "description" => $description,
                        "poster_name" => $posterName,
                        "poster_url" => $posterName !== null ? "events.php?poster=" . $eventId : null,
                    ],
                ]);
            }
            header("Location: events.php?status=updated");
            exit;
        } catch (Throwable $exception) {
            if ($isApiRequest) {
                $statusCode = $exception instanceof RuntimeException ? 422 : 500;
                $message = $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : "Could not save the event. Check the database setup and try again.";
                if (!($exception instanceof RuntimeException)) {
                    error_log("Event API error: " . $exception->getMessage());
                }
                event_json_response($statusCode, ["error" => $message]);
            }
            $errorMessage = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : "Could not save the event. Check the database setup and try again.";
        }
    }
}

if ($isApiRequest && ($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
    header("Allow: GET, POST");
    event_json_response(405, ["error" => "Method not allowed."]);
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
    <meta name="events-csrf-token" content="<?= event_escape($_SESSION["events_csrf"]) ?>">
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
        .event-poster-preview { margin: 0; }
        .event-status { margin: 15px 0; }
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
        <p class="crud-subtitle">Practical 13: Add, list, update, and delete StudentHub events with the Fetch API without reloading the page.</p>

        <?php if (isset($messages[$status])): ?>
            <p class="crud-message crud-success" role="status"><?= event_escape($messages[$status]) ?></p>
        <?php endif; ?>
        <?php if ($errorMessage !== ""): ?>
            <p class="crud-message crud-error" role="alert"><?= event_escape($errorMessage) ?></p>
        <?php endif; ?>
        <p id="event-status" class="event-status" role="status" aria-live="polite"></p>

        <section class="crud-card form-card">
            <h2 id="event-form-title"><?= $editingEvent ? "Edit Event" : "Add New Event" ?></h2>
            <form id="event-form" class="event-form" method="post" action="events.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= event_escape($_SESSION["events_csrf"]) ?>">
                <input id="event-action" type="hidden" name="action" value="<?= $editingEvent ? "update" : "create" ?>">
                <input id="event-id" type="hidden" name="event_id" value="<?= $editingEvent ? (int) $editingEvent["event_id"] : "" ?>">
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
                <div id="event-poster-preview" class="event-poster-preview">
                    <?php if (!empty($editingEvent["poster_name"])): ?>
                        <p>Current poster: <?= event_escape($editingEvent["poster_name"]) ?>
                            <img class="event-poster" src="events.php?poster=<?= (int) $editingEvent["event_id"] ?>" alt="Current event poster">
                        </p>
                    <?php endif; ?>
                </div>
                <div class="form-buttons">
                    <button id="event-submit" type="submit" class="crud-btn"><?= $editingEvent ? "Update Event" : "Add Event" ?></button>
                    <button id="event-cancel" type="button" class="secondary-btn" <?= $editingEvent ? "" : "hidden" ?>>Cancel</button>
                </div>
            </form>
        </section>

        <section class="crud-card" style="margin-top: 24px;">
            <div class="student-toolbar">
                <h2>Event Records</h2>
                <button id="refresh-events" type="button" class="crud-btn">Refresh events</button>
            </div>
            <div class="crud-table-wrapper">
                <table>
                    <thead><tr><th>ID</th><th>Event</th><th>Date</th><th>Location</th><th>Description</th><th>Poster</th><th>Actions</th></tr></thead>
                    <tbody id="event-records">
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
<script>
(() => {
    const apiUrl = "events.php?api=1";
    const csrfToken = document.querySelector('meta[name="events-csrf-token"]').content;
    const form = document.getElementById("event-form");
    const list = document.getElementById("event-records");
    const status = document.getElementById("event-status");
    const formTitle = document.getElementById("event-form-title");
    const actionInput = document.getElementById("event-action");
    const idInput = document.getElementById("event-id");
    const submitButton = document.getElementById("event-submit");
    const cancelButton = document.getElementById("event-cancel");
    const posterPreview = document.getElementById("event-poster-preview");

    function showStatus(message, isError = false) {
        status.textContent = message;
        status.className = isError ? "crud-message crud-error event-status" : "crud-message crud-success event-status";
        if (!message) status.className = "event-status";
    }

    async function sendRequest(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: {
                Accept: "application/json",
                ...(options.method === "POST" ? { "X-CSRF-Token": csrfToken } : {}),
                ...(options.body && !(options.body instanceof FormData) ? { "Content-Type": "application/json" } : {}),
                ...options.headers,
            },
        });
        let result;
        try {
            result = await response.json();
        } catch {
            throw new Error("The server returned an invalid response.");
        }
        if (!response.ok) throw new Error(result.error || "The request failed.");
        return result;
    }

    function addCell(row, value, className = "") {
        const cell = document.createElement("td");
        cell.textContent = value || "";
        if (className) cell.className = className;
        row.append(cell);
        return cell;
    }

    function renderEvents(events) {
        list.replaceChildren();
        if (events.length === 0) {
            const row = document.createElement("tr");
            const cell = document.createElement("td");
            cell.colSpan = 7;
            cell.className = "empty-row";
            cell.textContent = "No events found.";
            row.append(cell);
            list.append(row);
            return;
        }

        for (const event of events) {
            const row = document.createElement("tr");
            addCell(row, String(event.event_id));
            addCell(row, event.event_name);
            addCell(row, event.event_date);
            addCell(row, event.location || "");
            addCell(row, event.description || "", "event-description");

            const posterCell = document.createElement("td");
            if (event.poster_url) {
                const posterLink = document.createElement("a");
                posterLink.href = event.poster_url;
                posterLink.target = "_blank";
                posterLink.rel = "noopener";
                posterLink.textContent = "View poster";
                posterCell.append(posterLink);
            } else {
                posterCell.textContent = "None";
            }
            row.append(posterCell);

            const actionsCell = document.createElement("td");
            const actions = document.createElement("div");
            actions.className = "event-actions";
            const editLink = document.createElement("a");
            editLink.className = "crud-btn";
            editLink.href = `events.php?edit=${encodeURIComponent(event.event_id)}`;
            editLink.dataset.editId = String(event.event_id);
            editLink.textContent = "Edit";
            actions.append(editLink);

            const deleteForm = document.createElement("form");
            deleteForm.method = "post";
            deleteForm.action = "events.php";
            deleteForm.dataset.deleteId = String(event.event_id);
            for (const [name, value] of Object.entries({
                csrf_token: csrfToken,
                action: "delete",
                event_id: String(event.event_id),
            })) {
                const input = document.createElement("input");
                input.type = "hidden";
                input.name = name;
                input.value = value;
                deleteForm.append(input);
            }
            const deleteButton = document.createElement("button");
            deleteButton.className = "delete-btn small-btn";
            deleteButton.type = "submit";
            deleteButton.textContent = "Delete";
            deleteForm.append(deleteButton);
            actions.append(deleteForm);
            actionsCell.append(actions);
            row.append(actionsCell);
            list.append(row);
        }
    }

    async function loadEvents() {
        try {
            const result = await sendRequest(apiUrl);
            renderEvents(result.data);
        } catch (error) {
            showStatus(error.message, true);
        }
    }

    function setPosterPreview(event) {
        posterPreview.replaceChildren();
        if (!event.poster_name || !event.poster_url) return;
        const label = document.createElement("p");
        label.append(document.createTextNode(`Current poster: ${event.poster_name}`));
        const image = document.createElement("img");
        image.className = "event-poster";
        image.src = event.poster_url;
        image.alt = "Current event poster";
        label.append(image);
        posterPreview.append(label);
    }

    function startEditing(event) {
        idInput.value = event.event_id;
        actionInput.value = "update";
        form.elements.event_name.value = event.event_name;
        form.elements.event_date.value = event.event_date;
        form.elements.location.value = event.location || "";
        form.elements.description.value = event.description || "";
        form.elements.poster.value = "";
        formTitle.textContent = "Edit Event";
        submitButton.textContent = "Update Event";
        cancelButton.hidden = false;
        setPosterPreview(event);
        form.scrollIntoView({ behavior: "smooth", block: "start" });
        form.elements.event_name.focus();
    }

    function resetForm() {
        form.reset();
        idInput.value = "";
        actionInput.value = "create";
        formTitle.textContent = "Add New Event";
        submitButton.textContent = "Add Event";
        cancelButton.hidden = true;
        posterPreview.replaceChildren();
    }

    form.addEventListener("submit", async (event) => {
        event.preventDefault();
        const formData = new FormData(form);
        const poster = formData.get("poster");
        const payload = poster instanceof File && poster.size > 0
            ? formData
            : JSON.stringify(Object.fromEntries([...formData.entries()].filter(([name]) => name !== "poster")));
        submitButton.disabled = true;
        try {
            const result = await sendRequest(apiUrl, { method: "POST", body: payload });
            resetForm();
            showStatus(result.message);
            await loadEvents();
        } catch (error) {
            showStatus(error.message, true);
        } finally {
            submitButton.disabled = false;
        }
    });

    list.addEventListener("click", async (event) => {
        const editLink = event.target.closest("a[data-edit-id]");
        if (!editLink) return;
        event.preventDefault();
        try {
            const result = await sendRequest(`${apiUrl}&id=${encodeURIComponent(editLink.dataset.editId)}`);
            startEditing(result.data);
            showStatus(`Editing event #${result.data.event_id}.`);
        } catch (error) {
            showStatus(error.message, true);
        }
    });

    list.addEventListener("submit", async (event) => {
        const deleteForm = event.target.closest("form[data-delete-id]");
        if (!deleteForm) return;
        event.preventDefault();
        if (!window.confirm("Delete this event and its registrations?")) return;
        try {
            const result = await sendRequest(apiUrl, {
                method: "POST",
                body: JSON.stringify(Object.fromEntries(new FormData(deleteForm).entries())),
            });
            if (idInput.value === deleteForm.dataset.deleteId) resetForm();
            showStatus(result.message);
            await loadEvents();
        } catch (error) {
            showStatus(error.message, true);
        }
    });

    cancelButton.addEventListener("click", resetForm);
    document.getElementById("refresh-events").addEventListener("click", loadEvents);
    loadEvents();
})();
</script>
</body>
</html>