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
    csrf_verify();

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

        audit_log('visit.record.created', ['visit_id' => (int) $visit_id]);

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

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>

        body {
            min-height: 100vh;
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #333;
            background: linear-gradient(rgba(0, 135, 155, .78), rgba(20, 85, 150, .78)),
                url("https://images.unsplash.com/photo-1606811971618-4486d14f3f99?auto=format&fit=crop&w=1920&q=80") center / cover fixed;
        }

        .section {
            min-width: 0;
            padding: 22px;
            background: rgba(255,255,255,.97);
            border: 0;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,.15);
        }

        .header {
            min-height: 75px; display: flex; align-items: center; justify-content: space-between;
            padding: 12px 35px; background: rgba(255,255,255,.96); box-shadow: 0 3px 15px rgba(0,0,0,.15);
        }
        .logo { display: flex; align-items: center; gap: 12px; color: #167b8c; font-size: 21px; font-weight: bold; text-decoration: none; }
        .logo-icon { width: 43px; height: 43px; display: grid; place-items: center; border-radius: 50%; background: #e6f7fa; color: #167b8c; }
        .nav { display: flex; align-items: center; gap: 8px; }
        .nav a, .bottom-links a { color: #167b8c; text-decoration: none; font-weight: 600; }
        .nav a { padding: 10px 13px; border-radius: 8px; }
        .nav a:hover { background: #e6f7fa; }
        .container { width: 1100px; max-width: 92%; margin: 35px auto; }
        .welcome { margin-bottom: 22px; color: #fff; }
        .welcome h1 { margin-bottom: 7px; font-size: 30px; font-weight: 600; }
        .welcome p { opacity: .92; }
        .record-layout { display: grid; grid-template-columns: minmax(260px, .8fr) minmax(0, 1.2fr); align-items: start; gap: 20px; }
        .section h2 { margin-bottom: 18px; color: #167b8c; font-size: 20px; }
        .patient-info p { padding: 12px 0; border-bottom: 1px solid #eaf0f1; color: #56676b; font-size: 14px; }
        .patient-info p:last-child { border-bottom: 0; }
        .patient-info strong { display: block; margin-bottom: 4px; color: #718185; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .appointment-badge { display: inline-flex; align-items: center; gap: 7px; margin-bottom: 18px; padding: 7px 10px; border-radius: 20px; background: #e6f7fa; color: #167b8c; font-size: 12px; font-weight: 700; }
        label { display: block; margin-top: 15px; color: #45575b; font-size: 13px; font-weight: 700; }
        .section textarea { min-height: 78px; border: 1px solid #d8e2e4; border-radius: 8px; font: 14px Arial, sans-serif; }
        .section textarea:focus { border-color: #167b8c; outline: 0; box-shadow: 0 0 0 3px rgba(22,123,140,.12); }
        button { width: 100%; padding: 12px; margin-top: 20px; background: #167b8c; color: white; border: 0; border-radius: 8px; font-weight: 700; cursor: pointer; }
        button:hover { background: #126c79; }
        .bottom-links { display: flex; flex-wrap: wrap; gap: 18px; margin-top: 20px; padding: 15px 18px; border-radius: 12px; background: rgba(255,255,255,.96); font-size: 13px; }
        @media (max-width: 760px) {
            .header { padding: 12px 18px; }
            .record-layout { grid-template-columns: 1fr; }
            .container { max-width: 94%; margin: 25px auto; }
        }
        @media (max-width: 480px) {
            .header { align-items: flex-start; flex-direction: column; gap: 10px; }
            .nav { flex-wrap: wrap; }
            .section { padding: 19px; }
        }

        textarea {
            width: 100%;
            min-height: 100px;
            padding: 10px;
            margin-top: 5px;
            box-sizing: border-box;
            resize: vertical;
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

    </style>

</head>

<body>

    <header class="header">
        <a class="logo" href="dashboard.php">
            <span class="logo-icon"><i class="fa-solid fa-tooth" aria-hidden="true"></i></span>
            The Tooth Lounge
        </a>
        <nav class="nav" aria-label="Staff navigation">
            <a href="appointments.php"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Appointments</a>
            <a href="visit_records.php"><i class="fa-solid fa-file-medical" aria-hidden="true"></i> Visit records</a>
        </nav>
    </header>

    <main class="container">
        <section class="welcome">
            <h1>Create Visit Record</h1>
            <p>Review the current visit and enter its dental record.</p>
        </section>

    <div class="record-layout">
    <!-- CURRENT VISIT -->

    <section class="section patient-info">

        <h2><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i> Current visit</h2>
        <span class="appointment-badge"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Appointment completed</span>

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

    </section>


    <!-- VISIT RECORD FORM -->

    <section class="section">

        <h2>Visit Details</h2>

        <?php if ($message): ?>

            <div class="message <?= htmlspecialchars($message_type) ?>">

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>


        <form method="POST">
<?= csrf_field() ?>

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

    </section>

    </div>

    <nav class="bottom-links" aria-label="More staff links">
        <a href="appointments.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Appointments</a>
        <a href="dashboard.php"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i> Staff Dashboard</a>
        <a href="../auth/logout.php"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Logout</a>
    </nav>
    </main>

</body>

</html>
