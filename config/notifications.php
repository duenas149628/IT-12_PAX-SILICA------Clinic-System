<?php

function create_notification(PDO $pdo, int $userId, ?int $appointmentId, string $type, string $title, string $body): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO notifications
            (recipient_user_id, appointment_id, notification_type, title, body)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $appointmentId, $type, $title, $body]);
}

function notify_staff(PDO $pdo, ?int $appointmentId, string $type, string $title, string $body): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO notifications
            (recipient_user_id, appointment_id, notification_type, title, body)
         SELECT user_id, ?, ?, ?, ? FROM users WHERE role = 'staff'"
    );
    $stmt->execute([$appointmentId, $type, $title, $body]);
}

function notify_patient_of_appointment(PDO $pdo, int $appointmentId): void
{
    $stmt = $pdo->prepare(
        'SELECT p.user_id, a.status, a.confirmed_date,
                a.confirmed_start_time, a.confirmed_end_time
         FROM appointments a
         INNER JOIN patients p ON p.patient_id = a.patient_id
         WHERE a.appointment_id = ?'
    );
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch();
    if (!$appointment || empty($appointment['user_id'])) {
        return;
    }

    $status = strtolower((string) $appointment['status']);
    $label = ucwords(str_replace('_', ' ', $status));
    $body = 'Your appointment status is now ' . $label . '.';
    if ($status === 'confirmed' && !empty($appointment['confirmed_date'])) {
        $body = 'Your appointment is scheduled for '
            . date('M j, Y', strtotime($appointment['confirmed_date']));
        if (!empty($appointment['confirmed_start_time'])) {
            $body .= ' at ' . date('g:i A', strtotime($appointment['confirmed_start_time']));
        }
        $body .= '.';
    }

    create_notification(
        $pdo,
        (int) $appointment['user_id'],
        $appointmentId,
        'appointment.' . preg_replace('/[^a-z_]/', '', $status),
        'Appointment updated',
        $body
    );
}
