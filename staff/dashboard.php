<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../auth/login.php');
    exit;
}

$patientCount = $pdo->query(
    "SELECT COUNT(*) FROM patients"
)->fetchColumn();

$appointmentCount = $pdo->query(
    "SELECT COUNT(*) FROM appointments"
)->fetchColumn();

$visitCount = $pdo->query(
    "SELECT COUNT(*) FROM visit_records"
)->fetchColumn();

$pendingCount = $pdo->query(
    "SELECT COUNT(*) FROM appointments WHERE status = 'pending'"
)->fetchColumn();

$todayAppointmentCount = $pdo->query(
    "SELECT COUNT(*) FROM appointments WHERE status = 'confirmed' AND confirmed_date = CURDATE()"
)->fetchColumn();

$confirmedCount = $pdo->query(
    "SELECT COUNT(*) FROM appointments WHERE status = 'confirmed'"
)->fetchColumn();

$completedCount = $pdo->query(
    "SELECT COUNT(*) FROM appointments WHERE status = 'completed'"
)->fetchColumn();

$monthVisitCount = $pdo->query(
    "SELECT COUNT(*) FROM visit_records WHERE visit_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND visit_date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)"
)->fetchColumn();

$completionRate = (int) $appointmentCount > 0
    ? (int) round(((int) $completedCount / (int) $appointmentCount) * 100)
    : 0;

$notificationStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE recipient_user_id = ? AND read_at IS NULL');
$notificationStmt->execute([$_SESSION['user_id']]);
$unreadNotificationCount = (int) $notificationStmt->fetchColumn();

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

            font-family:
                Arial,
                Helvetica,
                sans-serif;

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

        .nav a:hover {
            background: #e6f7fa;
            color: #167b8c;
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
            width: 1100px;
            max-width: 92%;

            margin: 40px auto;
        }

        /* WELCOME */

        .welcome {
            margin-bottom: 25px;

            color: white;
        }

        .welcome h1 {
            font-size: 32px;
            font-weight: 600;

            margin-bottom: 8px;
        }

        .welcome p {
            font-size: 16px;
            opacity: 0.9;
        }

        /* STATISTICS */

        .dashboard-grid {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 20px;

            margin-bottom: 30px;
        }

        .card {
            padding: 25px;

            background: rgba(255, 255, 255, 0.96);

            border-radius: 15px;

            box-shadow:
                0 10px 30px rgba(0, 0, 0, 0.15);

            transition: 0.2s;
        }

        .card:hover {
            transform: translateY(-4px);

            box-shadow:
                0 15px 35px rgba(0, 0, 0, 0.20);
        }

        .card-icon {
            width: 50px;
            height: 50px;

            display: flex;
            align-items: center;
            justify-content: center;

            margin-bottom: 15px;

            background: #e6f7fa;

            color: #167b8c;

            border-radius: 12px;

            font-size: 21px;
        }

        .card h3 {
            margin-bottom: 8px;

            color: #555;

            font-size: 14px;

            font-weight: 500;
        }

        .card-number {
            color: #167b8c;

            font-size: 30px;

            font-weight: bold;
        }

        /* PANEL */

        .panel {
            padding: 30px;

            background: rgba(255, 255, 255, 0.96);

            border-radius: 15px;

            box-shadow:
                0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .panel h2 {
            margin-bottom: 20px;

            color: #167b8c;

            font-size: 22px;
        }

        /* QUICK ACTIONS */

        .quick-actions {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 15px;
        }

        .action {
            display: block;

            padding: 20px;

            border: 1px solid #e0e0e0;

            border-radius: 10px;

            color: #444;

            text-decoration: none;

            transition: 0.2s;
        }

        .action:hover {
            background: #f1fbfc;

            border-color: #168ca0;

            transform: translateY(-2px);
        }

        .action strong {
            display: block;

            margin-bottom: 8px;

            color: #167b8c;

            font-size: 16px;
        }

        .action strong i {
            margin-right: 8px;
        }

        .action span {
            font-size: 13px;
            color: #777;
        }

        /* TABLET */

        @media (max-width: 950px) {

            .dashboard-grid {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }

        }

        /* MOBILE */

        @media (max-width: 650px) {

            .header {
                flex-direction: column;

                gap: 15px;

                padding: 15px;
            }

            .nav {
                flex-wrap: wrap;

                justify-content: center;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .container {
                max-width: 94%;

                margin: 25px auto;
            }

            .welcome h1 {
                font-size: 26px;
            }

            .panel {
                padding: 20px;
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


            <a href="appointments.php">

                <i class="fa-solid fa-calendar-check"></i>

                Appointments

            </a>


            <a href="visit_records.php">

                <i class="fa-solid fa-file-medical"></i>

                Records

            </a>

            <a href="reports.php">

                <i class="fa-solid fa-chart-column"></i>

                Reports

            </a>

            <a href="../notifications.php">
                <i class="fa-regular fa-bell"></i>
                Notifications<?= $unreadNotificationCount > 0 ? ' (' . h($unreadNotificationCount) . ')' : '' ?>
            </a>

            <a href="activity_log.php">
                <i class="fa-solid fa-list-check"></i>
                Activity log
            </a>

            <a href="../auth/password_change.php">Change password</a>


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


        <!-- WELCOME -->

        <section class="welcome">

            <h1>
                Staff Dashboard
            </h1>

            <p>

                Welcome back,
                <?= htmlspecialchars(
                    $_SESSION['name'] ?? 'Staff'
                ) ?>.

            </p>

        </section>


        <!-- STATISTICS -->

        <section class="dashboard-grid">


            <!-- PATIENTS -->

            <div class="card">

                <div class="card-icon">

                    <i class="fa-solid fa-users"></i>

                </div>

                <h3>
                    Total Patients
                </h3>

                <div class="card-number">

                    <?= htmlspecialchars($patientCount) ?>

                </div>

            </div>


            <!-- APPOINTMENTS -->

            <div class="card">

                <div class="card-icon">

                    <i class="fa-solid fa-calendar-check"></i>

                </div>

                <h3>
                    Appointments
                </h3>

                <div class="card-number">

                    <?= htmlspecialchars($appointmentCount) ?>

                </div>

            </div>


            <!-- VISITS -->

            <div class="card">

                <div class="card-icon">

                    <i class="fa-solid fa-file-medical"></i>

                </div>

                <h3>
                    Visit Records
                </h3>

                <div class="card-number">

                    <?= htmlspecialchars($visitCount) ?>

                </div>

            </div>


            <!-- PENDING -->

            <div class="card">

                <div class="card-icon">

                    <i class="fa-solid fa-clock"></i>

                </div>

                <h3>
                    Pending Appointments
                </h3>

                <div class="card-number">

                    <?= htmlspecialchars($pendingCount) ?>

                </div>

            </div>

            <div class="card">
                <div class="card-icon"><i class="fa-solid fa-calendar-day"></i></div>
                <h3>Today's Appointments</h3>
                <div class="card-number"><?= h($todayAppointmentCount) ?></div>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fa-solid fa-calendar-check"></i></div>
                <h3>Confirmed Appointments</h3>
                <div class="card-number"><?= h($confirmedCount) ?></div>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fa-solid fa-circle-check"></i></div>
                <h3>Completed Appointments</h3>
                <div class="card-number"><?= h($completedCount) ?></div>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fa-solid fa-chart-line"></i></div>
                <h3>Visit Records This Month</h3>
                <div class="card-number"><?= h($monthVisitCount) ?></div>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fa-solid fa-percent"></i></div>
                <h3>Appointment Completion</h3>
                <div class="card-number"><?= h($completionRate) ?>%</div>
            </div>

        </section>


        <!-- QUICK ACTIONS -->

        <section class="panel">

            <h2>
                Quick Actions
            </h2>


            <div class="quick-actions">


                <a
                    href="patients.php"
                    class="action"
                >

                    <strong>

                        <i class="fa-solid fa-users"></i>

                        Manage Patients

                    </strong>

                    <span>
                        View and manage patient records.
                    </span>

                </a>


                <a
                    href="appointments.php"
                    class="action"
                >

                    <strong>

                        <i class="fa-solid fa-calendar-days"></i>

                        Manage Appointments

                    </strong>

                    <span>
                        Review and manage clinic appointments.
                    </span>

                </a>


                <a
                    href="visit_records.php"
                    class="action"
                >

                    <strong>

                        <i class="fa-solid fa-file-medical"></i>

                        Visit Records

                    </strong>

                    <span>
                        Manage patient visit records.
                    </span>

                </a>

                <a href="reports.php" class="action">
                    <strong><i class="fa-solid fa-chart-column"></i> View Reports</strong>
                    <span>Review appointment and visit activity by date range.</span>
                </a>


            </div>

        </section>


    </main>


</body>

</html>
