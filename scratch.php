<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/public/user/_buyer_ui.php';

$user = [
    'id' => 1,
    'username' => 'admin',
    'user_type' => USER_TYPE_ADMIN
];
$_SESSION['user'] = $user;

// Simulate notifications.php
ob_start();
try {
    include __DIR__ . '/public/notifications.php';
    echo "SUCCESS";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage();
}
$output = ob_get_clean();
echo substr($output, -100);
