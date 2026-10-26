<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

define('ROOT', dirname(__DIR__));
$configFile = ROOT . '/config/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "Missing config/config.php. Follow the XAMPP setup instructions first.\n");
    exit(1);
}
$config = require $configFile;
require_once ROOT . '/app/db.php';

function prompt(string $label): string
{
    fwrite(STDOUT, $label);
    $value = fgets(STDIN);
    if ($value === false) {
        throw new RuntimeException('Could not read terminal input.');
    }
    return rtrim($value, "\r\n");
}

try {
    fwrite(STDOUT, "Local administrator email/password recovery.\n");
    fwrite(STDOUT, "Account emails and password input will be visible in this terminal. Keep your screen private.\n\n");

    $pdo = db();
    $admins = $pdo->query(
        "SELECT id, full_name, email, role, active
         FROM users
         WHERE role IN ('super_admin', 'university_admin', 'college_admin')
         ORDER BY FIELD(role, 'super_admin', 'university_admin', 'college_admin'), full_name, id"
    )->fetchAll();
    if (!$admins) {
        throw new RuntimeException('No administrator accounts were found.');
    }

    foreach ($admins as $index => $admin) {
        $status = (int)$admin['active'] ? 'Active' : 'Inactive';
        fwrite(STDOUT, sprintf(
            "%d. %s | %s | %s | %s\n",
            $index + 1,
            ucwords(str_replace('_', ' ', (string)$admin['role'])),
            $admin['full_name'],
            $admin['email'],
            $status
        ));
    }
    fwrite(STDOUT, "0. Exit without changes\n\n");

    $choice = filter_var(prompt('Choose account number: '), FILTER_VALIDATE_INT);
    if ($choice === 0) {
        fwrite(STDOUT, "No changes made.\n");
        exit(0);
    }
    if ($choice === false || $choice < 1 || $choice > count($admins)) {
        throw new RuntimeException('Choose a number from the listed accounts.');
    }

    $selected = $admins[$choice - 1];
    $emailInput = trim(prompt('Correct email address (press Enter to keep current): '));
    $newEmail = $emailInput === '' ? (string)$selected['email'] : strtolower($emailInput);
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL) || strlen($newEmail) > 190) {
        throw new RuntimeException('Enter a valid email address of no more than 190 characters.');
    }

    $reactivate = (int)$selected['active'] === 1;
    if (!$reactivate) {
        $answer = strtolower(trim(prompt('This account is inactive. Reactivate it? Type yes to confirm: ')));
        $reactivate = $answer === 'yes';
    }

    $password = prompt('New password (12+ characters, letters, number, symbol): ');
    $confirmation = prompt('Confirm new password: ');
    if (strlen($password) < 12
        || !preg_match('/[A-Za-z]/', $password)
        || !preg_match('/\d/', $password)
        || !preg_match('/[^A-Za-z0-9]/', $password)
        || !hash_equals($password, $confirmation)) {
        throw new RuntimeException('Password does not meet the requirements or the confirmation differs.');
    }

    $pdo->beginTransaction();
    $find = $pdo->prepare(
        "SELECT id, active FROM users
         WHERE id=? AND role IN ('super_admin', 'university_admin', 'college_admin')
         FOR UPDATE"
    );
    $find->execute([(int)$selected['id']]);
    $target = $find->fetch();
    if (!$target) {
        throw new RuntimeException('The selected administrator account no longer exists.');
    }

    $update = $pdo->prepare(
        'UPDATE users
         SET email=?, password_hash=?, active=?, failed_logins=0, locked_until=NULL
         WHERE id=?'
    );
    $update->execute([
        $newEmail,
        password_hash($password, PASSWORD_DEFAULT),
        $reactivate ? 1 : 0,
        (int)$target['id'],
    ]);
    $pdo->commit();
    fwrite(STDOUT, "Administrator login updated. Email for sign-in: {$newEmail}\n");
    fwrite(STDOUT, "Sign in with this email and the new password you entered.\n");
    if (!$reactivate) {
        fwrite(STDOUT, "The account remains inactive; an active administrator must reactivate it before sign-in.\n");
    }
} catch (PDOException $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ((string)$error->getCode() === '23000') {
        fwrite(STDERR, "That email address is already assigned to another account. No changes were saved.\n");
    } else {
        fwrite(STDERR, "Database error: " . $error->getMessage() . "\n");
    }
    exit(1);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
} finally {
    if (isset($password)) {
        $password = str_repeat("\0", strlen($password));
    }
    if (isset($confirmation)) {
        $confirmation = str_repeat("\0", strlen($confirmation));
    }
}
