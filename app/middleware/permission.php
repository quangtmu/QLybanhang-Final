<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';

function require_user_type(array|string $allowedTypes, bool $json = false): array
{
    return PermissionMiddleware::requireUserType($allowedTypes, $json);
}

function require_module_permission(string $moduleKey, string $action = 'view', bool $json = false): array
{
    return PermissionMiddleware::requireModule($moduleKey, $action, $json);
}
