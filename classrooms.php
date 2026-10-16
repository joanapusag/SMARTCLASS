<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
require_once __DIR__ . '/../config/database.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Database connection failed. Please check config/database.php.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$errors = [];
$success = $_SESSION['classroom_success'] ?? '';
unset($_SESSION['classroom_success']);

$editRoom = null;
$roomName = '';
$building = '';
$capacityInput = '';
$equipment = '';
$description = '';

function h($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function validClassroomId($value): int|false
{
    return filter_var(
        $value,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
}

function classroomInputErrors(
    string $roomName,
    string $building,
    string $capacityInput,
    string $equipment,
    string $description
): array {
    $errors = [];

    if ($roomName === '' || strlen($roomName) > 100) {
        $errors[] = 'Enter a classroom name (up to 100 characters).';
    }

    if ($building === '' || strlen($building) > 100) {
        $errors[] = 'Enter a building name (up to 100 characters).';
    }

    $capacity = filter_var(
        $capacityInput,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 10000]]
    );

    if ($capacity === false) {
        $errors[] = 'Capacity must be a whole number between 1 and 10000.';
    }

    if (strlen($equipment) > 5000 || strlen($description) > 5000) {
        $errors[] = 'Equipment and description must each be at most 5000 characters.';
    }

    return $errors;
}

/*
|--------------------------------------------------------------------------
| Handle POST Actions
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($csrfToken, $token)) {
        http_response_code(403);
        exit('Invalid request token. Please refresh the page and try again.');
    }

    $action = $_POST['action'] ?? '';

    try {
        /*
        |--------------------------------------------------------------------------
        | Add or Update Classroom
        |--------------------------------------------------------------------------
        */
        if ($action === 'add' || $action === 'update') {
            $roomName = trim((string) ($_POST['room_name'] ?? ''));
            $building = trim((string) ($_POST['building'] ?? ''));
            $capacityInput = trim((string) ($_POST['capacity'] ?? ''));
            $equipment = trim((string) ($_POST['equipment'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));

            $errors = classroomInputErrors(
                $roomName,
                $building,
                $capacityInput,
                $equipment,
                $description
            );

            $id = null;

            if ($action === 'update') {
                $id = validClassroomId($_POST['classroom_id'] ?? '');

                if ($id === false) {
                    $errors[] = 'Invalid classroom ID.';
                }
            }

            if (!$errors) {
                $capacity = (int) $capacityInput;

                if ($action === 'add') {
                    // New classrooms start as available.
                    $stmt = $pdo->prepare(
                        'INSERT INTO classrooms
                            (
                                room_name,
                                building,
                                capacity,
                                equipment,
                                description,
                                status
                            )
                         VALUES
                            (
                                :room,
                                :building,
                                :capacity,
                                :equipment,
                                :description,
                                :status
                            )'
                    );

                    $stmt->execute([
                        'room' => $roomName,
                        'building' => $building,
                        'capacity' => $capacity,
                        'equipment' => $equipment,
                        'description' => $description,
                        'status' => 'available'
                    ]);

                    $_SESSION['classroom_success'] =
                        'Classroom added successfully.';
                } else {
                    // Editing details does not change classroom status.
                    $stmt = $pdo->prepare(
                        'UPDATE classrooms
                         SET room_name = :room,
                             building = :building,
                             capacity = :capacity,
                             equipment = :equipment,
                             description = :description
                         WHERE classroom_id = :id'
                    );

                    $stmt->execute([
                        'room' => $roomName,
                        'building' => $building,
                        'capacity' => $capacity,
                        'equipment' => $equipment,
                        'description' => $description,
                        'id' => $id
                    ]);

                    $_SESSION['classroom_success'] =
                        'Classroom information updated successfully.';
                }

                header('Location: classrooms.php');
                exit;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Update Classroom Status
        |--------------------------------------------------------------------------
        */
        elseif ($action === 'status') {
            $id = validClassroomId($_POST['classroom_id'] ?? '');
            $newStatus = (string) ($_POST['new_status'] ?? '');

            if (
                $id === false ||
                !in_array($newStatus, ['available', 'maintenance'], true)
            ) {
                $errors[] = 'Invalid classroom or status.';
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE classrooms
                     SET status = :status
                     WHERE classroom_id = :id'
                );

                $stmt->execute([
                    'status' => $newStatus,
                    'id' => $id
                ]);

                if ($stmt->rowCount() === 0) {
                    $check = $pdo->prepare(
                        'SELECT classroom_id
                         FROM classrooms
                         WHERE classroom_id = :id'
                    );

                    $check->execute(['id' => $id]);

                    if (!$check->fetchColumn()) {
                        $errors[] = 'The selected classroom was not found.';
                    } else {
                        $_SESSION['classroom_success'] =
                            $newStatus === 'available'
                                ? 'Classroom marked as available.'
                                : 'Classroom marked as under maintenance.';

                        header('Location: classrooms.php');
                        exit;
                    }
                } else {
                    $_SESSION['classroom_success'] =
                        $newStatus === 'available'
                            ? 'Classroom marked as available.'
                            : 'Classroom marked as under maintenance.';

                    header('Location: classrooms.php');
                    exit;
                }
            }
        } else {
            $errors[] = 'Unknown action.';
        }
    } catch (PDOException $e) {
        error_log('Classroom management error: ' . $e->getMessage());

        $errors[] = $e->getCode() === '23000'
            ? 'The classroom could not be saved because of a duplicate value or a related database constraint.'
            : 'The operation failed. Please check the database and try again.';
    } catch (Throwable $e) {
        error_log('Classroom management error: ' . $e->getMessage());
        $errors[] = 'An unexpected error occurred. Please try again.';
    }
}

