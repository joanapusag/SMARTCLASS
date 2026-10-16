<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('teacher');
require_once __DIR__ . '/../config/database.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Database connection failed.');
}

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$teacherId = (int) $_SESSION['user_id'];
$teacherName = $_SESSION['full_name'] ?? 'Teacher';

function escapeHtml($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function isValidReservationDate(string $date): bool
{
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    $errors = DateTime::getLastErrors();

    return $parsed !== false
        && ($errors === false || (
            $errors['warning_count'] === 0 &&
            $errors['error_count'] === 0
        ))
        && $parsed->format('Y-m-d') === $date;
}

function isValidReservationTime(string $time): bool
{
    return (bool) preg_match(
        '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
        $time
    );
}

function formatReservationTime(string $time): string
{
    $timestamp = strtotime($time);

    return $timestamp !== false
        ? date('g:i A', $timestamp)
        : $time;
}

$errors = [];
$success = '';

$classroomId = '';
$reservationDate = '';
$startTime = '';
$endTime = '';
$purpose = '';
$classrooms = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($csrfToken, $submittedToken)
    ) {
        $errors[] = 'Your security token is invalid. Refresh the page and try again.';
    }

    $rawClassroomId = $_POST['classroom_id'] ?? '';
    $validatedClassroomId = filter_var(
        $rawClassroomId,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($validatedClassroomId === false || $validatedClassroomId === null) {
        $errors[] = 'Please select a valid classroom.';
    } else {
        $classroomId = (string) $validatedClassroomId;
    }

    $reservationDate = trim((string) ($_POST['reservation_date'] ?? ''));
    $startTime = trim((string) ($_POST['start_time'] ?? ''));
    $endTime = trim((string) ($_POST['end_time'] ?? ''));
    $purpose = trim((string) ($_POST['purpose'] ?? ''));

    if (!isValidReservationDate($reservationDate)) {
        $errors[] = 'Please select a valid reservation date.';
    } elseif ($reservationDate < date('Y-m-d')) {
        $errors[] = 'You cannot reserve a classroom for a past date.';
    }

    if (
        !isValidReservationTime($startTime) ||
        !isValidReservationTime($endTime)
    ) {
        $errors[] = 'Please enter valid start and end times.';
    } elseif ($startTime >= $endTime) {
        $errors[] = 'The end time must be later than the start time.';
    }

    $purposeLength = function_exists('mb_strlen')
        ? mb_strlen($purpose)
        : strlen($purpose);

    if ($purpose === '') {
        $errors[] = 'Please enter the purpose of your reservation.';
    } elseif ($purposeLength > 255) {
        $errors[] = 'The purpose must not exceed 255 characters.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Lock the classroom row while checking for conflicts.
            $roomStatement = $pdo->prepare(
                "SELECT classroom_id, room_name
                 FROM classrooms
                 WHERE classroom_id = ?
                   AND LOWER(status) = 'available'
                 FOR UPDATE"
            );

            $roomStatement->execute([(int) $classroomId]);
            $selectedRoom = $roomStatement->fetch(PDO::FETCH_ASSOC);

            if (!$selectedRoom) {
                $errors[] = 'This classroom is not available. Please select another classroom.';
            } else {
                $conflictStatement = $pdo->prepare(
                    "SELECT reservation_id, start_time, end_time
                     FROM reservations
                     WHERE classroom_id = ?
                       AND reservation_date = ?
                       AND LOWER(status) IN ('pending', 'approved')
                       AND start_time < ?
                       AND end_time > ?
                     LIMIT 1"
                );

                $conflictStatement->execute([
                    (int) $classroomId,
                    $reservationDate,
                    $endTime . ':00',
                    $startTime . ':00'
                ]);

                $conflict = $conflictStatement->fetch(PDO::FETCH_ASSOC);

                if ($conflict) {
                    $errors[] =
                        'This classroom already has a pending or approved reservation from ' .
                        formatReservationTime($conflict['start_time']) .
                        ' to ' .
                        formatReservationTime($conflict['end_time']) .
                        ' on the selected date. Please choose another time or classroom.';
                } else {
                    $insertStatement = $pdo->prepare(
                        "INSERT INTO reservations (
                            user_id,
                            classroom_id,
                            reservation_date,
                            start_time,
                            end_time,
                            purpose,
                            status
                        ) VALUES (?, ?, ?, ?, ?, ?, 'pending')"
                    );

                    $insertStatement->execute([
                        $teacherId,
                        (int) $classroomId,
                        $reservationDate,
                        $startTime . ':00',
                        $endTime . ':00',
                        $purpose
                    ]);

                    $success = 'Your reservation request has been submitted successfully and is awaiting administrator approval.';

                    $classroomId = '';
                    $reservationDate = '';
                    $startTime = '';
                    $endTime = '';
                    $purpose = '';
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Teacher reservation submission error: ' . $e->getMessage());
            $errors[] = 'The reservation could not be submitted. Please try again.';
        }
    }
}

