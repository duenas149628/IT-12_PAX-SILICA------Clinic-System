<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SESSION['role'] !== 'staff') {
    die('Access denied.');
}

$appointment_id = $_GET['id'] ?? null;

if (!$appointment_id || !ctype_digit($appointment_id)) {
    die('Invalid appointment.');
}

$appointment_id = (int) $appointment_id;


/*
|--------------------------------------------------------------------------
| Get Appointment
|--------------------------------------------------------------------------
*/

function getAppointment(PDO $pdo, int $appointment_id): ?array
{
    $stmt = $pdo->prepare(
        "SELECT
            appointments.*,
            patients.patient_number,
            patients.first_name,
            patients.last_name,
            patients.contact_number
         FROM appointments
         INNER JOIN patients
            ON appointments.patient_id = patients.patient_id
         WHERE appointments.appointment_id = ?"
    );

    $stmt->execute([$appointment_id]);

    $appointment = $stmt->fetch();

    return $appointment ?: null;
}


$appointment = getAppointment($pdo, $appointment_id);

if (!$appointment) {
    die('Appointment not found.');
}

$message = '';
$message_type = '';


/*
|--------------------------------------------------------------------------
| Handle Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | ACCEPT PATIENT REQUEST
    |--------------------------------------------------------------------------
    */

    if ($action === 'accept') {

        $confirmed_date = $appointment['preferred_date'];
        $confirmed_start_time = $appointment['preferred_start_time'];
        $confirmed_end_time = $appointment['preferred_end_time'];


        $stmt = $pdo->prepare(
            "SELECT appointment_id
             FROM appointments
             WHERE status = 'confirmed'
             AND confirmed_date = ?
             AND confirmed_start_time < ?
             AND confirmed_end_time > ?
             AND appointment_id != ?"
        );

        $stmt->execute([
            $confirmed_date,
            $confirmed_end_time,
            $confirmed_start_time,
            $appointment_id
        ]);

        $conflict = $stmt->fetch();


        if ($conflict) {

            $message = 'The patient requested time conflicts with another confirmed appointment. Please configure a different schedule.';
            $message_type = 'error';

        } else {

            $stmt = $pdo->prepare(
                "UPDATE appointments
                 SET
                    confirmed_date = ?,
                    confirmed_start_time = ?,
                    confirmed_end_time = ?,
                    status = 'confirmed'
                 WHERE appointment_id = ?"
            );

            $stmt->execute([
                $confirmed_date,
                $confirmed_start_time,
                $confirmed_end_time,
                $appointment_id
            ]);

            $message = 'Appointment accepted successfully.';
            $message_type = 'success';

            $appointment = getAppointment($pdo, $appointment_id);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CONFIRM CUSTOM SCHEDULE
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'confirm') {

        $confirmed_date = $_POST['confirmed_date'] ?? '';
        $confirmed_start_time = $_POST['confirmed_start_time'] ?? '';
        $confirmed_end_time = $_POST['confirmed_end_time'] ?? '';


        if (
            empty($confirmed_date) ||
            empty($confirmed_start_time) ||
            empty($confirmed_end_time)
        ) {

            $message = 'Please complete the confirmed schedule.';
            $message_type = 'error';

        } elseif ($confirmed_start_time >= $confirmed_end_time) {

            $message = 'The confirmed end time must be later than the start time.';
            $message_type = 'error';

        } else {

            $stmt = $pdo->prepare(
                "SELECT appointment_id
                 FROM appointments
                 WHERE status = 'confirmed'
                 AND confirmed_date = ?
                 AND confirmed_start_time < ?
                 AND confirmed_end_time > ?
                 AND appointment_id != ?"
            );

            $stmt->execute([
                $confirmed_date,
                $confirmed_end_time,
                $confirmed_start_time,
                $appointment_id
            ]);

            $conflict = $stmt->fetch();


            if ($conflict) {

                $message = 'The selected time conflicts with another confirmed appointment.';
                $message_type = 'error';

            } else {

                $stmt = $pdo->prepare(
                    "UPDATE appointments
                     SET
                        confirmed_date = ?,
                        confirmed_start_time = ?,
                        confirmed_end_time = ?,
                        status = 'confirmed'
                     WHERE appointment_id = ?"
                );

                $stmt->execute([
                    $confirmed_date,
                    $confirmed_start_time,
                    $confirmed_end_time,
                    $appointment_id
                ]);

                $message = 'Appointment confirmed successfully.';
                $message_type = 'success';

                $appointment = getAppointment($pdo, $appointment_id);
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | RESCHEDULE APPOINTMENT
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'reschedule') {

        $confirmed_date = $_POST['confirmed_date'] ?? '';
        $confirmed_start_time = $_POST['confirmed_start_time'] ?? '';
        $confirmed_end_time = $_POST['confirmed_end_time'] ?? '';


        if (
            empty($confirmed_date) ||
            empty($confirmed_start_time) ||
            empty($confirmed_end_time)
        ) {

            $message = 'Please complete the new appointment schedule.';
            $message_type = 'error';

        } elseif ($confirmed_start_time >= $confirmed_end_time) {

            $message = 'The end time must be later than the start time.';
            $message_type = 'error';

        } else {

            $stmt = $pdo->prepare(
                "SELECT appointment_id
                 FROM appointments
                 WHERE status = 'confirmed'
                 AND confirmed_date = ?
                 AND confirmed_start_time < ?
                 AND confirmed_end_time > ?
                 AND appointment_id != ?"
            );

            $stmt->execute([
                $confirmed_date,
                $confirmed_end_time,
                $confirmed_start_time,
                $appointment_id
            ]);

            $conflict = $stmt->fetch();


            if ($conflict) {

                $message = 'The selected time conflicts with another confirmed appointment.';
                $message_type = 'error';

            } else {

                $stmt = $pdo->prepare(
                    "UPDATE appointments
                     SET
                        confirmed_date = ?,
                        confirmed_start_time = ?,
                        confirmed_end_time = ?,
                        status = 'confirmed'
                     WHERE appointment_id = ?"
                );

                $stmt->execute([
                    $confirmed_date,
                    $confirmed_start_time,
                    $confirmed_end_time,
                    $appointment_id
                ]);

                $message = 'Appointment rescheduled successfully.';
                $message_type = 'success';

                $appointment = getAppointment($pdo, $appointment_id);
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CANCEL APPOINTMENT
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'cancel') {

        $stmt = $pdo->prepare(
            "UPDATE appointments
             SET
                confirmed_date = NULL,
                confirmed_start_time = NULL,
                confirmed_end_time = NULL,
                status = 'cancelled'
             WHERE appointment_id = ?"
        );

        $stmt->execute([$appointment_id]);

        $message = 'Appointment cancelled successfully.';
        $message_type = 'success';

        $appointment = getAppointment($pdo, $appointment_id);
    }


    /*
    |--------------------------------------------------------------------------
    | MARK AS COMPLETED
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'completed') {

        if ($appointment['status'] !== 'confirmed') {

            $message = 'Only confirmed appointments can be marked as completed.';
            $message_type = 'error';

        } else {

            try {

                /*
                |--------------------------------------------------------------------------
                | Start Transaction
                |--------------------------------------------------------------------------
                */

                $pdo->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | Mark Appointment as Completed
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare(
                    "UPDATE appointments
                     SET status = 'completed'
                     WHERE appointment_id = ?"
                );

                $stmt->execute([$appointment_id]);


                /*
                |--------------------------------------------------------------------------
                | Automatically Create Visit Record
                |--------------------------------------------------------------------------
                */

                $checkVisit = $pdo->prepare(
                    "SELECT visit_id
                     FROM visit_records
                     WHERE appointment_id = ?"
                );

                $checkVisit->execute([$appointment_id]);

                $existingVisit = $checkVisit->fetch();


                if (!$existingVisit) {

                    $visit_date = $appointment['confirmed_date'];

                    $createVisit = $pdo->prepare(
                        "INSERT INTO visit_records (
                            patient_id,
                            appointment_id,
                            visit_date,
                            chief_complaint,
                            findings,
                            treatment,
                            notes,
                            follow_up
                        )
                        VALUES (?, ?, ?, '', '', '', '', '')"
                    );

                    $createVisit->execute([
                        $appointment['patient_id'],
                        $appointment_id,
                        $visit_date
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Commit Transaction
                |--------------------------------------------------------------------------
                */

                $pdo->commit();

                $message = 'Appointment completed and visit record created successfully.';
                $message_type = 'success';

                $appointment = getAppointment($pdo, $appointment_id);

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $message = 'The appointment could not be completed. Please try again.';
                $message_type = 'error';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | MARK AS NO-SHOW
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'no_show') {

        if ($appointment['status'] !== 'confirmed') {

            $message = 'Only confirmed appointments can be marked as no-show.';
            $message_type = 'error';

        } else {

            $stmt = $pdo->prepare(
                "UPDATE appointments
                 SET status = 'no_show'
                 WHERE appointment_id = ?"
            );

            $stmt->execute([$appointment_id]);

            $message = 'Appointment marked as no-show.';
            $message_type = 'success';

            $appointment = getAppointment($pdo, $appointment_id);
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Manage Appointment - The Tooth Lounge</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            max-width: 750px;
            margin: 40px auto;
            padding: 20px;
        }

        h1 {
            margin-bottom: 20px;
        }

        .section {
            border: 1px solid #ccc;
            padding: 20px;
            margin-top: 20px;
            border-radius: 8px;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        .button {
            display: inline-block;
            padding: 12px 20px;
            margin-top: 15px;
            background: #333;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        input {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            box-sizing: border-box;
        }

        button {
            padding: 12px 20px;
            margin-top: 20px;
            cursor: pointer;
            border: none;
            border-radius: 5px;
        }

        .accept-button {
            background: #2e7d32;
            color: white;
            width: 100%;
        }

        .confirm-button {
            background: #333;
            color: white;
            width: 100%;
        }

        .reschedule-button {
            background: #555;
            color: white;
            width: 100%;
        }

        .complete-button {
            background: #2e7d32;
            color: white;
            width: 100%;
        }

        .no-show-button {
            background: #777;
            color: white;
            width: 100%;
        }

        .cancel-button {
            background: #b00020;
            color: white;
            width: 100%;
        }

        .message {
            padding: 12px;
            margin-top: 15px;
            border-radius: 5px;
        }

        .success {
            background: #dff0d8;
        }

        .error {
            background: #f2dede;
        }

        .status {
            font-weight: bold;
        }

        a {
            display: inline-block;
            margin-top: 15px;
        }

    </style>

</head>

<body>

    <h1>Manage Appointment</h1>


    <?php if ($message): ?>

        <div class="message <?= htmlspecialchars($message_type) ?>">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <!-- PATIENT INFORMATION -->

    <div class="section">

        <h2>Patient Information</h2>

        <p>
            <strong>Name:</strong>

            <?= htmlspecialchars(
                $appointment['first_name']
                . ' '
                . $appointment['last_name']
            ) ?>
        </p>

        <p>
            <strong>Patient Number:</strong>

            <?= htmlspecialchars(
                $appointment['patient_number']
            ) ?>
        </p>

        <p>
            <strong>Contact:</strong>

            <?= htmlspecialchars(
                $appointment['contact_number'] ?? ''
            ) ?>
        </p>

    </div>


    <!-- PATIENT REQUEST -->

    <div class="section">

        <h2>Patient Request</h2>

        <p>
            <strong>Preferred Date:</strong>

            <?= htmlspecialchars(
                $appointment['preferred_date']
            ) ?>
        </p>

        <p>
            <strong>Preferred Time:</strong>

            <?= htmlspecialchars(
                date(
                    'g:i A',
                    strtotime(
                        $appointment['preferred_start_time']
                    )
                )
            ) ?>

            -

            <?= htmlspecialchars(
                date(
                    'g:i A',
                    strtotime(
                        $appointment['preferred_end_time']
                    )
                )
            ) ?>
        </p>

        <p>
            <strong>Reason:</strong>

            <?= htmlspecialchars(
                $appointment['reason'] ?? ''
            ) ?>
        </p>

        <p>
            <strong>Status:</strong>

            <span class="status">

                <?= htmlspecialchars(
                    ucfirst(
                        str_replace(
                            '_',
                            ' ',
                            $appointment['status']
                        )
                    )
                ) ?>

            </span>

        </p>

    </div>


    <!-- ACCEPT APPOINTMENT -->

    <?php if ($appointment['status'] === 'pending'): ?>

        <div class="section">

            <h2>Accept Appointment</h2>

            <p>
                The patient requested the date and time shown above.
                If the schedule is available, the staff can accept the request directly.
            </p>

            <form
                method="POST"
                onsubmit="return confirm('Accept this appointment using the requested date and time?');"
            >

                <button
                    type="submit"
                    name="action"
                    value="accept"
                    class="accept-button"
                >
                    Accept Appointment
                </button>

            </form>

        </div>

    <?php endif; ?>


    <!-- CUSTOM SCHEDULE / RESCHEDULE -->

    <?php if (
        $appointment['status'] === 'pending' ||
        $appointment['status'] === 'confirmed'
    ): ?>

        <div class="section">

            <?php if ($appointment['status'] === 'pending'): ?>

                <h2>Configure Custom Schedule</h2>

                <p>
                    Use this option if the patient's requested schedule
                    is not suitable and the staff needs to set another time.
                </p>

            <?php else: ?>

                <h2>Reschedule Appointment</h2>

                <p>
                    Change the confirmed date or time if necessary.
                </p>

            <?php endif; ?>


            <form method="POST">

                <label for="confirmed_date">
                    Confirmed Date
                </label>

                <input
                    type="date"
                    id="confirmed_date"
                    name="confirmed_date"
                    value="<?= htmlspecialchars(
                        $appointment['confirmed_date']
                        ?? $appointment['preferred_date']
                    ) ?>"
                    required
                >


                <label for="confirmed_start_time">
                    Confirmed Start Time
                </label>

                <input
                    type="time"
                    id="confirmed_start_time"
                    name="confirmed_start_time"
                    value="<?= htmlspecialchars(
                        $appointment['confirmed_start_time']
                        ?? $appointment['preferred_start_time']
                    ) ?>"
                    required
                >


                <label for="confirmed_end_time">
                    Confirmed End Time
                </label>

                <input
                    type="time"
                    id="confirmed_end_time"
                    name="confirmed_end_time"
                    value="<?= htmlspecialchars(
                        $appointment['confirmed_end_time']
                        ?? $appointment['preferred_end_time']
                    ) ?>"
                    required
                >


                <?php if ($appointment['status'] === 'pending'): ?>

                    <button
                        type="submit"
                        name="action"
                        value="confirm"
                        class="confirm-button"
                    >
                        Confirm Custom Schedule
                    </button>

                <?php else: ?>

                    <button
                        type="submit"
                        name="action"
                        value="reschedule"
                        class="reschedule-button"
                    >
                        Reschedule Appointment
                    </button>

                <?php endif; ?>

            </form>

        </div>

    <?php endif; ?>


    <!-- CONFIRMED SCHEDULE -->

    <?php if ($appointment['confirmed_date']): ?>

        <div class="section">

            <h2>Confirmed Schedule</h2>

            <p>
                <strong>Date:</strong>

                <?= htmlspecialchars(
                    $appointment['confirmed_date']
                ) ?>
            </p>

            <p>
                <strong>Time:</strong>

                <?= htmlspecialchars(
                    date(
                        'g:i A',
                        strtotime(
                            $appointment['confirmed_start_time']
                        )
                    )
                ) ?>

                -

                <?= htmlspecialchars(
                    date(
                        'g:i A',
                        strtotime(
                            $appointment['confirmed_end_time']
                        )
                    )
                ) ?>

            </p>

        </div>

    <?php endif; ?>


    <!-- COMPLETED / NO-SHOW -->

    <?php if ($appointment['status'] === 'confirmed'): ?>

        <div class="section">

            <h2>Appointment Status</h2>

            <form
                method="POST"
                onsubmit="return confirm('Mark this appointment as completed? A visit record will be created automatically.');"
            >

                <button
                    type="submit"
                    name="action"
                    value="completed"
                    class="complete-button"
                >
                    Mark as Completed
                </button>

            </form>


            <form
                method="POST"
                onsubmit="return confirm('Mark this appointment as no-show?');"
            >

                <button
                    type="submit"
                    name="action"
                    value="no_show"
                    class="no-show-button"
                >
                    Mark as No-Show
                </button>

            </form>

        </div>

    <?php endif; ?>


    <!-- VISIT RECORD -->

    <?php if ($appointment['status'] === 'completed'): ?>

        <div class="section">

            <h2>Visit Record</h2>

            <?php

            $visitStmt = $pdo->prepare(
                "SELECT visit_id
                 FROM visit_records
                 WHERE appointment_id = ?"
            );

            $visitStmt->execute([
                $appointment['appointment_id']
            ]);

            $existingVisit = $visitStmt->fetch();

            ?>


            <?php if ($existingVisit): ?>

                <p>
                    A visit record has been created for this completed appointment.
                </p>

                <a
                    href="visit_view.php?id=<?= $existingVisit['visit_id'] ?>"
                    class="button"
                >
                    View Visit Record
                </a>

                <a
                    href="visit_edit.php?id=<?= $existingVisit['visit_id'] ?>"
                    class="button"
                >
                    Edit Visit Record
                </a>

            <?php else: ?>

                <p>
                    No visit record is associated with this appointment.
                </p>

            <?php endif; ?>

        </div>

    <?php endif; ?>


    <!-- CANCEL -->

    <?php if (
        $appointment['status'] !== 'cancelled' &&
        $appointment['status'] !== 'completed' &&
        $appointment['status'] !== 'no_show'
    ): ?>

        <div class="section">

            <h2>Cancel Appointment</h2>

            <p>
                Cancelling this appointment will change its status
                to cancelled.
            </p>

            <form
                method="POST"
                onsubmit="return confirm('Are you sure you want to cancel this appointment?');"
            >

                <button
                    type="submit"
                    name="action"
                    value="cancel"
                    class="cancel-button"
                >
                    Cancel Appointment
                </button>

            </form>

        </div>

    <?php endif; ?>


    <a href="appointments.php">
        Back to Appointments
    </a>

    <br>

    <a href="dashboard.php">
        Back to Staff Dashboard
    </a>

    <br>

    <a href="../auth/logout.php">
        Logout
    </a>

</body>

</html>