<?php

declare(strict_types=1);

class UserModel
{
    public static function findById(int $id): ?array
    {
        $stmt = getDB()->prepare('SELECT * FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function findByEmailOrUsername(string $login): ?array
    {
        $stmt = getDB()->prepare(
            'SELECT * FROM users
             WHERE (email = :email_login OR username = :username_login) AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            ':email_login' => $login,
            ':username_login' => $login,
        ]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function emailExists(string $email): bool
    {
        $stmt = getDB()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);

        return (bool) $stmt->fetch();
    }

    public static function usernameExists(string $username): bool
    {
        $stmt = getDB()->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);

        return (bool) $stmt->fetch();
    }

    public static function createBuyer(array $data): int
    {
        $stmt = getDB()->prepare(
            "INSERT INTO users (
                uuid,
                username,
                email,
                password_hash,
                full_name,
                phone,
                user_type,
                is_first_login,
                email_verified_at
            ) VALUES (
                UUID(),
                :username,
                :email,
                :password_hash,
                :full_name,
                :phone,
                'user',
                0,
                NOW()
            )"
        );

        $stmt->execute([
            ':username' => $data['username'],
            ':email' => $data['email'],
            ':password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            ':full_name' => $data['full_name'],
            ':phone' => $data['phone'] ?: null,
        ]);

        return (int) getDB()->lastInsertId();
    }

    public static function markLoggedIn(int $id): void
    {
        $stmt = getDB()->prepare(
            'UPDATE users
             SET last_login_at = NOW(), login_count = login_count + 1, is_online = 1, last_seen_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    public static function markLoggedOut(int $id): void
    {
        $stmt = getDB()->prepare('UPDATE users SET is_online = 0, last_seen_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public static function updatePassword(int $id, string $password): void
    {
        $stmt = getDB()->prepare(
            'UPDATE users
             SET password_hash = :password_hash, is_first_login = 0, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $id,
            ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
        ]);
    }

    public static function updateProfile(int $id, string $fullName, ?string $phone): void
    {
        $fullName = trim($fullName);
        $phone = trim((string) $phone);

        if ($fullName === '') {
            throw new RuntimeException('Vui lòng nhập họ tên.');
        }

        $stmt = getDB()->prepare(
            'UPDATE users
             SET full_name = :full_name,
                 phone = :phone,
                 updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            ':id' => $id,
            ':full_name' => $fullName,
            ':phone' => $phone !== '' ? $phone : null,
        ]);
    }

    public static function createResetToken(string $email): ?string
    {
        $user = self::findByEmailOrUsername($email);
        if (!$user) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $stmt = getDB()->prepare(
            'UPDATE users SET reset_token = :token, reset_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = :id'
        );
        $stmt->execute([':token' => $token, ':id' => $user['id']]);

        return $token;
    }

    public static function findByResetToken(string $token): ?array
    {
        $stmt = getDB()->prepare(
            'SELECT * FROM users WHERE reset_token = :token AND reset_expires_at > NOW() AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function clearResetToken(int $id): void
    {
        $stmt = getDB()->prepare(
            'UPDATE users SET reset_token = NULL, reset_expires_at = NULL WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    public static function activeStoreEmployeeExists(int $employeeId): bool
    {
        $stmt = getDB()->prepare(
            'SELECT id
             FROM store_employees
             WHERE employee_id = :employee_id AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute([':employee_id' => $employeeId]);

        return (bool) $stmt->fetch();
    }
}
