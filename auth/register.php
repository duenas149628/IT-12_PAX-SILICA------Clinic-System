
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $contact_number = trim($_POST['contact_number']);

    if (
        empty($first_name) ||
        empty($last_name) ||
        empty($email) ||
        empty($password)
    ) {
        $message = 'Please fill in all required fields.';
    } else {

        $check = $pdo->prepare(
            "SELECT user_id FROM users WHERE email = ?"
        );

        $check->execute([$email]);

        if ($check->fetch()) {

            $message = 'Email address is already registered.';

        } else {

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            try {

                $pdo->beginTransaction();

                // Create user account
                $stmt = $pdo->prepare(
                    "INSERT INTO users
                    (name, email, password, role)
                    VALUES (?, ?, ?, 'patient')"
                );

                $full_name = $first_name . ' ' . $last_name;

                $stmt->execute([
                    $full_name,
                    $email,
                    $hashed_password
                ]);

                $user_id = $pdo->lastInsertId();

                // Generate patient number
                $patient_number = 'TL-' . str_pad(
                    $user_id,
                    5,
                    '0',
                    STR_PAD_LEFT
                );

                // Create patient profile
                $stmt = $pdo->prepare(
                    "INSERT INTO patients
                    (
                        user_id,
                        patient_number,
                        first_name,
                        last_name,
                        contact_number
                    )
                    VALUES (?, ?, ?, ?, ?)"
                );

                $stmt->execute([
                    $user_id,
                    $patient_number,
                    $first_name,
                    $last_name,
                    $contact_number
                ]);

                $pdo->commit();

                // Registration successful → redirect to login
                header('Location: login.php?registered=1');
                exit;

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $message = 'Registration failed. Please try again.';
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Patient Registration - The Tooth Lounge</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
        }

        h1 {
            text-align: center;
        }

        label {
            display: block;
            margin-top: 15px;
        }

        input {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: 10px;
            margin-top: 20px;
            cursor: pointer;
        }

        .message {
            margin-bottom: 15px;
            padding: 10px;
            background: #f0f0f0;
        }
    </style>
</head>

<body>

    <h1>Patient Registration</h1>

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
            required
        >

        <label for="last_name">
            Last Name *
        </label>

        <input
            type="text"
            id="last_name"
            name="last_name"
            required
        >

        <label for="email">
            Email *
        </label>

        <input
            type="email"
            id="email"
            name="email"
            required
        >

        <label for="password">
            Password *
        </label>

        <input
            type="password"
            id="password"
            name="password"
            required
        >

        <label for="contact_number">
            Contact Number
        </label>

        <input
            type="text"
            id="contact_number"
            name="contact_number"
        >

        <button type="submit">
            Register
        </button>

    </form>

</body>
</html>
