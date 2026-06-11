<?php

declare(strict_types=1);

class AdminOrderModel
{
    public static function paginate(array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(MAX_PAGE_SIZE, max(1, (int) ($filters['limit'] ?? DEFAULT_PAGE_SIZE)));
        $offset = ($page - 1) * $limit;
        $params = [];
        $where = ['1 = 1'];

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = strtoupper($filters['sort_dir'] ?? 'DESC');
        $allowedSortCols = ['id', 'order_code', 'total_amount', 'created_at'];
        if (!in_array($sortBy, $allowedSortCols, true)) {
            $sortBy = 'created_at';
        }
        if (!in_array($sortDir, ['ASC', 'DESC'], true)) {
            $sortDir = 'DESC';
        }
        $orderBy = "o.{$sortBy} {$sortDir}";

        if (!empty($filters['status']) && in_array($filters['status'], OrderModel::orderStatuses(), true)) {
            $where[] = 'o.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['store_id'])) {
            $where[] = 'o.store_id = :store_id';
            $params[':store_id'] = (int) $filters['store_id'];
        }

        if (!empty($filters['buyer_id'])) {
            $where[] = 'o.buyer_id = :buyer_id';
            $params[':buyer_id'] = (int) $filters['buyer_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(o.created_at) >= :date_from';
            $params[':date_from'] = (string) $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(o.created_at) <= :date_to';
            $params[':date_to'] = (string) $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string) $filters['search']) . '%';
            $where[] = '(o.order_code LIKE :search_order OR buyer.email LIKE :search_buyer_email OR buyer.full_name LIKE :search_buyer_name OR sp.store_name LIKE :search_store)';
            $params[':search_order'] = $search;
            $params[':search_buyer_email'] = $search;
            $params[':search_buyer_name'] = $search;
            $params[':search_store'] = $search;
        }

