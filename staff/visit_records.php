<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../auth/login.php');
    exit;
}

$search = request_string($_GET, 'search');
$visitDate = request_string($_GET, 'visit_date');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $visitDate) || !checkdate((int) substr($visitDate, 5, 2), (int) substr($visitDate, 8, 2), (int) substr($visitDate, 0, 4))) {
    $visitDate = '';
}

/*
|--------------------------------------------------------------------------
| Get Visit Records
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(patients.patient_number LIKE ? OR patients.first_name LIKE ? OR patients.last_name LIKE ? OR visit_records.chief_complaint LIKE ? OR visit_records.treatment LIKE ?)';
    $searchTerm = '%' . $search . '%';
    array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
}
if ($visitDate !== '') {
    $where[] = 'visit_records.visit_date = ?';
    $params[] = $visitDate;
}

$sql = "SELECT
            visit_records.visit_id,
            visit_records.visit_date,
            visit_records.chief_complaint,
            patients.patient_id,
            patients.patient_number,
            patients.first_name,
            patients.last_name
         FROM visit_records
         INNER JOIN patients ON visit_records.patient_id = patients.patient_id";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY visit_records.visit_date DESC, visit_records.visit_id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

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

    <title>Visit Records - The Tooth Lounge</title>

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


        /* =========================================================
           HEADER
        ========================================================= */

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
           WELCOME
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


        /* =========================================================
           SEARCH CARD
        ========================================================= */

        .search-card {

            padding: 25px;

            margin-bottom: 25px;

            background: rgba(255, 255, 255, 0.96);

            border-radius: 15px;

            box-shadow:
                0 10px 30px rgba(0, 0, 0, 0.15);
        }


        .search-title {

            display: flex;

            align-items: center;

            gap: 12px;

            margin-bottom: 18px;
        }


        .search-icon {

            width: 45px;

            height: 45px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #e6f7fa;

            color: #167b8c;

            border-radius: 10px;

            font-size: 19px;
        }


        .search-title h2 {

            color: #167b8c;

            font-size: 20px;
        }


        .search-title p {

            margin-top: 4px;

            color: #777;

            font-size: 13px;
        }


        .search-form {

            display: flex;

            align-items: center;

            flex-wrap: wrap;

            gap: 10px;
        }


        .search-input {

            flex: 1;

            padding: 12px 14px;

            border: 1px solid #d5d5d5;

            border-radius: 8px;

            background: white;

            color: #333;

            font-family: Arial, sans-serif;

            font-size: 14px;

            outline: none;
        }

        .date-input {
            min-width: 175px;
            padding: 12px 14px;
            border: 1px solid #d5d5d5;
            border-radius: 8px;
            background: white;
            color: #333;
            font: 14px Arial, sans-serif;
        }

        .date-input:focus {
            border-color: #167b8c;
            outline: none;
            box-shadow: 0 0 0 3px rgba(22, 123, 140, 0.10);
        }

        .visually-hidden {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }


        .search-input:focus {

            border-color: #167b8c;

            box-shadow:
                0 0 0 3px
                rgba(22, 123, 140, 0.10);
        }


        .search-button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 7px;

            padding: 10px 18px;

            border: none;

            border-radius: 8px;

            background: #167b8c;

            color: white;

            font-family: Arial, sans-serif;

            font-size: 13px;

            font-weight: 500;

            cursor: pointer;
        }


        .search-button:hover {

            background: #126c79;
        }


        .clear-button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 7px;

            padding: 10px 16px;

            background: #eef3f4;

            color: #555;

            border-radius: 8px;

            text-decoration: none;

            font-size: 13px;
        }


        .clear-button:hover {

            background: #e1eaec;

            color: #167b8c;
        }


        .search-result {

            margin-top: 15px;

            padding: 10px 13px;

            background: #f1fbfc;

            border-radius: 8px;

            color: #777;

            font-size: 13px;
        }


        .search-result strong {

            color: #167b8c;
        }


        /* =========================================================
           RECORDS PANEL
        ========================================================= */

        .panel {

            padding: 24px;

            background: rgba(255, 255, 255, 0.96);

            border-radius: 15px;

            box-shadow:
                0 10px 30px rgba(0, 0, 0, 0.15);

            overflow: hidden;
        }


        .panel-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 20px;
        }


        .panel-title {

            display: flex;

            align-items: center;

            gap: 12px;
        }


        .panel-icon {

            width: 45px;

            height: 45px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #e6f7fa;

            color: #167b8c;

            border-radius: 10px;

            font-size: 19px;
        }


        .panel-title h2 {

            color: #167b8c;

            font-size: 21px;
        }


        .panel-title p {

            margin-top: 4px;

            color: #777;

            font-size: 13px;
        }


        .records-count {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding: 7px 12px;

            background: #e6f7fa;

            color: #167b8c;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;
        }


        /* =========================================================
           TABLE
        ========================================================= */

        .table-wrapper {

            width: 100%;

            overflow-x: auto;
        }


        table {

            width: 100%;

            min-width: 800px;

            border-collapse: collapse;
        }


        th {

            padding: 14px 16px;

            background: #f4f9fa;

            color: #777;

            border-bottom: 1px solid #dfeaec;

            font-size: 12px;

            font-weight: 600;

            text-align: left;

            text-transform: uppercase;

            letter-spacing: 0.4px;
        }


        td {

            padding: 16px;

            color: #444;

            border-bottom: 1px solid #edf2f3;

            font-size: 14px;

            vertical-align: middle;
        }


        tbody tr {

            transition: 0.2s;
        }


        tbody tr:hover {

            background: #f8fcfc;
        }


        .visit-date {

            white-space: nowrap;

            color: #555;

            font-weight: 500;
        }


        .visit-date i {

            margin-right: 6px;

            color: #167b8c;
        }


        .patient-number {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            padding: 5px 9px;

            background: #edf7f8;

            color: #31818e;

            border-radius: 6px;

            font-size: 12px;

            font-weight: bold;
        }


        .patient-name {

            color: #444;

            font-weight: 600;
        }


        .patient-name i {

            margin-right: 6px;

            color: #167b8c;
        }


        .complaint {

            max-width: 270px;

            color: #667a80;

            line-height: 1.4;
        }


        .view-button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 6px;

            padding: 8px 13px;

            background: #167b8c;

            color: white;

            border-radius: 8px;

            text-decoration: none;

            font-size: 12px;

            font-weight: 500;

            transition: 0.2s;
        }


        .view-button:hover {

            background: #126c79;

            transform: translateY(-1px);
        }


        /* =========================================================
           EMPTY STATE
        ========================================================= */

        .empty {

            padding: 60px 25px;

            text-align: center;

            color: #777;
        }


        .empty-icon {

            width: 65px;

            height: 65px;

            display: flex;

            align-items: center;

            justify-content: center;

            margin: 0 auto 15px;

            background: #e6f7fa;

            color: #167b8c;

            border-radius: 50%;

            font-size: 27px;
        }


        .empty h3 {

            margin-bottom: 7px;

            color: #555;

            font-size: 18px;
        }


        .empty p {

            color: #777;

            font-size: 14px;
        }


        /* =========================================================
           BOTTOM ACTIONS
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

            padding: 9px 14px;

            background: rgba(255, 255, 255, 0.95);

            color: #555;

            border-radius: 8px;

            text-decoration: none;

            font-size: 13px;

            transition: 0.2s;
        }


        .bottom-actions a:hover {

            background: white;

            color: #167b8c;

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


            .search-form {

                flex-wrap: wrap;
            }


            .search-input {

                flex-basis: 100%;
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


            .search-card,
            .panel {

                padding: 20px;
            }


            .search-form {

                flex-direction: column;
            }


            .search-input {

                width: 100%;
            }


            .search-button,
            .clear-button {

                width: 100%;

                min-height: 40px;
            }


            .panel-header {

                align-items: flex-start;

                flex-direction: column;

                gap: 15px;
            }


            .records-count {

                align-self: flex-start;
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
<?php require __DIR__ . '/../includes/navigation.php'; ?>


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


<!-- =========================================================
     MAIN
========================================================= -->

<main class="container">


    <!-- WELCOME -->

    <section class="welcome">

        <h1>
            Visit Records
        </h1>

        <p>
            Search and manage completed patient visit records.
        </p>

    </section>


    <!-- =====================================================
         SEARCH
    ====================================================== -->

    <section class="search-card">


        <div class="search-title">

            <div class="search-icon">

                <i class="fa-solid fa-magnifying-glass"></i>

            </div>


            <div>

                <h2>
                    Search Visit Records
                </h2>

                <p>
                    Search by patient number, patient name, or chief complaint.
                </p>

            </div>

        </div>


        <form
            method="GET"
            class="search-form"
        >


            <input
                type="text"
                name="search"
                class="search-input"
                placeholder="Search patient number, name, or chief complaint..."
                value="<?= htmlspecialchars($search) ?>"
            >


            <button
                type="submit"
                class="search-button"
            >

                <i class="fa-solid fa-magnifying-glass"></i>

                Search

            </button>


            <label class="visually-hidden" for="visit-date">Filter by visit date</label>
            <input
                type="date"
                id="visit-date"
                name="visit_date"
                class="date-input"
                value="<?= h($visitDate) ?>"
                aria-label="Filter by visit date"
            >

            <?php if ($search !== '' || $visitDate !== ''): ?>

                <a
                    href="visit_records.php"
                    class="clear-button"
                >

                    <i class="fa-solid fa-xmark"></i>

                    Clear

                </a>

            <?php endif; ?>


        </form>


        <?php if ($search !== '' || $visitDate !== ''): ?>

            <div class="search-result">

                <i class="fa-solid fa-filter"></i>

                Showing records
                <?php if ($search !== ''): ?>
                    matching <strong><?= h($search) ?></strong>
                <?php endif; ?>
                <?php if ($visitDate !== ''): ?>
                    from <strong><?= h(date('M d, Y', strtotime($visitDate))) ?></strong>
                <?php endif; ?>

            </div>

        <?php endif; ?>


    </section>


    <!-- =====================================================
         VISIT RECORDS
    ====================================================== -->

    <section class="panel">


        <div class="panel-header">


            <div class="panel-title">


                <div class="panel-icon">

                    <i class="fa-solid fa-file-medical"></i>

                </div>


                <div>

                    <h2>
                        Patient Visit History
                    </h2>

                    <p>
                        View completed patient visit records.
                    </p>

                </div>


            </div>


            <span class="records-count">

                <i class="fa-solid fa-file-medical"></i>

                <?= count($visits) ?>

                Record<?= count($visits) !== 1 ? 's' : '' ?>

            </span>


        </div>


        <?php if (empty($visits)): ?>


            <!-- EMPTY -->

            <div class="empty">


                <div class="empty-icon">

                    <i class="fa-solid fa-file-circle-xmark"></i>

                </div>


                <h3>
                    No Visit Records Found
                </h3>


                <p>
                    There are currently no visit records matching your search.
                </p>


            </div>


        <?php else: ?>


            <!-- TABLE -->

            <div class="table-wrapper">


                <table>


                    <thead>

                        <tr>

                            <th>
                                Visit Date
                            </th>

                            <th>
                                Patient Number
                            </th>

                            <th>
                                Patient Name
                            </th>

                            <th>
                                Chief Complaint
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php foreach ($visits as $visit): ?>


                            <tr>


                                <!-- DATE -->

                                <td>

                                    <div class="visit-date">

                                        <i class="fa-solid fa-calendar-day"></i>

                                        <?= htmlspecialchars(
                                            date(
                                                'M d, Y',
                                                strtotime(
                                                    $visit['visit_date']
                                                )
                                            )
                                        ) ?>

                                    </div>

                                </td>


                                <!-- PATIENT NUMBER -->

                                <td>

                                    <span class="patient-number">

                                        <i class="fa-solid fa-id-card"></i>

                                        <?= htmlspecialchars(
                                            $visit['patient_number']
                                        ) ?>

                                    </span>

                                </td>


                                <!-- PATIENT NAME -->

                                <td>

                                    <div class="patient-name">

                                        <i class="fa-solid fa-user"></i>

                                        <?= htmlspecialchars(
                                            $visit['first_name']
                                            . ' '
                                            . $visit['last_name']
                                        ) ?>

                                    </div>

                                </td>


                                <!-- CHIEF COMPLAINT -->

                                <td>

                                    <div class="complaint">

                                        <?= htmlspecialchars(
                                            $visit['chief_complaint']
                                            ?: 'Not recorded'
                                        ) ?>

                                    </div>

                                </td>


                                <!-- ACTION -->

                                <td>

                                    <a
                                        href="visit_view.php?id=<?= htmlspecialchars($visit['visit_id']) ?>"
                                        class="view-button"
                                    >

                                        <i class="fa-solid fa-eye"></i>

                                        View Record

                                    </a>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    </tbody>


                </table>


            </div>


        <?php endif; ?>


    </section>


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

            Patient Records

        </a>


        <a href="appointments.php">

            <i class="fa-solid fa-calendar-check"></i>

            Appointments

        </a>


    </div>


</main>


</body>

</html>
