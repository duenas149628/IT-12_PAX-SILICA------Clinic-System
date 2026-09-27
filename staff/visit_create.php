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

$appointment_id = $_GET['id'] ?? null;

if (!$appointment_id || !ctype_digit($appointment_id)) {
    die('Invalid appointment.');
}

$appointment_id = (int) $appointment_id;

/*
|--------------------------------------------------------------------------
| Get completed appointment and patient information
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT
        appointments.appointment_id,
        appointments.patient_id,
        appointments.confirmed_date,
        appointments.confirmed_start_time,
        appointments.confirmed_end_time,
        appointments.status,
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

if (!$appointment) {
    die('Appointment not found.');
}

/*
|--------------------------------------------------------------------------
| Only completed appointments can have a visit record
|--------------------------------------------------------------------------
*/

if ($appointment['status'] !== 'completed') {
    die('A visit record can only be created for a completed appointment.');
}

/*
|--------------------------------------------------------------------------
| Check whether a visit record already exists
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT visit_id
     FROM visit_records
     WHERE appointment_id = ?"
);

$stmt->execute([$appointment_id]);

$existing_visit = $stmt->fetch();

if ($existing_visit) {
    header(
        'Location: visit_view.php?id='
        . $existing_visit['visit_id']
    );
    exit;
}

$message = '';
$message_type = '';

/*
|--------------------------------------------------------------------------
| Save Visit Record
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $chief_complaint = trim($_POST['chief_complaint'] ?? '');
    $findings = trim($_POST['findings'] ?? '');
    $treatment = trim($_POST['treatment'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $follow_up = trim($_POST['follow_up'] ?? '');

    if (
        empty($chief_complaint) &&
        empty($findings) &&
        empty($treatment) &&
        empty($notes) &&
        empty($follow_up)
    ) {

        $message = 'Please enter at least one visit detail.';
        $message_type = 'error';

    } else {

        $visit_date = $appointment['confirmed_date'];

        $stmt = $pdo->prepare(
            "INSERT INTO visit_records
            (
                patient_id,
                appointment_id,
                visit_date,
                chief_complaint,
                findings,
                treatment,
                notes,
                follow_up
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $appointment['patient_id'],
            $appointment['appointment_id'],
            $visit_date,
            $chief_complaint,
            $findings,
            $treatment,
            $notes,
            $follow_up
        ]);

        $visit_id = $pdo->lastInsertId();

        header(
            'Location: visit_view.php?id='
            . $visit_id
        );
        exit;
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

    <title>Create Visit Record - The Tooth Lounge</title>

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

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        textarea {
            width: 100%;
            min-height: 100px;
            padding: 10px;
            margin-top: 5px;
            box-sizing: border-box;
            resize: vertical;
        }

        button {
            width: 100%;
            padding: 12px;
            margin-top: 20px;
            background: #333;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .message {
            padding: 12px;
            margin-top: 15px;
            border-radius: 5px;
        }

        .success {
            background: #dff0d8;
        }

        .error {
            background: #f2dede;
        }

        a {
            display: inline-block;
            margin-top: 15px;
        }

    </style>

</head>

<body>

    <h1>Create Visit Record</h1>


    <!-- PATIENT INFORMATION -->

    <div class="section">

        <h2>Patient Information</h2>

        <p>
            <strong>Name:</strong>

            <?= htmlspecialchars(
                $appointment['first_name']
                . ' '
                . $appointment['last_name']
            ) ?>
        </p>

        <p>
            <strong>Patient Number:</strong>

            <?= htmlspecialchars(
                $appointment['patient_number']
            ) ?>
        </p>

        <p>
            <strong>Contact:</strong>

            <?= htmlspecialchars(
                $appointment['contact_number'] ?? ''
            ) ?>
        </p>

        <p>
            <strong>Visit Date:</strong>

            <?= htmlspecialchars(
                $appointment['confirmed_date']
            ) ?>
        </p>

    </div>


    <!-- VISIT RECORD FORM -->

    <div class="section">

        <h2>Visit Details</h2>

        <?php if ($message): ?>

            <div class="message <?= htmlspecialchars($message_type) ?>">

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>


        <form method="POST">

            <label for="chief_complaint">
                Chief Complaint
            </label>

            <textarea
                id="chief_complaint"
                name="chief_complaint"
                placeholder="Enter the patient's main complaint..."
            ></textarea>


            <label for="findings">
                Findings
            </label>

            <textarea
                id="findings"
                name="findings"
                placeholder="Enter clinical findings..."
            ></textarea>


            <label for="treatment">
                Treatment
            </label>

            <textarea
                id="treatment"
                name="treatment"
                placeholder="Enter treatment provided..."
            ></textarea>


            <label for="notes">
                Notes
            </label>

            <textarea
                id="notes"
                name="notes"
                placeholder="Enter additional notes..."
            ></textarea>


            <label for="follow_up">
                Follow-Up
            </label>

            <textarea
                id="follow_up"
                name="follow_up"
                placeholder="Enter follow-up instructions..."
            ></textarea>


            <button type="submit">
                Save Visit Record
            </button>

        </form>

    </div>


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