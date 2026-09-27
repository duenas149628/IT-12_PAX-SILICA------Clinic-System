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

$user_id = $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Get patient ID
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT patient_id
     FROM patients
     WHERE user_id = ?"
);

$stmt->execute([$user_id]);

$patient = $stmt->fetch();

if (!$patient) {
    die('Patient profile not found.');
}

$patient_id = $patient['patient_id'];

/*
|--------------------------------------------------------------------------
| Get patient's appointments
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT *
     FROM appointments
     WHERE patient_id = ?
     ORDER BY created_at DESC"
);

$stmt->execute([$patient_id]);

$appointments = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Appointments - The Tooth Lounge</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: left;
        }

        th {
            background: #f0f0f0;
        }

        .status {
            font-weight: bold;
        }

        a {
            display: inline-block;
            margin-top: 15px;
        }

    </style>

</head>

<body>

    <h1>My Appointments</h1>

    <?php if (count($appointments) === 0): ?>

        <p>
            You do not have any appointment requests yet.
        </p>

    <?php else: ?>

        <table>

            <thead>

                <tr>
                    <th>Preferred Date</th>
                    <th>Preferred Time</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Confirmed Schedule</th>
                </tr>

            </thead>

            <tbody>

                <?php foreach ($appointments as $appointment): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                $appointment['preferred_date']
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                date(
                                    'g:i A',
                                    strtotime(
                                        $appointment['preferred_start_time']
                                    )
                                )
                            ) ?>

                            -

                            <?= htmlspecialchars(
                                date(
                                    'g:i A',
                                    strtotime(
                                        $appointment['preferred_end_time']
                                    )
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $appointment['reason'] ?? ''
                            ) ?>
                        </td>

                        <td class="status">

                            <?= htmlspecialchars(
                                ucfirst(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $appointment['status']
                                    )
                                )
                            ) ?>

                        </td>

                        <td>

                            <?php if (
                                $appointment['confirmed_date']
                                && $appointment['confirmed_start_time']
                                && $appointment['confirmed_end_time']
                            ): ?>

                                <?= htmlspecialchars(
                                    $appointment['confirmed_date']
                                ) ?>

                                <br>

                                <?= htmlspecialchars(
                                    date(
                                        'g:i A',
                                        strtotime(
                                            $appointment['confirmed_start_time']
                                        )
                                    )
                                ) ?>

                                -

                                <?= htmlspecialchars(
                                    date(
                                        'g:i A',
                                        strtotime(
                                            $appointment['confirmed_end_time']
                                        )
                                    )
                                ) ?>

                            <?php else: ?>

                                Not yet confirmed

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    <?php endif; ?>

    <a href="request.php">
        Request Another Appointment
    </a>

    <br>

    <a href="dashboard.php">
        Back to Dashboard
    </a>

    <br>

    <a href="../auth/logout.php">
        Logout
    </a>

</body>

</html>