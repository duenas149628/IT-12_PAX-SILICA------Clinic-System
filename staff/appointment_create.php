<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: ../auth/login.php');
    exit;
}

$patients = $pdo->query(
    'SELECT patient_id, patient_number, first_name, last_name
     FROM patients
     ORDER BY last_name, first_name, patient_id'
)->fetchAll();
$message = '';
$selectedPatientId = request_string($_GET, 'patient_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $selectedPatientId = request_string($_POST, 'patient_id');
    $preferredDate = request_string($_POST, 'preferred_date');
    $startTime = request_string($_POST, 'preferred_start_time');
    $endTime = request_string($_POST, 'preferred_end_time');
    $reason = request_string($_POST, 'reason');
    $patientDate = DateTimeImmutable::createFromFormat('!Y-m-d', $preferredDate);
    $dateValid = $patientDate !== false && $patientDate->format('Y-m-d') === $preferredDate;
    $timePattern = '/^(?:[01]\d|2[0-3]):[0-5]\d$/';
    $patientExists = is_string($selectedPatientId) && ctype_digit($selectedPatientId);

    if ($patientExists) {
        $patientCheck = $pdo->prepare('SELECT patient_id FROM patients WHERE patient_id = ?');
        $patientCheck->execute([(int) $selectedPatientId]);
        $patientExists = (bool) $patientCheck->fetchColumn();
    }

    if (!$patientExists) {
        $message = 'Select a valid patient.';
    } elseif (!$dateValid || $patientDate < new DateTimeImmutable('today')) {
        $message = 'Select a valid date that is today or later.';
    } elseif (!preg_match($timePattern, $startTime) || !preg_match($timePattern, $endTime) || $endTime <= $startTime) {
        $message = 'Enter a valid time range with an end time after the start time.';
    } elseif ($reason === '' || strlen($reason) > 255) {
        $message = 'Enter a reason of 1 to 255 characters.';
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO appointments
                    (patient_id, preferred_date, preferred_start_time, preferred_end_time, reason, status)
                 VALUES (?, ?, ?, ?, ?, 'pending')"
            );
            $stmt->execute([
                (int) $selectedPatientId,
                $preferredDate,
                $startTime,
                $endTime,
                $reason,
            ]);
            $appointmentId = (int) $pdo->lastInsertId();
            audit_log('appointment.request.created_by_staff', [
                'appointment_id' => $appointmentId,
                'patient_id' => (int) $selectedPatientId,
            ]);
            header('Location: appointment_view.php?id=' . $appointmentId);
            exit;
        } catch (PDOException $error) {
            error_log('Staff appointment entry failed: ' . $error->getMessage());
            $message = 'Unable to save the appointment request right now. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Enter Appointment Request - The Tooth Lounge</title>
    <style>
        * { box-sizing:border-box; }
        body { min-height:100vh; margin:0; color:#263b40; font-family:Arial,Helvetica,sans-serif; background:linear-gradient(135deg,#e7f5f6,#f6fafb); }
        main { width:min(780px,94%); margin:34px auto; }
        .heading { margin-bottom:20px; color:#167b8c; }
        .heading h1 { margin:0 0 7px; font-size:30px; }
        .heading p { margin:0; color:#60777c; }
        .card { padding:26px; background:#fff; border:1px solid #e0ebed; border-radius:16px; box-shadow:0 12px 32px #123f4a17; }
        .grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
        .field { display:flex; flex-direction:column; gap:7px; }
        .field.full { grid-column:1/-1; }
        label { color:#40545a; font-size:13px; font-weight:700; }
        input,select,textarea { width:100%; min-height:44px; padding:10px 12px; border:1px solid #cad9dc; border-radius:8px; font:14px Arial,sans-serif; }
        textarea { min-height:100px; resize:vertical; }
        .actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:22px; }
        .button { display:inline-flex; align-items:center; justify-content:center; min-height:44px; padding:10px 16px; border:1px solid #167b8c; border-radius:8px; background:#167b8c; color:#fff; font-weight:700; text-decoration:none; cursor:pointer; }
        .button.secondary { background:#fff; color:#167b8c; }
        .message { margin-bottom:18px; padding:13px 15px; border-radius:9px; background:#fff1e8; color:#8b451f; }
        .hint { margin:14px 0 0; color:#697c81; font-size:13px; line-height:1.5; }
        @media(max-width:620px) { main { margin:24px auto; } .grid { grid-template-columns:1fr; } .field.full { grid-column:auto; } .card { padding:19px; } }
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/navigation.php'; ?>
<main>
    <header class="heading">
        <h1>Enter Appointment Request</h1>
        <p>Record a request received by phone, text, Facebook, or another offline channel.</p>
    </header>
    <section class="card">
        <?php if ($message !== ''): ?><div class="message" role="alert"><?= h($message) ?></div><?php endif; ?>
        <?php if (!$patients): ?>
            <p>No patient records exist yet. <a href="patient_create.php">Register a patient</a> before entering an appointment.</p>
        <?php else: ?>
            <form method="POST">
                <?= csrf_field() ?>
                <div class="grid">
                    <div class="field full">
                        <label for="patient_id">Patient</label>
                        <select id="patient_id" name="patient_id" required>
                            <option value="">Select patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?= h($patient['patient_id']) ?>" <?= (string) $patient['patient_id'] === $selectedPatientId ? 'selected' : '' ?>>
                                    <?= h($patient['last_name'] . ', ' . $patient['first_name'] . ' — ' . $patient['patient_number']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field"><label for="preferred_date">Preferred date</label><input id="preferred_date" name="preferred_date" type="date" min="<?= h(date('Y-m-d')) ?>" value="<?= h(request_string($_POST, 'preferred_date')) ?>" required></div>
                    <div class="field"><label for="preferred_start_time">Preferred start time</label><input id="preferred_start_time" name="preferred_start_time" type="time" value="<?= h(request_string($_POST, 'preferred_start_time')) ?>" required></div>
                    <div class="field"><label for="preferred_end_time">Preferred end time</label><input id="preferred_end_time" name="preferred_end_time" type="time" value="<?= h(request_string($_POST, 'preferred_end_time')) ?>" required></div>
                    <div class="field full"><label for="reason">Reason</label><textarea id="reason" name="reason" maxlength="255" required><?= h(request_string($_POST, 'reason')) ?></textarea></div>
                </div>
                <p class="hint">The request will be saved as pending. Review it from Appointments and confirm an available time using the normal scheduling flow.</p>
                <div class="actions">
                    <button class="button" type="submit">Save pending request</button>
                    <a class="button secondary" href="appointments.php">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
