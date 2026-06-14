<?php

declare(strict_types=1);

class StorageService
{
    public static function publicUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        $baseUrl = rtrim((string) env('STORAGE_PUBLIC_URL', STORAGE_PUBLIC_URL), '/');

        return $baseUrl . '/' . ltrim($path, '/');
    }

    public static function storeUploadedFile(array $file, string $directory, array $allowedMimeTypes, int $maxSizeBytes, string $prefix = 'file'): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Vui lòng upload file hop le.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxSizeBytes) {
            throw new RuntimeException('File upload vuot qua gioi han dung luong.');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $mimeType = self::detectMimeType($tmpPath);

        if ($mimeType === null || !isset($allowedMimeTypes[$mimeType])) {
            throw new RuntimeException('Dinh đang file upload không được ho tro.');
        }

        $provider = strtolower((string) env('STORAGE_PROVIDER', STORAGE_PROVIDER));
        $extension = $allowedMimeTypes[$mimeType];
        $filename = $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
        $relativePath = trim($directory, '/') . '/' . $filename;

        if ($provider === 'r2') {
            return self::storeUploadedFileToR2($tmpPath, $relativePath, $mimeType, $size);
        }

        if ($provider !== 'local') {
            throw new RuntimeException('Storage provider hiện tại chưa được hỗ trợ.');
        }

        $relativeDirectory = trim($directory, '/');
        $root = rtrim((string) env('STORAGE_LOCAL_PUBLIC_DIR', STORAGE_LOCAL_PUBLIC_DIR), '/');
        if (!str_starts_with($root, '/')) {
            $root = BASE_PATH . '/' . $root;
        }
        $targetDirectory = $root . '/' . $relativeDirectory;

        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException('Không thể tạo thu muc upload.');
        }

        $target = $targetDirectory . '/' . $filename;

        if (!move_uploaded_file($tmpPath, $target)) {
            throw new RuntimeException('Không thể luu file upload.');
        }

        $meta = [
            'path' => $relativePath,
            'url' => self::publicUrl($relativePath),
            'mime_type' => $mimeType,
            'size' => $size,
        ];

        $imageInfo = @getimagesize($target);
        if ($imageInfo) {
            $meta['width'] = (int) $imageInfo[0];
            $meta['height'] = (int) $imageInfo[1];
        }

        return $meta;
    }

    public static function deletePublicFile(string $urlOrPath): void
    {
        $baseUrl = rtrim((string) env('STORAGE_PUBLIC_URL', STORAGE_PUBLIC_URL), '/');
        $path = $urlOrPath;

        if ($baseUrl !== '' && str_starts_with($path, $baseUrl . '/')) {
            $path = substr($path, strlen($baseUrl) + 1);
        } elseif (str_starts_with($path, '/uploads/')) {
            $path = substr($path, strlen('/uploads/'));
        } elseif (str_starts_with($path, STORAGE_PUBLIC_URL . '/')) {
            $path = substr($path, strlen(STORAGE_PUBLIC_URL) + 1);
        }

        if (str_contains($path, '..') || str_starts_with($path, '/') || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        $root = rtrim((string) env('STORAGE_LOCAL_PUBLIC_DIR', STORAGE_LOCAL_PUBLIC_DIR), '/');
        if (!str_starts_with($root, '/')) {
            $root = BASE_PATH . '/' . $root;
        }
        $fullPath = $root . '/' . ltrim($path, '/');

        if (is_file($fullPath)) {
            unlink($fullPath);
        }
    }

    private static function detectMimeType(string $path): ?string
    {
        if ($path === '' || !is_file($path)) {
            return null;
        }

        $info = @getimagesize($path);
        if ($info && !empty($info['mime'])) {
            return (string) $info['mime'];
        }

        if (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($path);
            return is_string($mimeType) ? $mimeType : null;
        }

        return null;
    }

    private static function storeUploadedFileToR2(string $tmpPath, string $relativePath, string $mimeType, int $size): array
    {
        $accessKey = (string) env('R2_ACCESS_KEY_ID', '');
        $secretKey = (string) env('R2_SECRET_ACCESS_KEY', '');
        $bucket = (string) env('R2_BUCKET', '');
        $endpoint = (string) env('R2_ENDPOINT', '');

        if ($accessKey === '' || $secretKey === '' || $bucket === '' || $endpoint === '') {
            throw new RuntimeException('Thiếu cấu hình R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY, R2_BUCKET hoặc R2_ENDPOINT.');
        }

        $body = file_get_contents($tmpPath);
        if ($body === false) {
            throw new RuntimeException('Không thể đọc file upload.');
        }

        $endpoint = rtrim($endpoint, '/');
        if (str_ends_with($endpoint, '/' . $bucket)) {
            $endpoint = substr($endpoint, 0, -strlen('/' . $bucket));
        }

        $encodedKey = implode('/', array_map('rawurlencode', explode('/', ltrim($relativePath, '/'))));
        $url = $endpoint . '/' . rawurlencode($bucket) . '/' . $encodedKey;
        self::putS3Object($url, $body, $mimeType, $accessKey, $secretKey);

        $meta = [
            'path' => $relativePath,
            'url' => self::publicUrl($relativePath),
            'mime_type' => $mimeType,
            'size' => $size,
        ];

        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo) {
            $meta['width'] = (int) $imageInfo[0];
            $meta['height'] = (int) $imageInfo[1];
        }

        return $meta;
    }

    private static function putS3Object(string $url, string $body, string $mimeType, string $accessKey, string $secretKey): void
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Máy chủ PHP chưa bật cURL để upload lên R2.');
        }

        $parts = parse_url($url);
        $host = (string) ($parts['host'] ?? '');
        $path = (string) ($parts['path'] ?? '/');
        $amzDate = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $payloadHash = hash('sha256', $body);
        $service = 's3';
        $region = 'auto';
        $credentialScope = "{$date}/{$region}/{$service}/aws4_request";

        $headers = [
            'content-type' => $mimeType,
            'host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $amzDate,
        ];

        $canonicalHeaders = '';
        foreach ($headers as $name => $value) {
            $canonicalHeaders .= $name . ':' . trim($value) . "\n";
        }
        $signedHeaders = implode(';', array_keys($headers));
        $canonicalRequest = "PUT\n{$path}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);
        $signingKey = self::signatureKey($secretKey, $date, $region, $service);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $curlHeaders = [
            'Authorization: AWS4-HMAC-SHA256 Credential=' . $accessKey . '/' . $credentialScope . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature,
            'Content-Type: ' . $mimeType,
            'Host: ' . $host,
            'x-amz-content-sha256: ' . $payloadHash,
            'x-amz-date: ' . $amzDate,
            'Expect:',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 45,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $status < 200 || $status >= 300) {
            $logDir = BASE_PATH . '/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0775, true);
            }
            $logMsg = date('Y-m-d H:i:s') . " - R2 Upload Failed:\nURL: {$url}\nStatus: {$status}\ncURL Error: {$error}\nResponse: " . (is_string($response) ? substr($response, 0, 1000) : 'false') . "\n---\n";
            @file_put_contents($logDir . '/r2_upload_errors.log', $logMsg, FILE_APPEND);
            error_log($logMsg); // Also log to stderr so it shows in Railway logs
            $excerpt = is_string($response) ? substr($response, 0, 200) : '';
            throw new RuntimeException("Upload lên R2 thất bại (HTTP {$status}). {$error}. Phản hồi: {$excerpt}");
        }
    }

    private static function signatureKey(string $key, string $date, string $region, string $service): string
    {
        $kDate = hash_hmac('sha256', $date, 'AWS4' . $key, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }
}
