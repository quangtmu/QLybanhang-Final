<?php

declare(strict_types=1);

class PermissionMiddleware
{
    public static function requireUserType(array|string $allowedTypes, bool $json = false): array
    {
        $user = AuthMiddleware::requireLogin($json);
        AuthMiddleware::requireFirstLoginChange($user, $json);

        $allowedTypes = (array) $allowedTypes;

        if (!in_array($user['user_type'], $allowedTypes, true)) {
            AuthMiddleware::deny('Bạn không có quyền truy cap khu vuc này.', 403, '/login.php', $json);
        }

        return $user;
    }

    public static function requireModule(string $moduleKey, string $action = 'view', bool $json = false): array
    {
        $user = AuthMiddleware::requireLogin($json);
        AuthMiddleware::requireFirstLoginChange($user, $json);

        if (self::can($user, $moduleKey, $action)) {
            return $user;
        }

        AuthMiddleware::deny('Bạn không có quyền thuc hien thao tac này.', 403, '/login.php', $json);
    }

    public static function can(array $user, string $moduleKey, string $action = 'view'): bool
    {
        if ($user['user_type'] === USER_TYPE_ADMIN) {
            return true;
        }

        if ($user['user_type'] === USER_TYPE_SUB_ADMIN_ACTIVE) {
            return self::subAdminCan((int) $user['id'], $moduleKey, $action);
        }

        if ($user['user_type'] === USER_TYPE_STORE_APPROVED) {
            return in_array($moduleKey, [
                MODULE_PRODUCTS,
                MODULE_ORDERS,
                MODULE_SHIPMENTS,
                MODULE_INVOICES,
                MODULE_CHAT,
                MODULE_STORE_EMPLOYEES,
            ], true);
        }

        if ($user['user_type'] === USER_TYPE_STORE_EMPLOYEE) {
            return self::storeEmployeeCan((int) $user['id'], $moduleKey, $action);
        }

        if ($user['user_type'] === USER_TYPE_USER) {
            if ($moduleKey === MODULE_CHAT) {
                return in_array($action, ['view', 'create'], true);
            }

            return in_array($moduleKey, [MODULE_ORDERS, MODULE_INVOICES], true) && $action === 'view';
        }

        return false;
    }

    private static function subAdminCan(int $userId, string $moduleKey, string $action): bool
    {
        $column = self::actionColumn($action);
        $stmt = getDB()->prepare(
            "SELECT {$column} AS allowed
             FROM sub_admin_permissions
             WHERE sub_admin_id = :user_id AND module_key = :module_key
             LIMIT 1"
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':module_key' => $moduleKey,
        ]);
        $row = $stmt->fetch();

        return (bool) ($row['allowed'] ?? false);
    }

    private static function storeEmployeeCan(int $employeeId, string $moduleKey, string $action): bool
    {
        $stmt = getDB()->prepare(
            'SELECT permissions
             FROM store_employees
             WHERE employee_id = :employee_id AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute([':employee_id' => $employeeId]);
        $row = $stmt->fetch();

        if (!$row || empty($row['permissions'])) {
            return false;
        }

        $permissions = json_decode($row['permissions'], true);

        if (!is_array($permissions)) {
            return false;
        }

        $modulePermissions = $permissions[$moduleKey] ?? null;

        if ($modulePermissions === true) {
            return true;
        }

        return is_array($modulePermissions) && (bool) ($modulePermissions[$action] ?? false);
    }

    private static function actionColumn(string $action): string
    {
        return match ($action) {
            'create' => 'can_create',
            'update', 'edit' => 'can_update',
            'delete' => 'can_delete',
            'export' => 'can_export',
            'approve' => 'can_approve',
            default => 'can_view',
        };
    }
}
