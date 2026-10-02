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

/*
|--------------------------------------------------------------------------
| Get Appointments
|--------------------------------------------------------------------------
*/

$statusOptions = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];
$statusInput = $_GET['status'] ?? 'all';
$statusFilter = is_string($statusInput) && in_array($statusInput, $statusOptions, true) ? $statusInput : 'all';
$dateInput = $_GET['date'] ?? '';
$dateFilter = is_string($dateInput) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateInput) ? $dateInput : '';
$where = ['patient_id = ?'];
$params = [$patientId];
if ($statusFilter !== 'all') {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
}
if ($dateFilter !== '') {
    $where[] = 'COALESCE(confirmed_date, preferred_date) = ?';
    $params[] = $dateFilter;
}

$stmt = $pdo->prepare(
    "SELECT
        appointment_id,
        preferred_date,
        preferred_start_time,
        preferred_end_time,
        created_at,
        confirmed_date,
        confirmed_start_time,
        confirmed_end_time,
        reason,
        status
     FROM appointments
     WHERE " . implode(' AND ', $where) . "
     ORDER BY
        CASE status
            WHEN 'pending' THEN 1
            WHEN 'confirmed' THEN 2
            WHEN 'completed' THEN 3
            WHEN 'cancelled' THEN 4
            ELSE 5
        END,
        COALESCE(confirmed_date, preferred_date) DESC"
);

$stmt->execute($params);

