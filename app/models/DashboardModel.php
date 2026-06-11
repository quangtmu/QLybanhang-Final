<?php

declare(strict_types=1);

class DashboardModel
{
    public static function adminSummary(): array
    {
        return [
            'revenue' => self::revenueSummary(),
            'orders' => self::orderSummary(),
            'products' => self::productSummary(),
            'users' => self::userSummary(),
            'recent_orders' => self::recentOrders(),
            'top_stores' => self::topStores(),
        ];
    }

    public static function storeSummary(array $actor): array
    {
        $storeId = self::storeIdForActor($actor);

        return [
            'store' => self::storeInfo($storeId),
            'revenue' => self::revenueSummary($storeId),
            'orders' => self::orderSummary($storeId),
            'products' => self::productSummary($storeId),
            'employees' => self::employeeSummary($storeId),
            'recent_orders' => self::recentOrders($storeId),
            'top_products' => self::topProducts($storeId),
        ];
    }

    private static function revenueSummary(?int $storeId = null): array
    {
        $where = ['status = :status'];
        $params = [':status' => ORDER_STATUS_DELIVERED];

        if ($storeId !== null) {
            $where[] = 'store_id = :store_id';
            $params[':store_id'] = $storeId;
        }

        $whereSql = implode(' AND ', $where);

        return [
            'delivered_total' => self::moneyScalar("SELECT COALESCE(SUM(final_amount), 0) FROM orders WHERE {$whereSql}", $params),
            'delivered_month' => self::moneyScalar("SELECT COALESCE(SUM(final_amount), 0) FROM orders WHERE {$whereSql} AND YEAR(delivered_at) = YEAR(CURDATE()) AND MONTH(delivered_at) = MONTH(CURDATE())", $params),
            'delivered_today' => self::moneyScalar("SELECT COALESCE(SUM(final_amount), 0) FROM orders WHERE {$whereSql} AND DATE(delivered_at) = CURDATE()", $params),
        ];
    }

    private static function orderSummary(?int $storeId = null): array
    {
        $params = [];
        $where = ['1 = 1'];

        if ($storeId !== null) {
            $where[] = 'store_id = :store_id';
            $params[':store_id'] = $storeId;
        }

        $stmt = getDB()->prepare(
            'SELECT status, COUNT(*) AS total
             FROM orders
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY status'
        );
        $stmt->execute($params);
        $byStatus = array_fill_keys(OrderModel::orderStatuses(), 0);

        foreach ($stmt->fetchAll() as $row) {
            $byStatus[(string) $row['status']] = (int) $row['total'];
        }

        return [
            'total' => array_sum($byStatus),
            'by_status' => $byStatus,
            'open' => $byStatus[ORDER_STATUS_PENDING]
                + $byStatus[ORDER_STATUS_CONFIRMED]
                + $byStatus[ORDER_STATUS_PROCESSING]
                + $byStatus[ORDER_STATUS_SHIPPED]
                + $byStatus[ORDER_STATUS_DELIVERING],
            'delivered' => $byStatus[ORDER_STATUS_DELIVERED],
            'cancelled' => $byStatus[ORDER_STATUS_CANCELLED],
        ];
    }

    private static function productSummary(?int $storeId = null): array
    {
        $params = [];
        $where = ['deleted_at IS NULL'];

        if ($storeId !== null) {
            $where[] = 'store_id = :store_id';
            $params[':store_id'] = $storeId;
        }

        $stmt = getDB()->prepare(
            'SELECT status, COUNT(*) AS total
             FROM products
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY status'
        );
        $stmt->execute($params);
        $byStatus = array_fill_keys([
            PRODUCT_STATUS_DRAFT,
            PRODUCT_STATUS_PENDING_REVIEW,
            PRODUCT_STATUS_APPROVED,
            PRODUCT_STATUS_REJECTED,
            PRODUCT_STATUS_ARCHIVED,
        ], 0);

        foreach ($stmt->fetchAll() as $row) {
            $byStatus[(string) $row['status']] = (int) $row['total'];
        }

        return [
            'total' => array_sum($byStatus),
            'by_status' => $byStatus,
            'approved' => $byStatus[PRODUCT_STATUS_APPROVED],
            'pending_review' => $byStatus[PRODUCT_STATUS_PENDING_REVIEW],
            'rejected' => $byStatus[PRODUCT_STATUS_REJECTED],
        ];
    }

