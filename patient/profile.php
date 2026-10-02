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
| Get Patient Information
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT
        patient_id,
        patient_number,
        first_name,
        last_name,
        birth_date,
        sex,
        contact_number,
        address
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
| Handle Profile Update
|--------------------------------------------------------------------------
*/

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $firstName = request_string($_POST, 'first_name');
    $lastName = request_string($_POST, 'last_name');
    $birthDate = request_string($_POST, 'birth_date');
    $sex = request_string($_POST, 'sex');
    $contactNumber = request_string($_POST, 'contact_number');
    $address = request_string($_POST, 'address');
    $patient['first_name'] = $firstName;
    $patient['last_name'] = $lastName;
    $patient['birth_date'] = $birthDate;
    $patient['sex'] = $sex;
    $patient['contact_number'] = $contactNumber;
    $patient['address'] = $address;

    $birthDateValue = $birthDate !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate) : null;
    $birthDateValid = $birthDate === '' || (
        $birthDateValue !== false
        && $birthDateValue !== null
        && $birthDateValue->format('Y-m-d') === $birthDate
        && $birthDateValue <= new DateTimeImmutable('today')
    );

    if ($firstName === '' || $lastName === '') {

        $message = 'First name and last name are required.';
        $messageType = 'error';

    } elseif (
        strlen($firstName) > 50
        || strlen($lastName) > 50
        || strlen($firstName . ' ' . $lastName) > 100
        || strlen($contactNumber) > 20
        || strlen($address) > 255
        || !in_array($sex, ['', 'Male', 'Female'], true)
        || !$birthDateValid
    ) {

        $message = 'Check the name, birth date, sex, contact number, and address values.';
        $messageType = 'error';

    } else {

        $stmt = $pdo->prepare(
            "UPDATE patients
             SET
                first_name = ?,
                last_name = ?,
                birth_date = ?,
                sex = ?,
                contact_number = ?,
                address = ?
             WHERE patient_id = ?"
        );

        $stmt->execute([
            $firstName,
            $lastName,
            $birthDate !== '' ? $birthDate : null,
            $sex !== '' ? $sex : null,
            $contactNumber !== '' ? $contactNumber : null,
            $address !== '' ? $address : null,
            $patientId
        ]);

        audit_log('patient.profile.updated', ['patient_id' => (int) $patientId]);

        $_SESSION['name'] =
            $firstName . ' ' . $lastName;

        header('Location: profile.php?updated=1');
        exit;
    }
}

