<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../auth/login.php');
    exit;
}

$statusOptions = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];
$statusInput = $_GET['status'] ?? 'all';
$statusFilter = is_string($statusInput) && in_array($statusInput, $statusOptions, true)
    ? $statusInput
    : 'all';
$todayView = ($_GET['view'] ?? '') === 'today';
$dateInput = $_GET['date'] ?? '';
$dateFilter = is_string($dateInput) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateInput)
    ? $dateInput
    : '';
$searchInput = $_GET['search'] ?? '';
$search = is_string($searchInput) ? trim($searchInput) : '';
$search = substr($search, 0, 100);

$where = [];
$params = [];
if ($todayView) {
    $where[] = "appointments.status = 'confirmed'";
    $where[] = 'appointments.confirmed_date = ?';
    $params[] = date('Y-m-d');
    $statusFilter = 'confirmed';
    $dateFilter = date('Y-m-d');
} else {
    if ($statusFilter !== 'all') {
        $where[] = 'appointments.status = ?';
        $params[] = $statusFilter;
    }
    if ($dateFilter !== '') {
        $where[] = 'COALESCE(appointments.confirmed_date, appointments.preferred_date) = ?';
        $params[] = $dateFilter;
    }
}
if ($search !== '') {
    $where[] = '(patients.first_name LIKE ? OR patients.last_name LIKE ? OR patients.patient_number LIKE ?)';
    $needle = '%' . addcslashes($search, '%_\\') . '%';
    array_push($params, $needle, $needle, $needle);
}

$sql = "SELECT
        appointments.appointment_id,
        appointments.preferred_date,
        appointments.preferred_start_time,
        appointments.preferred_end_time,
        appointments.confirmed_date,
        appointments.confirmed_start_time,
        appointments.confirmed_end_time,
        appointments.reason,
        appointments.status,
        patients.patient_number,
        patients.first_name,
        patients.last_name,
        patients.contact_number
     FROM appointments
     INNER JOIN patients
        ON appointments.patient_id = patients.patient_id
     " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
     ORDER BY COALESCE(appointments.confirmed_date, appointments.preferred_date) ASC,
              COALESCE(appointments.confirmed_start_time, appointments.preferred_start_time) ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$requests = $stmt->fetchAll();
$pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'")->fetchColumn();
$todayCountStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE status = 'confirmed' AND confirmed_date = ?");
$todayCountStmt->execute([date('Y-m-d')]);
$todayCount = (int) $todayCountStmt->fetchColumn();

$monthInput = $_GET['month'] ?? '';
$monthValue = is_string($monthInput) && preg_match('/^\d{4}-\d{2}$/', $monthInput)
    ? $monthInput
    : ($dateFilter !== '' ? substr($dateFilter, 0, 7) : date('Y-m'));
