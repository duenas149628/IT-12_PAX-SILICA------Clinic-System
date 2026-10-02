<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

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
    foreach (array_slice(array_reverse($lines), 0, 5000) as $line) {
        $entry = json_decode($line, true);
        if (is_array($entry)) {
            $entries[] = $entry;
        }
    }
}

$fromDate = request_string($_GET, 'from_date');
$toDate = request_string($_GET, 'to_date');
$eventFilter = request_string($_GET, 'event');
$userFilter = request_string($_GET, 'user_id');
$search = request_string($_GET, 'search');
$validDate = static function (string $date): bool {
    if ($date === '') {
        return true;
    }
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
};
$filterMessage = '';
if (!$validDate($fromDate) || !$validDate($toDate) || ($fromDate !== '' && $toDate !== '' && $fromDate > $toDate)) {
    $filterMessage = 'Choose valid dates, with the start date on or before the end date.';
    $fromDate = '';
    $toDate = '';
}
if ($userFilter !== '' && !ctype_digit($userFilter)) {
    $userFilter = '';
}

$eventOptions = [];
foreach ($entries as $entry) {
    $eventName = (string) ($entry['event'] ?? 'unknown');
    $eventOptions[$eventName] = $eventName;
}
natcasesort($eventOptions);

$filteredEntries = array_values(array_filter($entries, static function (array $entry) use ($fromDate, $toDate, $eventFilter, $userFilter, $search): bool {
    $timestamp = (string) ($entry['time_utc'] ?? '');
    $entryDate = preg_match('/^\d{4}-\d{2}-\d{2}/', $timestamp) ? substr($timestamp, 0, 10) : '';
    if ($fromDate !== '' && ($entryDate === '' || $entryDate < $fromDate)) {
        return false;
    }
    if ($toDate !== '' && ($entryDate === '' || $entryDate > $toDate)) {
        return false;
    }
    if ($eventFilter !== '' && (string) ($entry['event'] ?? 'unknown') !== $eventFilter) {
        return false;
    }
    if ($userFilter !== '' && (string) ($entry['user_id'] ?? '') !== $userFilter) {
        return false;
    }
    if ($search !== '') {
        $details = json_encode($entry['details'] ?? [], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $haystack = (string) ($entry['event'] ?? '') . ' ' . (string) ($entry['user_id'] ?? '') . ' ' . (is_string($details) ? $details : '');
        if (stripos($haystack, $search) === false) {
            return false;
        }
    }
    return true;
}));
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
        .filter-form { display: grid; grid-template-columns: repeat(5, minmax(130px, 1fr)) auto; align-items: end; gap: 12px; margin-bottom: 20px; padding: 17px; border: 1px solid #e3edef; border-radius: 10px; background: #f8fcfc; }
        .filter-field label { display: block; margin-bottom: 6px; color: #52666a; font-size: 12px; font-weight: 700; }
        .filter-field input, .filter-field select { width: 100%; min-height: 39px; padding: 8px 10px; border: 1px solid #d5e1e3; border-radius: 7px; background: #fff; color: #34494e; font: 13px Arial, sans-serif; }
        .filter-actions { display: flex; gap: 7px; }
        .filter-actions button, .filter-actions a { min-height: 39px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 12px; border: 0; border-radius: 7px; background: #167b8c; color: #fff; font: 13px Arial, sans-serif; text-decoration: none; cursor: pointer; white-space: nowrap; }
        .filter-actions a { background: #eaf1f2; color: #52666a; }
        .filter-message { margin-bottom: 14px; padding: 11px 13px; border-radius: 8px; background: #fff4dd; color: #76540a; font-size: 13px; }
        .results-note { margin: -8px 0 14px; color: #667a7e; font-size: 13px; }
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
            .filter-form { grid-template-columns: repeat(3, minmax(130px, 1fr)); }
        }
        @media (max-width: 650px) {
            .header { gap: 12px; padding: 15px; }
            .nav { gap: 3px; }
            .nav a { padding: 9px; font-size: 13px; }
            .container { max-width: 94%; margin: 25px auto; }
            .welcome h1 { font-size: 26px; }
            .panel { padding: 19px 14px; }
            .panel-heading h2 { font-size: 19px; }
            .filter-form { grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 13px; }
            .filter-actions { grid-column: 1 / -1; }
            .filter-actions button, .filter-actions a { flex: 1; }
        }
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/navigation.php'; ?>

    <main class="container">
        <section class="welcome">
            <h1>Activity log</h1>
            <p>Review recent account and system activity.</p>
        </section>
        <section class="panel" aria-labelledby="activity-heading">
            <div class="panel-heading">
                <h2 id="activity-heading"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>Recent activity</h2>
                <span class="count"><?= h(count($filteredEntries)) ?> matching events</span>
            </div>
            <?php if ($filterMessage !== ''): ?><div class="filter-message" role="alert"><?= h($filterMessage) ?></div><?php endif; ?>
            <form class="filter-form" method="GET" action="activity_log.php">
                <div class="filter-field"><label for="from_date">From date (UTC)</label><input type="date" id="from_date" name="from_date" value="<?= h($fromDate) ?>"></div>
                <div class="filter-field"><label for="to_date">To date (UTC)</label><input type="date" id="to_date" name="to_date" value="<?= h($toDate) ?>"></div>
                <div class="filter-field"><label for="event">Event type</label><select id="event" name="event"><option value="">All events</option><?php foreach ($eventOptions as $eventOption): ?><option value="<?= h($eventOption) ?>" <?= $eventFilter === $eventOption ? 'selected' : '' ?>><?= h($eventOption) ?></option><?php endforeach; ?></select></div>
                <div class="filter-field"><label for="user_id">User ID</label><input type="number" min="1" id="user_id" name="user_id" value="<?= h($userFilter) ?>" placeholder="Any user"></div>
                <div class="filter-field"><label for="search">Search event/details</label><input type="search" id="search" name="search" value="<?= h($search) ?>" placeholder="e.g. visit.record"></div>
                <div class="filter-actions"><button type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i>Filter</button><a href="activity_log.php">Clear</a></div>
            </form>
            <p class="results-note">Filters search the latest <?= h(count($entries)) ?> readable log entries. Times are shown in UTC.</p>
            <?php if (!$filteredEntries): ?>
                <div class="empty">
                    <i class="fa-regular fa-clipboard" aria-hidden="true"></i>
                    <strong><?= $entries ? 'No matching activity' : 'No activity to show yet' ?></strong>
                    <p><?= $entries ? 'Try changing or clearing the filters.' : 'New account and system events will appear here.' ?></p>
                </div>
            <?php else: ?>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th scope="col">Time (UTC)</th><th scope="col">Event</th><th scope="col">User ID</th><th scope="col">Details</th></tr></thead>
                        <tbody><?php foreach ($filteredEntries as $entry): ?>
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