if (isset($_GET['updated'])) {

    $message = 'Profile updated successfully.';
    $messageType = 'success';

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

    <title>My Profile - The Tooth Lounge</title>

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
            width: 1000px;
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

        .message {
            padding: 13px 16px;

            margin-bottom: 20px;

            border-radius: 9px;

            font-size: 14px;
        }

        .success {
            background: #e8f8f0;
            border: 1px solid #b8e8d0;
            color: #287a53;
        }

        .error {
            background: #fdecec;
            border: 1px solid #f0bcbc;
            color: #a33a3a;
        }

        .patient-id-box {
            display: flex;

            align-items: center;

            gap: 12px;

            padding: 15px;

            margin-bottom: 25px;

            background: #f1fbfc;

            border: 1px solid #d7eef1;

            border-radius: 10px;
        }

        .patient-id-box i {
            color: #167b8c;
            font-size: 20px;
        }

        .patient-id-box span {
            color: #777;
            font-size: 12px;

            display: block;

            margin-bottom: 3px;
        }

        .patient-id-box strong {
            color: #167b8c;
            font-size: 15px;
        }

        .form-grid {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full {
            grid-column: span 2;
        }

        label {
            margin-bottom: 7px;

            color: #555;

            font-size: 13px;

            font-weight: 600;
        }

        label i {
            margin-right: 5px;
            color: #167b8c;
        }

        input,
        select,
        textarea {
            width: 100%;

            padding: 12px 13px;

            border: 1px solid #d5d5d5;

            border-radius: 8px;

            background: white;

            color: #333;

            font-family: Arial, sans-serif;

            font-size: 14px;

            outline: none;

            transition: 0.2s;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: #167b8c;

            box-shadow:
                0 0 0 3px
                rgba(22, 123, 140, 0.10);
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .actions {
            display: flex;

            gap: 12px;

            margin-top: 25px;

            padding-top: 20px;

            border-top: 1px solid #eeeeee;
        }

        .button {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            gap: 8px;

            padding: 12px 20px;

            border: none;

            border-radius: 8px;

            text-decoration: none;

            font-size: 14px;

            cursor: pointer;

            transition: 0.2s;
        }

        .save-button {
            background: #167b8c;
            color: white;
        }

        .save-button:hover {
            background: #126c79;
            transform: translateY(-1px);
        }

        .back-button {
            background: #eef3f4;
            color: #555;
        }

        .back-button:hover {
            background: #e1eaec;
            color: #167b8c;
        }

        @media (max-width: 950px) {

            .header {
                padding: 12px 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: span 1;
            }

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

            .actions {
                flex-direction: column;
            }

            .button {
                width: 100%;
            }

        }

    </style>

</head>

<body>
<?php require __DIR__ . '/../includes/navigation.php'; ?>

<main class="container">

    <section class="welcome">

        <h1>
            My Profile
        </h1>

        <p>
            Manage your personal information at The Tooth Lounge.
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
                <i class="fa-solid fa-user-pen"></i>
            </div>

            <div>

                <h2>
                    Personal Information
                </h2>

                <p>
                    Keep your patient information up to date.
                </p>

            </div>

        </div>

        <?php if ($message): ?>

            <div class="message <?= htmlspecialchars($messageType) ?>">

                <i class="fa-solid
                    <?= $messageType === 'success'
                        ? 'fa-circle-check'
                        : 'fa-circle-exclamation'
                    ?>">
                </i>

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>

        <div class="patient-id-box">

            <i class="fa-solid fa-id-card"></i>

            <div>

                <span>
                    Patient Number
                </span>

                <strong>
                    <?= htmlspecialchars($patientNumber) ?>
                </strong>

            </div>

        </div>

        <form method="POST">
<?= csrf_field() ?>

            <div class="form-grid">

                <div class="form-group">

                    <label for="first_name">
                        <i class="fa-solid fa-user"></i>
                        First Name
                    </label>

                    <input
                        type="text"
                        id="first_name"
                        name="first_name"
                        maxlength="50"
                        value="<?= htmlspecialchars(
                            $patient['first_name'] ?? ''
                        ) ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="last_name">
                        <i class="fa-solid fa-user"></i>
                        Last Name
                    </label>

                    <input
                        type="text"
                        id="last_name"
                        name="last_name"
                        maxlength="50"
                        value="<?= htmlspecialchars(
                            $patient['last_name'] ?? ''
                        ) ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="birth_date">
                        <i class="fa-solid fa-cake-candles"></i>
                        Birth Date
                    </label>

                    <input
                        type="date"
                        id="birth_date"
                        name="birth_date"
                        max="<?= h(date('Y-m-d')) ?>"
                        value="<?= htmlspecialchars(
                            $patient['birth_date'] ?? ''
                        ) ?>"
                    >

                </div>

                <div class="form-group">

                    <label for="sex">
                        <i class="fa-solid fa-venus-mars"></i>
                        Sex
                    </label>

                    <select
                        id="sex"
                        name="sex"
                    >

                        <option value="">
                            Select Sex
                        </option>

                        <option
                            value="Male"
                            <?= ($patient['sex'] ?? '') === 'Male'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Male
                        </option>

                        <option
                            value="Female"
                            <?= ($patient['sex'] ?? '') === 'Female'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Female
                        </option>

                    </select>

                </div>

                <div class="form-group full">

                    <label for="contact_number">
                        <i class="fa-solid fa-phone"></i>
                        Contact Number
                    </label>

                    <input
                        type="text"
                        id="contact_number"
                        name="contact_number"
                        maxlength="20"
                        value="<?= htmlspecialchars(
                            $patient['contact_number'] ?? ''
                        ) ?>"
                        placeholder="Enter your contact number"
                    >

                </div>

                <div class="form-group full">

                    <label for="address">
                        <i class="fa-solid fa-location-dot"></i>
                        Address
                    </label>

                    <textarea
                        id="address"
                        name="address"
                        maxlength="255"
                        placeholder="Enter your complete address"
                    ><?= htmlspecialchars(
                        $patient['address'] ?? ''
                    ) ?></textarea>

                </div>

            </div>

            <div class="actions">

                <button
                    type="submit"
                    class="button save-button"
                >

                    <i class="fa-solid fa-floppy-disk"></i>

                    Save Changes

                </button>

                <a
                    href="dashboard.php"
                    class="button back-button"
                >

                    <i class="fa-solid fa-arrow-left"></i>

                    Back to Dashboard

                </a>

            </div>

        </form>

    </section>

</main>

</body>

</html>
