<?php

declare(strict_types=1);

class OrderModel
{
    public static function create(int $buyerId, array $data): array
    {
        $selectedCartItemIds = self::normalizeIdList($data['selected_cart_item_ids'] ?? []);
        if (!empty($data['items']) && is_array($data['items'])) {
            $items = self::normalizeRequestedItems($data['items']);
        } elseif ($selectedCartItemIds) {
            $items = self::cartItems($buyerId, $selectedCartItemIds);
        } else {
            $items = self::cartItems($buyerId);
        }

        if (!$items) {
            throw new RuntimeException('Không co sản phẩm de dat hang.');
        }

        $shippingAddress = self::shippingAddress($data);
        $note = trim((string) ($data['note'] ?? '')) ?: null;

        $preparedItems = self::prepareItemsForOrder($items);
        $totalItemsAmount = array_reduce($preparedItems, fn($c, $i) => $c + $i['subtotal'], 0);

        require_once __DIR__ . '/LoyaltyModel.php';
        $loyaltyInfo = LoyaltyModel::getLoyaltyInfo($buyerId);
        $pointsAvailable = (int) $loyaltyInfo['current_points'];
        $pointsToUse = 0;
        $loyaltyDiscount = 0;
        
        if (!empty($data['use_loyalty_points']) && $pointsAvailable > 0 && $totalItemsAmount > 0) {
            $maxPointsNeeded = ceil($totalItemsAmount / LoyaltyModel::VND_PER_POINT);
            $pointsToUse = (int) min($pointsAvailable, $maxPointsNeeded);
            $loyaltyDiscount = $pointsToUse * LoyaltyModel::VND_PER_POINT;
        }
        
        $voucherCode = trim((string) ($data['voucher_code'] ?? ''));
        $voucherInfo = null;
        $globalVoucherDiscount = 0;
        if ($voucherCode !== '') {
            require_once __DIR__ . '/VoucherModel.php';
            $voucherInfo = VoucherModel::findByCode($voucherCode);
            if (!$voucherInfo) {
                throw new RuntimeException('Mã giảm giá không hợp lệ.');
            }
            if ($voucherInfo['store_id'] === null) {
                $globalVoucherDiscount = VoucherModel::validateAndCalculateDiscount($voucherInfo, $totalItemsAmount, null, $buyerId);
            }
        }

        $shippingMethod = $data['shipping_method'] ?? 'standard';
        $baseShippingFee = $shippingMethod === 'express' ? 50000.0 : 30000.0;
        $createdOrders = [];
        $db = getDB();
        $db->beginTransaction();

        try {
            $groups = self::groupByStore($preparedItems);
            
            $pointsRemainingToDeduct = $pointsToUse;

            foreach ($groups as $storeId => $groupItems) {
                $groupTotal = array_reduce($groupItems, fn($c, $i) => $c + $i['subtotal'], 0);
                $ratio = $totalItemsAmount > 0 ? ($groupTotal / $totalItemsAmount) : 0;
                
                $orderShipping = round($baseShippingFee * $ratio);
                
                $groupDiscount = 0;
                if ($voucherInfo) {
                    if ($voucherInfo['store_id'] === null) {
                        $groupDiscount += round($globalVoucherDiscount * $ratio);
                    } elseif ((int)$voucherInfo['store_id'] === (int)$storeId) {
                        $groupDiscount += VoucherModel::validateAndCalculateDiscount($voucherInfo, $groupTotal, (int)$storeId, $buyerId);
                    }
                }
                
                $orderDiscount = $groupDiscount + round($loyaltyDiscount * $ratio);
                $orderPointsUsed = round($pointsToUse * $ratio);
                
                $orderId = self::insertOrder(
                    $buyerId,
                    (int) $storeId,
                    $groupItems,
                    $shippingAddress,
                    $note,
                    $orderShipping,
                    $orderDiscount
                );
                
                if ($orderPointsUsed > 0) {
                    LoyaltyModel::usePoints($buyerId, (int) $orderPointsUsed, $orderId);
                    $pointsRemainingToDeduct -= $orderPointsUsed;
                }

                self::insertOrderItems($orderId, $groupItems);
                self::insertStatusLog($orderId, null, ORDER_STATUS_PENDING, 'Buyer dat hang', $buyerId, USER_TYPE_USER);
                NotificationModel::notifyOrderCreated($orderId);
                NotificationModel::notifyAdminOrderCreated($orderId);

                $createdOrders[] = self::detail($buyerId, $orderId);
            }
            
            if ($pointsRemainingToDeduct > 0 && !empty($createdOrders)) {
                 LoyaltyModel::usePoints($buyerId, (int) $pointsRemainingToDeduct, (int) $createdOrders[0]['id']);
            }
            
            if ($voucherInfo) {
                 VoucherModel::incrementUsage((int) $voucherInfo['id']);
            }

            if ($selectedCartItemIds) {
                self::removeCartItems($buyerId, $selectedCartItemIds);
            } elseif (empty($data['items'])) {
                self::clearCart($buyerId);
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return $createdOrders;
    }

    public static function paginateForBuyer(int $buyerId, array $filters = []): array
    {
        $params = [':buyer_id' => $buyerId];
        $where = ['o.buyer_id = :buyer_id'];

        if (!empty($filters['status']) && in_array($filters['status'], self::orderStatuses(), true)) {
            $where[] = 'o.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string) $filters['search']) . '%';
            $where[] = '(o.order_code LIKE :search_order OR sp.store_name LIKE :search_store)';
            $params[':search_order'] = $search;
            $params[':search_store'] = $search;
        }

        $whereClause = implode(' AND ', $where);

        $countStmt = getDB()->prepare(
            'SELECT COUNT(*)
             FROM orders o
             LEFT JOIN store_profiles sp ON sp.user_id = o.store_id
             WHERE ' . $whereClause
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = max(1, (int) ($filters['limit'] ?? 20));
        $offset = ($page - 1) * $limit;
        $totalPages = (int) ceil($total / $limit);

        $sortBy = in_array($filters['sort_by'] ?? '', ['created_at', 'total_amount'], true) ? $filters['sort_by'] : 'created_at';
        $sortDir = in_array(strtoupper($filters['sort_dir'] ?? ''), ['ASC', 'DESC'], true) ? strtoupper($filters['sort_dir']) : 'DESC';

        $stmt = getDB()->prepare(
            'SELECT o.*, sp.store_name
             FROM orders o
             LEFT JOIN store_profiles sp ON sp.user_id = o.store_id
             WHERE ' . $whereClause . '
             ORDER BY o.' . $sortBy . ' ' . $sortDir . '
             LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        foreach ($orders as &$order) {
            $order['items'] = self::itemsForOrder((int) $order['id']);
            $order['shipping_address_data'] = json_decode((string) $order['shipping_address'], true) ?: [];
        }

        return [
            'items' => $orders,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public static function detail(int $buyerId, int $orderId): ?array
    {
        $stmt = getDB()->prepare(
            'SELECT o.*, sp.store_name
             FROM orders o
             LEFT JOIN store_profiles sp ON sp.user_id = o.store_id
             WHERE o.id = :id AND o.buyer_id = :buyer_id
             LIMIT 1'
        );
        $stmt->execute([
            ':id' => $orderId,
            ':buyer_id' => $buyerId,
        ]);
        $order = $stmt->fetch();

        if (!$order) {
            return null;
        }

        $order['items'] = self::itemsForOrder($orderId);
        $order['logs'] = self::logsForOrder($orderId);
        $order['shipping_address_data'] = json_decode((string) $order['shipping_address'], true) ?: [];

        return $order;
    }

    public static function cancel(int $buyerId, int $orderId, string $reason = ''): void
    {
        $order = self::requireBuyerOrder($buyerId, $orderId);

        if ($order['status'] !== ORDER_STATUS_PENDING) {
            throw new RuntimeException('Chi co the huy don khi đang pending.');
        }

        $db = getDB();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare(
                'UPDATE orders
                 SET status = :status,
                     cancelled_by = :cancelled_by,
                     cancel_reason = :reason,
                     cancelled_at = NOW()
                 WHERE id = :id AND buyer_id = :buyer_id'
            );
            $stmt->execute([
                ':id' => $orderId,
                ':buyer_id' => $buyerId,
                ':cancelled_by' => $buyerId,
                ':status' => ORDER_STATUS_CANCELLED,
                ':reason' => trim($reason) ?: 'Buyer huy don',
            ]);

            self::restoreVariantStock($orderId);
            self::insertStatusLog($orderId, ORDER_STATUS_PENDING, ORDER_STATUS_CANCELLED, trim($reason) ?: 'Buyer huy don', $buyerId, USER_TYPE_USER);
            NotificationModel::notifyOrderStatus($orderId, ORDER_STATUS_PENDING, ORDER_STATUS_CANCELLED, trim($reason) ?: 'Buyer huy don', [$buyerId]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function markReceived(int $buyerId, int $orderId): void
    {
        $order = self::requireBuyerOrder($buyerId, $orderId);

        if (!in_array($order['status'], [ORDER_STATUS_SHIPPED, ORDER_STATUS_DELIVERING], true)) {
            throw new RuntimeException('Chi xac nhan đã nhan khi don đang van chuyen.');
        }

        $db = getDB();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare(
                'UPDATE orders
                 SET status = :status, delivered_at = NOW()
                 WHERE id = :id AND buyer_id = :buyer_id'
            );
            $stmt->execute([
                ':id' => $orderId,
                ':buyer_id' => $buyerId,
                ':status' => ORDER_STATUS_DELIVERED,
            ]);

            self::incrementSoldCount($orderId);
            self::markShipmentDelivered($orderId);
            self::insertStatusLog($orderId, $order['status'], ORDER_STATUS_DELIVERED, 'Buyer xac nhan đã nhan hang', $buyerId, USER_TYPE_USER);
            NotificationModel::notifyOrderStatus($orderId, (string) $order['status'], ORDER_STATUS_DELIVERED, 'Buyer xac nhan đã nhan hang', [$buyerId]);

            require_once __DIR__ . '/LoyaltyModel.php';
            LoyaltyModel::addPointsForOrder($orderId);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function orderStatuses(): array
    {
        return [
            ORDER_STATUS_PENDING,
            ORDER_STATUS_CONFIRMED,
            ORDER_STATUS_PROCESSING,
            ORDER_STATUS_SHIPPED,
            ORDER_STATUS_DELIVERING,
            ORDER_STATUS_DELIVERED,
            ORDER_STATUS_CANCELLED,
            ORDER_STATUS_REFUNDING,
            ORDER_STATUS_REFUNDED,
        ];
    }

    private static function normalizeRequestedItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $variantId = isset($item['variant_id']) && $item['variant_id'] !== '' ? (int) $item['variant_id'] : null;
            $quantity = (int) ($item['quantity'] ?? 1);

            if ($productId <= 0 || $quantity <= 0) {
                throw new RuntimeException('Thong tin sản phẩm dat hang không hop le.');
            }

            $normalized[] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity' => $quantity,
            ];
        }

        return $normalized;
    }

    private static function normalizeIdList($value): array
    {
        $values = is_array($value) ? $value : [$value];
        $ids = [];

        foreach ($values as $item) {
            $id = (int) $item;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private static function cartItems(int $buyerId, array $cartItemIds = []): array
    {
        $params = [':buyer_id' => $buyerId];
        $idFilter = '';

        if ($cartItemIds) {
            $placeholders = [];
            foreach ($cartItemIds as $index => $cartItemId) {
                $key = ':cart_item_id_' . $index;
                $placeholders[] = $key;
                $params[$key] = (int) $cartItemId;
            }
            $idFilter = ' AND ci.id IN (' . implode(', ', $placeholders) . ')';
        }

        $stmt = getDB()->prepare(
            'SELECT ci.product_id, ci.variant_id, ci.quantity
             FROM carts c
             INNER JOIN cart_items ci ON ci.cart_id = c.id
             WHERE c.buyer_id = :buyer_id
             ' . $idFilter . '
             ORDER BY ci.created_at ASC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private static function prepareItemsForOrder(array $items): array
    {
        $prepared = [];

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $variantId = isset($item['variant_id']) && $item['variant_id'] !== '' ? (int) $item['variant_id'] : null;
            $quantity = (int) $item['quantity'];

            $stmt = getDB()->prepare(
                'SELECT p.id, p.product_code, p.store_id, p.name, p.base_price, p.status, p.deleted_at, p.has_variants, p.stock_quantity AS base_stock,
                        pv.id AS selected_variant_id, pv.type_label, pv.color, pv.size,
                        pv.price AS variant_price, pv.stock_quantity, pv.is_active AS variant_active
                 FROM products p
                 LEFT JOIN product_variants pv ON pv.id = :variant_id AND pv.product_id = p.id
                 WHERE p.id = :product_id
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute([
                ':product_id' => $productId,
                ':variant_id' => $variantId,
            ]);
            $row = $stmt->fetch();

            if (!$row || $row['status'] !== PRODUCT_STATUS_APPROVED || $row['deleted_at'] !== null) {
                throw new RuntimeException('Sản phẩm không khả dụng.');
            }

            if ((int) $row['has_variants'] === 1 && $variantId === null) {
                throw new RuntimeException('Vui lòng chọn biến thể sản phẩm.');
            }

            if ($variantId !== null) {
                if (!$row['selected_variant_id'] || (int) $row['variant_active'] !== 1) {
                    throw new RuntimeException('Biến thể sản phẩm không khả dụng.');
                }

                if ((int) $row['stock_quantity'] < $quantity) {
                    throw new RuntimeException('Tồn kho của biến thể này không đủ so với số lượng bạn yêu cầu ');
                }

                $stockStmt = getDB()->prepare(
                    'UPDATE product_variants
                     SET stock_quantity = stock_quantity - :quantity
                     WHERE id = :variant_id'
                );
                $stockStmt->execute([
                    ':variant_id' => $variantId,
                    ':quantity' => $quantity,
                ]);
            } else {
                if ((int) $row['base_stock'] < $quantity) {
                    throw new RuntimeException('Ton kho sản phẩm không du so luong.');
                }

                $stockStmt = getDB()->prepare(
                    'UPDATE products
                     SET stock_quantity = stock_quantity - :quantity
                     WHERE id = :product_id'
                );
                $stockStmt->execute([
                    ':product_id' => $productId,
                    ':quantity' => $quantity,
                ]);
            }

            $unitPrice = $variantId !== null ? (float) $row['variant_price'] : (float) $row['base_price'];

            require_once __DIR__ . '/FlashSaleModel.php';
            if (class_exists('FlashSaleModel')) {
                $activeFlashSale = FlashSaleModel::getActiveFlashSale();
                if ($activeFlashSale) {
                    $fsProducts = FlashSaleModel::getProducts((int) $activeFlashSale['id']);
                    foreach ($fsProducts as $fsp) {
                        if ((int)$fsp['product_id'] === (int)$productId) {
                            $unitPrice = (float) $fsp['discount_price'];
                            break;
                        }
                    }
                }
            }

            $prepared[] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'store_id' => (int) $row['store_id'],
                'product_name' => $row['name'],
                'product_code' => $row['product_code'],
                'type_label' => $row['type_label'],
                'color' => $row['color'],
                'size' => $row['size'],
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'subtotal' => $unitPrice * $quantity,
            ];
        }

        return $prepared;
    }

    private static function groupByStore(array $items): array
    {
        $groups = [];

        foreach ($items as $item) {
            $groups[(int) $item['store_id']][] = $item;
        }

        return $groups;
    }

    private static function insertOrder(int $buyerId, int $storeId, array $items, array $shippingAddress, ?string $note, float $shippingFee, float $discountAmount): int
    {
        $totalAmount = array_sum(array_map(fn (array $item): float => (float) $item['subtotal'], $items));
        $finalAmount = max(0, $totalAmount + $shippingFee - $discountAmount);
        $stmt = getDB()->prepare(
            'INSERT INTO orders (
                order_code,
                buyer_id,
                store_id,
                status,
                total_amount,
                shipping_fee,
                discount_amount,
                final_amount,
                shipping_address,
                note
            ) VALUES (
                :order_code,
                :buyer_id,
                :store_id,
                :status,
                :total_amount,
                :shipping_fee,
                :discount_amount,
                :final_amount,
                :shipping_address,
                :note
            )'
        );
        $stmt->execute([
            ':order_code' => self::generateOrderCode(),
            ':buyer_id' => $buyerId,
            ':store_id' => $storeId,
            ':status' => ORDER_STATUS_PENDING,
            ':total_amount' => $totalAmount,
            ':shipping_fee' => $shippingFee,
            ':discount_amount' => $discountAmount,
            ':final_amount' => $finalAmount,
            ':shipping_address' => json_encode($shippingAddress, JSON_UNESCAPED_UNICODE),
            ':note' => $note,
        ]);

        return (int) getDB()->lastInsertId();
    }

    private static function insertOrderItems(int $orderId, array $items): void
    {
        $stmt = getDB()->prepare(
            'INSERT INTO order_items (
                order_id,
                product_id,
                variant_id,
                product_name,
                product_code,
                type_label,
                color,
                size,
                unit_price,
                quantity,
                subtotal
            ) VALUES (
                :order_id,
                :product_id,
                :variant_id,
                :product_name,
                :product_code,
                :type_label,
                :color,
                :size,
                :unit_price,
                :quantity,
                :subtotal
            )'
        );

        foreach ($items as $item) {
            $stmt->execute([
                ':order_id' => $orderId,
                ':product_id' => $item['product_id'],
                ':variant_id' => $item['variant_id'],
                ':product_name' => $item['product_name'],
                ':product_code' => $item['product_code'],
                ':type_label' => $item['type_label'],
                ':color' => $item['color'],
                ':size' => $item['size'],
                ':unit_price' => $item['unit_price'],
                ':quantity' => $item['quantity'],
                ':subtotal' => $item['subtotal'],
            ]);
        }
    }

    private static function shippingAddress(array $data): array
    {
        if (!empty($data['shipping_address']) && is_array($data['shipping_address'])) {
            $address = $data['shipping_address'];
        } else {
            $address = [
                'receiver_name' => trim((string) ($data['receiver_name'] ?? '')),
                'receiver_phone' => trim((string) ($data['receiver_phone'] ?? '')),
                'address_line' => trim((string) ($data['address_line'] ?? '')),
                'ward' => trim((string) ($data['ward'] ?? '')),
                'district' => trim((string) ($data['district'] ?? '')),
                'province' => trim((string) ($data['province'] ?? '')),
            ];
        }

        $address['receiver_name'] = trim((string) ($address['receiver_name'] ?? $address['full_name'] ?? $address['name'] ?? ''));
        $address['receiver_phone'] = trim((string) ($address['receiver_phone'] ?? $address['phone'] ?? ''));
        $address['address_line'] = trim((string) ($address['address_line'] ?? $address['address'] ?? ''));

        if ($address['receiver_name'] === '' || $address['receiver_phone'] === '' || $address['address_line'] === '') {
            throw new RuntimeException('Vui lòng nhap ten, số điện thoại va địa chỉ nhan hang.');
        }

        return $address;
    }

    private static function itemsForOrder(int $orderId): array
    {
        $stmt = getDB()->prepare(
            'SELECT oi.*, p.main_image_url
             FROM order_items oi
             LEFT JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = :order_id
             ORDER BY oi.id ASC'
        );
        $stmt->execute([':order_id' => $orderId]);

        $items = $stmt->fetchAll();
        require_once __DIR__ . '/../controllers/StorageService.php';
        foreach ($items as &$item) {
            if (!empty($item['main_image_url'])) {
                $item['main_image_url'] = StorageService::publicUrl($item['main_image_url']);
            }
        }
        return $items;
    }

    private static function logsForOrder(int $orderId): array
    {
        $stmt = getDB()->prepare(
            'SELECT *
             FROM order_status_logs
             WHERE order_id = :order_id
             ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute([':order_id' => $orderId]);

        return $stmt->fetchAll();
    }

    private static function requireBuyerOrder(int $buyerId, int $orderId): array
    {
        $order = self::detail($buyerId, $orderId);

        if (!$order) {
            throw new RuntimeException('Không tìm thấy đơn hàng.');
        }

        return $order;
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

    private static function restoreVariantStock(int $orderId): void
    {
        $stmt = getDB()->prepare(
            'SELECT product_id, variant_id, quantity
             FROM order_items
             WHERE order_id = :order_id'
        );
        $stmt->execute([':order_id' => $orderId]);
        $items = $stmt->fetchAll();

        foreach ($items as $item) {
            if ($item['variant_id'] !== null) {
                $update = getDB()->prepare(
                    'UPDATE product_variants
                     SET stock_quantity = stock_quantity + :quantity
                     WHERE id = :variant_id'
                );
                $update->execute([
                    ':variant_id' => (int) $item['variant_id'],
                    ':quantity' => (int) $item['quantity'],
                ]);
            } else {
                $update = getDB()->prepare(
                    'UPDATE products
                     SET stock_quantity = stock_quantity + :quantity
                     WHERE id = :product_id'
                );
                $update->execute([
                    ':product_id' => (int) $item['product_id'],
                    ':quantity' => (int) $item['quantity'],
                ]);
            }
        }
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

    private static function markShipmentDelivered(int $orderId): void
    {
        $stmt = getDB()->prepare(
            'UPDATE shipments
             SET current_status = :status
             WHERE order_id = :order_id'
        );
        $stmt->execute([
            ':order_id' => $orderId,
            ':status' => SHIPMENT_STATUS_DELIVERED,
        ]);
    }

    private static function clearCart(int $buyerId): void
    {
        $stmt = getDB()->prepare(
            'DELETE ci
             FROM carts c
             INNER JOIN cart_items ci ON ci.cart_id = c.id
             WHERE c.buyer_id = :buyer_id'
        );
        $stmt->execute([':buyer_id' => $buyerId]);
    }

    private static function removeCartItems(int $buyerId, array $cartItemIds): void
    {
        $cartItemIds = self::normalizeIdList($cartItemIds);
        if (!$cartItemIds) {
            return;
        }

        $params = [':buyer_id' => $buyerId];
        $placeholders = [];
        foreach ($cartItemIds as $index => $cartItemId) {
            $key = ':cart_item_id_' . $index;
            $placeholders[] = $key;
            $params[$key] = (int) $cartItemId;
        }

        $stmt = getDB()->prepare(
            'DELETE ci
             FROM carts c
             INNER JOIN cart_items ci ON ci.cart_id = c.id
             WHERE c.buyer_id = :buyer_id
               AND ci.id IN (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);
    }

    private static function generateOrderCode(): string
    {
        do {
            $code = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $stmt = getDB()->prepare('SELECT id FROM orders WHERE order_code = :code LIMIT 1');
            $stmt->execute([':code' => $code]);
        } while ($stmt->fetch());

        return $code;
    }
}
