
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$message = '';

// Show registration success message
if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $message = 'Registration successful! Please log in to continue.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {

        $message = 'Please enter your email and password.';

    } else {

        $stmt = $pdo->prepare(
            "SELECT user_id, name, email, password, role
             FROM users
             WHERE email = ?"
        );

        $stmt->execute([$email]);

        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'staff') {
                header('Location: ../staff/dashboard.php');
                exit;
            }

            header('Location: ../patient/dashboard.php');
            exit;

        } else {

            $message = 'Invalid email or password.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - The Tooth Lounge</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 400px;
            margin: 80px auto;
            padding: 20px;
        }

        h1 {
            text-align: center;
        }

        h2 {
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

    <h1>The Tooth Lounge</h1>

    <h2>Login</h2>

    <?php if ($message): ?>

        <div class="message">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>

    <form method="POST">

        <label for="email">
            Email
        </label>

        <input
            type="email"
            id="email"
            name="email"
            required
        >

        <label for="password">
            Password
        </label>

        <input
            type="password"
            id="password"
            name="password"
            required
        >

        <button type="submit">
            Login
        </button>

    </form>

    <p>
        Don't have an account?
        <a href="register.php">Create an Account</a>
    </p>

</body>

</html>

