<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

$user = PermissionMiddleware::requireUserType(USER_TYPE_ADMIN);
$db = getDB();
$tables = dbTables();
$selectedTable = (string) ($_GET['table'] ?? ($tables[0] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(100, max(10, (int) ($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;
$rows = [];
$columns = [];
$total = 0;
$error = null;

if ($selectedTable !== '' && in_array($selectedTable, $tables, true)) {
    try {
        $quoted = '`' . str_replace('`', '``', $selectedTable) . '`';
        $total = (int) $db->query("SELECT COUNT(*) FROM {$quoted}")->fetchColumn();
        $stmt = $db->prepare("SELECT * FROM {$quoted} LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $columns = $rows ? array_keys($rows[0]) : dbColumns($selectedTable);
    } catch (Throwable $e) {
        $error = APP_DEBUG ? $e->getMessage() : 'Không doc được bang.';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Viewer</title>
    <link rel="stylesheet" href="/assets/css/global.css?v=20260611-17">
</head>
<body class="portal-page">
    <main class="portal-shell portal-wide">
        <?php include __DIR__ . "/_admin_nav.php"; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <section class="portal-panel">
            <h1>Xem database</h1>
            <form method="get" class="filter-row">
                <select name="table">
                    <?php foreach ($tables as $table): ?>
                        <option value="<?= htmlspecialchars($table) ?>" <?= $selectedTable === $table ? 'selected' : '' ?>>
                            <?= htmlspecialchars($table) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="limit">
                    <?php foreach ([25, 50, 100] as $size): ?>
                        <option value="<?= $size ?>" <?= $limit === $size ? 'selected' : '' ?>><?= $size ?> dòng</option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Xem</button>
            </form>

            <p class="muted">Bảng <?= htmlspecialchars($selectedTable) ?> có <?= $total ?> dòng. Trang <?= $page ?>.</p>

            <div class="table-wrap db-table-wrap">
                <table class="data-table db-table">
                    <thead>
                        <tr>
                            <?php foreach ($columns as $column): ?>
                                <th><?= htmlspecialchars((string) $column) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="<?= max(1, count($columns)) ?>" class="empty">Không có dữ liệu.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php foreach ($columns as $column): ?>
                                    <td><?= htmlspecialchars(shortDbValue($row[$column] ?? null)) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="pager">
                <?php if ($page > 1): ?>
                    <a class="button-link" href="/admin/db.php?table=<?= urlencode($selectedTable) ?>&limit=<?= $limit ?>&page=<?= $page - 1 ?>">Trang trước</a>
                <?php endif; ?>
                <?php if (($offset + $limit) < $total): ?>
                    <a class="button-link" href="/admin/db.php?table=<?= urlencode($selectedTable) ?>&limit=<?= $limit ?>&page=<?= $page + 1 ?>">Trang sau</a>
                <?php endif; ?>
            </div>
        </section>
    </main>
    <script src="/assets/js/global.js?v=admin-ui-20260608"></script>
</body>
</html>
<?php

function dbTables(): array
{
    $stmt = getDB()->query(
        'SELECT table_name
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
         ORDER BY table_name'
    );

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function dbColumns(string $table): array
{
    $stmt = getDB()->prepare(
        'SELECT column_name
         FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = :table
         ORDER BY ordinal_position'
    );
    $stmt->execute([':table' => $table]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function shortDbValue(mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }

    $text = (string) $value;

    return strlen($text) > 180 ? substr($text, 0, 180) . '...' : $text;
}
