<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/src/Service/AtletasModuleService.php';
require_once dirname(__DIR__) . '/src/Service/AtletasDomainService.php';
require_once dirname(__DIR__) . '/src/Controller/AtletasController.php';

use FvdPortal\Services\PaginationView;
use FvdPortal\Services\QueryHelper;

fvd_admin_require_roles();

$svc = new FvdAdminService();
$selfUrl = url('modules/atletas_migrated/public/index.php');
$fvd_error = '';

$moduleService = new AtletasModuleService();
$domain = new AtletasDomainService($svc, $selfUrl);
$controller = new AtletasController($domain);
$controller->handleMutations($fvd_error);

$fvd_page_title = 'Atletas (Migrado)';
$action = $moduleService->resolveAction($_GET);
$id = $moduleService->resolveId($_GET);

if ($action === 'list') {
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $cedula = isset($_GET['cedula']) ? trim((string) $_GET['cedula']) : '';
    $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
    $fvd_puede_traspaso = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
    $perPage = 12;

    $lf = fvd_atletas_resolve_list_filters($_GET);
    $fvd_atletas_alcance = $lf['alcance'];
    $fvd_atletas_tipo = $lf['tipo'];
    $asociacionFiltroId = $lf['asociacion_id'];
    $fvd_atletas_marcador = fvd_atletas_resolve_marcador($_GET);

    $filtrosAtletasList = [
        '__cedula'         => $cedula,
        '__nombre'         => $q,
        '__alcance'        => $fvd_atletas_alcance,
        '__tipo'           => $fvd_atletas_tipo,
        '__asociacion_id'  => $asociacionFiltroId,
    ];
    if ($fvd_atletas_marcador !== '') {
        $filtrosAtletasList['__marcador'] = $fvd_atletas_marcador;
    }

    $paged = QueryHelper::selectPaginado(
        'atletas',
        $filtrosAtletasList,
        $page,
        $perPage,
        fvd_db()
    );

    $result = [
        'total'    => $paged['total'],
        'page'     => $page,
        'per_page' => $perPage,
        'pages'    => $paged['paginas'],
        'rows'     => $paged['registros'],
    ];

    $fvd_atletas_show_asociacion_col = !($fvd_atletas_alcance === 'asociacion' && $asociacionFiltroId > 0);
    $fvd_asociacion_header = null;
    $fvd_atletas_puede_elegir_alcance = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
    $fvd_asociaciones_list_filter = $svc->atletasListAsociacionesForSelect();

    $paginationQueryParams = [
        'action' => 'list',
        'cedula' => $cedula,
        'q' => $q,
        'alcance' => $fvd_atletas_alcance,
        'tipo' => $fvd_atletas_tipo,
    ];
    if ($fvd_atletas_alcance === 'asociacion' && $asociacionFiltroId > 0) {
        $paginationQueryParams['asociacion_id'] = $asociacionFiltroId;
    }
    if ($fvd_atletas_marcador !== '') {
        $paginationQueryParams['marcador'] = $fvd_atletas_marcador;
    }

    $fvdAtletasSite = $moduleService->buildAtletasSiteFlag();
    $atletasSearchApiUrl = $moduleService->buildSearchApiUrl($fvdAtletasSite);
    $atletasExportUrl = $moduleService->buildExportUrl($fvdAtletasSite);
    $atletasReportBaseUrl = $moduleService->buildReportBaseUrl($fvdAtletasSite);

    $fvd_atletas_pager_html = PaginationView::navHtml(
        $selfUrl,
        (int) $result['page'],
        (int) $result['pages'],
        (int) $result['total'],
        $paginationQueryParams,
        'fvd-atletas-pager'
    );

    require_once FVD_PROJECT_ROOT . '/src/Services/StatsService.php';
    $fvd_atletas_widget = \FvdPortal\Services\StatsService::atletasModuloWidgetResumen(
        fvd_db(),
        $fvd_atletas_alcance,
        $asociacionFiltroId
    );

    require FVD_MASTER_ROOT . '/includes/layout_header.php';
    include dirname(__DIR__) . '/views/list.view.php';
    require FVD_MASTER_ROOT . '/includes/layout_footer.php';
    exit;
}

header('Location: ' . $selfUrl . '?action=list');
exit;
