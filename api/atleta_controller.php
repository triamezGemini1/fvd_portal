<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/admin/_init.php';
fvd_admin_require_roles();

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/AtletaController.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = isset($_REQUEST['action']) ? trim((string) $_REQUEST['action']) : '';

$ctl = new AtletaController();

try {
    if ($method === 'GET' && $action === 'list') {
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        $idAsoc = 0;
        if (isset($_GET['id_asociacion'])) {
            $idAsoc = (int) $_GET['id_asociacion'];
        } elseif (isset($_GET['asociacion_id'])) {
            $idAsoc = (int) $_GET['asociacion_id'];
        }
        $tipo = isset($_GET['tipo']) ? trim((string) $_GET['tipo']) : 'normal';
        $out = $ctl->listAction($page, $q, $idAsoc, $tipo);
    } elseif ($method === 'GET' && $action === 'get') {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $out = $ctl->getAction($id);
    } elseif ($method === 'GET' && $action === 'search_referencial') {
        $ced = isset($_GET['cedula']) ? trim((string) $_GET['cedula']) : '';
        $out = $ctl->searchReferencialAction($ced);
    } elseif ($method === 'GET' && $action === 'persona_lookup') {
        $ced = isset($_GET['cedula']) ? trim((string) $_GET['cedula']) : '';
        $out = $ctl->personaLookupAction($ced);
    } elseif ($method === 'GET' && $action === 'siguiente_numfvd') {
        $out = $ctl->siguienteNumFvdAction();
    } elseif ($method === 'GET' && $action === 'cedula_disponible') {
        $ced = isset($_GET['cedula']) ? trim((string) $_GET['cedula']) : '';
        $out = $ctl->cedulaDisponibleAction($ced);
    } elseif ($method === 'GET' && $action === 'torneos_asociacion') {
        $aid = isset($_GET['asociacion_id']) ? (int) $_GET['asociacion_id'] : 0;
        $out = $ctl->torneosPorAsociacionAction($aid);
    } elseif ($method === 'POST' && $action === 'save') {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
        $out = $ctl->saveAction($id, $_POST, $_FILES);
    } elseif ($method === 'POST' && $action === 'toggle_activo') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $out = $ctl->toggleActivoAction($id);
    } else {
        fvd_api_json_out(['ok' => false, 'error' => 'Acción no reconocida.'], 400);
        exit;
    }

    $http = (int) ($out['code'] ?? ($out['ok'] ? 200 : 400));
    unset($out['code']);
    fvd_api_json_out($out, $http);
} catch (Throwable $e) {
    error_log('[atleta_controller] ' . $e->getMessage());
    fvd_api_json_out(['ok' => false, 'error' => 'Error interno.'], 500);
}
