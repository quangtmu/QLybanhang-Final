<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once BASE_PATH . '/includes/flash.php';

$user = PermissionMiddleware::requireModule(MODULE_ORDERS); // Using orders permission for reviews? Or maybe a new module. Let's use MODULE_PRODUCTS as reviews are tied to products.
$user = PermissionMiddleware::requireModule(MODULE_PRODUCTS);
$storeId = StoreEmployeeModel::storeIdForActor($user);

$errors = [];
$success = flash_success();
$csrfToken = AuthController::csrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthController::checkCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    } else {
        try {
            $formAction = (string) ($_POST['form_action'] ?? '');
            $reviewId = (int) ($_POST['review_id'] ?? 0);

            if ($formAction === 'reply') {
                $replyContent = (string) ($_POST['store_reply'] ?? '');
                ReviewModel::replyToReview($storeId, $reviewId, $replyContent);
                $_SESSION['flash_success'] = 'Đã cập nhật phản hồi đánh giá.';
                header('Location: /store/reviews.php');
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.';
        }
    }
}

$filters = [
    'rating' => $_GET['rating'] ?? '',
    'is_replied' => $_GET['is_replied'] ?? '',
    'page' => $_GET['page'] ?? 1,
    'limit' => 20,
];

$result = ReviewModel::reviewsForStore($storeId, $filters);
$reviews = $result['items'];
$pagination = $result['pagination'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý đánh giá</title>
    <link rel="stylesheet" href="/assets/css/global.css?v=20260611">
    <link rel="stylesheet" href="/assets/css/store-buyer.css?v=20260611">
</head>
<body class="portal-page store-page">
    <main class="portal-shell portal-wide">
        <?php include __DIR__ . "/_store_nav.php"; ?>

        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($errors): ?>
            <div class="alert alert-error"><?php foreach ($errors as $message): ?><p><?= htmlspecialchars((string) $message) ?></p><?php endforeach; ?></div>
        <?php endif; ?>

        <section class="portal-panel">
            <h1>Quản lý đánh giá</h1>
            <form method="get" class="filter-row filter-row-large">
                <select name="rating">
                    <option value="">Tất cả số sao</option>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?= $i ?>" <?= (string) $filters['rating'] === (string) $i ? 'selected' : '' ?>><?= $i ?> Sao</option>
                    <?php endfor; ?>
                </select>
                <select name="is_replied">
                    <option value="">Trạng thái phản hồi</option>
                    <option value="0" <?= $filters['is_replied'] === '0' ? 'selected' : '' ?>>Chưa phản hồi</option>
                    <option value="1" <?= $filters['is_replied'] === '1' ? 'selected' : '' ?>>Đã phản hồi</option>
                </select>
                <button type="submit">Lọc</button>
            </form>
        </section>

        <section class="portal-panel">
            <h2>Danh sách đánh giá</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Sản phẩm</th>
                            <th>Khách hàng</th>
                            <th>Đánh giá</th>
                            <th>Phản hồi của bạn</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$reviews): ?><tr><td colspan="4" class="empty">Chưa có đánh giá nào.</td></tr><?php endif; ?>
                        <?php foreach ($reviews as $review): ?>
                            <tr>
                                <td style="max-width: 200px; white-space: normal;">
                                    <strong><?= htmlspecialchars($review['product_name']) ?></strong>
                                    <div style="font-size: 11px; color: #64748b; margin-top: 4px;"><?= htmlspecialchars($review['created_at']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($review['buyer_name']) ?></td>
                                <td style="max-width: 300px; white-space: normal;">
                                    <div style="color: #f59e0b; font-size: 14px; margin-bottom: 4px;">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?= $i <= (int) $review['rating'] ? '★' : '☆' ?>
                                        <?php endfor; ?>
                                    </div>
                                    <div style="font-size: 13px;"><?= nl2br(htmlspecialchars((string) $review['comment'])) ?></div>
                                </td>
                                <td style="max-width: 300px; white-space: normal;">
                                    <form method="post" class="admin-form" style="margin: 0; display: flex; flex-direction: column; gap: 8px;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="form_action" value="reply">
                                        <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
                                        <textarea name="store_reply" rows="2" placeholder="Nhập nội dung phản hồi..." style="width: 100%; padding: 6px; font-size: 13px; border-radius: 4px; border: 1px solid #cbd5e1; resize: vertical;"><?= htmlspecialchars((string) $review['store_reply']) ?></textarea>
                                        <button type="submit" style="align-self: flex-start; padding: 4px 12px; font-size: 12px; font-weight: 600; border-radius: 4px; border: 1px solid #cbd5e1; background: #f8fafc; cursor: pointer;">Cập nhật phản hồi</button>
                                        <?php if ($review['store_replied_at']): ?>
                                            <span style="font-size: 11px; color: #10b981; margin-top: 4px;">Đã phản hồi lúc: <?= htmlspecialchars($review['store_replied_at']) ?></span>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php include BASE_PATH . '/public/includes/pagination.php'; ?>
        </section>
    </main>
    <script src="/assets/js/global.js"></script>
</body>
</html>
