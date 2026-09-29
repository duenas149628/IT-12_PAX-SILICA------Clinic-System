<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!is_string($current) || !is_string($new) || !is_string($confirm)) {
        $message = 'Please enter valid password values.';
    } elseif (strlen($new) < 12 || strlen($new) > 1024) {
        $message = 'Use a new password between 12 and 1024 characters.';
    } elseif ($new !== $confirm) {
        $message = 'The new passwords do not match.';
    } elseif ($current === $new) {
        $message = 'Choose a password different from your current password.';
    } else {
        $stmt = $pdo->prepare('SELECT password FROM users WHERE user_id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($current, $user['password'])) {
            audit_log('password.change.failure');
            $message = 'The current password is incorrect.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?');
            $stmt->execute([$hash, $_SESSION['user_id']]);
            session_regenerate_id(true);
            audit_log('password.change.success');
            $message = 'Your password has been changed.';
            $success = true;
        }
    }
}

$back = ($_SESSION['role'] ?? '') === 'staff' ? '../staff/dashboard.php' : '../patient/dashboard.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Change password - The Tooth Lounge</title>
    <style>
        body{font:16px Arial,sans-serif;background:#f2f8fa;color:#24343a;margin:0;padding:32px}
        main{max-width:480px;margin:6vh auto;background:#fff;padding:32px;border-radius:14px;box-shadow:0 8px 30px #18343a18}
        label{display:block;margin:18px 0 6px;font-weight:bold}input{box-sizing:border-box;width:100%;padding:12px;border:1px solid #b9c9ce;border-radius:8px}
        button{margin-top:22px;background:#167b8c;color:#fff;border:0;padding:12px 18px;border-radius:8px;cursor:pointer}
        .message{padding:12px;background:#fff2df;border-radius:8px}.success{background:#e8f7ed}a{color:#167b8c}
    </style>
</head>
<body>
<main>
    <h1>Change password</h1>
    <?php if ($message !== ''): ?>
        <p class="message <?= $success ? 'success' : '' ?>" role="status"><?= h($message) ?></p>
    <?php endif; ?>
    <form method="POST" autocomplete="on">
        <?= csrf_field() ?>
        <label for="current_password">Current password</label>
        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
        <label for="new_password">New password</label>
        <input id="new_password" name="new_password" type="password" minlength="12" maxlength="1024" autocomplete="new-password" required>
        <label for="confirm_password">Confirm new password</label>
        <input id="confirm_password" name="confirm_password" type="password" minlength="12" maxlength="1024" autocomplete="new-password" required>
        <button type="submit">Update password</button>
    </form>
    <p><a href="<?= h($back) ?>">Return to dashboard</a></p>
</main>
</body>
</html>
