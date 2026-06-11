<?php

declare(strict_types=1);

class InvoiceModel
{
    public static function paginateForActor(array $actor, array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(MAX_PAGE_SIZE, max(1, (int) ($filters['limit'] ?? DEFAULT_PAGE_SIZE)));
        $offset = ($page - 1) * $limit;
        [$where, $params] = self::whereForActor($actor);

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = strtoupper($filters['sort_dir'] ?? 'DESC');
        $allowedSortCols = ['id', 'invoice_code', 'total_amount', 'created_at'];
        if (!in_array($sortBy, $allowedSortCols, true)) {
            $sortBy = 'created_at';
        }
        if (!in_array($sortDir, ['ASC', 'DESC'], true)) {
            $sortDir = 'DESC';
        }
        $orderBy = "i.{$sortBy} {$sortDir}";

        if (!empty($filters['order_status']) && in_array($filters['order_status'], OrderModel::orderStatuses(), true)) {
            $where[] = 'o.status = :order_status';
            $params[':order_status'] = $filters['order_status'];
        }

        if (!empty($filters['store_id']) && self::isAdminScope($actor)) {
            $where[] = 'o.store_id = :store_id';
            $params[':store_id'] = (int) $filters['store_id'];
        }

        if (!empty($filters['buyer_id']) && self::isAdminScope($actor)) {
            $where[] = 'o.buyer_id = :buyer_id';
            $params[':buyer_id'] = (int) $filters['buyer_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(i.created_at) >= :date_from';
            $params[':date_from'] = (string) $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(i.created_at) <= :date_to';
            $params[':date_to'] = (string) $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string) $filters['search']) . '%';
            $where[] = '(i.invoice_code LIKE :search_invoice OR o.order_code LIKE :search_order OR buyer.email LIKE :search_buyer OR sp.store_name LIKE :search_store)';
            $params[':search_invoice'] = $search;
            $params[':search_order'] = $search;
            $params[':search_buyer'] = $search;
            $params[':search_store'] = $search;
        }

