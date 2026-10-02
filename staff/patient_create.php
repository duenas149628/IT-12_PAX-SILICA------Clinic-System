<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/patients.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: ../auth/login.php');
    exit;
}

header('Cache-Control: private, no-store, max-age=0');
$message = '';
$createdAccount = $_SESSION['created_patient_credentials'] ?? null;
unset($_SESSION['created_patient_credentials']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $firstName = request_string($_POST, 'first_name');
    $lastName = request_string($_POST, 'last_name');
    $email = request_string($_POST, 'email');
    $birthDate = request_string($_POST, 'birth_date');
    $sex = request_string($_POST, 'sex');
    $contactNumber = request_string($_POST, 'contact_number');
    $address = request_string($_POST, 'address');
    $today = new DateTimeImmutable('today');
    $birthDateValue = $birthDate !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate) : null;
    $birthDateValid = $birthDate === '' || (
        $birthDateValue !== false
        && $birthDateValue !== null
        && $birthDateValue->format('Y-m-d') === $birthDate
        && $birthDateValue <= $today
    );

    if ($firstName === '' || $lastName === '' || $email === '') {
        $message = 'First name, last name, and email are required.';
    } elseif (
        strlen($firstName) > 50
        || strlen($lastName) > 50
        || strlen($firstName . ' ' . $lastName) > 100
        || strlen($email) > 254
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($contactNumber) > 20
        || strlen($address) > 255
        || !in_array($sex, ['', 'Male', 'Female'], true)
        || !$birthDateValid
    ) {
        $message = 'Check the name, email, birth date, sex, contact number, and address values.';
    } else {
        $exists = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
        $exists->execute([$email]);
        if ($exists->fetch()) {
            $message = 'An account with this email already exists.';
        } else {
            $temporaryPassword = STAFF_ISSUED_TEMPORARY_PASSWORD;
            try {
                $account = create_patient_account(
                    $pdo,
                    $firstName,
                    $lastName,
                    $email,
                    $temporaryPassword,
                    true,
                    [
                        'birth_date' => $birthDate !== '' ? $birthDate : null,
                        'sex' => $sex !== '' ? $sex : null,
                        'contact_number' => $contactNumber !== '' ? $contactNumber : null,
                        'address' => $address !== '' ? $address : null,
                    ]
                );
                audit_log('patient.account.created_by_staff', [
                    'new_user_id' => $account['user_id'],
                    'patient_id' => $account['patient_id'],
                ]);
                $_SESSION['created_patient_credentials'] = [
                    'email' => $email,
                    'patient_number' => $account['patient_number'],
                    'temporary_password' => $temporaryPassword,
                ];
                header('Location: patient_create.php?created=1');
                exit;
            } catch (PDOException $error) {
                if ($error->getCode() === '23000') {
                    $message = 'An account with this email already exists.';
                } else {
                    error_log('Staff patient registration failed: ' . $error->getMessage());
                    $message = 'Unable to create the patient account right now. Please try again.';
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register Patient - The Tooth Lounge</title>
    <style>
        * { box-sizing: border-box; }
        body { min-height:100vh; margin:0; color:#263b40; font-family:Arial,Helvetica,sans-serif; background:linear-gradient(135deg,#e7f5f6,#f6fafb); }
        main { width:min(920px,94%); margin:34px auto; }
        .heading { margin-bottom:20px; color:#167b8c; }
        .heading h1 { margin:0 0 7px; font-size:30px; }
        .heading p { margin:0; color:#60777c; }
        .card { padding:26px; background:#fff; border:1px solid #e0ebed; border-radius:16px; box-shadow:0 12px 32px #123f4a17; }
        .grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
        .field { display:flex; flex-direction:column; gap:7px; }
        .field.full { grid-column:1/-1; }
        label { color:#40545a; font-size:13px; font-weight:700; }
        input,select,textarea { width:100%; min-height:44px; padding:10px 12px; border:1px solid #cad9dc; border-radius:8px; font:14px Arial,sans-serif; }
        textarea { min-height:90px; resize:vertical; }
        .actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:22px; }
        .button { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:44px; padding:10px 16px; border:1px solid #167b8c; border-radius:8px; background:#167b8c; color:#fff; font-weight:700; text-decoration:none; cursor:pointer; }
        .button.secondary { background:#fff; color:#167b8c; }
        .message { margin-bottom:18px; padding:13px 15px; border-radius:9px; background:#fff1e8; color:#8b451f; }
        .success { background:#e7f7ed; color:#236b43; }
        .credentials { overflow-wrap:anywhere; font-family:Consolas,monospace; }
        .hint { margin:14px 0 0; color:#697c81; font-size:13px; line-height:1.5; }
        @media(max-width:620px) { main { margin:24px auto; } .grid { grid-template-columns:1fr; } .field.full { grid-column:auto; } .card { padding:19px; } }
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/navigation.php'; ?>
<main>
    <header class="heading">
        <h1>Register Patient</h1>
        <p>Create the patient record and login together.</p>
    </header>
    <section class="card">
        <?php if ($createdAccount): ?>
            <div class="message success" role="status">
                <strong>Patient account created.</strong><br>
                Patient number: <span class="credentials"><?= h($createdAccount['patient_number']) ?></span><br>
                Login email: <span class="credentials"><?= h($createdAccount['email']) ?></span><br>
                Temporary password: <span class="credentials"><?= h($createdAccount['temporary_password']) ?></span><br>
                Give the temporary password to the patient privately. The system will require them to replace it before accessing records.
            </div>
        <?php endif; ?>
        <?php if ($message !== ''): ?><div class="message" role="alert"><?= h($message) ?></div><?php endif; ?>
        <form method="POST" autocomplete="off">
            <?= csrf_field() ?>
            <div class="grid">
                <div class="field"><label for="first_name">First name</label><input id="first_name" name="first_name" maxlength="50" autocomplete="given-name" required></div>
                <div class="field"><label for="last_name">Last name</label><input id="last_name" name="last_name" maxlength="50" autocomplete="family-name" required></div>
                <div class="field"><label for="email">Email/login</label><input id="email" name="email" type="email" maxlength="254" autocomplete="email" required></div>
                <div class="field"><label for="birth_date">Birth date</label><input id="birth_date" name="birth_date" type="date" max="<?= h(date('Y-m-d')) ?>"></div>
                <div class="field"><label for="sex">Sex</label><select id="sex" name="sex"><option value="">Select</option><option value="Female">Female</option><option value="Male">Male</option></select></div>
                <div class="field"><label for="contact_number">Contact number</label><input id="contact_number" name="contact_number" maxlength="20" autocomplete="tel"></div>
                <div class="field full"><label for="address">Address</label><textarea id="address" name="address" maxlength="255" autocomplete="street-address"></textarea></div>
            </div>
            <p class="hint">The shared temporary password, 1234, will be shown after submission. Give it to the patient privately; they must change it before viewing appointment or visit information.</p>
            <div class="actions">
                <button class="button" type="submit">Create patient account</button>
                <a class="button secondary" href="patients.php">Back to patient records</a>
            </div>
        </form>
    </section>
</main>
</body>
</html>
