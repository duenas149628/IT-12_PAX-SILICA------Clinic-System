<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/patients.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $emailInput = $_POST['email'] ?? '';
    $firstName = request_string($_POST, 'first_name');
    $lastName = request_string($_POST, 'last_name');
    $email = is_string($emailInput) ? trim($emailInput) : '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (
        $firstName === '' ||
        $lastName === '' ||
        empty($email) ||
        empty($password) ||
        empty($confirm_password)
    ) {

        $message = 'Please fill in all fields.';

    } elseif (
        strlen($firstName) > 50
        || strlen($lastName) > 50
        || strlen($firstName . ' ' . $lastName) > 100
        || strlen($email) > 254
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {

        $message = 'Please enter a valid first name, last name, and email address.';

    } elseif (!is_string($password) || !is_string($confirm_password) || strlen($password) < 12 || strlen($password) > 1024) {

        $message = 'Use a password between 12 and 1024 characters.';

    } elseif ($password !== $confirm_password) {

        $message = 'Passwords do not match.';

    } else {

        // Check if email already exists
        $stmt = $pdo->prepare(
            "SELECT user_id
             FROM users
             WHERE email = ?"
        );

        $stmt->execute([$email]);

        if ($stmt->fetch()) {

            $message = 'An account with this email already exists.';

        } else {

            try {
                $account = create_patient_account($pdo, $firstName, $lastName, $email, $password);
                audit_log('account.registered', [
                    'new_user_id' => $account['user_id'],
                    'patient_id' => $account['patient_id'],
                ]);
                header('Location: login.php?registered=1');
                exit;
            } catch (PDOException $error) {
                if ($error->getCode() === '23000') {
                    $message = 'An account with this email already exists.';
                } else {
                    error_log('Patient registration failed: ' . $error->getMessage());
                    $message = 'Unable to create your account right now. Please try again.';
                }
            }
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

    <title>Create Account - The Tooth Lounge</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {

            margin: 0;

            min-height: 100vh;

            font-family: Arial, sans-serif;

            display: flex;

            justify-content: center;

            align-items: center;

            background:
                linear-gradient(
                    rgba(0, 150, 170, 0.75),
                    rgba(30, 100, 180, 0.75)
                ),
                url("https://images.unsplash.com/photo-1606811971618-4486d14f3f99?auto=format&fit=crop&w=1920&q=80");

            background-size: cover;

            background-position: center;

            background-attachment: fixed;
        }


        /* Registration card */

        .register-card {

            width: 420px;

            max-width: 90%;

            padding: 40px;

            background: rgba(255, 255, 255, 0.95);

            border-radius: 20px;

            box-shadow:
                0 20px 50px rgba(0, 0, 0, 0.25);

            backdrop-filter: blur(10px);
        }


        /* Logo */

        .logo {

            width: 70px;

            height: 70px;

            margin: 0 auto 15px;

            display: flex;

            justify-content: center;

            align-items: center;

            background: #e6f7fa;

            border-radius: 50%;

            font-size: 35px;
        }


        /* Headings */

        h1 {

            margin: 0;

            text-align: center;

            color: #167b8c;

            font-size: 28px;
        }

        h2 {

            margin-top: 8px;

            margin-bottom: 25px;

            text-align: center;

            color: #555;

            font-size: 18px;

            font-weight: normal;
        }


        /* Labels */

        label {

            display: block;

            margin-top: 15px;

            margin-bottom: 6px;

            color: #444;

            font-weight: bold;
        }


        /* Inputs */

        input {

            width: 100%;

            padding: 13px 14px;

            border: 1px solid #d5d5d5;

            border-radius: 10px;

            font-size: 15px;

            outline: none;

            transition: 0.2s;
        }

        input:focus {

            border-color: #168ca0;

            box-shadow:
                0 0 0 3px rgba(22, 140, 160, 0.12);
        }


        /* Password container */

        .password-container {

            position: relative;
        }

        .password-container input {

            padding-right: 50px;
        }


        /* Eye button */

        .toggle-password {

            position: absolute;

            right: 12px;

            top: 50%;

            transform: translateY(-50%);

            width: 30px;

            height: 30px;

            padding: 0;

            border: none;

            background: transparent;

            cursor: pointer;

            font-size: 18px;
        }

        .toggle-password:hover {

            opacity: 0.6;
        }


        /* Create account button */

        .register-button {

            width: 100%;

            margin-top: 25px;

            padding: 13px;

            border: none;

            border-radius: 10px;

            background:
                linear-gradient(
                    135deg,
                    #168ca0,
                    #2367b1
                );

            color: white;

            font-size: 16px;

            font-weight: bold;

            cursor: pointer;

            transition: 0.2s;
        }

        .register-button:hover {

            transform: translateY(-2px);

            box-shadow:
                0 8px 20px rgba(22, 100, 160, 0.3);
        }


        /* Error message */

        .message {

            margin-bottom: 15px;

            padding: 12px;

            border-radius: 8px;

            background: #fff0f0;

            color: #c0392b;

            text-align: center;

            font-size: 14px;
        }


        /* Login link */

        .login-link {

            margin-top: 25px;

            text-align: center;

            color: #666;

            font-size: 14px;
        }

        .login-link a {

            color: #167b8c;

            font-weight: bold;

            text-decoration: none;
        }

        .login-link a:hover {

            text-decoration: underline;
        }


        /* Mobile */

        @media (max-width: 500px) {

            .register-card {

                padding: 30px 25px;
            }
        }

    </style>

</head>


<body>

    <div class="register-card">

        <!-- Logo -->

        <div class="logo">
            &#129463;
        </div>


        <!-- Title -->

        <h1>The Tooth Lounge</h1>

        <h2>Create Your Account</h2>


        <!-- Message -->

        <?php if ($message): ?>

            <div class="message">

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>


        <!-- Registration form -->

        <form method="POST">
<?= csrf_field() ?>


            <!-- Patient name -->

            <label for="first_name">
                First Name
            </label>

            <input
                type="text"
                id="first_name"
                name="first_name"
                maxlength="50"
                autocomplete="given-name"
                placeholder="Enter your first name"
                required
            >

            <label for="last_name">
                Last Name
            </label>

            <input
                type="text"
                id="last_name"
                name="last_name"
                maxlength="50"
                autocomplete="family-name"
                placeholder="Enter your last name"
                required
            >


            <!-- Email -->

            <label for="email">
                Email
            </label>

            <input
                type="email"
                id="email"
                name="email"
                placeholder="Enter your email"
                required
            >


            <!-- Password -->

            <label for="password">
                Password
            </label>

            <div class="password-container">

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Create a password"
                    required
                >

                <button
                    type="button"
                    class="toggle-password"
                    onclick="togglePassword('password', this)"
                    aria-label="Show password"
                >
                    &#128065;
                </button>

            </div>


            <!-- Confirm password -->

            <label for="confirm_password">
                Confirm Password
            </label>

            <div class="password-container">

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Confirm your password"
                    required
                >

                <button
                    type="button"
                    class="toggle-password"
                    onclick="togglePassword('confirm_password', this)"
                    aria-label="Show password"
                >
                    &#128065;
                </button>

            </div>


            <!-- Create account -->

            <button
                type="submit"
                class="register-button"
            >
                Create Account
            </button>

        </form>


        <!-- Login link -->

        <div class="login-link">

            Already have an account?

            <a href="login.php">
                Login
            </a>

        </div>

    </div>


    <script>

        function togglePassword(inputId, button) {

            const passwordInput =
                document.getElementById(inputId);


            if (passwordInput.type === 'password') {

                passwordInput.type = 'text';

                button.textContent = '\u{1F648}';

                button.setAttribute(
                    'aria-label',
                    'Hide password'
                );

            } else {

                passwordInput.type = 'password';

                button.textContent = '\u{1F441}';

                button.setAttribute(
                    'aria-label',
                    'Show password'
                );
            }
        }

    </script>

</body>

</html>
