<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/_init.php';
fvd_module_require_roles();

require_once __DIR__ . '/Controller.php';
$ctrl = new RelacionPagoController();

$selfUrl = fvd_module_url('relacion_pago/index.php');
$fvd_error = '';

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
    header('Location: ' . $selfUrl . '?msg=no_eliminar');
    exit;
}

$fvd_form_repost = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'save') {
    try {
        $sid = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
        if ($sid !== null) {
            header('Location: ' . $selfUrl . '?action=form&id=' . $sid . '&msg=no_edicion');
            exit;
        }
        $ctrl->save($sid, $_POST);
        header('Location: ' . $selfUrl);
        exit;
    } catch (Throwable $e) {
        $fvd_error = $e->getMessage();
        error_log('[relacion_pago] ' . $fvd_error);
        $fvd_form_repost = $_POST;
        $idErr = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
        $row = $idErr ? $ctrl->find($idErr) : null;
        if ($idErr !== null && $row === null) {
            header('Location: ' . $selfUrl);
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
    require FVD_MASTER_ROOT . '/includes/layout_header.php';
    include __DIR__ . '/form.view.php';
    require FVD_MASTER_ROOT . '/includes/layout_footer.php';
    exit;
}

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$result = $ctrl->paginateList($page, 15);

require FVD_MASTER_ROOT . '/includes/layout_header.php';
include __DIR__ . '/list.view.php';
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
