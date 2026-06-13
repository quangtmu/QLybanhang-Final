<?php

declare(strict_types=1);

class ShipmentModel
{
    public static function statuses(): array
    {
        return [
            SHIPMENT_STATUS_WAITING_PICKUP,
            SHIPMENT_STATUS_PICKED_UP,
            SHIPMENT_STATUS_IN_TRANSIT,
            SHIPMENT_STATUS_OUT_FOR_DELIVERY,
            SHIPMENT_STATUS_DELIVERED,
            SHIPMENT_STATUS_CANCELLED,
        ];
    }

    public static function paginate(array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(MAX_PAGE_SIZE, max(1, (int) ($filters['limit'] ?? DEFAULT_PAGE_SIZE)));
        $offset = ($page - 1) * $limit;
        $params = [];
        $where = ['1 = 1'];

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = strtoupper($filters['sort_dir'] ?? 'DESC');
        $allowedSortCols = ['id', 'tracking_code', 'created_at', 'estimated_date'];
        if (!in_array($sortBy, $allowedSortCols, true)) {
            $sortBy = 'created_at';
        }
        if (!in_array($sortDir, ['ASC', 'DESC'], true)) {
            $sortDir = 'DESC';
        }
        $orderBy = "s.{$sortBy} {$sortDir}";

        if (!empty($filters['status']) && in_array($filters['status'], self::statuses(), true)) {
            $where[] = 's.current_status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['order_status']) && in_array($filters['order_status'], OrderModel::orderStatuses(), true)) {
            $where[] = 'o.status = :order_status';
            $params[':order_status'] = $filters['order_status'];
        }

        if (!empty($filters['store_id'])) {
            $where[] = 'o.store_id = :store_id';
            $params[':store_id'] = (int) $filters['store_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(s.created_at) >= :date_from';
            $params[':date_from'] = (string) $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(s.created_at) <= :date_to';
            $params[':date_to'] = (string) $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string) $filters['search']) . '%';
            $where[] = '(s.tracking_code LIKE :search_tracking OR s.carrier_name LIKE :search_carrier OR o.order_code LIKE :search_order OR buyer.email LIKE :search_buyer OR sp.store_name LIKE :search_store)';
            $params[':search_tracking'] = $search;
            $params[':search_carrier'] = $search;
            $params[':search_order'] = $search;
            $params[':search_buyer'] = $search;
            $params[':search_store'] = $search;
        }

