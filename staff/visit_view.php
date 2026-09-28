<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../auth/login.php');
    exit;
}

$visit_id = $_GET['id'] ?? null;

if (!$visit_id || !ctype_digit($visit_id)) {
    die('Invalid visit record.');
}

$visit_id = (int) $visit_id;


/* =========================================================
   GET VISIT RECORD
========================================================= */

$stmt = $pdo->prepare(
    "SELECT
        visit_records.*,

        patients.patient_id,
        patients.patient_number,
        patients.first_name,
        patients.last_name,
        patients.contact_number,

        appointments.appointment_id,
        appointments.confirmed_date,
        appointments.confirmed_start_time,
        appointments.confirmed_end_time,
        appointments.status AS appointment_status

     FROM visit_records

     INNER JOIN patients
        ON visit_records.patient_id = patients.patient_id

     LEFT JOIN appointments
        ON visit_records.appointment_id = appointments.appointment_id

     WHERE visit_records.visit_id = ?"
);

$stmt->execute([$visit_id]);

$visit = $stmt->fetch();

if (!$visit) {
    die('Visit record not found.');
}


/* =========================================================
   HELPER FUNCTIONS
========================================================= */

function formatDate(?string $date): string
{
    if (!$date) {
        return 'Not recorded';
    }

    return date('M d, Y', strtotime($date));
}


