<?php

declare(strict_types=1);

class AuthController
{
    public static function currentUser(): ?array
    {
        if (!empty($_SESSION['user_id'])) {
            return UserModel::findById((int) $_SESSION['user_id']);
        }

        $token = self::bearerToken();

        if ($token === null) {
            return null;
        }

        $payload = JwtService::decode($token);

        if (!$payload || empty($payload['sub'])) {
            return null;
        }

        return UserModel::findById((int) $payload['sub']);
    }

    public static function login(string $login, string $password): array
    {
        $user = UserModel::findByEmailOrUsername(trim($login));

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Thông tin đăng nhập không đúng .'];
        }

        if (!self::canLogin($user)) {
            return ['success' => false, 'message' => 'Tài khoản hiện không được phép đăng nhập.'];
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['full_name'] = $user['full_name'];

        UserModel::markLoggedIn((int) $user['id']);
        self::storeSession((int) $user['id']);

        $token = JwtService::encode([
            'sub' => (int) $user['id'],
            'type' => $user['user_type'],
            'email' => $user['email'],
        ]);

        return [
            'success' => true,
            'user' => self::publicUser($user),
            'token' => $token,
            'redirect' => self::redirectForUser($user),
        ];
    }

    public static function registerBuyer(array $data): array
    {
        $errors = self::validateBuyerRegistration($data);

        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors];
        }

        $id = UserModel::createBuyer([
            'username' => trim($data['username']),
            'email' => strtolower(trim($data['email'])),
            'password' => (string) $data['password'],
            'full_name' => trim($data['full_name']),
            'phone' => trim((string) ($data['phone'] ?? '')),
        ]);

        $login = self::login((string) $data['email'], (string) $data['password']);
        $login['user_id'] = $id;

        return $login;
    }

    public static function changePassword(int $userId, string $currentPassword, string $newPassword, string $confirmPassword): array
    {
        $user = UserModel::findById($userId);

        if (!$user) {
            return ['success' => false, 'message' => 'Không tìm thấy tài khoản.'];
        }

        if (!password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Mật khẩu hiện tại không đúng.'];
        }

        if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            return ['success' => false, 'message' => 'Mật khẩu mới tối thiểu ' . PASSWORD_MIN_LENGTH . ' ký tự.'];
        }

        if ($newPassword !== $confirmPassword) {
            return ['success' => false, 'message' => 'Xác nhận mật khẩu mới không khớp.'];
        }

        UserModel::updatePassword($userId, $newPassword);
        
        return ['success' => true, 'redirect' => self::redirectForUser(UserModel::findById($userId) ?? $user)];
    }

    public static function forgotPassword(string $email): array
    {
        $token = UserModel::createResetToken($email);
        if (!$token) {
            return ['success' => false, 'message' => 'Email/Username không tồn tại trong hệ thống.'];
        }

        return ['success' => true, 'message' => 'Link đặt lại mật khẩu đã được tạo.', 'token' => $token];
    }

    public static function resetPassword(string $token, string $newPassword, string $confirmPassword): array
    {
        $user = UserModel::findByResetToken($token);

        if (!$user) {
            return ['success' => false, 'message' => 'Liên kết không hợp lệ hoặc đã hết hạn.'];
        }

        if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            return ['success' => false, 'message' => 'Mật khẩu mới tối thiểu ' . PASSWORD_MIN_LENGTH . ' ký tự.'];
        }

        if ($newPassword !== $confirmPassword) {
            return ['success' => false, 'message' => 'Xác nhận mật khẩu mới không khớp.'];
        }

        UserModel::updatePassword((int) $user['id'], $newPassword);
        UserModel::clearResetToken((int) $user['id']);

        return ['success' => true, 'message' => 'Đổi mật khẩu thành công. Bạn có thể đăng nhập ngay bây giờ.'];
    }

    public static function logout(): void
    {
        if (!empty($_SESSION['user_id'])) {
            UserModel::markLoggedOut((int) $_SESSION['user_id']);
            self::deleteStoredSession(session_id());
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }

    public static function redirectForUser(array $user): string
    {
        if ((int) $user['is_first_login'] === 1) {
            return '/change-password.php';
        }

        return match ($user['user_type']) {
            USER_TYPE_ADMIN, USER_TYPE_SUB_ADMIN_ACTIVE => '/admin/dashboard.php',
            USER_TYPE_STORE_APPROVED, USER_TYPE_STORE_EMPLOYEE => '/store/dashboard.php',
            default => '/user/home.php',
        };
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function checkCsrf(?string $token): bool
    {
        return is_string($token) && hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token);
    }

    private static function validateBuyerRegistration(array $data): array
    {
        $errors = [];
        $username = trim((string) ($data['username'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if ($username === '' || !preg_match('/^[a-zA-Z0-9_]{3,100}$/', $username)) {
            $errors['username'] = 'Username từ 3 ký tự, chỉ dùng chữ cái, số và dấu gạch dưới.';
        } elseif (UserModel::usernameExists($username)) {
            $errors['username'] = 'Username đã tồn tại.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email không hợp lệ.';
        } elseif (UserModel::emailExists($email)) {
            $errors['email'] = 'Email đã tồn tại.';
        }

        if (trim((string) ($data['full_name'] ?? '')) === '') {
            $errors['full_name'] = 'Vui lòng nhập họ tên.';
        }

        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors['password'] = 'Mật khẩu tối thiểu ' . PASSWORD_MIN_LENGTH . ' ký tự.';
        }

        if ($password !== (string) ($data['password_confirmation'] ?? '')) {
            $errors['password_confirmation'] = 'Xác nhận mật khẩu không khớp.';
        }

        return $errors;
    }

    private static function canLogin(array $user): bool
    {
        if (in_array($user['user_type'], [
            USER_TYPE_SUB_ADMIN_INACTIVE,
            USER_TYPE_STORE_REJECTED,
            USER_TYPE_STORE_SUSPENDED,
            USER_TYPE_USER_BANNED,
        ], true)) {
            return false;
        }

        if ($user['user_type'] === USER_TYPE_STORE_EMPLOYEE) {
            return UserModel::activeStoreEmployeeExists((int) $user['id']);
        }

        return true;
    }

    private static function publicUser(array $user): array
    {
        unset($user['password_hash'], $user['password_dev']);

        return $user;
    }

    private static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

        if (preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private static function storeSession(int $userId): void
    {
        $stmt = getDB()->prepare(
            'INSERT INTO sessions (user_id, session_id, ip_address, user_agent, last_activity_at)
             VALUES (:user_id, :session_id, :ip_address, :user_agent, NOW())
             ON DUPLICATE KEY UPDATE last_activity_at = NOW(), user_id = VALUES(user_id)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':session_id' => session_id(),
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
        ]);
    }

    private static function deleteStoredSession(string $sessionId): void
    {
        $stmt = getDB()->prepare('DELETE FROM sessions WHERE session_id = :session_id');
        $stmt->execute([':session_id' => $sessionId]);
    }
}
