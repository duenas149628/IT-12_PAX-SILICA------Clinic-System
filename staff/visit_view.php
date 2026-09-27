<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SESSION['role'] !== 'staff') {
    die('Access denied.');
}

$visit_id = $_GET['id'] ?? null;

if (!$visit_id || !ctype_digit($visit_id)) {
    die('Invalid visit record.');
}

$visit_id = (int) $visit_id;

/*
|--------------------------------------------------------------------------
| Get Visit Record
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT
        visit_records.*,
        patients.patient_number,
        patients.first_name,
        patients.last_name,
        patients.contact_number,
        appointments.confirmed_date,
        appointments.confirmed_start_time,
        appointments.confirmed_end_time
     FROM visit_records
     INNER JOIN patients
        ON visit_records.patient_id = patients.patient_id
     INNER JOIN appointments
        ON visit_records.appointment_id = appointments.appointment_id
     WHERE visit_records.visit_id = ?"
);

$stmt->execute([$visit_id]);

$visit = $stmt->fetch();

if (!$visit) {
    die('Visit record not found.');
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

    <title>Visit Record - The Tooth Lounge</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
        }

        .section {
            border: 1px solid #ccc;
            padding: 20px;
            margin-top: 20px;
            border-radius: 8px;
        }

        .record {
            margin-top: 20px;
        }

        .record-label {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .record-value {
            white-space: pre-wrap;
            margin-bottom: 20px;
        }

        .button {
            display: inline-block;
            padding: 12px 20px;
            margin-top: 15px;
            background: #333;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        a {
            display: inline-block;
            margin-top: 15px;
        }

    </style>

</head>

<body>

    <h1>Visit Record</h1>


    <!-- PATIENT INFORMATION -->

    <div class="section">

        <h2>Patient Information</h2>

        <p>
            <strong>Name:</strong>

            <?= htmlspecialchars(
                $visit['first_name']
                . ' '
                . $visit['last_name']
            ) ?>
        </p>

        <p>
            <strong>Patient Number:</strong>

            <?= htmlspecialchars(
                $visit['patient_number']
            ) ?>
        </p>

        <p>
            <strong>Contact:</strong>

            <?= htmlspecialchars(
                $visit['contact_number'] ?? ''
            ) ?>
        </p>

        <p>
            <strong>Visit Date:</strong>

            <?= htmlspecialchars(
                $visit['visit_date']
            ) ?>
        </p>

    </div>


    <!-- APPOINTMENT INFORMATION -->

    <div class="section">

        <h2>Appointment Information</h2>

        <p>
            <strong>Date:</strong>

            <?= htmlspecialchars(
                $visit['confirmed_date']
            ) ?>
        </p>

        <p>
            <strong>Time:</strong>

            <?= htmlspecialchars(
                date(
                    'g:i A',
                    strtotime(
                        $visit['confirmed_start_time']
                    )
                )
            ) ?>

            -

            <?= htmlspecialchars(
                date(
                    'g:i A',
                    strtotime(
                        $visit['confirmed_end_time']
                    )
                )
            ) ?>

        </p>

    </div>


    <!-- VISIT DETAILS -->

    <div class="section">

        <h2>Visit Details</h2>


        <div class="record">

            <div class="record-label">
                Chief Complaint
            </div>

            <div class="record-value">
                <?= htmlspecialchars(
                    $visit['chief_complaint'] ?? ''
                ) ?>
            </div>

        </div>


        <div class="record">

            <div class="record-label">
                Findings
            </div>

            <div class="record-value">
                <?= htmlspecialchars(
                    $visit['findings'] ?? ''
                ) ?>
            </div>

        </div>


        <div class="record">

            <div class="record-label">
                Treatment
            </div>

            <div class="record-value">
                <?= htmlspecialchars(
                    $visit['treatment'] ?? ''
                ) ?>
            </div>

        </div>


        <div class="record">

            <div class="record-label">
                Notes
            </div>

            <div class="record-value">
                <?= htmlspecialchars(
                    $visit['notes'] ?? ''
                ) ?>
            </div>

        </div>


        <div class="record">

            <div class="record-label">
                Follow-Up
            </div>

            <div class="record-value">
                <?= htmlspecialchars(
                    $visit['follow_up'] ?? ''
                ) ?>
            </div>

        </div>

    </div>


    <!-- ACTIONS -->

    <a
        href="visit_edit.php?id=<?= $visit['visit_id'] ?>"
        class="button"
    >
        Edit Visit Record
    </a>

    <br>

    <a href="appointments.php">
        Back to Appointments
    </a>

    <br>

    <a href="dashboard.php">
        Back to Staff Dashboard
    </a>

    <br>

    <a href="../auth/logout.php">
        Logout
    </a>

</body>

</html>