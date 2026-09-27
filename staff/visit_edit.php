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

$message = '';
$message_type = '';

/*
|--------------------------------------------------------------------------
| Update Visit Record
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

        $stmt = $pdo->prepare(
            "UPDATE visit_records
             SET
                chief_complaint = ?,
                findings = ?,
                treatment = ?,
                notes = ?,
                follow_up = ?
             WHERE visit_id = ?"
        );

        $stmt->execute([
            $chief_complaint,
            $findings,
            $treatment,
            $notes,
            $follow_up,
            $visit_id
        ]);

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

    <title>Edit Visit Record - The Tooth Lounge</title>

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

    <h1>Edit Visit Record</h1>


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
            <strong>Visit Date:</strong>

            <?= htmlspecialchars(
                $visit['visit_date']
            ) ?>
        </p>

    </div>


    <!-- EDIT FORM -->

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
            ><?= htmlspecialchars(
                $visit['chief_complaint'] ?? ''
            ) ?></textarea>


            <label for="findings">
                Findings
            </label>

            <textarea
                id="findings"
                name="findings"
            ><?= htmlspecialchars(
                $visit['findings'] ?? ''
            ) ?></textarea>


            <label for="treatment">
                Treatment
            </label>

            <textarea
                id="treatment"
                name="treatment"
            ><?= htmlspecialchars(
                $visit['treatment'] ?? ''
            ) ?></textarea>


            <label for="notes">
                Notes
            </label>

            <textarea
                id="notes"
                name="notes"
            ><?= htmlspecialchars(
                $visit['notes'] ?? ''
            ) ?></textarea>


            <label for="follow_up">
                Follow-Up
            </label>

            <textarea
                id="follow_up"
                name="follow_up"
            ><?= htmlspecialchars(
                $visit['follow_up'] ?? ''
            ) ?></textarea>


            <button type="submit">
                Update Visit Record
            </button>

        </form>

    </div>


    <a href="visit_view.php?id=<?= $visit['visit_id'] ?>">
        Back to Visit Record
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