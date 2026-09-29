<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: ../auth/login.php');
    exit;
}

$today = date('Y-m-d');
$defaultStart = date('Y-m-01');
$startInput = $_GET['start_date'] ?? $defaultStart;
$endInput = $_GET['end_date'] ?? $today;
$startDate = is_string($startInput) ? trim($startInput) : '';
$endDate = is_string($endInput) ? trim($endInput) : '';
$startParsed = DateTime::createFromFormat('!Y-m-d', $startDate);
$endParsed = DateTime::createFromFormat('!Y-m-d', $endDate);
$rangeValid = $startParsed && $startParsed->format('Y-m-d') === $startDate
    && $endParsed && $endParsed->format('Y-m-d') === $endDate
    && $startDate <= $endDate;
$message = '';

if (!$rangeValid) {
    $message = 'Choose a valid date range with the start date on or before the end date.';
    $startDate = $defaultStart;
    $endDate = $today;
}

$appointmentRows = [];
$visitRows = [];
$statusCounts = [];
$appointmentTotal = 0;
$visitTotal = 0;

if ($rangeValid) {
    $statusStmt = $pdo->prepare(
        'SELECT status, COUNT(*) AS total
         FROM appointments
         WHERE COALESCE(confirmed_date, preferred_date) BETWEEN ? AND ?
         GROUP BY status'
    );
    $statusStmt->execute([$startDate, $endDate]);
    foreach ($statusStmt->fetchAll() as $row) {
        $statusCounts[(string) $row['status']] = (int) $row['total'];
        $appointmentTotal += (int) $row['total'];
    }

    $appointmentStmt = $pdo->prepare(
        "SELECT a.appointment_id,
                COALESCE(a.confirmed_date, a.preferred_date) AS report_date,
                COALESCE(a.confirmed_start_time, a.preferred_start_time) AS report_start,
                COALESCE(a.confirmed_end_time, a.preferred_end_time) AS report_end,
                a.status, a.reason, p.patient_number, p.first_name, p.last_name
         FROM appointments a
         INNER JOIN patients p ON p.patient_id = a.patient_id
         WHERE COALESCE(a.confirmed_date, a.preferred_date) BETWEEN ? AND ?
         ORDER BY report_date DESC, report_start DESC, a.appointment_id DESC"
    );
    $appointmentStmt->execute([$startDate, $endDate]);
    $appointmentRows = $appointmentStmt->fetchAll();

    $visitStmt = $pdo->prepare(
        'SELECT v.visit_id, v.visit_date, v.chief_complaint, v.treatment,
                p.patient_number, p.first_name, p.last_name
         FROM visit_records v
         INNER JOIN patients p ON p.patient_id = v.patient_id
         WHERE v.visit_date BETWEEN ? AND ?
         ORDER BY v.visit_date DESC, v.visit_id DESC'
    );
    $visitStmt->execute([$startDate, $endDate]);
    $visitRows = $visitStmt->fetchAll();
    $visitTotal = count($visitRows);
}

