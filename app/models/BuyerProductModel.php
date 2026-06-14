<?php

declare(strict_types=1);

class BuyerProductModel
{
    public static function listProducts(array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(MAX_PAGE_SIZE, max(1, (int) ($filters['limit'] ?? DEFAULT_PAGE_SIZE)));
        $offset = ($page - 1) * $limit;
        $params = [];
        $where = [
            'p.status = :approved_status',
            'p.deleted_at IS NULL',
        ];
        $params[':approved_status'] = PRODUCT_STATUS_APPROVED;

        if (!empty($filters['search'])) {
            $search = '%' . trim((string) $filters['search']) . '%';
            $where[] = '(p.name LIKE :search_name OR p.product_code LIKE :search_code OR sp.store_name LIKE :search_store)';
            $params[':search_name'] = $search;
            $params[':search_code'] = $search;
            $params[':search_store'] = $search;
        }

        if (!empty($filters['category_id'])) {
            $categoryIds = self::categoryIdsForFilter((int) $filters['category_id']);
            $placeholders = [];
            foreach ($categoryIds as $index => $categoryId) {
                $key = ':category_id_' . $index;
                $placeholders[] = $key;
                $params[$key] = $categoryId;
            }
            $where[] = 'p.category_id IN (' . implode(', ', $placeholders) . ')';
        }

        if (!empty($filters['store_id'])) {
            $where[] = 'p.store_id = :store_id';
            $params[':store_id'] = (int) $filters['store_id'];
        }

        if (!empty($filters['tag_id'])) {
            $where[] = 'EXISTS (
                SELECT 1 FROM product_tags pt
                WHERE pt.product_id = p.id AND pt.tag_id = :tag_id
            )';
            $params[':tag_id'] = (int) $filters['tag_id'];
        }

        if (isset($filters['min_price']) && $filters['min_price'] !== '') {
            $where[] = 'p.base_price >= :min_price';
            $params[':min_price'] = (float) $filters['min_price'];
        }

        if (isset($filters['max_price']) && $filters['max_price'] !== '') {
            $where[] = 'p.base_price <= :max_price';
            $params[':max_price'] = (float) $filters['max_price'];
        }

        $orderBy = match ((string) ($filters['sort'] ?? 'newest')) {
            'price_asc' => 'p.base_price ASC, p.created_at DESC',
            'price_desc' => 'p.base_price DESC, p.created_at DESC',
            'sold' => 'p.sold_count DESC, p.created_at DESC',
            default => 'p.created_at DESC',
        };

