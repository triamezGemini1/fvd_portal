<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';
fvd_admin_require_roles();

$svc = new FvdAdminService();
$selfUrl = fvd_crud_self_url('asociaciones');
$fvd_error = '';

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    $svc->asociacionesDelete((int) $_GET['id']);
    header('Location: ' . fvd_return_preserve_query_params($selfUrl));
    exit;
}

if (($_GET['action'] ?? '') === 'toggle_estatus' && isset($_GET['id']) && AuthService::role() === AuthService::ROLE_FVD_ADMIN) {
    $svc->asociacionesToggleEstatus((int) $_GET['id']);
    $redir = $selfUrl;
    $qs = [];
    if (isset($_GET['page']) && (int) $_GET['page'] > 1) {
        $qs['page'] = (int) $_GET['page'];
    }
    $qToggle = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
    if ($qToggle !== '') {
        $qs['q'] = $qToggle;
    }
    $estToggle = isset($_GET['estado']) ? trim((string) $_GET['estado']) : 'todas';
    if (in_array($estToggle, ['activas', 'inactivas'], true)) {
        $qs['estado'] = $estToggle;
    }
    if ($qs !== []) {
        $redir .= '?' . http_build_query($qs);
    }
    header('Location: ' . fvd_return_preserve_query_params($redir));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'save') {
    try {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
        $svc->asociacionesSave($id, $_POST, $_FILES);
        header('Location: ' . fvd_return_preserve_query_params($selfUrl));
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
$estadoRaw = isset($_GET['estado']) ? trim((string) $_GET['estado']) : 'todas';
$filtroEstatus = in_array($estadoRaw, ['activas', 'inactivas'], true) ? $estadoRaw : 'todas';
$result = $svc->asociacionesPaginateList($page, 15, $q, $filtroEstatus);

require FVD_MASTER_ROOT . '/includes/layout_header.php';
include __DIR__ . '/list.view.php';
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
