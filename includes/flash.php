<?php

declare(strict_types=1);

function flash_error(): ?string
{
    $message = $_SESSION['flash_error'] ?? null;
    unset($_SESSION['flash_error']);

    return is_string($message) ? $message : null;
}

function flash_success(): ?string
{
    $message = $_SESSION['flash_success'] ?? null;
    unset($_SESSION['flash_success']);

    return is_string($message) ? $message : null;
}
