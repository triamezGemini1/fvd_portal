<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/_init.php';
fvd_module_require_roles();

require_once __DIR__ . '/Controller.php';
$ctrl = new RelacionPagoController();

$selfUrl = fvd_module_url('relacion_pago/index.php');
$fvdRpAppBase = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');
$fvdRpPanelUrl = AuthService::isSuperAdmin()
    ? $fvdRpAppBase . '/fvdmasteradmin/master_panel.php'
    : $fvdRpAppBase . '/fvdmasteradmin/index.php';
$fvd_error = '';

$rpRef = isset($_GET['ref']) ? trim((string) $_GET['ref']) : '';
$rpRid = isset($_GET['rid']) ? max(0, (int) $_GET['rid']) : 0;

if (($_GET['action'] ?? '') === 'deuda_resumen' && ($_GET['fmt'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    $tid = isset($_GET['tid']) ? (int) $_GET['tid'] : 0;
    $aid = isset($_GET['aid']) ? (int) $_GET['aid'] : 0;
    echo json_encode($ctrl->deudaResumenParaRecibo($tid, $aid), JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_GET['action'] ?? '') === 'bcv_euro' && ($_GET['fmt'] ?? '') === 'json') {
    require_once __DIR__ . '/BcvEuroFetcher.php';
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(BcvEuroFetcher::fetchOfficialEuroRate(), JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    header('Location: ' . fvd_return_preserve_query_params($selfUrl . '?msg=no_eliminar'));
    exit;
}

$fvd_form_repost = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'save') {
    try {
        $sid = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
        if ($sid !== null) {
            header('Location: ' . fvd_return_preserve_query_params($selfUrl . '?action=form&id=' . $sid . '&msg=no_edicion'));
            exit;
        }
        $ctrl->save($sid, $_POST);
        $retRef = trim((string) ($_POST['_retorno_ref'] ?? ''));
        $retRid = (int) ($_POST['_retorno_rid'] ?? 0);
        if ($retRef === 'rep_asoc' && $retRid > 0) {
            header('Location: ' . $fvdRpAppBase . '/fvdmasteradmin/asociacion_reporte_financiero.php?id=' . $retRid);
            exit;
        }
        $aidPost = (int) ($_POST['asociacion_id'] ?? 0);
        $loc = $selfUrl;
        $qs = [];
        if ($aidPost > 0) {
            $qs['aid'] = $aidPost;
        }
        $loc .= $qs !== [] ? ('?' . http_build_query($qs)) : '';
        header('Location: ' . fvd_return_preserve_query_params($loc));
        exit;
    } catch (Throwable $e) {
        $fvd_error = $e->getMessage();
        error_log('[relacion_pago] ' . $fvd_error);
        $fvd_form_repost = $_POST;
        $idErr = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
        $row = $idErr ? $ctrl->find($idErr) : null;
        if ($idErr !== null && $row === null) {
            header('Location: ' . fvd_return_preserve_query_params($selfUrl));
            exit;
        }
        $asociaciones = $ctrl->listAsociacionesForSelect();
        if ($row !== null) {
            $fvdTorneoRecibo = [
                'torneo_id' => (int) ($row['torneo_id'] ?? 0),
                'nombre' => $ctrl->torneoNombrePorId((int) ($row['torneo_id'] ?? 0)),
            ];
        } else {
            $fvdTorneoRecibo = $ctrl->torneoActivoParaRecibo();
        }
        $fvdDeudaInicial = null;
        if ($fvdTorneoRecibo !== null) {
            $aidInit = (int) ($_POST['asociacion_id'] ?? 0);
            if ($aidInit <= 0 && $row !== null) {
                $aidInit = (int) ($row['asociacion_id'] ?? 0);
            }
            if ($aidInit <= 0 && $asociaciones !== []) {
                $aidInit = (int) ($asociaciones[0]['id'] ?? 0);
            }
            if ($aidInit > 0) {
                $fvdDeudaInicial = $ctrl->deudaResumenParaRecibo((int) $fvdTorneoRecibo['torneo_id'], $aidInit);
            }
        }
        $fvd_page_title = 'Relación de pagos';
        $fvdRpRetornoRef = trim((string) ($_POST['_retorno_ref'] ?? ''));
        $fvdRpRetornoRid = (int) ($_POST['_retorno_rid'] ?? 0);
        if ($fvdRpRetornoRef !== 'rep_asoc' || $fvdRpRetornoRid <= 0) {
            $fvdRpRetornoRef = '';
            $fvdRpRetornoRid = 0;
        }
        $fvdReporteOrigenUrl = ($fvdRpRetornoRef === 'rep_asoc' && $fvdRpRetornoRid > 0)
            ? $fvdRpAppBase . '/fvdmasteradmin/asociacion_reporte_financiero.php?id=' . $fvdRpRetornoRid
            : null;
        require FVD_MASTER_ROOT . '/includes/layout_header.php';
        include __DIR__ . '/form.view.php';
        require FVD_MASTER_ROOT . '/includes/layout_footer.php';
        exit;
    }
}

$fvd_page_title = 'Relación de pagos';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

if ($action === 'form') {
    $row = $ctrl->find($id);
    $preAsoc = isset($_GET['asociacion_id']) ? max(0, (int) $_GET['asociacion_id']) : 0;
    if ($id === null && $row === null && $preAsoc > 0) {
        $puedePre = AuthService::isSuperAdmin()
            || (AuthService::idAsociacion() !== null && (int) AuthService::idAsociacion() === $preAsoc);
        if ($puedePre) {
            $row = ['asociacion_id' => $preAsoc];
        }
    }
    $asociaciones = $ctrl->listAsociacionesForSelect();
    if ($id !== null && $row === null) {
        $fvdTorneoRecibo = null;
    } elseif ($row !== null) {
        $fvdTorneoRecibo = [
            'torneo_id' => (int) ($row['torneo_id'] ?? 0),
            'nombre' => $ctrl->torneoNombrePorId((int) ($row['torneo_id'] ?? 0)),
        ];
    } else {
        $fvdTorneoRecibo = $ctrl->torneoActivoParaRecibo();
    }
    if ($id !== null && $row === null) {
        http_response_code(404);
        $fvd_page_title = 'No encontrado';
    }
    $fvdDeudaInicial = null;
    if ($fvdTorneoRecibo !== null) {
        $aidInit = $row !== null ? (int) ($row['asociacion_id'] ?? 0) : (int) (($asociaciones[0]['id'] ?? 0));
        if ($aidInit > 0) {
            $fvdDeudaInicial = $ctrl->deudaResumenParaRecibo((int) $fvdTorneoRecibo['torneo_id'], $aidInit);
        }
    }
    $fvdRpRetornoRef = ($rpRef === 'rep_asoc' && $rpRid > 0) ? 'rep_asoc' : '';
    $fvdRpRetornoRid = ($fvdRpRetornoRef !== '') ? $rpRid : 0;
    $fvdReporteOrigenUrl = ($fvdRpRetornoRef === 'rep_asoc' && $fvdRpRetornoRid > 0)
        ? $fvdRpAppBase . '/fvdmasteradmin/asociacion_reporte_financiero.php?id=' . $fvdRpRetornoRid
        : null;
    require FVD_MASTER_ROOT . '/includes/layout_header.php';
    include __DIR__ . '/form.view.php';
    require FVD_MASTER_ROOT . '/includes/layout_footer.php';
    exit;
}

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$filtroAid = isset($_GET['aid']) ? max(0, (int) $_GET['aid']) : 0;
$result = $ctrl->paginateList($page, 15, $filtroAid);

$fvdRpPreservar = [];
if ($filtroAid > 0) {
    $fvdRpPreservar['aid'] = $filtroAid;
}
if ($rpRef === 'rep_asoc' && $rpRid > 0) {
    $fvdRpPreservar['ref'] = 'rep_asoc';
    $fvdRpPreservar['rid'] = $rpRid;
}
$fvdRpPreservar = fvd_return_merge_get_params($fvdRpPreservar);
$fvdRpPreservarQs = $fvdRpPreservar === [] ? '' : http_build_query($fvdRpPreservar);
$fvdReporteOrigenUrl = ($rpRef === 'rep_asoc' && $rpRid > 0)
    ? $fvdRpAppBase . '/fvdmasteradmin/asociacion_reporte_financiero.php?id=' . $rpRid
    : null;
$fvdRpQuitarFiltroAidUrl = $selfUrl;
$qsSinAid = [];
if ($rpRef === 'rep_asoc' && $rpRid > 0) {
    $qsSinAid['ref'] = 'rep_asoc';
    $qsSinAid['rid'] = $rpRid;
}
$qsSinAid = fvd_return_merge_get_params($qsSinAid);
$fvdRpQuitarFiltroAidUrl .= $qsSinAid !== [] ? ('?' . http_build_query($qsSinAid)) : '';

require FVD_MASTER_ROOT . '/includes/layout_header.php';
$fvdFiltroAsociacionId = $filtroAid;
include __DIR__ . '/list.view.php';
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
