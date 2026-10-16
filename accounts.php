
<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
require_once __DIR__ . '/../config/database.php';

// Create CSRF token if needed.
if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === ''
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$error = '';
$success = '';

function accountEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Handle activation/deactivation.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $userId = $_POST['user_id'] ?? '';
    $action = $_POST['action'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($csrfToken, $submittedToken)
    ) {
        http_response_code(403);
        exit('Invalid security token. Please refresh and try again.');
    }

    if (
        !is_string($userId) ||
        !ctype_digit($userId) ||
        (int) $userId <= 0 ||
        !is_string($action) ||
        !in_array($action, ['activate', 'deactivate'], true)
    ) {
        $error = 'Invalid account action.';
    } else {
        $newStatus = $action === 'activate' ? 1 : 0;

        try {
            $stmt = $pdo->prepare(
                "UPDATE users
                 SET is_active = :is_active
                 WHERE user_id = :user_id
                   AND role IN ('student', 'teacher')"
            );

            $stmt->execute([
                'is_active' => $newStatus,
                'user_id' => (int) $userId
            ]);

            if ($stmt->rowCount() > 0) {
                $_SESSION['accounts_success'] = $newStatus
                    ? 'Account activated successfully.'
                    : 'Account deactivated successfully.';
            } else {
                $check = $pdo->prepare(
                    "SELECT user_id, is_active
                     FROM users
                     WHERE user_id = :user_id
                       AND role IN ('student', 'teacher')
                     LIMIT 1"
                );

                $check->execute(['user_id' => (int) $userId]);
                $account = $check->fetch(PDO::FETCH_ASSOC);

                if ($account && (int) $account['is_active'] === $newStatus) {
                    $_SESSION['accounts_success'] = $newStatus
                        ? 'This account is already active.'
                        : 'This account is already deactivated.';
                } else {
                    $_SESSION['accounts_error'] =
                        'Account not found or cannot be managed.';
                }
            }

            header('Location: accounts.php');
            exit;

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'Unable to update the account. Please try again.';
        }
    }
}

// Read feedback messages.
if (isset($_SESSION['accounts_success'])) {
    $success = $_SESSION['accounts_success'];
    unset($_SESSION['accounts_success']);
}

if (isset($_SESSION['accounts_error'])) {
    $error = $_SESSION['accounts_error'];
    unset($_SESSION['accounts_error']);
}

// Search accounts.
$search = $_GET['search'] ?? '';
$search = is_string($search) ? trim($search) : '';
$search = substr($search, 0, 150);

