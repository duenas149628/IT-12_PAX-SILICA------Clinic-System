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

$visit_id = request_string($_GET, 'id');

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
    csrf_verify();

    $chief_complaint = request_string($_POST, 'chief_complaint');
    $findings = request_string($_POST, 'findings');
    $treatment = request_string($_POST, 'treatment');
    $notes = request_string($_POST, 'notes');
    $follow_up = request_string($_POST, 'follow_up');

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

        audit_log('visit.record.updated', ['visit_id' => (int) $visit_id]);

        header(
            'Location: visit_view.php?id=' . $visit_id
        );

        exit;
    }
}

function e($value)
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

function formatDate($date)
{
    if (!$date) {
        return 'Not recorded';
    }

    return date('F d, Y', strtotime($date));
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

    <title>
        Edit Visit Record - The Tooth Lounge
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;

            min-height: 100vh;

            background:
                linear-gradient(
                    135deg,
                    rgba(220, 245, 246, 0.95),
                    rgba(238, 248, 249, 0.95)
                );

            color: #263238;
        }

        .page {
            min-height: 100vh;
            padding: 30px 20px 50px;
        }

        /* TOP BAR */

        .topbar {
            max-width: 1100px;
            margin: 0 auto 25px;

            display: flex;
            justify-content: space-between;
            align-items: center;

            background: rgba(255, 255, 255, 0.94);

            padding: 18px 25px;

            border-radius: 18px;

            box-shadow:
                0 8px 25px rgba(40, 100, 110, 0.10);

            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-icon {
            width: 45px;
            height: 45px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #d8f0f1;
            color: #2d777d;

            border-radius: 12px;

            font-size: 21px;
            font-weight: bold;
        }

        .brand h1 {
            margin: 0;

            font-size: 20px;

            color: #245f65;
        }

        .brand p {
            margin: 3px 0 0;

            color: #7b8b8d;

            font-size: 13px;
        }

        .back-link {
            text-decoration: none;

            color: #2d777d;

            font-weight: bold;

            font-size: 14px;
        }

        /* CONTAINER */

        .container {
            max-width: 900px;

            margin: auto;
        }

        .page-title {
            margin-bottom: 25px;
        }

        .page-title h2 {
            margin: 0;

            color: #245f65;

            font-size: 30px;
        }

        .page-title p {
            margin-top: 8px;

            color: #718083;
        }

        /* CARDS */

        .card {
            background: rgba(255, 255, 255, 0.95);

            border-radius: 18px;

            padding: 25px;

            margin-bottom: 20px;

            box-shadow:
                0 8px 25px rgba(40, 100, 110, 0.10);

            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        .card-header {
            display: flex;

            align-items: center;

            gap: 12px;

            margin-bottom: 22px;

            padding-bottom: 15px;

            border-bottom: 1px solid #e8eeee;
        }

        .card-icon {
            width: 42px;
            height: 42px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 11px;

            background: #e3f4f5;

            color: #2d777d;

            font-size: 19px;
        }

        .card-header h3 {
            margin: 0;

            color: #285f64;

            font-size: 18px;
        }

        /* PATIENT INFORMATION */

        .info-grid {
            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 15px;
        }

        .info-item {
            padding: 14px;

            background: #f7fbfb;

            border-radius: 12px;

            border: 1px solid #e8f0f0;
        }

        .label {
            display: block;

            font-size: 12px;

            font-weight: bold;

            text-transform: uppercase;

            letter-spacing: 0.5px;

            color: #819092;

            margin-bottom: 6px;
        }

        .value {
            font-size: 15px;

            color: #304548;

            font-weight: 500;
        }

        /* MESSAGE */

        .message {
            padding: 13px 15px;

            margin-bottom: 20px;

            border-radius: 10px;

            font-size: 14px;
        }

        .message.error {
            background: #fff1f1;

            color: #a94442;

            border: 1px solid #f0cccc;
        }

        /* FORM */

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;

            margin-bottom: 8px;

            color: #2d777d;

            font-weight: bold;

            font-size: 14px;
        }

        textarea {
            width: 100%;

            min-height: 120px;

            padding: 14px;

            border: 1px solid #d5e4e5;

            border-radius: 12px;

            background: #fbfdfd;

            color: #304548;

            font-family: Arial, Helvetica, sans-serif;

            font-size: 14px;

            line-height: 1.5;

            resize: vertical;

            outline: none;

            transition: 0.2s;
        }

        textarea:focus {
            border-color: #2d777d;

            box-shadow:
                0 0 0 3px rgba(45, 119, 125, 0.10);

            background: white;
        }

        textarea::placeholder {
            color: #a4b1b3;
        }

        /* BUTTONS */

        .actions {
            display: flex;

            gap: 12px;

            flex-wrap: wrap;

            margin-top: 25px;
        }

        button,
        .button {
            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding: 12px 18px;

            border-radius: 10px;

            font-size: 14px;

            font-weight: bold;

            text-decoration: none;

            cursor: pointer;

            transition: 0.2s;
        }

        .save-button {
            border: none;

            background: #2d777d;

            color: white;

            flex: 1;
        }

        .save-button:hover {
            background: #245f65;
        }

        .cancel-button {
            background: white;

            color: #2d777d;

            border: 1px solid #cfe1e2;
        }

        .cancel-button:hover {
            background: #f1f8f8;
        }

        .dashboard-button {
            background: #f7fbfb;

            color: #527477;

            border: 1px solid #dce9e9;
        }

        .dashboard-button:hover {
            background: #edf6f6;
        }

        /* BOTTOM NAVIGATION */

        .bottom-navigation {
            display: flex;

            justify-content: center;

            gap: 20px;

            flex-wrap: wrap;

            margin-top: 25px;
        }

        .bottom-navigation a {
            color: #2d777d;

            text-decoration: none;

            font-size: 14px;

            font-weight: 500;
        }

        .bottom-navigation a:hover {
            text-decoration: underline;
        }

        /* MOBILE */

        @media (max-width: 700px) {

            .page {
                padding: 15px;
            }

            .topbar {
                padding: 15px;
            }

            .back-link {
                display: none;
            }

            .page-title h2 {
                font-size: 25px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .card {
                padding: 20px;
            }

            .actions {
                flex-direction: column;
            }

            .save-button {
                flex: none;
                width: 100%;
            }

            .cancel-button,
            .dashboard-button {
                width: 100%;
            }

        }

    </style>

</head>

<body>
<?php require __DIR__ . '/../includes/navigation.php'; ?>

<div class="page">


    <!-- TOP BAR -->

    <div class="topbar">

        <div class="brand">

            <div class="brand-icon">
                +
            </div>

            <div>

                <h1>
                    The Tooth Lounge
                </h1>

                <p>
                    Staff Management System
                </p>

            </div>

        </div>

        <a
            href="visit_view.php?id=<?= $visit['visit_id'] ?>"
            class="back-link"
        >
            &larr; Back to Record
        </a>

    </div>


    <div class="container">


        <!-- PAGE TITLE -->

        <div class="page-title">

            <h2>
                Edit Visit Record
            </h2>

            <p>
                Update the patient's consultation and treatment information.
            </p>

        </div>


        <!-- PATIENT INFORMATION -->

        <div class="card">

            <div class="card-header">

                <div class="card-icon">
                    P
                </div>

                <h3>
                    Patient Information
                </h3>

            </div>


            <div class="info-grid">


                <div class="info-item">

                    <span class="label">
                        Patient Name
                    </span>

                    <span class="value">

                        <?= e(
                            $visit['first_name']
                            . ' '
                            . $visit['last_name']
                        ) ?>

                    </span>

                </div>


                <div class="info-item">

                    <span class="label">
                        Patient Number
                    </span>

                    <span class="value">

                        <?= e(
                            $visit['patient_number']
                        ) ?>

                    </span>

                </div>


                <div class="info-item">

                    <span class="label">
                        Contact Number
                    </span>

                    <span class="value">

                        <?= e(
                            $visit['contact_number']
                        ) ?>

                    </span>

                </div>


                <div class="info-item">

                    <span class="label">
                        Visit Date
                    </span>

                    <span class="value">

                        <?= e(
                            formatDate(
                                $visit['visit_date']
                            )
                        ) ?>

                    </span>

                </div>

            </div>

        </div>


        <!-- EDIT FORM -->

        <div class="card">

            <div class="card-header">

                <div class="card-icon">
                    E
                </div>

                <h3>
                    Visit Details
                </h3>

            </div>


            <?php if ($message): ?>

                <div
                    class="message <?= e($message_type) ?>"
                >

                    <?= e($message) ?>

                </div>

            <?php endif; ?>


            <form method="POST">
<?= csrf_field() ?>


                <!-- CHIEF COMPLAINT -->

                <div class="form-group">

                    <label for="chief_complaint">
                        Chief Complaint
                    </label>

                    <textarea
                        id="chief_complaint"
                        name="chief_complaint"
                        placeholder="Enter the patient's main complaint..."
                    ><?= e(
                        $visit['chief_complaint']
                    ) ?></textarea>

                </div>


                <!-- FINDINGS -->

                <div class="form-group">

                    <label for="findings">
                        Findings
                    </label>

                    <textarea
                        id="findings"
                        name="findings"
                        placeholder="Enter examination findings..."
                    ><?= e(
                        $visit['findings']
                    ) ?></textarea>

                </div>


                <!-- TREATMENT -->

                <div class="form-group">

                    <label for="treatment">
                        Treatment
                    </label>

                    <textarea
                        id="treatment"
                        name="treatment"
                        placeholder="Enter the treatment provided..."
                    ><?= e(
                        $visit['treatment']
                    ) ?></textarea>

                </div>


                <!-- NOTES -->

                <div class="form-group">

                    <label for="notes">
                        Notes
                    </label>

                    <textarea
                        id="notes"
                        name="notes"
                        placeholder="Enter additional notes..."
                    ><?= e(
                        $visit['notes']
                    ) ?></textarea>

                </div>


                <!-- FOLLOW UP -->

                <div class="form-group">

                    <label for="follow_up">
                        Follow-Up
                    </label>

                    <textarea
                        id="follow_up"
                        name="follow_up"
                        placeholder="Enter recommended follow-up instructions..."
                    ><?= e(
                        $visit['follow_up']
                    ) ?></textarea>

                </div>


                <!-- ACTION BUTTONS -->

                <div class="actions">

                    <button
                        type="submit"
                        class="save-button"
                    >
                        Save Changes
                    </button>

                    <a
                        href="visit_view.php?id=<?= $visit['visit_id'] ?>"
                        class="button cancel-button"
                    >
                        Cancel
                    </a>

                    <a
                        href="dashboard.php"
                        class="button dashboard-button"
                    >
                        Staff Dashboard
                    </a>

                </div>

            </form>

        </div>


        <!-- NAVIGATION -->

        <div class="bottom-navigation">

            <a href="visit_records.php">
                Visit Records
            </a>

            <a href="patients.php">
                Patient Records
            </a>

            <a href="appointments.php">
                Appointments
            </a>

            <a href="../auth/logout.php">
                Logout
            </a>

        </div>

    </div>

</div>

</body>

</html>
