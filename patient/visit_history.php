<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Get Patient ID
|--------------------------------------------------------------------------
*/

$patientStmt = $pdo->prepare(
    "SELECT patient_id
     FROM patients
     WHERE user_id = ?"
);

$patientStmt->execute([$user_id]);

$patient = $patientStmt->fetch();

if (!$patient) {
    die('Patient profile not found.');
}

$patient_id = $patient['patient_id'];


/*
|--------------------------------------------------------------------------
| Get Visit History
|--------------------------------------------------------------------------
*/

$visitStmt = $pdo->prepare(
    "SELECT
        visit_id,
        visit_date,
        chief_complaint,
        findings,
        treatment,
        notes,
        follow_up
     FROM visit_records
     WHERE patient_id = ?
     ORDER BY visit_date DESC, visit_id DESC"
);

$visitStmt->execute([$patient_id]);

$visits = $visitStmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Visit History - The Tooth Lounge</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f4f7f9;
            margin: 0;
            padding: 0;
        }

        .header {
            background: #ffffff;
            padding: 20px 40px;
            border-bottom: 1px solid #ddd;
        }

        .header h1 {
            margin: 0 0 5px;
        }

        .header p {
            margin: 0;
            color: #666;
        }

        .container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .visit {
            background: #ffffff;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
        }

        .visit h2 {
            margin-top: 0;
        }

        .visit-section {
            margin-top: 20px;
        }

        .visit-section strong {
            display: block;
            margin-bottom: 5px;
        }

        .visit-section p {
            margin: 0;
            line-height: 1.6;
            color: #444;
            white-space: pre-wrap;
        }

        .empty {
            background: #ffffff;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 25px;
        }

        .navigation {
            margin-top: 30px;
        }

        .navigation a {
            margin-right: 15px;
            text-decoration: none;
        }

    </style>

</head>

<body>

    <div class="header">

        <h1>My Visit History</h1>

        <p>
            View your previous clinic visit records.
        </p>

    </div>


    <div class="container">

        <?php if (empty($visits)): ?>

            <div class="empty">

                <p>
                    You do not have any visit records yet.
                </p>

            </div>

        <?php else: ?>

            <?php foreach ($visits as $visit): ?>

                <div class="visit">

                    <h2>
                        Visit Date:
                        <?= htmlspecialchars($visit['visit_date']) ?>
                    </h2>


                    <div class="visit-section">

                        <strong>
                            Chief Complaint
                        </strong>

                        <p>
                            <?= htmlspecialchars(
                                $visit['chief_complaint'] ?? ''
                            ) ?>
                        </p>

                    </div>


                    <div class="visit-section">

                        <strong>
                            Findings
                        </strong>

                        <p>
                            <?= htmlspecialchars(
                                $visit['findings'] ?? ''
                            ) ?>
                        </p>

                    </div>


                    <div class="visit-section">

                        <strong>
                            Treatment
                        </strong>

                        <p>
                            <?= htmlspecialchars(
                                $visit['treatment'] ?? ''
                            ) ?>
                        </p>

                    </div>


                    <div class="visit-section">

                        <strong>
                            Notes
                        </strong>

                        <p>
                            <?= htmlspecialchars(
                                $visit['notes'] ?? ''
                            ) ?>
                        </p>

                    </div>


                    <div class="visit-section">

                        <strong>
                            Follow-up
                        </strong>

                        <p>
                            <?= htmlspecialchars(
                                $visit['follow_up'] ?? ''
                            ) ?>
                        </p>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>


        <div class="navigation">

            <a href="dashboard.php">
                Patient Dashboard
            </a>

            <a href="appointments.php">
                My Appointments
            </a>

            <a href="profile.php">
                My Profile
            </a>

            <a href="../auth/logout.php">
                Logout
            </a>

        </div>

    </div>

</body>

</html>