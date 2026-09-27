<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../auth/login.php');
    exit;
}

$stmt = $pdo->query(
    "SELECT
        appointments.appointment_id,
        appointments.preferred_date,
        appointments.preferred_start_time,
        appointments.preferred_end_time,
        appointments.reason,
        appointments.status,
        patients.patient_number,
        patients.first_name,
        patients.last_name,
        patients.contact_number
     FROM appointments
     INNER JOIN patients
        ON appointments.patient_id = patients.patient_id
     WHERE appointments.status = 'pending'
     ORDER BY appointments.preferred_date ASC,
              appointments.preferred_start_time ASC"
);

$requests = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Appointment Requests - The Tooth Lounge</title>

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
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .table-container {
            background: #ffffff;
            border: 1px solid #ddd;
            border-radius: 10px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 14px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #f1f1f1;
        }

        .button {
            display: inline-block;
            padding: 8px 12px;
            background: #333;
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
        }

        .empty {
            background: #ffffff;
            padding: 25px;
            border: 1px solid #ddd;
            border-radius: 10px;
        }

        .navigation {
            margin-top: 25px;
        }

        .navigation a {
            margin-right: 15px;
            text-decoration: none;
        }
    </style>
</head>

<body>

    <div class="header">
        <h1>Appointment Requests</h1>
        <p>Pending appointment requests from patients</p>
    </div>

    <div class="container">

        <?php if (empty($requests)): ?>

            <div class="empty">
                <p>No pending appointment requests.</p>
            </div>

        <?php else: ?>

            <div class="table-container">

                <table>

                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Patient Number</th>
                            <th>Contact</th>
                            <th>Preferred Date</th>
                            <th>Preferred Time</th>
                            <th>Reason</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($requests as $request): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars(
                                        $request['first_name'] . ' ' . $request['last_name']
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($request['patient_number']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($request['contact_number']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($request['preferred_date']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        date('g:i A', strtotime($request['preferred_start_time']))
                                        . ' - ' .
                                        date('g:i A', strtotime($request['preferred_end_time']))
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($request['reason']) ?>
                                </td>

                                <td>
                                    <a
                                        href="appointment_view.php?id=<?= $request['appointment_id'] ?>"
                                        class="button"
                                    >
                                        Manage
                                    </a>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>


        <div class="navigation">

            <a href="dashboard.php">
                Staff Dashboard
            </a>

            <a href="appointments.php">
                All Appointments
            </a>

            <a href="patients.php">
                Patient Records
            </a>

            <a href="visit_records.php">
                Visit Records
            </a>

            <a href="../auth/logout.php">
                Logout
            </a>

        </div>

    </div>

</body>

</html>