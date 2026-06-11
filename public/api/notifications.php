<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = (string) ($_GET['action'] ?? 'list');
$body = json_decode(file_get_contents('php://input') ?: '[]', true);

if (!is_array($body)) {
    $body = [];
}

try {
    $user = AuthMiddleware::requireLogin(true);
    AuthMiddleware::requireFirstLoginChange($user, true);
    $userId = (int) $user['id'];

    if ($method === 'GET' && $action === 'list') {
        responseJson(['success' => true, 'data' => NotificationModel::listForUser($userId, $_GET)]);
    }

    if ($method === 'GET' && $action === 'unread-count') {
        responseJson(['success' => true, 'unread_count' => NotificationModel::unreadCount($userId)]);
    }

    if ($method === 'POST' && $action === 'mark-read') {
        $notificationId = (int) ($_GET['id'] ?? $body['id'] ?? 0);
        NotificationModel::markRead($notificationId, $userId);
        responseJson(['success' => true]);
    }

    if ($method === 'POST' && $action === 'mark-all-read') {
        $updated = NotificationModel::markAllRead($userId);
        responseJson(['success' => true, 'updated' => $updated]);
    }

    responseJson(['success' => false, 'message' => 'Endpoint không ton tai.'], 404);
} catch (Throwable $e) {
    responseJson(['success' => false, 'message' => APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.'], 500);
}

function responseJson(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
