<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once BASE_PATH . '/includes/flash.php';

AuthMiddleware::requireGuest();

$error = flash_error();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthController::checkCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    } else {
        $result = AuthController::login((string) ($_POST['login'] ?? ''), (string) ($_POST['password'] ?? ''));

        if ($result['success']) {
            $_SESSION['jwt_token'] = $result['token'];
            header('Location: ' . $result['redirect']);
            exit;
        }

        $error = $result['message'];
    }
}

$csrfToken = AuthController::csrfToken();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="/assets/css/global.css?v=20260611-17">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-panel">
            <h1>Đăng nhập</h1>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <label>
                    Email hoac username
                    <input type="text" name="login" autocomplete="username" required>
                </label>
                <label>
                    Mật khẩu
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>
                <div style="text-align: right; margin-bottom: 15px; font-size: 14px;">
                    <a href="/forgot-password.php">Quên mật khẩu?</a>
                </div>
                <button type="submit">Đăng nhập</button>
            </form>
            <p class="auth-note">Chưa có tài khoản buyer? <a href="/register.php">Đăng ký</a></p>
        </section>
    </main>
</body>
</html>
