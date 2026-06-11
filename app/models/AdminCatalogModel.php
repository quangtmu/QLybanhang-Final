<?php

declare(strict_types=1);

class AdminCatalogModel
{
    public static function categories(array $filters = []): array
    {
        $params = [];
        $where = ['1 = 1'];

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $where[] = 'is_active = :is_active';
            $params[':is_active'] = (int) $filters['is_active'];
        }

        if (!empty($filters['level']) && in_array($filters['level'], self::categoryLevels(), true)) {
            $where[] = 'level = :level';
            $params[':level'] = $filters['level'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string) $filters['search']) . '%';
            $where[] = '(name LIKE :search_name OR slug LIKE :search_slug)';
            $params[':search_name'] = $search;
            $params[':search_slug'] = $search;
        }

        $stmt = getDB()->prepare(
            'SELECT id, name, slug, parent_id, level, icon_url, sort_order, is_active, created_at
             FROM categories
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY FIELD(level, "large", "medium", "small"), sort_order ASC, name ASC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function categoryTree(array $filters = []): array
    {
        $items = self::categories($filters);
        $byParent = [];

        foreach ($items as $item) {
            $key = (int) ($item['parent_id'] ?? 0);
            $item['children'] = [];
            $byParent[$key][] = $item;
        }

        return self::buildTree($byParent, 0);
    }

    public static function findCategory(int $id): ?array
    {
        $stmt = getDB()->prepare('SELECT * FROM categories WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function createCategory(array $data): int
    {
        $validated = self::validateCategoryData($data);
        $slug = self::uniqueCategorySlug($validated['slug'] ?: self::slugify($validated['name']));

        $stmt = getDB()->prepare(
            'INSERT INTO categories (name, slug, parent_id, level, icon_url, sort_order, is_active)
             VALUES (:name, :slug, :parent_id, :level, :icon_url, :sort_order, :is_active)'
        );
        $stmt->execute([
            ':name' => $validated['name'],
            ':slug' => $slug,
            ':parent_id' => $validated['parent_id'],
            ':level' => $validated['level'],
            ':icon_url' => $validated['icon_url'],
            ':sort_order' => $validated['sort_order'],
            ':is_active' => $validated['is_active'],
        ]);

        return (int) getDB()->lastInsertId();
    }

    public static function updateCategory(int $id, array $data): void
    {
        $current = self::findCategory($id);

        if (!$current) {
            throw new RuntimeException('Không tìm thấy danh mục.');
        }

        $validated = self::validateCategoryData($data, $id);
        $baseSlug = $validated['slug'] ?: self::slugify($validated['name']);
        $slug = self::uniqueCategorySlug($baseSlug, $id);

        $stmt = getDB()->prepare(
            'UPDATE categories
             SET name = :name,
                 slug = :slug,
                 parent_id = :parent_id,
                 level = :level,
                 icon_url = :icon_url,
                 sort_order = :sort_order,
                 is_active = :is_active
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $id,
            ':name' => $validated['name'],
            ':slug' => $slug,
            ':parent_id' => $validated['parent_id'],
            ':level' => $validated['level'],
            ':icon_url' => $validated['icon_url'],
            ':sort_order' => $validated['sort_order'],
            ':is_active' => $validated['is_active'],
        ]);
    }

    public static function setCategoryActive(int $id, bool $isActive): void
    {
        self::requireCategory($id);
        $stmt = getDB()->prepare('UPDATE categories SET is_active = :is_active WHERE id = :id');
        $stmt->execute([':id' => $id, ':is_active' => $isActive ? 1 : 0]);
    }

    public static function deleteCategory(int $id): void
    {
        self::requireCategory($id);

        if (self::categoryHasChildren($id)) {
            throw new RuntimeException('Danh mục đang co danh mục con, không thể xoa.');
        }

        if (self::categoryHasProducts($id)) {
            throw new RuntimeException('Danh mục đang co sản phẩm, hay chuyen inactive thay vi xoa.');
        }

        $stmt = getDB()->prepare('DELETE FROM categories WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public static function tags(array $filters = []): array
    {
        $params = [];
        $where = ['1 = 1'];

        $params = [];
        $where = ['1 = 1'];

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $where[] = 'is_active = :is_active';
            $params[':is_active'] = (int) $filters['is_active'];
        }

        if (!empty($filters['search'])) {
            $where[] = 'name LIKE :search';
            $params[':search'] = '%' . trim((string) $filters['search']) . '%';
        }

        $stmt = getDB()->prepare(
            'SELECT id, name, color_hex, is_active, created_at
             FROM tags
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY name ASC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function findTag(int $id): ?array
    {
        $stmt = getDB()->prepare('SELECT * FROM tags WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function createTag(array $data): int
    {
        $validated = self::validateTagData($data);
        $stmt = getDB()->prepare(
            'INSERT INTO tags (name, category_size, color_hex, is_active)
             VALUES (:name, :category_size, :color_hex, :is_active)'
        );
        $stmt->execute([
            ':name' => $validated['name'],
            ':category_size' => 'small',
            ':color_hex' => $validated['color_hex'],
            ':is_active' => $validated['is_active'],
        ]);

        return (int) getDB()->lastInsertId();
    }

    public static function updateTag(int $id, array $data): void
    {
        self::requireTag($id);
        $validated = self::validateTagData($data, $id);
        $stmt = getDB()->prepare(
            'UPDATE tags
             SET name = :name,
                 category_size = :category_size,
                 color_hex = :color_hex,
                 is_active = :is_active
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $id,
            ':name' => $validated['name'],
            ':category_size' => 'small',
            ':color_hex' => $validated['color_hex'],
            ':is_active' => $validated['is_active'],
        ]);
    }

    public static function setTagActive(int $id, bool $isActive): void
    {
        self::requireTag($id);
        $stmt = getDB()->prepare('UPDATE tags SET is_active = :is_active WHERE id = :id');
        $stmt->execute([':id' => $id, ':is_active' => $isActive ? 1 : 0]);
    }

    public static function deleteTag(int $id): void
    {
        self::requireTag($id);

        if (self::tagHasProducts($id)) {
            throw new RuntimeException('Tag đang được gan sản phẩm, hay chuyen inactive thay vi xoa.');
        }

        $stmt = getDB()->prepare('DELETE FROM tags WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public static function categoryLevels(): array
    {
        return [CATEGORY_LEVEL_LARGE, CATEGORY_LEVEL_MEDIUM, CATEGORY_LEVEL_SMALL];
    }

    public static function slugify(string $value): string
    {
        $value = trim(function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value));

        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }

        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
        $value = trim($value, '-');

        return $value !== '' ? $value : 'category';
    }

    private static function buildTree(array $byParent, int $parentId): array
    {
        $branch = [];

        foreach ($byParent[$parentId] ?? [] as $item) {
            $item['children'] = self::buildTree($byParent, (int) $item['id']);
            $branch[] = $item;
        }

        return $branch;
    }

    private static function validateCategoryData(array $data, ?int $currentId = null): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $level = (string) ($data['level'] ?? '');
        $parentId = isset($data['parent_id']) && $data['parent_id'] !== '' ? (int) $data['parent_id'] : null;

        if ($name === '') {
            throw new RuntimeException('Vui lòng nhập tên danh mục.');
        }

        if (!in_array($level, self::categoryLevels(), true)) {
            throw new RuntimeException('Cấp danh mục không hợp lệ.');
        }

        if ($currentId !== null && $parentId === $currentId) {
            throw new RuntimeException('Danh mục không thể là cha của chính nó.');
        }

        if ($level === CATEGORY_LEVEL_LARGE && $parentId !== null) {
            throw new RuntimeException('Danh mục large không được co parent.');
        }

        if ($level !== CATEGORY_LEVEL_LARGE) {
            if ($parentId === null) {
                throw new RuntimeException('Danh mục medium/small can parent.');
            }

            $parent = self::findCategory($parentId);

            if (!$parent) {
                throw new RuntimeException('Parent category không ton tai.');
            }

            $expectedParentLevel = $level === CATEGORY_LEVEL_MEDIUM ? CATEGORY_LEVEL_LARGE : CATEGORY_LEVEL_MEDIUM;

            if ($parent['level'] !== $expectedParentLevel) {
                throw new RuntimeException('Parent category không dung cap.');
            }
        }

        return [
            'name' => $name,
            'slug' => trim((string) ($data['slug'] ?? '')),
            'parent_id' => $parentId,
            'level' => $level,
            'icon_url' => trim((string) ($data['icon_url'] ?? '')) ?: null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1,
        ];
    }

    private static function validateTagData(array $data, ?int $currentId = null): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $colorHex = trim((string) ($data['color_hex'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('Vui lòng nhap ten tag.');
        }

        if ($colorHex !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $colorHex)) {
            throw new RuntimeException('Mau tag can dung dinh đang #RRGGBB.');
        }

        if (self::tagNameExists($name, $currentId)) {
            throw new RuntimeException('Tag đã ton tai trong nhom này.');
        }

        return [
            'name' => $name,
            'color_hex' => $colorHex ?: null,
            'is_active' => isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1,
        ];
    }

    private static function uniqueCategorySlug(string $slug, ?int $ignoreId = null): string
    {
        $base = self::slugify($slug);
        $candidate = $base;
        $counter = 2;

        while (self::categorySlugExists($candidate, $ignoreId)) {
            $candidate = $base . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }

    private static function categorySlugExists(string $slug, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id FROM categories WHERE slug = :slug';
        $params = [':slug' => $slug];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $ignoreId;
        }

        $stmt = getDB()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetch();
    }

    private static function tagNameExists(string $name, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id FROM tags WHERE name = :name';
        $params = [':name' => $name];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $ignoreId;
        }

        $stmt = getDB()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetch();
    }

    private static function requireCategory(int $id): array
    {
        $category = self::findCategory($id);

        if (!$category) {
            throw new RuntimeException('Không tìm thấy danh mục.');
        }

        return $category;
    }

    private static function requireTag(int $id): array
    {
        $tag = self::findTag($id);

        if (!$tag) {
            throw new RuntimeException('Không tìm thấy tag.');
        }

        return $tag;
    }

    private static function categoryHasChildren(int $id): bool
    {
        $stmt = getDB()->prepare('SELECT id FROM categories WHERE parent_id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        return (bool) $stmt->fetch();
    }

    private static function categoryHasProducts(int $id): bool
    {
        $stmt = getDB()->prepare('SELECT id FROM products WHERE category_id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        return (bool) $stmt->fetch();
    }

    private static function tagHasProducts(int $id): bool
    {
        $stmt = getDB()->prepare('SELECT product_id FROM product_tags WHERE tag_id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        return (bool) $stmt->fetch();
    }
}
