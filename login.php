
<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /smart-classroom/index.php');
    exit;
}

// Validate CSRF token.
$sessionToken = $_SESSION['csrf_token'] ?? '';
$submittedToken = $_POST['csrf_token'] ?? '';

if (
    !is_string($sessionToken) ||
    $sessionToken === '' ||
    !is_string($submittedToken) ||
    $submittedToken === '' ||
    !hash_equals($sessionToken, $submittedToken)
) {
    http_response_code(403);
    exit(
        'Your session has expired or the security token is invalid. ' .
        'Please return to the login page, refresh it, and try again.'
    );
}

// Validate input.
$email = $_POST['email'] ?? '';
$passwordInput = $_POST['password'] ?? '';
$selectedRole = $_POST['role'] ?? '';

if (
    !is_string($email) ||
    !is_string($passwordInput) ||
    !is_string($selectedRole)
) {
    $_SESSION['login_error'] = 'Please enter valid login information.';
    header('Location: /smart-classroom/index.php');
    exit;
}

$email = trim($email);

if (
    strlen($email) > 150 ||
    strlen($passwordInput) > 128 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    $passwordInput === '' ||
    !in_array($selectedRole, ['student', 'teacher', 'admin'], true)
) {
    $_SESSION['login_error'] =
        'Please enter a valid email address, password, and account type.';
    header('Location: /smart-classroom/index.php');
    exit;
}

// Find the account by email, including its status.
try {
    $stmt = $pdo->prepare(
        'SELECT user_id, full_name, email, password, role, is_active
         FROM users
         WHERE email = :email
         LIMIT 1'
    );

    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Unable to process your login right now. Please try again later.');
}

// Check credentials, role, and account status.
if (
    !$user ||
    !isset($user['password'], $user['is_active']) ||
    !password_verify($passwordInput, $user['password']) ||
    !in_array($user['role'], ['student', 'teacher', 'admin'], true) ||
    $user['role'] !== $selectedRole
) {
    $_SESSION['login_error'] =
        'Invalid login information or incorrect account type.';
    header('Location: /smart-classroom/index.php');
    exit;
}

// Reject deactivated accounts.
if ((int) $user['is_active'] !== 1) {
    $_SESSION['login_error'] =
        'Your account has been deactivated. Please contact the administrator.';
    header('Location: /smart-classroom/index.php');
    exit;
}

// Regenerate the session ID after successful authentication.
session_regenerate_id(true);

// Store account details in the session.
$_SESSION['user_id'] = (int) $user['user_id'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['email'] = $user['email'];
$_SESSION['role'] = $user['role'];

// Generate a new CSRF token.
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Redirect according to the account's stored role.
header('Location: ' . dashboardForRole($user['role']));
exit;
