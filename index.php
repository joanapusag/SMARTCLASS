
<?php
require_once __DIR__ . '/includes/auth.php';

redirectToDashboard();

if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === ''
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartClass | Sign In</title>

    <link rel="stylesheet" href="assets/css/style.css?v=smartclass-login-4">
    <script src="assets/js/script.js?v=smartclass-password-toggle-6" defer></script>
</head>
<body class="sc-login-body">

<main class="sc-login-page">

    <section class="sc-login-card">

        <!-- LEFT PANEL: LOGIN FORM -->
        <div class="sc-login-left">

            <a href="index.php" class="sc-login-brand">
                <span class="sc-login-logo">SC</span>
                <span class="sc-login-brand-text">
                    <strong>SmartClass</strong>
                    <small>Classroom Reservation System</small>
                </span>
            </a>

            <div class="sc-login-heading">
                <span class="sc-login-eyebrow">YOUR CLASSROOM, ORGANIZED</span>
                <h1>Welcome back!</h1>
                <p>Sign in to manage your classroom reservations.</p>
            </div>

            <?php if (is_string($error) && $error !== ''): ?>
                <div class="sc-login-error" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form id="loginForm" action="login.php" method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION['csrf_token'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <div class="sc-login-field">
                    <label for="role">Sign in as</label>

                    <div class="sc-login-input-wrap">
                        <span class="sc-field-icon" aria-hidden="true">
                            ♙
                        </span>

                        <select id="role" name="role" required>
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="admin">Administrator</option>
                        </select>

                        <span class="sc-select-arrow" aria-hidden="true">⌄</span>
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
                            placeholder="Enter your school email"
                            autocomplete="username"
                            maxlength="150"
                            required
                        >
                    </div>
                </div>

                <!-- PASSWORD -->
                <div class="sc-login-field">
                    <label for="password">Password</label>

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
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            maxlength="128"
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

                <div class="sc-login-options">
                    <span class="sc-login-secure">
                        <span aria-hidden="true">✓</span>
                        Secure sign in
                    </span>

                    <span class="sc-login-role-note">
                        Use your registered account
                    </span>
                </div>

                <?php if (!is_string($error) || $error === ''): ?>
                    <p
                        id="formMessage"
                        class="form-message sc-login-js-message"
                        role="alert"
                        aria-live="polite"
                    ></p>
                <?php endif; ?>

                <button type="submit" class="sc-login-submit">
                    <span>Sign In</span>
                    <span class="sc-submit-arrow" aria-hidden="true">→</span>
                </button>

            </form>

            <div class="sc-login-register">
                <p>New to SmartClass?</p>

                <div class="sc-register-links">
                    <a href="register.php?role=student">
                        <span aria-hidden="true">♙</span>
                        Create Student Account
                    </a>

                    <a href="register.php?role=teacher">
                        <span aria-hidden="true">▣</span>
                        Create Teacher Account
                    </a>
                </div>
            </div>

            <footer class="sc-login-footer">
                &copy; <?= date('Y') ?> SmartClass Classroom Reservation System
            </footer>

        </div>

        <!-- RIGHT PANEL: BLUE WELCOME DESIGN -->
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

                <!-- CSS classroom illustration -->
                <div class="sc-classroom-art" aria-hidden="true">

                    <div class="sc-art-window">
                        <span></span>
                        <span></span>
                    </div>

                    <div class="sc-art-clock">
                        <span></span>
                    </div>

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
