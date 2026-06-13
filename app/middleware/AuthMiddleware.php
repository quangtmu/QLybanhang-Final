<?php

declare(strict_types=1);

class AuthMiddleware
{
    public static function user(): ?array
    {
        return AuthController::currentUser();
    }

    public static function requireLogin(bool $json = false): array
    {
        $user = self::user();

        if (!$user) {
            self::deny('Vui lòng đăng nhập để tiếp tục.', 401, '/login.php', $json);
        }

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['full_name'] = $user['full_name'];

        return $user;
    }

    public static function requireGuest(): void
    {
        $user = self::user();

        if ($user) {
            header('Location: ' . AuthController::redirectForUser($user));
            exit;
        }
    }

    public static function requireFirstLoginChange(array $user, bool $json = false): void
    {
        $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

        if ((int) $user['is_first_login'] === 1 && $currentPath !== '/change-password.php' && $currentPath !== '/logout.php') {
            if ($json) {
                self::deny('Cần đổi mật khẩu lần đầu trước khi tiếp tục.', 403, '/change-password.php', true);
            }

            header('Location: /change-password.php');
            exit;
        }
    }

    public static function deny(string $message, int $statusCode = 403, string $redirect = '/login.php', bool $json = false): never
    {
        http_response_code($statusCode);

        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $_SESSION['flash_error'] = $message;
        header('Location: ' . $redirect);
        exit;
    }
}
