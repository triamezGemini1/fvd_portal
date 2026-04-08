<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';
require_once FVD_PROJECT_ROOT . '/src/Services/InvitacionService.php';
require_once FVD_PROJECT_ROOT . '/src/Services/QueryHelper.php';

use FvdPortal\Services\InvitacionService;
use FvdPortal\Services\QueryHelper;

fvd_admin_require_roles([
    AuthService::ROLE_FVD_ADMIN,
    AuthService::ROLE_ASO_ADMIN,
    AuthService::ROLE_DELEGADO_ASOC,
]);

$selfUrl = fvd_crud_self_url('invitaciones');
$fvd_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'invitar') {
    try {
        $tipo = (string) ($_POST['tipo'] ?? 'atleta');
        $doc = (string) ($_POST['documento'] ?? '');
        $email = isset($_POST['email_destino']) ? trim((string) $_POST['email_destino']) : '';
        $dias = isset($_POST['validez_dias']) ? (int) $_POST['validez_dias'] : 14;
        $memo = isset($_POST['titulo_memo']) ? trim((string) $_POST['titulo_memo']) : '';
        InvitacionService::crearInvitacion(
            fvd_db(),
            $tipo,
            $doc,
            $email !== '' ? $email : null,
            $dias,
            $memo !== '' ? $memo : null
        );
        header('Location: ' . $selfUrl . '?ok=1');
        exit;
    } catch (Throwable $e) {
        $fvd_error = $e->getMessage();
        error_log('[admin/invitaciones] ' . $fvd_error);
    }
}

$fvd_page_title = 'Invitaciones';
$pdo = fvd_db();
InvitacionService::ensureTable($pdo);

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$estadoFiltro = isset($_GET['estado']) ? trim((string) $_GET['estado']) : '';
if (!in_array($estadoFiltro, ['pendiente', 'aceptada', 'expirada', ''], true)) {
    $estadoFiltro = '';
}

$perPage = 8;
$filtros = InvitacionService::filtrosListado($estadoFiltro);
$paged = QueryHelper::selectPaginado('fvd_invitaciones', $filtros, $page, $perPage, $pdo);

$rows = [];
foreach ($paged['registros'] as $raw) {
    $rows[] = InvitacionService::filaVista($raw);
}

$result = [
    'total'    => $paged['total'],
    'page'     => $page,
    'per_page' => $perPage,
    'pages'    => $paged['paginas'],
    'rows'     => $rows,
];

$sn = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
$invitacionesSearchApiUrl = (str_contains($sn, '/fvdmasteradmin/modules/invitaciones/')
        || (str_contains($sn, '/modules/invitaciones/') && !str_contains($sn, '/admin/modules/')))
    ? fvd_master_module_url('invitaciones/search_api.php')
    : admin_module_url('invitaciones/search_api.php');

require FVD_MASTER_ROOT . '/includes/layout_header.php';
include __DIR__ . '/list.view.php';
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
