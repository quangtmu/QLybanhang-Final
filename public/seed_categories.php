<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/models/AdminCatalogModel.php'; // For slugify

try {
    $db = getDB();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $categories = [
        'Thời trang' => [
            'icon' => 'checkroom',
            'children' => [
                'Áo nam' => ['Áo thun nam', 'Áo sơ mi nam', 'Áo khoác nam', 'Áo polo nam'],
                'Quần nam' => ['Quần jean nam', 'Quần tây nam', 'Quần kaki nam', 'Quần đùi nam'],
                'Áo nữ' => ['Áo sơ mi nữ', 'Áo thun nữ', 'Áo len nữ', 'Áo kiểu nữ'],
                'Quần nữ' => ['Quần jean nữ', 'Quần legging', 'Chân váy'],
                'Phụ kiện thời trang' => ['Thắt lưng', 'Ví da', 'Nón, mũ', 'Mắt kính'],
            ]
        ],
        'Điện thoại & Phụ kiện' => [
            'icon' => 'smartphone',
            'children' => [
                'Điện thoại thông minh' => ['iPhone', 'Samsung', 'Xiaomi', 'Oppo'],
                'Phụ kiện điện thoại' => ['Ốp lưng', 'Kính cường lực', 'Cáp sạc', 'Sạc dự phòng', 'Gậy chụp ảnh'],
                'Thiết bị đeo thông minh' => ['Đồng hồ thông minh', 'Vòng đeo tay thông minh'],
            ]
        ],
        'Máy tính & Laptop' => [
            'icon' => 'laptop_mac',
            'children' => [
                'Laptop' => ['Laptop văn phòng', 'Laptop gaming', 'Macbook', 'Laptop đồ họa'],
                'Linh kiện máy tính' => ['Bàn phím', 'Chuột', 'Màn hình', 'Ram, Ổ cứng'],
                'Thiết bị mạng' => ['Router Wifi', 'Bộ phát Wifi 4G', 'Switch'],
            ]
        ],
        'Nhà cửa & Đời sống' => [
            'icon' => 'chair',
            'children' => [
                'Nội thất' => ['Bàn ghế', 'Tủ quần áo', 'Giường', 'Kệ tivi'],
                'Trang trí nhà cửa' => ['Đèn trang trí', 'Tranh treo tường', 'Hoa giả', 'Đồng hồ treo tường'],
                'Đồ dùng nhà bếp' => ['Bộ nồi chảo', 'Dao thớt', 'Hộp đựng thực phẩm', 'Chén dĩa'],
                'Dụng cụ vệ sinh' => ['Cây lau nhà', 'Nước lau sàn', 'Bàn chải, cọ rửa'],
            ]
        ],
        'Làm đẹp & Sức khỏe' => [
            'icon' => 'health_and_beauty',
            'children' => [
                'Chăm sóc da mặt' => ['Sữa rửa mặt', 'Toner (Nước hoa hồng)', 'Serum', 'Kem dưỡng da', 'Kem chống nắng'],
                'Trang điểm' => ['Son môi', 'Kem nền', 'Phấn phủ', 'Chì kẻ mắt'],
                'Chăm sóc tóc' => ['Dầu gội', 'Dầu xả', 'Kem ủ tóc', 'Thuốc nhuộm tóc'],
                'Thực phẩm chức năng' => ['Vitamin', 'Collagen', 'Whey Protein'],
            ]
        ],
        'Thể thao & Du lịch' => [
            'icon' => 'sports_soccer',
            'children' => [
                'Đồ thể thao' => ['Quần áo bóng đá', 'Quần áo gym', 'Giày chạy bộ', 'Giày đá bóng'],
                'Dụng cụ tập luyện' => ['Tạ tay', 'Thảm yoga', 'Dây nhảy', 'Xà đơn'],
                'Dã ngoại' => ['Lều cắm trại', 'Balo du lịch', 'Đèn pin', 'Túi ngủ'],
            ]
        ],
        'Mẹ & Bé' => [
            'icon' => 'child_care',
            'children' => [
                'Đồ dùng cho bé' => ['Tã, bỉm', 'Sữa công thức', 'Bình sữa', 'Khăn giấy ướt'],
                'Đồ chơi' => ['Đồ chơi xếp hình', 'Đồ chơi giáo dục', 'Búp bê', 'Xe đồ chơi'],
                'Thời trang bé trai' => ['Áo bé trai', 'Quần bé trai', 'Bộ đồ bé trai'],
                'Thời trang bé gái' => ['Váy bé gái', 'Áo bé gái', 'Bộ đồ bé gái'],
            ]
        ]
    ];

    echo "<h3>Bắt đầu seed dữ liệu danh mục...</h3>";

    try {
        $db->exec("ALTER TABLE categories ADD COLUMN icon VARCHAR(255) NULL AFTER description");
        echo "<p>Đã thêm cột `icon` vào bảng `categories` thành công.</p>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<p>Cột `icon` đã tồn tại.</p>";
        } else {
            echo "<p style='color:orange'>Lưu ý khi thêm cột icon: " . $e->getMessage() . "</p>";
        }
    }

    $insertStmt = $db->prepare('INSERT INTO categories (name, slug, parent_id, level, icon, is_active) VALUES (?, ?, ?, ?, ?, 1)');
    $count = 0;

    foreach ($categories as $largeName => $largeData) {
        // Create large
        $slug = AdminCatalogModel::slugify($largeName);
        $insertStmt->execute([$largeName, $slug, null, CATEGORY_LEVEL_LARGE, $largeData['icon']]);
        $largeId = $db->lastInsertId();
        $count++;

        foreach ($largeData['children'] as $mediumName => $smalls) {
            // Create medium
            $mSlug = AdminCatalogModel::slugify($mediumName);
            // Append ID to make slug unique if necessary, but here we assume uniqueness
            $insertStmt->execute([$mediumName, $mSlug, $largeId, CATEGORY_LEVEL_MEDIUM, null]);
            $mediumId = $db->lastInsertId();
            $count++;

            foreach ($smalls as $smallName) {
                // Create small
                $sSlug = AdminCatalogModel::slugify($smallName);
                $insertStmt->execute([$smallName, $sSlug, $mediumId, CATEGORY_LEVEL_SMALL, null]);
                $count++;
            }
        }
    }

    echo "<p>Đã tạo thành công {$count} danh mục các cấp!</p>";
    echo "<p><a href='/admin/categories.php'>Quay lại trang Quản lý danh mục</a></p>";

} catch (Exception $e) {
    echo "<p style='color:red'>Lỗi: " . $e->getMessage() . "</p>";
}