$calendarMonth = DateTime::createFromFormat('!Y-m', $monthValue);
if (!$calendarMonth || $calendarMonth->format('Y-m') !== $monthValue) {
    $calendarMonth = new DateTime('first day of this month');
}
$monthStart = $calendarMonth->format('Y-m-01');
$nextMonthStart = (clone $calendarMonth)->modify('first day of next month')->format('Y-m-01');
$calendarStmt = $pdo->prepare(
    "SELECT appointments.appointment_id, appointments.confirmed_date AS event_date,
            appointments.confirmed_start_time AS starts_at, appointments.confirmed_end_time AS ends_at,
            patients.first_name, patients.last_name, patients.patient_number
     FROM appointments
     INNER JOIN patients ON appointments.patient_id = patients.patient_id
     WHERE appointments.status = 'confirmed'
       AND appointments.confirmed_date >= ? AND appointments.confirmed_date < ?
     ORDER BY appointments.confirmed_date, appointments.confirmed_start_time"
);
$calendarStmt->execute([$monthStart, $nextMonthStart]);
$calendarEvents = $calendarStmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Appointment Requests - The Tooth Lounge</title>

    <!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous"
    >

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;

            font-family: Arial, Helvetica, sans-serif;

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

        /* HEADER */

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

        /* LOGO */

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

        /* NAVIGATION */

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

        .nav a:hover,
        .nav a.active {
            background: #e6f7fa;
            color: #167b8c;
        }

        .nav a.active {
            font-weight: 600;
        }

        .logout {
            background: #167b8c !important;
            color: white !important;

            margin-left: 5px;
        }

        .logout:hover {
            background: #126c79 !important;
        }

        /* MAIN */

        .container {
            width: 1200px;
            max-width: 94%;

            margin: 40px auto;
        }

        /* PAGE TITLE */

        .page-title {
            color: white;

            margin-bottom: 25px;
        }

        .page-title h1 {
            display: flex;
            align-items: center;

            gap: 12px;

            font-size: 30px;

            margin-bottom: 8px;
        }

        .page-title p {
            font-size: 15px;
            opacity: 0.9;
        }

        /* SUMMARY */

        .summary {
            display: flex;
            align-items: center;
            justify-content: space-between;

            margin-bottom: 20px;

            padding: 20px 25px;

            background: rgba(255, 255, 255, 0.96);

            border-radius: 14px;

            box-shadow:
                0 8px 25px rgba(0, 0, 0, 0.14);
        }

        .summary-left {
            display: flex;
            align-items: center;

            gap: 12px;

            color: #555;

            font-size: 15px;
        }

        .summary-left i {
            color: #167b8c;

            font-size: 20px;
        }

        .count {
            min-width: 32px;
            height: 32px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            padding: 0 10px;

            background: #167b8c;

            color: white;

            border-radius: 20px;

            font-size: 13px;

            font-weight: bold;
        }

        /* TABLE */

        .table-container {
            background: rgba(255, 255, 255, 0.97);

            border-radius: 15px;

            overflow-x: auto;

            box-shadow:
                0 10px 30px rgba(0, 0, 0, 0.15);
        }

        table {
            width: 100%;

            min-width: 1050px;

            border-collapse: collapse;
        }

        th {
            padding: 16px;

            background: #e6f7fa;

            color: #167b8c;

            text-align: left;

            font-size: 13px;

            font-weight: 600;

            border-bottom: 1px solid #d6edef;
        }

        td {
            padding: 16px;

            border-bottom: 1px solid #eeeeee;

            color: #555;

            font-size: 14px;

            vertical-align: middle;
        }

        tbody tr {
            transition: 0.2s;
        }

        tbody tr:hover {
            background: #f7fcfd;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* PATIENT */

        .patient-name {
            display: flex;
            align-items: center;

            gap: 10px;

            color: #333;

            font-weight: 600;
        }

        .patient-icon {
            width: 35px;
            height: 35px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #e6f7fa;

            color: #167b8c;

            border-radius: 50%;

            font-size: 14px;
        }

        /* STATUS */

        .status {
            display: inline-flex;
            align-items: center;

            gap: 6px;

            padding: 6px 10px;

            background: #fff4d6;

            color: #9a6a00;

            border-radius: 20px;

            font-size: 12px;

            font-weight: 600;
        }

        .status-pending { background: #fff4d6; color: #8a5a00; }
        .status-confirmed { background: #e1f5ec; color: #176b45; }
        .status-completed { background: #e7efff; color: #315ca8; }
        .status-cancelled { background: #fde8e8; color: #a12f36; }
        .status-no_show { background: #f1e9f8; color: #703b91; }

        .appointment-filters, .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin: 18px 0;
        }
        .appointment-filters {
            align-items: end;
            padding: 20px;
            background: rgba(255,255,255,.97);
            border-radius: 14px;
            box-shadow: 0 8px 25px rgba(0,0,0,.12);
        }
        .appointment-filters label { display:block; margin-bottom:6px; font-size:13px; font-weight:600; color:#40545a; }
        .appointment-filters .form-control, .appointment-filters .form-select { min-height:44px; }
        .quick-stat {
            display:flex; align-items:center; justify-content:space-between; gap:12px;
            padding:16px 20px; color:#27454c; text-decoration:none;
            background:rgba(255,255,255,.97); border-radius:14px;
            box-shadow:0 6px 20px rgba(0,0,0,.1);
        }
        .quick-stat:hover { color:#116c7b; transform:translateY(-1px); }
        .quick-stat strong { font-size:24px; }
        .table-container { -webkit-overflow-scrolling:touch; }
        tbody tr:hover { background:#f4fbfc; }

        .schedule-panel {
            margin:18px 0 24px; padding:22px;
            background:rgba(255,255,255,.97); border-radius:16px;
            box-shadow:0 10px 30px rgba(0,0,0,.12);
        }
        .calendar-toolbar,.agenda-heading { display:flex; align-items:center; justify-content:space-between; gap:14px; }
        .calendar-toolbar h2 { margin:0 0 5px; color:#174b55; font-size:22px; }
        .calendar-toolbar p,.agenda-heading p { margin:0; color:#66777b; font-size:13px; }
        .calendar-navigation { display:flex; gap:7px; }
        .schedule-layout { display:grid; grid-template-columns:minmax(0,1.25fr) minmax(310px,.75fr); gap:22px; margin-top:20px; }
        .calendar-weekdays,.calendar-grid { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); }
        .calendar-weekdays span { padding:8px 3px; color:#64777b; text-align:center; font-size:12px; font-weight:700; }
        .calendar-day { display:flex; flex-direction:column; align-items:flex-start; gap:6px; min-height:72px; padding:9px; border:1px solid #e4edef; background:#fff; color:#29484f; text-align:left; }
        .calendar-day:not(:disabled) { cursor:pointer; }
        .calendar-day:not(:disabled):hover { background:#f0fafb; border-color:#8ccbd3; }
        .calendar-day:disabled { background:#f8fafb; opacity:.55; }
        .calendar-day.selected { background:#e8f7f9; border:2px solid #168ca0; padding:8px; }
        .calendar-day.is-today .calendar-day-number { color:#087c8e; font-weight:800; }
        .calendar-day-number { font-size:14px; }
        .calendar-event-count { padding:3px 6px; border-radius:20px; background:#dff3ec; color:#176b45; font-size:10px; font-weight:700; }
        .day-agenda { min-width:0; padding:15px; border:1px solid #e4edef; border-radius:12px; background:#fbfdfd; }
        .agenda-heading { align-items:flex-start; margin-bottom:13px; }
        .agenda-heading h3 { margin:0 0 4px; color:#174b55; font-size:17px; }
        .timeline { max-height:520px; overflow:auto; padding:4px 2px; }
        .timeline-track { position:relative; min-width:240px; margin-left:58px; border-left:1px solid #dbe7e9; }
        .timeline-hour { position:absolute; left:-58px; width:50px; height:1px; border-top:1px solid #e4edef; color:#728387; font-size:10px; transform:translateY(-50%); }
        .schedule-event { position:absolute; left:8px; right:8px; z-index:1; display:flex; flex-direction:column; gap:2px; overflow:hidden; padding:6px 9px; border-left:4px solid #17846f; border-radius:6px; background:#e4f5ee; color:#184f40; text-decoration:none; font-size:12px; box-shadow:0 2px 5px rgba(0,0,0,.07); }
        .schedule-event:hover { z-index:2; color:#103d31; background:#d5efe4; }
        .schedule-event span,.schedule-event small { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .schedule-event.has-overlap { border-left-color:#c53d46; background:#ffeded; color:#8f2525; }
        .agenda-empty { margin:0; padding:20px 12px; color:#718084; background:white; border-radius:8px; text-align:center; }

        /* MANAGE BUTTON */

        .button {
            display: inline-flex;
            align-items: center;

            gap: 7px;

            padding: 9px 14px;

            background: #167b8c;

            color: white;

            text-decoration: none;

            border-radius: 7px;

            font-size: 13px;

            font-weight: 500;

            transition: 0.2s;
        }

        .button:hover {
            background: #126c79;

            transform: translateY(-1px);
        }

        /* EMPTY */

        .empty {
            padding: 60px 30px;

            text-align: center;

            background: rgba(255, 255, 255, 0.97);

            border-radius: 15px;

            box-shadow:
                0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .empty-icon {
            width: 70px;
            height: 70px;

            display: flex;
            align-items: center;
            justify-content: center;

            margin: 0 auto 20px;

            background: #e6f7fa;

            color: #167b8c;

            border-radius: 50%;

            font-size: 28px;
        }

        .empty h2 {
            margin-bottom: 8px;

            color: #444;

            font-size: 20px;
        }

        .empty p {
            color: #777;

            font-size: 14px;
        }

        /* BOTTOM NAVIGATION */

        .bottom-navigation {
            display: flex;

            flex-wrap: wrap;

            gap: 10px;

            margin-top: 25px;
        }

        .bottom-navigation a {
            display: inline-flex;
            align-items: center;

            gap: 7px;

            padding: 10px 14px;

            background: rgba(255, 255, 255, 0.95);

            color: #555;

            text-decoration: none;

            border-radius: 8px;

            font-size: 13px;

            box-shadow:
                0 3px 10px rgba(0, 0, 0, 0.08);

            transition: 0.2s;
        }

        .bottom-navigation a:hover {
            color: #167b8c;

            background: white;
        }

        /* RESPONSIVE */

        @media (max-width: 900px) {

            .header {
                flex-direction: column;

                gap: 15px;

                padding: 15px 20px;
            }

            .nav {
                flex-wrap: wrap;

                justify-content: center;
            }

            .container {
                max-width: 94%;
            }
        }

        @media (max-width: 600px) {

            .header { padding:12px 16px; }
            .nav { width:100%; }
            .container { width:100%; max-width:100%; padding:18px 14px; }
            .appointment-filters { padding:14px; grid-template-columns:1fr; }
            .schedule-panel { padding:14px; }
            .calendar-toolbar { align-items:flex-start; flex-direction:column; }
            .schedule-layout { grid-template-columns:1fr; }
            .calendar-day { min-height:58px; padding:6px 4px; }
            .calendar-day.selected { padding:5px 3px; }
            .calendar-event-count { padding:2px 4px; font-size:9px; }
            .day-agenda { padding:12px; }
            .quick-stats { grid-template-columns:1fr 1fr; gap:8px; }
            .quick-stat { padding:12px; font-size:13px; }
            .quick-stat strong { font-size:20px; }

            .page-title h1 {
                font-size: 25px;
            }

            .bottom-navigation {
                flex-direction: column;
            }

            .bottom-navigation a {
                justify-content: center;
            }
        }

    </style>

</head>


<body>


    <!-- HEADER -->

    <header class="header">

        <div class="logo">

            <div class="logo-icon">

                <i class="fa-solid fa-tooth"></i>

            </div>

            The Tooth Lounge

        </div>


        <nav class="nav">

            <a href="dashboard.php">

                <i class="fa-solid fa-gauge-high"></i>

                Dashboard

            </a>


            <a href="patients.php">

                <i class="fa-solid fa-users"></i>

                Patients

            </a>


            <a
                href="appointments.php"
                class="active"
            >

                <i class="fa-solid fa-calendar-check"></i>

                Appointments

            </a>


            <a href="visit_records.php">

                <i class="fa-solid fa-file-medical"></i>

                Records

            </a>

            <a href="reports.php">
                <i class="fa-solid fa-chart-column"></i>
                Reports
            </a>


            <a
                href="../auth/logout.php"
                class="logout"
            >

                <i class="fa-solid fa-right-from-bracket"></i>

                Logout

            </a>

        </nav>

    </header>


    <!-- MAIN -->

    <main class="container">


        <section class="page-title">

            <h1>

                <i class="fa-solid fa-calendar-plus"></i>

                Appointments

            </h1>

            <p>
                Search, filter, and manage patient requests and confirmed visits.
            </p>

        </section>


        <div class="quick-stats">
            <a class="quick-stat" href="appointments.php?status=pending">
                <span><i class="fa-regular fa-clock"></i> Pending requests</span>
                <strong><?= h($pendingCount) ?></strong>
            </a>
            <a class="quick-stat" href="appointments.php?view=today">
                <span><i class="fa-solid fa-calendar-day"></i> Today's appointments</span>
                <strong><?= h($todayCount) ?></strong>
            </a>
        </div>

        <form class="appointment-filters" method="GET">
            <div>
                <label for="status">Status</label>
                <select class="form-select" name="status" id="status">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All statuses</option>
                    <?php foreach ($statusOptions as $option): ?>
                        <option value="<?= h($option) ?>" <?= $statusFilter === $option ? 'selected' : '' ?>><?= h(ucwords(str_replace('_', ' ', $option))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="date">Appointment date</label>
                <input class="form-control" type="date" name="date" id="date" value="<?= h($dateFilter) ?>">
            </div>
            <div>
                <label for="search">Patient or number</label>
                <input class="form-control" type="search" name="search" id="search" maxlength="100" value="<?= h($search) ?>" placeholder="Search patients">
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
                <a class="btn btn-outline-secondary" href="appointments.php">Clear</a>
            </div>
        </form>

        <?php
        $previousMonth = (clone $calendarMonth)->modify('-1 month')->format('Y-m');
        $followingMonth = (clone $calendarMonth)->modify('+1 month')->format('Y-m');
        $currentMonth = date('Y-m');
        ?>
        <section class="schedule-panel" aria-labelledby="calendarTitle">
            <div class="calendar-toolbar">
                <div>
                    <h2 id="calendarTitle">Visual schedule</h2>
                    <p>Confirmed appointments for <?= h($calendarMonth->format('F Y')) ?>. Select a day to view its timeline.</p>
                </div>
                <div class="calendar-navigation">
                    <a class="btn btn-outline-secondary" aria-label="Previous month" href="?month=<?= h($previousMonth) ?>"><i class="fa-solid fa-chevron-left"></i></a>
                    <a class="btn btn-outline-secondary" href="?month=<?= h($currentMonth) ?>">Today</a>
                    <a class="btn btn-outline-secondary" aria-label="Next month" href="?month=<?= h($followingMonth) ?>"><i class="fa-solid fa-chevron-right"></i></a>
                </div>
            </div>
            <div class="schedule-layout">
                <div class="calendar-area">
                    <div class="calendar-weekdays" aria-hidden="true">
                        <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
                    </div>
                    <div class="calendar-grid" id="calendarGrid" role="grid" aria-label="Appointment calendar"></div>
                </div>
                <aside class="day-agenda" aria-live="polite">
                    <div class="agenda-heading">
                        <div><h3 id="selectedDateHeading"></h3><p id="agendaSummary"></p></div>
                        <button class="btn btn-sm btn-outline-primary" type="button" id="applySelectedDate">Filter list</button>
                    </div>
                    <div class="timeline" id="dayTimeline"></div>
                </aside>
            </div>
        </section>
        <script>
            const scheduleMonth = <?= json_encode($calendarMonth->format('Y-m')) ?>;
            const filteredDate = <?= json_encode($dateFilter) ?>;
            const scheduleEvents = <?= json_encode($calendarEvents, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const monthParts = scheduleMonth.split('-').map(Number);
            const calendarYear = monthParts[0];
            const calendarMonthIndex = monthParts[1] - 1;
            const calendarGrid = document.getElementById('calendarGrid');
            const scheduleDateField = document.getElementById('date');
            const selectedDateHeading = document.getElementById('selectedDateHeading');
            const agendaSummary = document.getElementById('agendaSummary');
            const dayTimeline = document.getElementById('dayTimeline');
            const eventsByDate = scheduleEvents.reduce((map, event) => {
                (map[event.event_date] ||= []).push(event);
                return map;
            }, {});
            const toIsoDate = (day) => `${scheduleMonth}-${String(day).padStart(2, '0')}`;
            const now = new Date();
            const todayIso = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
            let selectedIso = filteredDate.startsWith(`${scheduleMonth}-`)
                ? filteredDate
                : scheduleMonth === todayIso.slice(0, 7)
                ? todayIso
                : `${scheduleMonth}-01`;

            function renderCalendar() {
                calendarGrid.replaceChildren();
                const daysInMonth = new Date(calendarYear, calendarMonthIndex + 1, 0).getDate();
                const mondayOffset = (new Date(calendarYear, calendarMonthIndex, 1).getDay() + 6) % 7;
                const cellCount = Math.ceil((mondayOffset + daysInMonth) / 7) * 7;
                for (let cell = 0; cell < cellCount; cell++) {
                    const day = cell - mondayOffset + 1;
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'calendar-day';
                    button.setAttribute('role', 'gridcell');
                    if (day < 1 || day > daysInMonth) {
                        button.disabled = true;
                        button.classList.add('outside-month');
                    } else {
                        const iso = toIsoDate(day);
                        const dayEvents = eventsByDate[iso] || [];
                        button.setAttribute('aria-pressed', String(iso === selectedIso));
                        button.setAttribute('aria-label', `${iso}, ${dayEvents.length} appointments`);
                        button.classList.toggle('selected', iso === selectedIso);
                        button.classList.toggle('is-today', iso === todayIso);
                        const number = document.createElement('span');
                        number.className = 'calendar-day-number';
                        number.textContent = String(day);
                        button.append(number);
                        if (dayEvents.length) {
                            const count = document.createElement('span');
                            count.className = 'calendar-event-count';
                            count.textContent = `${dayEvents.length} ${dayEvents.length === 1 ? 'visit' : 'visits'}`;
                            button.append(count);
                        }
                        button.addEventListener('click', () => {
                            selectedIso = iso;
                            scheduleDateField.value = iso;
                            renderCalendar();
                            renderAgenda();
                        });
                    }
                    calendarGrid.append(button);
                }
            }

            function renderAgenda() {
                const dayEvents = [...(eventsByDate[selectedIso] || [])].sort((a, b) => a.starts_at.localeCompare(b.starts_at));
                selectedDateHeading.textContent = new Date(`${selectedIso}T12:00:00`).toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' });
                agendaSummary.textContent = `${dayEvents.length} confirmed ${dayEvents.length === 1 ? 'appointment' : 'appointments'}`;
                dayTimeline.replaceChildren();
                if (!dayEvents.length) {
                    const empty = document.createElement('p');
                    empty.className = 'agenda-empty';
                    empty.textContent = 'No confirmed appointments for this day.';
                    dayTimeline.append(empty);
                    return;
                }

                const minutesOf = (time) => {
                    const [hours, minutes] = String(time || '00:00').split(':').map(Number);
                    return hours * 60 + minutes;
                };
                const displayTime = (time) => {
                    const [hours, minutes] = String(time || '00:00').split(':').map(Number);
                    return `${hours % 12 || 12}:${String(minutes).padStart(2, '0')} ${hours < 12 ? 'AM' : 'PM'}`;
                };
                const earliestHour = Math.min(8, ...dayEvents.map(event => Math.floor(minutesOf(event.starts_at) / 60)));
                const latestHour = Math.max(18, ...dayEvents.map(event => Math.ceil(minutesOf(event.ends_at) / 60)));
                const startHour = Math.max(0, earliestHour);
                const endHour = Math.min(24, latestHour);
                const track = document.createElement('div');
                track.className = 'timeline-track';
                track.style.height = `${(endHour - startHour) * 60}px`;

                for (let hour = startHour; hour <= endHour; hour++) {
                    const line = document.createElement('div');
                    line.className = 'timeline-hour';
                    line.style.top = `${(hour - startHour) * 60}px`;
                    line.textContent = `${String(hour % 12 || 12).padStart(2, '0')}:00 ${hour < 12 ? 'AM' : 'PM'}`;
                    track.append(line);
                }

                const intervals = dayEvents.map(event => ({ start: minutesOf(event.starts_at), end: minutesOf(event.ends_at) }));
                dayEvents.forEach((event, index) => {
                    const start = minutesOf(event.starts_at);
                    const end = Math.max(start + 30, minutesOf(event.ends_at));
                    const overlaps = intervals.some((other, otherIndex) => otherIndex !== index && start < other.end && end > other.start);
                    const item = document.createElement('a');
                    item.className = `schedule-event${overlaps ? ' has-overlap' : ''}`;
                    item.style.top = `${start - startHour * 60}px`;
                    item.style.height = `${Math.max(44, end - start)}px`;
                    item.href = `appointment_view.php?id=${encodeURIComponent(event.appointment_id)}`;
                    const time = document.createElement('strong');
                    time.textContent = `${displayTime(event.starts_at)}–${displayTime(event.ends_at)}`;
                    const patient = document.createElement('span');
                    patient.textContent = `${event.first_name} ${event.last_name}`;
                    const number = document.createElement('small');
                    number.textContent = `#${event.patient_number}${overlaps ? ' · Schedule overlap' : ''}`;
                    item.append(time, patient, number);
                    track.append(item);
                });
                dayTimeline.append(track);
            }

            document.getElementById('applySelectedDate').addEventListener('click', () => document.querySelector('.appointment-filters').requestSubmit());
            renderCalendar();
            renderAgenda();
        </script>

        <?php if (empty($requests)): ?>


            <div class="empty">

                <div class="empty-icon">

                    <i class="fa-regular fa-calendar-check"></i>

                </div>

                <h2>
                    No Appointments Found
                </h2>

                <p>
                    Try changing the filters, or check again later.
                </p>

            </div>


        <?php else: ?>


            <div class="summary">

                <div class="summary-left">

                    <i class="fa-solid fa-calendar-plus"></i>

                    <span>
                        Appointments matching your filters
                    </span>

                    <span class="count">
                        <?= count($requests) ?>
                    </span>

                </div>

            </div>


            <div class="table-container">

                <table>

                    <thead>

                        <tr>

                            <th>Patient</th>

                            <th>Patient Number</th>

                            <th>Contact</th>

                            <th>Appointment Date</th>

                            <th>Appointment Time</th>

                            <th>Reason</th>

                            <th>Status</th>

                            <th>Action</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach ($requests as $request): ?>

                            <tr>

                                <td>

                                    <div class="patient-name">

                                        <div class="patient-icon">

                                            <i class="fa-solid fa-user"></i>

                                        </div>

                                        <?= htmlspecialchars(
                                            $request['first_name']
                                            . ' '
                                            . $request['last_name']
                                        ) ?>

                                    </div>

                                </td>


                                <td>
                                    <?= htmlspecialchars(
                                        $request['patient_number']
                                    ) ?>
                                </td>


                                <td>
                                    <?= htmlspecialchars(
                                        $request['contact_number']
                                    ) ?>
                                </td>


                                <?php
                                $scheduledDate = $request['status'] === 'confirmed' && !empty($request['confirmed_date']) ? $request['confirmed_date'] : $request['preferred_date'];
                                $scheduledStart = $request['status'] === 'confirmed' && !empty($request['confirmed_start_time']) ? $request['confirmed_start_time'] : $request['preferred_start_time'];
                                $scheduledEnd = $request['status'] === 'confirmed' && !empty($request['confirmed_end_time']) ? $request['confirmed_end_time'] : $request['preferred_end_time'];
                                $rowStatus = strtolower((string) $request['status']);
                                $statusIcons = ['pending' => 'clock', 'confirmed' => 'calendar-check', 'completed' => 'circle-check', 'cancelled' => 'circle-xmark', 'no_show' => 'triangle-exclamation'];
                                ?>
                                <td><?= h(date('M d, Y', strtotime($scheduledDate))) ?></td>

                                <td><?= h(date('g:i A', strtotime($scheduledStart)) . ' - ' . date('g:i A', strtotime($scheduledEnd))) ?></td>


                                <td>

                                    <?= htmlspecialchars(
                                        $request['reason']
                                    ) ?>

                                </td>


                                <td>

                                    <span class="status status-<?= h($rowStatus) ?>">
                                        <i class="fa-solid fa-<?= h($statusIcons[$rowStatus] ?? 'circle-question') ?>"></i>
                                        <?= h(ucwords(str_replace('_', ' ', $rowStatus))) ?>
                                    </span>

                                </td>


                                <td>

                                    <a
                                        href="appointment_view.php?id=<?= h($request['appointment_id']) ?>"
                                        class="button"
                                    >

                                        <i class="fa-solid fa-eye"></i>

                                        Manage

                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


        <?php endif; ?>


        <div class="bottom-navigation">

            <a href="dashboard.php">

                <i class="fa-solid fa-gauge-high"></i>

                Staff Dashboard

            </a>


            <a href="appointments.php">

                <i class="fa-solid fa-calendar-check"></i>

                All Appointments

            </a>


            <a href="patients.php">

                <i class="fa-solid fa-users"></i>

                Patient Records

            </a>


            <a href="visit_records.php">

                <i class="fa-solid fa-file-medical"></i>

                Visit Records

            </a>

        </div>

    </main>


</body>

</html>
