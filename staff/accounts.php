<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: ../auth/login.php');
    exit;
}

header('Cache-Control: private, no-store, max-age=0');
$message = '';
$createdAccount = $_SESSION['created_staff_credentials'] ?? null;
unset($_SESSION['created_staff_credentials']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $firstName = request_string($_POST, 'first_name');
    $lastName = request_string($_POST, 'last_name');
    $email = request_string($_POST, 'email');
    $name = trim($firstName . ' ' . $lastName);

    if ($firstName === '' || $lastName === '' || $email === '') {
        $message = 'First name, last name, and email are required.';
    } elseif (strlen($firstName) > 50 || strlen($lastName) > 50 || strlen($name) > 100 || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Enter valid names and an email address.';
    } else {
        $exists = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
        $exists->execute([$email]);
        if ($exists->fetch()) {
            $message = 'An account with this email already exists.';
        } else {
            $temporaryPassword = STAFF_ISSUED_TEMPORARY_PASSWORD;
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO users (name, email, password, role, must_change_password)
                     VALUES (?, ?, ?, 'staff', 1)"
                );
                $stmt->execute([$name, $email, password_hash($temporaryPassword, PASSWORD_DEFAULT)]);
                $staffUserId = (int) $pdo->lastInsertId();
                audit_log('staff.account.created', ['new_user_id' => $staffUserId]);
                $_SESSION['created_staff_credentials'] = [
                    'email' => $email,
                    'temporary_password' => $temporaryPassword,
                ];
                header('Location: accounts.php?created=1');
                exit;
            } catch (PDOException $error) {
                if ($error->getCode() === '23000') {
                    $message = 'An account with this email already exists.';
                } else {
                    error_log('Staff account creation failed: ' . $error->getMessage());
                    $message = 'Unable to create the staff account right now. Please try again.';
                }
            }
        }
    }
}

$staffAccounts = $pdo->query(
    "SELECT user_id, name, email, created_at FROM users WHERE role = 'staff' ORDER BY name, user_id"
)->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Accounts - The Tooth Lounge</title>
    <style>
        * { box-sizing:border-box; }
        body { min-height:100vh; margin:0; color:#263b40; font-family:Arial,Helvetica,sans-serif; background:linear-gradient(135deg,#e7f5f6,#f6fafb); }
        main { width:min(1000px,94%); margin:34px auto; }
        h1 { margin:0 0 7px; color:#167b8c; font-size:30px; }
        .intro { margin:0 0 20px; color:#60777c; }
        .columns { display:grid; grid-template-columns:minmax(280px,.8fr) minmax(0,1.2fr); gap:18px; align-items:start; }
        .card { padding:22px; background:#fff; border:1px solid #e0ebed; border-radius:15px; box-shadow:0 12px 32px #123f4a17; }
        .card h2 { margin:0 0 16px; color:#167b8c; font-size:19px; }
        label { display:block; margin:14px 0 6px; color:#40545a; font-size:13px; font-weight:700; }
        input { width:100%; min-height:44px; padding:10px 12px; border:1px solid #cad9dc; border-radius:8px; font-size:14px; }
        .button { display:inline-flex; align-items:center; justify-content:center; min-height:43px; margin-top:18px; padding:10px 15px; border:1px solid #167b8c; border-radius:8px; background:#167b8c; color:#fff; font-weight:700; cursor:pointer; }
        .message { margin-bottom:16px; padding:13px 15px; border-radius:9px; background:#fff1e8; color:#8b451f; }
        .success { background:#e7f7ed; color:#236b43; }
        .credentials { overflow-wrap:anywhere; font-family:Consolas,monospace; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; font-size:14px; }
        th,td { padding:12px 9px; border-bottom:1px solid #e7eef0; text-align:left; }
        th { color:#167b8c; font-size:12px; text-transform:uppercase; }
        .hint { color:#687a7e; font-size:13px; line-height:1.5; }
        @media(max-width:760px) { .columns { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/navigation.php'; ?>
<main>
    <h1>Staff Accounts</h1>
    <p class="intro">Create an individual login for each staff member.</p>
    <?php if ($createdAccount): ?>
        <div class="message success" role="status">
            <strong>Staff account created.</strong><br>
            Login email: <span class="credentials"><?= h($createdAccount['email']) ?></span><br>
            Temporary password: <span class="credentials"><?= h($createdAccount['temporary_password']) ?></span><br>
            Share it privately. The staff member must replace it before using the system.
        </div>
    <?php endif; ?>
    <?php if ($message !== ''): ?><div class="message" role="alert"><?= h($message) ?></div><?php endif; ?>
    <div class="columns">
        <section class="card">
            <h2>Create staff login</h2>
            <form method="POST" autocomplete="off">
                <?= csrf_field() ?>
                <label for="first_name">First name</label>
                <input id="first_name" name="first_name" maxlength="50" autocomplete="given-name" required>
                <label for="last_name">Last name</label>
                <input id="last_name" name="last_name" maxlength="50" autocomplete="family-name" required>
                <label for="email">Work email</label>
                <input id="email" name="email" type="email" maxlength="254" autocomplete="email" required>
                <button class="button" type="submit">Create account</button>
            </form>
            <p class="hint">The shared temporary password, 1234, is displayed after creation. Each staff login is recorded separately, and every new staff member must change it at first sign-in.</p>
        </section>
        <section class="card">
            <h2>Current staff logins</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Name</th><th>Email</th><th>Created</th></tr></thead>
                    <tbody>
                    <?php foreach ($staffAccounts as $account): ?>
                        <tr><td><?= h($account['name']) ?></td><td><?= h($account['email']) ?></td><td><?= h($account['created_at']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>
</body>
</html>
