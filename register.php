
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

// Only students and teachers may register publicly.
$role = $_GET['role'] ?? $_POST['role'] ?? 'student';

if (!is_string($role) || !in_array($role, ['student', 'teacher'], true)) {
    $role = 'student';
}

$error = '';
$fullName = '';
$email = '';

// Create a CSRF token if needed.
if (
    !isset($_SESSION['register_csrf_token']) ||
    !is_string($_SESSION['register_csrf_token']) ||
    $_SESSION['register_csrf_token'] === ''
) {
    $_SESSION['register_csrf_token'] = bin2hex(random_bytes(32));
}

// Process registration.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['register_csrf_token'] ?? '';
    $fullNameInput = $_POST['full_name'] ?? '';
    $emailInput = $_POST['email'] ?? '';
    $passwordInput = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $postedRole = $_POST['role'] ?? '';

    // Validate CSRF token.
    if (
        !is_string($submittedToken) ||
        !is_string($_SESSION['register_csrf_token'] ?? null) ||
        $_SESSION['register_csrf_token'] === '' ||
        !hash_equals($_SESSION['register_csrf_token'], $submittedToken)
    ) {
        http_response_code(403);
        exit('Invalid security token. Please return to the registration page and try again.');
    }

    // Public registration is limited to students and teachers.
    if (
        !is_string($postedRole) ||
        !in_array($postedRole, ['student', 'teacher'], true)
    ) {
        http_response_code(400);
        exit('Invalid account type.');
    }

    $role = $postedRole;

    if (
        !is_string($fullNameInput) ||
        !is_string($emailInput) ||
        !is_string($passwordInput) ||
        !is_string($confirmPassword)
    ) {
        $error = 'Please enter valid information.';
    } else {
        $fullName = trim($fullNameInput);
        $email = trim($emailInput);

        if ($fullName === '' || strlen($fullName) > 100) {
            $error = 'Please enter your name (up to 100 characters).';
        } elseif (
            strlen($email) > 150 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            $error = 'Please enter a valid email address.';
        } elseif (
            strlen($passwordInput) < 8 ||
            strlen($passwordInput) > 128
        ) {
            $error = 'Your password must be between 8 and 128 characters.';
        } elseif ($passwordInput !== $confirmPassword) {
            $error = 'The passwords do not match.';
        } else {
            try {
                // Check whether the email is already registered.
                $check = $pdo->prepare(
                    'SELECT user_id
                     FROM users
                     WHERE email = :email
                     LIMIT 1'
                );

                $check->execute(['email' => $email]);

                if ($check->fetch()) {
                    $error = 'That email address is already registered.';
                } else {
                    // Securely hash the password.
                    $hashedPassword = password_hash(
                        $passwordInput,
                        PASSWORD_DEFAULT
                    );

                    // Create the account.
                    $insert = $pdo->prepare(
                        'INSERT INTO users (full_name, email, password, role)
                         VALUES (:full_name, :email, :password, :role)'
                    );

                    $insert->execute([
                        'full_name' => $fullName,
                        'email' => $email,
                        'password' => $hashedPassword,
                        'role' => $role
                    ]);

                    // Rotate the CSRF token after successful registration.
                    $_SESSION['register_csrf_token'] = bin2hex(random_bytes(32));

                    $safeRole = htmlspecialchars(
                        ucfirst($role),
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                    <!DOCTYPE html>
                    <html lang="en">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title>Account Created | SmartClass</title>
                        <link rel="stylesheet" href="assets/css/style.css?v=smartclass-register-6">
                    </head>
                    <body class="sc-login-body">
                    <main class="sc-login-page">
                        <section class="sc-login-card">
                            <div class="sc-login-left">
                                <a href="index.php" class="sc-login-brand">
                                    <span class="sc-login-logo">SC</span>
                                    <span class="sc-login-brand-text">
                                        <strong>SmartClass</strong>
                                        <small>Classroom Reservation System</small>
                                    </span>
                                </a>

                                <div class="sc-registration-success-icon">✓</div>

                                <div class="sc-login-heading">
                                    <span class="sc-login-eyebrow">REGISTRATION COMPLETE</span>
                                    <h1>Account created!</h1>
                                    <p>
                                        Your <?= $safeRole ?> account has been
                                        registered successfully.
                                    </p>
                                </div>

                                <div class="sc-success-details">
                                    <strong>You're ready to get started.</strong>
                                    <p>
                                        Sign in using the email address and password
                                        you registered with.
                                    </p>
                                </div>

                                <a class="sc-login-submit sc-success-link" href="index.php">
                                    <span>Go to Sign In</span>
                                    <span class="sc-submit-arrow" aria-hidden="true">→</span>
                                </a>

                                <footer class="sc-login-footer">
                                    &copy; <?= date('Y') ?> SmartClass Classroom Reservation System
                                </footer>
                            </div>

                            <aside class="sc-login-right">
                                <div class="sc-decor sc-decor-top"></div>
                                <div class="sc-decor sc-decor-bottom"></div>
                                <div class="sc-dot-pattern" aria-hidden="true"></div>

                                <div class="sc-promo-content">
                                    <span class="sc-promo-badge">
                                        <span class="sc-promo-status-dot"></span>
                                        SMARTER CLASSROOM MANAGEMENT
                                    </span>

                                    <h2>Welcome to<br>SmartClass!</h2>

                                    <p class="sc-promo-description">
                                        A simple and efficient classroom reservation
                                        system for students and teachers.
                                    </p>

                                    <div class="sc-classroom-art" aria-hidden="true">
                                        <div class="sc-art-window">
                                            <span></span><span></span>
                                        </div>
                                        <div class="sc-art-clock"><span></span></div>
                                        <div class="sc-art-board">
                                            <div class="sc-art-board-line"></div>
                                            <div class="sc-art-board-check">✓</div>
                                            <div class="sc-art-board-tray"></div>
                                        </div>
                                        <div class="sc-art-plant">
                                            <span class="sc-leaf leaf-one"></span>
                                            <span class="sc-leaf leaf-two"></span>
                                            <span class="sc-leaf leaf-three"></span>
                                            <span class="sc-plant-pot"></span>
                                        </div>
                                        <div class="sc-art-desk desk-back">
                                            <div class="sc-desk-top"></div>
                                            <div class="sc-desk-leg left"></div>
                                            <div class="sc-desk-leg right"></div>
                                        </div>
                                        <div class="sc-art-chair chair-back">
                                            <div class="sc-chair-back"></div>
                                            <div class="sc-chair-seat"></div>
                                            <div class="sc-chair-leg left"></div>
                                            <div class="sc-chair-leg right"></div>
                                        </div>
                                        <div class="sc-art-desk desk-front">
                                            <div class="sc-desk-top"></div>
                                            <div class="sc-desk-leg left"></div>
                                            <div class="sc-desk-leg right"></div>
                                        </div>
                                        <div class="sc-art-chair chair-front">
                                            <div class="sc-chair-back"></div>
                                            <div class="sc-chair-seat"></div>
                                            <div class="sc-chair-leg left"></div>
                                            <div class="sc-chair-leg right"></div>
                                        </div>
                                        <div class="sc-art-floor"></div>
                                    </div>
                                </div>
                            </aside>
                        </section>
                    </main>
                    </body>
                    </html>
                    <?php
                    exit;
                }
            } catch (PDOException $e) {
                error_log($e->getMessage());

                if ($e->getCode() === '23000') {
                    $error = 'That email address is already registered.';
                } else {
                    $error = 'Unable to create your account right now. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create <?= htmlspecialchars(ucfirst($role), ENT_QUOTES, 'UTF-8') ?> Account | SmartClass</title>

    <link rel="stylesheet" href="assets/css/style.css?v=smartclass-register-6">
    <script src="assets/js/script.js?v=smartclass-password-toggle-6" defer></script>
</head>
<body class="sc-login-body">

<main class="sc-login-page">
    <section class="sc-login-card">

        <!-- LEFT: REGISTRATION FORM -->
        <div class="sc-login-left">

            <a href="index.php" class="sc-login-brand">
                <span class="sc-login-logo">SC</span>
                <span class="sc-login-brand-text">
                    <strong>SmartClass</strong>
                    <small>Classroom Reservation System</small>
                </span>
            </a>

            <div class="sc-login-heading">
                <span class="sc-login-eyebrow">JOIN SMARTCLASS TODAY</span>
                <h1>Create your account</h1>
                <p>Register to start using the classroom reservation system.</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="sc-login-error" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST">
                <input
                    type="hidden"
                    name="register_csrf_token"
                    value="<?= htmlspecialchars($_SESSION['register_csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
                >

                <div class="sc-login-field">
                    <label for="role">Create account as</label>
                    <div class="sc-login-input-wrap">
                        <span class="sc-field-icon" aria-hidden="true">♙</span>
                        <select id="role" name="role" required>
                            <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>Student</option>
                            <option value="teacher" <?= $role === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                        </select>
                        <span class="sc-select-arrow" aria-hidden="true">⌄</span>
                    </div>
                </div>

                <div class="sc-login-field">
                    <label for="full_name">Full name</label>
                    <div class="sc-login-input-wrap">
                        <span class="sc-field-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <circle cx="12" cy="8" r="4"/>
                                <path d="M4 21v-2a8 8 0 0 1 16 0v2z"/>
                            </svg>
                        </span>
                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            placeholder="Enter your full name"
                            value="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>"
                            maxlength="100"
                            autocomplete="name"
                            required
                        >
                    </div>
                </div>

                <div class="sc-login-field">
                    <label for="email">Email address</label>
                    <div class="sc-login-input-wrap">
                        <span class="sc-field-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <rect x="3" y="5" width="18" height="14" rx="2"/>
                                <path d="m4 7 8 6 8-6"/>
                            </svg>
                        </span>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Enter your email address"
                            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                            maxlength="150"
                            autocomplete="email"
                            required
                        >
                    </div>
                </div>

                <!-- PASSWORD -->
                <div class="sc-login-field">
                    <label for="password">Password (at least 8 characters)</label>

                    <div class="sc-login-input-wrap sc-password-wrap">
                        <span class="sc-field-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <rect x="4" y="10" width="16" height="11" rx="2"/>
                                <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                                <circle cx="12" cy="15" r="1"/>
                            </svg>
                        </span>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Create a password"
                            minlength="8"
                            maxlength="128"
                            autocomplete="new-password"
                            required
                        >

                        <button
                            type="button"
                            id="togglePassword"
                            class="sc-password-toggle"
                            aria-label="Show password"
                            aria-controls="password"
                            aria-pressed="false"
                            onclick="togglePasswordVisibility('password', this)"
                        >
                            <svg
                                id="eyeIcon"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- CONFIRM PASSWORD -->
                <div class="sc-login-field">
                    <label for="confirm_password">Confirm password</label>

                    <div class="sc-login-input-wrap sc-password-wrap">
                        <span class="sc-field-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <rect x="4" y="10" width="16" height="11" rx="2"/>
                                <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                                <path d="m9 15 2 2 4-4"/>
                            </svg>
                        </span>

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Enter your password again"
                            minlength="8"
                            maxlength="128"
                            autocomplete="new-password"
                            required
                        >

                        <button
                            type="button"
                            id="toggleConfirmPassword"
                            class="sc-password-toggle"
                            aria-label="Show confirm password"
                            aria-controls="confirm_password"
                            aria-pressed="false"
                            onclick="togglePasswordVisibility('confirm_password', this)"
                        >
                            <svg
                                id="confirmEyeIcon"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="sc-login-submit">
                    <span>Create Account</span>
                    <span class="sc-submit-arrow" aria-hidden="true">→</span>
                </button>
            </form>

            <div class="sc-login-register">
                <p>Already have a SmartClass account?</p>

                <div class="sc-register-links">
                    <a href="index.php">← Back to Sign In</a>

                    <a href="register.php?role=<?= $role === 'student' ? 'teacher' : 'student' ?>">
                        Register as <?= $role === 'student' ? 'Teacher' : 'Student' ?>
                    </a>
                </div>
            </div>

            <footer class="sc-login-footer">
                &copy; <?= date('Y') ?> SmartClass Classroom Reservation System
            </footer>
        </div>

        <!-- RIGHT: WELCOME PANEL -->
        <aside class="sc-login-right">
            <div class="sc-decor sc-decor-top"></div>
            <div class="sc-decor sc-decor-bottom"></div>
            <div class="sc-dot-pattern" aria-hidden="true"></div>

            <div class="sc-promo-content">
                <span class="sc-promo-badge">
                    <span class="sc-promo-status-dot"></span>
                    YOUR SMARTCLASS JOURNEY STARTS HERE
                </span>

                <h2>
                    <?= $role === 'teacher'
                        ? 'Welcome, Teacher!'
                        : 'Hello, Student!' ?>
                </h2>

                <p class="sc-promo-description">
                    <?= $role === 'teacher'
                        ? 'Create your account to manage classroom reservations and organize your teaching schedule.'
                        : 'Create your account to find available classrooms and manage your reservations with ease.' ?>
                </p>

                <div class="sc-classroom-art" aria-hidden="true">
                    <div class="sc-art-window">
                        <span></span><span></span>
                    </div>
                    <div class="sc-art-clock"><span></span></div>
                    <div class="sc-art-board">
                        <div class="sc-art-board-line"></div>
                        <div class="sc-art-board-check">✓</div>
                        <div class="sc-art-board-tray"></div>
                    </div>
                    <div class="sc-art-plant">
                        <span class="sc-leaf leaf-one"></span>
                        <span class="sc-leaf leaf-two"></span>
                        <span class="sc-leaf leaf-three"></span>
                        <span class="sc-plant-pot"></span>
                    </div>
                    <div class="sc-art-desk desk-back">
                        <div class="sc-desk-top"></div>
                        <div class="sc-desk-leg left"></div>
                        <div class="sc-desk-leg right"></div>
                    </div>
                    <div class="sc-art-chair chair-back">
                        <div class="sc-chair-back"></div>
                        <div class="sc-chair-seat"></div>
                        <div class="sc-chair-leg left"></div>
                        <div class="sc-chair-leg right"></div>
                    </div>
                    <div class="sc-art-desk desk-front">
                        <div class="sc-desk-top"></div>
                        <div class="sc-desk-leg left"></div>
                        <div class="sc-desk-leg right"></div>
                    </div>
                    <div class="sc-art-chair chair-front">
                        <div class="sc-chair-back"></div>
                        <div class="sc-chair-seat"></div>
                        <div class="sc-chair-leg left"></div>
                        <div class="sc-chair-leg right"></div>
                    </div>
                    <div class="sc-art-floor"></div>
                </div>
            </div>
        </aside>

    </section>
</main>

</body>
</html>