        $whereSql = implode(' AND ', $where);
        $countStmt = getDB()->prepare(
            "SELECT COUNT(*)
             FROM shipments s
             JOIN orders o ON o.id = s.order_id
             JOIN users buyer ON buyer.id = o.buyer_id
             JOIN users store_user ON store_user.id = o.store_id
             LEFT JOIN store_profiles sp ON sp.user_id = o.store_id
             WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = getDB()->prepare(
            "SELECT s.*,
                    o.order_code,
                    o.store_id,
                    o.status AS order_status,
                    o.final_amount,
                    o.shipping_address,
                    buyer.full_name AS buyer_name,
                    buyer.email AS buyer_email,
                    store_user.email AS store_email,
                    sp.store_name
             FROM shipments s
             JOIN orders o ON o.id = s.order_id
             JOIN users buyer ON buyer.id = o.buyer_id
             JOIN users store_user ON store_user.id = o.store_id
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
            $item['logs'] = self::logsForShipment((int) $item['id']);
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

    public static function detail(int $shipmentId): ?array
    {
        $stmt = getDB()->prepare(
            'SELECT s.*,
                    o.order_code,
                    o.store_id,
                    o.status AS order_status,
                    o.final_amount,
                    o.shipping_address,
                    buyer.full_name AS buyer_name,
                    buyer.email AS buyer_email,
                    store_user.email AS store_email,
                    sp.store_name
             FROM shipments s
             JOIN orders o ON o.id = s.order_id
             JOIN users buyer ON buyer.id = o.buyer_id
             JOIN users store_user ON store_user.id = o.store_id
             LEFT JOIN store_profiles sp ON sp.user_id = o.store_id
             WHERE s.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $shipmentId]);
        $shipment = $stmt->fetch();

        if (!$shipment) {
            return null;
        }

        $shipment['logs'] = self::logsForShipment($shipmentId);
        $shipment['shipping_address_data'] = json_decode((string) $shipment['shipping_address'], true) ?: [];

        return $shipment;
    }

    public static function ordersWithoutShipment(string $search = '', int $limit = 50): array
    {
        $params = [];
        $where = [
            's.id IS NULL',
            'o.status NOT IN ("cancelled", "refunded", "delivered")',
        ];

        if (trim($search) !== '') {
            $keyword = '%' . trim($search) . '%';
            $where[] = '(o.order_code LIKE :search_order OR buyer.email LIKE :search_buyer OR sp.store_name LIKE :search_store)';
            $params[':search_order'] = $keyword;
            $params[':search_buyer'] = $keyword;
            $params[':search_store'] = $keyword;
        }

        $stmt = getDB()->prepare(
            'SELECT o.id,
                    o.order_code,
                    o.store_id,
                    o.status,
                    o.final_amount,
                    buyer.email AS buyer_email,
                    COALESCE(sp.store_name, store_user.email) AS store_name
             FROM orders o
             LEFT JOIN shipments s ON s.order_id = o.id
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

    public static function create(array $data, int $actorId, string $actorRole): int
    {
        $orderId = (int) ($data['order_id'] ?? 0);

        if ($orderId <= 0) {
            throw new RuntimeException('Vui lòng chọn đơn hàng.');
        }

        $status = (string) ($data['current_status'] ?? SHIPMENT_STATUS_WAITING_PICKUP);

        if (!in_array($status, self::statuses(), true)) {
            throw new RuntimeException('Trạng thái vận đơn không hop le.');
        }

        $db = getDB();
        $db->beginTransaction();

        try {
            $order = self::lockOrder($orderId);

            if (!$order) {
                throw new RuntimeException('Không tìm thấy đơn hàng.');
            }

            if (in_array($order['status'], [ORDER_STATUS_CANCELLED, ORDER_STATUS_REFUNDED, ORDER_STATUS_DELIVERED], true)) {
                throw new RuntimeException('Đơn hàng này không thể tạo vận đơn.');
            }

            if (self::shipmentExistsForOrder($orderId)) {
                throw new RuntimeException('Đơn hàng đã co vận đơn.');
            }

            $trackingCode = trim((string) ($data['tracking_code'] ?? '')) ?: self::generateTrackingCode();
            self::ensureTrackingCodeAvailable($trackingCode);

            $stmt = $db->prepare(
                'INSERT INTO shipments (
                    order_id,
                    tracking_code,
                    carrier_name,
                    current_status,
                    estimated_date,
                    shipper_name,
                    shipper_phone,
                    proof_image_url
                ) VALUES (
                    :order_id,
                    :tracking_code,
                    :carrier_name,
                    :current_status,
                    :estimated_date,
                    :shipper_name,
                    :shipper_phone,
                    :proof_image_url
                )'
            );
            $stmt->execute([
                ':order_id' => $orderId,
                ':tracking_code' => $trackingCode,
                ':carrier_name' => self::nullableText($data['carrier_name'] ?? null),
                ':current_status' => $status,
                ':estimated_date' => self::nullableDate($data['estimated_date'] ?? null),
                ':shipper_name' => self::nullableText($data['shipper_name'] ?? null),
                ':shipper_phone' => self::nullableText($data['shipper_phone'] ?? null),
                ':proof_image_url' => self::nullableText($data['proof_image_url'] ?? null),
            ]);
            $shipmentId = (int) $db->lastInsertId();

            self::insertShipmentLog($shipmentId, $status, 'Tạo vận đơn', $actorId, $actorRole);
            self::syncOrderStatus($orderId, (string) $order['status'], $status, $actorId, $actorRole);

            $db->commit();

            return $shipmentId;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function updateInfo(int $shipmentId, array $data, int $actorId, string $actorRole): void
    {
        $db = getDB();
        $db->beginTransaction();

        try {
            $shipment = self::lockShipment($shipmentId);

            if (!$shipment) {
                throw new RuntimeException('Không tìm thấy vận đơn.');
            }

            $trackingCode = trim((string) ($data['tracking_code'] ?? $shipment['tracking_code']));

            if ($trackingCode === '') {
                throw new RuntimeException('Ma vận đơn không được de trong.');
            }

            self::ensureTrackingCodeAvailable($trackingCode, $shipmentId);

            $stmt = $db->prepare(
                'UPDATE shipments
                 SET tracking_code = :tracking_code,
                     carrier_name = :carrier_name,
                     estimated_date = :estimated_date,
                     shipper_name = :shipper_name,
                     shipper_phone = :shipper_phone
                 WHERE id = :id'
            );
            $stmt->execute([
                ':id' => $shipmentId,
                ':tracking_code' => $trackingCode,
                ':carrier_name' => self::nullableText($data['carrier_name'] ?? null),
                ':estimated_date' => self::nullableDate($data['estimated_date'] ?? null),
                ':shipper_name' => self::nullableText($data['shipper_name'] ?? null),
                ':shipper_phone' => self::nullableText($data['shipper_phone'] ?? null),
            ]);

            self::insertShipmentLog($shipmentId, (string) $shipment['current_status'], 'Cập nhật thong tin vận đơn', $actorId, $actorRole);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function attachProofImage(int $shipmentId, string $imageUrl, int $actorId, string $actorRole): void
    {
        $imageUrl = trim($imageUrl);
        if ($imageUrl === '') {
            return;
        }

        $shipment = self::detail($shipmentId);
        if (!$shipment) {
            throw new RuntimeException('Không tìm thấy vận đơn.');
        }

        $stmt = getDB()->prepare('UPDATE shipments SET proof_image_url = :proof_image_url WHERE id = :id');
        $stmt->execute([
            ':id' => $shipmentId,
            ':proof_image_url' => $imageUrl,
        ]);

        self::insertShipmentLog($shipmentId, (string) $shipment['current_status'], 'Cập nhật ảnh vận đơn', $actorId, $actorRole);
    }

    public static function updateStatus(int $shipmentId, string $status, string $note, int $actorId, string $actorRole): void
    {
        if (!in_array($status, self::statuses(), true)) {
            throw new RuntimeException('Trạng thái vận đơn không hop le.');
        }

        $db = getDB();
        $db->beginTransaction();

        try {
            $shipment = self::lockShipmentWithOrder($shipmentId);

            if (!$shipment) {
                throw new RuntimeException('Không tìm thấy vận đơn.');
            }

            $stmt = $db->prepare(
                'UPDATE shipments
                 SET current_status = :status
                 WHERE id = :id'
            );
            $stmt->execute([
                ':id' => $shipmentId,
                ':status' => $status,
            ]);

            self::insertShipmentLog($shipmentId, $status, trim($note) ?: null, $actorId, $actorRole);
            self::syncOrderStatus((int) $shipment['order_id'], (string) $shipment['order_status'], $status, $actorId, $actorRole);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private static function lockOrder(int $orderId): ?array
    {
        $stmt = getDB()->prepare(
            'SELECT *
             FROM orders
             WHERE id = :id
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch();

        return $order ?: null;
    }

    private static function lockShipment(int $shipmentId): ?array
    {
        $stmt = getDB()->prepare(
            'SELECT *
             FROM shipments
             WHERE id = :id
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([':id' => $shipmentId]);
        $shipment = $stmt->fetch();

        return $shipment ?: null;
    }

    private static function lockShipmentWithOrder(int $shipmentId): ?array
    {
        $stmt = getDB()->prepare(
            'SELECT s.*, o.status AS order_status
             FROM shipments s
             JOIN orders o ON o.id = s.order_id
             WHERE s.id = :id
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([':id' => $shipmentId]);
        $shipment = $stmt->fetch();

        return $shipment ?: null;
    }

    private static function shipmentExistsForOrder(int $orderId): bool
    {
        $stmt = getDB()->prepare('SELECT id FROM shipments WHERE order_id = :order_id LIMIT 1');
        $stmt->execute([':order_id' => $orderId]);

        return (bool) $stmt->fetch();
    }

    private static function logsForShipment(int $shipmentId): array
    {
        $stmt = getDB()->prepare(
            'SELECT l.*, u.full_name AS updated_by_name
             FROM shipment_status_logs l
             JOIN users u ON u.id = l.updated_by
             WHERE l.shipment_id = :shipment_id
             ORDER BY l.created_at ASC, l.id ASC'
        );
        $stmt->execute([':shipment_id' => $shipmentId]);

        return $stmt->fetchAll();
    }

    private static function insertShipmentLog(int $shipmentId, string $status, ?string $note, int $actorId, string $actorRole): void
    {
        $stmt = getDB()->prepare(
            'INSERT INTO shipment_status_logs (shipment_id, status, note, updated_by, updated_by_role)
             VALUES (:shipment_id, :status, :note, :updated_by, :updated_by_role)'
        );
        $stmt->execute([
            ':shipment_id' => $shipmentId,
            ':status' => $status,
            ':note' => $note,
            ':updated_by' => $actorId,
            ':updated_by_role' => $actorRole,
        ]);
    }

    private static function syncOrderStatus(int $orderId, string $currentOrderStatus, string $shipmentStatus, int $actorId, string $actorRole): void
    {
        $nextStatus = self::nextOrderStatus($currentOrderStatus, $shipmentStatus);

        if ($nextStatus === null || $nextStatus === $currentOrderStatus) {
            return;
        }

        $stmt = getDB()->prepare(
            'UPDATE orders
             SET status = :status,
                 confirmed_at = CASE WHEN confirmed_at IS NULL AND :status_confirmed IN ("confirmed", "processing", "shipped", "delivering", "delivered") THEN NOW() ELSE confirmed_at END,
                 delivered_at = CASE WHEN :status_delivered = "delivered" THEN NOW() ELSE delivered_at END
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $orderId,
            ':status' => $nextStatus,
            ':status_confirmed' => $nextStatus,
            ':status_delivered' => $nextStatus,
        ]);

        if ($nextStatus === ORDER_STATUS_DELIVERED && $currentOrderStatus !== ORDER_STATUS_DELIVERED) {
            self::incrementSoldCount($orderId);
            require_once __DIR__ . '/LoyaltyModel.php';
            LoyaltyModel::addPointsForOrder($orderId);
        }

        self::insertOrderStatusLog(
            $orderId,
            $currentOrderStatus,
            $nextStatus,
            'Dong bo tu vận đơn: ' . $shipmentStatus,
            $actorId,
            $actorRole
        );
        NotificationModel::notifyOrderStatus($orderId, $currentOrderStatus, $nextStatus, 'Dong bo tu vận đơn: ' . $shipmentStatus, [$actorId]);
    }

    private static function nextOrderStatus(string $currentOrderStatus, string $shipmentStatus): ?string
    {
        if (in_array($currentOrderStatus, [ORDER_STATUS_CANCELLED, ORDER_STATUS_REFUNDED], true)) {
            return null;
        }

        return match ($shipmentStatus) {
            SHIPMENT_STATUS_WAITING_PICKUP => in_array($currentOrderStatus, [ORDER_STATUS_PENDING, ORDER_STATUS_CONFIRMED], true)
                ? ORDER_STATUS_PROCESSING
                : null,
            SHIPMENT_STATUS_PICKED_UP,
            SHIPMENT_STATUS_IN_TRANSIT => $currentOrderStatus === ORDER_STATUS_DELIVERED ? null : ORDER_STATUS_SHIPPED,
            SHIPMENT_STATUS_OUT_FOR_DELIVERY => $currentOrderStatus === ORDER_STATUS_DELIVERED ? null : ORDER_STATUS_DELIVERING,
            SHIPMENT_STATUS_DELIVERED => ORDER_STATUS_DELIVERED,
            default => null,
        };
    }

    private static function insertOrderStatusLog(int $orderId, ?string $oldStatus, string $newStatus, ?string $note, int $updatedBy, string $updatedByRole): void
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

    private static function incrementSoldCount(int $orderId): void
    {
        $stmt = getDB()->prepare(
            'SELECT product_id, quantity
             FROM order_items
             WHERE order_id = :order_id'
        );
        $stmt->execute([':order_id' => $orderId]);
        $items = $stmt->fetchAll();

        foreach ($items as $item) {
            $update = getDB()->prepare(
                'UPDATE products
                 SET sold_count = sold_count + :quantity
                 WHERE id = :product_id'
            );
            $update->execute([
                ':product_id' => (int) $item['product_id'],
                ':quantity' => (int) $item['quantity'],
            ]);
        }
    }

    private static function ensureTrackingCodeAvailable(string $trackingCode, ?int $ignoreShipmentId = null): void
    {
        $params = [':tracking_code' => $trackingCode];
        $where = 'tracking_code = :tracking_code';

        if ($ignoreShipmentId !== null) {
            $where .= ' AND id <> :id';
            $params[':id'] = $ignoreShipmentId;
        }

        $stmt = getDB()->prepare("SELECT id FROM shipments WHERE {$where} LIMIT 1");
        $stmt->execute($params);

        if ($stmt->fetch()) {
            throw new RuntimeException('Ma vận đơn đã ton tai.');
        }
    }

    private static function nullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

    private static function nullableDate(mixed $value): ?string
    {
        $date = trim((string) ($value ?? ''));

        if ($date === '') {
            return null;
        }

        $parsed = DateTime::createFromFormat('Y-m-d', $date);

        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new RuntimeException('Ngay du kien giao không hop le.');
        }

        return $date;
    }

    private static function generateTrackingCode(): string
    {
        do {
            $code = 'SHP-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $stmt = getDB()->prepare('SELECT id FROM shipments WHERE tracking_code = :code LIMIT 1');
            $stmt->execute([':code' => $code]);
        } while ($stmt->fetch());

        return $code;
    }
}
