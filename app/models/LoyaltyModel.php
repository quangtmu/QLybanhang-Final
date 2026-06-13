<?php

declare(strict_types=1);

class LoyaltyModel
{
    public const POINTS_PER_VND = 0.0001; // 10,000 VND = 1 point
    public const VND_PER_POINT = 100; // 1 point = 100 VND discount

    public static function getLoyaltyInfo(int $userId): array
    {
        $stmt = getDB()->prepare('SELECT * FROM user_loyalty WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);
        $info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$info) {
            return [
                'user_id' => $userId,
                'total_spent' => 0,
                'current_points' => 0,
                'tier_level' => 'bronze'
            ];
        }

        return $info;
    }

    public static function getHistory(int $userId, int $limit = 20): array
    {
        $stmt = getDB()->prepare('SELECT * FROM loyalty_points_history WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function addPointsForOrder(int $orderId): void
    {
        $db = getDB();
        $stmt = $db->prepare('SELECT buyer_id, final_amount, status FROM orders WHERE id = :id');
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order || $order['status'] !== ORDER_STATUS_DELIVERED) {
            return;
        }

        // Check if points already added for this order
        $check = $db->prepare('SELECT id FROM loyalty_points_history WHERE order_id = :order_id AND points > 0');
        $check->execute([':order_id' => $orderId]);
        if ($check->fetchColumn()) {
            return; // Already rewarded
        }

        $amount = (float) $order['final_amount'];
        $pointsEarned = (int) floor($amount * self::POINTS_PER_VND);

        if ($pointsEarned <= 0) {
            return;
        }

        self::updateLoyalty($db, (int) $order['buyer_id'], $amount, $pointsEarned, $orderId, "Tích điểm từ đơn hàng #" . $orderId, true);
    }

    public static function usePoints(int $userId, int $pointsToUse, ?int $orderId = null): void
    {
        if ($pointsToUse <= 0) return;
        $db = getDB();
        
        $info = self::getLoyaltyInfo($userId);
        if ($info['current_points'] < $pointsToUse) {
            throw new RuntimeException("Không đủ điểm tích luỹ.");
        }

        self::updateLoyalty($db, $userId, 0, -$pointsToUse, $orderId, "Sử dụng điểm cho đơn hàng" . ($orderId ? " #" . $orderId : ""));
    }

    private static function updateLoyalty(PDO $db, int $userId, float $spentToAdd, int $pointsToAdd, ?int $orderId, string $reason, bool $addSpin = false): void
    {
        $spinVal = $addSpin ? 1 : 0;
        // 1. Update total_spent and current_points
        $stmt = $db->prepare('
            INSERT INTO user_loyalty (user_id, total_spent, current_points, tier_level, spins_available)
            VALUES (:user_id, :spent, :points, :tier, :spin)
            ON DUPLICATE KEY UPDATE
            total_spent = total_spent + VALUES(total_spent),
            current_points = current_points + VALUES(current_points),
            spins_available = spins_available + VALUES(spins_available)
        ');
        $stmt->execute([
            ':user_id' => $userId,
            ':spent' => $spentToAdd,
            ':points' => $pointsToAdd,
            ':tier' => 'bronze', // initial, will recalculate
            ':spin' => $spinVal
        ]);

        // 2. Insert history
        $hist = $db->prepare('INSERT INTO loyalty_points_history (user_id, order_id, points, reason) VALUES (:user_id, :order_id, :points, :reason)');
        $hist->execute([
            ':user_id' => $userId,
            ':order_id' => $orderId,
            ':points' => $pointsToAdd,
            ':reason' => $reason
        ]);

        // 3. Recalculate tier
        self::recalculateTier($db, $userId);
    }

    private static function recalculateTier(PDO $db, int $userId): void
    {
        $stmt = $db->prepare('SELECT total_spent, tier_level FROM user_loyalty WHERE user_id = :user_id FOR UPDATE');
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch();
        if (!$row) return;

        $spent = (float) $row['total_spent'];
        $newTier = 'bronze';

        if ($spent >= 50000000) { // 50m
            $newTier = 'diamond';
        } elseif ($spent >= 20000000) { // 20m
            $newTier = 'gold';
        } elseif ($spent >= 5000000) { // 5m
            $newTier = 'silver';
        }

        if ($newTier !== $row['tier_level']) {
            $update = $db->prepare("UPDATE user_loyalty SET tier_level = :tier WHERE user_id = :user_id");
            $update->execute([':tier' => $newTier, ':user_id' => $userId]);
        }
    }
}