try {
    $sql = "
        SELECT user_id, full_name, email, role, is_active, created_at
        FROM users
        WHERE role IN ('student', 'teacher')
    ";

    $params = [];

    if ($search !== '') {
        $sql .= "
            AND (full_name LIKE :name_search
                 OR email LIKE :email_search)
        ";

        $params['name_search'] = '%' . $search . '%';
        $params['email_search'] = '%' . $search . '%';
    }

    $sql .= ' ORDER BY created_at DESC, user_id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Account totals include all students and teachers.
    $countStmt = $pdo->query("
        SELECT
            COUNT(*) AS total_accounts,
            COALESCE(SUM(
                CASE WHEN is_active = 1 THEN 1 ELSE 0 END
            ), 0) AS active_accounts,
            COALESCE(SUM(
                CASE WHEN is_active = 0 THEN 1 ELSE 0 END
            ), 0) AS inactive_accounts
        FROM users
        WHERE role IN ('student', 'teacher')
    ");

    $counts = $countStmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Unable to load accounts. Please check your database configuration.');
}

$name = accountEscape($_SESSION['full_name'] ?? 'Administrator');
$safeSearch = accountEscape($search);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accounts | SmartClass</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/script.js?v=2"></script>
</head>

<body class="sc-app">
<div class="sc-layout">

    <!-- Sidebar -->
    <aside class="sc-sidebar">
        <a href="dashboard.php" class="sc-brand">
            <span class="sc-brand-icon" aria-hidden="true">SC</span>
            <span class="sc-brand-text">
                <strong>SmartClass</strong>
                <small>Classroom Reservation System</small>
            </span>
        </a>

        <nav class="sc-navigation" aria-label="Admin navigation">
            <a href="dashboard.php" class="sc-nav-link">
                <span class="sc-nav-icon" aria-hidden="true">⌂</span>
                <span>Dashboard</span>
            </a>

            <a href="classrooms.php" class="sc-nav-link">
                <span class="sc-nav-icon" aria-hidden="true">▦</span>
                <span>Manage Classrooms</span>
            </a>

            <a href="reservations.php" class="sc-nav-link">
                <span class="sc-nav-icon" aria-hidden="true">▤</span>
                <span>Manage Reservations</span>
            </a>

            <a href="accounts.php"
               class="sc-nav-link active"
               aria-current="page">
                <span class="sc-nav-icon" aria-hidden="true">♙</span>
                <span>Manage Accounts</span>
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
                <input type="hidden" name="csrf_token"
                       value="<?= accountEscape($csrfToken) ?>">
                <button type="submit" class="sc-logout-button">
                    <span class="sc-nav-icon" aria-hidden="true">↪</span>
                    <span>Sign Out</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Main content -->
    <main class="sc-main">

        <header class="sc-topbar">
            <div>
                <p class="sc-topbar-label">ADMINISTRATION</p>
                <h1>Manage Accounts</h1>
            </div>

            <div class="sc-user">
                <span class="sc-avatar" aria-hidden="true">A</span>
                <span class="sc-user-info">
                    <strong><?= $name ?></strong>
                    <small>Administrator</small>
                </span>
            </div>
        </header>

        <div class="sc-accounts-page">

            <!-- Manage Accounts Banner -->
            <section class="sc-welcome reservations-welcome">
                <div class="sc-welcome-content">
                    <span class="sc-welcome-tag">ACCOUNT MANAGEMENT</span>

                    <h2>Manage Accounts</h2>

                    <p>
                        View registered student and teacher accounts, manage account
                        access, and keep track of registered users in one convenient place.
                    </p>
                </div>

                <div class="sc-accounts-hero-art" aria-hidden="true">
                    <span class="sc-accounts-hero-card sc-accounts-hero-card-one">
                        ♙
                    </span>

                    <span class="sc-accounts-hero-check">✓</span>

                    <span class="sc-accounts-hero-card sc-accounts-hero-card-two">
                        ▦
                    </span>
                </div>
            </section>

            <!-- Feedback -->
            <?php if ($success !== ''): ?>
                <div class="accounts-message success" role="status">
                    <?= accountEscape($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="accounts-message error" role="alert">
                    <?= accountEscape($error) ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <section class="sc-accounts-summary" aria-label="Account totals">
                <article class="sc-accounts-stat-card">
                    <span class="sc-accounts-stat-icon blue" aria-hidden="true">♙</span>
                    <div class="sc-accounts-stat-info">
                        <p>Total Accounts</p>
                        <strong><?= (int) $counts['total_accounts'] ?></strong>
                        <small>Registered users</small>
                    </div>
                </article>

                <article class="sc-accounts-stat-card">
                    <span class="sc-accounts-stat-icon green" aria-hidden="true">✓</span>
                    <div class="sc-accounts-stat-info">
                        <p>Active Accounts</p>
                        <strong><?= (int) $counts['active_accounts'] ?></strong>
                        <small>Can sign in to SmartClass</small>
                    </div>
                </article>

                <article class="sc-accounts-stat-card">
                    <span class="sc-accounts-stat-icon red" aria-hidden="true">⊘</span>
                    <div class="sc-accounts-stat-info">
                        <p>Deactivated Accounts</p>
                        <strong><?= (int) $counts['inactive_accounts'] ?></strong>
                        <small>Sign-in access disabled</small>
                    </div>
                </article>
            </section>

            <!-- Registered accounts panel -->
            <section class="sc-accounts-panel">
                <div class="sc-accounts-panel-heading">
                    <div>
                        <h2>Registered Accounts</h2>
                        <p>
                            Search and manage registered student and teacher accounts.
                        </p>
                    </div>

                    <span class="sc-accounts-count">
                        <?= count($accounts) ?>
                        result<?= count($accounts) === 1 ? '' : 's' ?>
                    </span>
                </div>

                <form method="GET" action="accounts.php"
                      class="sc-accounts-search">
                    <label for="account-search">Search accounts</label>

                    <div class="sc-accounts-search-controls">
                        <input
                            id="account-search"
                            type="search"
                            name="search"
                            placeholder="Search name or email..."
                            value="<?= $safeSearch ?>"
                            maxlength="150"
                        >

                        <button type="submit">Search</button>
                    </div>
                </form>

                <div class="sc-accounts-table-wrap">
                    <table class="sc-accounts-table">
                        <thead>
                            <tr>
                                <th>Account</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Date Registered</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if (empty($accounts)): ?>
                            <tr>
                                <td colspan="5" class="sc-accounts-empty">
                                    No student or teacher accounts found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($accounts as $account): ?>
                                <?php
                                $isActive = (int) $account['is_active'] === 1;
                                $accountName = accountEscape($account['full_name']);
                                $accountEmail = accountEscape($account['email']);
                                $accountRole = accountEscape(ucfirst($account['role']));

                                $registeredDate = !empty($account['created_at'])
                                    ? date('M d, Y', strtotime($account['created_at']))
                                    : '—';
                                ?>
                                <tr>
                                    <td>
                                        <div class="sc-accounts-name">
                                            <?= $accountName ?>
                                        </div>
                                        <div class="sc-accounts-email">
                                            <?= $accountEmail ?>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="sc-accounts-role">
                                            <?= $accountRole ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="sc-accounts-status <?= $isActive ? 'active' : 'inactive' ?>">
                                            <span class="sc-status-dot"></span>
                                            <?= $isActive ? 'Active' : 'Deactivated' ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= accountEscape($registeredDate) ?>
                                    </td>

                                    <td>
                                        <form
                                            method="POST"
                                            action="accounts.php"
                                            class="sc-accounts-action-form"
                                            onsubmit="return confirm('Are you sure you want to <?= $isActive ? 'deactivate' : 'activate' ?> this account?');"
                                        >
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= accountEscape($csrfToken) ?>">
                                            <input type="hidden" name="user_id"
                                                   value="<?= (int) $account['user_id'] ?>">
                                            <input type="hidden" name="action"
                                                   value="<?= $isActive ? 'deactivate' : 'activate' ?>">

                                            <button
                                                type="submit"
                                                class="sc-accounts-action-button <?= $isActive ? 'deactivate' : 'activate' ?>"
                                            >
                                                <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <footer class="sc-footer">
            &copy; <?= date('Y') ?> SmartClass Classroom Reservation System.
        </footer>

    </main>
</div>
</body>
</html>
