<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$message = '';

if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $message = 'Registration successful! Please log in to continue.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $emailInput = $_POST['email'] ?? '';
    $email = is_string($emailInput) ? trim($emailInput) : '';
    $password = $_POST['password'] ?? '';

    if (login_is_rate_limited()) {
        http_response_code(429);
        $message = 'Too many login attempts. Please wait 15 minutes and try again.';

    } elseif (!is_string($password) || $email === '' || $password === '') {

        $message = 'Please enter your email and password.';

    } elseif (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {

        login_record_failure();
        $message = 'Invalid email or password.';

    } else {

        $stmt = $pdo->prepare(
            "SELECT user_id, name, email, password, role, must_change_password
             FROM users
             WHERE email = ?"
        );

        $stmt->execute([$email]);

        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {

            login_clear_failures();
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['must_change_password'] = (bool) $user['must_change_password'];
            audit_log('login.success');

            if ($_SESSION['must_change_password']) {
                header('Location: password_change.php');
                exit;
            }

            if ($user['role'] === 'staff') {

                header('Location: ../staff/dashboard.php');
                exit;
            }

            header('Location: ../patient/dashboard.php');
            exit;

        } else {

            login_record_failure();
            audit_log('login.failure', ['email_hash' => hash('sha256', strtolower($email))]);
            $message = 'Invalid email or password.';
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

    <title>Login - The Tooth Lounge</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {

            margin: 0;

            min-height: 100vh;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

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


        /* =========================
           LOGIN CARD
        ========================= */

        .login-card {

            width: 400px;

            max-width: 90%;

            padding: 40px;

            background:
                rgba(255, 255, 255, 0.95);

            border-radius: 20px;

            box-shadow:
                0 20px 50px
                rgba(0, 0, 0, 0.25);

            backdrop-filter: blur(10px);
        }


        /* =========================
           LOGO
        ========================= */

        .login-logo {
            display:block;
            width:min(100%, 280px);
            height:auto;
            margin:0 auto 18px;
        }


        /* =========================
           TITLE
        ========================= */

        h1 {

            margin: 0;

            text-align: center;

            color: #167b8c;

            font-size: 28px;
        }


        h2 {

            margin-top: 8px;

            margin-bottom: 30px;

            text-align: center;

            color: #555;

            font-size: 18px;

            font-weight: normal;
        }


        /* =========================
           LABEL
        ========================= */

        label {

            display: block;

            margin-top: 18px;

            margin-bottom: 6px;

            color: #444;

            font-weight: bold;
        }


        /* =========================
           INPUT
        ========================= */

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
                0 0 0 3px
                rgba(22, 140, 160, 0.12);
        }


        /* =========================
           PASSWORD
        ========================= */

        .password-container {

            position: relative;
        }


        .password-container input {

            padding-right: 50px;
        }


        /* =========================
           EYE ICON
        ========================= */

        .toggle-password {

            position: absolute;

            right: 10px;

            top: 50%;

            transform: translateY(-50%);

            width: 35px;

            height: 35px;

            padding: 0;

            border: none;

            background: transparent;

            cursor: pointer;

            display: flex;

            justify-content: center;

            align-items: center;

            color: #60757a;

            opacity: 0.75;

            transition: 0.2s;
        }


        .toggle-password:hover {

            color: #167b8c;

            opacity: 1;

            transform:
                translateY(-50%)
                scale(1.1);
        }


        .toggle-password svg {

            width: 21px;

            height: 21px;

            fill: none;

            stroke: currentColor;

            stroke-width: 2;

            stroke-linecap: round;

            stroke-linejoin: round;
        }


        /* =========================
           LOGIN BUTTON
        ========================= */

        .login-button {

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


        .login-button:hover {

            transform: translateY(-2px);

            box-shadow:
                0 8px 20px
                rgba(22, 100, 160, 0.3);
        }


        /* =========================
           MESSAGE
        ========================= */

        .message {

            margin-bottom: 15px;

            padding: 12px;

            border-radius: 8px;

            background: #eef9fa;

            color: #167b8c;

            text-align: center;

            font-size: 14px;
        }


        /* =========================
           REGISTER
        ========================= */

        .register-link {

            margin-top: 25px;

            text-align: center;

            color: #666;

            font-size: 14px;
        }


        .register-link a {

            color: #167b8c;

            font-weight: bold;

            text-decoration: none;
        }


        .register-link a:hover {

            text-decoration: underline;
        }


        /* =========================
           MOBILE
        ========================= */

        @media (max-width: 500px) {

            .login-card {

                padding: 30px 25px;
            }

        }

    </style>

</head>


<body>


    <div class="login-card">


        <!-- LOGO -->

        <img class="login-logo" src="../assets/tooth-lounge-logo.png" alt="The Tooth Lounge Co.">


        <!-- TITLE -->

        <h1>
            Welcome Back
        </h1>


        <!-- MESSAGE -->

        <?php if ($message): ?>

            <div class="message">

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>


        <!-- LOGIN FORM -->

        <form method="POST">
<?= csrf_field() ?>


            <!-- EMAIL -->

            <label for="email">
                Email
            </label>


            <input
                type="email"
                id="email"
                name="email"
                placeholder="Enter your email"
                autocomplete="email"
                required
            >


            <!-- PASSWORD -->

            <label for="password">
                Password
            </label>


            <div class="password-container">


                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >


                <!-- EYE ICON -->

                <button
                    type="button"
                    class="toggle-password"
                    aria-label="Show password"
                >

                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >

                        <path
                            d="M2 12s3.5-7 10-7
                               10 7 10 7
                               -3.5 7-10 7
                               -10-7-10-7z"
                        />

                        <circle
                            cx="12"
                            cy="12"
                            r="3"
                        />

                    </svg>

                </button>


            </div>


            <!-- LOGIN BUTTON -->

            <button
                type="submit"
                class="login-button"
            >

                Login

            </button>


        </form>


        <!-- REGISTER -->

        <div class="register-link">

            Don't have an account?

            <a href="register.php">
                Create an Account
            </a>

        </div>


    </div>


    <script>

        const passwordInput =
            document.getElementById('password');

        const eyeButton =
            document.querySelector('.toggle-password');


        /*
         * SHOW PASSWORD
         * WHEN MOUSE ENTERS EYE
         */

        eyeButton.addEventListener(
            'mouseenter',
            function () {

                passwordInput.type = 'text';

                eyeButton.setAttribute(
                    'aria-label',
                    'Hide password'
                );

            }
        );


        /*
         * HIDE PASSWORD
         * WHEN MOUSE LEAVES EYE
         */

        eyeButton.addEventListener(
            'mouseleave',
            function () {

                passwordInput.type = 'password';

                eyeButton.setAttribute(
                    'aria-label',
                    'Show password'
                );

            }
        );


        /*
         * ALSO HIDE PASSWORD
         * WHEN EYE LOSES FOCUS
         */

        eyeButton.addEventListener(
            'blur',
            function () {

                passwordInput.type = 'password';

                eyeButton.setAttribute(
                    'aria-label',
                    'Show password'
                );

            }
        );

    </script>


</body>

</html>
