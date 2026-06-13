<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$user = AuthMiddleware::requireLogin();
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthController::checkCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    } else {
        $result = AuthController::changePassword(
            (int) $user['id'],
            (string) ($_POST['current_password'] ?? ''),
            (string) ($_POST['new_password'] ?? ''),
            (string) ($_POST['new_password_confirmation'] ?? '')
        );

        if ($result['success']) {
            $_SESSION['flash_success'] = 'Đã đổi mật khẩu thành công.';
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
    <title>Đổi mật khẩu - <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="/assets/css/global.css?v=20260611-17">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-panel">
            <h1>Đổi mật khẩu</h1>
            <?php if ((int) $user['is_first_login'] === 1): ?>
                <div class="alert alert-info">Lần đăng nhập đầu tiên cần đổi mật khẩu trước khi vào hệ thống.</div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <label>
                    Mật khẩu hiện tại
                    <input type="password" name="current_password" autocomplete="current-password" required>
                </label>
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
            <?php if ((int) $user['is_first_login'] !== 1): ?>
                <p class="auth-note"><a href="<?= htmlspecialchars(AuthController::redirectForUser($user)) ?>">Quay lại</a></p>
            <?php endif; ?>
        </section>
    </main>
    <script src="/assets/js/global.js"></script>
</body>
</html>
