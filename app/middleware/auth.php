<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';

$currentUser = AuthMiddleware::requireLogin();
AuthMiddleware::requireFirstLoginChange($currentUser);