        $whereSql = implode(' AND ', $where);
        $db = getDB();
        $countStmt = $db->prepare(
            "SELECT COUNT(*)
             FROM products p
             LEFT JOIN store_profiles sp ON sp.user_id = p.store_id
             WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare(
            "SELECT p.id, p.product_code, p.name, p.slug, p.description, p.base_price, p.has_variants, p.main_image_url,
                    p.view_count, p.sold_count, p.created_at,
                    c.name AS category_name, c.slug AS category_slug,
                    sp.store_name, sp.store_slug, sp.rating AS store_rating
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN store_profiles sp ON sp.user_id = p.store_id
             WHERE {$whereSql}
             ORDER BY {$orderBy}
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll();
        require_once __DIR__ . '/../controllers/StorageService.php';
        foreach ($items as &$item) {
            if (!empty($item['main_image_url'])) {
                $item['main_image_url'] = StorageService::publicUrl($item['main_image_url']);
            }
        }

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / $limit),
            ],
        ];
    }

    public static function detail(int $id): ?array
    {
        $stmt = getDB()->prepare(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.parent_id AS category_parent_id,
                    sp.store_name, sp.store_slug, sp.logo_url, sp.rating
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN store_profiles sp ON sp.user_id = p.store_id
             WHERE p.id = :id
               AND p.status = :approved_status
               AND p.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            ':id' => $id,
            ':approved_status' => PRODUCT_STATUS_APPROVED,
        ]);
        $product = $stmt->fetch();

        if (!$product) {
            return null;
        }

        require_once __DIR__ . '/../controllers/StorageService.php';
        if (!empty($product['main_image_url'])) {
            $product['main_image_url'] = StorageService::publicUrl($product['main_image_url']);
        }
        $images = self::jsonOrNull($product['images']);
        if (is_array($images)) {
            foreach ($images as &$img) {
                $img = StorageService::publicUrl($img);
            }
            $product['images'] = json_encode($images);
        }

        $product['tags'] = self::tagsForProduct($id);
        $product['variants'] = self::variantsForProduct($id);
        self::incrementViewCount($id);

        return $product;
    }

    public static function detailBySlug(string $slug): ?array
    {
        $stmt = getDB()->prepare(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.parent_id AS category_parent_id,
                    sp.store_name, sp.store_slug, sp.logo_url, sp.rating
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN store_profiles sp ON sp.user_id = p.store_id
             WHERE p.slug = :slug
               AND p.status = :approved_status
               AND p.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            ':slug' => $slug,
            ':approved_status' => PRODUCT_STATUS_APPROVED,
        ]);
        $product = $stmt->fetch();

        if (!$product) {
            return null;
        }

        $id = (int) $product['id'];
        $product['tags'] = self::tagsForProduct($id);
        $product['variants'] = self::variantsForProduct($id);
        self::incrementViewCount($id);

        return $product;
    }

    public static function relatedProducts(array $product, int $limit = 8): array
    {
        $limit = max(1, min(16, $limit));
        $items = self::listProducts([
            'category_id' => $product['category_id'] ?? 0,
            'sort' => 'sold',
            'limit' => $limit + 1,
        ])['items'];

        $items = array_values(array_filter($items, fn (array $item): bool => (int) $item['id'] !== (int) $product['id']));

        if (count($items) < $limit && !empty($product['category_parent_id'])) {
            $parentItems = self::listProducts([
                'category_id' => (int) $product['category_parent_id'],
                'sort' => 'sold',
                'limit' => $limit + 1,
            ])['items'];

            foreach ($parentItems as $item) {
                if ((int) $item['id'] === (int) $product['id']) {
                    continue;
                }
                $alreadyAdded = false;
                foreach ($items as $existing) {
                    if ((int) $existing['id'] === (int) $item['id']) {
                        $alreadyAdded = true;
                        break;
                    }
                }
                if ($alreadyAdded) {
                    continue;
                }
                $items[] = $item;
                if (count($items) >= $limit) {
                    break;
                }
            }
        }

        if (count($items) < $limit && !empty($product['store_id'])) {
            $storeItems = self::listProducts([
                'store_id' => (int) $product['store_id'],
                'sort' => 'sold',
                'limit' => $limit + 1,
            ])['items'];

            foreach ($storeItems as $item) {
                if ((int) $item['id'] === (int) $product['id']) {
                    continue;
                }
                $alreadyAdded = false;
                foreach ($items as $existing) {
                    if ((int) $existing['id'] === (int) $item['id']) {
                        $alreadyAdded = true;
                        break;
                    }
                }
                if ($alreadyAdded) {
                    continue;
                }
                $items[] = $item;
                if (count($items) >= $limit) {
                    break;
                }
            }
        }

        return array_slice($items, 0, $limit);
    }

    public static function productsByCategoryGroups(int $limitCategories = 4, int $perCategory = 4): array
    {
        $limitCategories = max(1, min(8, $limitCategories));
        $perCategory = max(1, min(8, $perCategory));
        $stmt = getDB()->prepare(
            'SELECT c.id, c.name, c.slug, c.level, COUNT(p.id) AS product_count
             FROM categories c
             INNER JOIN products p ON p.category_id = c.id
             WHERE c.is_active = 1
               AND p.status = :approved_status
               AND p.deleted_at IS NULL
             GROUP BY c.id, c.name, c.slug, c.level, c.sort_order
             ORDER BY FIELD(c.level, "large", "medium", "small"), c.sort_order ASC, product_count DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':approved_status', PRODUCT_STATUS_APPROVED);
        $stmt->bindValue(':limit', $limitCategories, PDO::PARAM_INT);
        $stmt->execute();

        $groups = [];
        foreach ($stmt->fetchAll() as $category) {
            $items = self::listProducts([
                'category_id' => (int) $category['id'],
                'sort' => 'sold',
                'limit' => $perCategory,
            ])['items'];

            if ($items) {
                $groups[] = [
                    'category' => $category,
                    'items' => $items,
                ];
            }
        }

        return $groups;
    }

    public static function featuredStores(int $limit = 6): array
    {
        $limit = max(1, min(12, $limit));
        $stmt = getDB()->prepare(
            'SELECT sp.user_id, sp.store_name, sp.store_slug, sp.logo_url, sp.banner_url, sp.description, sp.rating,
                    (
                        SELECT COUNT(*)
                        FROM products p
                        WHERE p.store_id = sp.user_id
                          AND p.status = :approved_status
                          AND p.deleted_at IS NULL
                    ) AS product_count,
                    (
                        SELECT COALESCE(SUM(p.sold_count), 0)
                        FROM products p
                        WHERE p.store_id = sp.user_id
                          AND p.status = :approved_status_2
                          AND p.deleted_at IS NULL
                    ) AS sold_count,
                    (
                        SELECT COUNT(*)
                        FROM orders o
                        WHERE o.store_id = sp.user_id
                          AND o.status IN ("confirmed", "processing", "shipped", "delivering", "delivered")
                    ) AS order_count
             FROM store_profiles sp
             WHERE EXISTS (
                SELECT 1
                FROM products p
                WHERE p.store_id = sp.user_id
                  AND p.status = :approved_status_3
                  AND p.deleted_at IS NULL
             )
             ORDER BY sold_count DESC, order_count DESC, product_count DESC, sp.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':approved_status', PRODUCT_STATUS_APPROVED);
        $stmt->bindValue(':approved_status_2', PRODUCT_STATUS_APPROVED);
        $stmt->bindValue(':approved_status_3', PRODUCT_STATUS_APPROVED);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function storeProfile(string $slug): ?array
    {
        $stmt = getDB()->prepare(
            'SELECT sp.user_id, sp.store_name, sp.store_slug, sp.logo_url, sp.banner_url, sp.description,
                    sp.address, sp.product_types, sp.rating, sp.created_at,
                    (
                        SELECT COUNT(*)
                        FROM products p
                        WHERE p.store_id = sp.user_id
                          AND p.status = :approved_status
                          AND p.deleted_at IS NULL
                    ) AS product_count,
                    (
                        SELECT COALESCE(SUM(p.sold_count), 0)
                        FROM products p
                        WHERE p.store_id = sp.user_id
                          AND p.status = :approved_status_2
                          AND p.deleted_at IS NULL
                    ) AS sold_count,
                    (
                        SELECT COUNT(*)
                        FROM orders o
                        WHERE o.store_id = sp.user_id
                          AND o.status IN ("confirmed", "processing", "shipped", "delivering", "delivered")
                    ) AS order_count
             FROM store_profiles sp
             WHERE sp.store_slug = :slug
             LIMIT 1'
        );
        $stmt->execute([
            ':slug' => $slug,
            ':approved_status' => PRODUCT_STATUS_APPROVED,
            ':approved_status_2' => PRODUCT_STATUS_APPROVED,
        ]);
        $store = $stmt->fetch();

        if (!$store) {
            return null;
        }

        $store['product_types_data'] = json_decode((string) ($store['product_types'] ?? '[]'), true) ?: [];

        return $store;
    }

    public static function activeCategories(): array
    {
        return AdminCatalogModel::categories(['is_active' => 1]);
    }

    public static function activeTags(): array
    {
        return AdminCatalogModel::tags(['is_active' => 1]);
    }

    private static function tagsForProduct(int $productId): array
    {
        $stmt = getDB()->prepare(
            'SELECT t.id, t.name, t.color_hex
             FROM tags t
             INNER JOIN product_tags pt ON pt.tag_id = t.id
             WHERE pt.product_id = :product_id AND t.is_active = 1
             ORDER BY t.name ASC'
        );
        $stmt->execute([':product_id' => $productId]);

        return $stmt->fetchAll();
    }

    private static function variantsForProduct(int $productId): array
    {
        $stmt = getDB()->prepare(
            'SELECT id, type_label, color, size, sku, price, stock_quantity, image_url
             FROM product_variants
             WHERE product_id = :product_id AND is_active = 1
             ORDER BY id ASC'
        );
        $stmt->execute([':product_id' => $productId]);

        return $stmt->fetchAll();
    }

    private static function incrementViewCount(int $productId): void
    {
        $stmt = getDB()->prepare('UPDATE products SET view_count = view_count + 1 WHERE id = :id');
        $stmt->execute([':id' => $productId]);
    }

    private static function categoryIdsForFilter(int $categoryId): array
    {
        if ($categoryId <= 0) {
            return [$categoryId];
        }

        $stmt = getDB()->prepare(
            'SELECT id
             FROM categories
             WHERE id = :id
                OR parent_id = :parent_id
                OR parent_id IN (
                    SELECT id FROM categories WHERE parent_id = :grand_parent_id
                )'
        );
        $stmt->execute([
            ':id' => $categoryId,
            ':parent_id' => $categoryId,
            ':grand_parent_id' => $categoryId,
        ]);
        $ids = array_map('intval', array_column($stmt->fetchAll(), 'id'));

        return $ids ?: [$categoryId];
    }
}
