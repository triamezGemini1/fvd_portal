<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/admin/_init.php';
fvd_admin_require_roles();

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/AsociacionController.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = isset($_REQUEST['action']) ? trim((string) $_REQUEST['action']) : '';

$ctl = new AsociacionController();

try {
    if ($method === 'GET' && $action === 'list') {
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 25;
        $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        $estado = isset($_GET['estado']) ? trim((string) $_GET['estado']) : 'todas';
        $out = $ctl->listAction($page, $perPage, $q, $estado);
    } elseif ($method === 'GET' && $action === 'get') {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $out = $ctl->getAction($id);
    } elseif ($method === 'POST' && $action === 'save') {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
        $out = $ctl->saveAction($id, $_POST, $_FILES);
    } elseif ($method === 'POST' && $action === 'delete') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $out = $ctl->deleteAction($id);
    } elseif ($method === 'POST' && $action === 'toggle') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $out = $ctl->toggleEstatusAction($id);
    } else {
        fvd_api_json_out(['ok' => false, 'error' => 'Acción no reconocida.'], 400);
        exit;
    }

    $http = (int) ($out['code'] ?? ($out['ok'] ? 200 : 400));
    unset($out['code']);
    fvd_api_json_out($out, $http);
} catch (Throwable $e) {
    error_log('[gestion_asociaciones] ' . $e->getMessage());
    fvd_api_json_out(['ok' => false, 'error' => 'Error interno.'], 500);
}
