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
| Get Visit Records
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $stmt = $pdo->prepare(
        "SELECT
            visit_records.visit_id,
            visit_records.visit_date,
            visit_records.chief_complaint,
            patients.patient_id,
            patients.patient_number,
            patients.first_name,
            patients.last_name
         FROM visit_records
         INNER JOIN patients
            ON visit_records.patient_id = patients.patient_id
         WHERE
            patients.patient_number LIKE ?
            OR patients.first_name LIKE ?
            OR patients.last_name LIKE ?
            OR visit_records.chief_complaint LIKE ?
            OR visit_records.treatment LIKE ?
         ORDER BY visit_records.visit_date DESC,
                  visit_records.visit_id DESC"
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
            visit_records.visit_id,
            visit_records.visit_date,
            visit_records.chief_complaint,
            patients.patient_id,
            patients.patient_number,
            patients.first_name,
            patients.last_name
         FROM visit_records
         INNER JOIN patients
            ON visit_records.patient_id = patients.patient_id
         ORDER BY visit_records.visit_date DESC,
                  visit_records.visit_id DESC"
    );
}

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

    <title>
        Visit Records - The Tooth Lounge
    </title>

    <style>

        body {
            font-family: Arial, sans-serif;
            max-width: 1100px;
            margin: 40px auto;
            padding: 20px;
        }

        .search-box {
            margin-top: 20px;
            margin-bottom: 25px;
        }

        .search-box input {
            width: 70%;
            padding: 10px;
            box-sizing: border-box;
        }

        .search-box button {
            padding: 10px 18px;
            cursor: pointer;
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
            background: #f5f5f5;
        }

        .empty {
            border: 1px solid #ccc;
            padding: 20px;
            margin-top: 20px;
        }

        a {
            display: inline-block;
            margin-top: 15px;
        }

    </style>

</head>

<body>

    <h1>
        Visit Records
    </h1>

    <p>
        Search and view completed patient visit records.
    </p>


    <!-- SEARCH -->

    <form
        method="GET"
        class="search-box"
    >

        <input
            type="text"
            name="search"
            placeholder="Search patient number, name, or chief complaint"
            value="<?= htmlspecialchars($search) ?>"
        >

        <button type="submit">
            Search
        </button>

    </form>


    <?php if ($search !== ''): ?>

        <p>

            Search results for:

            <strong>
                <?= htmlspecialchars($search) ?>
            </strong>

        </p>

        <a href="visit_records.php">
            Clear Search
        </a>

    <?php endif; ?>


    <!-- VISIT RECORD LIST -->

    <?php if (empty($visits)): ?>

        <div class="empty">

            <p>
                No visit records found.
            </p>

        </div>

    <?php else: ?>

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

                        <td>

                            <?= htmlspecialchars(
                                $visit['visit_date']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $visit['patient_number']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $visit['first_name']
                                . ' '
                                . $visit['last_name']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $visit['chief_complaint']
                                ?: 'Not recorded'
                            ) ?>

                        </td>


                        <td>

                            <a
                                href="visit_view.php?id=<?= $visit['visit_id'] ?>"
                            >
                                View Visit Record
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    <?php endif; ?>


    <br>

    <a href="patients.php">
        Patient Records
    </a>

    <br>

    <a href="appointments.php">
        Appointments
    </a>

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