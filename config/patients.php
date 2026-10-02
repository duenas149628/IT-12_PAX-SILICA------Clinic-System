<?php

function create_patient_account(
    PDO $pdo,
    string $firstName,
    string $lastName,
    string $email,
    string $password,
    bool $mustChangePassword = false,
    array $profile = []
): array {
    $name = trim($firstName . ' ' . $lastName);
    $pdo->beginTransaction();

    try {
        $userStmt = $pdo->prepare(
            'INSERT INTO users (name, email, password, role, must_change_password)
             VALUES (?, ?, ?, \'patient\', ?)'
        );
        $userStmt->execute([
            $name,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $mustChangePassword ? 1 : 0,
        ]);
        $userId = (int) $pdo->lastInsertId();

        // Use a temporary unique value, then derive the permanent clinic number
        // from the generated patient ID. The transaction hides the temporary value.
        $patientStmt = $pdo->prepare(
            'INSERT INTO patients
                (user_id, patient_number, first_name, last_name, birth_date, sex, contact_number, address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $patientStmt->execute([
            $userId,
            'TMP-' . bin2hex(random_bytes(6)),
            $firstName,
            $lastName,
            $profile['birth_date'] ?? null,
            $profile['sex'] ?? null,
            $profile['contact_number'] ?? null,
            $profile['address'] ?? null,
        ]);
        $patientId = (int) $pdo->lastInsertId();
        $patientNumber = 'TL-' . str_pad((string) $patientId, 5, '0', STR_PAD_LEFT);

        $numberStmt = $pdo->prepare('UPDATE patients SET patient_number = ? WHERE patient_id = ?');
        $numberStmt->execute([$patientNumber, $patientId]);
        $pdo->commit();

        return ['user_id' => $userId, 'patient_id' => $patientId, 'patient_number' => $patientNumber];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
