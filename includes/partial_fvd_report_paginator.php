<?php

declare(strict_types=1);

require_once __DIR__ . '/fvd_report_pagination.php';

/** @var array $fvd_repPaginator */
/** @var string $fvd_repPaginatorSelf */

if (empty($fvd_repPaginator) || !is_array($fvd_repPaginator) || empty($fvd_repPaginatorSelf)) {
    return;
}

$p = $fvd_repPaginator;
$total = (int) ($p['total'] ?? 0);
$pages = (int) ($p['pages'] ?? 1);
$page = (int) ($p['page'] ?? 1);
$per = (int) ($p['per_page'] ?? 16);
$self = (string) $fvd_repPaginatorSelf;

if ($total <= 0) {
    return;
}

$from = ($page - 1) * $per + 1;
$to = min($total, $page * $per);
$prevUrl = $page > 1 ? fvd_report_paginator_url($self, $page - 1) : null;
$nextUrl = $page < $pages ? fvd_report_paginator_url($self, $page + 1) : null;

?>
<nav class="fvd-report-paginator no-print" aria-label="Paginación del informe">
    <p class="fvd-report-paginator__meta">
        <span class="fvd-report-paginator__range"><?= (int) $from ?>–<?= (int) $to ?></span>
        de <strong><?= (int) $total ?></strong> · Página <?= (int) $page ?>/<?= (int) $pages ?>
        · <span class="fvd-report-paginator__hint"><?= (int) $per ?> filas por pantalla</span>
    </p>
    <div class="fvd-report-paginator__nav">
        <?php if ($prevUrl !== null): ?>
            <a class="fvd-report-paginator__btn" href="<?= htmlspecialchars($prevUrl, ENT_QUOTES, 'UTF-8') ?>">← Anterior</a>
        <?php else: ?>
            <span class="fvd-report-paginator__btn fvd-report-paginator__btn--disabled" aria-disabled="true">← Anterior</span>
        <?php endif; ?>
        <?php if ($nextUrl !== null): ?>
            <a class="fvd-report-paginator__btn" href="<?= htmlspecialchars($nextUrl, ENT_QUOTES, 'UTF-8') ?>">Siguiente →</a>
        <?php else: ?>
            <span class="fvd-report-paginator__btn fvd-report-paginator__btn--disabled" aria-disabled="true">Siguiente →</span>
        <?php endif; ?>
    </div>
</nav>
