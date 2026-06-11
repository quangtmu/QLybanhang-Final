<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/controllers/StorageService.php';

$_FILES['test'] = [
    'name' => 'test.jpg',
    'type' => 'image/jpeg',
    'tmp_name' => __DIR__ . '/public/assets/images/admin_icon.png',
    'error' => UPLOAD_ERR_OK,
    'size' => filesize(__DIR__ . '/public/assets/images/admin_icon.png'),
];

try {
    $meta = StorageService::storeUploadedFile(
        $_FILES['test'],
        'chat_images',
        ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
        5242880, // 5MB
        'chat'
    );
    print_r($meta);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
