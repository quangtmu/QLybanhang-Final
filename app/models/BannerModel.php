<?php

declare(strict_types=1);

class BannerModel
{
    private const MAX_IMAGE_SIZE = 5242880;
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public static function all(array $filters = []): array
    {
        $params = [];
        $where = ['1 = 1'];

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $where[] = 'b.is_active = :is_active';
            $params[':is_active'] = (int) $filters['is_active'];
        }

        if (!empty($filters['search'])) {
            $where[] = 'b.title LIKE :search';
            $params[':search'] = '%' . trim((string) $filters['search']) . '%';
        }

        $stmt = getDB()->prepare(
            'SELECT b.*, u.full_name AS created_by_name
             FROM banners b
             JOIN users u ON u.id = b.created_by
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY b.position ASC, b.created_at DESC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function active(): array
    {
        $stmt = getDB()->prepare(
            'SELECT id, title, image_url, link_url, width, height, position
             FROM banners
             WHERE is_active = 1
               AND (display_from IS NULL OR display_from <= NOW())
               AND (display_to IS NULL OR display_to >= NOW())
             ORDER BY position ASC, created_at DESC'
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function create(array $data, array $file, int $adminId): int
    {
        $title = trim((string) ($data['title'] ?? ''));

        if ($title === '') {
            throw new RuntimeException('Vui lòng nhap tiêu đề banner.');
        }

        $image = self::storeUploadedImage($file);
        $stmt = getDB()->prepare(
            'INSERT INTO banners (
                title,
                image_url,
                link_url,
                position,
                width,
                height,
                is_active,
                display_from,
                display_to,
                created_by
            ) VALUES (
                :title,
                :image_url,
                :link_url,
                :position,
                :width,
                :height,
                :is_active,
                :display_from,
                :display_to,
                :created_by
            )'
        );
        $stmt->execute([
            ':title' => $title,
            ':image_url' => $image['url'],
            ':link_url' => self::nullableUrl($data['link_url'] ?? null),
            ':position' => (int) ($data['position'] ?? 0),
            ':width' => $image['width'],
            ':height' => $image['height'],
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':display_from' => self::nullableDateTime($data['display_from'] ?? null),
            ':display_to' => self::nullableDateTime($data['display_to'] ?? null),
            ':created_by' => $adminId,
        ]);

        return (int) getDB()->lastInsertId();
    }

    public static function update(int $id, array $data, ?array $file = null): void
    {
        $banner = self::requireBanner($id);
        $title = trim((string) ($data['title'] ?? $banner['title']));

        if ($title === '') {
            throw new RuntimeException('Vui lòng nhap tiêu đề banner.');
        }

        $imageUrl = $banner['image_url'];
        $width = $banner['width'];
        $height = $banner['height'];

        if ($file && !empty($file['name'])) {
            $image = self::storeUploadedImage($file);
            self::deleteImageFile((string) $banner['image_url']);
            $imageUrl = $image['url'];
            $width = $image['width'];
            $height = $image['height'];
        }

        $stmt = getDB()->prepare(
            'UPDATE banners
             SET title = :title,
                 image_url = :image_url,
                 link_url = :link_url,
                 position = :position,
                 width = :width,
                 height = :height,
                 is_active = :is_active,
                 display_from = :display_from,
                 display_to = :display_to
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $id,
            ':title' => $title,
            ':image_url' => $imageUrl,
            ':link_url' => self::nullableUrl($data['link_url'] ?? null),
            ':position' => (int) ($data['position'] ?? 0),
            ':width' => $width,
            ':height' => $height,
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':display_from' => self::nullableDateTime($data['display_from'] ?? null),
            ':display_to' => self::nullableDateTime($data['display_to'] ?? null),
        ]);
    }

    public static function setActive(int $id, bool $active): void
    {
        self::requireBanner($id);
        $stmt = getDB()->prepare('UPDATE banners SET is_active = :is_active WHERE id = :id');
        $stmt->execute([
            ':id' => $id,
            ':is_active' => $active ? 1 : 0,
        ]);
    }

    public static function updatePositions(array $positions): void
    {
        $stmt = getDB()->prepare('UPDATE banners SET position = :position WHERE id = :id');

        foreach ($positions as $id => $position) {
            $stmt->execute([
                ':id' => (int) $id,
                ':position' => (int) $position,
            ]);
        }
    }

    public static function delete(int $id): void
    {
        $banner = self::requireBanner($id);
        $stmt = getDB()->prepare('DELETE FROM banners WHERE id = :id');
        $stmt->execute([':id' => $id]);
        self::deleteImageFile((string) $banner['image_url']);
    }

    public static function find(int $id): ?array
    {
        $stmt = getDB()->prepare('SELECT * FROM banners WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $banner = $stmt->fetch();

        return $banner ?: null;
    }

    private static function requireBanner(int $id): array
    {
        $banner = self::find($id);

        if (!$banner) {
            throw new RuntimeException('Không tìm thấy banner.');
        }

        return $banner;
    }

    private static function storeUploadedImage(array $file): array
    {
        $image = StorageService::storeUploadedFile($file, 'banners', self::ALLOWED_MIME_TYPES, self::MAX_IMAGE_SIZE, 'banner');

        return [
            'url' => $image['url'],
            'width' => (int) ($image['width'] ?? 0),
            'height' => (int) ($image['height'] ?? 0),
        ];
    }

    private static function deleteImageFile(string $imageUrl): void
    {
        StorageService::deletePublicFile($imageUrl);
    }

    private static function nullableUrl(mixed $value): ?string
    {
        $url = trim((string) ($value ?? ''));

        if ($url === '') {
            return null;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL) && !str_starts_with($url, '/')) {
            throw new RuntimeException('Link banner không hop le.');
        }

        return $url;
    }

    private static function nullableDateTime(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        $value = str_replace('T', ' ', $value);
        $date = DateTime::createFromFormat('Y-m-d H:i', $value) ?: DateTime::createFromFormat('Y-m-d H:i:s', $value);

        if (!$date) {
            throw new RuntimeException('Thoi gian hien thi banner không hop le.');
        }

        return $date->format('Y-m-d H:i:s');
    }
}
