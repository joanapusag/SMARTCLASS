<?php
require_once __DIR__ . '/../config/database.php';

// Start the session only if one is not already active.
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/'
    ]);

    session_start();
}

/**
 * Return the correct dashboard URL for a recognized role.
 */
function dashboardForRole(string $role): string
{
    $dashboards = [
        'student' => '/smart-classroom/student/dashboard.php',
        'teacher' => '/smart-classroom/teacher/dashboard.php',
        'admin'   => '/smart-classroom/admin/dashboard.php'
    ];

    return $dashboards[$role] ?? '/smart-classroom/index.php';
}

/**
 * Check whether the current account is active in the database.
 */

function currentAccountIsActive(): bool
{
    if (
        !isset($_SESSION['user_id']) ||
        !is_numeric($_SESSION['user_id']) ||
        (int) $_SESSION['user_id'] <= 0
    ) {
        return false;
    }

    // Access the shared database connection.
    global $pdo;

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        error_log('SmartClass: Database connection is unavailable.');
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT is_active
             FROM users
             WHERE user_id = :user_id
             LIMIT 1'
        );

        $stmt->execute([
            'user_id' => (int) $_SESSION['user_id']
        ]);

        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        return $account !== false
            && isset($account['is_active'])
            && (int) $account['is_active'] === 1;

    } catch (PDOException $e) {
        error_log($e->getMessage());
        return false;
    }
}
/**
 * Clear the authenticated session and redirect to sign in.
 */
function endAuthenticatedSession(string $message = ''): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax'
            ]
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    if ($message !== '') {
        session_start();
        $_SESSION['login_error'] = $message;
    }

    header('Location: /smart-classroom/index.php');
    exit;
}

/**
 * Redirect an authenticated user to their dashboard.
 */
function redirectToDashboard(): void
{
    if (
        !isset($_SESSION['user_id'], $_SESSION['role']) ||
        !is_numeric($_SESSION['user_id']) ||
        !is_string($_SESSION['role']) ||
        !in_array(
            $_SESSION['role'],
            ['student', 'teacher', 'admin'],
            true
        )
    ) {
        return;
    }

    if (!currentAccountIsActive()) {
        endAuthenticatedSession(
            'Your account is inactive or could not be verified. Please sign in again or contact the administrator.'
        );
    }

    header('Location: ' . dashboardForRole($_SESSION['role']));
    exit;
}

/**
 * Restrict a page to one or more permitted roles.
 *
 * Example:
 * requireRole('admin');
 * requireRole('teacher', 'student');
 */
function requireRole(string ...$requiredRoles): void
{
    if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
        header('Location: /smart-classroom/index.php');
        exit;
    }

    if (
        !is_string($_SESSION['role']) ||
        !in_array($_SESSION['role'], $requiredRoles, true)
    ) {
        http_response_code(403);
        exit(
            'Access denied. You do not have permission to view this page.'
        );
    }

    if (!currentAccountIsActive()) {
        endAuthenticatedSession(
            'Your account is inactive or could not be verified. Please sign in again or contact the administrator.'
        );
    }
}
