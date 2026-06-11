<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$username = getenv('ADMIN_USERNAME') ?: 'admin';
$email = getenv('ADMIN_EMAIL') ?: 'admin@example.com';
$password = getenv('ADMIN_PASSWORD') ?: 'Admin@123456';
$fullName = getenv('ADMIN_FULL_NAME') ?: 'System Administrator';

if (strlen($password) < PASSWORD_MIN_LENGTH) {
    fwrite(STDERR, "Admin password must be at least " . PASSWORD_MIN_LENGTH . " characters.\n");
    exit(1);
}

$db = getDB();
$passwordHash = password_hash($password, PASSWORD_BCRYPT);
$hasPasswordDev = false;
try {
    $columnStmt = $db->query("SHOW COLUMNS FROM users LIKE 'password_dev'");
    $hasPasswordDev = (bool) $columnStmt->fetch();
} catch (Throwable) {
    $hasPasswordDev = false;
}

$stmt = $db->prepare(
    "INSERT INTO users (
        uuid,
        username,
        email,
        password_hash,
        " . ($hasPasswordDev ? "password_dev," : "") . "
        full_name,
        user_type,
        is_first_login,
        email_verified_at
    ) VALUES (
        UUID(),
        :username,
        :email,
        :password_hash,
        " . ($hasPasswordDev ? ":password_dev," : "") . "
        :full_name,
        'admin',
        1,
        NOW()
    )
    ON DUPLICATE KEY UPDATE
        password_hash = VALUES(password_hash),
        " . ($hasPasswordDev ? "password_dev = VALUES(password_dev)," : "") . "
        full_name = VALUES(full_name),
        user_type = 'admin',
        deleted_at = NULL"
);

$stmt->execute([
    ':username' => $username,
    ':email' => $email,
    ':password_hash' => $passwordHash,
    ...($hasPasswordDev ? [':password_dev' => $password] : []),
    ':full_name' => $fullName,
]);

echo "Admin seed completed for {$email}.\n";
