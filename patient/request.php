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
| Get patient ID
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT patient_id
     FROM patients
     WHERE user_id = ?"
);

$stmt->execute([$user_id]);

$patient = $stmt->fetch();

if (!$patient) {
    die('Patient profile not found.');
}

$patient_id = $patient['patient_id'];

/*
|--------------------------------------------------------------------------
| Submit appointment request
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $preferred_date = $_POST['preferred_date'];
    $preferred_start_time = $_POST['preferred_start_time'];
    $preferred_end_time = $_POST['preferred_end_time'];
    $reason = trim($_POST['reason']);

    if (
        empty($preferred_date) ||
        empty($preferred_start_time) ||
        empty($preferred_end_time)
    ) {

        $message = 'Please complete the date and preferred time range.';

    } elseif ($preferred_start_time >= $preferred_end_time) {

        $message = 'The preferred end time must be later than the start time.';

    } else {

        $stmt = $pdo->prepare(
            "INSERT INTO appointments
            (
                patient_id,
                preferred_date,
                preferred_start_time,
                preferred_end_time,
                reason,
                status
            )
            VALUES (?, ?, ?, ?, ?, 'pending')"
        );

        $stmt->execute([
            $patient_id,
            $preferred_date,
            $preferred_start_time,
            $preferred_end_time,
            $reason
        ]);

        $message = 'Appointment request submitted successfully.';
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

    <title>Request Appointment - The Tooth Lounge</title>

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
        textarea {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            box-sizing: border-box;
        }

        textarea {
            height: 100px;
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

        a {
            display: inline-block;
            margin-top: 15px;
        }

    </style>

</head>

<body>

    <h1>Request an Appointment</h1>

    <?php if ($message): ?>

        <div class="message">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>

    <form method="POST">

        <label for="preferred_date">
            Preferred Date *
        </label>

        <input
            type="date"
            id="preferred_date"
            name="preferred_date"
            required
        >

        <label for="preferred_start_time">
            Preferred Start Time *
        </label>

        <input
            type="time"
            id="preferred_start_time"
            name="preferred_start_time"
            required
        >

        <label for="preferred_end_time">
            Preferred End Time *
        </label>

        <input
            type="time"
            id="preferred_end_time"
            name="preferred_end_time"
            required
        >

        <label for="reason">
            Reason for Appointment
        </label>

        <textarea
            id="reason"
            name="reason"
            placeholder="Enter the reason for your appointment"
        ></textarea>

        <button type="submit">
            Submit Appointment Request
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