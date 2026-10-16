<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('teacher');

$name = htmlspecialchars(
    $_SESSION['full_name'] ?? 'Teacher',
    ENT_QUOTES,
    'UTF-8'
);

$csrfToken = htmlspecialchars(
    $_SESSION['csrf_token'] ?? '',
    ENT_QUOTES,
    'UTF-8'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Teacher Dashboard | SmartClass</title>

    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/script.js" defer></script>
</head>

<body class="sc-app">

<div class="sc-layout">

    <!-- SIDEBAR -->
    <aside class="sc-sidebar">

        <a href="dashboard.php" class="sc-brand">
            <span class="sc-brand-icon" aria-hidden="true">SC</span>

            <span class="sc-brand-text">
                <strong>SmartClass</strong>
                <small>Classroom Reservation System</small>
            </span>
        </a>

        <nav class="sc-navigation" aria-label="Teacher navigation">

            <a
                href="dashboard.php"
                class="sc-nav-link active"
                aria-current="page"
            >
                <span class="sc-nav-icon" aria-hidden="true">⌂</span>
                <span>Dashboard</span>
            </a>

            <a href="reservations.php" class="sc-nav-link">
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

                <p>
                    Smart scheduling.<br>
                    Better learning spaces.
                </p>
            </div>

            <form
                action="../logout.php"
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
                <p class="sc-topbar-label">TEACHER PORTAL</p>
                <h1>Teacher Dashboard</h1>
            </div>

            <div class="sc-user">

                <span class="sc-avatar" aria-hidden="true">T</span>

                <span class="sc-user-info">
                    <strong><?= $name ?></strong>
                    <small>Teacher</small>
                </span>

            </div>

        </header>

        <!-- WELCOME SECTION -->
        <section class="sc-welcome">

            <div class="sc-welcome-content">

                <span class="sc-welcome-tag">
                    SMARTCLASS TEACHER PORTAL
                </span>

                <h2>
                    Welcome <?= htmlspecialchars(
                        explode(' ', trim($_SESSION['full_name'] ?? 'Teacher'))[0],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>!
                </h2>

                <p>
                    Manage your classroom reservations, monitor the status
                    of your requests, and check classroom schedules to help
                    plan your classes and academic activities.
                </p>

            </div>

            <div class="sc-welcome-art" aria-hidden="true">
                <span>▤</span>
                <span>▦</span>
                <span>✓</span>
            </div>

        </section>

        <!-- TEACHER FUNCTIONS -->
        <section class="sc-section">

            <div class="sc-section-heading">
                <div>
                    <h2>Teacher Functions</h2>
                    <p>
                        Choose a function to manage your classroom needs.
                    </p>
                </div>
            </div>

            <div class="sc-dashboard-grid">

                <!-- RESERVE A CLASSROOM -->
                <article class="sc-feature-card">

                    <div class="sc-feature-top">
                        <span
                            class="sc-feature-icon blue"
                            aria-hidden="true"
                        >▦</span>

                        <span class="sc-feature-label">BOOKING</span>
                    </div>

                    <h3>Reserve a Classroom</h3>

                    <p>
                        Submit a classroom reservation for a class,
                        meeting, discussion, or other academic activity.
                    </p>

                    <a href="reservations.php" class="sc-card-link">
                        Make a Reservation
                        <span aria-hidden="true">→</span>
                    </a>

                </article>

                <!-- MY RESERVATIONS -->
                <article class="sc-feature-card">

                    <div class="sc-feature-top">
                        <span
                            class="sc-feature-icon purple"
                            aria-hidden="true"
                        >▤</span>

                        <span class="sc-feature-label">MY REQUESTS</span>
                    </div>

                    <h3>My Reservations</h3>

                    <p>
                        Review your submitted reservation requests and
                        check whether they are pending, approved, rejected,
                        or cancelled.
                    </p>

                    <a href="my_reservations.php" class="sc-card-link">
                        View My Reservations
                        <span aria-hidden="true">→</span>
                    </a>

                </article>

                <!-- CLASSROOM SCHEDULE -->
                <article class="sc-feature-card">

                    <div class="sc-feature-top">
                        <span
                            class="sc-feature-icon teal"
                            aria-hidden="true"
                        >▣</span>

                        <span class="sc-feature-label">SCHEDULING</span>
                    </div>

                    <h3>Classroom Schedule</h3>

                    <p>
                        View approved classroom reservations and check
                        scheduled room usage before planning your activities.
                    </p>

                    <a href="../schedule.php" class="sc-card-link">
                        View Classroom Schedule
                        <span aria-hidden="true">→</span>
                    </a>

                </article>
            </div>

        </section>

        <!-- FOOTER -->
        <footer class="sc-footer">
            &copy; <?= date('Y') ?> SmartClass Classroom Reservation System.
        </footer>

    </main>

</div>

</body>
</html>