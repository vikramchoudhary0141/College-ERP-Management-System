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
    fwrite(STDOUT, "Super Admin password recovery (local CLI only).\n");
    fwrite(STDOUT, "Password input is visible in this terminal; make sure nobody can view your screen.\n");
    $email = strtolower(trim(prompt('Super Admin email: ')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Enter a valid email address.');
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

    $pdo = db();
    $pdo->beginTransaction();
    $find = $pdo->prepare("SELECT id, active FROM users WHERE email=? AND role='super_admin' LIMIT 1 FOR UPDATE");
    $find->execute([$email]);
    $admin = $find->fetch();
    if (!$admin) {
        $pdo->rollBack();
        throw new RuntimeException('No Super Admin account matches that email.');
    }
    if (!(int)$admin['active']) {
        $pdo->rollBack();
        throw new RuntimeException('The Super Admin account is inactive. Contact the system owner to review its status.');
    }

    $update = $pdo->prepare('UPDATE users SET password_hash=?,failed_logins=0,locked_until=NULL WHERE id=?');
    $update->execute([password_hash($password, PASSWORD_DEFAULT), (int)$admin['id']]);
    $pdo->commit();
    fwrite(STDOUT, "Password updated. You can now sign in with the new password.\n");
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
