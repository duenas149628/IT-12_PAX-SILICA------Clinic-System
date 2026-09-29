<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/notifications.php';

$reminderDate = date('Y-m-d', strtotime('+1 day'));
$stmt = $pdo->prepare(
    "SELECT a.appointment_id, a.confirmed_date, a.confirmed_start_time,
            p.user_id
     FROM appointments a
     INNER JOIN patients p ON p.patient_id = a.patient_id
     WHERE a.status = 'confirmed' AND a.confirmed_date = ?"
);
$stmt->execute([$reminderDate]);
$appointments = $stmt->fetchAll();
$created = 0;

foreach ($appointments as $appointment) {
    $pdo->beginTransaction();
    try {
        $current = $pdo->prepare("SELECT status, confirmed_date, confirmed_start_time FROM appointments WHERE appointment_id = ? FOR UPDATE");
        $current->execute([$appointment['appointment_id']]);
        $schedule = $current->fetch();
        if (!$schedule || $schedule['status'] !== 'confirmed' || $schedule['confirmed_date'] !== $reminderDate) {
            $pdo->commit();
            continue;
        }

        $claim = $pdo->prepare(
            "INSERT IGNORE INTO appointment_reminders
                (appointment_id, reminder_date, reminder_type)
             VALUES (?, ?, 'day_before')"
        );
        $claim->execute([$appointment['appointment_id'], $schedule['confirmed_date']]);
        $createdReminder = false;
        if ($claim->rowCount() === 1) {
            $body = 'Reminder: your appointment is tomorrow, '
                . date('M j, Y', strtotime($schedule['confirmed_date']));
            if (!empty($schedule['confirmed_start_time'])) {
                $body .= ' at ' . date('g:i A', strtotime($schedule['confirmed_start_time']));
            }
            $body .= '.';
            create_notification(
                $pdo,
                (int) $appointment['user_id'],
                (int) $appointment['appointment_id'],
                'appointment.reminder',
                'Appointment reminder',
                $body
            );
            $createdReminder = true;
        }
        $pdo->commit();
        if ($createdReminder) {
            audit_log('appointment.reminder.created', ['appointment_id' => (int) $appointment['appointment_id']]);
            $created++;
        }
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Appointment reminder failed: ' . $error->getMessage());
        fwrite(STDERR, "Reminder job failed for an appointment.\n");
        exit(1);
    }
}

fwrite(STDOUT, "Created {$created} appointment reminder(s) for {$reminderDate}.\n");