function formatTime(?string $time): string
{
    if (!$time) {
        return 'Not recorded';
    }

    return date('g:i A', strtotime($time));
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
        View Visit Record - The Tooth Lounge
    </title>


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


        /* =========================================================
           BODY
        ========================================================= */

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


        /* =========================================================
           HEADER
        ========================================================= */

        .header {

            min-height: 75px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 12px 35px;

            background:
                rgba(255, 255, 255, 0.96);

            box-shadow:
                0 3px 15px rgba(0, 0, 0, 0.15);
        }


        /* =========================================================
           LOGO
        ========================================================= */

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


        /* =========================================================
           NAVIGATION
        ========================================================= */

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


        .nav a.active {

            background: #e6f7fa;

            color: #167b8c;

            font-weight: bold;
        }


        .logout {

            background: #167b8c !important;

            color: white !important;

            margin-left: 5px;
        }


        .logout:hover {

            background: #126c79 !important;
        }


        /* =========================================================
           MAIN
        ========================================================= */

        .container {

            width: 1100px;

            max-width: 92%;

            margin: 40px auto;
        }


        /* =========================================================
           PAGE TITLE
        ========================================================= */

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


        .record-number {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            margin-top: 12px;

            padding: 7px 13px;

            background:
                rgba(255, 255, 255, 0.18);

            border:
                1px solid
                rgba(255, 255, 255, 0.3);

            border-radius: 20px;

            font-size: 13px;
        }


        /* =========================================================
           CARD
        ========================================================= */

        .card {

            padding: 30px;

            margin-bottom: 20px;

            background:
                rgba(255, 255, 255, 0.96);

            border-radius: 15px;

            box-shadow:
                0 10px 30px rgba(0, 0, 0, 0.15);
        }


        /* =========================================================
           CARD HEADER
        ========================================================= */

        .card-header {

            display: flex;

            align-items: center;

            gap: 15px;

            margin-bottom: 22px;

            padding-bottom: 18px;

            border-bottom:
                1px solid #e3ebed;
        }


        .card-icon {

            width: 50px;

            height: 50px;

            display: flex;

            align-items: center;

            justify-content: center;

            flex-shrink: 0;

            background: #e6f7fa;

            color: #167b8c;

            border-radius: 12px;

            font-size: 21px;
        }


        .card-header h2 {

            color: #167b8c;

            font-size: 21px;
        }


        .card-header p {

            margin-top: 5px;

            color: #777;

            font-size: 13px;
        }


        /* =========================================================
           INFORMATION GRID
        ========================================================= */

        .info-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 15px;
        }


        .info-box {

            padding: 17px;

            background: #f8fbfc;

            border:
                1px solid #e0e8ea;

            border-radius: 10px;
        }


        .info-label {

            margin-bottom: 7px;

            color: #777;

            font-size: 12px;

            font-weight: 600;
        }


        .info-label i {

            margin-right: 5px;

            color: #167b8c;
        }


        .info-value {

            color: #444;

            font-size: 14px;

            line-height: 1.5;
        }


        .patient-number {

            display: inline-block;

            padding: 5px 9px;

            background: #e6f7fa;

            color: #167b8c;

            border-radius: 6px;

            font-size: 12px;

            font-weight: bold;
        }


        /* =========================================================
           VISIT DETAILS
        ========================================================= */

        .detail-box {

            margin-bottom: 15px;

            padding: 18px;

            background: #f8fbfc;

            border:
                1px solid #e0e8ea;

            border-radius: 10px;
        }


        .detail-box:last-child {

            margin-bottom: 0;
        }


        .detail-title {

            margin-bottom: 8px;

            color: #167b8c;

            font-size: 14px;

            font-weight: bold;
        }


        .detail-title i {

            width: 20px;

            margin-right: 5px;

            text-align: center;
        }


        .detail-content {

            color: #555;

            font-size: 14px;

            line-height: 1.6;

            white-space: pre-wrap;

            min-height: 20px;
        }


        .not-recorded {

            color: #999;

            font-style: italic;
        }


        /* =========================================================
           APPOINTMENT INFORMATION
        ========================================================= */

        .appointment-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 15px;
        }


        .appointment-box {

            padding: 17px;

            background: #f1fbfc;

            border:
                1px solid #d7eef1;

            border-radius: 10px;
        }


        .appointment-label {

            margin-bottom: 7px;

            color: #777;

            font-size: 12px;
        }


        .appointment-label i {

            margin-right: 5px;

            color: #167b8c;
        }


        .appointment-value {

            color: #167b8c;

            font-size: 14px;

            font-weight: bold;
        }


        /* =========================================================
           STATUS
        ========================================================= */

        .status {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding: 6px 11px;

            background: #e6f7fa;

            color: #167b8c;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;
        }


        /* =========================================================
           ACTIONS
        ========================================================= */

        .actions {

            display: flex;

            flex-wrap: wrap;

            gap: 10px;

            margin-top: 25px;
        }


        .button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 7px;

            padding: 10px 15px;

            border-radius: 8px;

            text-decoration: none;

            font-size: 13px;

            font-weight: 500;

            transition: 0.2s;
        }


        .button:hover {

            transform: translateY(-1px);
        }


        .primary-button {

            background: #167b8c;

            color: white;
        }


        .primary-button:hover {

            background: #126c79;
        }


        .secondary-button {

            background: #eef3f4;

            color: #555;
        }


        .secondary-button:hover {

            background: #e0eaec;
        }


        .edit-button {

            background: #287a53;

            color: white;
        }


        .edit-button:hover {

            background: #216542;
        }


        /* =========================================================
           BOTTOM NAVIGATION
        ========================================================= */

        .bottom-actions {

            display: flex;

            flex-wrap: wrap;

            gap: 10px;

            margin-top: 25px;
        }


        .bottom-actions a {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding: 10px 14px;

            background:
                rgba(255, 255, 255, 0.95);

            color: #555;

            border-radius: 8px;

            text-decoration: none;

            font-size: 13px;

            transition: 0.2s;
        }


        .bottom-actions a:hover {

            background: white;

            transform: translateY(-1px);
        }


        .bottom-actions a i {

            color: #167b8c;
        }


        /* =========================================================
           TABLET
        ========================================================= */

        @media (max-width: 950px) {

            .header {

                padding: 12px 20px;
            }


            .info-grid {

                grid-template-columns:
                    repeat(2, 1fr);
            }


            .appointment-grid {

                grid-template-columns:
                    repeat(2, 1fr);
            }

        }


        /* =========================================================
           MOBILE
        ========================================================= */

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


            .container {

                max-width: 94%;

                margin: 25px auto;
            }


            .welcome h1 {

                font-size: 26px;
            }


            .card {

                padding: 20px;
            }


            .info-grid,
            .appointment-grid {

                grid-template-columns: 1fr;
            }


            .actions {

                flex-direction: column;
            }


            .actions .button {

                width: 100%;
            }


            .bottom-actions {

                flex-direction: column;
            }


            .bottom-actions a {

                width: 100%;

                justify-content: center;
            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     HEADER
========================================================= -->

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


        <a
            href="visit_records.php"
            class="active"
        >

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


<!-- =========================================================
     MAIN
========================================================= -->

<main class="container">


    <!-- PAGE TITLE -->

    <section class="welcome">

        <h1>
            Visit Record
        </h1>

        <p>
            View the complete patient visit record and appointment information.
        </p>


        <span class="record-number">

            <i class="fa-solid fa-file-medical"></i>

            Visit Record #

            <?= htmlspecialchars(
                $visit['visit_id']
            ) ?>

        </span>

    </section>


    <!-- =====================================================
         PATIENT INFORMATION
    ====================================================== -->

    <section class="card">


        <div class="card-header">

            <div class="card-icon">

                <i class="fa-solid fa-user"></i>

            </div>


            <div>

                <h2>
                    Patient Information
                </h2>

                <p>
                    Patient details associated with this visit.
                </p>

            </div>

        </div>


        <div class="info-grid">


            <!-- PATIENT NAME -->

            <div class="info-box">

                <div class="info-label">

                    <i class="fa-solid fa-user"></i>

                    Patient Name

                </div>


                <div class="info-value">

                    <?= htmlspecialchars(
                        $visit['first_name']
                        . ' '
                        . $visit['last_name']
                    ) ?>

                </div>

            </div>


            <!-- PATIENT NUMBER -->

            <div class="info-box">

                <div class="info-label">

                    <i class="fa-solid fa-id-card"></i>

                    Patient Number

                </div>


                <div class="info-value">

                    <span class="patient-number">

                        <?= htmlspecialchars(
                            $visit['patient_number']
                        ) ?>

                    </span>

                </div>

            </div>


            <!-- CONTACT -->

            <div class="info-box">

                <div class="info-label">

                    <i class="fa-solid fa-phone"></i>

                    Contact Number

                </div>


                <div class="info-value">

                    <?= htmlspecialchars(
                        $visit['contact_number']
                        ?: 'Not provided'
                    ) ?>

                </div>

            </div>


            <!-- VISIT DATE -->

            <div class="info-box">

                <div class="info-label">

                    <i class="fa-solid fa-calendar-day"></i>

                    Visit Date

                </div>


                <div class="info-value">

                    <?= htmlspecialchars(
                        formatDate(
                            $visit['visit_date']
                        )
                    ) ?>

                </div>

            </div>


            <!-- RECORD ID -->

            <div class="info-box">

                <div class="info-label">

                    <i class="fa-solid fa-file-medical"></i>

                    Record ID

                </div>


                <div class="info-value">

                    #<?= htmlspecialchars(
                        $visit['visit_id']
                    ) ?>

                </div>

            </div>


            <!-- APPOINTMENT -->

            <div class="info-box">

                <div class="info-label">

                    <i class="fa-solid fa-calendar-check"></i>

                    Appointment

                </div>


                <div class="info-value">

                    <?php if ($visit['appointment_id']): ?>

                        Appointment #
                        <?= htmlspecialchars(
                            $visit['appointment_id']
                        ) ?>

                    <?php else: ?>

                        Not linked

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </section>


    <!-- =====================================================
         VISIT DETAILS
    ====================================================== -->

    <section class="card">


        <div class="card-header">

            <div class="card-icon">

                <i class="fa-solid fa-notes-medical"></i>

            </div>


            <div>

                <h2>
                    Visit Details
                </h2>

                <p>
                    Clinical information recorded during the patient's visit.
                </p>

            </div>

        </div>


        <!-- CHIEF COMPLAINT -->

        <div class="detail-box">

            <div class="detail-title">

                <i class="fa-solid fa-comment-medical"></i>

                Chief Complaint

            </div>


            <div class="detail-content">

                <?php if (!empty($visit['chief_complaint'])): ?>

                    <?= htmlspecialchars(
                        $visit['chief_complaint']
                    ) ?>

                <?php else: ?>

                    <span class="not-recorded">
                        No chief complaint recorded.
                    </span>

                <?php endif; ?>

            </div>

        </div>


        <!-- FINDINGS -->

        <div class="detail-box">

            <div class="detail-title">

                <i class="fa-solid fa-magnifying-glass"></i>

                Findings

            </div>


            <div class="detail-content">

                <?php if (!empty($visit['findings'])): ?>

                    <?= htmlspecialchars(
                        $visit['findings']
                    ) ?>

                <?php else: ?>

                    <span class="not-recorded">
                        No findings recorded.
                    </span>

                <?php endif; ?>

            </div>

        </div>


        <!-- TREATMENT -->

        <div class="detail-box">

            <div class="detail-title">

                <i class="fa-solid fa-tooth"></i>

                Treatment

            </div>


            <div class="detail-content">

                <?php if (!empty($visit['treatment'])): ?>

                    <?= htmlspecialchars(
                        $visit['treatment']
                    ) ?>

                <?php else: ?>

                    <span class="not-recorded">
                        No treatment recorded.
                    </span>

                <?php endif; ?>

            </div>

        </div>


        <!-- NOTES -->

        <div class="detail-box">

            <div class="detail-title">

                <i class="fa-solid fa-note-sticky"></i>

                Notes

            </div>


            <div class="detail-content">

                <?php if (!empty($visit['notes'])): ?>

                    <?= htmlspecialchars(
                        $visit['notes']
                    ) ?>

                <?php else: ?>

                    <span class="not-recorded">
                        No additional notes recorded.
                    </span>

                <?php endif; ?>

            </div>

        </div>


        <!-- FOLLOW UP -->

        <div class="detail-box">

            <div class="detail-title">

                <i class="fa-solid fa-calendar-plus"></i>

                Follow-Up

            </div>


            <div class="detail-content">

                <?php if (!empty($visit['follow_up'])): ?>

                    <?= htmlspecialchars(
                        $visit['follow_up']
                    ) ?>

                <?php else: ?>

                    <span class="not-recorded">
                        No follow-up instructions recorded.
                    </span>

                <?php endif; ?>

            </div>

        </div>

    </section>


    <!-- =====================================================
         APPOINTMENT INFORMATION
    ====================================================== -->

    <?php if ($visit['appointment_id']): ?>

        <section class="card">


            <div class="card-header">

                <div class="card-icon">

                    <i class="fa-solid fa-calendar-check"></i>

                </div>


                <div>

                    <h2>
                        Appointment Information
                    </h2>

                    <p>
                        Appointment schedule associated with this visit.
                    </p>

                </div>

            </div>


            <div class="appointment-grid">


                <!-- DATE -->

                <div class="appointment-box">

                    <div class="appointment-label">

                        <i class="fa-solid fa-calendar"></i>

                        Appointment Date

                    </div>


                    <div class="appointment-value">

                        <?= htmlspecialchars(
                            formatDate(
                                $visit['confirmed_date']
                            )
                        ) ?>

                    </div>

                </div>


                <!-- TIME -->

                <div class="appointment-box">

                    <div class="appointment-label">

                        <i class="fa-solid fa-clock"></i>

                        Appointment Time

                    </div>


                    <div class="appointment-value">

                        <?= htmlspecialchars(
                            formatTime(
                                $visit['confirmed_start_time']
                            )
                        ) ?>

                        -

                        <?= htmlspecialchars(
                            formatTime(
                                $visit['confirmed_end_time']
                            )
                        ) ?>

                    </div>

                </div>


                <!-- STATUS -->

                <div class="appointment-box">

                    <div class="appointment-label">

                        <i class="fa-solid fa-circle-check"></i>

                        Appointment Status

                    </div>


                    <div>

                        <span class="status">

                            <i class="fa-solid fa-circle"></i>

                            <?= htmlspecialchars(
                                ucfirst(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $visit['appointment_status']
                                    )
                                )
                            ) ?>

                        </span>

                    </div>

                </div>

            </div>

        </section>

    <?php endif; ?>


    <!-- =====================================================
         ACTIONS
    ====================================================== -->

    <div class="actions">


        <a
            href="visit_edit.php?id=<?= htmlspecialchars($visit['visit_id']) ?>"
            class="button edit-button"
        >

            <i class="fa-solid fa-pen-to-square"></i>

            Edit Visit Record

        </a>


        <a
            href="visit_records.php"
            class="button primary-button"
        >

            <i class="fa-solid fa-arrow-left"></i>

            Back to Visit Records

        </a>

    </div>


    <!-- =====================================================
         BOTTOM ACTIONS
    ====================================================== -->

    <div class="bottom-actions">


        <a href="dashboard.php">

            <i class="fa-solid fa-gauge-high"></i>

            Staff Dashboard

        </a>


        <a href="patients.php">

            <i class="fa-solid fa-users"></i>

            Patients

        </a>


        <a href="appointments.php">

            <i class="fa-solid fa-calendar-check"></i>

            Appointments

        </a>


        <a href="../auth/logout.php">

            <i class="fa-solid fa-right-from-bracket"></i>

            Logout

        </a>

    </div>


</main>


</body>

</html>