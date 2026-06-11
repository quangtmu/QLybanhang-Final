<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="/assets/css/global.css?v=20260611-17">
</head>
<body>
    <main class="foundation">
        <h1><?= htmlspecialchars(APP_NAME) ?></h1>
        <p>Foundation da san sang: config, database, constants, autoload va schema khoi tao.</p>
        <p><a href="/login.php">Đăng nhập</a> · <a href="/register.php">Đăng ký buyer</a></p>
    </main>
</body>
</html>
