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

    <style>

        body {
            font-family: Arial, sans-serif;
            max-width: 1100px;
            margin: 40px auto;
            padding: 20px;
        }

        h1 {
            margin-bottom: 10px;
        }

        .search-box {
            margin-top: 25px;
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

        .view-button {
            display: inline-block;
            padding: 7px 12px;
            background: #333;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }

        .view-button:hover {
            background: #555;
        }

        .clear-link {
            margin-left: 10px;
        }

        .empty {
            border: 1px solid #ccc;
            padding: 20px;
            margin-top: 20px;
        }

        .bottom-links {
            margin-top: 30px;
        }

        .bottom-links a {
            display: block;
            margin-top: 10px;
        }

    </style>

</head>

<body>

    <h1>
        Patient Records
    </h1>

    <p>
        Search and view registered patient records.
    </p>


    <!-- SEARCH -->

    <form
        method="GET"
        class="search-box"
    >

        <input
            type="text"
            name="search"
            placeholder="Search patient number, name, contact, or email"
            value="<?= htmlspecialchars($search) ?>"
        >

        <button type="submit">
            Search
        </button>

        <?php if ($search !== ''): ?>

            <a
                href="patients.php"
                class="clear-link"
            >
                Clear Search
            </a>

        <?php endif; ?>

    </form>


    <?php if ($search !== ''): ?>

        <p>

            Search results for:

            <strong>
                <?= htmlspecialchars($search) ?>
            </strong>

        </p>

    <?php endif; ?>


    <!-- PATIENT LIST -->

    <?php if (empty($patients)): ?>

        <div class="empty">

            <p>
                No patient records found.
            </p>

        </div>

    <?php else: ?>

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

                            <?= htmlspecialchars(
                                $patient['patient_number']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $patient['first_name']
                                . ' '
                                . $patient['last_name']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $patient['birth_date']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $patient['sex'] ?? ''
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $patient['contact_number']
                            ) ?>

                        </td>


                        <td>

                            <a
                                href="patient_view.php?id=<?= $patient['patient_id'] ?>"
                                class="view-button"
                            >
                                View
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    <?php endif; ?>


    <!-- NAVIGATION -->

    <div class="bottom-links">

        <a href="dashboard.php">
            Back to Staff Dashboard
        </a>

        <a href="appointments.php">
            Appointments
        </a>

        <a href="../auth/logout.php">
            Logout
        </a>

    </div>

</body>

</html>