        $whereSql = implode(' AND ', $where);
        $countStmt = getDB()->prepare(
            "SELECT COUNT(*)
             FROM orders o
             JOIN users buyer ON buyer.id = o.buyer_id
             JOIN users store ON store.id = o.store_id
             LEFT JOIN store_profiles sp ON sp.user_id = o.store_id
             WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = getDB()->prepare(
            "SELECT o.*,
                    buyer.full_name AS buyer_name,
                    buyer.email AS buyer_email,
                    store.email AS store_email,
                    sp.store_name
             FROM orders o
             JOIN users buyer ON buyer.id = o.buyer_id
             JOIN users store ON store.id = o.store_id
             LEFT JOIN store_profiles sp ON sp.user_id = o.store_id
             WHERE {$whereSql}
             ORDER BY {$orderBy}
             LIMIT :limit OFFSET :offset"
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();

        foreach ($items as &$item) {
            $item['order_items'] = self::itemsForOrder((int) $item['id']);
            $item['shipping_address_data'] = json_decode((string) $item['shipping_address'], true) ?: [];
        }

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => (int) ceil($total / $limit),
            ],
        ];
    }

    public static function detail(int $orderId): ?array
    {
        $stmt = getDB()->prepare(
            'SELECT o.*,
                    buyer.full_name AS buyer_name,
                    buyer.email AS buyer_email,
                    store.email AS store_email,
                    sp.store_name
             FROM orders o
             JOIN users buyer ON buyer.id = o.buyer_id
             JOIN users store ON store.id = o.store_id
             LEFT JOIN store_profiles sp ON sp.user_id = o.store_id
             WHERE o.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            return null;
        }

        $order['order_items'] = self::itemsForOrder($orderId);
        $order['logs'] = self::logsForOrder($orderId);
        $order['shipping_address_data'] = json_decode((string) $order['shipping_address'], true) ?: [];

        return $order;
    }

    public static function cancelByAdmin(int $orderId, int $adminId, string $reason): void
    {
        $order = self::detail($orderId);

        if (!$order) {
            throw new RuntimeException('Không tìm thấy đơn hàng.');
        }

        if ($order['status'] === ORDER_STATUS_CANCELLED) {
            throw new RuntimeException('Đơn hàng này đã bị huy.');
        }

        if (trim($reason) === '') {
            throw new RuntimeException('Vui lòng nhap lý do huy don.');
        }

        $db = getDB();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare(
                'UPDATE orders
                 SET status = :status,
                     cancelled_by = :admin_id,
                     cancel_reason = :reason,
                     cancelled_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                ':id' => $orderId,
                ':admin_id' => $adminId,
                ':status' => ORDER_STATUS_CANCELLED,
                ':reason' => trim($reason),
            ]);

            self::restoreVariantStock($orderId);
            self::cancelShipment($orderId);
            self::insertStatusLog($orderId, (string) $order['status'], ORDER_STATUS_CANCELLED, trim($reason), $adminId, USER_TYPE_ADMIN);
            NotificationModel::notifyOrderStatus($orderId, (string) $order['status'], ORDER_STATUS_CANCELLED, trim($reason), [$adminId]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function updateShippingAddress(int $orderId, array $newAddressData): void
    {
        $stmt = getDB()->prepare(
            'UPDATE orders SET shipping_address = :shipping_address WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $orderId,
            ':shipping_address' => json_encode($newAddressData, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public static function updateOrderStatus(int $orderId, string $newStatus, int $adminId): void
    {
        $order = self::detail($orderId);
        if (!$order || $order['status'] === $newStatus) return;
        
        $stmt = getDB()->prepare('UPDATE orders SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $newStatus, ':id' => $orderId]);
        
        self::insertStatusLog($orderId, (string)$order['status'], $newStatus, 'Admin thay đổi trạng thái', $adminId, USER_TYPE_ADMIN);
        NotificationModel::notifyOrderStatus($orderId, (string)$order['status'], $newStatus, 'Admin cập nhật', [$adminId]);
    }

    public static function updateOrderItems(int $orderId, array $itemsData): void
    {
        $db = getDB();
        $stmt = $db->prepare('UPDATE order_items SET product_name = :name, product_code = :code, type_label = :type_label, color = :color, size = :size, quantity = :qty WHERE id = :item_id AND order_id = :order_id');
        foreach ($itemsData as $itemId => $data) {
            $stmt->execute([
                ':name' => $data['product_name'] ?? '',
                ':code' => $data['product_code'] ?? '',
                ':type_label' => $data['type_label'] ?? '',
                ':color' => $data['color'] ?? '',
                ':size' => $data['size'] ?? '',
                ':qty' => (int)($data['quantity'] ?? 1),
                ':item_id' => (int)$itemId,
                ':order_id' => $orderId
            ]);
        }
    }

    public static function storesForFilter(): array
    {
        $stmt = getDB()->query(
            'SELECT u.id, COALESCE(sp.store_name, u.full_name, u.email) AS store_name, u.email
             FROM users u
             LEFT JOIN store_profiles sp ON sp.user_id = u.id
             WHERE u.user_type IN ("store_pending", "store_approved", "store_suspended")
             ORDER BY store_name ASC'
        );

        return $stmt->fetchAll();
    }

    private static function itemsForOrder(int $orderId): array
    {
        $stmt = getDB()->prepare(
            'SELECT *
             FROM order_items
             WHERE order_id = :order_id
             ORDER BY id ASC'
        );
        $stmt->execute([':order_id' => $orderId]);

        return $stmt->fetchAll();
    }

    private static function logsForOrder(int $orderId): array
    {
        $stmt = getDB()->prepare(
            'SELECT l.*, u.full_name AS updated_by_name
             FROM order_status_logs l
             LEFT JOIN users u ON u.id = l.updated_by
             WHERE l.order_id = :order_id
             ORDER BY l.created_at ASC, l.id ASC'
        );
        $stmt->execute([':order_id' => $orderId]);

        return $stmt->fetchAll();
    }

    private static function restoreVariantStock(int $orderId): void
    {
        $stmt = getDB()->prepare(
            'SELECT variant_id, quantity
             FROM order_items
             WHERE order_id = :order_id AND variant_id IS NOT NULL'
        );
        $stmt->execute([':order_id' => $orderId]);
        $items = $stmt->fetchAll();

        foreach ($items as $item) {
            $update = getDB()->prepare(
                'UPDATE product_variants
                 SET stock_quantity = stock_quantity + :quantity
                 WHERE id = :variant_id'
            );
            $update->execute([
                ':variant_id' => (int) $item['variant_id'],
                ':quantity' => (int) $item['quantity'],
            ]);
        }
    }

    private static function cancelShipment(int $orderId): void
    {
        $stmt = getDB()->prepare(
            'UPDATE shipments
             SET current_status = :status
             WHERE order_id = :order_id'
        );
        $stmt->execute([
            ':order_id' => $orderId,
            ':status' => SHIPMENT_STATUS_CANCELLED,
        ]);
    }

    private static function insertStatusLog(int $orderId, ?string $oldStatus, string $newStatus, ?string $note, ?int $updatedBy, ?string $updatedByRole): void
    {
        $stmt = getDB()->prepare(
            'INSERT INTO order_status_logs (order_id, old_status, new_status, note, updated_by, updated_by_role)
             VALUES (:order_id, :old_status, :new_status, :note, :updated_by, :updated_by_role)'
        );
        $stmt->execute([
            ':order_id' => $orderId,
            ':old_status' => $oldStatus,
            ':new_status' => $newStatus,
            ':note' => $note,
            ':updated_by' => $updatedBy,
            ':updated_by_role' => $updatedByRole,
        ]);
    }
}