$statusStyles = [
    'pending' => ['label' => 'Pending', 'class' => 'pending'],
    'confirmed' => ['label' => 'Confirmed', 'class' => 'confirmed'],
    'completed' => ['label' => 'Completed', 'class' => 'completed'],
    'cancelled' => ['label' => 'Cancelled', 'class' => 'cancelled'],
    'no_show' => ['label' => 'No-show', 'class' => 'no-show'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clinic reports - The Tooth Lounge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <style>
        :root{--teal:#167b8c;--ink:#20363c;--muted:#63777c}
        body{min-height:100vh;background:#f2f8f9;color:var(--ink);font:15px Arial,sans-serif}
        .topbar{background:#fff;border-bottom:1px solid #dbe8ea}
        .brand{font-weight:700;color:var(--teal);text-decoration:none}
        .report-wrap{max-width:1280px;margin:28px auto;padding:0 20px 40px}
        .report-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:22px}
        .report-heading h1{font-size:30px;font-weight:750;margin:0 0 5px}
        .report-heading p{color:var(--muted);margin:0}
        .filter-card,.report-card,.metric{background:#fff;border:1px solid #e0eaec;border-radius:14px;box-shadow:0 5px 20px rgba(21,62,70,.06)}
        .filter-card{padding:18px;margin-bottom:20px}
        .metric{padding:18px;height:100%}.metric-label{font-size:13px;color:var(--muted)}.metric-value{font-size:28px;font-weight:750;margin-top:5px}
        .report-card{padding:20px;margin-top:20px}.report-card h2{font-size:20px;margin:0 0 15px}
        .status-badge{display:inline-flex;border-radius:999px;padding:5px 10px;font-size:12px;font-weight:700;white-space:nowrap}
        .badge-pending{background:#fff2d6;color:#855900}.badge-confirmed{background:#e0f4e9;color:#17653d}.badge-completed{background:#e7efff;color:#315ca8}.badge-cancelled{background:#fde9e9;color:#a12f36}.badge-no-show{background:#f0e7f7;color:#703b91}
        .table-responsive{border-radius:10px}.table{margin-bottom:0}.table thead th{background:#eaf5f6;color:#31545b;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
        .print-meta{display:none}
        @media(max-width:700px){.report-wrap{padding:0 12px 24px;margin-top:18px}.report-heading{display:block}.report-heading .actions{margin-top:14px}.filter-card .row{row-gap:12px}.report-card{padding:14px}.report-heading h1{font-size:25px}}
        @media print{
            @page{size:landscape;margin:12mm}
            body{background:#fff!important;color:#111;font-size:10pt}
            .topbar,.screen-only,.filter-card,.actions,.no-print{display:none!important}
            .report-wrap{max-width:none;margin:0;padding:0}
            .report-heading{margin-bottom:10px}.report-heading h1{font-size:21pt}
            .print-meta{display:block;margin:4px 0 15px;color:#333}
            .metric,.report-card{box-shadow:none;border:1px solid #bbb;break-inside:avoid}
            .metric{padding:10px}.metric-value{font-size:18pt}
            .report-card{margin-top:12px;padding:12px}
            .table{font-size:8.5pt}.table td,.table th{padding:5px 7px}
            tr{break-inside:avoid}
        }
    </style>
</head>
<body>
<header class="topbar screen-only">
    <nav class="navbar navbar-expand-lg container-fluid px-3 px-lg-5">
        <a class="brand navbar-brand" href="dashboard.php"><i class="fa-solid fa-tooth"></i> The Tooth Lounge</a>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="dashboard.php">Dashboard</a>
            <a class="btn btn-sm btn-outline-secondary" href="appointments.php">Appointments</a>
            <a class="btn btn-sm btn-outline-secondary" href="visit_records.php">Visit records</a>
        </div>
    </nav>
</header>
<main class="report-wrap">
    <div class="report-heading">
        <div>
            <h1>Clinic reports</h1>
            <p>Appointment activity and completed visit records for the selected period.</p>
        </div>
        <div class="actions d-flex gap-2">
            <button type="button" class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Print reports</button>
        </div>
    </div>
    <p class="print-meta">Reporting period: <?= h(date('M j, Y', strtotime($startDate))) ?> – <?= h(date('M j, Y', strtotime($endDate))) ?> | Printed <?= h(date('M j, Y g:i A')) ?></p>

    <?php if ($message !== ''): ?><div class="alert alert-warning" role="alert"><?= h($message) ?></div><?php endif; ?>

    <form class="filter-card screen-only" method="GET">
        <div class="row align-items-end g-3">
            <div class="col-12 col-md-4"><label class="form-label" for="start_date">Start date</label><input class="form-control" type="date" id="start_date" name="start_date" value="<?= h($startDate) ?>" required></div>
            <div class="col-12 col-md-4"><label class="form-label" for="end_date">End date</label><input class="form-control" type="date" id="end_date" name="end_date" value="<?= h($endDate) ?>" required></div>
            <div class="col-12 col-md-4 d-flex gap-2"><button class="btn btn-primary" type="submit">Apply range</button><a class="btn btn-outline-secondary" href="reports.php">This month</a></div>
        </div>
    </form>

    <section class="row g-3" aria-label="Report summary">
        <div class="col-6 col-lg-3"><article class="metric"><div class="metric-label">Appointments</div><div class="metric-value"><?= h($appointmentTotal) ?></div></article></div>
        <div class="col-6 col-lg-3"><article class="metric"><div class="metric-label">Completed visits</div><div class="metric-value"><?= h($visitTotal) ?></div></article></div>
        <div class="col-6 col-lg-3"><article class="metric"><div class="metric-label">Pending</div><div class="metric-value"><?= h($statusCounts['pending'] ?? 0) ?></div></article></div>
        <div class="col-6 col-lg-3"><article class="metric"><div class="metric-label">Confirmed</div><div class="metric-value"><?= h($statusCounts['confirmed'] ?? 0) ?></div></article></div>
    </section>

    <section class="report-card" aria-labelledby="appointment-report-title">
        <h2 id="appointment-report-title"><i class="fa-solid fa-calendar-check text-primary"></i> Appointment report</h2>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <?php foreach ($statusStyles as $statusKey => $style): ?>
                <span class="status-badge badge-<?= h($style['class']) ?>"><?= h($style['label']) ?>: <?= h($statusCounts[$statusKey] ?? 0) ?></span>
            <?php endforeach; ?>
        </div>
        <?php if (!$appointmentRows): ?>
            <p class="text-secondary mb-0">No appointments were found in this date range.</p>
        <?php else: ?>
            <div class="table-responsive"><table class="table table-hover align-middle">
                <thead><tr><th>Date</th><th>Time</th><th>Patient</th><th>Patient No.</th><th>Reason</th><th>Status</th></tr></thead>
                <tbody><?php foreach ($appointmentRows as $row): $statusKey = strtolower((string) $row['status']); $style = $statusStyles[$statusKey] ?? ['label' => ucwords(str_replace('_', ' ', $statusKey)), 'class' => 'pending']; ?>
                    <tr>
                        <td><?= h(date('M j, Y', strtotime($row['report_date']))) ?></td>
                        <td><?= h(date('g:i A', strtotime($row['report_start'])) . ' - ' . date('g:i A', strtotime($row['report_end']))) ?></td>
                        <td><?= h($row['first_name'] . ' ' . $row['last_name']) ?></td>
                        <td><?= h($row['patient_number']) ?></td>
                        <td><?= h($row['reason']) ?></td>
                        <td><span class="status-badge badge-<?= h($style['class']) ?>"><?= h($style['label']) ?></span></td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>

    <section class="report-card" aria-labelledby="visit-report-title">
        <h2 id="visit-report-title"><i class="fa-solid fa-file-medical text-primary"></i> Visit report</h2>
        <?php if (!$visitRows): ?>
            <p class="text-secondary mb-0">No visit records were found in this date range.</p>
        <?php else: ?>
            <div class="table-responsive"><table class="table table-hover align-middle">
                <thead><tr><th>Visit date</th><th>Patient</th><th>Patient No.</th><th>Chief complaint</th><th>Treatment</th></tr></thead>
                <tbody><?php foreach ($visitRows as $row): ?>
                    <tr>
                        <td><?= h(date('M j, Y', strtotime($row['visit_date']))) ?></td>
                        <td><?= h($row['first_name'] . ' ' . $row['last_name']) ?></td>
                        <td><?= h($row['patient_number']) ?></td>
                        <td><?= h($row['chief_complaint']) ?></td>
                        <td><?= h($row['treatment']) ?></td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
