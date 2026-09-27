<?php

require_once __DIR__ . '/../config/auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SESSION['role'] !== 'patient') {
    die('Access denied.');
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

    <style>

        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
        }

        .welcome {
            margin-bottom: 30px;
        }

        .menu {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(200px, 1fr)
            );
            gap: 15px;
        }

        .card {
            display: block;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
        }

        .card:hover {
            background: #f5f5f5;
        }

        .card h2 {
            margin-top: 0;
        }

        .logout {
            display: inline-block;
            margin-top: 30px;
        }

    </style>

</head>

<body>

    <div class="welcome">

        <h1>
            Patient Dashboard
        </h1>

        <p>
            Welcome,
            <strong>
                <?= htmlspecialchars($_SESSION['name']) ?>
            </strong>
        </p>

        <p>
            The Tooth Lounge
        </p>

    </div>


    <div class="menu">

        <a
            href="profile.php"
            class="card"
        >

            <h2>
                My Profile
            </h2>

            <p>
                View and update your personal information.
            </p>

        </a>


        <a
            href="request.php"
            class="card"
        >

            <h2>
                Request Appointment
            </h2>

            <p>
                Submit a preferred date and time for your appointment.
            </p>

        </a>


        <a
            href="appointments.php"
            class="card"
        >

            <h2>
                My Appointments
            </h2>

            <p>
                View your pending, confirmed, completed,
                and cancelled appointments.
            </p>

        </a>


        <a
            href="visit_history.php"
            class="card"
        >

            <h2>
                My Visit History
            </h2>

            <p>
                View your previous completed visit records.
            </p>

        </a>

    </div>


    <a
        href="../auth/logout.php"
        class="logout"
    >
        Logout
    </a>

</body>

</html>