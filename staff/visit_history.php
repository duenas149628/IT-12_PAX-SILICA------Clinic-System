<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SESSION['role'] !== 'patient') {
    die('Access denied.');
}

/*
|--------------------------------------------------------------------------
| Get Patient
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT patient_id
     FROM patients
     WHERE user_id = ?"
);

$stmt->execute([$_SESSION['user_id']]);

$patient = $stmt->fetch();

if (!$patient) {
    die('Patient record not found.');
}

$patient_id = (int) $patient['patient_id'];

/*
|--------------------------------------------------------------------------
| Get Visit History
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT
        visit_records.*,
        appointments.confirmed_start_time,
        appointments.confirmed_end_time
     FROM visit_records
     INNER JOIN appointments
        ON visit_records.appointment_id = appointments.appointment_id
     WHERE visit_records.patient_id = ?
     ORDER BY visit_records.visit_date DESC,
              visit_records.visit_id DESC"
);

$stmt->execute([$patient_id]);

$visits = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Visit History - The Tooth Lounge</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
        }

        .visit {
            border: 1px solid #ccc;
            padding: 20px;
            margin-top: 20px;
            border-radius: 8px;
        }

        .field {
            margin-top: 15px;
        }

        .field strong {
            display: block;
            margin-bottom: 5px;
        }

        .empty {
            border: 1px solid #ccc;
            padding: 20px;
            margin-top: 20px;
            border-radius: 8px;
        }

        a {
            display: inline-block;
            margin-top: 15px;
        }

    </style>

</head>

<body>

    <h1>My Visit History</h1>

    <p>
        View your previous completed dental visits.
    </p>


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
                    <?= htmlspecialchars(
                        $visit['visit_date']
                    ) ?>
                </h2>


                <div class="field">

                    <strong>
                        Chief Complaint
                    </strong>

                    <div>
                        <?= nl2br(
                            htmlspecialchars(
                                $visit['chief_complaint'] ?? ''
                            )
                        ) ?: 'No information recorded.' ?>
                    </div>

                </div>


                <div class="field">

                    <strong>
                        Findings
                    </strong>

                    <div>
                        <?= nl2br(
                            htmlspecialchars(
                                $visit['findings'] ?? ''
                            )
                        ) ?: 'No information recorded.' ?>
                    </div>

                </div>


                <div class="field">

                    <strong>
                        Treatment
                    </strong>

                    <div>
                        <?= nl2br(
                            htmlspecialchars(
                                $visit['treatment'] ?? ''
                            )
                        ) ?: 'No information recorded.' ?>
                    </div>

                </div>


                <div class="field">

                    <strong>
                        Notes
                    </strong>

                    <div>
                        <?= nl2br(
                            htmlspecialchars(
                                $visit['notes'] ?? ''
                            )
                        ) ?: 'No information recorded.' ?>
                    </div>

                </div>


                <div class="field">

                    <strong>
                        Follow-Up
                    </strong>

                    <div>
                        <?= nl2br(
                            htmlspecialchars(
                                $visit['follow_up'] ?? ''
                            )
                        ) ?: 'No information recorded.' ?>
                    </div>

                </div>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>


    <br>

    <a href="dashboard.php">
        Back to Patient Dashboard
    </a>

    <br>

    <a href="appointments.php">
        My Appointments
    </a>

    <br>

    <a href="profile.php">
        My Profile
    </a>

    <br>

    <a href="../auth/logout.php">
        Logout
    </a>

</body>

</html>