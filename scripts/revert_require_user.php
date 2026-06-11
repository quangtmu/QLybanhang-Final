<?php
$files = glob('public/user/*.php');

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $content = str_replace(
        'PermissionMiddleware::requireUserType([USER_TYPE_USER, USER_TYPE_STORE_APPROVED])',
        'PermissionMiddleware::requireUserType(USER_TYPE_USER)',
        $content
    );
    file_put_contents($file, $content);
}
echo "Reverted requireUserType in public/user/*.php";
