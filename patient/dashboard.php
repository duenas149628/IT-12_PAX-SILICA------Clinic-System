<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../auth/login.php');
    exit;
}

$patientName = $_SESSION['name'] ?? 'Patient';

/*
|--------------------------------------------------------------------------
| Get Patient ID
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT patient_id, patient_number
     FROM patients
     WHERE user_id = ?"
);

$stmt->execute([$_SESSION['user_id']]);

$patient = $stmt->fetch();

$patientId = $patient['patient_id'] ?? null;
$patientNumber = $patient['patient_number'] ?? 'Not assigned';

/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

$appointmentCount = 0;
$pendingCount = 0;
$visitCount = 0;

if ($patientId) {

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM appointments
         WHERE patient_id = ?"
    );

    $stmt->execute([$patientId]);
    $appointmentCount = $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM appointments
         WHERE patient_id = ?
         AND status = 'pending'"
    );

    $stmt->execute([$patientId]);
    $pendingCount = $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM visit_records
         WHERE patient_id = ?"
    );

    $stmt->execute([$patientId]);
    $visitCount = $stmt->fetchColumn();
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

    <title>Patient Dashboard - The Tooth Lounge</title>

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

        .patient-number {
            display: inline-block;

            margin-top: 10px;

            padding: 7px 12px;

            background: rgba(255, 255, 255, 0.18);

            border: 1px solid rgba(255, 255, 255, 0.3);

            border-radius: 20px;

            font-size: 13px;
        }

        /* STATISTICS */

        .dashboard-grid {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

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
                repeat(2, 1fr);

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

        /* PROFILE ACTION */

        .profile-action {
            grid-column: span 2;
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

            .profile-action {
                grid-column: span 1;
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
<?php require __DIR__ . '/../includes/navigation.php'; ?>

    <!-- HEADER -->

    <!-- MAIN -->

    <main class="container">


        <!-- WELCOME -->

        <section class="welcome">

            <h1>
                Patient Dashboard
            </h1>

            <p>

                Welcome back,
                <strong>
                    <?= htmlspecialchars($patientName) ?>
                </strong>.

            </p>

            <span class="patient-number">

                <i class="fa-solid fa-id-card"></i>

                Patient No:
                <?= htmlspecialchars($patientNumber) ?>

            </span>

        </section>


        <!-- STATISTICS -->

        <section class="dashboard-grid">


            <!-- APPOINTMENTS -->

            <div class="card">

                <div class="card-icon">

                    <i class="fa-solid fa-calendar-check"></i>

                </div>

                <h3>
                    My Appointments
                </h3>

                <div class="card-number">

                    <?= htmlspecialchars($appointmentCount) ?>

                </div>

            </div>


            <!-- PENDING -->

            <div class="card">

                <div class="card-icon">

                    <i class="fa-solid fa-clock"></i>

                </div>

                <h3>
                    Pending Requests
                </h3>

                <div class="card-number">

                    <?= htmlspecialchars($pendingCount) ?>

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


        </section>


        <!-- QUICK ACTIONS -->

        <section class="panel">

            <h2>
                Patient Services
            </h2>


            <div class="quick-actions">


                <!-- PROFILE -->

                <a
                    href="profile.php"
                    class="action profile-action"
                >

                    <strong>

                        <i class="fa-solid fa-user"></i>

                        My Profile

                    </strong>

                    <span>
                        View and update your personal information.
                    </span>

                </a>


                <!-- APPOINTMENT REQUEST -->

                <a
                    href="request.php"
                    class="action"
                >

                    <strong>

                        <i class="fa-solid fa-calendar-plus"></i>

                        Request Appointment

                    </strong>

                    <span>
                        Submit a preferred date and time for your dental appointment.
                    </span>

                </a>


                <!-- APPOINTMENTS -->

                <a
                    href="appointments.php"
                    class="action"
                >

                    <strong>

                        <i class="fa-solid fa-calendar-days"></i>

                        My Appointments

                    </strong>

                    <span>
                        View your pending, confirmed, completed, and cancelled appointments.
                    </span>

                </a>


                <!-- VISIT HISTORY -->

                <a
                    href="visit_history.php"
                    class="action"
                >

                    <strong>

                        <i class="fa-solid fa-folder-open"></i>

                         Visit History

                    </strong>

                    <span>
                        View your previous completed dental visit records.
                    </span>

                </a>


            </div>

        </section>


    </main>

</body>

</html>
