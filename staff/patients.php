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

$search = trim($_GET['search'] ?? '');

/*
|--------------------------------------------------------------------------
| Get Patients
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $stmt = $pdo->prepare(
        "SELECT
            patients.patient_id,
            patients.patient_number,
            patients.first_name,
            patients.last_name,
            patients.birth_date,
            patients.sex,
            patients.contact_number,
            patients.address,
            users.email
         FROM patients
         INNER JOIN users
            ON patients.user_id = users.user_id
         WHERE
            patients.patient_number LIKE ?
            OR patients.first_name LIKE ?
            OR patients.last_name LIKE ?
            OR patients.contact_number LIKE ?
            OR users.email LIKE ?
         ORDER BY
            patients.last_name ASC,
            patients.first_name ASC"
    );

    $searchTerm = '%' . $search . '%';

    $stmt->execute([
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm
    ]);

} else {

    $stmt = $pdo->query(
        "SELECT
            patients.patient_id,
            patients.patient_number,
            patients.first_name,
            patients.last_name,
            patients.birth_date,
            patients.sex,
            patients.contact_number,
            patients.address,
            users.email
         FROM patients
         INNER JOIN users
            ON patients.user_id = users.user_id
         ORDER BY
            patients.last_name ASC,
            patients.first_name ASC"
    );
}

$patients = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Patient Records - The Tooth Lounge</title>


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


        /* ==============================
           HEADER
        =============================== */

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


        /* ==============================
           LOGO
        =============================== */

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


        /* ==============================
           NAVIGATION
        =============================== */

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


        /* ==============================
           MAIN
        =============================== */

        .container {

            width: 1200px;

            max-width: 94%;

            margin: 40px auto;
        }


        /* ==============================
           PAGE TITLE
        =============================== */

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


        /* ==============================
           SEARCH CARD
        =============================== */

        .search-card {

            padding: 20px 25px;

            margin-bottom: 20px;

            background:
                rgba(255, 255, 255, 0.97);

            border-radius: 14px;

            box-shadow:
                0 8px 25px rgba(0, 0, 0, 0.14);
        }


        .search-form {

            display: flex;

            align-items: center;

            gap: 10px;
        }


        .search-input-wrapper {

            position: relative;

            flex: 1;
        }


        .search-input-wrapper i {

            position: absolute;

            left: 14px;

            top: 50%;

            transform: translateY(-50%);

            color: #167b8c;
        }


        .search-input {

            width: 100%;

            padding: 12px 15px 12px 42px;

            border: 1px solid #d5dfe2;

            border-radius: 8px;

            outline: none;

            font-size: 14px;
        }


        .search-input:focus {

            border-color: #167b8c;

            box-shadow:
                0 0 0 3px
                rgba(22, 123, 140, 0.1);
        }


        .search-button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 7px;

            padding: 12px 18px;

            border: none;

            border-radius: 8px;

            background: #167b8c;

            color: white;

            font-size: 14px;

            cursor: pointer;
        }


        .search-button:hover {

            background: #126c79;
        }


        .clear-button {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding: 12px 15px;

            background: #f2f2f2;

            color: #555;

            text-decoration: none;

            border-radius: 8px;

            font-size: 14px;
        }


        .clear-button:hover {

            background: #e5e5e5;
        }


        /* ==============================
           SEARCH RESULT
        =============================== */

        .search-result {

            margin-top: 15px;

            color: #666;

            font-size: 14px;
        }


        .search-result strong {

            color: #167b8c;
        }


        /* ==============================
           SUMMARY
        =============================== */

        .summary {

            display: flex;

            align-items: center;

            gap: 12px;

            margin-bottom: 15px;

            color: white;

            font-size: 14px;
        }


        .summary-icon {

            width: 35px;

            height: 35px;

            display: flex;

            align-items: center;

            justify-content: center;

            background:
                rgba(255, 255, 255, 0.2);

            border-radius: 50%;
        }


        .count {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            min-width: 32px;

            height: 30px;

            padding: 0 10px;

            background: white;

            color: #167b8c;

            border-radius: 20px;

            font-weight: bold;
        }


        /* ==============================
           TABLE
        =============================== */

        .table-container {

            background:
                rgba(255, 255, 255, 0.97);

            border-radius: 15px;

            overflow-x: auto;

            box-shadow:
                0 10px 30px rgba(0, 0, 0, 0.15);
        }


        table {

            width: 100%;

            min-width: 900px;

            border-collapse: collapse;
        }


        th {

            padding: 16px;

            background: #e6f7fa;

            color: #167b8c;

            text-align: left;

            font-size: 13px;

            font-weight: 600;

            border-bottom:
                1px solid #d6edef;
        }


        td {

            padding: 15px 16px;

            border-bottom:
                1px solid #eeeeee;

            color: #555;

            font-size: 14px;

            vertical-align: middle;
        }


        tbody tr:hover {

            background: #f7fcfd;
        }


        tbody tr:last-child td {

            border-bottom: none;
        }


        /* ==============================
           PATIENT NAME
        =============================== */

        .patient-name {

            display: flex;

            align-items: center;

            gap: 10px;

            color: #333;

            font-weight: 600;
        }


        .patient-icon {

            width: 38px;

            height: 38px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #e6f7fa;

            color: #167b8c;

            border-radius: 50%;
        }


        /* ==============================
           PATIENT NUMBER
        =============================== */

        .patient-number {

            color: #167b8c;

            font-weight: 600;
        }


        /* ==============================
           VIEW BUTTON
        =============================== */

        .view-button {

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


        .view-button:hover {

            background: #126c79;

            transform: translateY(-1px);
        }


        /* ==============================
           EMPTY STATE
        =============================== */

        .empty {

            padding: 60px 30px;

            text-align: center;

            background:
                rgba(255, 255, 255, 0.97);

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


        /* ==============================
           BOTTOM LINKS
        =============================== */

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

            background:
                rgba(255, 255, 255, 0.95);

            color: #555;

            text-decoration: none;

            border-radius: 8px;

            font-size: 13px;

            box-shadow:
                0 3px 10px rgba(0, 0, 0, 0.08);
        }


        .bottom-navigation a:hover {

            color: #167b8c;

            background: white;
        }


        /* ==============================
           RESPONSIVE
        =============================== */

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


            .search-form {

                flex-wrap: wrap;
            }


            .search-input-wrapper {

                width: 100%;

                flex-basis: 100%;
            }

        }


        @media (max-width: 600px) {

            .page-title h1 {

                font-size: 25px;
            }


            .search-button,
            .clear-button {

                flex: 1;

                justify-content: center;
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


    <!-- ==============================
         HEADER
    =============================== -->

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


            <a
                href="patients.php"
                class="active"
            >

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


    <!-- ==============================
         MAIN
    =============================== -->

    <main class="container">


        <section class="page-title">

            <h1>

                <i class="fa-solid fa-users"></i>

                Patient Records

            </h1>

            <p>
                Search and view registered patient records.
            </p>

        </section>


        <!-- SEARCH -->

        <div class="search-card">


            <form
                method="GET"
                class="search-form"
            >


                <div class="search-input-wrapper">

                    <i class="fa-solid fa-magnifying-glass"></i>

                    <input
                        type="text"
                        name="search"
                        class="search-input"
                        placeholder="Search patient number, name, contact, or email"
                        value="<?= htmlspecialchars($search) ?>"
                    >

                </div>


                <button
                    type="submit"
                    class="search-button"
                >

                    <i class="fa-solid fa-magnifying-glass"></i>

                    Search

                </button>


                <?php if ($search !== ''): ?>

                    <a
                        href="patients.php"
                        class="clear-button"
                    >

                        <i class="fa-solid fa-xmark"></i>

                        Clear

                    </a>

                <?php endif; ?>


            </form>


            <?php if ($search !== ''): ?>

                <div class="search-result">

                    Search results for:

                    <strong>
                        <?= htmlspecialchars($search) ?>
                    </strong>

                </div>

            <?php endif; ?>


        </div>


        <!-- PATIENTS -->

        <?php if (empty($patients)): ?>


            <div class="empty">

                <div class="empty-icon">

                    <i class="fa-solid fa-user-slash"></i>

                </div>


                <h2>
                    No Patient Records Found
                </h2>


                <p>
                    No patient records matched your search.
                </p>

            </div>


        <?php else: ?>


            <!-- SUMMARY -->

            <div class="summary">

                <div class="summary-icon">

                    <i class="fa-solid fa-users"></i>

                </div>


                <span>
                    Registered patients
                </span>


                <span class="count">

                    <?= count($patients) ?>

                </span>

            </div>


            <!-- TABLE -->

            <div class="table-container">

                <table>


                    <thead>

                        <tr>

                            <th>
                                Patient Number
                            </th>

                            <th>
                                Name
                            </th>

                            <th>
                                Birth Date
                            </th>

                            <th>
                                Sex
                            </th>

                            <th>
                                Contact Number
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php foreach ($patients as $patient): ?>


                            <tr>


                                <td>

                                    <span class="patient-number">

                                        <?= htmlspecialchars(
                                            $patient['patient_number']
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <div class="patient-name">

                                        <div class="patient-icon">

                                            <i class="fa-solid fa-user"></i>

                                        </div>


                                        <?= htmlspecialchars(
                                            $patient['first_name']
                                            . ' '
                                            . $patient['last_name']
                                        ) ?>

                                    </div>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $patient['birth_date'] ?? ''
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $patient['sex'] ?? ''
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $patient['contact_number'] ?? ''
                                    ) ?>

                                </td>


                                <td>

                                    <a
                                        href="patient_view.php?id=<?= $patient['patient_id'] ?>"
                                        class="view-button"
                                    >

                                        <i class="fa-solid fa-eye"></i>

                                        View

                                    </a>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    </tbody>

                </table>

            </div>


        <?php endif; ?>


        <!-- BOTTOM NAVIGATION -->

        <div class="bottom-navigation">


            <a href="dashboard.php">

                <i class="fa-solid fa-gauge-high"></i>

                Staff Dashboard

            </a>


            <a href="appointments.php">

                <i class="fa-solid fa-calendar-check"></i>

                Appointments

            </a>


            <a href="visit_records.php">

                <i class="fa-solid fa-file-medical"></i>

                Visit Records

            </a>


        </div>


    </main>


</body>

</html>