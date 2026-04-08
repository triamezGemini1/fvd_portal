<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/_init.php';
fvd_module_require_roles();

require_once __DIR__ . '/Controller.php';
$ctrl = new DeudaAsociacionController();

$selfUrl = fvd_module_url('deuda_asociacion/index.php');
$fvd_error = '';

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['tid'], $_GET['aid'])) {
    $ctrl->delete((int) $_GET['tid'], (int) $_GET['aid']);
    header('Location: ' . $selfUrl);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'save') {
    try {
        $ctrl->save((int) $_POST['torneo_id'], (int) $_POST['asociacion_id'], $_POST);
        header('Location: ' . $selfUrl);
        exit;
    } catch (Throwable $e) {
        $fvd_error = $e->getMessage();
        error_log('[deuda_asociacion] ' . $fvd_error);
    }
}

$fvd_page_title = 'Deudas por asociación';
$action = $_GET['action'] ?? 'list';
$tid = isset($_GET['tid']) ? (int) $_GET['tid'] : null;
$aid = isset($_GET['aid']) ? (int) $_GET['aid'] : null;

if ($action === 'form' && $tid !== null && $aid !== null) {
    $row = $ctrl->find($tid, $aid);
    $fvdCostoTarifa = $row !== null ? $ctrl->ultimoCostoTarifa() : null;
    if ($row === null) {
        http_response_code(404);
        $fvd_page_title = 'No encontrado';
    } else {
        $fvd_page_title = 'Reporte detallado de costos';
    }
    require FVD_MASTER_ROOT . '/includes/layout_header.php';
    include __DIR__ . '/form.view.php';
    require FVD_MASTER_ROOT . '/includes/layout_footer.php';
    exit;
}

if ($action === 'reporte_conceptos' && $tid !== null && $aid !== null) {
    $row = $ctrl->find($tid, $aid);
    $fvdReportePorConcepto = [];
    $fvdReporteConceptosOk = false;
    $fvdReporteConceptoFiltro = null;
    $cParam = isset($_GET['concepto']) ? trim((string) $_GET['concepto']) : '';
    if ($cParam !== '' && array_key_exists($cParam, DeudaAsociacionController::ETIQUETAS_CONCEPTO)) {
        $fvdReporteConceptoFiltro = $cParam;
    }
    if ($row === null) {
        http_response_code(404);
        $fvd_page_title = 'No encontrado';
    } else {
        $fvd_page_title = $fvdReporteConceptoFiltro !== null
            ? ('Registros · ' . DeudaAsociacionController::ETIQUETAS_CONCEPTO[$fvdReporteConceptoFiltro])
            : 'Detalle por concepto';
        try {
            $fvdReportePorConcepto = $ctrl->detallePorConceptos($tid, $aid, $fvdReporteConceptoFiltro);
            $fvdReporteConceptosOk = true;
        } catch (Throwable $e) {
            $fvd_error = $e->getMessage();
            error_log('[deuda_asociacion] reporte_conceptos: ' . $fvd_error);
        }
    }
    require FVD_MASTER_ROOT . '/includes/layout_header.php';
    include __DIR__ . '/reporte_conceptos.view.php';
    require FVD_MASTER_ROOT . '/includes/layout_footer.php';
    exit;
}

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$result = $ctrl->paginateList($page, 15);

require FVD_MASTER_ROOT . '/includes/layout_header.php';
include __DIR__ . '/list.view.php';
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
