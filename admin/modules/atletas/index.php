<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';
require_once FVD_PROJECT_ROOT . '/src/Services/QueryHelper.php';
require_once FVD_PROJECT_ROOT . '/src/Services/PaginationView.php';
use FvdPortal\Services\PaginationView;
use FvdPortal\Services\QueryHelper;

fvd_admin_require_roles();

require_once __DIR__ . '/list_filters.inc.php';

$svc = new FvdAdminService();
$selfUrl = fvd_crud_self_url('atletas');
$fvd_error = '';

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    $svc->atletasDelete((int) $_GET['id']);
    header('Location: ' . fvd_return_preserve_query_params($selfUrl . '?action=list'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'toggle_activo' && AuthService::role() === AuthService::ROLE_FVD_ADMIN) {
    $tid = (int) ($_POST['id'] ?? 0);
    if ($tid > 0) {
        $svc->atletasToggleActivo($tid);
    }
    header('Location: ' . fvd_return_preserve_query_params($selfUrl . '?action=list'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'dar_baja') {
    $tid = (int) ($_POST['id'] ?? 0);
    if ($tid > 0) {
        $svc->atletasDarBaja($tid);
    }
    header('Location: ' . fvd_return_preserve_query_params($selfUrl . '?action=list'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'restaurar_atleta') {
    $tid = (int) ($_POST['id'] ?? 0);
    if ($tid > 0) {
        $svc->atletasRestaurarDesdeBaja($tid);
    }
    header('Location: ' . fvd_return_preserve_query_params($selfUrl . '?action=list'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'save') {
    try {
        $sid = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
        $svc->atletasSave($sid, $_POST, $_FILES);
        if ($sid !== null) {
            header('Location: ' . fvd_return_preserve_query_params($selfUrl . '?action=form&id=' . $sid));
        } else {
            $redir = $selfUrl;
            if (AuthService::isDelegadoAsociacion()) {
                $mineAsoc = (int) (AuthService::idAsociacion() ?? 0);
                $redir = $selfUrl . '?action=list&alcance=asociacion&asociacion_id=' . $mineAsoc;
            }
            header('Location: ' . fvd_return_preserve_query_params($redir));
        }
        exit;
    } catch (Throwable $e) {
        $fvd_error = $e->getMessage();
        error_log('[admin/atletas] ' . $fvd_error);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'traspaso_confirm') {
    require_once FVD_PROJECT_ROOT . '/src/Services/TraspasoService.php';
    AuthService::ensureSession();
    if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Solo personal FVD puede confirmar traspasos.';
        exit;
    }
    $taid = isset($_POST['atleta_id']) ? (int) $_POST['atleta_id'] : 0;
    $tdest = isset($_POST['asociacion_destino_id']) ? (int) $_POST['asociacion_destino_id'] : 0;
    try {
        \FvdPortal\Services\TraspasoService::ejecutar(fvd_db(), $taid, $tdest, AuthService::userId());
        header('Location: ' . fvd_return_preserve_query_params($selfUrl . '?action=list'));
        exit;
    } catch (Throwable $e) {
        $_SESSION['fvd_traspaso_error'] = $e->getMessage();
        header('Location: ' . fvd_return_preserve_query_params($selfUrl . '?action=traspaso&id=' . $taid));
        exit;
    }
}

$fvd_page_title = 'Atletas';
$rawAction = isset($_GET['action']) ? trim((string) $_GET['action']) : '';
$impliesAtletasList = isset($_GET['page']) || isset($_GET['cedula'])
    || (isset($_GET['q']) && trim((string) $_GET['q']) !== '');
if ($rawAction === '') {
    $action = $impliesAtletasList ? 'list' : 'form';
} else {
    $action = $rawAction;
}
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

if ($action === 'lookup_cedula') {
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    $cedLookup = isset($_GET['cedula']) ? trim((string) $_GET['cedula']) : '';
    if ($cedLookup === '' || strlen($cedLookup) < 3) {
        echo json_encode(['found' => false]);

        exit;
    }
    $foundRow = $svc->atletasFindByCedula($cedLookup);
    if ($foundRow === null) {
        echo json_encode(['found' => false]);

        exit;
    }
    echo json_encode([
        'found' => true,
        'id' => (int) $foundRow['id'],
        'nombre' => (string) ($foundRow['nombre'] ?? ''),
        'redirect' => fvd_return_append_to_url($selfUrl . '?action=form&id=' . (int) $foundRow['id']),
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if ($action === 'traspaso') {
    if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Solo personal FVD puede gestionar traspasos.';
        exit;
    }
    require_once FVD_PROJECT_ROOT . '/src/Services/CarnetService.php';
    $tid = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($tid <= 0) {
        header('Location: ' . $selfUrl . '?action=list');
        exit;
    }
    $trow = $svc->atletasFind($tid);
    if ($trow === null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Atleta no encontrado o fuera de su ámbito.';
        exit;
    }
    $pdo = fvd_db();
    $trow = \FvdPortal\Services\CarnetService::enriquecerAsociacion($pdo, $trow);
    $allAsoc = $svc->atletasListAsociacionesForSelect();
    $currentAid = isset($trow['asociacion']) && $trow['asociacion'] !== null && $trow['asociacion'] !== ''
        ? (int) $trow['asociacion'] : 0;
    $asociacionesDestino = [];
    foreach ($allAsoc as $a) {
        if ((int) ($a['id'] ?? 0) !== $currentAid) {
            $asociacionesDestino[] = $a;
        }
    }
    AuthService::ensureSession();
    $traspasoError = '';
    if (!empty($_SESSION['fvd_traspaso_error'])) {
        $traspasoError = (string) $_SESSION['fvd_traspaso_error'];
        unset($_SESSION['fvd_traspaso_error']);
    }
    $cardBase = \FvdPortal\Services\CarnetService::prepararTarjeta($trow, FVD_PROJECT_ROOT);
    $cardPreview = $cardBase;
    $first = $asociacionesDestino[0] ?? null;
    if ($first !== null) {
        $cardPreview['asociacion'] = trim((string) ($first['nombre'] ?? '—'));
    }
    $asocMap = [];
    foreach ($asociacionesDestino as $a) {
        $asocMap[(string) (int) ($a['id'] ?? 0)] = trim((string) ($a['nombre'] ?? ''));
    }
    $sn = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $fvdAtletasSite = str_contains($sn, '/fvdmasteradmin/modules/atletas/')
        || (str_contains($sn, '/modules/atletas/') && !str_contains($sn, '/admin/modules/'));
    $carnetFotoApiUrl = $fvdAtletasSite
        ? fvd_master_module_url('atletas/carnet_foto_api.php')
        : admin_module_url('atletas/carnet_foto_api.php');
    $atletasListUrl = $selfUrl . '?action=list';
    $currentAsocNombre = trim((string) ($trow['asociacion_nombre'] ?? '—'));
    $atletaIdTraspaso = $tid;
    include __DIR__ . '/traspaso.view.php';
    exit;
}

if ($action === 'carnets') {
    require_once FVD_PROJECT_ROOT . '/src/Services/CarnetService.php';
    $idsRaw = isset($_GET['ids']) ? preg_split('/[\s,]+/', (string) $_GET['ids'], -1, PREG_SPLIT_NO_EMPTY) : [];
    $ids = [];
    foreach ($idsRaw as $p) {
        $i = (int) $p;
        if ($i > 0) {
            $ids[$i] = $i;
        }
    }
    $ids = array_slice(array_values($ids), 0, 36);
    $pdo = fvd_db();
    $carnetCards = [];
    foreach ($ids as $aid) {
        $row = $svc->atletasFind($aid);
        if ($row === null) {
            continue;
        }
        $row = \FvdPortal\Services\CarnetService::enriquecerAsociacion($pdo, $row);
        $carnetCards[] = \FvdPortal\Services\CarnetService::prepararTarjeta($row, FVD_PROJECT_ROOT);
    }
    $carnetIdsMarcar = [];
    foreach ($carnetCards as $c) {
        if (!empty($c['atleta_id'])) {
            $carnetIdsMarcar[] = (int) $c['atleta_id'];
        }
    }
    $sn = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $fvdAtletasSite = str_contains($sn, '/fvdmasteradmin/modules/atletas/')
        || (str_contains($sn, '/modules/atletas/') && !str_contains($sn, '/admin/modules/'));
    $carnetMarcarApiUrl = $fvdAtletasSite
        ? fvd_master_module_url('atletas/carnet_marcar_api.php')
        : admin_module_url('atletas/carnet_marcar_api.php');
    $carnetFotoApiUrl = $fvdAtletasSite
        ? fvd_master_module_url('atletas/carnet_foto_api.php')
        : admin_module_url('atletas/carnet_foto_api.php');
    $atletasListUrl = $selfUrl . '?action=list';
    $carnetVistaCompacta = count($carnetCards) === 1;
    include __DIR__ . '/carnets.view.php';
    exit;
}

if ($action === 'form') {
    $row = $svc->atletasFind($id);
    $asociaciones = $svc->atletasListAsociacionesForSelect();
    if ($id !== null && $row === null) {
        http_response_code(404);
        $fvd_page_title = 'No encontrado';
    }
    $fvd_form_embed = isset($_GET['embed']) && $_GET['embed'] === '1';
    if ($fvd_form_embed) {
        header('X-Frame-Options: SAMEORIGIN');
        if (!function_exists('url')) {
            require_once FVD_PROJECT_ROOT . '/config/paths.php';
        }
        $embedCss = url('assets/css/fvd-ui-mistorneos.css');
        header('Content-Type: text/html; charset=UTF-8');
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($fvd_page_title ?? 'Atleta', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($embedCss, ENT_QUOTES, 'UTF-8') ?>">
    <style>
        body.fvd-embed-atleta { margin: 0; padding: 0.65rem; background: var(--fvd-bg, #0f172a); min-height: 100vh; box-sizing: border-box; }
        body.fvd-embed-atleta,
        body.fvd-embed-atleta .fvd-atleta-form,
        body.fvd-embed-atleta .fvd-atleta-form * {
            color: #000 !important;
            font-weight: 700 !important;
        }
        body.fvd-embed-atleta .fvd-atleta-form.fvd-atleta-form--framed {
            background: #003366 !important;
        }
        body.fvd-embed-atleta .fvd-atleta-form { max-width: none; }
    </style>
</head>
<body class="fvd-embed-atleta">
        <?php
        include __DIR__ . '/form.view.php';
        ?>
</body>
</html>
        <?php
        exit;
    }
    require FVD_MASTER_ROOT . '/includes/layout_header.php';
    include __DIR__ . '/form.view.php';
    require FVD_MASTER_ROOT . '/includes/layout_footer.php';
    exit;
}

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$cedula = isset($_GET['cedula']) ? trim((string) $_GET['cedula']) : '';
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$fvd_puede_traspaso = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
$perPage = 12;

$lf = fvd_atletas_resolve_list_filters($_GET);
$fvd_atletas_alcance = $lf['alcance'];
$fvd_atletas_tipo = $lf['tipo'];
$asociacionFiltroId = $lf['asociacion_id'];

$paged = QueryHelper::selectPaginado(
    'atletas',
    [
        '__cedula'         => $cedula,
        '__nombre'         => $q,
        '__alcance'        => $fvd_atletas_alcance,
        '__tipo'           => $fvd_atletas_tipo,
        '__asociacion_id'  => $asociacionFiltroId,
    ],
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
if ($fvd_atletas_puede_elegir_alcance && $fvd_asociaciones_list_filter === []) {
    $stAsocAll = fvd_db()->query('SELECT id, nombre FROM asociaciones ORDER BY nombre ASC');
    $fvd_asociaciones_list_filter = $stAsocAll ? $stAsocAll->fetchAll(PDO::FETCH_ASSOC) : [];
}
if ($fvd_atletas_alcance === 'asociacion' && $asociacionFiltroId > 0) {
    $stH = fvd_db()->prepare('SELECT id, nombre, logo, delegado FROM asociaciones WHERE id = :id LIMIT 1');
    $stH->execute([':id' => $asociacionFiltroId]);
    $rowH = $stH->fetch(PDO::FETCH_ASSOC);
    if (is_array($rowH)) {
        $fvd_asociacion_header = $rowH;
    }
}

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
$paginationQueryParams = fvd_return_merge_get_params($paginationQueryParams);

$sn = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
$fvdAtletasSite = str_contains($sn, '/fvdmasteradmin/modules/atletas/')
    || (str_contains($sn, '/modules/atletas/') && !str_contains($sn, '/admin/modules/'));
$atletasSearchApiUrl = $fvdAtletasSite
    ? fvd_master_module_url('atletas/search_api.php')
    : admin_module_url('atletas/search_api.php');
$atletasSearchApiUrl = fvd_return_preserve_query_params($atletasSearchApiUrl);
$atletasExportUrl = $fvdAtletasSite
    ? fvd_master_module_url('atletas/export.php')
    : admin_module_url('atletas/export.php');
$atletasExportUrl = fvd_return_preserve_query_params($atletasExportUrl);
$atletasReportBaseUrl = $fvdAtletasSite
    ? fvd_master_module_url('atletas/')
    : admin_module_url('atletas/');
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
include __DIR__ . '/list.view.php';
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
