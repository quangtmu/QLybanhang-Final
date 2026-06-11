<?php
$files = [
    'public/user/orders.php',
    'public/user/products.php',
    'public/user/cart.php',
    'public/user/home.php',
    'public/user/product-detail.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $replacement = "                <?php if (\$user['user_type'] === USER_TYPE_STORE_APPROVED): ?>\n                    <a href=\"/store/dashboard.php\">Store của tôi</a>\n                <?php else: ?>\n                    <a href=\"/user/store-registration.php\">Mở shop</a>\n                <?php endif; ?>";
    $content = str_replace('                <a href="/user/store-registration.php">Mở shop</a>', $replacement, $content);
    file_put_contents($file, $content);
}
echo "Done";
