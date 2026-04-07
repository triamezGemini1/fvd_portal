<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/_init.php';
fvd_module_require_roles();

require_once __DIR__ . '/Controller.php';
$ctrl = new CostosController();

$selfUrl = fvd_module_url('costos/index.php');
$fvd_error = '';

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    $ctrl->delete((int) $_GET['id']);
    header('Location: ' . $selfUrl);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'save') {
    try {
        $sid = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
        $ctrl->save($sid, $_POST);
        header('Location: ' . $selfUrl);
        exit;
    } catch (Throwable $e) {
        $fvd_error = $e->getMessage();
        error_log('[costos] ' . $fvd_error);
    }
}

$fvd_page_title = 'Costos (tarifas)';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

if ($action === 'form') {
    if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
        http_response_code(403);
        exit('Solo el administrador FVD puede editar tarifas.');
    }
    $row = $ctrl->find($id);
    if ($id !== null && $row === null) {
        http_response_code(404);
        $fvd_page_title = 'No encontrado';
    }
    require FVD_MASTER_ROOT . '/includes/layout_header.php';
    include __DIR__ . '/form.view.php';
    require FVD_MASTER_ROOT . '/includes/layout_footer.php';
    exit;
}

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$result = $ctrl->paginateList($page, 20);

require FVD_MASTER_ROOT . '/includes/layout_header.php';
include __DIR__ . '/list.view.php';
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
