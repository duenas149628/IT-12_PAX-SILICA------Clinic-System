<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/notifications.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SESSION['role'] !== 'staff') {
    die('Access denied.');
}

$appointment_id = $_GET['id'] ?? null;

if (!$appointment_id || !ctype_digit($appointment_id)) {
    die('Invalid appointment.');
}

$appointment_id = (int) $appointment_id;


/*
|--------------------------------------------------------------------------
| Get Appointment
|--------------------------------------------------------------------------
*/

function getAppointment(PDO $pdo, int $appointment_id): ?array
{
    $stmt = $pdo->prepare(
        "SELECT
            appointments.*,
            patients.patient_number,
            patients.first_name,
            patients.last_name,
            patients.contact_number
         FROM appointments
         INNER JOIN patients
            ON appointments.patient_id = patients.patient_id
         WHERE appointments.appointment_id = ?"
    );

    $stmt->execute([$appointment_id]);

    $appointment = $stmt->fetch();

    return $appointment ?: null;
}

function findScheduleConflict(PDO $pdo, string $date, string $start, string $end, int $excludeId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT a.appointment_id, a.confirmed_date, a.confirmed_start_time,
                a.confirmed_end_time, p.first_name, p.last_name, p.patient_number
         FROM appointments a
         INNER JOIN patients p ON p.patient_id = a.patient_id
         WHERE a.status = 'confirmed'
           AND a.confirmed_date = ?
           AND a.confirmed_start_time < ?
           AND a.confirmed_end_time > ?
           AND a.appointment_id != ?
         ORDER BY a.confirmed_start_time
         LIMIT 1"
    );
    $stmt->execute([$date, $end, $start, $excludeId]);
    $conflict = $stmt->fetch();
    return $conflict ?: null;
}


$appointment = getAppointment($pdo, $appointment_id);

if (!$appointment) {
    die('Appointment not found.');
}

$message = '';
$message_type = '';
$conflict = null;