$appointments = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Appointments - The Tooth Lounge</title>

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
            padding: 30px;

            background: rgba(255, 255, 255, 0.96);

            border-radius: 15px;

            box-shadow:
                0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .panel-header {
            display: flex;
            align-items: center;
            gap: 15px;

            margin-bottom: 25px;
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

        .appointment-grid {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 18px;
        }

        .appointment-card {
            padding: 20px;

            border: 1px solid #e0e0e0;

            border-radius: 12px;

            background: white;

            transition: 0.2s;
        }

        .appointment-card:hover {
            background: #f1fbfc;

            border-color: #168ca0;

            transform: translateY(-2px);
        }

        .appointment-top {
            display: flex;

            justify-content: space-between;
            align-items: center;

            margin-bottom: 18px;
        }

        .appointment-title {
            color: #167b8c;
            font-size: 17px;
            font-weight: bold;
        }

        .status {
            display: inline-block;

            padding: 6px 10px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;

            text-transform: capitalize;
        }

        .status-pending {
            background: #fff4d6;
            color: #9a6a00;
        }

        .status-confirmed {
            background: #e5f7f0;
            color: #267653;
        }

        .status-completed {
            background: #e6f0ff;
            color: #3567a8;
        }

        .status-cancelled {
            background: #fdecec;
            color: #a33a3a;
        }

        .status-no_show { background:#f1e9f8; color:#703b91; }

        .appointment-filters {
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
            gap:12px;
            align-items:end;
            margin-bottom:20px;
            padding:18px;
            background:#fff;
            border:1px solid #e3ecee;
            border-radius:14px;
            box-shadow:0 6px 20px rgba(0,0,0,.08);
        }
        .appointment-filters label { display:block; margin-bottom:6px; font-weight:600; font-size:13px; }
        .appointment-filters .form-control,.appointment-filters .form-select { min-height:44px; }
        .appointment-grid { grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr)); }

        .detail {
            display: flex;

            gap: 10px;

            margin-bottom: 10px;

            color: #555;

            font-size: 14px;
        }

        .detail i {
            width: 18px;
            color: #167b8c;
        }

        .reason {
            margin-top: 15px;

            padding-top: 15px;

            border-top: 1px solid #eee;

            color: #777;

            font-size: 13px;

            line-height: 1.5;
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

            padding: 12px 20px;

            background: #167b8c;

            color: white;

            border-radius: 8px;

            text-decoration: none;

            font-size: 14px;

            transition: 0.2s;
        }

        .button:hover {
            background: #126c79;
            transform: translateY(-1px);
        }

        @media (max-width: 950px) {

            .header {
                padding: 12px 20px;
            }

            .appointment-grid {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 650px) {

            body { background-attachment:scroll; }
            .header { padding:12px 14px; }
            .appointment-filters { grid-template-columns:1fr; padding:14px; }
            .panel { padding:18px 14px; }
            .appointment-card { padding:16px; }
            .detail { flex-wrap:wrap; }

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

            .appointment-top {
                align-items: flex-start;
                flex-direction: column;
                gap: 10px;
            }

        }

    </style>

</head>

<body>
<?php require __DIR__ . '/../includes/navigation.php'; ?>

<main class="container">

    <section class="welcome">

        <h1>
            My Appointments
        </h1>

        <p>
            View your dental appointment requests and schedules.
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
                <i class="fa-solid fa-calendar-check"></i>
            </div>

            <div>

                <h2>
                    Appointment History
                </h2>

                <p>
                    Your pending, confirmed, completed, and cancelled appointments.
                </p>

            </div>

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
                <input class="form-control" type="date" id="date" name="date" value="<?= h($dateFilter) ?>">
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
                <a class="btn btn-outline-secondary" href="appointments.php">Clear</a>
            </div>
        </form>

        <?php if (empty($appointments)): ?>

            <div class="empty">

                <i class="fa-regular fa-calendar-xmark"></i>

                <h3>
                    No Appointments
                </h3>

                <p>
                    You currently have no appointment records.
                </p>

                <a
                    href="request.php"
                    class="button"
                >
                    <i class="fa-solid fa-calendar-plus"></i>
                    Request Appointment
                </a>

            </div>

        <?php else: ?>

            <div class="appointment-grid">

                <?php foreach ($appointments as $appointment): ?>

                    <?php
                    $status = strtolower(
                        $appointment['status'] ?? ''
                    );

                    $statusClass = 'status-' . $status;
                    ?>

                    <div class="appointment-card">

                        <div class="appointment-top">

                        <div class="appointment-title">
                            Appointment #<?= htmlspecialchars(
                                $appointment['appointment_id']
                            ) ?>
                        </div>

                            <span
                                class="status <?= htmlspecialchars($statusClass) ?>"
                            >
                                <?= htmlspecialchars(
                                    ucwords(str_replace('_', ' ', $status))
                                ) ?>
                            </span>

                        </div>

                        <?php if (!empty($appointment['created_at'])): ?>
                            <div class="detail">
                                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                                <strong>Requested on:</strong>
                                <?= h(date('M j, Y g:i A', strtotime($appointment['created_at']))) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (
                            $status === 'confirmed' &&
                            !empty($appointment['confirmed_date'])
                        ): ?>

                            <div class="detail">

                                <i class="fa-solid fa-calendar-day"></i>

                                <strong>Confirmed Date:</strong>

                                <?= htmlspecialchars(
                                    $appointment['confirmed_date']
                                ) ?>

                            </div>

                            <div class="detail">

                                <i class="fa-solid fa-clock"></i>

                                <strong>Confirmed Time:</strong>

                                <?= !empty(
                                    $appointment['confirmed_start_time']
                                )
                                    ? htmlspecialchars(
                                        date(
                                            'g:i A',
                                            strtotime(
                                                $appointment['confirmed_start_time']
                                            )
                                        )
                                    )
                                    : 'Not assigned'
                                ?>

                                <?php if (
                                    !empty(
                                        $appointment['confirmed_end_time']
                                    )
                                ): ?>

                                    -
                                    <?= htmlspecialchars(
                                        date(
                                            'g:i A',
                                            strtotime(
                                                $appointment['confirmed_end_time']
                                            )
                                        )
                                    ) ?>

                                <?php endif; ?>

                            </div>

                        <?php else: ?>

                            <div class="detail">

                                <i class="fa-solid fa-calendar-day"></i>

                                <strong>Preferred Date:</strong>

                                <?= htmlspecialchars(
                                    $appointment['preferred_date']
                                    ?? 'Not specified'
                                ) ?>

                            </div>

                            <div class="detail">

                                <i class="fa-solid fa-clock"></i>

                                <strong>Preferred Time:</strong>

                                <?= !empty(
                                    $appointment['preferred_start_time']
                                )
                                    ? htmlspecialchars(
                                        date(
                                            'g:i A',
                                            strtotime(
                                                $appointment['preferred_start_time']
                                            )
                                        )
                                    )
                                    : 'Not specified'
                                ?>

                                <?php if (
                                    !empty(
                                        $appointment['preferred_end_time']
                                    )
                                ): ?>

                                    -
                                    <?= htmlspecialchars(
                                        date(
                                            'g:i A',
                                            strtotime(
                                                $appointment['preferred_end_time']
                                            )
                                        )
                                    ) ?>

                                <?php endif; ?>

                            </div>

                        <?php endif; ?>

                        <div class="reason">

                            <strong>
                                <i class="fa-solid fa-comment-medical"></i>
                                Reason:
                            </strong>

                            <?= htmlspecialchars(
                                $appointment['reason']
                                ?? 'Not provided'
                            ) ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
