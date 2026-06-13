<?php
// Tự động import Database cho Railway/Vercel
require_once __DIR__ . '/../config/config.php';

try {
    $db = getDB();
    
    // Kiểm tra xem bảng users đã tồn tại chưa
    $check = $db->query("SHOW TABLES LIKE 'users'")->fetch();
    if ($check) {
        die("<h3>Database đã được cài đặt!</h3><p>Vui lòng xóa file này đi để bảo mật.</p>");
    }

    echo "<h3>Đang tiến hành Import Database...</h3>";

    // 1. Import schema
    $schemaPath = __DIR__ . '/../database/schema.sql';
    if (file_exists($schemaPath)) {
        $sql = file_get_contents($schemaPath);
        $db->exec($sql);
        echo "<p>✅ Đã import cấu trúc cơ sở dữ liệu (schema.sql).</p>";
    } else {
        die("Không tìm thấy file schema.sql");
    }

    // 2. Chạy migration phase 2
    echo "<p>Đang chạy cập nhật tính năng mới...</p>";
    require_once __DIR__ . '/../run_migration.php';
    
    // 3. Import seed admin
    $seedPath = __DIR__ . '/../database/seed_admin.sql';
    if (file_exists($seedPath)) {
        $sql = file_get_contents($seedPath);
        $db->exec($sql);
        echo "<p>✅ Đã import tài khoản Admin mặc định (admin@example.com / password).</p>";
    }

    echo "<h3>🎉 HOÀN TẤT!</h3>";
    echo "<p>Bạn có thể đăng nhập bằng tài khoản: <b>admin@example.com</b> | Mật khẩu: <b>password</b></p>";
    echo '<p><a href="/login.php">Đi đến trang đăng nhập</a></p>';
    echo '<hr><p style="color:red"><b>QUAN TRỌNG:</b> Sau khi đăng nhập, hãy xóa file `public/import_db.php` này đi trên mã nguồn GitHub của bạn để tránh người khác chạy lại làm hỏng dữ liệu.</p>';

} catch (PDOException $e) {
    die("Lỗi Import: " . $e->getMessage());
}
