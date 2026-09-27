<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SESSION['role'] !== 'patient') {
    die('Access denied.');
}

$user_id = $_SESSION['user_id'];

$message = '';

/*
|--------------------------------------------------------------------------
| Get patient information
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT *
     FROM patients
     WHERE user_id = ?"
);

$stmt->execute([$user_id]);

$patient = $stmt->fetch();

if (!$patient) {
    die('Patient profile not found.');
}

/*
|--------------------------------------------------------------------------
| Update patient information
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $birth_date = $_POST['birth_date'] ?: null;
    $sex = trim($_POST['sex']);
    $contact_number = trim($_POST['contact_number']);
    $address = trim($_POST['address']);

    if (empty($first_name) || empty($last_name)) {

        $message = 'First name and last name are required.';

    } else {

        $stmt = $pdo->prepare(
            "UPDATE patients
             SET
                first_name = ?,
                last_name = ?,
                birth_date = ?,
                sex = ?,
                contact_number = ?,
                address = ?
             WHERE user_id = ?"
        );

        $stmt->execute([
            $first_name,
            $last_name,
            $birth_date,
            $sex,
            $contact_number,
            $address,
            $user_id
        ]);

        // Update the name stored in the login session
        $full_name = $first_name . ' ' . $last_name;

        $stmt = $pdo->prepare(
            "UPDATE users
             SET name = ?
             WHERE user_id = ?"
        );

        $stmt->execute([
            $full_name,
            $user_id
        ]);

        $_SESSION['name'] = $full_name;

        $message = 'Profile updated successfully.';

        // Reload updated patient information
        $stmt = $pdo->prepare(
            "SELECT *
             FROM patients
             WHERE user_id = ?"
        );

        $stmt->execute([$user_id]);

        $patient = $stmt->fetch();
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

    <title>My Profile - The Tooth Lounge</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 40px auto;
            padding: 20px;
        }

        h1 {
            margin-bottom: 10px;
        }

        label {
            display: block;
            margin-top: 15px;
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
            height: 80px;
        }

        button {
            width: 100%;
            padding: 12px;
            margin-top: 20px;
            cursor: pointer;
        }

        .message {
            padding: 10px;
            margin: 15px 0;
            background: #f0f0f0;
        }

        .info {
            margin-bottom: 20px;
        }

        a {
            display: inline-block;
            margin-top: 15px;
        }

    </style>

</head>

<body>

    <h1>My Profile</h1>

    <div class="info">

        <strong>Patient Number:</strong>

        <?= htmlspecialchars($patient['patient_number']) ?>

    </div>

    <?php if ($message): ?>

        <div class="message">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>

    <form method="POST">

        <label for="first_name">
            First Name *
        </label>

        <input
            type="text"
            id="first_name"
            name="first_name"
            value="<?= htmlspecialchars($patient['first_name']) ?>"
            required
        >

        <label for="last_name">
            Last Name *
        </label>

        <input
            type="text"
            id="last_name"
            name="last_name"
            value="<?= htmlspecialchars($patient['last_name']) ?>"
            required
        >

        <label for="birth_date">
            Birth Date
        </label>

        <input
            type="date"
            id="birth_date"
            name="birth_date"
            value="<?= htmlspecialchars($patient['birth_date'] ?? '') ?>"
        >

        <label for="sex">
            Sex
        </label>

        <select id="sex" name="sex">

            <option value="">Select</option>

            <option
                value="Male"
                <?= $patient['sex'] === 'Male' ? 'selected' : '' ?>
            >
                Male
            </option>

            <option
                value="Female"
                <?= $patient['sex'] === 'Female' ? 'selected' : '' ?>
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
            value="<?= htmlspecialchars($patient['contact_number'] ?? '') ?>"
        >

        <label for="address">
            Address
        </label>

        <textarea
            id="address"
            name="address"
        ><?= htmlspecialchars($patient['address'] ?? '') ?></textarea>

        <button type="submit">
            Save Changes
        </button>

    </form>

    <a href="dashboard.php">
        Back to Dashboard
    </a>

    <br>

    <a href="../auth/logout.php">
        Logout
    </a>

</body>

</html>