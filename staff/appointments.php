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

$stmt = $pdo->query(
    "SELECT
        appointments.*,
        patients.patient_number,
        patients.first_name,
        patients.last_name,
        patients.contact_number
     FROM appointments
     INNER JOIN patients
        ON appointments.patient_id = patients.patient_id
     ORDER BY appointments.created_at DESC"
);

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

    <title>Appointment Requests - The Tooth Lounge</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            max-width: 1100px;
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

        a {
            display: inline-block;
            margin-top: 15px;
        }

    </style>

</head>

<body>

    <h1>Appointment Requests</h1>

    <?php if (empty($appointments)): ?>

        <p>No appointments found.</p>

    <?php else: ?>

        <table>

            <thead>

                <tr>

                    <th>Patient</th>
                    <th>Patient Number</th>
                    <th>Contact</th>
                    <th>Preferred Date</th>
                    <th>Preferred Time</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Action</th>

                </tr>

            </thead>

            <tbody>

                <?php foreach ($appointments as $appointment): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                $appointment['first_name']
                                . ' '
                                . $appointment['last_name']
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $appointment['patient_number']
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $appointment['contact_number'] ?? ''
                            ) ?>
                        </td>

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

                        <td>
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

                            <a href="appointment_view.php?id=<?= $appointment['appointment_id'] ?>">
                                Manage
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    <?php endif; ?>

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