/*
|--------------------------------------------------------------------------
| Load Classroom for Editing
|--------------------------------------------------------------------------
*/
$editId = validClassroomId($_GET['edit'] ?? '');

if ($editId !== false && $editId !== null) {
    try {
        $stmt = $pdo->prepare(
            'SELECT classroom_id,
                    room_name,
                    building,
                    capacity,
                    equipment,
                    description
             FROM classrooms
             WHERE classroom_id = :id'
        );

        $stmt->execute(['id' => $editId]);
        $editRoom = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$editRoom) {
            $errors[] = 'The classroom you selected for editing was not found.';
        } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$errors) {
            $roomName = $editRoom['room_name'];
            $building = $editRoom['building'];
            $capacityInput = (string) $editRoom['capacity'];
            $equipment = $editRoom['equipment'] ?? '';
            $description = $editRoom['description'] ?? '';
        }
    } catch (PDOException $e) {
        error_log('Load classroom for editing error: ' . $e->getMessage());
        $errors[] = 'Unable to load classroom details.';
    }
}

/*
|--------------------------------------------------------------------------
| Preserve Submitted Form Values if Validation Fails
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    in_array($_POST['action'] ?? '', ['add', 'update'], true) &&
    $errors
) {
    $roomName = trim((string) ($_POST['room_name'] ?? ''));
    $building = trim((string) ($_POST['building'] ?? ''));
    $capacityInput = trim((string) ($_POST['capacity'] ?? ''));
    $equipment = trim((string) ($_POST['equipment'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));

    if (($_POST['action'] ?? '') === 'update') {
        $postedId = validClassroomId($_POST['classroom_id'] ?? '');

        if ($postedId !== false) {
            $stmt = $pdo->prepare(
                'SELECT classroom_id,
                        room_name,
                        building,
                        capacity,
                        equipment,
                        description
                 FROM classrooms
                 WHERE classroom_id = :id'
            );

            $stmt->execute(['id' => $postedId]);
            $editRoom = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load All Classrooms
|--------------------------------------------------------------------------
*/
try {
    $stmt = $pdo->query(
        'SELECT classroom_id,
                room_name,
                building,
                capacity,
                equipment,
                description,
                status,
                created_at
         FROM classrooms
         ORDER BY building, room_name'
    );

    $classrooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Classroom list error: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load classrooms. Please check the database.');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classrooms | SmartClass</title>
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

        <nav class="sc-navigation" aria-label="Admin navigation">
            <a href="dashboard.php" class="sc-nav-link">
                <span class="sc-nav-icon" aria-hidden="true">⌂</span>
                <span>Dashboard</span>
            </a>

            <a href="classrooms.php" class="sc-nav-link active" aria-current="page">
                <span class="sc-nav-icon" aria-hidden="true">▦</span>
                <span>Manage Classrooms</span>
            </a>

            <a href="reservations.php" class="sc-nav-link">
                <span class="sc-nav-icon" aria-hidden="true">▤</span>
                <span>Manage Reservations</span>
            </a>

            <a href="accounts.php" class="sc-nav-link">
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
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
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
                <p class="sc-topbar-label">ADMINISTRATION</p>
                <h1>Manage Classrooms</h1>
            </div>

            <div class="sc-user">
                <span class="sc-avatar" aria-hidden="true">A</span>
                <span class="sc-user-info">
                    <strong><?= h($_SESSION['full_name'] ?? 'Administrator') ?></strong>
                    <small>Administrator</small>
                </span>
            </div>
        </header>
        
        <!-- Manage Classrooms Welcome Banner -->
        <section class="sc-welcome classroom-welcome">
            <div class="sc-welcome-content">
                <span class="sc-welcome-tag">CLASSROOM MANAGEMENT</span>

                <h2>Manage Your Classrooms</h2>

                <p>
                    Add new classrooms, update room information, and manage
                    classroom availability and maintenance status in one place.
                </p>
            </div>

            <div class="sc-welcome-art" aria-hidden="true">
                <span>▦</span>
                <span>▤</span>
                <span>✓</span>
            </div>
        </section>

        <?php if ($success !== ''): ?>
            <div class="notice success" role="status">
                <?= h($success) ?>
            </div>
        <?php endif; ?>

        <?php foreach ($errors as $error): ?>
            <div class="notice error" role="alert">
                <?= h($error) ?>
            </div>
        <?php endforeach; ?>

        <section class="panel" id="classroom-form">
            <div class="section-heading">
                <div>
                    <h2><?= $editRoom ? 'Edit Classroom' : 'Add a Classroom' ?></h2>
                    <p>
                        <?= $editRoom
                            ? 'Update the selected classroom information.'
                            : 'Enter the details of the classroom you want to register.' ?>
                    </p>
                </div>
            </div>

            <form
                method="POST"
                action="classrooms.php<?= $editRoom ? '?edit=' . (int) $editRoom['classroom_id'] . '#classroom-form' : '' ?>"
                class="room-form"
            >
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

                <input
                    type="hidden"
                    name="action"
                    value="<?= $editRoom ? 'update' : 'add' ?>"
                >

                <?php if ($editRoom): ?>
                    <input
                        type="hidden"
                        name="classroom_id"
                        value="<?= (int) $editRoom['classroom_id'] ?>"
                    >
                <?php endif; ?>

                <div class="field">
                    <label for="room_name">Classroom Name *</label>
                    <input
                        id="room_name"
                        name="room_name"
                        maxlength="100"
                        value="<?= h($roomName) ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="building">Building *</label>
                    <input
                        id="building"
                        name="building"
                        maxlength="100"
                        value="<?= h($building) ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="capacity">Room Capacity *</label>
                    <input
                        id="capacity"
                        name="capacity"
                        type="number"
                        min="1"
                        max="10000"
                        step="1"
                        value="<?= h($capacityInput) ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="equipment">Equipment</label>
                    <input
                        id="equipment"
                        name="equipment"
                        maxlength="5000"
                        value="<?= h($equipment) ?>"
                        placeholder="Projector, computers, whiteboard"
                    >
                </div>

                <div class="field full">
                    <label for="description">Description (Optional)</label>
                    <textarea
                        id="description"
                        name="description"
                        maxlength="5000"
                        rows="3"
                        placeholder="Describe the classroom and its intended use."
                    ><?= h($description) ?></textarea>
                </div>

                <div class="field full form-actions">
                    <button type="submit" class="primary-button">
                        <?= $editRoom ? 'Save Changes' : '+ Add Classroom' ?>
                    </button>

                    <?php if ($editRoom): ?>
                        <a href="classrooms.php" class="secondary-button">
                            Cancel Editing
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="section-heading">
                <div>
                    <h2>Registered Classrooms</h2>
                    <p>Edit classroom details or update classroom availability.</p>
                </div>

                <span class="count-badge">
                    <?= count($classrooms) ?> classroom(s)
                </span>
            </div>

            <?php if (!$classrooms): ?>
                <p class="empty-state">
                    No classrooms have been added yet. Use the form above to add one.
                </p>
            <?php else: ?>
                <div class="room-list">
                    <?php foreach ($classrooms as $room): ?>
                        <?php
                        $roomStatus = strtolower(
                            (string) ($room['status'] ?? 'available')
                        );

                        $isAvailable = $roomStatus === 'available';

                        $statusClass = in_array(
                            $roomStatus,
                            ['available', 'maintenance'],
                            true
                        ) ? $roomStatus : '';
                        ?>

                        <article class="room-card">
                            <div class="room-card-top">
                                <div>
                                    <h3><?= h($room['room_name']) ?></h3>
                                    <p class="room-building"><?= h($room['building']) ?></p>
                                </div>

                                <span class="status <?= h($statusClass) ?>">
                                    <?= h(
                                        $roomStatus === 'maintenance'
                                            ? 'Under Maintenance'
                                            : ucfirst($roomStatus)
                                    ) ?>
                                </span>
                            </div>

                            <div class="room-details">
                                <p>
                                    <strong>Capacity:</strong>
                                    <?= (int) $room['capacity'] ?> people
                                </p>

                                <p>
                                    <strong>Equipment:</strong>
                                    <?= h($room['equipment'] ?: 'Not specified') ?>
                                </p>

                                <?php if (!empty($room['description'])): ?>
                                    <p>
                                        <strong>Description:</strong>
                                        <?= h($room['description']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:16px;">
                                <a
                                    class="secondary-button"
                                    href="classrooms.php?edit=<?= (int) $room['classroom_id'] ?>#classroom-form"
                                >
                                    Edit Classroom
                                </a>

                                <form method="POST" action="classrooms.php" class="room-status-form">
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= h($csrfToken) ?>"
                                    >

                                    <input type="hidden" name="action" value="status">

                                    <input
                                        type="hidden"
                                        name="classroom_id"
                                        value="<?= (int) $room['classroom_id'] ?>"
                                    >

                                    <?php if ($isAvailable): ?>
                                        <input type="hidden" name="new_status" value="maintenance">

                                        <button class="secondary-button" type="submit">
                                            Mark Under Maintenance
                                        </button>
                                    <?php else: ?>
                                        <input type="hidden" name="new_status" value="available">

                                        <button class="secondary-button" type="submit">
                                            Mark Available
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
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