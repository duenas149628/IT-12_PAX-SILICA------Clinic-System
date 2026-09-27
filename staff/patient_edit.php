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
| Get Patient
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

$message = '';
$message_type = '';

/*
|--------------------------------------------------------------------------
| Update Patient
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $birth_date = $_POST['birth_date'] ?? '';
    $sex = trim($_POST['sex'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($first_name === '' || $last_name === '') {

        $message = 'First name and last name are required.';
        $message_type = 'error';

    } else {

        try {

            $pdo->beginTransaction();

            /*
             * Update patient information.
             */
            $stmt = $pdo->prepare(
                "UPDATE patients
                 SET
                    first_name = ?,
                    last_name = ?,
                    birth_date = ?,
                    sex = ?,
                    contact_number = ?,
                    address = ?
                 WHERE patient_id = ?"
            );

            $stmt->execute([
                $first_name,
                $last_name,
                $birth_date !== '' ? $birth_date : null,
                $sex !== '' ? $sex : null,
                $contact_number,
                $address,
                $patient_id
            ]);

            /*
             * Update the name stored in the users table.
             */
            $stmt = $pdo->prepare(
                "UPDATE users
                 SET name = ?
                 WHERE user_id = ?"
            );

            $stmt->execute([
                $first_name . ' ' . $last_name,
                $patient['user_id']
            ]);

            $pdo->commit();

            header(
                'Location: patient_view.php?id='
                . $patient_id
            );

            exit;

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message = 'Unable to update patient information.';
            $message_type = 'error';
        }
    }
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
        Edit Patient - The Tooth Lounge
    </title>

    <style>

        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
        }

        .section {
            border: 1px solid #ccc;
            padding: 20px;
            margin-top: 20px;
            border-radius: 8px;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            box-sizing: border-box;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        button {
            width: 100%;
            padding: 12px;
            margin-top: 20px;
            background: #333;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .message {
            padding: 12px;
            margin-top: 15px;
            border-radius: 5px;
        }

        .error {
            background: #f2dede;
        }

        .readonly {
            background: #f5f5f5;
        }

        a {
            display: inline-block;
            margin-top: 15px;
        }

    </style>

</head>

<body>

    <h1>
        Edit Patient Information
    </h1>


    <div class="section">

        <p>
            <strong>
                Patient Number:
            </strong>

            <?= htmlspecialchars(
                $patient['patient_number']
            ) ?>
        </p>


        <?php if ($message): ?>

            <div class="message <?= htmlspecialchars($message_type) ?>">

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>


        <form method="POST">

            <label for="first_name">
                First Name
            </label>

            <input
                type="text"
                id="first_name"
                name="first_name"
                value="<?= htmlspecialchars(
                    $patient['first_name']
                ) ?>"
                required
            >


            <label for="last_name">
                Last Name
            </label>

            <input
                type="text"
                id="last_name"
                name="last_name"
                value="<?= htmlspecialchars(
                    $patient['last_name']
                ) ?>"
                required
            >


            <label for="birth_date">
                Birth Date
            </label>

            <input
                type="date"
                id="birth_date"
                name="birth_date"
                value="<?= htmlspecialchars(
                    $patient['birth_date'] ?? ''
                ) ?>"
            >


            <label for="sex">
                Sex
            </label>

            <select
                id="sex"
                name="sex"
            >

                <option value="">
                    Select
                </option>

                <option
                    value="Male"
                    <?= $patient['sex'] === 'Male'
                        ? 'selected'
                        : '' ?>
                >
                    Male
                </option>

                <option
                    value="Female"
                    <?= $patient['sex'] === 'Female'
                        ? 'selected'
                        : '' ?>
                >
                    Female
                </option>

            </select>


            <label for="contact_number">
                Contact Number
            </label>

            <input
                type="text"
                id="contact_number"
                name="contact_number"
                value="<?= htmlspecialchars(
                    $patient['contact_number']
                ) ?>"
            >


            <label for="address">
                Address
            </label>

            <textarea
                id="address"
                name="address"
            ><?= htmlspecialchars(
                $patient['address']
            ) ?></textarea>


            <label for="email">
                Email
            </label>

            <input
                type="email"
                value="<?= htmlspecialchars(
                    $patient['email']
                ) ?>"
                class="readonly"
                readonly
            >


            <button type="submit">
                Update Patient Information
            </button>

        </form>

    </div>


    <a href="patient_view.php?id=<?= $patient['patient_id'] ?>">
        Back to Patient Record
    </a>

    <br>

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