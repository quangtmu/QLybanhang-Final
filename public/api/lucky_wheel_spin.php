<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once BASE_PATH . '/includes/flash.php';
require_once __DIR__ . '/../../app/models/OrderModel.php';
require_once __DIR__ . '/../../app/models/LoyaltyModel.php';
require_once __DIR__ . '/../../app/models/VoucherModel.php';

header('Content-Type: application/json');

try {
    $user = AuthMiddleware::requireLogin();
    $userId = (int) $user['id'];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
        exit;
    }

    $db = getDB();
    $db->beginTransaction();

    // Get free spin status
    $stmt = $db->prepare('SELECT free_spin_used FROM user_loyalty WHERE user_id = :uid FOR UPDATE');
    $stmt->execute([':uid' => $userId]);
    $result = $stmt->fetchColumn();

    $freeSpinUsed = false;
    if ($result === false) {
        // User has no loyalty row yet, create one
        $insert = $db->prepare('INSERT INTO user_loyalty (user_id, total_spent, current_points, tier_level, spins_available, free_spin_used) VALUES (:uid, 0, 0, "bronze", 0, 0)');
        $insert->execute([':uid' => $userId]);
        $freeSpinUsed = false;
    } else {
        $freeSpinUsed = (bool) $result;
    }

    // Get number of unused order spins
    $stmtOrders = $db->prepare("SELECT COUNT(*) FROM orders WHERE buyer_id = :uid AND status != 'cancelled' AND is_spin_used = 0");
    $stmtOrders->execute([':uid' => $userId]);
    $orderSpinsAvailable = (int) $stmtOrders->fetchColumn();

    $spinsAvailable = ($freeSpinUsed ? 0 : 1) + $orderSpinsAvailable;

    if ($spinsAvailable <= 0) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Bạn đã hết lượt quay. Hãy mua thêm hàng để nhận lượt quay mới nhé!']);
        exit;
    }

    // Deduct spin
    if (!$freeSpinUsed) {
        $updateStmt = $db->prepare('UPDATE user_loyalty SET free_spin_used = 1 WHERE user_id = :uid');
        $updateStmt->execute([':uid' => $userId]);
    } else {
        // Find the oldest unused order and mark it as used
        $updateStmt = $db->prepare("
            UPDATE orders 
            SET is_spin_used = 1 
            WHERE id = (
                SELECT id FROM (
                    SELECT id FROM orders 
                    WHERE buyer_id = :uid AND status != 'cancelled' AND is_spin_used = 0 
                    ORDER BY created_at ASC LIMIT 1
                ) tmp
            )
        ");
        $updateStmt->execute([':uid' => $userId]);
    }

    $prizes = [
        ['id' => 1, 'label' => 'Thêm 5 Điểm', 'type' => 'points', 'value' => 5, 'weight' => 30],
        ['id' => 2, 'label' => 'Chúc bạn may mắn', 'type' => 'none', 'value' => 0, 'weight' => 25],
        ['id' => 3, 'label' => 'Voucher 50K', 'type' => 'voucher', 'value' => 50000, 'weight' => 10],
        ['id' => 4, 'label' => 'Thêm 1 Điểm', 'type' => 'points', 'value' => 1, 'weight' => 25],
        ['id' => 5, 'label' => 'Voucher 20K', 'type' => 'voucher', 'value' => 20000, 'weight' => 10],
    ];

    $totalWeight = array_reduce($prizes, fn($carry, $item) => $carry + $item['weight'], 0);
    $rand = random_int(1, $totalWeight);
    $currentWeight = 0;
    $wonPrize = null;

    foreach ($prizes as $prize) {
        $currentWeight += $prize['weight'];
        if ($rand <= $currentWeight) {
            $wonPrize = $prize;
            break;
        }
    }

    // Process Prize
    if ($wonPrize['type'] === 'points') {
        // We reuse the internal Loyalty update
        $loyaltyInsert = $db->prepare('
            INSERT INTO user_loyalty (user_id, total_spent, current_points, tier_level)
            VALUES (:user_id, 0, :points, "bronze")
            ON DUPLICATE KEY UPDATE current_points = current_points + VALUES(current_points)
        ');
        $loyaltyInsert->execute([':user_id' => $userId, ':points' => $wonPrize['value']]);

        $hist = $db->prepare('INSERT INTO loyalty_points_history (user_id, order_id, points, reason) VALUES (:user_id, NULL, :points, :reason)');
        $hist->execute([
            ':user_id' => $userId,
            ':points' => $wonPrize['value'],
            ':reason' => "Thưởng Vòng quay may mắn"
        ]);
    } else if ($wonPrize['type'] === 'voucher') {
        // Create personalized voucher
        $code = 'LUCKY' . strtoupper(substr(uniqid(), -5));
        $now = date('Y-m-d H:i:s');
        $endDate = date('Y-m-d H:i:s', strtotime('+7 days'));

        $vInsert = $db->prepare('
            INSERT INTO vouchers (store_id, buyer_id, code, discount_type, discount_amount, min_order_amount, usage_limit, start_date, end_date)
            VALUES (NULL, :buyer_id, :code, "fixed", :amount, 0, 1, :start_date, :end_date)
        ');
        $vInsert->execute([
            ':buyer_id' => $userId,
            ':code' => $code,
            ':amount' => $wonPrize['value'],
            ':start_date' => $now,
            ':end_date' => $endDate
        ]);
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'prize_id' => $wonPrize['id'],
        'prize_index' => array_search($wonPrize, $prizes),
        'message' => 'Bạn đã trúng: ' . $wonPrize['label'],
        'spins_left' => $spinsAvailable - 1
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}