    private static function userSummary(): array
    {
        $stmt = getDB()->query(
            'SELECT user_type, COUNT(*) AS total
             FROM users
             WHERE deleted_at IS NULL
             GROUP BY user_type'
        );
        $byType = array_fill_keys(USER_TYPES, 0);

        foreach ($stmt->fetchAll() as $row) {
            $byType[(string) $row['user_type']] = (int) $row['total'];
        }

        return [
            'total' => array_sum($byType),
            'buyers' => $byType[USER_TYPE_USER] + $byType[USER_TYPE_USER_BANNED],
            'stores' => $byType[USER_TYPE_STORE_APPROVED] + $byType[USER_TYPE_STORE_PENDING] + $byType[USER_TYPE_STORE_SUSPENDED],
            'store_approved' => $byType[USER_TYPE_STORE_APPROVED],
            'store_pending' => $byType[USER_TYPE_STORE_PENDING],
            'admins' => $byType[USER_TYPE_ADMIN] + $byType[USER_TYPE_SUB_ADMIN_ACTIVE] + $byType[USER_TYPE_SUB_ADMIN_INACTIVE],
            'by_type' => $byType,
        ];
    }

    private static function employeeSummary(int $storeId): array
    {
        $stmt = getDB()->prepare(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_total,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive_total
             FROM store_employees
             WHERE store_id = :store_id'
        );
        $stmt->execute([':store_id' => $storeId]);
        $row = $stmt->fetch() ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active_total'] ?? 0),
            'inactive' => (int) ($row['inactive_total'] ?? 0),
        ];
    }

    private static function recentOrders(?int $storeId = null, int $limit = 8): array
    {
        $params = [];
        $where = ['1 = 1'];

        if ($storeId !== null) {
            $where[] = 'o.store_id = :store_id';
            $params[':store_id'] = $storeId;
        }

        $stmt = getDB()->prepare(
            'SELECT o.id, o.order_code, o.status, o.final_amount, o.created_at,
                    buyer.email AS buyer_email,
                    COALESCE(sp.store_name, store_user.email) AS store_name
             FROM orders o
             JOIN users buyer ON buyer.id = o.buyer_id
             JOIN users store_user ON store_user.id = o.store_id
             LEFT JOIN store_profiles sp ON sp.user_id = o.store_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY o.created_at DESC
             LIMIT :limit'
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', min(MAX_PAGE_SIZE, max(1, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private static function topStores(int $limit = 8): array
    {
        $stmt = getDB()->prepare(
            'SELECT o.store_id,
                    COALESCE(sp.store_name, u.email) AS store_name,
                    COUNT(*) AS orders_total,
                    COALESCE(SUM(o.final_amount), 0) AS revenue_total
             FROM orders o
             JOIN users u ON u.id = o.store_id
             LEFT JOIN store_profiles sp ON sp.user_id = o.store_id
             WHERE o.status = :status
             GROUP BY o.store_id, sp.store_name, u.email
             ORDER BY revenue_total DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':status', ORDER_STATUS_DELIVERED);
        $stmt->bindValue(':limit', min(MAX_PAGE_SIZE, max(1, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private static function topProducts(int $storeId, int $limit = 8): array
    {
        $stmt = getDB()->prepare(
            'SELECT oi.product_id,
                    oi.product_name,
                    SUM(oi.quantity) AS quantity_total,
                    COALESCE(SUM(oi.subtotal), 0) AS revenue_total
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE o.store_id = :store_id
               AND o.status = :status
             GROUP BY oi.product_id, oi.product_name
             ORDER BY quantity_total DESC, revenue_total DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':store_id', $storeId, PDO::PARAM_INT);
        $stmt->bindValue(':status', ORDER_STATUS_DELIVERED);
        $stmt->bindValue(':limit', min(MAX_PAGE_SIZE, max(1, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private static function storeInfo(int $storeId): array
    {
        $stmt = getDB()->prepare(
            'SELECT u.id, u.email, COALESCE(sp.store_name, u.full_name, u.email) AS store_name
             FROM users u
             LEFT JOIN store_profiles sp ON sp.user_id = u.id
             WHERE u.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $storeId]);

        return $stmt->fetch() ?: ['id' => $storeId, 'store_name' => 'Shop'];
    }

    private static function storeIdForActor(array $actor): int
    {
        if ($actor['user_type'] === USER_TYPE_STORE_APPROVED) {
            return (int) $actor['id'];
        }

        if ($actor['user_type'] === USER_TYPE_STORE_EMPLOYEE) {
            $stmt = getDB()->prepare(
                'SELECT store_id
                 FROM store_employees
                 WHERE employee_id = :employee_id AND is_active = 1
                 LIMIT 1'
            );
            $stmt->execute([':employee_id' => (int) $actor['id']]);
            $storeId = $stmt->fetchColumn();

            if ($storeId) {
                return (int) $storeId;
            }
        }

        throw new RuntimeException('Không tìm thấy shop cho tài khoản này.');
    }

    private static function moneyScalar(string $sql, array $params = []): float
    {
        $stmt = getDB()->prepare($sql);
        $stmt->execute($params);

        return (float) $stmt->fetchColumn();
    }
}