try {
    $roomQuery = $pdo->query(
        "SELECT classroom_id, room_name, building, capacity, equipment
         FROM classrooms
         WHERE LOWER(status) = 'available'
         ORDER BY building ASC, room_name ASC"
    );

    $classrooms = $roomQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Teacher classroom directory error: ' . $e->getMessage());
    $errors[] = 'The classroom directory could not be loaded. Please try again later.';
}

$teacherInitial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($teacherName, 0, 1))
    : strtoupper(substr($teacherName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserve a Classroom | SmartClass</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/script.js" defer></script>
</head>

<body class="sc-app">
<div class="sc-layout">

    <aside class="sc-sidebar">
        <a href="dashboard.php" class="sc-brand">
            <span class="sc-brand-icon" aria-hidden="true">SC</span>
            <span class="sc-brand-text">
                <strong>SmartClass</strong>
                <small>Classroom Reservation System</small>
            </span>
        </a>

        <nav class="sc-navigation" aria-label="Teacher navigation">
            <a href="dashboard.php" class="sc-nav-link">
                <span class="sc-nav-icon" aria-hidden="true">⌂</span>
                <span>Dashboard</span>
            </a>

            <a href="reservations.php"
               class="sc-nav-link active"
               aria-current="page">
                <span class="sc-nav-icon" aria-hidden="true">▦</span>
                <span>Reserve a Classroom</span>
            </a>

            <a href="my_reservations.php" class="sc-nav-link">
                <span class="sc-nav-icon" aria-hidden="true">◷</span>
                <span>My Reservations</span>
            </a>

            <a href="../schedule.php" class="sc-nav-link">
                <span class="sc-nav-icon" aria-hidden="true">▣</span>
                <span>Classroom Schedule</span>
            </a>
        </nav>

        <div class="sc-sidebar-bottom">
            <div class="sc-sidebar-note">
                <span class="sc-note-icon" aria-hidden="true">✦</span>
                <p>Smart scheduling.<br>Better learning spaces.</p>
            </div>

            <form action="../logout.php" method="POST" class="sc-logout-form">
                <input type="hidden"
                       name="csrf_token"
                       value="<?= escapeHtml($csrfToken) ?>">

                <button type="submit" class="sc-logout-button">
                    <span class="sc-nav-icon" aria-hidden="true">↪</span>
                    <span>Sign Out</span>
                </button>
            </form>
        </div>
    </aside>

    <main class="sc-main">
        <header class="sc-topbar">
            <div>
                <p class="sc-topbar-label">TEACHER PORTAL</p>
                <h1>Reserve a Classroom</h1>
            </div>

            <div class="sc-user">
                <span class="sc-avatar" aria-hidden="true">
                    <?= escapeHtml($teacherInitial) ?>
                </span>
                <span class="sc-user-info">
                    <strong><?= escapeHtml($teacherName) ?></strong>
                    <small>Teacher</small>
                </span>
            </div>
        </header>

        <div class="sc-reservation-page">

            <section class="sc-welcome">
                <div class="sc-welcome-content">
                    <span class="sc-welcome-tag">ROOM DIRECTORY &amp; BOOKING</span>
                    <h2>Find Your Classroom</h2>
                    <p>
                        Browse available classrooms, select a suitable
                        time, and submit a reservation request for
                        administrator approval.
                    </p>
                </div>

                <a href="my_reservations.php" class="sc-button">
                    View My Reservations <span aria-hidden="true">→</span>
                </a>
            </section>

            <?php if ($success !== ''): ?>
                <div class="sc-notice success" role="status">
                    <strong>Reservation Submitted Successfully</strong>
                    <p><?= escapeHtml($success) ?></p>
                    <p>
                        <a href="my_reservations.php">
                            View your reservation status →
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="sc-notice error" role="alert">
                    <strong>Please check the following:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= escapeHtml($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <section class="sc-section">
                <div class="sc-section-heading">
                    <div>
                        <h2>Available Classrooms</h2>
                        <p>Select a room to use in your reservation request.</p>
                    </div>
                </div>

                <div class="sc-panel">
                    <div class="sc-panel-body">
                        <?php if (!$classrooms): ?>
                            <div class="sc-empty-state">
                                <div class="sc-empty-icon" aria-hidden="true">▦</div>
                                <h3>No Available Classrooms</h3>
                                <p>
                                    There are currently no classrooms marked
                                    as available. Please check again later
                                    or contact the administrator.
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="sc-room-grid">
                                <?php foreach ($classrooms as $room): ?>
                                    <article class="sc-room-card">
                                        <div class="sc-room-title">
                                            <div class="sc-room-icon" aria-hidden="true">▦</div>
                                            <div>
                                                <h3><?= escapeHtml($room['room_name']) ?></h3>
                                                <p><?= escapeHtml($room['building'] ?? 'Building not specified') ?></p>
                                            </div>
                                        </div>

                                        <div class="sc-room-details">
                                            <div class="sc-room-detail">
                                                <span>Capacity</span>
                                                <span><?= (int) $room['capacity'] ?> people</span>
                                            </div>
                                            <div class="sc-room-detail">
                                                <span>Equipment</span>
                                                <span><?= escapeHtml($room['equipment'] ?: 'Not specified') ?></span>
                                            </div>
                                        </div>

                                        <button
                                            type="button"
                                            class="sc-button sc-button-secondary"
                                            data-select-classroom="<?= (int) $room['classroom_id'] ?>">
                                            Select This Classroom
                                        </button>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="sc-section" id="booking-form">
                <div class="sc-section-heading">
                    <div>
                        <h2>Reservation Details</h2>
                        <p>Complete the form below to submit your request.</p>
                    </div>
                </div>

                <div class="sc-panel">
                    <div class="sc-panel-header">
                        <h2>Request a Classroom</h2>
                        <p>
                            Reservations are checked for overlapping pending
                            or approved bookings before being saved.
                        </p>
                    </div>

                    <div class="sc-panel-body">
                        <?php if (!$classrooms): ?>
                            <div class="sc-empty-state">
                                <h3>Booking Currently Unavailable</h3>
                                <p>
                                    A classroom must be marked as available
                                    before you can submit a reservation.
                                </p>
                            </div>
                        <?php else: ?>
                            <form method="POST"
                                  action="reservations.php#booking-form"
                                  class="sc-form-grid">

                                <input type="hidden"
                                       name="csrf_token"
                                       value="<?= escapeHtml($csrfToken) ?>">

                                <div class="sc-field full">
                                    <label for="classroom_id">
                                        Classroom <span class="sc-required">*</span>
                                    </label>
                                    <select id="classroom_id"
                                            name="classroom_id"
                                            required>
                                        <option value="">Select a classroom</option>
                                        <?php foreach ($classrooms as $room): ?>
                                            <option
                                                value="<?= (int) $room['classroom_id'] ?>"
                                                <?= $classroomId === (string) $room['classroom_id'] ? 'selected' : '' ?>>
                                                <?= escapeHtml($room['room_name']) ?>
                                                — <?= escapeHtml($room['building'] ?? '') ?>
                                                (Capacity: <?= (int) $room['capacity'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="sc-help">
                                        Choose a room and time. The system
                                        checks for conflicting reservations
                                        when you submit the form.
                                    </p>
                                </div>

                                <div class="sc-field">
                                    <label for="reservation_date">
                                        Reservation Date <span class="sc-required">*</span>
                                    </label>
                                    <input type="date"
                                           id="reservation_date"
                                           name="reservation_date"
                                           min="<?= date('Y-m-d') ?>"
                                           value="<?= escapeHtml($reservationDate) ?>"
                                           required>
                                </div>

                                <div class="sc-field">
                                    <label for="purpose">
                                        Purpose <span class="sc-required">*</span>
                                    </label>
                                    <input type="text"
                                           id="purpose"
                                           name="purpose"
                                           maxlength="255"
                                           value="<?= escapeHtml($purpose) ?>"
                                           placeholder="e.g. Programming class"
                                           required>
                                </div>

                                <div class="sc-field">
                                    <label for="start_time">
                                        Start Time <span class="sc-required">*</span>
                                    </label>
                                    <input type="time"
                                           id="start_time"
                                           name="start_time"
                                           value="<?= escapeHtml($startTime) ?>"
                                           required>
                                </div>

                                <div class="sc-field">
                                    <label for="end_time">
                                        End Time <span class="sc-required">*</span>
                                    </label>
                                    <input type="time"
                                           id="end_time"
                                           name="end_time"
                                           value="<?= escapeHtml($endTime) ?>"
                                           required>
                                </div>

                                <div class="sc-field full sc-form-actions">
                                    <button type="submit" class="sc-button">
                                        Submit Reservation <span aria-hidden="true">→</span>
                                    </button>
                                    <p class="sc-help">
                                        Your request remains pending until
                                        an administrator reviews it.
                                    </p>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <footer class="sc-footer">
                &copy; <?= date('Y') ?> SmartClass Classroom Reservation System.
            </footer>
        </div>
    </main>
</div>
</body>
</html>