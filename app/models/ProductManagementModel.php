<?php

declare(strict_types=1);

class ProductManagementModel
{
    public static function paginateForStore(array $actor, array $filters = []): array
    {
        $storeId = StoreEmployeeModel::storeIdForActor($actor);
        return self::paginate(['p.store_id = :store_id'], [':store_id' => $storeId], $filters);
    }

    public static function paginateForAdmin(array $filters = []): array
    {
        return self::paginate(['1 = 1'], [], $filters);
    }

    public static function detailForStore(array $actor, int $id): ?array
    {
        $storeId = StoreEmployeeModel::storeIdForActor($actor);
        return self::detail($id, 'p.store_id = :store_id', [':store_id' => $storeId]);
    }

    public static function detailForAdmin(int $id): ?array
    {
        return self::detail($id, '1 = 1', []);
    }

    public static function createForStore(array $actor, array $data): int
    {
        $storeId = StoreEmployeeModel::storeIdForActor($actor);
        $payload = self::validatePayload($data);
        $db = getDB();
        $db->beginTransaction();

        try {
            $productCode = self::uniqueProductCode($payload['product_code'] ?: self::codeFromName($payload['name']));
            $status = !empty($data['submit_for_review']) ? PRODUCT_STATUS_PENDING_REVIEW : PRODUCT_STATUS_DRAFT;

            $stmt = $db->prepare(
                'INSERT INTO products (
                    product_code, store_id, category_id, name, description, base_price, discount_price, stock_quantity, status,
                    has_variants, main_image_url, images, weight, weight_unit, volume, volume_unit, length, width, height, is_recommended
                ) VALUES (
                    :product_code, :store_id, :category_id, :name, :description, :base_price, :discount_price, :stock_quantity, :status,
                    :has_variants, :main_image_url, :images, :weight, :weight_unit, :volume, :volume_unit, :length, :width, :height, :is_recommended
                )'
            );
            $stmt->execute([
                ':product_code' => $productCode,
                ':store_id' => $storeId,
                ':category_id' => $payload['category_id'],
                ':name' => $payload['name'],
                ':description' => $payload['description'],
                ':base_price' => $payload['base_price'],
                ':discount_price' => $payload['discount_price'],
                ':stock_quantity' => $payload['stock_quantity'],
                ':status' => $status,
                ':has_variants' => $payload['has_variants'] ? 1 : 0,
                ':main_image_url' => $payload['main_image_url'],
                ':images' => $payload['images'],
                ':weight' => $payload['weight'],
                ':weight_unit' => $payload['weight_unit'],
                ':volume' => $payload['volume'],
                ':volume_unit' => $payload['volume_unit'],
                ':length' => $payload['length'],
                ':width' => $payload['width'],
                ':height' => $payload['height'],
                ':is_recommended' => $payload['is_recommended'],
            ]);
            $productId = (int) $db->lastInsertId();
            
            require_once __DIR__ . '/../controllers/UiHelper.php';
            $slug = UiHelper::slugify($payload['name']) . '-' . $productId;
            $db->prepare('UPDATE products SET slug = :slug WHERE id = :id')->execute([
                ':slug' => $slug,
                ':id' => $productId,
            ]);

            self::replaceTags($productId, $payload['tag_ids']);
            self::replaceVariants($productId, $payload['variants']);

            $db->commit();

            if ($status === PRODUCT_STATUS_PENDING_REVIEW) {
                NotificationModel::notifyAdminProductRequested($storeId, $payload['name']);
            }

            return $productId;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function updateForStore(array $actor, int $id, array $data): void
    {
        $product = self::requireStoreProduct($actor, $id);
        if ($product['status'] === PRODUCT_STATUS_APPROVED) {
            throw new RuntimeException('Sản phẩm đã duyệt không lưu nháp được.');
        }

        $payload = self::validatePayload($data, $id);
        $db = getDB();
        $db->beginTransaction();

        try {
            $productCode = self::uniqueProductCode($payload['product_code'] ?: $product['product_code'], $id);
            $stmt = $db->prepare(
                'UPDATE products
                 SET product_code = :product_code,
                     category_id = :category_id,
                     name = :name,
                     description = :description,
                     base_price = :base_price,
                     discount_price = :discount_price,
                     stock_quantity = :stock_quantity,
                     status = :status,
                     has_variants = :has_variants,
                     main_image_url = :main_image_url,
                     images = :images,
                     weight = :weight,
                     weight_unit = :weight_unit,
                     volume = :volume,
                     volume_unit = :volume_unit,
                     length = :length,
                     width = :width,
                     height = :height,
                     is_recommended = :is_recommended,
                     reject_reason = NULL,
                     approved_by = NULL,
                     approved_at = NULL
                 WHERE id = :id'
            );
            $stmt->execute([
                ':id' => $id,
                ':product_code' => $productCode,
                ':category_id' => $payload['category_id'],
                ':name' => $payload['name'],
                ':description' => $payload['description'],
                ':base_price' => $payload['base_price'],
                ':discount_price' => $payload['discount_price'],
                ':stock_quantity' => $payload['stock_quantity'],
                ':status' => !empty($data['submit_for_review']) ? PRODUCT_STATUS_PENDING_REVIEW : PRODUCT_STATUS_DRAFT,
                ':has_variants' => $payload['has_variants'] ? 1 : 0,
                ':main_image_url' => $payload['main_image_url'],
                ':images' => $payload['images'],
                ':weight' => $payload['weight'],
                ':weight_unit' => $payload['weight_unit'],
                ':volume' => $payload['volume'],
                ':volume_unit' => $payload['volume_unit'],
                ':length' => $payload['length'],
                ':width' => $payload['width'],
                ':height' => $payload['height'],
                ':is_recommended' => $payload['is_recommended'],
            ]);

            require_once __DIR__ . '/../controllers/UiHelper.php';
            $slug = UiHelper::slugify($payload['name']) . '-' . $id;
            $db->prepare('UPDATE products SET slug = :slug WHERE id = :id')->execute([
                ':slug' => $slug,
                ':id' => $id,
            ]);

            self::replaceTags($id, $payload['tag_ids']);
            self::replaceVariants($id, $payload['variants']);

            $db->commit();

            if (!empty($data['submit_for_review'])) {
                $storeId = StoreEmployeeModel::storeIdForActor($actor);
                NotificationModel::notifyAdminProductRequested($storeId, $payload['name']);
            }
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function submitForReview(array $actor, int $id): void
    {
        $product = self::requireStoreProduct($actor, $id);
        if (!in_array($product['status'], [PRODUCT_STATUS_DRAFT, PRODUCT_STATUS_REJECTED], true)) {
            throw new RuntimeException('Chỉ có thể gửi duyệt sản phẩm draft hoặc rejected.');
        }

        $stmt = getDB()->prepare(
            'UPDATE products
             SET status = :status, reject_reason = NULL, approved_by = NULL, approved_at = NULL
             WHERE id = :id'
        );
        $stmt->execute([':id' => $id, ':status' => PRODUCT_STATUS_PENDING_REVIEW]);

        NotificationModel::notifyAdminProductRequested((int) $product['store_id'], $product['name']);
    }

    public static function archiveForStore(array $actor, int $id): void
    {
        self::requireStoreProduct($actor, $id);
        $stmt = getDB()->prepare('UPDATE products SET status = :status WHERE id = :id');
        $stmt->execute([':id' => $id, ':status' => PRODUCT_STATUS_ARCHIVED]);
    }

    public static function approve(int $id, int $adminId): void
    {
        $product = self::requireAdminProduct($id);
        if ($product['status'] !== PRODUCT_STATUS_PENDING_REVIEW) {
            throw new RuntimeException('Chỉ duyệt sản phẩm đang trong hàng chờ.');
        }

        $stmt = getDB()->prepare(
            'UPDATE products
             SET status = :status, approved_by = :approved_by, approved_at = NOW(), reject_reason = NULL
             WHERE id = :id'
        );
        $stmt->execute([':id' => $id, ':status' => PRODUCT_STATUS_APPROVED, ':approved_by' => $adminId]);
    }

    public static function reject(int $id, int $adminId, string $reason): void
    {
        $product = self::requireAdminProduct($id);
        if ($product['status'] !== PRODUCT_STATUS_PENDING_REVIEW) {
            throw new RuntimeException('Chỉ từ chối sản phẩm đang trong hàng chờ.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Vui lòng nhập lý do từ chối.');
        }

        $stmt = getDB()->prepare(
            'UPDATE products
             SET status = :status, approved_by = :approved_by, approved_at = NULL, reject_reason = :reason
             WHERE id = :id'
        );
        $stmt->execute([':id' => $id, ':status' => PRODUCT_STATUS_REJECTED, ':approved_by' => $adminId, ':reason' => $reason]);
    }

    public static function statuses(): array
    {
        return [
            PRODUCT_STATUS_DRAFT,
            PRODUCT_STATUS_PENDING_REVIEW,
            PRODUCT_STATUS_APPROVED,
            PRODUCT_STATUS_REJECTED,
            PRODUCT_STATUS_ARCHIVED,
        ];
    }

    private static function paginate(array $baseWhere, array $baseParams, array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(MAX_PAGE_SIZE, max(1, (int) ($filters['limit'] ?? DEFAULT_PAGE_SIZE)));
        $offset = ($page - 1) * $limit;
        $where = array_merge($baseWhere, ['p.deleted_at IS NULL']);
        $params = $baseParams;

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = strtoupper($filters['sort_dir'] ?? 'DESC');
        $allowedSortCols = ['id', 'name', 'base_price', 'view_count', 'sold_count', 'created_at'];
        if (!in_array($sortBy, $allowedSortCols, true)) {
            $sortBy = 'created_at';
        }
        if (!in_array($sortDir, ['ASC', 'DESC'], true)) {
            $sortDir = 'DESC';
        }
        $orderBy = "p.{$sortBy} {$sortDir}";

        if (!empty($filters['status']) && in_array($filters['status'], self::statuses(), true)) {
            $where[] = 'p.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['category_id'])) {
            $where[] = 'p.category_id = :category_id';
            $params[':category_id'] = (int) $filters['category_id'];
        }

        if (!empty($filters['store_id'])) {
            $where[] = 'p.store_id = :filter_store_id';
            $params[':filter_store_id'] = (int) $filters['store_id'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string) $filters['search']) . '%';
            $where[] = '(p.name LIKE :search_name OR p.product_code LIKE :search_code OR sp.store_name LIKE :search_store)';
            $params[':search_name'] = $search;
            $params[':search_code'] = $search;
            $params[':search_store'] = $search;
        }

        $whereSql = implode(' AND ', $where);
        $db = getDB();
        $countStmt = $db->prepare("SELECT COUNT(*) FROM products p LEFT JOIN store_profiles sp ON sp.user_id = p.store_id WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare(
            "SELECT p.id, p.product_code, p.store_id, p.category_id, p.name, p.base_price, p.status,
                    p.has_variants, p.main_image_url, p.weight, p.reject_reason, p.view_count, p.sold_count,
                    p.created_at, p.updated_at, p.approved_at,
                    c.name AS category_name,
                    sp.store_name,
                    approver.full_name AS approved_by_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN store_profiles sp ON sp.user_id = p.store_id
             LEFT JOIN users approver ON approver.id = p.approved_by
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

        return [
            'items' => $stmt->fetchAll(),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => (int) ceil($total / $limit),
            ],
        ];
    }

    private static function detail(int $id, string $scopeSql, array $scopeParams): ?array
    {
        $stmt = getDB()->prepare(
            "SELECT p.*, c.name AS category_name, sp.store_name, approver.full_name AS approved_by_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN store_profiles sp ON sp.user_id = p.store_id
             LEFT JOIN users approver ON approver.id = p.approved_by
             WHERE p.id = :id AND p.deleted_at IS NULL AND {$scopeSql}
             LIMIT 1"
        );
        $stmt->execute([':id' => $id] + $scopeParams);
        $product = $stmt->fetch();
        if (!$product) {
            return null;
        }

        $product['tags'] = self::tagsForProduct($id);
        $product['variants'] = self::variantsForProduct($id);
        $product['images_data'] = json_decode((string) ($product['images'] ?? '[]'), true) ?: [];
        return $product;
    }

    private static function requireStoreProduct(array $actor, int $id): array
    {
        if ($id <= 0) {
            throw new RuntimeException('Thieu sản phẩm.');
        }
        $product = self::detailForStore($actor, $id);
        if (!$product) {
            throw new RuntimeException('Không tìm thấy sản phẩm của shop.');
        }
        return $product;
    }

    private static function requireAdminProduct(int $id): array
    {
        if ($id <= 0) {
            throw new RuntimeException('Thiếu sản phẩm.');
        }
        $product = self::detailForAdmin($id);
        if (!$product) {
            throw new RuntimeException('Không tìm thấy sản phẩm.');
        }
        return $product;
    }

    private static function validatePayload(array $data, ?int $currentId = null): array
    {
        $errors = [];
        $name = trim((string) ($data['name'] ?? ''));
        $categoryId = (int) ($data['category_id'] ?? 0);
        $basePrice = (float) ($data['base_price'] ?? 0);

        if ($name === '') {
            $errors['name'] = 'Vui lòng nhập tên sản phẩm.';
        }
        if ($categoryId <= 0 || !self::activeCategoryExists($categoryId)) {
            $errors['category_id'] = 'Danh mục sản phẩm không hợp lệ.';
        }
        if ($basePrice <= 0) {
            $errors['base_price'] = 'Giá sản phẩm phải lớn hơn 0.';
        }

        $variants = [];
        try {
            $variants = self::normalizeVariants($data['variants'] ?? []);
        } catch (RuntimeException $e) {
            $errors['variants'] = $e->getMessage();
        }
        
        if (!empty($errors)) {
            throw new RuntimeException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }
        $images = self::jsonOrNull($data['images'] ?? null);
        $tagIds = self::normalizeTagIds($data['tag_ids'] ?? []);

        return [
            'product_code' => trim((string) ($data['product_code'] ?? '')),
            'category_id' => $categoryId,
            'name' => $name,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'base_price' => $basePrice,
            'discount_price' => isset($data['discount_price']) && $data['discount_price'] !== '' ? max(0, (float) $data['discount_price']) : null,
            'stock_quantity' => isset($data['stock_quantity']) && $data['stock_quantity'] !== '' ? max(0, (int) $data['stock_quantity']) : 0,
            'has_variants' => !empty($variants),
            'main_image_url' => trim((string) ($data['main_image_url'] ?? '')) ?: null,
            'images' => $images,
            'weight' => isset($data['weight']) && $data['weight'] !== '' ? max(0, (float) $data['weight']) : null,
            'weight_unit' => trim((string) ($data['weight_unit'] ?? 'g')) === 'kg' ? 'kg' : 'g',
            'volume' => isset($data['volume']) && $data['volume'] !== '' ? max(0, (float) $data['volume']) : null,
            'volume_unit' => in_array(trim((string) ($data['volume_unit'] ?? '')), ['ml', 'l', 'm3'], true) ? trim((string) $data['volume_unit']) : null,
            'length' => isset($data['length']) && $data['length'] !== '' ? max(0, (float) $data['length']) : null,
            'width' => isset($data['width']) && $data['width'] !== '' ? max(0, (float) $data['width']) : null,
            'height' => isset($data['height']) && $data['height'] !== '' ? max(0, (float) $data['height']) : null,
            'is_recommended' => !empty($data['is_recommended']) ? 1 : 0,
            'variants' => $variants,
            'tag_ids' => $tagIds,
        ];
    }

    private static function activeCategoryExists(int $id): bool
    {
        $stmt = getDB()->prepare('SELECT id FROM categories WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute([':id' => $id]);
        return (bool) $stmt->fetch();
    }

    private static function normalizeVariants(mixed $variants): array
    {
        if ($variants === null || $variants === '') {
            return [];
        }
        if (is_string($variants)) {
            $decoded = json_decode($variants, true);
            $variants = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($variants)) {
            return [];
        }

        $normalized = [];
        foreach ($variants as $variant) {
            if (!is_array($variant)) {
                continue;
            }
            $price = (float) ($variant['price'] ?? 0);
            $stock = (int) ($variant['stock_quantity'] ?? 0);
            if ($price <= 0) {
                throw new RuntimeException('Giá biến thể phải lớn hơn 0.');
            }
            if ($stock < 0) {
                throw new RuntimeException('Số lượng tồn kho biến thể không hợp lệ.');
            }
            $normalized[] = [
                'type_label' => trim((string) ($variant['type_label'] ?? '')) ?: null,
                'color' => trim((string) ($variant['color'] ?? '')) ?: null,
                'size' => trim((string) ($variant['size'] ?? '')) ?: null,
                'sku' => trim((string) ($variant['sku'] ?? '')) ?: null,
                'price' => $price,
                'stock_quantity' => $stock,
                'restock_wait_days' => (int) ($variant['restock_wait_days'] ?? 0),
                'image_url' => trim((string) ($variant['image_url'] ?? '')) ?: null,
                'is_active' => isset($variant['is_active']) ? (int) (bool) $variant['is_active'] : 1,
            ];
        }
        return $normalized;
    }

    private static function normalizeTagIds(mixed $tagIds): array
    {
        if (is_string($tagIds)) {
            $decoded = json_decode($tagIds, true);
            $tagIds = is_array($decoded) ? $decoded : preg_split('/,/', $tagIds);
        }
        if (!is_array($tagIds)) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $tagIds))));
        if (!$ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = getDB()->prepare("SELECT id FROM tags WHERE is_active = 1 AND id IN ({$placeholders})");
        $stmt->execute($ids);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private static function replaceTags(int $productId, array $tagIds): void
    {
        $delete = getDB()->prepare('DELETE FROM product_tags WHERE product_id = :product_id');
        $delete->execute([':product_id' => $productId]);
        if (!$tagIds) {
            return;
        }
        $insert = getDB()->prepare('INSERT INTO product_tags (product_id, tag_id) VALUES (:product_id, :tag_id)');
        foreach ($tagIds as $tagId) {
            $insert->execute([':product_id' => $productId, ':tag_id' => $tagId]);
        }
    }

    private static function replaceVariants(int $productId, array $variants): void
    {
        $delete = getDB()->prepare('DELETE FROM product_variants WHERE product_id = :product_id');
        $delete->execute([':product_id' => $productId]);
        if (!$variants) {
            return;
        }
        $insert = getDB()->prepare(
            'INSERT INTO product_variants (product_id, type_label, color, size, sku, price, stock_quantity, restock_wait_days, image_url, is_active)
             VALUES (:product_id, :type_label, :color, :size, :sku, :price, :stock_quantity, :restock_wait_days, :image_url, :is_active)'
        );
        foreach ($variants as $variant) {
            $insert->execute([
                ':product_id' => $productId,
                ':type_label' => $variant['type_label'],
                ':color' => $variant['color'],
                ':size' => $variant['size'],
                ':sku' => $variant['sku'],
                ':price' => $variant['price'],
                ':stock_quantity' => $variant['stock_quantity'],
                ':restock_wait_days' => $variant['restock_wait_days'],
                ':image_url' => $variant['image_url'],
                ':is_active' => $variant['is_active'],
            ]);
        }
    }

    private static function tagsForProduct(int $productId): array
    {
        $stmt = getDB()->prepare(
            'SELECT t.id, t.name, t.color_hex
             FROM tags t
             INNER JOIN product_tags pt ON pt.tag_id = t.id
             WHERE pt.product_id = :product_id
             ORDER BY t.name ASC'
        );
        $stmt->execute([':product_id' => $productId]);
        return $stmt->fetchAll();
    }

    private static function variantsForProduct(int $productId): array
    {
        $stmt = getDB()->prepare(
            'SELECT id, type_label, color, size, sku, price, stock_quantity, restock_wait_days, image_url, is_active
             FROM product_variants
             WHERE product_id = :product_id
             ORDER BY id ASC'
        );
        $stmt->execute([':product_id' => $productId]);
        return $stmt->fetchAll();
    }

    private static function uniqueProductCode(string $baseCode, ?int $ignoreId = null): string
    {
        $baseCode = strtoupper(preg_replace('/[^A-Z0-9]+/i', '-', trim($baseCode)) ?: 'PRD');
        $baseCode = trim($baseCode, '-');
        $code = $baseCode;
        $suffix = 1;

        while (self::productCodeExists($code, $ignoreId)) {
            $suffix++;
            $code = $baseCode . '-' . $suffix;
        }
        return $code;
    }

    private static function productCodeExists(string $code, ?int $ignoreId): bool
    {
        $sql = 'SELECT id FROM products WHERE product_code = :code';
        $params = [':code' => $code];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $ignoreId;
        }
        $sql .= ' LIMIT 1';
        $stmt = getDB()->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    private static function codeFromName(string $name): string
    {
        return 'PRD-' . strtoupper(AdminCatalogModel::slugify($name));
    }

    private static function jsonOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            return json_encode(array_values($value), JSON_UNESCAPED_UNICODE);
        }
        $decoded = json_decode((string) $value, true);
        return json_last_error() === JSON_ERROR_NONE ? json_encode($decoded, JSON_UNESCAPED_UNICODE) : null;
    }
}
