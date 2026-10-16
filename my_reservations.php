
<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('teacher');
require_once __DIR__ . '/../config/database.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Database connection failed. Please check config/database.php.');
}

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$userId = (int) $_SESSION['user_id'];
$name = $_SESSION['full_name'] ?? 'Teacher';

function escapeHtml($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function formatReservationDate($date): string
{
    if (empty($date)) {
        return 'Not specified';
    }

    $timestamp = strtotime($date);

    return $timestamp !== false
        ? date('F j, Y', $timestamp)
        : 'Not specified';
}

function formatReservationTime($time): string
{
    if (empty($time)) {
        return 'Not specified';
    }

    $timestamp = strtotime($time);

    return $timestamp !== false
        ? date('g:i A', $timestamp)
        : 'Not specified';
}

$reservations = [];
$pending = 0;
$approved = 0;
$rejected = 0;
$cancelled = 0;
$pageError = '';

try {
    $sql = "
        SELECT
            r.reservation_id,
            r.classroom_id,
            r.reservation_date,
            r.start_time,
            r.end_time,
            r.purpose,
            r.status,
            r.admin_remarks,
            c.room_name,
            c.building,
            c.capacity
        FROM reservations AS r
        INNER JOIN classrooms AS c
            ON c.classroom_id = r.classroom_id
        WHERE r.user_id = :user_id
        ORDER BY
            r.reservation_date DESC,
            r.start_time DESC,
            r.reservation_id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $userId]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reservations as $reservation) {
        $status = strtolower(
            trim((string) ($reservation['status'] ?? ''))
        );

        switch ($status) {
            case 'pending':
                $pending++;
                break;
            case 'approved':
                $approved++;
                break;
            case 'rejected':
                $rejected++;
                break;
            case 'cancelled':
                $cancelled++;
                break;
        }
    }
} catch (PDOException $e) {
    error_log('Teacher My Reservations error: ' . $e->getMessage());
    $pageError = 'Unable to load your reservations. Please try again later.';
}

