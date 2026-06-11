<?php

declare(strict_types=1);

class AdminUserModel
{
    public static function paginate(array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(MAX_PAGE_SIZE, max(1, (int) ($filters['limit'] ?? DEFAULT_PAGE_SIZE)));
        $offset = ($page - 1) * $limit;
        $params = [];
        $where = ['deleted_at IS NULL'];
        
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = strtoupper($filters['sort_dir'] ?? 'DESC');
        
        $allowedSortCols = ['id', 'full_name', 'email', 'login_count', 'last_seen_at', 'created_at'];
        if (!in_array($sortBy, $allowedSortCols, true)) {
            $sortBy = 'created_at';
        }
        if (!in_array($sortDir, ['ASC', 'DESC'], true)) {
            $sortDir = 'DESC';
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string) $filters['search']) . '%';
            $where[] = '(full_name LIKE :search_full_name OR email LIKE :search_email OR username LIKE :search_username OR phone LIKE :search_phone)';
            $params[':search_full_name'] = $search;
            $params[':search_email'] = $search;
            $params[':search_username'] = $search;
            $params[':search_phone'] = $search;
        }

        if (!empty($filters['user_type']) && in_array($filters['user_type'], USER_TYPES, true)) {
            $where[] = 'user_type = :user_type';
            $params[':user_type'] = $filters['user_type'];
        } elseif (!empty($filters['user_types']) && is_array($filters['user_types'])) {
            $placeholders = [];
            foreach ($filters['user_types'] as $idx => $type) {
                if (in_array($type, USER_TYPES, true)) {
                    $placeholder = ':ut_' . $idx;
                    $placeholders[] = $placeholder;
                    $params[$placeholder] = $type;
                }
            }
            if ($placeholders) {
                $where[] = 'user_type IN (' . implode(', ', $placeholders) . ')';
            }
        }

        $whereSql = implode(' AND ', $where);
        $db = getDB();
        $countStmt = $db->prepare("SELECT COUNT(*) AS total FROM users WHERE {$whereSql}");
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $stmt = $db->prepare(
            "SELECT id, uuid, username, email, full_name, phone, avatar_url, user_type,
                    is_first_login, email_verified_at, last_login_at, login_count,
                    is_online, last_seen_at, created_by, created_at, updated_at, deleted_at
             FROM users
             WHERE {$whereSql}
             ORDER BY {$sortBy} {$sortDir}
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

    public static function createSubAdmin(array $data, int $createdBy): int
    {
        $stmt = getDB()->prepare(
            "INSERT INTO users (
                uuid,
                username,
                email,
                password_hash,
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
                :full_name,
                :phone,
                'sub_admin_active',
                1,
                NOW(),
                :created_by
            )"
        );

        $stmt->execute([
            ':username' => $data['username'],
            ':email' => $data['email'],
            ':password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            ':full_name' => $data['full_name'],
            ':phone' => $data['phone'] ?: null,
            ':created_by' => $createdBy,
        ]);

        return (int) getDB()->lastInsertId();
    }

    public static function setUserType(int $id, string $userType): void
    {
        $stmt = getDB()->prepare('UPDATE users SET user_type = :user_type WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([
            ':id' => $id,
            ':user_type' => $userType,
        ]);
    }

    public static function getPermissions(int $subAdminId): array
    {
        $stmt = getDB()->prepare(
            'SELECT module_key, can_view, can_create, can_update, can_delete, can_export, can_approve
             FROM sub_admin_permissions
             WHERE sub_admin_id = :sub_admin_id
             ORDER BY module_key'
        );
        $stmt->execute([':sub_admin_id' => $subAdminId]);

        return $stmt->fetchAll();
    }

    public static function replacePermissions(int $subAdminId, array $permissions): void
    {
        $db = getDB();
        $db->beginTransaction();

        try {
            $deleteStmt = $db->prepare('DELETE FROM sub_admin_permissions WHERE sub_admin_id = :sub_admin_id');
            $deleteStmt->execute([':sub_admin_id' => $subAdminId]);

            $insertStmt = $db->prepare(
                'INSERT INTO sub_admin_permissions (
                    sub_admin_id,
                    module_key,
                    can_view,
                    can_create,
                    can_update,
                    can_delete,
                    can_export,
                    can_approve
                ) VALUES (
                    :sub_admin_id,
                    :module_key,
                    :can_view,
                    :can_create,
                    :can_update,
                    :can_delete,
                    :can_export,
                    :can_approve
                )'
            );

            foreach ($permissions as $row) {
                $moduleKey = (string) ($row['module_key'] ?? '');

                if (!in_array($moduleKey, self::moduleKeys(), true)) {
                    continue;
                }

                $insertStmt->execute([
                    ':sub_admin_id' => $subAdminId,
                    ':module_key' => $moduleKey,
                    ':can_view' => !empty($row['can_view']) ? 1 : 0,
                    ':can_create' => !empty($row['can_create']) ? 1 : 0,
                    ':can_update' => !empty($row['can_update']) ? 1 : 0,
                    ':can_delete' => !empty($row['can_delete']) ? 1 : 0,
                    ':can_export' => !empty($row['can_export']) ? 1 : 0,
                    ':can_approve' => !empty($row['can_approve']) ? 1 : 0,
                ]);
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function moduleKeys(): array
    {
        return [
            MODULE_ADMIN_DASHBOARD,
            MODULE_USERS,
            MODULE_STORES,
            MODULE_PRODUCTS,
            MODULE_CATEGORIES,
            MODULE_TAGS,
            MODULE_BANNERS,
            MODULE_ORDERS,
            MODULE_SHIPMENTS,
            MODULE_INVOICES,
            MODULE_CHAT,
            MODULE_STORE_EMPLOYEES,
            MODULE_CONFIGS,
        ];
    }

    public static function transitionForLock(array $user): ?string
    {
        return match ($user['user_type']) {
            USER_TYPE_SUB_ADMIN_ACTIVE => USER_TYPE_SUB_ADMIN_INACTIVE,
            USER_TYPE_STORE_PENDING, USER_TYPE_STORE_APPROVED => USER_TYPE_STORE_SUSPENDED,
            USER_TYPE_USER => USER_TYPE_USER_BANNED,
            default => null,
        };
    }

    public static function transitionForUnlock(array $user): ?string
    {
        return match ($user['user_type']) {
            USER_TYPE_SUB_ADMIN_INACTIVE => USER_TYPE_SUB_ADMIN_ACTIVE,
            USER_TYPE_STORE_SUSPENDED => USER_TYPE_STORE_APPROVED,
            USER_TYPE_USER_BANNED => USER_TYPE_USER,
            default => null,
        };
    }
}
