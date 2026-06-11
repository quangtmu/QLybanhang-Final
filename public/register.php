<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

AuthMiddleware::requireGuest();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthController::checkCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    } else {
        $result = AuthController::registerBuyer($_POST);

        if ($result['success']) {
            $_SESSION['jwt_token'] = $result['token'];
            header('Location: ' . $result['redirect']);
            exit;
        }

        $errors = $result['errors'] ?? [$result['message'] ?? 'Đăng ký thất bại.'];
    }
}

$fieldErrors = [];
$generalErrors = [];
foreach ($errors as $key => $val) {
    if (is_int($key)) {
        $generalErrors[] = $val;
    } else {
        $fieldErrors[$key] = $val;
    }
}

$csrfToken = AuthController::csrfToken();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký buyer - <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="/assets/css/global.css?v=20260611-17">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-panel">
            <h1>Đăng ký buyer</h1>
            <?php if ($generalErrors): ?>
                <div class="alert alert-error">
                    <?php foreach ($generalErrors as $message): ?>
                        <p><?= htmlspecialchars((string) $message) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <label>
                    <span class="label-text">Họ tên <span class="required-mark">*</span></span>
                    <input type="text" name="full_name" class="<?= isset($fieldErrors['full_name']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars((string) ($_POST['full_name'] ?? '')) ?>" required>
                    <?php if (isset($fieldErrors['full_name'])): ?>
                        <span class="field-error"><?= htmlspecialchars($fieldErrors['full_name']) ?></span>
                    <?php endif; ?>
                </label>
                <label>
                    <span class="label-text">Username <span class="required-mark">*</span></span>
                    <input type="text" name="username" class="<?= isset($fieldErrors['username']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars((string) ($_POST['username'] ?? '')) ?>" autocomplete="username" required>
                    <?php if (isset($fieldErrors['username'])): ?>
                        <span class="field-error"><?= htmlspecialchars($fieldErrors['username']) ?></span>
                    <?php endif; ?>
                </label>
                <label>
                    <span class="label-text">Email <span class="required-mark">*</span></span>
                    <input type="email" name="email" class="<?= isset($fieldErrors['email']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars((string) ($_POST['email'] ?? '')) ?>" autocomplete="email" required>
                    <?php if (isset($fieldErrors['email'])): ?>
                        <span class="field-error"><?= htmlspecialchars($fieldErrors['email']) ?></span>
                    <?php endif; ?>
                </label>
                <label>
                    Số điện thoại
                    <input type="tel" name="phone" class="<?= isset($fieldErrors['phone']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars((string) ($_POST['phone'] ?? '')) ?>">
                    <?php if (isset($fieldErrors['phone'])): ?>
                        <span class="field-error"><?= htmlspecialchars($fieldErrors['phone']) ?></span>
                    <?php endif; ?>
                </label>
                <label>
                    <span class="label-text">Mật khẩu <span class="required-mark">*</span></span>
                    <input type="password" name="password" class="<?= isset($fieldErrors['password']) ? 'is-invalid' : '' ?>" autocomplete="new-password" required>
                    <?php if (isset($fieldErrors['password'])): ?>
                        <span class="field-error"><?= htmlspecialchars($fieldErrors['password']) ?></span>
                    <?php endif; ?>
                </label>
                <label>
                    <span class="label-text">Xác nhận mật khẩu <span class="required-mark">*</span></span>
                    <input type="password" name="password_confirmation" class="<?= isset($fieldErrors['password_confirmation']) ? 'is-invalid' : '' ?>" autocomplete="new-password" required>
                    <?php if (isset($fieldErrors['password_confirmation'])): ?>
                        <span class="field-error"><?= htmlspecialchars($fieldErrors['password_confirmation']) ?></span>
                    <?php endif; ?>
                </label>
                <button type="submit">Tạo tài khoản</button>
            </form>
            <p class="auth-note">Đã có tài khoản? <a href="/login.php">Đăng nhập</a></p>
        </section>
    </main>
</body>
</html>