/*
|--------------------------------------------------------------------------
| Handle Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';
    audit_log('appointment.action.submitted', [
        'appointment_id' => (int) $appointment_id,
        'action' => is_string($action) ? preg_replace('/[^a-z_]/i', '', $action) : 'invalid',
    ]);


    /*
    |--------------------------------------------------------------------------
    | ACCEPT PATIENT REQUEST
    |--------------------------------------------------------------------------
    */

    if ($action === 'accept') {

        $confirmed_date = $appointment['preferred_date'];
        $confirmed_start_time = $appointment['preferred_start_time'];
        $confirmed_end_time = $appointment['preferred_end_time'];

        $conflict = findScheduleConflict($pdo, $confirmed_date, $confirmed_start_time, $confirmed_end_time, $appointment_id);

        if ($conflict) {

            $message = 'This time overlaps an existing confirmed appointment.';
            $message_type = 'error';

        } else {

            $stmt = $pdo->prepare(
                "UPDATE appointments
                 SET
                    confirmed_date = ?,
                    confirmed_start_time = ?,
                    confirmed_end_time = ?,
                    status = 'confirmed'
                 WHERE appointment_id = ?"
            );

            $stmt->execute([
                $confirmed_date,
                $confirmed_start_time,
                $confirmed_end_time,
                $appointment_id
            ]);

            $message = 'Appointment accepted successfully.';
            $message_type = 'success';

            $appointment = getAppointment($pdo, $appointment_id);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CONFIRM CUSTOM SCHEDULE
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'confirm') {

        $confirmed_date = $_POST['confirmed_date'] ?? '';
        $confirmed_start_time = $_POST['confirmed_start_time'] ?? '';
        $confirmed_end_time = $_POST['confirmed_end_time'] ?? '';

        if (
            empty($confirmed_date) ||
            empty($confirmed_start_time) ||
            empty($confirmed_end_time)
        ) {

            $message = 'Please complete the confirmed schedule.';
            $message_type = 'error';

        } elseif ($confirmed_start_time >= $confirmed_end_time) {

            $message = 'The confirmed end time must be later than the start time.';
            $message_type = 'error';

        } else {

            $conflict = findScheduleConflict($pdo, $confirmed_date, $confirmed_start_time, $confirmed_end_time, $appointment_id);

            if ($conflict) {

                $message = 'This time overlaps an existing confirmed appointment.';
                $message_type = 'error';

            } else {

                $stmt = $pdo->prepare(
                    "UPDATE appointments
                     SET
                        confirmed_date = ?,
                        confirmed_start_time = ?,
                        confirmed_end_time = ?,
                        status = 'confirmed'
                     WHERE appointment_id = ?"
                );

                $stmt->execute([
                    $confirmed_date,
                    $confirmed_start_time,
                    $confirmed_end_time,
                    $appointment_id
                ]);

                $message = 'Appointment confirmed successfully.';
                $message_type = 'success';

                $appointment = getAppointment($pdo, $appointment_id);
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | RESCHEDULE APPOINTMENT
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'reschedule') {

        $confirmed_date = $_POST['confirmed_date'] ?? '';
        $confirmed_start_time = $_POST['confirmed_start_time'] ?? '';
        $confirmed_end_time = $_POST['confirmed_end_time'] ?? '';

        if (
            empty($confirmed_date) ||
            empty($confirmed_start_time) ||
            empty($confirmed_end_time)
        ) {

            $message = 'Please complete the new appointment schedule.';
            $message_type = 'error';

        } elseif ($confirmed_start_time >= $confirmed_end_time) {

            $message = 'The end time must be later than the start time.';
            $message_type = 'error';

        } else {

            $conflict = findScheduleConflict($pdo, $confirmed_date, $confirmed_start_time, $confirmed_end_time, $appointment_id);

            if ($conflict) {

                $message = 'This time overlaps an existing confirmed appointment.';
                $message_type = 'error';

            } else {

                $stmt = $pdo->prepare(
                    "UPDATE appointments
                     SET
                        confirmed_date = ?,
                        confirmed_start_time = ?,
                        confirmed_end_time = ?,
                        status = 'confirmed'
                     WHERE appointment_id = ?"
                );

                $stmt->execute([
                    $confirmed_date,
                    $confirmed_start_time,
                    $confirmed_end_time,
                    $appointment_id
                ]);

                $message = 'Appointment rescheduled successfully.';
                $message_type = 'success';

                $appointment = getAppointment($pdo, $appointment_id);
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CANCEL APPOINTMENT
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'cancel') {

        $stmt = $pdo->prepare(
            "UPDATE appointments
             SET
                confirmed_date = NULL,
                confirmed_start_time = NULL,
                confirmed_end_time = NULL,
                status = 'cancelled'
             WHERE appointment_id = ?"
        );

        $stmt->execute([$appointment_id]);

        $message = 'Appointment cancelled successfully.';
        $message_type = 'success';

        $appointment = getAppointment($pdo, $appointment_id);
    }


    /*
    |--------------------------------------------------------------------------
    | MARK AS COMPLETED
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'completed') {

        if ($appointment['status'] !== 'confirmed') {

            $message = 'Only confirmed appointments can be marked as completed.';
            $message_type = 'error';

        } else {

            try {

                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "UPDATE appointments
                     SET status = 'completed'
                     WHERE appointment_id = ?"
                );

                $stmt->execute([$appointment_id]);


                /*
                |--------------------------------------------------------------------------
                | Create Visit Record Automatically
                |--------------------------------------------------------------------------
                */

                $checkVisit = $pdo->prepare(
                    "SELECT visit_id
                     FROM visit_records
                     WHERE appointment_id = ?"
                );

                $checkVisit->execute([$appointment_id]);

                $existingVisit = $checkVisit->fetch();

                if (!$existingVisit) {

                    $visit_date = $appointment['confirmed_date'];

                    $createVisit = $pdo->prepare(
                        "INSERT INTO visit_records (
                            patient_id,
                            appointment_id,
                            visit_date,
                            chief_complaint,
                            findings,
                            treatment,
                            notes,
                            follow_up
                        )
                        VALUES (?, ?, ?, '', '', '', '', '')"
                    );

                    $createVisit->execute([
                        $appointment['patient_id'],
                        $appointment_id,
                        $visit_date
                    ]);
                }

                $pdo->commit();

                $message = 'Appointment completed and visit record created successfully.';
                $message_type = 'success';

                $appointment = getAppointment($pdo, $appointment_id);

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $message = 'The appointment could not be completed. Please try again.';
                $message_type = 'error';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | MARK AS NO-SHOW
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'no_show') {

        if ($appointment['status'] !== 'confirmed') {

            $message = 'Only confirmed appointments can be marked as no-show.';
            $message_type = 'error';

        } else {

            $stmt = $pdo->prepare(
                "UPDATE appointments
                 SET status = 'no_show'
                 WHERE appointment_id = ?"
            );

            $stmt->execute([$appointment_id]);

            $message = 'Appointment marked as no-show.';
            $message_type = 'success';

            $appointment = getAppointment($pdo, $appointment_id);
        }
    }

    if ($message_type === 'success') {
        notify_patient_of_appointment($pdo, $appointment_id);
    }
}


/*
|--------------------------------------------------------------------------
| Get Visit Record
|--------------------------------------------------------------------------
*/

$existingVisit = null;

if ($appointment['status'] === 'completed') {

    $visitStmt = $pdo->prepare(
        "SELECT visit_id
         FROM visit_records
         WHERE appointment_id = ?"
    );

    $visitStmt->execute([
        $appointment['appointment_id']
    ]);

    $existingVisit = $visitStmt->fetch();
}


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function formatTime(?string $time): string
{
    if (!$time) {
        return 'Not set';
    }

    return date('g:i A', strtotime($time));
}

function formatStatus(string $status): string
{
    return ucfirst(str_replace('_', ' ', $status));
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

    <title>Manage Appointment - The Tooth Lounge</title>

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


        /* =========================================================
           HEADER
        ========================================================= */

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


        /* =========================================================
           NAVIGATION
        ========================================================= */

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
        }

        .logout {
            background: #167b8c;

            color: white !important;

            margin-left: 5px;
        }


        /* =========================================================
           MAIN
        ========================================================= */

        .container {
            width: 1100px;
            max-width: 92%;

            margin: 40px auto;
        }


        /* =========================================================
           PAGE TITLE
        ========================================================= */

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

        .appointment-number {
            display: inline-flex;

            align-items: center;

            gap: 7px;

            margin-top: 10px;

            padding: 7px 12px;

            background: rgba(255, 255, 255, 0.18);

            border:
                1px solid
                rgba(255, 255, 255, 0.3);

            border-radius: 20px;

            font-size: 13px;
        }


        /* =========================================================
           MESSAGE
        ========================================================= */

        .message {
            display: flex;

            align-items: center;

            gap: 9px;

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

        .conflict-details {
            display: grid;
            gap: 5px;
            margin-left: 8px;
            padding: 11px 13px;
            background: rgba(255,255,255,.72);
            border: 1px solid #efcaca;
            border-radius: 8px;
        }
        .conflict-details a { color:#8f2525; font-weight:700; }


        /* =========================================================
           SECTION
        ========================================================= */

        .section {
            padding: 30px;

            margin-bottom: 20px;

            background: rgba(255, 255, 255, 0.96);

            border-radius: 15px;

            box-shadow:
                0 10px 30px rgba(0, 0, 0, 0.15);
        }


        .section-header {
            display: flex;

            align-items: center;

            gap: 15px;

            margin-bottom: 22px;
        }

        .section-icon {
            width: 50px;
            height: 50px;

            display: flex;

            align-items: center;
            justify-content: center;

            background: #e6f7fa;

            color: #167b8c;

            border-radius: 12px;

            font-size: 21px;

            flex-shrink: 0;
        }

        .section-header h2 {
            color: #167b8c;

            font-size: 21px;
        }

        .section-header p {
            margin-top: 5px;

            color: #777;

            font-size: 13px;
        }


        /* =========================================================
           INFORMATION GRID
        ========================================================= */

        .info-grid {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 15px;
        }

        .info-box {
            padding: 16px;

            background: #f8fbfc;

            border: 1px solid #e0e8ea;

            border-radius: 10px;
        }

        .info-label {
            margin-bottom: 6px;

            color: #777;

            font-size: 12px;

            font-weight: 600;
        }

        .info-label i {
            margin-right: 5px;

            color: #167b8c;
        }

        .info-value {
            color: #444;

            font-size: 14px;

            line-height: 1.4;
        }


        /* =========================================================
           REQUEST GRID
        ========================================================= */

        .request-grid {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 15px;
        }

        .request-box {
            padding: 18px;

            background: #f8fbfc;

            border: 1px solid #e0e8ea;

            border-radius: 10px;
        }

        .request-box strong {
            display: block;

            margin-bottom: 7px;

            color: #167b8c;

            font-size: 13px;
        }

        .request-box strong i {
            margin-right: 6px;
        }

        .request-box span {
            color: #555;

            font-size: 14px;

            line-height: 1.5;
        }


        /* =========================================================
           STATUS
        ========================================================= */

        .status {
            display: inline-flex !important;

            align-items: center;

            gap: 6px;

            padding: 6px 11px;

            background: #e6f7fa;

            color: #167b8c !important;

            border-radius: 20px;

            font-size: 12px !important;

            font-weight: bold;
        }


        /* =========================================================
           FORM
        ========================================================= */

        .form-grid {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 16px;
        }

        .form-group {
            display: flex;

            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 7px;

            color: #555;

            font-size: 13px;

            font-weight: 600;
        }

        .form-group label i {
            margin-right: 5px;

            color: #167b8c;
        }

        input {
            width: 100%;

            padding: 11px 12px;

            border: 1px solid #d5d5d5;

            border-radius: 8px;

            background: white;

            color: #333;

            font-family: Arial, sans-serif;

            font-size: 14px;

            outline: none;
        }


        /* =========================================================
           BUTTONS
        ========================================================= */

        .button-row {
            display: flex;

            flex-wrap: wrap;

            gap: 10px;

            margin-top: 20px;
        }

        button,
        .button {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            gap: 7px;

            padding: 9px 14px;

            border: none;

            border-radius: 8px;

            text-decoration: none;

            font-family: Arial, sans-serif;

            font-size: 13px;

            font-weight: 500;

            cursor: pointer;
        }

        .accept-button {
            background: #167b8c;

            color: white;
        }

        .confirm-button {
            background: #167b8c;

            color: white;
        }

        .reschedule-button {
            background: #167b8c;

            color: white;
        }

        .complete-button {
            background: #287a53;

            color: white;
        }

        .no-show-button {
            background: #777;

            color: white;
        }

        .cancel-button {
            background: #b00020;

            color: white;
        }

        .button {
            background: #167b8c;

            color: white;
        }

        .secondary-button {
            background: #eef3f4;

            color: #555;
        }


        /* =========================================================
           CONFIRMED SCHEDULE
        ========================================================= */

        .schedule-box {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 15px;
        }

        .schedule-item {
            padding: 18px;

            background: #f1fbfc;

            border: 1px solid #d7eef1;

            border-radius: 10px;
        }

        .schedule-item span {
            display: block;

            margin-bottom: 6px;

            color: #777;

            font-size: 12px;
        }

        .schedule-item span i {
            margin-right: 7px;

            color: #167b8c;
        }

        .schedule-item strong {
            color: #167b8c;

            font-size: 15px;
        }


        /* =========================================================
           VISIT RECORD MESSAGE
        ========================================================= */

        .record-message {
            color: #777;

            font-size: 14px;

            margin-bottom: 15px;
        }

        .record-message i {
            color: #287a53;

            margin-right: 5px;
        }


        /* =========================================================
           BOTTOM ACTIONS
        ========================================================= */

        .bottom-actions {
            display: flex;

            flex-wrap: wrap;

            gap: 10px;

            margin-top: 25px;

            padding-top: 20px;
        }

        .bottom-actions a {
            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding: 9px 14px;

            background: rgba(255, 255, 255, 0.95);

            color: #555;

            border-radius: 8px;

            text-decoration: none;

            font-size: 13px;
        }

        .bottom-actions a i {
            color: #167b8c;
        }


        /* =========================================================
           TABLET
        ========================================================= */

        @media (max-width: 950px) {

            .header {
                padding: 12px 20px;
            }

            .info-grid {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .form-grid {
                grid-template-columns:
                    1fr;
            }

            .schedule-box {
                grid-template-columns:
                    1fr;
            }
        }


        /* =========================================================
           MOBILE
        ========================================================= */

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

            .section {
                padding: 20px;
            }

            .info-grid,
            .request-grid {
                grid-template-columns: 1fr;
            }

            .button-row {
                flex-direction: column;
            }

            .button-row button,
            .button-row .button {
                width: 100%;
            }

            .bottom-actions {
                flex-direction: column;
            }

            .bottom-actions a {
                width: 100%;

                justify-content: center;
            }
        }

    </style>

</head>

<body>


<!-- =========================================================
     HEADER
========================================================= -->

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

        <a href="patients.php">
            <i class="fa-solid fa-users"></i>
            Patients
        </a>

        <a href="appointments.php">
            <i class="fa-solid fa-calendar-check"></i>
            Appointments
        </a>

        <a href="visit_records.php">
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


<!-- =========================================================
     MAIN
========================================================= -->

<main class="container">


    <!-- PAGE TITLE -->

    <section class="welcome">

        <h1>
            Manage Appointment
        </h1>

        <p>
            Review and manage this patient's appointment.
        </p>

        <span class="appointment-number">

            <i class="fa-solid fa-calendar-check"></i>

            Appointment #
            <?= htmlspecialchars(
                $appointment['appointment_id']
            ) ?>

        </span>

    </section>


    <!-- MESSAGE -->

    <?php if ($message): ?>

        <div class="message <?= htmlspecialchars($message_type) ?>" role="<?= $message_type === 'error' ? 'alert' : 'status' ?>">

            <i class="fa-solid
                <?= $message_type === 'success'
                    ? 'fa-circle-check'
                    : 'fa-circle-exclamation'
                ?>"></i>

            <div>
                <?= htmlspecialchars($message) ?>
                <?php if ($conflict): ?>
                    <div class="conflict-details">
                        <strong>Conflicting booking</strong>
                        <span><?= h($conflict['first_name'] . ' ' . $conflict['last_name']) ?> · Patient <?= h($conflict['patient_number']) ?></span>
                        <span><?= h(date('M d, Y', strtotime($conflict['confirmed_date']))) ?>, <?= h(date('g:i A', strtotime($conflict['confirmed_start_time']))) ?>–<?= h(date('g:i A', strtotime($conflict['confirmed_end_time']))) ?></span>
                        <a href="appointment_view.php?id=<?= h($conflict['appointment_id']) ?>">Open appointment #<?= h($conflict['appointment_id']) ?></a>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         PATIENT INFORMATION
    ====================================================== -->

    <section class="section">

        <div class="section-header">

            <div class="section-icon">
                <i class="fa-solid fa-user"></i>
            </div>

            <div>

                <h2>
                    Patient Information
                </h2>

                <p>
                    Patient details associated with this appointment.
                </p>

            </div>

        </div>


        <div class="info-grid">

            <div class="info-box">

                <div class="info-label">

                    <i class="fa-solid fa-user"></i>

                    Patient Name

                </div>

                <div class="info-value">

                    <?= htmlspecialchars(
                        $appointment['first_name']
                        . ' '
                        . $appointment['last_name']
                    ) ?>

                </div>

            </div>


            <div class="info-box">

                <div class="info-label">

                    <i class="fa-solid fa-id-card"></i>

                    Patient Number

                </div>

                <div class="info-value">

                    <?= htmlspecialchars(
                        $appointment['patient_number']
                    ) ?>

                </div>

            </div>


            <div class="info-box">

                <div class="info-label">

                    <i class="fa-solid fa-phone"></i>

                    Contact Number

                </div>

                <div class="info-value">

                    <?= htmlspecialchars(
                        $appointment['contact_number'] ?? 'Not provided'
                    ) ?>

                </div>

            </div>

        </div>

    </section>


    <!-- =====================================================
         PATIENT REQUEST
    ====================================================== -->

    <section class="section">

        <div class="section-header">

            <div class="section-icon">
                <i class="fa-solid fa-calendar-check"></i>
            </div>

            <div>

                <h2>
                    Patient Request
                </h2>

                <p>
                    Requested appointment schedule and reason.
                </p>

            </div>

        </div>


        <div class="request-grid">

            <div class="request-box">

                <strong>
                    <i class="fa-solid fa-calendar-day"></i>
                    Preferred Date
                </strong>

                <span>
                    <?= htmlspecialchars(
                        $appointment['preferred_date']
                    ) ?>
                </span>

            </div>


            <div class="request-box">

                <strong>
                    <i class="fa-solid fa-clock"></i>
                    Preferred Time
                </strong>

                <span>

                    <?= htmlspecialchars(
                        formatTime(
                            $appointment['preferred_start_time']
                        )
                    ) ?>

                    -

                    <?= htmlspecialchars(
                        formatTime(
                            $appointment['preferred_end_time']
                        )
                    ) ?>

                </span>

            </div>


            <div class="request-box">

                <strong>
                    <i class="fa-solid fa-comment-medical"></i>
                    Reason
                </strong>

                <span>
                    <?= htmlspecialchars(
                        $appointment['reason'] ?? 'Not provided'
                    ) ?>
                </span>

            </div>


            <div class="request-box">

                <strong>
                    <i class="fa-solid fa-circle-info"></i>
                    Current Status
                </strong>

                <span class="status">

                    <i class="fa-solid fa-circle"></i>

                    <?= htmlspecialchars(
                        formatStatus(
                            $appointment['status']
                        )
                    ) ?>

                </span>

            </div>

        </div>

    </section>


    <!-- =====================================================
         ACCEPT APPOINTMENT
    ====================================================== -->

    <?php if ($appointment['status'] === 'pending'): ?>

        <section class="section">

            <div class="section-header">

                <div class="section-icon">
                    <i class="fa-solid fa-check"></i>
                </div>

                <div>

                    <h2>
                        Accept Appointment
                    </h2>

                    <p>
                        Accept the patient's requested date and time.
                    </p>

                </div>

            </div>


            <form
                method="POST"
                onsubmit="return confirm('Accept this appointment using the requested date and time?');"
            >
<?= csrf_field() ?>

                <button
                    type="submit"
                    name="action"
                    value="accept"
                    class="accept-button"
                >

                    <i class="fa-solid fa-check"></i>

                    Accept Appointment

                </button>

            </form>

        </section>

    <?php endif; ?>


    <!-- =====================================================
         CUSTOM SCHEDULE / RESCHEDULE
    ====================================================== -->

    <?php if (
        $appointment['status'] === 'pending' ||
        $appointment['status'] === 'confirmed'
    ): ?>

        <section class="section">

            <div class="section-header">

                <div class="section-icon">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>

                <div>

                    <?php if ($appointment['status'] === 'pending'): ?>

                        <h2>
                            Configure Custom Schedule
                        </h2>

                        <p>
                            Set a different schedule for the patient.
                        </p>

                    <?php else: ?>

                        <h2>
                            Reschedule Appointment
                        </h2>

                        <p>
                            Change the confirmed appointment schedule.
                        </p>

                    <?php endif; ?>

                </div>

            </div>


            <form method="POST">
<?= csrf_field() ?>

                <div class="form-grid">

                    <div class="form-group">

                        <label for="confirmed_date">

                            <i class="fa-solid fa-calendar"></i>

                            Confirmed Date

                        </label>

                        <input
                            type="date"
                            id="confirmed_date"
                            name="confirmed_date"
                            value="<?= htmlspecialchars(
                                $appointment['confirmed_date']
                                ?? $appointment['preferred_date']
                            ) ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="confirmed_start_time">

                            <i class="fa-solid fa-clock"></i>

                            Start Time

                        </label>

                        <input
                            type="time"
                            id="confirmed_start_time"
                            name="confirmed_start_time"
                            value="<?= htmlspecialchars(
                                $appointment['confirmed_start_time']
                                ?? $appointment['preferred_start_time']
                            ) ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="confirmed_end_time">

                            <i class="fa-solid fa-clock"></i>

                            End Time

                        </label>

                        <input
                            type="time"
                            id="confirmed_end_time"
                            name="confirmed_end_time"
                            value="<?= htmlspecialchars(
                                $appointment['confirmed_end_time']
                                ?? $appointment['preferred_end_time']
                            ) ?>"
                            required
                        >

                    </div>

                </div>


                <div class="button-row">

                    <?php if ($appointment['status'] === 'pending'): ?>

                        <button
                            type="submit"
                            name="action"
                            value="confirm"
                            class="confirm-button"
                        >

                            <i class="fa-solid fa-check"></i>

                            Confirm Custom Schedule

                        </button>

                    <?php else: ?>

                        <button
                            type="submit"
                            name="action"
                            value="reschedule"
                            class="reschedule-button"
                        >

                            <i class="fa-solid fa-calendar-days"></i>

                            Reschedule Appointment

                        </button>

                    <?php endif; ?>

                </div>

            </form>

        </section>

    <?php endif; ?>


    <!-- =====================================================
         CONFIRMED SCHEDULE
    ====================================================== -->

    <?php if (!empty($appointment['confirmed_date'])): ?>

        <section class="section">

            <div class="section-header">

                <div class="section-icon">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>

                <div>

                    <h2>
                        Confirmed Schedule
                    </h2>

                    <p>
                        Current confirmed appointment schedule.
                    </p>

                </div>

            </div>


            <div class="schedule-box">

                <div class="schedule-item">

                    <span>

                        <i class="fa-solid fa-calendar"></i>

                        Date

                    </span>

                    <strong>

                        <?= htmlspecialchars(
                            $appointment['confirmed_date']
                        ) ?>

                    </strong>

                </div>


                <div class="schedule-item">

                    <span>

                        <i class="fa-solid fa-clock"></i>

                        Time

                    </span>

                    <strong>

                        <?= htmlspecialchars(
                            formatTime(
                                $appointment['confirmed_start_time']
                            )
                        ) ?>

                        -

                        <?= htmlspecialchars(
                            formatTime(
                                $appointment['confirmed_end_time']
                            )
                        ) ?>

                    </strong>

                </div>

            </div>

        </section>

    <?php endif; ?>


    <!-- =====================================================
         APPOINTMENT STATUS
    ====================================================== -->

    <?php if ($appointment['status'] === 'confirmed'): ?>

        <section class="section">

            <div class="section-header">

                <div class="section-icon">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>

                <div>

                    <h2>
                        Appointment Status
                    </h2>

                    <p>
                        Update the appointment after the scheduled visit.
                    </p>

                </div>

            </div>


            <div class="button-row">

                <form
                    method="POST"
                    onsubmit="return confirm('Mark this appointment as completed? A visit record will be created automatically.');"
                >
<?= csrf_field() ?>

                    <button
                        type="submit"
                        name="action"
                        value="completed"
                        class="complete-button"
                    >

                        <i class="fa-solid fa-circle-check"></i>

                        Mark as Completed

                    </button>

                </form>


                <form
                    method="POST"
                    onsubmit="return confirm('Mark this appointment as no-show?');"
                >
<?= csrf_field() ?>

                    <button
                        type="submit"
                        name="action"
                        value="no_show"
                        class="no-show-button"
                    >

                        <i class="fa-solid fa-user-xmark"></i>

                        Mark as No-Show

                    </button>

                </form>

            </div>

        </section>

    <?php endif; ?>


    <!-- =====================================================
         VISIT RECORD
    ====================================================== -->

    <?php if ($appointment['status'] === 'completed'): ?>

        <section class="section">

            <div class="section-header">

                <div class="section-icon">
                    <i class="fa-solid fa-file-medical"></i>
                </div>

                <div>

                    <h2>
                        Visit Record
                    </h2>

                    <p>
                        Visit record associated with this appointment.
                    </p>

                </div>

            </div>


            <?php if ($existingVisit): ?>

                <p class="record-message">

                    <i class="fa-solid fa-circle-check"></i>

                    A visit record has been created for this completed appointment.

                </p>


                <div class="button-row">

                    <a
                        href="visit_view.php?id=<?= htmlspecialchars($existingVisit['visit_id']) ?>"
                        class="button"
                    >

                        <i class="fa-solid fa-eye"></i>

                        View Visit Record

                    </a>


                    <a
                        href="visit_edit.php?id=<?= htmlspecialchars($existingVisit['visit_id']) ?>"
                        class="button"
                    >

                        <i class="fa-solid fa-pen-to-square"></i>

                        Edit Visit Record

                    </a>

                </div>

            <?php else: ?>

                <p class="record-message">

                    No visit record is associated with this appointment.

                </p>

            <?php endif; ?>

        </section>

    <?php endif; ?>


    <!-- =====================================================
         CANCEL APPOINTMENT
    ====================================================== -->

    <?php if (
        $appointment['status'] !== 'cancelled' &&
        $appointment['status'] !== 'completed' &&
        $appointment['status'] !== 'no_show'
    ): ?>

        <section class="section">

            <div class="section-header">

                <div class="section-icon">
                    <i class="fa-solid fa-ban"></i>
                </div>

                <div>

                    <h2>
                        Cancel Appointment
                    </h2>

                    <p>
                        Cancel this appointment if it is no longer needed.
                    </p>

                </div>

            </div>


            <form
                method="POST"
                onsubmit="return confirm('Are you sure you want to cancel this appointment?');"
            >
<?= csrf_field() ?>

                <button
                    type="submit"
                    name="action"
                    value="cancel"
                    class="cancel-button"
                >

                    <i class="fa-solid fa-xmark"></i>

                    Cancel Appointment

                </button>

            </form>

        </section>

    <?php endif; ?>


    <!-- =====================================================
         BOTTOM ACTIONS
    ====================================================== -->

    <div class="bottom-actions">

        <a href="appointments.php">

            <i class="fa-solid fa-arrow-left"></i>

            Back to Appointments

        </a>


        <a href="dashboard.php">

            <i class="fa-solid fa-gauge-high"></i>

            Staff Dashboard

        </a>


        <a href="../auth/logout.php">

            <i class="fa-solid fa-right-from-bracket"></i>

            Logout

        </a>

    </div>


</main>

</body>

</html>
