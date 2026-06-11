<?php

declare(strict_types=1);

class NotificationModel
{
    public static function listForUser(int $userId, array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(MAX_PAGE_SIZE, max(1, (int) ($filters['limit'] ?? DEFAULT_PAGE_SIZE)));
        $offset = ($page - 1) * $limit;
        $params = [':user_id' => $userId];
        $where = ['user_id = :user_id'];

        if (isset($filters['is_read']) && $filters['is_read'] !== '') {
            $where[] = 'is_read = :is_read';
            $params[':is_read'] = (int) $filters['is_read'];
        }

        if (!empty($filters['notification_type'])) {
            $where[] = 'notification_type = :notification_type';
            $params[':notification_type'] = trim((string) $filters['notification_type']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string) $filters['search']) . '%';
            $where[] = '(title LIKE :search_title OR content LIKE :search_content)';
            $params[':search_title'] = $search;
            $params[':search_content'] = $search;
        }

        $whereSql = implode(' AND ', $where);
        $countStmt = getDB()->prepare("SELECT COUNT(*) FROM notifications WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = getDB()->prepare(
            "SELECT *
             FROM notifications
             WHERE {$whereSql}
             ORDER BY is_read ASC, created_at DESC, id DESC
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
            $item['data'] = self::decodeData($item['data'] ?? null);
        }

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => (int) ceil($total / $limit),
            ],
            'unread_count' => self::unreadCount($userId),
        ];
    }

    public static function unreadCount(int $userId): int
    {
        $stmt = getDB()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0');
        $stmt->execute([':user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    public static function create(int $userId, string $title, ?string $content = null, string $type = 'system', array $data = []): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $title = trim($title);

        if ($title === '') {
            throw new RuntimeException('Tiêu đề thong bao không được de trong.');
        }

        $stmt = getDB()->prepare(
            'INSERT INTO notifications (user_id, title, content, notification_type, data)
             VALUES (:user_id, :title, :content, :notification_type, :data)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':title' => $title,
            ':content' => self::nullableText($content),
            ':notification_type' => trim($type) ?: 'system',
            ':data' => $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null,
        ]);

        return (int) getDB()->lastInsertId();
    }

    public static function createMany(array $userIds, string $title, ?string $content = null, string $type = 'system', array $data = [], array $excludeUserIds = []): array
    {
        $exclude = array_map('intval', $excludeUserIds);
        $ids = [];

        foreach (array_unique(array_map('intval', $userIds)) as $userId) {
            if ($userId <= 0 || in_array($userId, $exclude, true)) {
                continue;
            }

            $notificationId = self::create($userId, $title, $content, $type, $data);

            if ($notificationId > 0) {
                $ids[] = $notificationId;
            }
        }

        return $ids;
    }

    public static function markRead(int $notificationId, int $userId): void
    {
        $stmt = getDB()->prepare(
            'UPDATE notifications
             SET is_read = 1,
                 read_at = COALESCE(read_at, NOW())
             WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([
            ':id' => $notificationId,
            ':user_id' => $userId,
        ]);
    }

    public static function markAllRead(int $userId): int
    {
        $stmt = getDB()->prepare(
            'UPDATE notifications
             SET is_read = 1,
                 read_at = COALESCE(read_at, NOW())
             WHERE user_id = :user_id AND is_read = 0'
        );
        $stmt->execute([':user_id' => $userId]);

        return $stmt->rowCount();
    }

    public static function notificationTypesForUser(int $userId): array
    {
        $stmt = getDB()->prepare(
            'SELECT DISTINCT notification_type
             FROM notifications
             WHERE user_id = :user_id
             ORDER BY notification_type ASC'
        );
        $stmt->execute([':user_id' => $userId]);

        return array_column($stmt->fetchAll(), 'notification_type');
    }

    public static function notifyOrderCreated(int $orderId): void
    {
        $order = self::orderForNotification($orderId);

        if (!$order) {
            return;
        }

        self::create(
            (int) $order['store_id'],
            'Có đơn hàng mới',
            'Đơn ' . $order['order_code'] . ' đang chờ xử lý.',
            'order_status',
            self::orderData($order)
        );
    }

    public static function notifyOrderStatus(int $orderId, ?string $oldStatus, string $newStatus, ?string $note = null, array $excludeUserIds = []): void
    {
        $order = self::orderForNotification($orderId);

        if (!$order) {
            return;
        }

        $content = 'Đơn ' . $order['order_code'] . ' chuyển từ ' . ($oldStatus ?: 'mới') . ' sang ' . $newStatus . '.';

        if ($note !== null && trim($note) !== '') {
            $content .= ' Ghi chú: ' . trim($note);
        }

        self::createMany(
            [(int) $order['buyer_id'], (int) $order['store_id']],
            'Trạng thái đơn hàng thay đổi',
            $content,
            'order_status',
            self::orderData($order, ['old_status' => $oldStatus, 'new_status' => $newStatus]),
            $excludeUserIds
        );
    }

    public static function notifyChatMessage(int $roomId, int $messageId, array $sender): void
    {
        $room = self::chatRoomForNotification($roomId);

        if (!$room) {
            return;
        }

        $message = self::messageForNotification($messageId);
        $content = $message ? mb_substr((string) $message['content'], 0, 160) : 'Ban co tin nhắn mới.';
        $senderName = trim((string) ($sender['full_name'] ?? $sender['email'] ?? 'Người dùng'));
        $recipients = [(int) $room['buyer_id'], (int) $room['store_id']];

        if (!self::isAdminActor($sender)) {
            $recipients = array_merge($recipients, self::adminIdsForModule(MODULE_CHAT));
        }

        self::createMany(
            $recipients,
            'Tin nhắn mới',
            $senderName . ': ' . $content,
            'chat_message',
            [
                'room_id' => $roomId,
                'message_id' => $messageId,
                'order_id' => (int) $room['order_id'],
                'order_code' => $room['order_code'],
                'url' => '/chat.php?room_id=' . $roomId,
            ],
            [(int) $sender['id']]
        );
    }

    public static function notifyAdminStoreRequested(int $requesterUserId, string $storeName): void
    {
        self::createMany(
            self::adminIdsForModule(MODULE_USERS),
            'Có yêu cầu mở gian hàng mới',
            'Tài khoản #' . $requesterUserId . ' vừa gửi yêu cầu duyệt gian hàng "' . $storeName . '".',
            'admin_store_request',
            ['requester_user_id' => $requesterUserId, 'store_name' => $storeName, 'url' => '/admin/users.php?tab=store']
        );
    }

    public static function notifyAdminProductRequested(int $storeId, string $productName): void
    {
        self::createMany(
            self::adminIdsForModule(MODULE_PRODUCTS),
            'Có sản phẩm mới chờ duyệt',
            'Cửa hàng #' . $storeId . ' vừa gửi yêu cầu duyệt sản phẩm "' . $productName . '".',
            'admin_product_request',
            ['store_id' => $storeId, 'product_name' => $productName, 'url' => '/admin/products.php']
        );
    }

    public static function notifyAdminOrderCreated(int $orderId): void
    {
        $order = self::orderForNotification($orderId);
        if (!$order) {
            return;
        }

        self::createMany(
            self::adminIdsForModule(MODULE_ORDERS),
            'Hệ thống có đơn hàng mới',
            'Đơn hàng ' . $order['order_code'] . ' vừa được tạo trên hệ thống.',
            'admin_order_created',
            self::orderData($order, ['url' => '/admin/orders.php'])
        );
    }

    public static function notifyStoreApproved(int $requesterUserId, int $storeUserId, string $storeName): void
    {
        self::createMany(
            [$requesterUserId, $storeUserId],
            'Shop đã được duyệt',
            'Hồ sơ mo shop ' . $storeName . ' đã được duyệt. Tài khoản shop đã san sang.',
            'store_approved',
            ['store_user_id' => $storeUserId, 'store_name' => $storeName, 'url' => '/login.php']
        );
    }

    public static function notifyStoreRejected(int $requesterUserId, string $storeName, string $reason): void
    {
        self::create(
            $requesterUserId,
            'Đơn mở shop bị từ chối',
            'Hồ sơ mở shop ' . $storeName . ' bị từ chối. Lý do: ' . trim($reason),
            'store_rejected',
            ['store_name' => $storeName, 'url' => '/user/store-registration.php']
        );
    }

    public static function notifyProductApproved(int $storeId, int $productId, string $productName): void
    {
        self::create(
            $storeId,
            'Sản phẩm đã được duyệt',
            'Sản phẩm ' . $productName . ' đã được duyệt va co the hien thi cho buyer.',
            'product_approved',
            ['product_id' => $productId]
        );
    }

    private static function orderForNotification(int $orderId): ?array
    {
        $stmt = getDB()->prepare(
            'SELECT id, order_code, buyer_id, store_id, status
             FROM orders
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch();

        return $order ?: null;
    }

    private static function orderData(array $order, array $extra = []): array
    {
        return array_merge([
            'order_id' => (int) $order['id'],
            'order_code' => $order['order_code'],
            'status' => $order['status'],
        ], $extra);
    }

    private static function chatRoomForNotification(int $roomId): ?array
    {
        $stmt = getDB()->prepare(
            'SELECT r.id, r.order_id, o.order_code, o.buyer_id, o.store_id
             FROM message_rooms r
             JOIN orders o ON o.id = r.order_id
             WHERE r.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $roomId]);
        $room = $stmt->fetch();

        return $room ?: null;
    }

    private static function messageForNotification(int $messageId): ?array
    {
        $stmt = getDB()->prepare('SELECT id, content FROM messages WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $messageId]);
        $message = $stmt->fetch();

        return $message ?: null;
    }

    private static function adminIdsForModule(string $moduleKey): array
    {
        $stmt = getDB()->prepare(
            'SELECT DISTINCT u.id
             FROM users u
             LEFT JOIN sub_admin_permissions p
               ON p.sub_admin_id = u.id
              AND p.module_key = :module_key
             WHERE u.user_type = :admin_type
                OR (u.user_type = :sub_admin_type AND p.can_view = 1)'
        );
        $stmt->execute([
            ':module_key' => $moduleKey,
            ':admin_type' => USER_TYPE_ADMIN,
            ':sub_admin_type' => USER_TYPE_SUB_ADMIN_ACTIVE,
        ]);

        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }

    private static function isAdminActor(array $actor): bool
    {
        return in_array($actor['user_type'] ?? '', [USER_TYPE_ADMIN, USER_TYPE_SUB_ADMIN_ACTIVE], true);
    }

    private static function decodeData(mixed $value): array
    {
        if (!$value) {
            return [];
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function nullableText(?string $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
