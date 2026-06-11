<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/models/ChatModel.php';

try {
    $roomId = 3;
    $actor = ['id' => 1, 'user_type' => 'admin'];
    $messageId = ChatModel::sendImageMessage($roomId, $actor, 'https://example.com/test.jpg');
    echo "Success: $messageId\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
