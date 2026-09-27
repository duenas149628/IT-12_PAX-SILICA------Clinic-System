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

$patient_id = $_GET['id'] ?? null;

if (!$patient_id || !ctype_digit($patient_id)) {
    die('Invalid patient.');
}

$patient_id = (int) $patient_id;

/*
|--------------------------------------------------------------------------
| Get Patient Information
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT
        patients.*,
        users.email
     FROM patients
     INNER JOIN users
        ON patients.user_id = users.user_id
     WHERE patients.patient_id = ?"
);

$stmt->execute([$patient_id]);

$patient = $stmt->fetch();

if (!$patient) {
    die('Patient not found.');
}

/*
|--------------------------------------------------------------------------
| Get Patient Appointments
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT
        appointment_id,
        preferred_date,
        preferred_start_time,
        preferred_end_time,
        confirmed_date,
        confirmed_start_time,
        confirmed_end_time,
        reason,
        status
     FROM appointments
     WHERE patient_id = ?
     ORDER BY
        COALESCE(confirmed_date, preferred_date) DESC,
        appointment_id DESC"
);

$stmt->execute([$patient_id]);

$appointments = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Get Patient Visit Records
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT
        visit_id,
        appointment_id,
        visit_date,
        chief_complaint,
        findings,
        treatment,
        notes,
        follow_up
     FROM visit_records
     WHERE patient_id = ?
     ORDER BY visit_date DESC,
              visit_id DESC"
);

$stmt->execute([$patient_id]);

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
        Patient Record - The Tooth Lounge
    </title>

    <style>

        body {
            font-family: Arial, sans-serif;
            max-width: 1100px;
            margin: 40px auto;
            padding: 20px;
        }

        .section {
            border: 1px solid #ccc;
            padding: 20px;
            margin-top: 20px;
            border-radius: 8px;
        }

        .info-row {
            margin-top: 10px;
        }

        .info-row strong {
            display: inline-block;
            min-width: 160px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
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

        .visit {
            border: 1px solid #ddd;
            padding: 15px;
            margin-top: 15px;
            border-radius: 6px;
        }

        .field {
            margin-top: 12px;
        }

        .field strong {
            display: block;
            margin-bottom: 4px;
        }

        .button {
            display: inline-block;
            padding: 10px 16px;
            margin-top: 15px;
            background: #333;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .empty {
            padding: 15px;
            border: 1px solid #ddd;
            margin-top: 15px;
        }

        a {
            display: inline-block;
            margin-top: 15px;
        }

    </style>

</head>

<body>

    <h1>
        Patient Record
    </h1>


    <!-- PATIENT INFORMATION -->

    <div class="section">

        <h2>
            Patient Information
        </h2>

        <div class="info-row">

            <strong>
                Patient Number:
            </strong>

            <?= htmlspecialchars(
                $patient['patient_number']
            ) ?>

        </div>


        <div class="info-row">

            <strong>
                First Name:
            </strong>

            <?= htmlspecialchars(
                $patient['first_name']
            ) ?>

        </div>


        <div class="info-row">

            <strong>
                Last Name:
            </strong>

            <?= htmlspecialchars(
                $patient['last_name']
            ) ?>

        </div>


        <div class="info-row">

            <strong>
                Birth Date:
            </strong>

            <?= htmlspecialchars(
                $patient['birth_date']
            ) ?>

        </div>


        <div class="info-row">

            <strong>
                Sex:
            </strong>

            <?= htmlspecialchars(
                $patient['sex'] ?? ''
            ) ?>

        </div>


        <div class="info-row">

            <strong>
                Contact Number:
            </strong>

            <?= htmlspecialchars(
                $patient['contact_number']
            ) ?>

        </div>


        <div class="info-row">

            <strong>
                Address:
            </strong>

            <?= htmlspecialchars(
                $patient['address']
            ) ?>

        </div>


        <div class="info-row">

            <strong>
                Email:
            </strong>

            <?= htmlspecialchars(
                $patient['email']
            ) ?>

        </div>


        <a
            href="patient_edit.php?id=<?= $patient['patient_id'] ?>"
            class="button"
        >
            Edit Patient Information
        </a>

    </div>


    <!-- APPOINTMENTS -->

    <div class="section">

        <h2>
            Appointment History
        </h2>


        <?php if (empty($appointments)): ?>

            <div class="empty">

                No appointments found.

            </div>

        <?php else: ?>

            <table>

                <thead>

                    <tr>

                        <th>
                            Preferred Date
                        </th>

                        <th>
                            Preferred Time
                        </th>

                        <th>
                            Confirmed Date
                        </th>

                        <th>
                            Confirmed Time
                        </th>

                        <th>
                            Reason
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($appointments as $appointment): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars(
                                    $appointment['preferred_date']
                                ) ?>
                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    $appointment['preferred_start_time']
                                ) ?>

                                -

                                <?= htmlspecialchars(
                                    $appointment['preferred_end_time']
                                ) ?>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    $appointment['confirmed_date']
                                    ?? ''
                                ) ?>

                            </td>

                            <td>

                                <?php if (
                                    $appointment['confirmed_start_time']
                                ): ?>

                                    <?= htmlspecialchars(
                                        $appointment['confirmed_start_time']
                                    ) ?>

                                    -

                                    <?= htmlspecialchars(
                                        $appointment['confirmed_end_time']
                                    ) ?>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    $appointment['reason']
                                ) ?>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    $appointment['status']
                                ) ?>

                            </td>

                            <td>

                                <a
                                    href="appointment_view.php?id=<?= $appointment['appointment_id'] ?>"
                                >
                                    Manage
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>


    <!-- VISIT HISTORY -->

    <div class="section">

        <h2>
            Visit History
        </h2>


        <?php if (empty($visits)): ?>

            <div class="empty">

                No visit records found.

            </div>

        <?php else: ?>

            <?php foreach ($visits as $visit): ?>

                <div class="visit">

                    <h3>
                        Visit Date:
                        <?= htmlspecialchars(
                            $visit['visit_date']
                        ) ?>
                    </h3>


                    <div class="field">

                        <strong>
                            Chief Complaint
                        </strong>

                        <?= nl2br(
                            htmlspecialchars(
                                $visit['chief_complaint'] ?? ''
                            )
                        ) ?: 'No information recorded.' ?>

                    </div>


                    <div class="field">

                        <strong>
                            Findings
                        </strong>

                        <?= nl2br(
                            htmlspecialchars(
                                $visit['findings'] ?? ''
                            )
                        ) ?: 'No information recorded.' ?>

                    </div>


                    <div class="field">

                        <strong>
                            Treatment
                        </strong>

                        <?= nl2br(
                            htmlspecialchars(
                                $visit['treatment'] ?? ''
                            )
                        ) ?: 'No information recorded.' ?>

                    </div>


                    <div class="field">

                        <strong>
                            Notes
                        </strong>

                        <?= nl2br(
                            htmlspecialchars(
                                $visit['notes'] ?? ''
                            )
                        ) ?: 'No information recorded.' ?>

                    </div>


                    <div class="field">

                        <strong>
                            Follow-Up
                        </strong>

                        <?= nl2br(
                            htmlspecialchars(
                                $visit['follow_up'] ?? ''
                            )
                        ) ?: 'No information recorded.' ?>

                    </div>


                    <a
                        href="visit_view.php?id=<?= $visit['visit_id'] ?>"
                    >
                        View Full Visit Record
                    </a>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>


    <a href="patients.php">
        Back to Patient Records
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