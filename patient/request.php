<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/notifications.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../auth/login.php');
    exit;
}

$patientName = $_SESSION['name'] ?? 'Patient';

/*
|--------------------------------------------------------------------------
| Get Patient
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT patient_id, patient_number
     FROM patients
     WHERE user_id = ?"
);

$stmt->execute([$_SESSION['user_id']]);

$patient = $stmt->fetch();

if (!$patient) {
    die('Patient record not found.');
}

$patientId = $patient['patient_id'];
$patientNumber = $patient['patient_number'] ?? 'Not assigned';

/*
|--------------------------------------------------------------------------
| Handle Appointment Request
|--------------------------------------------------------------------------
*/

$message = '';
$messageType = '';

$preferredDate = '';
$preferredStartTime = '';
$preferredEndTime = '';
$reason = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $preferredDate = trim(
        $_POST['preferred_date'] ?? ''
    );

    $preferredStartTime = trim(
        $_POST['preferred_start_time'] ?? ''
    );

    $preferredEndTime = trim(
        $_POST['preferred_end_time'] ?? ''
    );

    $reason = trim(
        $_POST['reason'] ?? ''
    );

    $dateValue = DateTime::createFromFormat('!Y-m-d', $preferredDate);
    $dateIsValid = $dateValue && $dateValue->format('Y-m-d') === $preferredDate;
    $timePattern = '/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/';

    if (
        !$dateIsValid ||
        !preg_match($timePattern, $preferredStartTime) ||
        !preg_match($timePattern, $preferredEndTime) ||
        strlen($reason) > 1000
    ) {

        $message = 'Please complete the preferred date and time.';
        $messageType = 'error';

    } elseif ($dateValue < new DateTime('today')) {

        $message = 'Please choose today or a future date.';
        $messageType = 'error';

    } elseif ($reason === '') {

        $message = 'Please enter the reason for your appointment.';
        $messageType = 'error';

    } elseif (
        strtotime($preferredEndTime)
        <= strtotime($preferredStartTime)
    ) {

        $message = 'The preferred end time must be later than the start time.';
        $messageType = 'error';

    } else {

        $stmt = $pdo->prepare(
            "INSERT INTO appointments
            (
                patient_id,
                preferred_date,
                preferred_start_time,
                preferred_end_time,
                reason,
                status
            )
            VALUES (?, ?, ?, ?, ?, 'pending')"
        );

        $stmt->execute([
            $patientId,
            $preferredDate,
            $preferredStartTime,
            $preferredEndTime,
            $reason
        ]);

        audit_log('appointment.request.created', ['appointment_id' => (int) $pdo->lastInsertId()]);
        notify_staff($pdo, (int) $pdo->lastInsertId(), 'appointment.request', 'New appointment request', 'A patient submitted an appointment request for review.');

        $message = 'Appointment request submitted successfully.';
        $messageType = 'success';

        $preferredDate = '';
        $preferredStartTime = '';
        $preferredEndTime = '';
        $reason = '';
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Request Appointment - The Tooth Lounge</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            color: #333;

            background:
                linear-gradient(
                    rgba(0, 135, 155, 0.78),
                    rgba(20, 85, 150, 0.78)
                ),
                url("https://images.unsplash.com/photo-1606811971618-4486d14f3f99?auto=format&fit=crop&w=1920&q=80");

            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }

        .header {
            min-height: 75px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 12px 35px;

            background: rgba(255, 255, 255, 0.96);

            box-shadow:
                0 3px 15px rgba(0, 0, 0, 0.15);
        }

        .logo {
            display: flex;
            align-items: center;

            gap: 12px;

            color: #167b8c;

            font-size: 21px;
            font-weight: bold;
        }

        .logo-icon {
            width: 43px;
            height: 43px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #e6f7fa;

            border-radius: 50%;

            color: #167b8c;

            font-size: 21px;
        }

        .nav {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .nav a {
            display: flex;
            align-items: center;

            gap: 7px;

            padding: 10px 13px;

            border-radius: 8px;

            color: #555;

            text-decoration: none;

            font-size: 14px;

            transition: 0.2s;
        }

        .nav a:hover {
            background: #e6f7fa;
            color: #167b8c;
        }

        .logout {
            background: #167b8c !important;
            color: white !important;
            margin-left: 5px;
        }

        .logout:hover {
            background: #126c79 !important;
        }

        .container {
            width: 1000px;
            max-width: 92%;
            margin: 40px auto;
        }

        .welcome {
            margin-bottom: 25px;
            color: white;
        }

        .welcome h1 {
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .welcome p {
            font-size: 16px;
            opacity: 0.9;
        }

        .patient-number {
            display: inline-block;

            margin-top: 10px;

            padding: 7px 12px;

            background: rgba(255, 255, 255, 0.18);

            border: 1px solid rgba(255, 255, 255, 0.3);

            border-radius: 20px;

            font-size: 13px;
        }

        .panel {
            padding: 30px;

            background: rgba(255, 255, 255, 0.96);

            border-radius: 15px;

            box-shadow:
                0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .panel-header {
            display: flex;

            align-items: center;

            gap: 15px;

            margin-bottom: 25px;
        }

        .panel-icon {
            width: 55px;
            height: 55px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #e6f7fa;

            color: #167b8c;

            border-radius: 14px;

            font-size: 23px;
        }

        .panel-header h2 {
            color: #167b8c;
            font-size: 22px;
        }

        .panel-header p {
            margin-top: 5px;
            color: #777;
            font-size: 13px;
        }

        .message {
            padding: 13px 16px;

            margin-bottom: 20px;

            border-radius: 9px;

            font-size: 14px;
        }

        .success {
            background: #e8f8f0;
            border: 1px solid #b8e8d0;
            color: #287a53;
        }

        .error {
            background: #fdecec;
            border: 1px solid #f0bcbc;
            color: #a33a3a;
        }

        .patient-id-box {
            display: flex;

            align-items: center;

            gap: 12px;

            padding: 15px;

            margin-bottom: 25px;

            background: #f1fbfc;

            border: 1px solid #d7eef1;

            border-radius: 10px;
        }

        .patient-id-box i {
            color: #167b8c;
            font-size: 20px;
        }

        .patient-id-box span {
            color: #777;
            font-size: 12px;

            display: block;

            margin-bottom: 3px;
        }

        .patient-id-box strong {
            color: #167b8c;
            font-size: 15px;
        }

        .form-grid {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full {
            grid-column: span 2;
        }

        label {
            margin-bottom: 7px;

            color: #555;

            font-size: 13px;

            font-weight: 600;
        }

        label i {
            margin-right: 5px;
            color: #167b8c;
        }

        input,
        textarea {
            width: 100%;

            padding: 12px 13px;

            border: 1px solid #d5d5d5;

            border-radius: 8px;

            background: white;

            color: #333;

            font-family: Arial, sans-serif;

            font-size: 14px;

            outline: none;

            transition: 0.2s;
        }

        input:focus,
        textarea:focus {
            border-color: #167b8c;

            box-shadow:
                0 0 0 3px
                rgba(22, 123, 140, 0.10);
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        .help {
            margin-top: 6px;

            color: #888;

            font-size: 12px;
        }

        .actions {
            display: flex;

            gap: 12px;

            margin-top: 25px;

            padding-top: 20px;

            border-top: 1px solid #eeeeee;
        }

        .button {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            gap: 8px;

            padding: 12px 20px;

            border: none;

            border-radius: 8px;

            text-decoration: none;

            font-size: 14px;

            cursor: pointer;

            transition: 0.2s;
        }

        .submit-button {
            background: #167b8c;
            color: white;
        }

        .submit-button:hover {
            background: #126c79;
            transform: translateY(-1px);
        }

        .back-button {
            background: #eef3f4;
            color: #555;
        }

        .back-button:hover {
            background: #e1eaec;
            color: #167b8c;
        }

        @media (max-width: 950px) {

            .header {
                padding: 12px 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: span 1;
            }

        }

        @media (max-width: 650px) {

            .header {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }

            .nav {
                flex-wrap: wrap;
                justify-content: center;
            }

            .container {
                max-width: 94%;
                margin: 25px auto;
            }

            .welcome h1 {
                font-size: 26px;
            }

            .panel {
                padding: 20px;
            }

            .actions {
                flex-direction: column;
            }

            .button {
                width: 100%;
            }

        }

    </style>

</head>

<body>

<header class="header">

    <div class="logo">

        <div class="logo-icon">
            <i class="fa-solid fa-tooth"></i>
        </div>

        The Tooth Lounge

    </div>

    <nav class="nav">

        <a href="dashboard.php">
            <i class="fa-solid fa-gauge-high"></i>
            Dashboard
        </a>

        <a href="profile.php">
            <i class="fa-solid fa-user"></i>
            Profile
        </a>

        <a href="appointments.php">
            <i class="fa-solid fa-calendar-check"></i>
            Appointments
        </a>

        <a href="visit_history.php">
            <i class="fa-solid fa-file-medical"></i>
            Records
        </a>

        <a
            href="../auth/logout.php"
            class="logout"
        >
            <i class="fa-solid fa-right-from-bracket"></i>
            Logout
        </a>

    </nav>

</header>

<main class="container">

    <section class="welcome">

        <h1>
            Request Appointment
        </h1>

        <p>
            Submit your preferred schedule for a dental appointment.
        </p>

        <span class="patient-number">

            <i class="fa-solid fa-id-card"></i>

            Patient No:
            <?= htmlspecialchars($patientNumber) ?>

        </span>

    </section>

    <section class="panel">

        <div class="panel-header">

            <div class="panel-icon">
                <i class="fa-solid fa-calendar-plus"></i>
            </div>

            <div>

                <h2>
                    Appointment Request
                </h2>

                <p>
                    Choose your preferred date and time.
                </p>

            </div>

        </div>

        <?php if ($message): ?>

            <div class="message <?= htmlspecialchars($messageType) ?>">

                <i class="fa-solid
                    <?= $messageType === 'success'
                        ? 'fa-circle-check'
                        : 'fa-circle-exclamation'
                    ?>">
                </i>

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>

        <div class="patient-id-box">

            <i class="fa-solid fa-id-card"></i>

            <div>

                <span>
                    Patient Number
                </span>

                <strong>
                    <?= htmlspecialchars($patientNumber) ?>
                </strong>

            </div>

        </div>

        <form method="POST">
<?= csrf_field() ?>

            <div class="form-grid">

                <div class="form-group">

                    <label for="preferred_date">

                        <i class="fa-solid fa-calendar-day"></i>

                        Preferred Date

                    </label>

                    <input
                        type="date"
                        id="preferred_date"
                        name="preferred_date"
                        value="<?= htmlspecialchars($preferredDate) ?>"
                        min="<?= date('Y-m-d') ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="preferred_start_time">

                        <i class="fa-solid fa-clock"></i>

                        Preferred Start Time

                    </label>

                    <input
                        type="time"
                        id="preferred_start_time"
                        name="preferred_start_time"
                        value="<?= htmlspecialchars($preferredStartTime) ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="preferred_end_time">

                        <i class="fa-solid fa-clock"></i>

                        Preferred End Time

                    </label>

                    <input
                        type="time"
                        id="preferred_end_time"
                        name="preferred_end_time"
                        value="<?= htmlspecialchars($preferredEndTime) ?>"
                        required
                    >

                </div>

                <div class="form-group full">

                    <label for="reason">

                        <i class="fa-solid fa-comment-medical"></i>

                        Reason for Appointment

                    </label>

                    <textarea
                        id="reason"
                        name="reason"
                        placeholder="Example: Dental cleaning, toothache, consultation..."
                        required
                    ><?= htmlspecialchars($reason) ?></textarea>

                    <span class="help">
                        Please briefly describe the reason for your appointment.
                    </span>

                </div>

            </div>

            <div class="actions">

                <button
                    type="submit"
                    class="button submit-button"
                >

                    <i class="fa-solid fa-paper-plane"></i>

                    Submit Request

                </button>

                <a
                    href="dashboard.php"
                    class="button back-button"
                >

                    <i class="fa-solid fa-arrow-left"></i>

                    Back to Dashboard

                </a>

            </div>

        </form>

    </section>

</main>

</body>

</html>
