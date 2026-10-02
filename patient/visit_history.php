<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../auth/login.php');
    exit;
}

$patientName = $_SESSION['name'] ?? 'Patient';

/*
|--------------------------------------------------------------------------
| Get Patient
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT patient_id, patient_number
     FROM patients
     WHERE user_id = ?"
);

$stmt->execute([$_SESSION['user_id']]);

$patient = $stmt->fetch();

if (!$patient) {
    die('Patient record not found.');
}

$patientId = $patient['patient_id'];
$patientNumber = $patient['patient_number'] ?? 'Not assigned';

$visitDate = request_string($_GET, 'visit_date');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $visitDate)
    || !checkdate((int) substr($visitDate, 5, 2), (int) substr($visitDate, 8, 2), (int) substr($visitDate, 0, 4))) {
    $visitDate = '';
}

/*
|--------------------------------------------------------------------------
| Get Visit History
|--------------------------------------------------------------------------
*/

$visitSql = "SELECT
        visit_records.visit_id,
        visit_records.visit_date,
        visit_records.chief_complaint,
        visit_records.findings,
        visit_records.treatment,
        visit_records.notes,
        visit_records.follow_up
     FROM visit_records
     WHERE visit_records.patient_id = ?";
$visitParams = [$patientId];
if ($visitDate !== '') {
    $visitSql .= ' AND visit_records.visit_date = ?';
    $visitParams[] = $visitDate;
}
$visitSql .= ' ORDER BY visit_records.visit_date DESC, visit_records.visit_id DESC';
$stmt = $pdo->prepare($visitSql);

$stmt->execute($visitParams);

