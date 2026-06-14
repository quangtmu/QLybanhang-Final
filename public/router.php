<?php
// router.php
if (preg_match('/\.(?:png|jpg|jpeg|gif|css|js|webp|svg|ico)$/', $_SERVER["REQUEST_URI"])) {
    return false;    // serve the requested resource as-is.
}

$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

// Simple routing based on .htaccess rules
if (preg_match('#^/san-pham/([a-zA-Z0-9-]+)$#', $path, $matches)) {
    $_GET['slug'] = $matches[1];
    require __DIR__ . '/user/product-detail.php';
    return true;
}

if (preg_match('#^/danh-muc/([a-zA-Z0-9-]+)$#', $path, $matches)) {
    $_GET['category_slug'] = $matches[1];
    require __DIR__ . '/user/products.php';
    return true;
}

if (file_exists(__DIR__ . $path) && is_file(__DIR__ . $path)) {
    return false; // serve the file
}

if ($path === '/' || $path === '/index.php') {
    require __DIR__ . '/index.php';
    return true;
}

// Fallback for 404
http_response_code(404);
echo "404 Not Found";
return true;
