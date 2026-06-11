<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once BASE_PATH . '/includes/flash.php';

AuthMiddleware::requireGuest();

$error = null;
$message = null;
$resetLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthController::checkCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    } else {
        $result = AuthController::forgotPassword((string) ($_POST['login'] ?? ''));

        if ($result['success']) {
            $message = $result['message'];
            // FOR DEV PURPOSES: DISPLAY THE LINK DIRECTLY
            $resetLink = BASE_URL . '/reset-password.php?token=' . $result['token'];
        } else {
            $error = $result['message'];
        }
    }
}

$csrfToken = AuthController::csrfToken();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quên mật khẩu - <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="/assets/css/global.css?v=20260611-17">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-panel">
            <?php if ($message): ?>
                <div style="text-align: center; padding: 20px 0;">
                    <div style="width: 60px; height: 60px; background: #d1fae5; color: #059669; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 16px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-envelope-check" viewBox="0 0 16 16">
                            <path d="M2 2a2 2 0 0 0-2 2v8.01A2 2 0 0 0 2 14h5.5a.5.5 0 0 0 0-1H2a1 1 0 0 1-1-1V4.01T1.01 4h13.98A1 1 0 0 1 15 5v2.5a.5.5 0 0 0 1 0V4a2 2 0 0 0-2-2H2Zm.05 2a.5.5 0 0 0-.1.01A1 1 0 0 1 2 4h12a1 1 0 0 1 .05.01L8 7.33 2.05 4Z"/>
                            <path d="M16 12.5a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0Zm-1.993-1.679a.5.5 0 0 0-.686.172l-1.17 1.95-.547-.547a.5.5 0 0 0-.708.708l.774.773a.75.75 0 0 0 1.174-.144l1.335-2.226a.5.5 0 0 0-.172-.686Z"/>
                        </svg>
                    </div>
                    <h2 style="margin: 0 0 10px 0; font-size: 20px; color: #0f172a;">Đã gửi hướng dẫn!</h2>
                    <p style="color: #64748b; margin-bottom: 24px; line-height: 1.6; font-size: 15px;">
                        <?= htmlspecialchars($message) ?> <br>
                        Vui lòng kiểm tra hộp thư đến (hoặc thư rác) để tiếp tục.
                    </p>
                    
                    <?php if ($resetLink): ?>
                        <div style="background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 24px;">
                            <div style="color: #475569; font-size: 13px; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; gap: 6px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/><path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533L8.93 6.588zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/></svg>
                                <strong>Chế độ DEV MODE</strong>
                            </div>
                            <p style="font-size: 13px; color: #64748b; margin-top: 0; margin-bottom: 16px;">Hệ thống chưa gửi email thật. Nhấn nút dưới đây để đặt lại mật khẩu trực tiếp.</p>
                            <a href="<?= htmlspecialchars($resetLink) ?>" style="display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 8px; font-weight: 600; text-decoration: none; transition: background 0.2s;" onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
                                Đặt lại mật khẩu ngay
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <a href="/login.php" style="color: #2563eb; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 6px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/></svg>
                        Quay lại đăng nhập
                    </a>
                </div>
            <?php else: ?>
                <h1>Quên mật khẩu</h1>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <label>
                        Nhập Email hoặc Username của bạn
                        <input type="text" name="login" autocomplete="username" required>
                    </label>
                    <button type="submit">Khôi phục mật khẩu</button>
                </form>
                <p class="auth-note"><a href="/login.php">Quay lại Đăng nhập</a></p>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
