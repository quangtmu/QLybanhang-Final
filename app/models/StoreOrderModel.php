<?php

declare(strict_types=1);

class StoreOrderModel
{
    public static function paginate(array $actor, array $filters = []): array
    {
        $filters['store_id'] = StoreEmployeeModel::storeIdForActor($actor);
        return AdminOrderModel::paginate($filters);
    }

    public static function detail(array $actor, int $orderId): ?array
    {
        $storeId = StoreEmployeeModel::storeIdForActor($actor);
        $order = AdminOrderModel::detail($orderId);
        return $order && (int) $order['store_id'] === $storeId ? $order : null;
    }

    public static function requireOrder(array $actor, int $orderId): array
    {
        $order = self::detail($actor, $orderId);
        if (!$order) {
            throw new RuntimeException('Không tìm thấy đơn hàng của shop.');
        }
        return $order;
    }

    public static function confirm(array $actor, int $orderId, string $note = ''): void
    {
        self::transition($actor, $orderId, ORDER_STATUS_CONFIRMED, [ORDER_STATUS_PENDING], trim($note) ?: 'Shop xac nhan đơn hàng');
    }

    public static function startProcessing(array $actor, int $orderId, string $note = ''): void
    {
        self::transition($actor, $orderId, ORDER_STATUS_PROCESSING, [ORDER_STATUS_PENDING, ORDER_STATUS_CONFIRMED], trim($note) ?: 'Shop bat dau xu ly đơn hàng');
    }

    public static function cancel(array $actor, int $orderId, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Vui lòng nhap lý do huy don.');
        }
        $order = self::requireOrder($actor, $orderId);
        if (!in_array($order['status'], [ORDER_STATUS_PENDING, ORDER_STATUS_CONFIRMED], true)) {
            throw new RuntimeException('Shop chi co the huy don khi pending hoac confirmed.');
        }

