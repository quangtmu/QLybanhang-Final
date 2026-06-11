<?php

declare(strict_types=1);

class StoreRegistrationModel
{
    public static function submit(int $userId, array $data): int
    {
        if (self::hasPendingRequest($userId)) {
            throw new RuntimeException('Ban đang co đơn mở shop cho duyệt.');
        }

        $validated = self::validateRequestData($data);
        $stmt = getDB()->prepare(
            'INSERT INTO store_registration_requests (
                user_id,
                full_name,
                phone,
                cccd,
                cccd_image_url,
                store_name,
                gmail,
                business_license_url,
                product_category,
                sample_products,
                sample_images,
                status
             ) VALUES (
                :user_id,
                :full_name,
                :phone,
                :cccd,
                :cccd_image_url,
                :store_name,
                :gmail,
                :business_license_url,
                :product_category,
                :sample_products,
                :sample_images,
                "pending"
             )'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':full_name' => $validated['full_name'],
            ':phone' => $validated['phone'],
            ':cccd' => $validated['cccd'],
            ':cccd_image_url' => $validated['cccd_image_url'],
            ':store_name' => $validated['store_name'],
            ':gmail' => $validated['gmail'],
            ':business_license_url' => $validated['business_license_url'],
            ':product_category' => $validated['product_category'],
            ':sample_products' => $validated['sample_products'],
            ':sample_images' => $validated['sample_images'],
        ]);

        $requestId = (int) getDB()->lastInsertId();

        NotificationModel::notifyAdminStoreRequested($userId, $validated['store_name']);

        return $requestId;
    }

    public static function myRequests(int $userId): array
    {
        $stmt = getDB()->prepare(
            'SELECT r.*, su.username AS store_username, su.email AS store_email, sp.store_slug
             FROM store_registration_requests r
             LEFT JOIN users su ON su.id = r.store_user_id
             LEFT JOIN store_profiles sp ON sp.user_id = r.store_user_id
             WHERE r.user_id = :user_id
             ORDER BY r.created_at DESC'
        );
        $stmt->execute([':user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public static function paginateAdmin(array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(MAX_PAGE_SIZE, max(1, (int) ($filters['limit'] ?? DEFAULT_PAGE_SIZE)));
        $offset = ($page - 1) * $limit;
        $params = [];
        $where = ['1 = 1'];

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = strtoupper($filters['sort_dir'] ?? 'DESC');
        $allowedSortCols = ['id', 'store_name', 'created_at'];
        if (!in_array($sortBy, $allowedSortCols, true)) {
            $sortBy = 'created_at';
        }
        if (!in_array($sortDir, ['ASC', 'DESC'], true)) {
            $sortDir = 'DESC';
        }
        $orderBy = "r.{$sortBy} {$sortDir}";

        if (!empty($filters['status']) && in_array($filters['status'], ['pending', 'approved', 'rejected'], true)) {
            $where[] = 'r.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string) $filters['search']) . '%';
            $where[] = '(r.store_name LIKE :search_store OR r.gmail LIKE :search_gmail OR r.full_name LIKE :search_name OR u.email LIKE :search_email)';
            $params[':search_store'] = $search;
            $params[':search_gmail'] = $search;
            $params[':search_name'] = $search;
            $params[':search_email'] = $search;
        }

        $whereSql = implode(' AND ', $where);
        $countStmt = getDB()->prepare(
            "SELECT COUNT(*) AS total
             FROM store_registration_requests r
             JOIN users u ON u.id = r.user_id
             WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $stmt = getDB()->prepare(
            "SELECT r.*, u.email AS requester_email, u.username AS requester_username,
                    su.username AS store_username, su.email AS store_email,
                    reviewer.full_name AS reviewer_name
             FROM store_registration_requests r
             JOIN users u ON u.id = r.user_id
             LEFT JOIN users su ON su.id = r.store_user_id
             LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by
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

        return [
            'items' => $stmt->fetchAll(),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => (int) ceil($total / $limit),
            ],
        ];
    }

    public static function find(int $id): ?array
    {
        $stmt = getDB()->prepare(
            'SELECT r.*, u.email AS requester_email, u.username AS requester_username,
                    su.username AS store_username, su.email AS store_email
             FROM store_registration_requests r
             JOIN users u ON u.id = r.user_id
             LEFT JOIN users su ON su.id = r.store_user_id
             WHERE r.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function approve(int $id, int $adminId, ?string $adminNote = null): array
    {
        $db = getDB();
        $db->beginTransaction();

        try {
            $request = self::findForUpdate($id);

            if (!$request) {
                throw new RuntimeException('Không tìm thấy đơn mở shop.');
            }

            if ($request['status'] !== 'pending') {
                throw new RuntimeException('Đơn này đã được xử lý.');
            }

            $storeSlug = self::uniqueStoreSlug(AdminCatalogModel::slugify($request['store_name']));
            $username = self::uniqueUsername($storeSlug);
            $accountEmail = self::accountEmailForStore($request['gmail'], $username);
            $plainPassword = self::temporaryPassword();

            $hasPasswordDev = self::hasUserColumn('password_dev');
            $userStmt = $db->prepare(
                "INSERT INTO users (
                    uuid,
                    username,
                    email,
                    password_hash,
                    " . ($hasPasswordDev ? "password_dev," : "") . "
                    full_name,
                    phone,
                    user_type,
                    is_first_login,
                    email_verified_at,
                    created_by
                ) VALUES (
                    UUID(),
                    :username,
                    :email,
                    :password_hash,
                    " . ($hasPasswordDev ? ":password_dev," : "") . "
                    :full_name,
                    :phone,
                    'store_approved',
                    1,
                    NOW(),
                    :created_by
                )"
            );
            $userStmt->execute([
                ':username' => $username,
                ':email' => $accountEmail,
                ':password_hash' => password_hash($plainPassword, PASSWORD_BCRYPT),
                ...($hasPasswordDev ? [':password_dev' => $plainPassword] : []),
                ':full_name' => $request['full_name'],
                ':phone' => $request['phone'],
                ':created_by' => $adminId,
            ]);
            $storeUserId = (int) $db->lastInsertId();

            $profileStmt = $db->prepare(
                'INSERT INTO store_profiles (
                    user_id,
                    store_name,
                    store_slug,
                    description,
                    product_types,
                    approved_at,
                    approved_by
                ) VALUES (
                    :user_id,
                    :store_name,
                    :store_slug,
                    :description,
                    :product_types,
                    NOW(),
                    :approved_by
                )'
            );
            $profileStmt->execute([
                ':user_id' => $storeUserId,
                ':store_name' => $request['store_name'],
                ':store_slug' => $storeSlug,
                ':description' => $adminNote ?: null,
                ':product_types' => json_encode([$request['product_category']], JSON_UNESCAPED_UNICODE),
                ':approved_by' => $adminId,
            ]);

            $updateStmt = $db->prepare(
                'UPDATE store_registration_requests
                 SET status = "approved",
                     admin_note = :admin_note,
                     store_user_id = :store_user_id,
                     reviewed_by = :reviewed_by,
                     reviewed_at = NOW()
                 WHERE id = :id'
            );
            $updateStmt->execute([
                ':id' => $id,
                ':admin_note' => $adminNote,
                ':store_user_id' => $storeUserId,
                ':reviewed_by' => $adminId,
            ]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        // Notify via Notification system WITH credentials!
        $notificationMessage = "Hồ sơ mở shop {$request['store_name']} đã được duyệt.\n\n";
        $notificationMessage .= "TÀI KHOẢN SHOP CỦA BẠN:\n";
        $notificationMessage .= "- Username: {$username}\n";
        $notificationMessage .= "- Mật khẩu: {$plainPassword}\n\n";
        $notificationMessage .= "Vui lòng đăng xuất và đăng nhập lại bằng tài khoản trên để truy cập Store Portal.";

        NotificationModel::create(
            (int) $request['user_id'],
            'Shop đã được duyệt - Thông tin tài khoản',
            $notificationMessage,
            'store_approved',
            ['store_user_id' => $storeUserId, 'store_name' => $request['store_name'], 'url' => '/login.php']
        );

        return [
            'store_user_id' => $storeUserId,
            'username' => $username,
            'email' => $accountEmail,
            'email_result' => ['status' => 'Notification Sent'],
        ];
    }

    public static function reject(int $id, int $adminId, string $adminNote): array
    {
        $request = self::find($id);

        if (!$request) {
            throw new RuntimeException('Không tìm thấy đơn mở shop.');
        }

        if ($request['status'] !== 'pending') {
            throw new RuntimeException('Đơn này đã được xử lý.');
        }

        if (trim($adminNote) === '') {
            throw new RuntimeException('Vui lòng nhập lý do từ chối.');
        }

        $stmt = getDB()->prepare(
            'UPDATE store_registration_requests
             SET status = "rejected",
                 admin_note = :admin_note,
                 reviewed_by = :reviewed_by,
                 reviewed_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $id,
            ':admin_note' => trim($adminNote),
            ':reviewed_by' => $adminId,
        ]);

        NotificationModel::notifyStoreRejected((int) $request['user_id'], (string) $request['store_name'], trim($adminNote));

        $emailResult = EmailService::send(
            $request['gmail'],
            'Đơn mở shop đã bị từ chối',
            "Xin chào {$request['full_name']},\n\nĐơn mở shop {$request['store_name']} đã bị từ chối.\nLý do: {$adminNote}\n\nVui lòng cập nhật hồ sơ và gửi lại nếu cần.\n"
        );

        return ['email_result' => $emailResult];
    }

    public static function requestStatuses(): array
    {
        return ['pending', 'approved', 'rejected'];
    }

    private static function validateRequestData(array $data): array
    {
        $required = ['full_name', 'phone', 'cccd', 'store_name', 'gmail', 'product_category'];
        $validated = [];

        foreach ($required as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            if ($value === '') {
                throw new RuntimeException('Vui lòng nhap day du thong tin bat buoc.');
            }
            $validated[$field] = $value;
        }

        if (!filter_var($validated['gmail'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Gmail nhan tài khoản shop không hop le.');
        }

        $validated['cccd_image_url'] = trim((string) ($data['cccd_image_url'] ?? '')) ?: null;
        $validated['business_license_url'] = trim((string) ($data['business_license_url'] ?? '')) ?: null;
        $validated['sample_products'] = self::jsonOrNull($data['sample_products'] ?? null);
        $validated['sample_images'] = self::jsonOrNull($data['sample_images'] ?? null);

        return $validated;
    }

    private static function hasPendingRequest(int $userId): bool
    {
        $stmt = getDB()->prepare('SELECT id FROM store_registration_requests WHERE user_id = :user_id AND status = "pending" LIMIT 1');
        $stmt->execute([':user_id' => $userId]);

        return (bool) $stmt->fetch();
    }

    private static function findForUpdate(int $id): ?array
    {
        $stmt = getDB()->prepare('SELECT * FROM store_registration_requests WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private static function jsonOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return json_encode(array_values($value), JSON_UNESCAPED_UNICODE);
        }

        $value = trim((string) $value);
        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }

        return json_encode(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', $value) ?: [])), JSON_UNESCAPED_UNICODE);
    }



    private static function uniqueStoreSlug(string $base): string
    {
        $base = $base !== '' ? $base : 'store';
        $candidate = $base;
        $counter = 2;

        while (self::storeSlugExists($candidate)) {
            $candidate = $base . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }

    private static function storeSlugExists(string $slug): bool
    {
        $stmt = getDB()->prepare('SELECT id FROM store_profiles WHERE store_slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);

        return (bool) $stmt->fetch();
    }


    private static function temporaryPassword(): string
    {
        return 'Shop@' . substr(strtoupper(bin2hex(random_bytes(4))), 0, 8);
    }

    private static function hasUserColumn(string $column): bool
    {
        $stmt = getDB()->prepare(
            "SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'users'
               AND COLUMN_NAME = :column_name"
        );
        $stmt->execute([':column_name' => $column]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private static function uniqueUsername(string $base): string
    {
        $base = preg_replace('/[^a-z0-9_]+/', '_', strtolower($base)) ?: 'store';
        $base = trim($base, '_') ?: 'store';
        $candidate = $base;
        $counter = 2;

        while (UserModel::usernameExists($candidate)) {
            $candidate = $base . '_' . $counter;
            $counter++;
        }

        return $candidate;
    }

    private static function accountEmailForStore(string $gmail, string $username): string
    {
        if (!UserModel::emailExists($gmail)) {
            return $gmail;
        }

        $counter = 1;
        do {
            $email = $username . ($counter === 1 ? '' : $counter) . '@store.local';
            $counter++;
        } while (UserModel::emailExists($email));

        return $email;
    }

}