        $whereSql = implode(' AND ', $where);
        $countStmt = getDB()->prepare(
            "SELECT COUNT(*)
             FROM invoices i
             JOIN orders o ON o.id = i.order_id
             JOIN users buyer ON buyer.id = o.buyer_id
             JOIN users store_user ON store_user.id = o.store_id
             LEFT JOIN store_profiles sp ON sp.user_id = o.store_id
             WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = getDB()->prepare(
            "SELECT i.*,
                    o.order_code,
                    o.status AS order_status,
                    o.store_id,
                    o.buyer_id,
                    buyer.email AS buyer_email,
                    buyer.full_name AS buyer_name,
                    store_user.email AS store_email,
                    sp.store_name
             FROM invoices i
             JOIN orders o ON o.id = i.order_id
             JOIN users buyer ON buyer.id = o.buyer_id
             JOIN users store_user ON store_user.id = o.store_id
             LEFT JOIN store_profiles sp ON sp.user_id = o.store_id
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

    public static function detailForActor(int $invoiceId, array $actor): ?array
    {
        [$where, $params] = self::whereForActor($actor);
        $where[] = 'i.id = :id';
        $params[':id'] = $invoiceId;
        $invoice = self::fetchInvoice(implode(' AND ', $where), $params);

        if (!$invoice) {
            return null;
        }

        $invoice['items'] = self::itemsForOrder((int) $invoice['order_id']);

        return $invoice;
    }

    public static function invoiceableOrdersForActor(array $actor, string $search = '', int $limit = 60): array
    {
        if (self::isBuyerScope($actor)) {
            return [];
        }

        [$where, $params] = self::orderWhereForActor($actor);
        $where[] = 'i.id IS NULL';
        $where[] = 'o.status NOT IN ("cancelled", "refunded")';

        if (trim($search) !== '') {
            $keyword = '%' . trim($search) . '%';
            $where[] = '(o.order_code LIKE :search_order OR buyer.email LIKE :search_buyer OR sp.store_name LIKE :search_store)';
            $params[':search_order'] = $keyword;
            $params[':search_buyer'] = $keyword;
            $params[':search_store'] = $keyword;
        }

        $stmt = getDB()->prepare(
            'SELECT o.id,
                    o.order_code,
                    o.status,
                    o.final_amount,
                    buyer.email AS buyer_email,
                    COALESCE(sp.store_name, store_user.email) AS store_name
             FROM orders o
             LEFT JOIN invoices i ON i.order_id = o.id
             JOIN users buyer ON buyer.id = o.buyer_id
             JOIN users store_user ON store_user.id = o.store_id
             LEFT JOIN store_profiles sp ON sp.user_id = o.store_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY o.created_at DESC
             LIMIT :limit'
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', min(MAX_PAGE_SIZE, max(1, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function generateForActor(int $orderId, array $actor): int
    {
        if (self::isBuyerScope($actor)) {
            throw new RuntimeException('Buyer chi được tai hóa đơn đã phat hanh.');
        }

        $db = getDB();
        $db->beginTransaction();

        try {
            $order = self::lockOrder($orderId);

            if (!$order) {
                throw new RuntimeException('Không tìm thấy đơn hàng.');
            }

            if (!self::canActorAccessOrder($actor, $order)) {
                throw new RuntimeException('Bạn không có quyền xuất hóa đơn cho don này.');
            }

            if (in_array($order['status'], [ORDER_STATUS_CANCELLED, ORDER_STATUS_REFUNDED], true)) {
                throw new RuntimeException('Đơn hàng đã huy/hoàn tiền không thể xuất hóa đơn.');
            }

            $existing = self::invoiceByOrderId($orderId);

            if ($existing) {
                $db->commit();

                return (int) $existing['id'];
            }

            $invoiceCode = self::generateInvoiceCode();
            $taxRate = 0.08; // 8% VAT
            $taxAmount = round((float) $order['final_amount'] * $taxRate, 2);
            $totalAmount = (float) $order['final_amount'] + $taxAmount;

            $stmt = $db->prepare(
                'INSERT INTO invoices (
                    invoice_code,
                    order_id,
                    issued_to,
                    issued_by,
                    total_amount,
                    tax_amount,
                    pdf_url
                ) VALUES (
                    :invoice_code,
                    :order_id,
                    :issued_to,
                    :issued_by,
                    :total_amount,
                    :tax_amount,
                    :pdf_url
                )'
            );
            $stmt->execute([
                ':invoice_code' => $invoiceCode,
                ':order_id' => $orderId,
                ':issued_to' => (int) $order['buyer_id'],
                ':issued_by' => (int) $actor['id'],
                ':total_amount' => $totalAmount,
                ':tax_amount' => $taxAmount,
                ':pdf_url' => null,
            ]);
            $invoiceId = (int) $db->lastInsertId();
            $relativePath = 'exports/invoices/' . $invoiceCode . '.pdf';
            $invoice = self::fetchByIdForPdf($invoiceId);

            if (!$invoice) {
                throw new RuntimeException('Không thể doc lai hóa đơn vua tao.');
            }

            $invoice['items'] = self::itemsForOrder($orderId);
            PdfInvoiceService::writeInvoice($invoice, BASE_PATH . '/' . $relativePath);

            $update = $db->prepare('UPDATE invoices SET pdf_url = :pdf_url WHERE id = :id');
            $update->execute([
                ':id' => $invoiceId,
                ':pdf_url' => $relativePath,
            ]);

            $db->commit();

            return $invoiceId;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function ensurePdfFile(array $invoice): string
    {
        $pdfUrl = (string) ($invoice['pdf_url'] ?? '');

        if ($pdfUrl === '') {
            $pdfUrl = 'exports/invoices/' . $invoice['invoice_code'] . '.pdf';
            $stmt = getDB()->prepare('UPDATE invoices SET pdf_url = :pdf_url WHERE id = :id');
            $stmt->execute([
                ':id' => (int) $invoice['id'],
                ':pdf_url' => $pdfUrl,
            ]);
            $invoice['pdf_url'] = $pdfUrl;
        }

        $path = BASE_PATH . '/' . ltrim($pdfUrl, '/');

        if (!str_starts_with($path, EXPORT_DIR . '/')) {
            throw new RuntimeException('Duong dan hóa đơn không hop le.');
        }

        if (!is_file($path)) {
            $invoice['items'] = $invoice['items'] ?? self::itemsForOrder((int) $invoice['order_id']);
            PdfInvoiceService::writeInvoice($invoice, $path);
        }

        return $path;
    }

    private static function whereForActor(array $actor): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (self::isAdminScope($actor)) {
            return [$where, $params];
        }

        if (self::isBuyerScope($actor)) {
            $where[] = 'o.buyer_id = :buyer_id';
            $params[':buyer_id'] = (int) $actor['id'];

            return [$where, $params];
        }

        $storeId = self::storeIdForActor($actor);
        $where[] = 'o.store_id = :store_id';
        $params[':store_id'] = $storeId;

        return [$where, $params];
    }

    private static function orderWhereForActor(array $actor): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (self::isAdminScope($actor)) {
            return [$where, $params];
        }

        $storeId = self::storeIdForActor($actor);
        $where[] = 'o.store_id = :store_id';
        $params[':store_id'] = $storeId;

        return [$where, $params];
    }

    private static function canActorAccessOrder(array $actor, array $order): bool
    {
        if (self::isAdminScope($actor)) {
            return true;
        }

        return self::storeIdForActor($actor) === (int) $order['store_id'];
    }

    private static function isAdminScope(array $actor): bool
    {
        return in_array($actor['user_type'], [USER_TYPE_ADMIN, USER_TYPE_SUB_ADMIN_ACTIVE], true);
    }

    private static function isBuyerScope(array $actor): bool
    {
        return $actor['user_type'] === USER_TYPE_USER;
    }

    private static function storeIdForActor(array $actor): int
    {
        if ($actor['user_type'] === USER_TYPE_STORE_APPROVED) {
            return (int) $actor['id'];
        }

        if ($actor['user_type'] === USER_TYPE_STORE_EMPLOYEE) {
            $stmt = getDB()->prepare(
                'SELECT store_id
                 FROM store_employees
                 WHERE employee_id = :employee_id AND is_active = 1
                 LIMIT 1'
            );
            $stmt->execute([':employee_id' => (int) $actor['id']]);
            $storeId = $stmt->fetchColumn();

            if ($storeId) {
                return (int) $storeId;
            }
        }

        throw new RuntimeException('Không tìm thấy shop cho tài khoản này.');
    }

    private static function fetchInvoice(string $whereSql, array $params): ?array
    {
        $stmt = getDB()->prepare(
            "SELECT i.*,
                    o.order_code,
                    o.status AS order_status,
                    o.total_amount AS order_total_amount,
                    o.shipping_fee,
                    o.discount_amount,
                    o.final_amount,
                    o.buyer_id,
                    o.store_id,
                    buyer.full_name AS buyer_name,
                    buyer.email AS buyer_email,
                    store_user.email AS store_email,
                    sp.store_name,
                    issuer.full_name AS issued_by_name
             FROM invoices i
             JOIN orders o ON o.id = i.order_id
             JOIN users buyer ON buyer.id = o.buyer_id
             JOIN users store_user ON store_user.id = o.store_id
             LEFT JOIN store_profiles sp ON sp.user_id = o.store_id
             JOIN users issuer ON issuer.id = i.issued_by
             WHERE {$whereSql}
             LIMIT 1"
        );
        $stmt->execute($params);
        $invoice = $stmt->fetch();

        return $invoice ?: null;
    }

    private static function fetchByIdForPdf(int $invoiceId): ?array
    {
        return self::fetchInvoice('i.id = :id', [':id' => $invoiceId]);
    }

    private static function invoiceByOrderId(int $orderId): ?array
    {
        $stmt = getDB()->prepare('SELECT * FROM invoices WHERE order_id = :order_id LIMIT 1');
        $stmt->execute([':order_id' => $orderId]);
        $invoice = $stmt->fetch();

        return $invoice ?: null;
    }

    private static function lockOrder(int $orderId): ?array
    {
        $stmt = getDB()->prepare(
            'SELECT *
             FROM orders
             WHERE id = :id
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch();

        return $order ?: null;
    }

    private static function itemsForOrder(int $orderId): array
    {
        $stmt = getDB()->prepare(
            'SELECT *
             FROM order_items
             WHERE order_id = :order_id
             ORDER BY id ASC'
        );
        $stmt->execute([':order_id' => $orderId]);

        return $stmt->fetchAll();
    }

    private static function generateInvoiceCode(): string
    {
        do {
            $code = 'INV-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $stmt = getDB()->prepare('SELECT id FROM invoices WHERE invoice_code = :code LIMIT 1');
            $stmt->execute([':code' => $code]);
        } while ($stmt->fetch());

        return $code;
    }
}
