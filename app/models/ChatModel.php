<?php

declare(strict_types=1);

class ChatModel
{
    public static function roomsForActor(array $actor, array $filters = []): array
    {
        [$where, $params] = self::orderWhereForActor($actor);

        if (!empty($filters['search'])) {
            $search = '%' . trim((string) $filters['search']) . '%';
            $where[] = '(o.order_code LIKE :search_order OR buyer.email LIKE :search_buyer OR sp.store_name LIKE :search_store)';
            $params[':search_order'] = $search;
            $params[':search_buyer'] = $search;
            $params[':search_store'] = $search;
        }

        $stmt = getDB()->prepare(
            'SELECT r.*,
                    o.order_code,
                    o.status AS order_status,
                    o.buyer_id,
                    o.store_id,
                    buyer.email AS buyer_email,
                    buyer.full_name AS buyer_name,
                    store_user.email AS store_email,
                    sp.store_name,
                    last_msg.content AS last_message,
                    last_msg.created_at AS last_message_at,
                    SUM(CASE WHEN m.sender_id <> :actor_id_unread AND m.is_read = 0 THEN 1 ELSE 0 END) AS unread_count
             FROM message_rooms r
             JOIN orders o ON o.id = r.order_id
             JOIN users buyer ON buyer.id = o.buyer_id
             JOIN users store_user ON store_user.id = o.store_id
             LEFT JOIN store_profiles sp ON sp.user_id = o.store_id
             LEFT JOIN messages last_msg ON last_msg.id = (
                SELECT m2.id
                FROM messages m2
                WHERE m2.room_id = r.id
                ORDER BY m2.created_at DESC, m2.id DESC
                LIMIT 1
             )
             LEFT JOIN messages m ON m.room_id = r.id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY r.id, o.id, buyer.id, store_user.id, sp.id, last_msg.id
             ORDER BY COALESCE(last_msg.created_at, r.created_at) DESC'
        );
        $stmt->bindValue(':actor_id_unread', (int) $actor['id'], PDO::PARAM_INT);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function ordersForActor(array $actor, string $search = '', int $limit = 60): array
    {
        [$where, $params] = self::orderWhereForActor($actor);

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
                    o.status,
                    buyer.email AS buyer_email,
                    COALESCE(sp.store_name, store_user.email) AS store_name,
                    r.id AS room_id
             FROM orders o
             JOIN users buyer ON buyer.id = o.buyer_id
             JOIN users store_user ON store_user.id = o.store_id
             LEFT JOIN store_profiles sp ON sp.user_id = o.store_id
             LEFT JOIN message_rooms r ON r.order_id = o.id
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

    public static function roomForOrder(int $orderId, array $actor): array
    {
        $order = self::orderById($orderId);

        if (!$order || !self::canActorAccessOrder($actor, $order)) {
            throw new RuntimeException('Bạn không có quyền mo chat cho don này.');
        }

        $stmt = getDB()->prepare('SELECT id FROM message_rooms WHERE order_id = :order_id LIMIT 1');
        $stmt->execute([':order_id' => $orderId]);
        $roomId = $stmt->fetchColumn();

        if (!$roomId) {
            $insert = getDB()->prepare(
                'INSERT INTO message_rooms (order_id, room_type)
                 VALUES (:order_id, :room_type)'
            );
            $insert->execute([
                ':order_id' => $orderId,
                ':room_type' => 'order',
            ]);
            $roomId = getDB()->lastInsertId();
        }

        $room = self::roomDetail((int) $roomId, $actor);

        if (!$room) {
            throw new RuntimeException('Không thể mo room chat.');
        }

        return $room;
    }

    public static function roomDetail(int $roomId, array $actor): ?array
    {
        $room = self::roomWithOrder($roomId);

        if (!$room || !self::canActorAccessOrder($actor, $room)) {
            return null;
        }

        return $room;
    }

    public static function messagesForRoom(int $roomId, array $actor, int $afterId = 0): array
    {
        $room = self::roomDetail($roomId, $actor);

        if (!$room) {
            throw new RuntimeException('Bạn không có quyền xem room chat này.');
        }

        $stmt = getDB()->prepare(
            'SELECT m.*,
                    u.full_name AS sender_name,
                    u.user_type AS sender_type
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             WHERE m.room_id = :room_id AND m.id > :after_id
             ORDER BY m.created_at ASC, m.id ASC'
        );
        $stmt->execute([
            ':room_id' => $roomId,
            ':after_id' => $afterId,
        ]);
        $messages = $stmt->fetchAll();

        self::markRead($roomId, (int) $actor['id']);

        return $messages;
    }

    public static function sendMessage(int $roomId, array $actor, string $content): int
    {
        $room = self::roomDetail($roomId, $actor);

        if (!$room) {
            throw new RuntimeException('Bạn không có quyền gửi tin nhắn trong room này.');
        }

        $content = trim($content);

        if ($content === '') {
            throw new RuntimeException('Noi dung tin nhắn không được de trong.');
        }

        if (mb_strlen($content) > 2000) {
            throw new RuntimeException('Tin nhắn toi đã 2000 ky tu.');
        }

        $stmt = getDB()->prepare(
            'INSERT INTO messages (room_id, sender_id, content, message_type, is_read)
             VALUES (:room_id, :sender_id, :content, :message_type, 0)'
        );
        $stmt->execute([
            ':room_id' => $roomId,
            ':sender_id' => (int) $actor['id'],
            ':content' => $content,
            ':message_type' => 'text',
        ]);

        $messageId = (int) getDB()->lastInsertId();
        NotificationModel::notifyChatMessage($roomId, $messageId, $actor);

        return $messageId;
    }

    public static function sendImageMessage(int $roomId, array $actor, string $imageUrl): int
    {
        $room = self::roomDetail($roomId, $actor);

        if (!$room) {
            throw new RuntimeException('Bạn không có quyền gửi tin nhắn trong room này.');
        }

        if (trim($imageUrl) === '') {
            throw new RuntimeException('URL hình ảnh không hợp lệ.');
        }

        $stmt = getDB()->prepare(
            'INSERT INTO messages (room_id, sender_id, content, message_type, is_read)
             VALUES (:room_id, :sender_id, :content, :message_type, 0)'
        );
        $stmt->execute([
            ':room_id' => $roomId,
            ':sender_id' => (int) $actor['id'],
            ':content' => trim($imageUrl),
            ':message_type' => 'image',
        ]);

        $messageId = (int) getDB()->lastInsertId();
        NotificationModel::notifyChatMessage($roomId, $messageId, $actor);

        return $messageId;
    }

    private static function markRead(int $roomId, int $actorId): void
    {
        $stmt = getDB()->prepare(
            'UPDATE messages
             SET is_read = 1
             WHERE room_id = :room_id AND sender_id <> :actor_id'
        );
        $stmt->execute([
            ':room_id' => $roomId,
            ':actor_id' => $actorId,
        ]);
    }

    private static function orderWhereForActor(array $actor): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (self::isAdminScope($actor)) {
            return [$where, $params];
        }

        if ($actor['user_type'] === USER_TYPE_USER) {
            $where[] = 'o.buyer_id = :buyer_id';
            $params[':buyer_id'] = (int) $actor['id'];

            return [$where, $params];
        }

        $where[] = 'o.store_id = :store_id';
        $params[':store_id'] = self::storeIdForActor($actor);

        return [$where, $params];
    }

    private static function canActorAccessOrder(array $actor, array $order): bool
    {
        if (self::isAdminScope($actor)) {
            return true;
        }

        if ($actor['user_type'] === USER_TYPE_USER) {
            return (int) $order['buyer_id'] === (int) $actor['id'];
        }

        return (int) $order['store_id'] === self::storeIdForActor($actor);
    }

    private static function isAdminScope(array $actor): bool
    {
        return in_array($actor['user_type'], [USER_TYPE_ADMIN, USER_TYPE_SUB_ADMIN_ACTIVE], true);
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

    private static function orderById(int $orderId): ?array
    {
        $stmt = getDB()->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch();

        return $order ?: null;
    }

    private static function roomWithOrder(int $roomId): ?array
    {
        $stmt = getDB()->prepare(
            'SELECT r.*,
                    o.order_code,
                    o.status AS order_status,
                    o.buyer_id,
                    o.store_id,
                    buyer.email AS buyer_email,
                    buyer.full_name AS buyer_name,
                    store_user.email AS store_email,
                    sp.store_name
             FROM message_rooms r
             JOIN orders o ON o.id = r.order_id
             JOIN users buyer ON buyer.id = o.buyer_id
             JOIN users store_user ON store_user.id = o.store_id
             LEFT JOIN store_profiles sp ON sp.user_id = o.store_id
             WHERE r.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $roomId]);
        $room = $stmt->fetch();

        return $room ?: null;
    }
}
