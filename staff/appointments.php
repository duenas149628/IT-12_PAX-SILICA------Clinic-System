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

    <!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;

            font-family: Arial, Helvetica, sans-serif;

            color: #333;

            background:
                linear-gradient(
                    rgba(0, 135, 155, 0.78),
                    rgba(20, 85, 150, 0.78)
                ),
                url("https://images.unsplash.com/photo-1606811971618-4486d14f3f99?auto=format&fit=crop&w=1920&q=80");

            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }

        /* HEADER */

        .header {
            min-height: 75px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 12px 35px;

            background: rgba(255, 255, 255, 0.96);

            box-shadow:
                0 3px 15px rgba(0, 0, 0, 0.15);
        }

        /* LOGO */

        .logo {
            display: flex;
            align-items: center;

            gap: 12px;

            color: #167b8c;

            font-size: 21px;
            font-weight: bold;
        }

        .logo-icon {
            width: 43px;
            height: 43px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #e6f7fa;

            border-radius: 50%;

            color: #167b8c;

            font-size: 21px;
        }

        /* NAVIGATION */

        .nav {
            display: flex;
            align-items: center;

            gap: 5px;
        }

        .nav a {
            display: flex;
            align-items: center;

            gap: 7px;

            padding: 10px 13px;

            border-radius: 8px;

            color: #555;

            text-decoration: none;

            font-size: 14px;

            transition: 0.2s;
        }

        .nav a:hover,
        .nav a.active {
            background: #e6f7fa;
            color: #167b8c;
        }

        .nav a.active {
            font-weight: 600;
        }

        .logout {
            background: #167b8c !important;
            color: white !important;

            margin-left: 5px;
        }

        .logout:hover {
            background: #126c79 !important;
        }

        /* MAIN */

        .container {
            width: 1200px;
            max-width: 94%;

            margin: 40px auto;
        }

        /* PAGE TITLE */

        .page-title {
            color: white;

            margin-bottom: 25px;
        }

        .page-title h1 {
            display: flex;
            align-items: center;

            gap: 12px;

            font-size: 30px;

            margin-bottom: 8px;
        }

        .page-title p {
            font-size: 15px;
            opacity: 0.9;
        }

        /* SUMMARY */

        .summary {
            display: flex;
            align-items: center;
            justify-content: space-between;

            margin-bottom: 20px;

            padding: 20px 25px;

            background: rgba(255, 255, 255, 0.96);

            border-radius: 14px;

            box-shadow:
                0 8px 25px rgba(0, 0, 0, 0.14);
        }

        .summary-left {
            display: flex;
            align-items: center;

            gap: 12px;

            color: #555;

            font-size: 15px;
        }

        .summary-left i {
            color: #167b8c;

            font-size: 20px;
        }

        .count {
            min-width: 32px;
            height: 32px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            padding: 0 10px;

            background: #167b8c;

            color: white;

            border-radius: 20px;

            font-size: 13px;

            font-weight: bold;
        }

        /* TABLE */

        .table-container {
            background: rgba(255, 255, 255, 0.97);

            border-radius: 15px;

            overflow-x: auto;

            box-shadow:
                0 10px 30px rgba(0, 0, 0, 0.15);
        }

        table {
            width: 100%;

            min-width: 1050px;

            border-collapse: collapse;
        }

        th {
            padding: 16px;

            background: #e6f7fa;

            color: #167b8c;

            text-align: left;

            font-size: 13px;

            font-weight: 600;

            border-bottom: 1px solid #d6edef;
        }

        td {
            padding: 16px;

            border-bottom: 1px solid #eeeeee;

            color: #555;

            font-size: 14px;

            vertical-align: middle;
        }

        tbody tr {
            transition: 0.2s;
        }

        tbody tr:hover {
            background: #f7fcfd;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* PATIENT */

        .patient-name {
            display: flex;
            align-items: center;

            gap: 10px;

            color: #333;

            font-weight: 600;
        }

        .patient-icon {
            width: 35px;
            height: 35px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #e6f7fa;

            color: #167b8c;

            border-radius: 50%;

            font-size: 14px;
        }

        /* STATUS */

        .status {
            display: inline-flex;
            align-items: center;

            gap: 6px;

            padding: 6px 10px;

            background: #fff4d6;

            color: #9a6a00;

            border-radius: 20px;

            font-size: 12px;

            font-weight: 600;
        }

        /* MANAGE BUTTON */

        .button {
            display: inline-flex;
            align-items: center;

            gap: 7px;

            padding: 9px 14px;

            background: #167b8c;

            color: white;

            text-decoration: none;

            border-radius: 7px;

            font-size: 13px;

            font-weight: 500;

            transition: 0.2s;
        }

        .button:hover {
            background: #126c79;

            transform: translateY(-1px);
        }

        /* EMPTY */

        .empty {
            padding: 60px 30px;

            text-align: center;

            background: rgba(255, 255, 255, 0.97);

            border-radius: 15px;

            box-shadow:
                0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .empty-icon {
            width: 70px;
            height: 70px;

            display: flex;
            align-items: center;
            justify-content: center;

            margin: 0 auto 20px;

            background: #e6f7fa;

            color: #167b8c;

            border-radius: 50%;

            font-size: 28px;
        }

        .empty h2 {
            margin-bottom: 8px;

            color: #444;

            font-size: 20px;
        }

        .empty p {
            color: #777;

            font-size: 14px;
        }

        /* BOTTOM NAVIGATION */

        .bottom-navigation {
            display: flex;

            flex-wrap: wrap;

            gap: 10px;

            margin-top: 25px;
        }

        .bottom-navigation a {
            display: inline-flex;
            align-items: center;

            gap: 7px;

            padding: 10px 14px;

            background: rgba(255, 255, 255, 0.95);

            color: #555;

            text-decoration: none;

            border-radius: 8px;

            font-size: 13px;

            box-shadow:
                0 3px 10px rgba(0, 0, 0, 0.08);

            transition: 0.2s;
        }

        .bottom-navigation a:hover {
            color: #167b8c;

            background: white;
        }

        /* RESPONSIVE */

        @media (max-width: 900px) {

            .header {
                flex-direction: column;

                gap: 15px;

                padding: 15px 20px;
            }

            .nav {
                flex-wrap: wrap;

                justify-content: center;
            }

            .container {
                max-width: 94%;
            }
        }

        @media (max-width: 600px) {

            .page-title h1 {
                font-size: 25px;
            }

            .bottom-navigation {
                flex-direction: column;
            }

            .bottom-navigation a {
                justify-content: center;
            }
        }

    </style>

</head>


<body>


    <!-- HEADER -->

    <header class="header">

        <div class="logo">

            <div class="logo-icon">

                <i class="fa-solid fa-tooth"></i>

            </div>

            The Tooth Lounge

        </div>


        <nav class="nav">

            <a href="dashboard.php">

                <i class="fa-solid fa-gauge-high"></i>

                Dashboard

            </a>


            <a href="patients.php">

                <i class="fa-solid fa-users"></i>

                Patients

            </a>


            <a
                href="appointments.php"
                class="active"
            >

                <i class="fa-solid fa-calendar-check"></i>

                Appointments

            </a>


            <a href="visit_records.php">

                <i class="fa-solid fa-file-medical"></i>

                Records

            </a>


            <a
                href="../auth/logout.php"
                class="logout"
            >

                <i class="fa-solid fa-right-from-bracket"></i>

                Logout

            </a>

        </nav>

    </header>


    <!-- MAIN -->

    <main class="container">


        <section class="page-title">

            <h1>

                <i class="fa-solid fa-calendar-plus"></i>

                Appointment Requests

            </h1>

            <p>
                Review and manage pending appointment requests from patients.
            </p>

        </section>


        <?php if (empty($requests)): ?>


            <div class="empty">

                <div class="empty-icon">

                    <i class="fa-regular fa-calendar-check"></i>

                </div>

                <h2>
                    No Pending Requests
                </h2>

                <p>
                    There are currently no appointment requests waiting for review.
                </p>

            </div>


        <?php else: ?>


            <div class="summary">

                <div class="summary-left">

                    <i class="fa-solid fa-calendar-plus"></i>

                    <span>
                        Pending appointment requests
                    </span>

                    <span class="count">
                        <?= count($requests) ?>
                    </span>

                </div>

            </div>


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

                            <th>Status</th>

                            <th>Action</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach ($requests as $request): ?>

                            <tr>

                                <td>

                                    <div class="patient-name">

                                        <div class="patient-icon">

                                            <i class="fa-solid fa-user"></i>

                                        </div>

                                        <?= htmlspecialchars(
                                            $request['first_name']
                                            . ' '
                                            . $request['last_name']
                                        ) ?>

                                    </div>

                                </td>


                                <td>
                                    <?= htmlspecialchars(
                                        $request['patient_number']
                                    ) ?>
                                </td>


                                <td>
                                    <?= htmlspecialchars(
                                        $request['contact_number']
                                    ) ?>
                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        date(
                                            'M d, Y',
                                            strtotime(
                                                $request['preferred_date']
                                            )
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        date(
                                            'g:i A',
                                            strtotime(
                                                $request['preferred_start_time']
                                            )
                                        )
                                        . ' - ' .
                                        date(
                                            'g:i A',
                                            strtotime(
                                                $request['preferred_end_time']
                                            )
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $request['reason']
                                    ) ?>

                                </td>


                                <td>

                                    <span class="status">

                                        <i class="fa-solid fa-clock"></i>

                                        Pending

                                    </span>

                                </td>


                                <td>

                                    <a
                                        href="appointment_view.php?id=<?= $request['appointment_id'] ?>"
                                        class="button"
                                    >

                                        <i class="fa-solid fa-eye"></i>

                                        Manage

                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


        <?php endif; ?>


        <div class="bottom-navigation">

            <a href="dashboard.php">

                <i class="fa-solid fa-gauge-high"></i>

                Staff Dashboard

            </a>


            <a href="appointments.php">

                <i class="fa-solid fa-calendar-check"></i>

                All Appointments

            </a>


            <a href="patients.php">

                <i class="fa-solid fa-users"></i>

                Patient Records

            </a>


            <a href="visit_records.php">

                <i class="fa-solid fa-file-medical"></i>

                Visit Records

            </a>

        </div>

    </main>


</body>

</html>