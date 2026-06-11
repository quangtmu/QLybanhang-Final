<?php

declare(strict_types=1);

class StoreEmployeeModel
{
    public static function modules(): array
    {
        return [
            MODULE_PRODUCTS => 'Sản phẩm',
            MODULE_ORDERS => 'Đơn hàng',
            MODULE_SHIPMENTS => 'Vận đơn',
            MODULE_INVOICES => 'Hóa đơn',
            MODULE_CHAT => 'Chat',
            MODULE_STORE_EMPLOYEES => 'Nhân viên',
        ];
    }

    public static function actions(): array
    {
        return ['view', 'create', 'update', 'delete', 'export', 'approve'];
    }

    public static function listForStore(int $storeId): array
    {
        $stmt = getDB()->prepare(
            'SELECT se.*,
                    u.username,
                    u.email,
                    u.full_name,
                    u.phone,
                    u.last_login_at,
                    u.created_at AS user_created_at
             FROM store_employees se
             JOIN users u ON u.id = se.employee_id
             WHERE se.store_id = :store_id AND u.deleted_at IS NULL
             ORDER BY se.created_at DESC'
        );
        $stmt->execute([':store_id' => $storeId]);
        $employees = $stmt->fetchAll();

        foreach ($employees as &$employee) {
            $employee['permissions_data'] = json_decode((string) ($employee['permissions'] ?? '{}'), true) ?: [];
        }

        return $employees;
    }

    public static function create(array $actor, array $data): int
    {
        $storeId = self::storeIdForActor($actor);
        $payload = self::validateEmployeePayload($data);
        $permissions = self::normalizePermissions($data['permissions'] ?? []);
        $db = getDB();
        $db->beginTransaction();

        try {
            if (UserModel::usernameExists($payload['username'])) {
                throw new RuntimeException('Username đã ton tai.');
            }

            if (UserModel::emailExists($payload['email'])) {
                throw new RuntimeException('Email đã ton tai.');
            }

            $stmt = $db->prepare(
                'INSERT INTO users (
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
                    :user_type,
                    1,
                    NOW(),
                    :created_by
                )'
            );
            $stmt->execute([
                ':username' => $payload['username'],
                ':email' => $payload['email'],
                ':password_hash' => password_hash($payload['password'], PASSWORD_BCRYPT),
                ':full_name' => $payload['full_name'],
                ':phone' => $payload['phone'] ?: null,
                ':user_type' => USER_TYPE_STORE_EMPLOYEE,
                ':created_by' => (int) $actor['id'],
            ]);
            $employeeId = (int) $db->lastInsertId();

            $link = $db->prepare(
                'INSERT INTO store_employees (store_id, employee_id, permissions, is_active, created_by)
                 VALUES (:store_id, :employee_id, :permissions, 1, :created_by)'
            );
            $link->execute([
                ':store_id' => $storeId,
                ':employee_id' => $employeeId,
                ':permissions' => json_encode($permissions, JSON_UNESCAPED_UNICODE),
                ':created_by' => (int) $actor['id'],
            ]);

            $db->commit();

            return $employeeId;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function updatePermissions(array $actor, int $employeeId, array $permissions): void
    {
        $storeId = self::storeIdForActor($actor);
        self::requireStoreEmployee($storeId, $employeeId);
        $normalized = self::normalizePermissions($permissions);
        $stmt = getDB()->prepare(
            'UPDATE store_employees
             SET permissions = :permissions
             WHERE store_id = :store_id AND employee_id = :employee_id'
        );
        $stmt->execute([
            ':store_id' => $storeId,
            ':employee_id' => $employeeId,
            ':permissions' => json_encode($normalized, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public static function setActive(array $actor, int $employeeId, bool $active): void
    {
        $storeId = self::storeIdForActor($actor);
        self::requireStoreEmployee($storeId, $employeeId);
        $stmt = getDB()->prepare(
            'UPDATE store_employees
             SET is_active = :is_active
             WHERE store_id = :store_id AND employee_id = :employee_id'
        );
        $stmt->execute([
            ':store_id' => $storeId,
            ':employee_id' => $employeeId,
            ':is_active' => $active ? 1 : 0,
        ]);
    }

    public static function storeIdForActor(array $actor): int
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

    public static function normalizePermissions(array $rawPermissions): array
    {
        $normalized = [];
        $allowedModules = array_keys(self::modules());
        $allowedActions = self::actions();

        foreach ($allowedModules as $moduleKey) {
            $modulePermissions = $rawPermissions[$moduleKey] ?? [];

            if (!is_array($modulePermissions)) {
                continue;
            }

            foreach ($allowedActions as $action) {
                $value = $modulePermissions[$action] ?? false;

                if ($value === true || $value === '1' || $value === 1 || $value === 'on') {
                    $normalized[$moduleKey][$action] = true;
                }
            }
        }

        return $normalized;
    }

    private static function validateEmployeePayload(array $data): array
    {
        $username = trim((string) ($data['username'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $fullName = trim((string) ($data['full_name'] ?? ''));

        if ($username === '' || !preg_match('/^[a-zA-Z0-9_]{3,100}$/', $username)) {
            throw new RuntimeException('Username tu 3 ky tu, chi dung chu cai, so va dau gach duoi.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Email không hop le.');
        }

        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            throw new RuntimeException('Mật khẩu toi thieu ' . PASSWORD_MIN_LENGTH . ' ky tu.');
        }

        if ($fullName === '') {
            throw new RuntimeException('Vui lòng nhap họ tên nhân viên.');
        }

        return [
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'full_name' => $fullName,
            'phone' => trim((string) ($data['phone'] ?? '')),
        ];
    }

    private static function requireStoreEmployee(int $storeId, int $employeeId): array
    {
        if ($employeeId <= 0) {
            throw new RuntimeException('Thieu employee id.');
        }

        $stmt = getDB()->prepare(
            'SELECT *
             FROM store_employees
             WHERE store_id = :store_id AND employee_id = :employee_id
             LIMIT 1'
        );
        $stmt->execute([
            ':store_id' => $storeId,
            ':employee_id' => $employeeId,
        ]);
        $employee = $stmt->fetch();

        if (!$employee) {
            throw new RuntimeException('Không tìm thấy nhân viên shop.');
        }

        return $employee;
    }
}
