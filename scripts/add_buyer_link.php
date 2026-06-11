<?php
$files = [
    'public/store/employees.php',
    'public/store/dashboard.php',
    'public/store/products.php',
    'public/store/invoices.php',
    'public/store/shipments.php',
    'public/store/orders.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // Check if not already added
    if (strpos($content, '<a href="/user/home.php">Trang mua hàng</a>') === false) {
        $replacement = "                <a href=\"/user/home.php\">Trang mua hàng</a>\n                <a href=\"/logout.php\">Đăng xuất</a>";
        $content = str_replace('                <a href="/logout.php">Đăng xuất</a>', $replacement, $content);
        file_put_contents($file, $content);
    }
}
echo "Done";
