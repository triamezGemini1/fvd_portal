<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';
fvd_admin_require_roles();

$svc = new FvdAdminService();
$selfUrl = fvd_crud_self_url('asociaciones');
$fvd_error = '';

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    $svc->asociacionesDelete((int) $_GET['id']);
    header('Location: ' . $selfUrl);
    exit;
}

if (($_GET['action'] ?? '') === 'toggle_estatus' && isset($_GET['id']) && AuthService::role() === AuthService::ROLE_FVD_ADMIN) {
    $svc->asociacionesToggleEstatus((int) $_GET['id']);
    header('Location: ' . $selfUrl);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'save') {
    try {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
        $svc->asociacionesSave($id, $_POST, $_FILES);
        header('Location: ' . $selfUrl);
        exit;
    } catch (Throwable $e) {
        $fvd_error = $e->getMessage();
        error_log('[admin/asociaciones] ' . $fvd_error);
    }
}

$fvd_page_title = 'Asociaciones';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

if ($action === 'form') {
    $row = $svc->asociacionesFind($id);
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
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$result = $svc->asociacionesPaginateList($page, 15, $q);

require FVD_MASTER_ROOT . '/includes/layout_header.php';
include __DIR__ . '/list.view.php';
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