$teacherInitial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($name, 0, 1))
    : strtoupper(substr($name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reservations | SmartClass</title>
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

            <a href="reservations.php" class="sc-nav-link">
                <span class="sc-nav-icon" aria-hidden="true">▦</span>
                <span>Reserve a Classroom</span>
            </a>

            <a href="my_reservations.php"
               class="sc-nav-link active"
               aria-current="page">
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
                <h1>My Reservations</h1>
            </div>

            <div class="sc-user">
                <span class="sc-avatar" aria-hidden="true">
                    <?= escapeHtml($teacherInitial) ?>
                </span>
                <span class="sc-user-info">
                    <strong><?= escapeHtml($name) ?></strong>
                    <small>Teacher</small>
                </span>
            </div>
        </header>

        <section class="sc-welcome">
            <div class="sc-welcome-content">
                <span class="sc-welcome-tag">SMARTCLASS TEACHER PORTAL</span>

                <h2>My Classroom Reservations</h2>

                <p>
                    Track your classroom reservation requests, review their
                    approval status, and check the details of your scheduled
                    classroom activities.
                </p>

                <a href="reservations.php" class="reservation-book-button">
                    <span>Reserve a Classroom</span>
                    <span aria-hidden="true" class="reservation-book-arrow">→</span>
                </a>
            </div>

            <div class="sc-welcome-art" aria-hidden="true">
                <span>▤</span>
                <span>▦</span>
                <span>✓</span>
            </div>
        </section>

        <section class="sc-section">
            <div class="sc-section-heading">
                <div>
                    <h2>Reservation Summary</h2>
                    <p>Overview of your submitted reservation requests.</p>
                </div>
            </div>

            <?php if ($pageError !== ''): ?>
                <div class="reservation-empty" role="alert">
                    <h3>Unable to Load Reservations</h3>
                    <p><?= escapeHtml($pageError) ?></p>
                    <a href="my_reservations.php" class="sc-card-link">
                        Try Again →
                    </a>
                </div>
            <?php else: ?>
                
            <section class="reservation-summary">
                <article class="reservation-stat">
                    <span class="reservation-stat-icon pending-icon" aria-hidden="true">◷</span>
                    <div>
                        <p>Pending Requests</p>
                        <strong><?= (int) $pending ?></strong>
                    </div>
                </article>

                <article class="reservation-stat">
                    <span class="reservation-stat-icon approved-icon" aria-hidden="true">✓</span>
                    <div>
                        <p>Approved</p>
                        <strong><?= (int) $approved ?></strong>
                    </div>
                </article>

                <article class="reservation-stat">
                    <span class="reservation-stat-icon rejected-icon" aria-hidden="true">×</span>
                    <div>
                        <p>Rejected</p>
                        <strong><?= (int) $rejected ?></strong>
                    </div>
                </article>

                <article class="reservation-stat">
                    <span class="reservation-stat-icon cancelled-icon" aria-hidden="true">↶</span>
                    <div>
                        <p>Cancelled</p>
                        <strong><?= (int) $cancelled ?></strong>
                    </div>
                </article>
            </section>

                <div class="sc-section-heading reservation-history-heading">
                    <div>
                        <h2>Reservation History</h2>
                        <p>View the details and decisions for your requests.</p>
                    </div>
                </div>

                <div class="reservation-list">
                    <?php if (!$reservations): ?>
                        <div class="reservation-empty">
                            <h3>No Reservations Yet</h3>
                            <p>
                                You have not submitted any classroom
                                reservation requests.
                            </p>
                            <a href="reservations.php" class="sc-card-link">
                                Reserve a Classroom →
                            </a>
                        </div>
                    <?php else: ?>

                        <?php foreach ($reservations as $reservation): ?>
                            <?php
                            $status = strtolower(
                                trim((string) ($reservation['status'] ?? ''))
                            );

                            $validStatuses = [
                                'pending',
                                'approved',
                                'rejected',
                                'cancelled'
                            ];

                            $statusClass = in_array(
                                $status,
                                $validStatuses,
                                true
                            ) ? $status : 'unknown';

                            $displayStatus = $status !== ''
                                ? ucfirst($status)
                                : 'Unknown';
                            ?>

                            <article class="reservation-card">
                                <div class="reservation-card-header">
                                    <div>
                                        <h3>
                                            <?= escapeHtml(
                                                $reservation['room_name'] ?? 'Classroom'
                                            ) ?>
                                        </h3>

                                        <div class="reservation-muted">
                                            Reservation #
                                            <?= (int) $reservation['reservation_id'] ?>
                                        </div>

                                        <?php if (!empty($reservation['building'])): ?>
                                            <div class="reservation-muted">
                                                <?= escapeHtml($reservation['building']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <span class="reservation-status <?= escapeHtml($statusClass) ?>">
                                        <?= escapeHtml($displayStatus) ?>
                                    </span>
                                </div>

                                <div class="reservation-details">
                                    <div>
                                        <span class="reservation-detail-label">Date</span>
                                        <div class="reservation-detail-value">
                                            <?= escapeHtml(
                                                formatReservationDate(
                                                    $reservation['reservation_date'] ?? ''
                                                )
                                            ) ?>
                                        </div>
                                    </div>

                                    <div>
                                        <span class="reservation-detail-label">Time</span>
                                        <div class="reservation-detail-value">
                                            <?= escapeHtml(
                                                formatReservationTime(
                                                    $reservation['start_time'] ?? ''
                                                )
                                            ) ?>
                                            &ndash;
                                            <?= escapeHtml(
                                                formatReservationTime(
                                                    $reservation['end_time'] ?? ''
                                                )
                                            ) ?>
                                        </div>
                                    </div>

                                    <div>
                                        <span class="reservation-detail-label">Purpose</span>
                                        <div class="reservation-detail-value">
                                            <?= nl2br(
                                                escapeHtml(
                                                    $reservation['purpose'] ?? 'Not specified'
                                                )
                                            ) ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($reservation['admin_remarks'])): ?>
                                    <div class="reservation-remarks">
                                        <strong>Admin Remarks</strong>
                                        <p>
                                            <?= nl2br(
                                                escapeHtml($reservation['admin_remarks'])
                                            ) ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>

                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <footer class="sc-footer">
            &copy; <?= date('Y') ?> SmartClass Classroom Reservation System.
        </footer>
    </main>
</div>
</body>
</html>
