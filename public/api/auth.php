<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'me';
$body = json_decode(file_get_contents('php://input') ?: '[]', true);

if (!is_array($body)) {
    $body = [];
}

try {
    if ($method === 'POST' && $action === 'login') {
        $result = AuthController::login((string) ($body['login'] ?? ''), (string) ($body['password'] ?? ''));
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST' && $action === 'register') {
        $result = AuthController::registerBuyer($body);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST' && $action === 'change-password') {
        $user = AuthMiddleware::requireLogin(true);
        $result = AuthController::changePassword(
            (int) $user['id'],
            (string) ($body['current_password'] ?? ''),
            (string) ($body['new_password'] ?? ''),
            (string) ($body['new_password_confirmation'] ?? '')
        );
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST' && $action === 'logout') {
        AuthController::logout();
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'GET' && $action === 'me') {
        $user = AuthMiddleware::requireLogin(true);
        unset($user['password_hash'], $user['password_dev']);
        echo json_encode(['success' => true, 'user' => $user], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Endpoint không ton tai.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.'], JSON_UNESCAPED_UNICODE);
}
