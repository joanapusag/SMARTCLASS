<?php
require_once __DIR__ . '/includes/auth.php';

if (
    !isset($_SESSION['user_id'], $_SESSION['role']) ||
    !in_array($_SESSION['role'], ['student', 'teacher', 'admin'], true)
) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/config/database.php';

function escapeHtml($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$role = $_SESSION['role'];

$dashboardUrl = match ($role) {
    'admin'   => 'admin/dashboard.php',
    'teacher' => 'teacher/dashboard.php',
    default   => 'student/dashboard.php',
};

$userName = $_SESSION['full_name'] ?? ucfirst($role);

$csrfToken = htmlspecialchars(
    $_SESSION['csrf_token'] ?? '',
    ENT_QUOTES,
    'UTF-8'
);

$selectedDate = trim($_GET['date'] ?? '');
$dateError = '';

if ($selectedDate !== '') {
    $dateObject = DateTime::createFromFormat('!Y-m-d', $selectedDate);
    $dateErrors = DateTime::getLastErrors();

    if (
        !$dateObject ||
        ($dateErrors !== false &&
            ($dateErrors['warning_count'] > 0 ||
             $dateErrors['error_count'] > 0)) ||
        $dateObject->format('Y-m-d') !== $selectedDate
    ) {
        $selectedDate = '';
        $dateError = 'Please select a valid date.';
    }
}

$sql = "
    SELECT
        r.reservation_date,
        r.start_time,
        r.end_time,
        c.room_name,
        c.building,
        c.capacity
    FROM reservations r
    INNER JOIN classrooms c
        ON c.classroom_id = r.classroom_id
    WHERE r.status = 'approved'
";

$params = [];

if ($selectedDate !== '') {
    $sql .= " AND r.reservation_date = ?";
    $params[] = $selectedDate;
}

$sql .= "
    ORDER BY
        r.reservation_date ASC,
        r.start_time ASC,
        c.room_name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalReservations = count($schedule);

$uniqueClassrooms = count(
    array_unique(
        array_column($schedule, 'room_name')
    )
);

$todayReservations = count(
    array_filter($schedule, static function ($item) {
        return $item['reservation_date'] === date('Y-m-d');
    })
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Classroom Schedule | SmartClass</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/script.js" defer></script>
</head>

<body class="sc-app">

<div class="sc-layout">

    <!-- SIDEBAR: MATCHED TO ADMIN DASHBOARD -->
    
    <!-- SIDEBAR: ROLE-BASED NAVIGATION -->
    <aside class="sc-sidebar">

        <a href="<?= escapeHtml($dashboardUrl) ?>" class="sc-brand">
            <span class="sc-brand-icon" aria-hidden="true">SC</span>

            <span class="sc-brand-text">
                <strong>SmartClass</strong>
                <small>Classroom Reservation System</small>
            </span>
        </a>

        <nav class="sc-navigation" aria-label="Main navigation">

            <a
                href="<?= escapeHtml($dashboardUrl) ?>"
                class="sc-nav-link"
            >
                <span class="sc-nav-icon" aria-hidden="true">⌂</span>
                <span>Dashboard</span>
            </a>

            <?php if ($role === 'admin'): ?>

                <a href="admin/classrooms.php" class="sc-nav-link">
                    <span class="sc-nav-icon" aria-hidden="true">▦</span>
                    <span>Manage Classrooms</span>
                </a>

                <a href="admin/reservations.php" class="sc-nav-link">
                    <span class="sc-nav-icon" aria-hidden="true">▤</span>
                    <span>Manage Reservations</span>
                </a>

                <a href="admin/accounts.php" class="sc-nav-link">
                    <span class="sc-nav-icon" aria-hidden="true">♙</span>
                    <span>Manage Accounts</span>
                </a>

            <?php elseif ($role === 'teacher'): ?>

                <a href="teacher/reservations.php" class="sc-nav-link">
                    <span class="sc-nav-icon" aria-hidden="true">▦</span>
                    <span>Reserve a Classroom</span>
                </a>

                <a href="teacher/my_reservations.php" class="sc-nav-link">
                    <span class="sc-nav-icon" aria-hidden="true">◷</span>
                    <span>My Reservations</span>
                </a>

            <?php elseif ($role === 'student'): ?>

                <a href="student/reservations.php" class="sc-nav-link">
                    <span class="sc-nav-icon" aria-hidden="true">▦</span>
                    <span>Reserve a Classroom</span>
                </a>

                <a href="student/my_reservations.php" class="sc-nav-link">
                    <span class="sc-nav-icon" aria-hidden="true">◷</span>
                    <span>My Reservations</span>
                </a>

            <?php endif; ?>

            <a
                href="schedule.php"
                class="sc-nav-link active"
                aria-current="page"
            >
                <span class="sc-nav-icon" aria-hidden="true">▣</span>
                <span>Classroom Schedule</span>
            </a>

        </nav>

        <div class="sc-sidebar-bottom">

            <div class="sc-sidebar-note">
                <span class="sc-note-icon" aria-hidden="true">✦</span>

                <p>
                    Smart scheduling.<br>
                    Better learning spaces.
                </p>
            </div>

            <form
                action="logout.php"
                method="POST"
                class="sc-logout-form"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= $csrfToken ?>"
                >

                <button type="submit" class="sc-logout-button">
                    <span class="sc-nav-icon" aria-hidden="true">↪</span>
                    <span>Sign Out</span>
                </button>
            </form>

        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="sc-main">

        <!-- TOPBAR -->
        <header class="sc-topbar">

            <div>
                <p class="sc-topbar-label">SCHEDULING</p>
                <h1>Classroom Schedule</h1>
            </div>

            <div class="sc-user">

                <span class="sc-avatar" aria-hidden="true">
                    <?= escapeHtml(strtoupper(substr($role, 0, 1))) ?>
                </span>

                <span class="sc-user-info">
                    <strong><?= escapeHtml($userName) ?></strong>
                    <small><?= escapeHtml(ucfirst($role)) ?></small>
                </span>

            </div>

        </header>

        <!-- WELCOME SECTION -->
        <section class="sc-welcome">

            <div class="sc-welcome-content">
                <span class="sc-welcome-tag">
                    SCHEDULE MANAGEMENT
                </span>

                <h2>Classroom Schedule</h2>

                <p>
                    View approved classroom reservations, check booking
                    times, and plan classroom activities around confirmed
                    schedules.
                </p>
            </div>

            <div class="sc-welcome-art" aria-hidden="true">
                <span>▤</span>
                <span>▦</span>
                <span>✓</span>
            </div>

        </section>

        <!-- DATE VALIDATION NOTICE -->
        <?php if ($dateError !== ''): ?>
            <div class="schedule-notice schedule-error" role="alert">
                <?= escapeHtml($dateError) ?>
            </div>
        <?php endif; ?>

        <!-- SUMMARY CARDS -->
        <section
            class="schedule-summary"
            aria-label="Schedule summary"
        >

            <article class="schedule-stat-card">

                <div
                    class="schedule-stat-icon schedule-stat-blue"
                    aria-hidden="true"
                >
                    ▤
                </div>

                <div>
                    <p>Total Reservations</p>
                    <h3><?= $totalReservations ?></h3>

                    <span>
                        <?= $selectedDate !== ''
                            ? 'For selected date'
                            : 'All scheduled dates' ?>
                    </span>
                </div>

            </article>

            <article class="schedule-stat-card">

                <div
                    class="schedule-stat-icon schedule-stat-purple"
                    aria-hidden="true"
                >
                    ▦
                </div>

                <div>
                    <p>Classrooms Used</p>
                    <h3><?= $uniqueClassrooms ?></h3>
                    <span>Different classrooms</span>
                </div>

            </article>

            <article class="schedule-stat-card">

                <div
                    class="schedule-stat-icon schedule-stat-green"
                    aria-hidden="true"
                >
                    ◷
                </div>

                <div>
                    <p>Scheduled Today</p>
                    <h3><?= $todayReservations ?></h3>
                    <span>Approved bookings today</span>
                </div>

            </article>

        </section>

        <!-- APPROVED RESERVATIONS -->
        <section class="schedule-panel">

            <div class="schedule-panel-heading">

                <div>
                    <h2>Approved Reservations</h2>
                    <p>
                        Only reservations approved by the administrator
                        appear in this schedule.
                    </p>
                </div>

                <span class="schedule-count">
                    <?= $totalReservations ?>
                    <?= $totalReservations === 1
                        ? 'reservation'
                        : 'reservations' ?>
                </span>

            </div>

            <!-- DATE FILTER -->
            <form
                method="GET"
                action="schedule.php"
                class="schedule-filter"
            >

                <div class="schedule-field">
                    <label for="schedule-date">Filter by date</label>

                    <input
                        type="date"
                        id="schedule-date"
                        name="date"
                        value="<?= escapeHtml($selectedDate) ?>"
                    >
                </div>

                <div class="schedule-filter-actions">

                    <button
                        class="schedule-apply-button"
                        type="submit"
                    >
                        Apply Filter
                    </button>

                    <?php if ($selectedDate !== ''): ?>
                        <a
                            class="schedule-clear-button"
                            href="schedule.php"
                        >
                            Clear Filter
                        </a>
                    <?php endif; ?>

                </div>

            </form>

            <!-- EMPTY STATE -->
            <?php if (!$schedule): ?>

                <div class="schedule-empty">

                    <div
                        class="schedule-empty-icon"
                        aria-hidden="true"
                    >
                        <span>▤</span>
                    </div>

                    <h3>No approved reservations found</h3>

                    <p>
                        <?php if ($selectedDate !== ''): ?>

                            There are no approved classroom reservations for
                            <?= escapeHtml(
                                date('F j, Y', strtotime($selectedDate))
                            ) ?>.

                        <?php else: ?>

                            There are currently no approved classroom
                            reservations to display.

                        <?php endif; ?>
                    </p>

                    <?php if ($selectedDate !== ''): ?>
                        <a
                            href="schedule.php"
                            class="schedule-clear-button"
                        >
                            View All Schedules
                        </a>
                    <?php endif; ?>

                </div>

            <?php else: ?>

                <!-- SCHEDULE TABLE -->
                <div class="schedule-table-wrapper">

                    <table class="schedule-table">

                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Classroom</th>
                                <th scope="col">Building</th>
                                <th scope="col">Time</th>
                                <th scope="col">Capacity</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($schedule as $item): ?>

                                <tr>

                                    <td>
                                        <div class="schedule-date-cell">

                                            <strong>
                                                <?= escapeHtml(
                                                    date(
                                                        'M d, Y',
                                                        strtotime(
                                                            $item['reservation_date']
                                                        )
                                                    )
                                                ) ?>
                                            </strong>

                                            <span>
                                                <?= escapeHtml(
                                                    date(
                                                        'l',
                                                        strtotime(
                                                            $item['reservation_date']
                                                        )
                                                    )
                                                ) ?>
                                            </span>

                                        </div>
                                    </td>

                                    <td>
                                        <div class="schedule-room-cell">

                                            <span
                                                class="schedule-room-icon"
                                                aria-hidden="true"
                                            >
                                                ▦
                                            </span>

                                            <strong>
                                                <?= escapeHtml(
                                                    $item['room_name']
                                                ) ?>
                                            </strong>

                                        </div>
                                    </td>

                                    <td>
                                        <?= escapeHtml(
                                            $item['building'] ?: 'Not specified'
                                        ) ?>
                                    </td>

                                    <td>
                                        <div class="schedule-time-cell">

                                            <strong>
                                                <?= escapeHtml(
                                                    date(
                                                        'g:i A',
                                                        strtotime(
                                                            $item['start_time']
                                                        )
                                                    )
                                                ) ?>
                                            </strong>

                                            <span>
                                                to
                                                <?= escapeHtml(
                                                    date(
                                                        'g:i A',
                                                        strtotime(
                                                            $item['end_time']
                                                        )
                                                    )
                                                ) ?>
                                            </span>

                                        </div>
                                    </td>

                                    <td>
                                        <span class="schedule-capacity">
                                            <?= (int) $item['capacity'] ?>
                                            people
                                        </span>
                                    </td>

                                    <td>
                                        <span
                                            class="status-badge status-approved"
                                        >
                                            <span
                                                class="status-dot"
                                                aria-hidden="true"
                                            ></span>
                                            Approved
                                        </span>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <div class="schedule-table-footer">
                    <span>
                        Showing <?= $totalReservations ?>
                        <?= $totalReservations === 1
                            ? 'reservation'
                            : 'reservations' ?>
                    </span>

                    <span>Sorted by date and start time</span>
                </div>

            <?php endif; ?>

        </section>

        <!-- FOOTNOTE -->
        <p class="schedule-footnote">
            <span aria-hidden="true">ⓘ</span>

            This schedule displays approved reservations only.
            Confirm classroom availability before submitting a new
            reservation request.
        </p>

        <!-- FOOTER -->
        <footer class="sc-footer">
            &copy; <?= date('Y') ?> SmartClass Classroom Reservation System.
        </footer>

    </main>

</div>

</body>
</html>