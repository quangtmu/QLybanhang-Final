<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once BASE_PATH . '/includes/flash.php';

AuthMiddleware::requireGuest();

$token = $_GET['token'] ?? '';
if (!$token) {
    header('Location: /login.php');
    exit;
}

$error = null;
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthController::checkCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    } else {
        $result = AuthController::resetPassword(
            $token,
            (string) ($_POST['new_password'] ?? ''),
            (string) ($_POST['new_password_confirmation'] ?? '')
        );

        if ($result['success']) {
            $_SESSION['flash_success'] = $result['message'];
            header('Location: /login.php');
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
    <title>Đặt lại mật khẩu - <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="/assets/css/global.css?v=20260611-17">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-panel">
            <h1>Đặt lại mật khẩu</h1>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <label>
                    Mật khẩu mới
                    <input type="password" name="new_password" autocomplete="new-password" required>
                </label>
                <label>
                    Xác nhận mật khẩu mới
                    <input type="password" name="new_password_confirmation" autocomplete="new-password" required>
                </label>
                <button type="submit">Lưu mật khẩu mới</button>
            </form>
            <p class="auth-note"><a href="/login.php">Quay lại Đăng nhập</a></p>
        </section>
    </main>
</body>
</html>
