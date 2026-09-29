<?php

require_once __DIR__ . '/../config/auth.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: ../auth/login.php');
    exit;
}

$path = audit_log_path();
$entries = [];
if (is_file($path) && is_readable($path)) {
    $file = new SplFileObject($path, 'r');
    $lines = [];
    while (!$file->eof()) {
        $line = trim($file->fgets());
        if ($line !== '') {
            $lines[] = $line;
        }
    }
    foreach (array_slice(array_reverse($lines), 0, 500) as $line) {
        $entry = json_decode($line, true);
        if (is_array($entry)) {
            $entries[] = $entry;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activity Log - The Tooth Lounge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            color: #333;
            background: linear-gradient(rgba(0, 135, 155, .78), rgba(20, 85, 150, .78)),
                url("https://images.unsplash.com/photo-1606811971618-4486d14f3f99?auto=format&fit=crop&w=1920&q=80");
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
        .header {
            min-height: 75px; display: flex; align-items: center; justify-content: space-between;
            gap: 20px; padding: 12px 35px; background: rgba(255,255,255,.96);
            box-shadow: 0 3px 15px rgba(0,0,0,.15);
        }
        .logo { display: flex; align-items: center; gap: 12px; color: #167b8c; font-size: 21px; font-weight: bold; white-space: nowrap; }
        .logo-icon { width: 43px; height: 43px; display: grid; place-items: center; border-radius: 50%; background: #e6f7fa; color: #167b8c; font-size: 21px; }
        .nav { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 5px; }
        .nav a { display: flex; align-items: center; gap: 7px; padding: 10px 12px; border-radius: 8px; color: #555; text-decoration: none; font-size: 14px; transition: .2s; }
        .nav a:hover, .nav a[aria-current="page"] { background: #e6f7fa; color: #167b8c; }
        .nav .logout { margin-left: 5px; background: #167b8c; color: #fff; }
        .nav .logout:hover { background: #126c79; color: #fff; }
        .container { width: 1100px; max-width: 92%; margin: 40px auto; }
        .welcome { margin-bottom: 25px; color: #fff; }
        .welcome h1 { margin-bottom: 8px; font-size: 32px; font-weight: 600; }
        .welcome p { font-size: 16px; opacity: .92; }
        .panel { padding: 28px; border-radius: 15px; background: rgba(255,255,255,.97); box-shadow: 0 10px 30px rgba(0,0,0,.15); }
        .panel-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .panel-heading h2 { display: flex; align-items: center; gap: 10px; color: #167b8c; font-size: 22px; }
        .count { padding: 7px 11px; border-radius: 20px; background: #e6f7fa; color: #167b8c; font-size: 13px; font-weight: 700; white-space: nowrap; }
        .table-scroll { overflow-x: auto; border: 1px solid #e4ebed; border-radius: 10px; }
        table { width: 100%; min-width: 700px; border-collapse: collapse; text-align: left; }
        th { padding: 14px 16px; background: #f1fbfc; color: #52666a; font-size: 12px; text-transform: uppercase; letter-spacing: .055em; white-space: nowrap; }
        td { padding: 14px 16px; border-top: 1px solid #edf1f2; color: #45565a; font-size: 14px; vertical-align: middle; }
        tbody tr:hover { background: #f8fcfc; }
        .time { min-width: 190px; color: #64777b; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .event-name { display: inline-block; padding: 6px 9px; border-radius: 6px; background: #e6f7fa; color: #126b7a; font-family: Consolas, monospace; font-size: 13px; font-weight: 700; white-space: nowrap; }
        .user-id { color: #53676b; font-weight: 600; }
        .details { max-width: 380px; overflow-wrap: anywhere; color: #687a7e; font-family: Consolas, monospace; font-size: 13px; }
        .empty { padding: 42px 20px; text-align: center; color: #617579; }
        .empty i { display: block; margin-bottom: 13px; color: #167b8c; font-size: 30px; }
        .empty p { margin-top: 7px; font-size: 14px; }
        @media (max-width: 1050px) {
            .header { align-items: flex-start; flex-direction: column; }
            .nav { justify-content: flex-start; }
        }
        @media (max-width: 650px) {
            .header { gap: 12px; padding: 15px; }
            .nav { gap: 3px; }
            .nav a { padding: 9px; font-size: 13px; }
            .container { max-width: 94%; margin: 25px auto; }
            .welcome h1 { font-size: 26px; }
            .panel { padding: 19px 14px; }
            .panel-heading h2 { font-size: 19px; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo"><span class="logo-icon"><i class="fa-solid fa-tooth" aria-hidden="true"></i></span>The Tooth Lounge</div>
        <nav class="nav" aria-label="Staff navigation">
            <a href="dashboard.php"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i>Dashboard</a>
            <a href="patients.php"><i class="fa-solid fa-users" aria-hidden="true"></i>Patients</a>
            <a href="appointments.php"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i>Appointments</a>
            <a href="visit_records.php"><i class="fa-solid fa-file-medical" aria-hidden="true"></i>Records</a>
            <a href="reports.php"><i class="fa-solid fa-chart-column" aria-hidden="true"></i>Reports</a>
            <a href="../notifications.php"><i class="fa-regular fa-bell" aria-hidden="true"></i>Notifications</a>
            <a href="activity_log.php" aria-current="page"><i class="fa-solid fa-list-check" aria-hidden="true"></i>Activity log</a>
            <a href="../auth/password_change.php">Change password</a>
            <a href="../auth/logout.php" class="logout"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>Logout</a>
        </nav>
    </header>

    <main class="container">
        <section class="welcome">
            <h1>Activity log</h1>
            <p>Review recent account and system activity.</p>
        </section>
        <section class="panel" aria-labelledby="activity-heading">
            <div class="panel-heading">
                <h2 id="activity-heading"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>Recent activity</h2>
                <span class="count">Latest <?= h(count($entries)) ?> events</span>
            </div>
            <?php if (!$entries): ?>
                <div class="empty">
                    <i class="fa-regular fa-clipboard" aria-hidden="true"></i>
                    <strong>No activity to show yet</strong>
                    <p>New account and system events will appear here.</p>
                </div>
            <?php else: ?>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th scope="col">Time (UTC)</th><th scope="col">Event</th><th scope="col">User ID</th><th scope="col">Details</th></tr></thead>
                        <tbody><?php foreach ($entries as $entry): ?>
                            <tr>
                                <td class="time"><?= h($entry['time_utc'] ?? '') ?></td>
                                <td><span class="event-name"><?= h($entry['event'] ?? 'unknown') ?></span></td>
                                <td class="user-id"><?= h($entry['user_id'] ?? 'Guest') ?></td>
                                <td class="details"><?= h(json_encode($entry['details'] ?? [], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)) ?></td>
                            </tr>
                        <?php endforeach; ?></tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
