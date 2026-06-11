<?php
/**
 * Trình phân trang chung cho các màn hình dạng bảng.
 * Yêu cầu: 
 * - $pagination: array (page, limit, total, total_pages)
 * - Các tham số filter khác trên URL cần được giữ nguyên, truyền qua $_GET.
 */

$totalPages = $pagination['total_pages'] ?? 1;
$currentPage = $pagination['page'] ?? 1;
$currentLimit = $pagination['limit'] ?? 20;

// Các tham số ẩn để giữ filter/sort
$hiddenFields = '';
foreach ($_GET as $key => $val) {
    if ($key !== 'page' && $key !== 'limit') {
        $hiddenFields .= '<input type="hidden" name="' . htmlspecialchars((string) $key) . '" value="' . htmlspecialchars((string) $val) . '">';
    }
}
?>

<div class="pagination-container" style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; flex-wrap: wrap; gap: 15px;">
    <div class="limit-selector">
        <form method="get" style="display: inline-flex; align-items: center; gap: 8px;">
            <?= $hiddenFields ?>
            <input type="hidden" name="page" value="1">
            <span>Hiển thị:</span>
            <select name="limit" onchange="this.form.submit()" style="padding: 4px 35px; border-radius: var(--ui-radius-control); border: 1px solid var(--ui-border);">
                <?php foreach ([10, 20, 50, 100] as $opt): ?>
                    <option value="<?= $opt ?>" <?= $currentLimit === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    
    <div class="pagination-pages" style="display: flex; gap: 5px;">
        <?php
        $queryParams = $_GET;
        $getPageUrl = function(int $p) use ($queryParams) {
            $queryParams['page'] = $p;
            return '?' . http_build_query($queryParams);
        };
        
        if ($totalPages > 1):
            if ($currentPage > 1): ?>
                <a href="<?= $getPageUrl($currentPage - 1) ?>" class="page-link">&laquo; Trước</a>
            <?php endif;
            
            for ($i = 1; $i <= $totalPages; $i++):
                if ($i === $currentPage): ?>
                    <span class="page-link active"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= $getPageUrl($i) ?>" class="page-link"><?= $i ?></a>
                <?php endif;
            endfor;
            
            if ($currentPage < $totalPages): ?>
                <a href="<?= $getPageUrl($currentPage + 1) ?>" class="page-link">Sau &raquo;</a>
            <?php endif;
        endif;
        ?>
    </div>
</div>
