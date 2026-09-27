<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../auth/login.php');
    exit;
}

$staffName = $_SESSION['name'] ?? 'Staff';

/*
|--------------------------------------------------------------------------
| Dashboard Counts
|--------------------------------------------------------------------------
*/

$pendingStmt = $pdo->query(
    "SELECT COUNT(*)
     FROM appointments
     WHERE status = 'pending'"
);

$pendingCount = $pendingStmt->fetchColumn();


$confirmedStmt = $pdo->query(
    "SELECT COUNT(*)
     FROM appointments
     WHERE status = 'confirmed'"
);

$confirmedCount = $confirmedStmt->fetchColumn();


$completedStmt = $pdo->query(
    "SELECT COUNT(*)
     FROM appointments
     WHERE status = 'completed'"
);

$completedCount = $completedStmt->fetchColumn();


$patientsStmt = $pdo->query(
    "SELECT COUNT(*)
     FROM patients"
);

$patientsCount = $patientsStmt->fetchColumn();


$visitRecordsStmt = $pdo->query(
    "SELECT COUNT(*)
     FROM visit_records"
);

$visitRecordsCount = $visitRecordsStmt->fetchColumn();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Staff Dashboard - The Tooth Lounge</title>

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
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(180px, 1fr)
            );

            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #ffffff;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #ddd;
        }

        .stat-card h3 {
            margin: 0 0 10px;
            font-size: 16px;
            color: #666;
        }

        .stat-number {
            font-size: 30px;
            font-weight: bold;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(220px, 1fr)
            );

            gap: 20px;
        }

        .card {
            background: #ffffff;
            padding: 25px;
            border-radius: 10px;
            border: 1px solid #ddd;
        }

        .card h2 {
            margin-top: 0;
        }

        .card p {
            color: #666;
            line-height: 1.5;
        }

        .button {
            display: inline-block;
            margin-top: 10px;
            padding: 10px 16px;
            background: #333;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
        }

        .logout {
            display: inline-block;
            margin-top: 30px;
            color: #c00;
            text-decoration: none;
        }

    </style>

</head>

<body>

    <div class="header">

        <h1>Staff Dashboard</h1>

        <p>
            Welcome, <?= htmlspecialchars($staffName) ?>
        </p>

    </div>


    <div class="container">

        <!-- Dashboard Statistics -->

        <div class="stats">

            <div class="stat-card">

                <h3>Pending Requests</h3>

                <div class="stat-number">
                    <?= $pendingCount ?>
                </div>

            </div>


            <div class="stat-card">

                <h3>Confirmed Appointments</h3>

                <div class="stat-number">
                    <?= $confirmedCount ?>
                </div>

            </div>


            <div class="stat-card">

                <h3>Completed Appointments</h3>

                <div class="stat-number">
                    <?= $completedCount ?>
                </div>

            </div>


            <div class="stat-card">

                <h3>Total Patients</h3>

                <div class="stat-number">
                    <?= $patientsCount ?>
                </div>

            </div>


            <div class="stat-card">

                <h3>Visit Records</h3>

                <div class="stat-number">
                    <?= $visitRecordsCount ?>
                </div>

            </div>

        </div>


        <!-- Main Dashboard Cards -->

        <div class="cards">

            <div class="card">

                <h2>Appointments</h2>

                <p>
                    View and manage all patient appointments.
                </p>

                <a
                    href="appointments.php"
                    class="button"
                >
                    View Appointments
                </a>

            </div>


            <div class="card">

                <h2>Appointment Requests</h2>

                <p>
                    Review pending appointment requests
                    submitted by patients.
                </p>

                <a
                    href="appointment_requests.php"
                    class="button"
                >
                    View Requests
                </a>

            </div>


            <div class="card">

                <h2>Patient Records</h2>

                <p>
                    Search and manage patient information,
                    appointments, and visit history.
                </p>

                <a
                    href="patients.php"
                    class="button"
                >
                    View Patients
                </a>

            </div>


            <div class="card">

                <h2>Visit Records</h2>

                <p>
                    View and manage completed patient
                    visit records.
                </p>

                <a
                    href="visit_records.php"
                    class="button"
                >
                    View Visit Records
                </a>

            </div>

        </div>


        <a
            href="../auth/logout.php"
            class="logout"
        >
            Logout
        </a>

    </div>

</body>

</html>