<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = (string) ($_GET['action'] ?? 'rooms');
$roomId = (int) ($_GET['room_id'] ?? $_GET['id'] ?? 0);
$body = json_decode(file_get_contents('php://input') ?: '[]', true);

if (!is_array($body)) {
    $body = [];
}

try {
    $actor = PermissionMiddleware::requireModule(MODULE_CHAT, $action === 'send' ? 'create' : 'view', true);

    if ($method === 'GET' && $action === 'rooms') {
        responseJson(['success' => true, 'data' => ChatModel::roomsForActor($actor, $_GET)]);
    }

    if ($method === 'GET' && $action === 'orders') {
        responseJson(['success' => true, 'data' => ChatModel::ordersForActor($actor, (string) ($_GET['search'] ?? ''), (int) ($_GET['limit'] ?? 60))]);
    }

    if ($method === 'POST' && $action === 'room') {
        $room = ChatModel::roomForOrder((int) ($body['order_id'] ?? 0), $actor);
        responseJson(['success' => true, 'data' => $room]);
    }

    if ($method === 'GET' && $action === 'messages') {
        responseJson(['success' => true, 'data' => ChatModel::messagesForRoom($roomId, $actor, (int) ($_GET['after_id'] ?? 0))]);
    }

    if ($method === 'POST' && $action === 'send') {
        $messageId = ChatModel::sendMessage($roomId, $actor, (string) ($body['content'] ?? ''));
        responseJson(['success' => true, 'id' => $messageId]);
    }

    if ($method === 'POST' && $action === 'upload_image') {
        require_once __DIR__ . '/../../app/controllers/StorageService.php';
        
        if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
             throw new RuntimeException('Vui lòng chọn một ảnh hợp lệ.');
        }
        
        $meta = StorageService::storeUploadedFile(
            $_FILES['image'],
            'chat_images',
            ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
            5242880, // 5MB
            'chat'
        );
        $imageUrl = $meta['url'];
        $messageId = ChatModel::sendImageMessage($roomId, $actor, $imageUrl);
        
        responseJson(['success' => true, 'id' => $messageId, 'url' => $imageUrl]);
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
