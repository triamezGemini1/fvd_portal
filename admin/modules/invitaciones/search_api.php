<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';
require_once FVD_PROJECT_ROOT . '/src/Services/InvitacionService.php';
require_once FVD_PROJECT_ROOT . '/src/Services/QueryHelper.php';

use FvdPortal\Services\InvitacionService;
use FvdPortal\Services\QueryHelper;

fvd_admin_require_roles([
    AuthService::ROLE_FVD_ADMIN,
    AuthService::ROLE_ASO_ADMIN,
    AuthService::ROLE_DELEGADO_ASOC,
]);

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

try {
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $estadoFiltro = isset($_GET['estado']) ? trim((string) $_GET['estado']) : '';
    if (!in_array($estadoFiltro, ['pendiente', 'aceptada', 'expirada', ''], true)) {
        $estadoFiltro = '';
    }

    $pdo = fvd_db();
    InvitacionService::ensureTable($pdo);
    $perPage = 8;
    $filtros = InvitacionService::filtrosListado($estadoFiltro);
    $paged = QueryHelper::selectPaginado('fvd_invitaciones', $filtros, $page, $perPage, $pdo);

    $rowTpl = FVD_PROJECT_ROOT . '/templates/components/invitacion_row.php';
    ob_start();
    foreach ($paged['registros'] as $raw) {
        $r = InvitacionService::filaVista($raw);
        require $rowTpl;
    }
    if ($paged['registros'] === []) {
        echo '<tr><td colspan="7" style="padding:10px">Sin registros con este filtro.</td></tr>';
    }
    $tbodyHtml = ob_get_clean();

    $pages = (int) $paged['paginas'];
    $total = (int) $paged['total'];

    ob_start();
    ?>
    <span><?= $total ?> reg. · pág. <?= $page ?>/<?= $pages ?></span>
    <?php if ($page > 1): ?>
        <a href="#" data-fvd-page="<?= $page - 1 ?>">Anterior</a>
    <?php endif; ?>
    <?php if ($page < $pages): ?>
        <a href="#" data-fvd-page="<?= $page + 1 ?>">Siguiente</a>
    <?php endif; ?>
    <?php
    $pagerHtml = trim(preg_replace('/\s+/', ' ', ob_get_clean()));

    echo json_encode([
        'ok'         => true,
        'tbody_html' => $tbodyHtml,
        'pager_html' => $pagerHtml,
        'total'      => $total,
        'page'       => $page,
        'pages'      => $pages,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Error al cargar invitaciones.',
    ], JSON_UNESCAPED_UNICODE);
    error_log('[invitaciones/search_api] ' . $e->getMessage());
}