        $db = getDB();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('UPDATE orders SET status = :status, cancelled_by = :actor_id, cancel_reason = :reason, cancelled_at = NOW() WHERE id = :id AND store_id = :store_id');
            $stmt->execute([
                ':id' => $orderId,
                ':store_id' => StoreEmployeeModel::storeIdForActor($actor),
                ':status' => ORDER_STATUS_CANCELLED,
                ':actor_id' => (int) $actor['id'],
                ':reason' => $reason,
            ]);
            self::restoreVariantStock($orderId);
            self::insertStatusLog($orderId, (string) $order['status'], ORDER_STATUS_CANCELLED, $reason, (int) $actor['id'], (string) $actor['user_type']);
            NotificationModel::notifyOrderStatus($orderId, (string) $order['status'], ORDER_STATUS_CANCELLED, $reason, [(int) $actor['id']]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function createManual(array $actor, array $data): int
    {
        $storeId = StoreEmployeeModel::storeIdForActor($actor);
        $buyerId = self::resolveBuyerId($data);
        $items = self::normalizeItems($data['items'] ?? []);
        $shippingAddress = self::shippingAddress($data);
        $shippingFee = max(0, (float) ($data['shipping_fee'] ?? 0));
        $discountAmount = max(0, (float) ($data['discount_amount'] ?? 0));
        $note = trim((string) ($data['note'] ?? '')) ?: 'Shop tạo don thu cong';
        $db = getDB();
        $db->beginTransaction();
        try {
            $prepared = self::prepareItems($storeId, $items);
            $orderId = self::insertOrder($buyerId, $storeId, $prepared, $shippingAddress, $note, $shippingFee, $discountAmount);
            self::insertOrderItems($orderId, $prepared);
            self::insertStatusLog($orderId, null, ORDER_STATUS_CONFIRMED, 'Shop tạo don thu cong', (int) $actor['id'], (string) $actor['user_type']);
            NotificationModel::notifyOrderCreated($orderId);
            $db->commit();
            return $orderId;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private static function transition(array $actor, int $orderId, string $nextStatus, array $allowedStatuses, string $note): void
    {
        $order = self::requireOrder($actor, $orderId);
        if (!in_array($order['status'], $allowedStatuses, true)) {
            throw new RuntimeException('Trạng thái đơn hàng không cho phep thao tac này.');
        }
        $stmt = getDB()->prepare('UPDATE orders SET status = :status, confirmed_at = CASE WHEN confirmed_at IS NULL THEN NOW() ELSE confirmed_at END WHERE id = :id AND store_id = :store_id');
        $stmt->execute([':id' => $orderId, ':store_id' => StoreEmployeeModel::storeIdForActor($actor), ':status' => $nextStatus]);
        self::insertStatusLog($orderId, (string) $order['status'], $nextStatus, $note, (int) $actor['id'], (string) $actor['user_type']);
        NotificationModel::notifyOrderStatus($orderId, (string) $order['status'], $nextStatus, $note, [(int) $actor['id']]);
    }

    private static function resolveBuyerId(array $data): int
    {
        $buyerId = (int) ($data['buyer_id'] ?? 0);
        if ($buyerId > 0) {
            $stmt = getDB()->prepare('SELECT id FROM users WHERE id = :id AND user_type = :type AND deleted_at IS NULL LIMIT 1');
            $stmt->execute([':id' => $buyerId, ':type' => USER_TYPE_USER]);
            if ($stmt->fetch()) return $buyerId;
        }
        $email = strtolower(trim((string) ($data['buyer_email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Vui lòng nhap buyer_id hoac buyer_email hop le.');
        }
        $stmt = getDB()->prepare('SELECT id FROM users WHERE email = :email AND user_type = :type AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':email' => $email, ':type' => USER_TYPE_USER]);
        $id = $stmt->fetchColumn();
        if (!$id) {
            throw new RuntimeException('Buyer can đang ky truoc khi shop tạo don thu cong.');
        }
        return (int) $id;
    }

    private static function normalizeItems(mixed $items): array
    {
        if (is_string($items)) {
            $decoded = json_decode($items, true);
            $items = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($items) || !$items) {
            throw new RuntimeException('Vui lòng thêm sản phẩm vao don.');
        }
        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $productId = (int) ($item['product_id'] ?? 0);
            $variantId = isset($item['variant_id']) && $item['variant_id'] !== '' ? (int) $item['variant_id'] : null;
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($productId <= 0 || $quantity <= 0) {
                throw new RuntimeException('Sản phẩm va so luong không hop le.');
            }
            $normalized[] = ['product_id' => $productId, 'variant_id' => $variantId, 'quantity' => $quantity];
        }
        return $normalized;
    }

    private static function prepareItems(int $storeId, array $items): array
    {
        $prepared = [];
        foreach ($items as $item) {
            $stmt = getDB()->prepare('SELECT p.id, p.product_code, p.store_id, p.name, p.base_price, p.status, p.deleted_at, p.has_variants, pv.id AS selected_variant_id, pv.type_label, pv.color, pv.size, pv.price AS variant_price, pv.stock_quantity, pv.is_active AS variant_active FROM products p LEFT JOIN product_variants pv ON pv.id = :variant_id AND pv.product_id = p.id WHERE p.id = :product_id AND p.store_id = :store_id LIMIT 1 FOR UPDATE');
            $stmt->execute([':product_id' => $item['product_id'], ':variant_id' => $item['variant_id'], ':store_id' => $storeId]);
            $row = $stmt->fetch();
            if (!$row || $row['status'] !== PRODUCT_STATUS_APPROVED || $row['deleted_at'] !== null) {
                throw new RuntimeException('Sản phẩm shop không khả dụng.');
            }
            if ((int) $row['has_variants'] === 1 && $item['variant_id'] === null) {
                throw new RuntimeException('Vui lòng chọn biến thể sản phẩm.');
            }
            if ($item['variant_id'] !== null) {
                if (!$row['selected_variant_id'] || (int) $row['variant_active'] !== 1 || (int) $row['stock_quantity'] < (int) $item['quantity']) {
                    throw new RuntimeException('Biến thể không khả dụng hoac ton kho không du.');
                }
                getDB()->prepare('UPDATE product_variants SET stock_quantity = stock_quantity - :quantity WHERE id = :id')->execute([':id' => $item['variant_id'], ':quantity' => $item['quantity']]);
            }
            $unitPrice = $item['variant_id'] !== null ? (float) $row['variant_price'] : (float) $row['base_price'];
            $prepared[] = ['product_id' => $item['product_id'], 'variant_id' => $item['variant_id'], 'product_name' => $row['name'], 'product_code' => $row['product_code'], 'type_label' => $row['type_label'], 'color' => $row['color'], 'size' => $row['size'], 'unit_price' => $unitPrice, 'quantity' => $item['quantity'], 'subtotal' => $unitPrice * $item['quantity']];
        }
        return $prepared;
    }

    private static function shippingAddress(array $data): array
    {
        $address = !empty($data['shipping_address']) && is_array($data['shipping_address']) ? $data['shipping_address'] : ['receiver_name' => trim((string) ($data['receiver_name'] ?? '')), 'receiver_phone' => trim((string) ($data['receiver_phone'] ?? '')), 'address_line' => trim((string) ($data['address_line'] ?? '')), 'ward' => trim((string) ($data['ward'] ?? '')), 'district' => trim((string) ($data['district'] ?? '')), 'province' => trim((string) ($data['province'] ?? ''))];
        $address['receiver_name'] = trim((string) ($address['receiver_name'] ?? $address['full_name'] ?? $address['name'] ?? ''));
        $address['receiver_phone'] = trim((string) ($address['receiver_phone'] ?? $address['phone'] ?? ''));
        $address['address_line'] = trim((string) ($address['address_line'] ?? $address['address'] ?? ''));
        if ($address['receiver_name'] === '' || $address['receiver_phone'] === '' || $address['address_line'] === '') {
            throw new RuntimeException('Vui lòng nhap ten, số điện thoại va địa chỉ nhan hang.');
        }
        return $address;
    }

    private static function insertOrder(int $buyerId, int $storeId, array $items, array $shippingAddress, ?string $note, float $shippingFee, float $discountAmount): int
    {
        $totalAmount = array_sum(array_map(fn (array $item): float => (float) $item['subtotal'], $items));
        $finalAmount = max(0, $totalAmount + $shippingFee - $discountAmount);
        $stmt = getDB()->prepare('INSERT INTO orders (order_code, buyer_id, store_id, status, total_amount, shipping_fee, discount_amount, final_amount, shipping_address, note, confirmed_at) VALUES (:order_code, :buyer_id, :store_id, :status, :total_amount, :shipping_fee, :discount_amount, :final_amount, :shipping_address, :note, NOW())');
        $stmt->execute([':order_code' => 'ORD' . date('YmdHis') . strtoupper(bin2hex(random_bytes(3))), ':buyer_id' => $buyerId, ':store_id' => $storeId, ':status' => ORDER_STATUS_CONFIRMED, ':total_amount' => $totalAmount, ':shipping_fee' => $shippingFee, ':discount_amount' => $discountAmount, ':final_amount' => $finalAmount, ':shipping_address' => json_encode($shippingAddress, JSON_UNESCAPED_UNICODE), ':note' => $note]);
        return (int) getDB()->lastInsertId();
    }

    private static function insertOrderItems(int $orderId, array $items): void
    {
        $stmt = getDB()->prepare('INSERT INTO order_items (order_id, product_id, variant_id, product_name, product_code, type_label, color, size, unit_price, quantity, subtotal) VALUES (:order_id, :product_id, :variant_id, :product_name, :product_code, :type_label, :color, :size, :unit_price, :quantity, :subtotal)');
        foreach ($items as $item) {
            $stmt->execute([':order_id' => $orderId, ':product_id' => $item['product_id'], ':variant_id' => $item['variant_id'], ':product_name' => $item['product_name'], ':product_code' => $item['product_code'], ':type_label' => $item['type_label'], ':color' => $item['color'], ':size' => $item['size'], ':unit_price' => $item['unit_price'], ':quantity' => $item['quantity'], ':subtotal' => $item['subtotal']]);
        }
    }

    private static function restoreVariantStock(int $orderId): void
    {
        $stmt = getDB()->prepare('SELECT variant_id, quantity FROM order_items WHERE order_id = :order_id AND variant_id IS NOT NULL');
        $stmt->execute([':order_id' => $orderId]);
        foreach ($stmt->fetchAll() as $item) {
            getDB()->prepare('UPDATE product_variants SET stock_quantity = stock_quantity + :quantity WHERE id = :id')->execute([':id' => (int) $item['variant_id'], ':quantity' => (int) $item['quantity']]);
        }
    }

    private static function insertStatusLog(int $orderId, ?string $oldStatus, string $newStatus, ?string $note, int $updatedBy, string $updatedByRole): void
    {
        $stmt = getDB()->prepare('INSERT INTO order_status_logs (order_id, old_status, new_status, note, updated_by, updated_by_role) VALUES (:order_id, :old_status, :new_status, :note, :updated_by, :updated_by_role)');
        $stmt->execute([':order_id' => $orderId, ':old_status' => $oldStatus, ':new_status' => $newStatus, ':note' => $note, ':updated_by' => $updatedBy, ':updated_by_role' => $updatedByRole]);
    }
}
