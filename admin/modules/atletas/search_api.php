<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';
require_once FVD_PROJECT_ROOT . '/src/Services/QueryHelper.php';
require_once FVD_PROJECT_ROOT . '/src/Services/FvdAdminRevisionPendienteService.php';

use FvdPortal\Services\QueryHelper;

fvd_admin_require_roles();

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

try {
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
    $cedula = isset($_GET['cedula']) ? trim((string) $_GET['cedula']) : '';
    $filtrosParam = isset($_GET['filtros']) ? (string) $_GET['filtros'] : '';

    $filtros = [
        '__cedula' => $cedula,
        '__nombre' => $q,
    ];

    if ($filtrosParam !== '') {
        $decoded = json_decode($filtrosParam, true);
        if (is_array($decoded)) {
            foreach ($decoded as $k => $v) {
                if (!is_string($k) || $k === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $k)) {
                    continue;
                }
                if ($k === '__ficha_filtro' || $k === '__revision_delegado') {
                    continue;
                }
                if (is_scalar($v) || $v === null) {
                    $filtros[$k] = $v;
                }
            }
        }
    }

    $tab = isset($_GET['tab']) && $_GET['tab'] === 'ficha' ? 'ficha' : 'list';
    $fichaFiltro = '';
    if ($tab === 'ficha') {
        $fichaFiltro = isset($_GET['ficha']) ? trim((string) $_GET['ficha']) : '';
        if (!in_array($fichaFiltro, ['sin_carnet', 'carnet_solicitado', 'carnet_emitido', 'ficha_vencida'], true)) {
            $fichaFiltro = '';
        }
        if ($fichaFiltro === 'carnet_emitido') {
            $fichaFiltro = 'carnet_solicitado';
        }
    }
    $filtros['__ficha_filtro'] = $fichaFiltro;

    $revisionDelegado = AuthService::isSuperAdmin()
        && isset($_GET['revision_delegado'])
        && (string) $_GET['revision_delegado'] === '1';
    if ($revisionDelegado) {
        \FvdPortal\Services\FvdAdminRevisionPendienteService::ensureAltaDesdeDelegadoColumn(fvd_db());
    }
    $filtros['__revision_delegado'] = $revisionDelegado ? '1' : '0';

    $perPage = 12;
    $paged = QueryHelper::selectPaginado('atletas', $filtros, $page, $perPage, fvd_db());

    $selfUrl = fvd_crud_self_url('atletas');
    $atletaRowTpl = FVD_PROJECT_ROOT . '/templates/components/atleta_table_row.php';
    $fvd_puede_traspaso = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
    $appBaseAtletas = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');
    $fvd_url_solicitud_carnet_base = $appBaseAtletas !== '' ? $appBaseAtletas . '/fvdmasteradmin/solicitud_carnet.php' : '/fvdmasteradmin/solicitud_carnet.php';

    ob_start();
    foreach ($paged['registros'] as $r) {
        require $atletaRowTpl;
    }
    if ($paged['registros'] === []) {
        echo '<tr><td colspan="12" style="padding:12px">Sin registros con los filtros actuales.</td></tr>';
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
        'per_page'   => $perPage,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Error al cargar resultados.',
    ], JSON_UNESCAPED_UNICODE);
    error_log('[atletas/search_api] ' . $e->getMessage());
}