$visits = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Visit History - The Tooth Lounge</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            color: #333;

            background:
                linear-gradient(
                    rgba(0, 135, 155, 0.78),
                    rgba(20, 85, 150, 0.78)
                ),
                url("https://images.unsplash.com/photo-1606811971618-4486d14f3f99?auto=format&fit=crop&w=1920&q=80");

            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }

        .header {
            min-height: 75px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 12px 35px;

            background: rgba(255, 255, 255, 0.96);

            box-shadow:
                0 3px 15px rgba(0, 0, 0, 0.15);
        }

        .logo {
            display: flex;
            align-items: center;

            gap: 12px;

            color: #167b8c;

            font-size: 21px;
            font-weight: bold;
        }

        .logo-icon {
            width: 43px;
            height: 43px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #e6f7fa;

            border-radius: 50%;

            color: #167b8c;

            font-size: 21px;
        }

        .nav {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .nav a {
            display: flex;
            align-items: center;

            gap: 7px;

            padding: 10px 13px;

            border-radius: 8px;

            color: #555;

            text-decoration: none;

            font-size: 14px;

            transition: 0.2s;
        }

        .nav a:hover {
            background: #e6f7fa;
            color: #167b8c;
        }

        .logout {
            background: #167b8c !important;
            color: white !important;
            margin-left: 5px;
        }

        .logout:hover {
            background: #126c79 !important;
        }

        .container {
            width: 1100px;
            max-width: 92%;
            margin: 40px auto;
        }

        .welcome {
            margin-bottom: 25px;
            color: white;
        }

        .welcome h1 {
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .welcome p {
            font-size: 16px;
            opacity: 0.9;
        }

        .patient-number {
            display: inline-block;

            margin-top: 10px;

            padding: 7px 12px;

            background: rgba(255, 255, 255, 0.18);

            border: 1px solid rgba(255, 255, 255, 0.3);

            border-radius: 20px;

            font-size: 13px;
        }

        .panel {
            padding: 24px;

            background: rgba(255, 255, 255, 0.96);

            border-radius: 15px;

            box-shadow:
                0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .panel-header {
            display: flex;

            align-items: center;

            gap: 15px;

            margin-bottom: 18px;
        }

        .panel-icon {
            width: 55px;
            height: 55px;

            display: flex;

            align-items: center;
            justify-content: center;

            background: #e6f7fa;

            color: #167b8c;

            border-radius: 14px;

            font-size: 23px;
        }

        .panel-header h2 {
            color: #167b8c;
            font-size: 22px;
        }

        .panel-header p {
            margin-top: 5px;
            color: #777;
            font-size: 13px;
        }

        .visit-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .history-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(250px, .75fr);
            align-items: start;
            gap: 18px;
        }

        .history-list { min-width: 0; }
        .history-title { margin: 0 0 12px; color: #167b8c; font-size: 16px; }

        .date-filter {
            display: flex;
            align-items: end;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 14px;
            padding: 11px;
            border-radius: 10px;
            background: #f5fafb;
        }

        .date-filter label { display: block; margin-bottom: 5px; color: #687a7e; font-size: 12px; font-weight: bold; }
        .date-filter input { min-height: 38px; padding: 8px 10px; border: 1px solid #d8e4e6; border-radius: 7px; color: #45575b; font: 13px Arial, sans-serif; }
        .date-filter button, .clear-filter { min-height: 38px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 11px; border: 0; border-radius: 7px; background: #167b8c; color: white; font-size: 12px; font-weight: bold; text-decoration: none; cursor: pointer; }
        .clear-filter { background: #e8eff0; color: #52666a; }
        .date-filter button:hover { background: #126c79; }

        .latest-card {
            padding: 17px;
            border: 1px solid #e0e9eb;
            border-radius: 12px;
            background: #f9fcfc;
        }

        .latest-label { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 12px; padding: 6px 9px; border-radius: 16px; background: #e6f7fa; color: #167b8c; font-size: 11px; font-weight: bold; }
        .latest-date { margin-bottom: 12px; color: #167b8c; font-size: 17px; font-weight: bold; }
        .latest-card .record { margin-bottom: 12px; }
        .latest-card .record:last-child { margin-bottom: 0; }

        .visit-card {
            padding: 16px;

            background: white;

            border: 1px solid #e0e0e0;

            border-radius: 12px;

            transition: 0.2s;
        }

        .visit-card:hover {
            background: #f1fbfc;

            border-color: #168ca0;

            transform: translateY(-2px);
        }

        .visit-header {
            display: flex;

            align-items: center;
            justify-content: space-between;

            gap: 10px;

            margin-bottom: 12px;

            padding-bottom: 10px;

            border-bottom: 1px solid #eeeeee;
        }

        .visit-date {
            color: #167b8c;

            font-size: 17px;

            font-weight: bold;
        }

        .visit-date i {
            margin-right: 7px;
        }

        .visit-id {
            padding: 5px 9px;

            background: #e6f7fa;

            color: #167b8c;

            border-radius: 15px;

            font-size: 11px;
        }

        .record {
            margin-bottom: 10px;
        }

        .record:last-child {
            margin-bottom: 0;
        }

        .record-label {
            margin-bottom: 5px;

            color: #555;

            font-size: 13px;

            font-weight: bold;
        }

        .record-label i {
            margin-right: 5px;
            color: #167b8c;
        }

        .record-value {
            color: #777;

            font-size: 13px;

            line-height: 1.5;

            white-space: pre-wrap;
        }

        .empty {
            padding: 40px 20px;

            text-align: center;

            border: 1px dashed #cfdfe2;

            border-radius: 12px;

            color: #777;
        }

        .empty i {
            margin-bottom: 15px;

            color: #167b8c;

            font-size: 40px;
        }

        .empty h3 {
            margin-bottom: 8px;
            color: #555;
        }

        .button {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            gap: 8px;

            margin-top: 20px;

            padding: 5px 15px;

            background: #167b8c;

            color: white;

            border-radius: 8px;

            text-decoration: none;

            font-size: 14px;

            transition: 0.2s;
        }

        .button:hover {
            background: #0a4242;
            transform: translateY(-1px);
        }

        @media (max-width: 950px) {

            .header {
                padding: 12px 20px;
            }

            .history-layout { grid-template-columns: minmax(0, 1.2fr) minmax(220px, .8fr); }

        }

        @media (max-width: 650px) {

            .header {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }

            .nav {
                flex-wrap: wrap;
                justify-content: center;
            }

            .container {
                max-width: 94%;
                margin: 25px auto;
            }

            .welcome h1 {
                font-size: 26px;
            }

            .panel {
                padding: 20px;
            }

            .visit-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .history-layout { grid-template-columns: 1fr; }

        }

    </style>

</head>

<body>
<?php require __DIR__ . '/../includes/navigation.php'; ?>

<main class="container">

    <section class="welcome">

        <h1>
            Visit History
        </h1>

        <p>
            View your previous completed dental visit records.
        </p>

        <span class="patient-number">

            <i class="fa-solid fa-id-card"></i>

            Patient No:
            <?= htmlspecialchars($patientNumber) ?>

        </span>

    </section>

    <section class="panel">

        <div class="panel-header">

            <div class="panel-icon">
                <i class="fa-solid fa-file-medical"></i>
            </div>

            <div>

                <h2>
                    Dental Visit Records
                </h2>

                <p>
                    Your recorded dental visits and treatments.
                </p>

            </div>

        </div>

        <div class="history-layout">
            <div class="history-list">
                <h3 class="history-title">Current visit records <span class="count"><?= count($visits) ?></span></h3>
                <form class="date-filter" method="GET">
                    <div>
                        <label for="visit-date">Filter by visit date</label>
                        <input type="date" id="visit-date" name="visit_date" value="<?= h($visitDate) ?>">
                    </div>
                    <button type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i>Filter</button>
                    <?php if ($visitDate !== ''): ?>
                        <a class="clear-filter" href="visit_history.php"><i class="fa-solid fa-xmark" aria-hidden="true"></i>Clear</a>
                    <?php endif; ?>
                </form>

                <?php if (!$visits): ?>
                    <div class="empty">
                        <i class="fa-solid fa-folder-open"></i>
                        <h3>No Visit Records</h3>
                        <p><?= $visitDate !== '' ? 'There are no records for that date.' : 'You currently have no completed visit records.' ?></p>
                    </div>
                <?php else: ?>
                    <div class="visit-grid">
                        <?php foreach ($visits as $visit): ?>
                            <article class="visit-card">
                                <div class="visit-header">
                                    <div class="visit-date"><i class="fa-solid fa-calendar-check"></i> <?= h(date('M d, Y', strtotime($visit['visit_date']))) ?></div>
                                    <span class="visit-id">Visit #<?= h($visit['visit_id']) ?></span>
                                </div>
                                <div class="record">
                                    <div class="record-label"><i class="fa-solid fa-comment-medical"></i>Chief Complaint</div>
                                    <div class="record-value"><?= h($visit['chief_complaint'] ?: 'Not recorded') ?></div>
                                </div>
                                <div class="record">
                                    <div class="record-label"><i class="fa-solid fa-magnifying-glass"></i>Findings</div>
                                    <div class="record-value"><?= h($visit['findings'] ?: 'Not recorded') ?></div>
                                </div>
                                <div class="record">
                                    <div class="record-label"><i class="fa-solid fa-tooth"></i>Treatment</div>
                                    <div class="record-value"><?= h($visit['treatment'] ?: 'Not recorded') ?></div>
                                </div>
                                <div class="record">
                                    <div class="record-label"><i class="fa-solid fa-note-sticky"></i>Notes</div>
                                    <div class="record-value"><?= h($visit['notes'] ?: 'Not recorded') ?></div>
                                </div>
                                <div class="record">
                                    <div class="record-label"><i class="fa-solid fa-calendar-plus"></i>Follow-Up</div>
                                    <div class="record-value"><?= h($visit['follow_up'] ?: 'Not recorded') ?></div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <aside class="latest-card">
                <span class="latest-label"><i class="fa-solid fa-sparkles" aria-hidden="true"></i>Latest matching visit</span>
                <?php if ($visits): ?>
                    <?php $latestVisit = $visits[0]; ?>
                    <div class="latest-date"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> <?= h(date('M d, Y', strtotime($latestVisit['visit_date']))) ?></div>
                    <span class="visit-id">Visit #<?= h($latestVisit['visit_id']) ?></span>
                    <div class="record">
                        <div class="record-label"><i class="fa-solid fa-comment-medical"></i>Chief Complaint</div>
                        <div class="record-value"><?= h($latestVisit['chief_complaint'] ?: 'Not recorded') ?></div>
                    </div>
                    <div class="record">
                        <div class="record-label"><i class="fa-solid fa-tooth"></i>Treatment</div>
                        <div class="record-value"><?= h($latestVisit['treatment'] ?: 'Not recorded') ?></div>
                    </div>
                <?php else: ?>
                    <div class="empty"><i class="fa-regular fa-calendar"></i><p>No latest visit is available for this date.</p></div>
                <?php endif; ?>
            </aside>
        </div>

    </section>

</main>

</body>

</html>
