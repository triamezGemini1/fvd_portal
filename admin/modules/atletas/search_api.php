<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';
require_once FVD_PROJECT_ROOT . '/src/Services/QueryHelper.php';
require_once FVD_PROJECT_ROOT . '/src/Services/PaginationView.php';
use FvdPortal\Services\PaginationView;
use FvdPortal\Services\QueryHelper;

fvd_admin_require_roles();

require_once __DIR__ . '/list_filters.inc.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

try {
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
    $cedula = isset($_GET['cedula']) ? trim((string) $_GET['cedula']) : '';
    $filtrosParam = isset($_GET['filtros']) ? (string) $_GET['filtros'] : '';

    $lf = fvd_atletas_resolve_list_filters($_GET);
    $alcance = $lf['alcance'];
    $tipo = $lf['tipo'];
    $asociacionFiltroId = $lf['asociacion_id'];
    $marcadorApi = fvd_atletas_resolve_marcador($_GET);

    $filtros = [
        '__cedula'         => $cedula,
        '__nombre'         => $q,
        '__alcance'        => $alcance,
        '__tipo'           => $tipo,
        '__asociacion_id'  => $asociacionFiltroId,
    ];
    if ($marcadorApi !== '') {
        $filtros['__marcador'] = $marcadorApi;
    }

    if ($filtrosParam !== '') {
        $decoded = json_decode($filtrosParam, true);
        if (is_array($decoded)) {
            foreach ($decoded as $k => $v) {
                if (!is_string($k) || $k === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $k)) {
                    continue;
                }
                if ($k === '__ficha_filtro' || $k === '__revision_delegado' || $k === '__list_mode') {
                    continue;
                }
                if (is_scalar($v) || $v === null) {
                    $filtros[$k] = $v;
                }
            }
        }
    }

    $perPage = 12;
    $paged = QueryHelper::selectPaginado('atletas', $filtros, $page, $perPage, fvd_db());

    $selfUrl = fvd_crud_self_url('atletas');
    $atletaRowTpl = FVD_PROJECT_ROOT . '/templates/components/atleta_table_row.php';
    $fvd_puede_traspaso = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
    $fvd_atletas_show_asociacion_col = !($alcance === 'asociacion' && $asociacionFiltroId > 0);
    if (!function_exists('url')) {
        require_once FVD_PROJECT_ROOT . '/config/paths.php';
    }
    $fvd_url_solicitud_carnet_base = url('fvdmasteradmin/solicitud_carnet.php');
    $fvd_url_solicitud_traspaso_base = url('fvdmasteradmin/solicitud_traspaso.php');
    $fvd_atletas_delegado_line = AuthService::isDelegadoAsociacion();
    $fvd_delegado_traspaso_destinos = [];
    if ($fvd_atletas_delegado_line) {
        $myAsTr = (int) (AuthService::idAsociacion() ?? 0);
        if ($myAsTr > 0) {
            try {
                $stTr = fvd_db()->prepare('SELECT id, nombre FROM asociaciones WHERE id <> :my ORDER BY nombre ASC');
                $stTr->execute([':my' => $myAsTr]);
                $fvd_delegado_traspaso_destinos = $stTr->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                error_log('[atletas/search_api] traspaso destinos: ' . $e->getMessage());
            }
        }
    }

    ob_start();
    foreach ($paged['registros'] as $r) {
        require $atletaRowTpl;
    }
    if ($paged['registros'] === []) {
        if ($fvd_atletas_delegado_line) {
            $cs = '6';
        } else {
            $cs = $fvd_atletas_show_asociacion_col ? '9' : '8';
        }
        echo '<tr><td colspan="' . $cs . '" style="padding:12px">Sin registros con los filtros actuales.</td></tr>';
    }
    $tbodyHtml = ob_get_clean();

    $pages = (int) $paged['paginas'];
    $total = (int) $paged['total'];

    $pagerHtml = trim(preg_replace('/\s+/', ' ', PaginationView::navPrefetchHtml($page, $pages, $total)));

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
