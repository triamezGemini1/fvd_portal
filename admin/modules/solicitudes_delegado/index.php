<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';
require_once FVD_PROJECT_ROOT . '/src/Services/DelegadoSolicitudService.php';

use FvdPortal\Services\DelegadoSolicitudService;

fvd_admin_require_roles([AuthService::ROLE_FVD_ADMIN]);

if (!AuthService::isSuperAdmin()) {
    http_response_code(403);
    echo 'Solo administrador general.';
    exit;
}

$selfUrl = fvd_crud_self_url('solicitudes_delegado');
$fvd_error = '';
$pdo = fvd_db();
DelegadoSolicitudService::ensureTable($pdo);

$tipoFiltro = isset($_GET['tipo']) ? trim((string) $_GET['tipo']) : '';
$soloTipo = null;
if ($tipoFiltro === 'traspaso') {
    $soloTipo = 'traspaso';
} elseif ($tipoFiltro === 'carnet' || $tipoFiltro === 'carnet_afiliacion') {
    $soloTipo = 'carnet_afiliacion';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string) ($_POST['_action'] ?? '');
    $sid = (int) ($_POST['id'] ?? 0);
    $uid = AuthService::userId();
    try {
        if ($act === 'aprobar' && $sid > 0) {
            DelegadoSolicitudService::aprobar($pdo, $sid, $uid);
        } elseif ($act === 'rechazar' && $sid > 0) {
            DelegadoSolicitudService::rechazar($pdo, $sid, $uid);
        }
        $rt = trim((string) ($_POST['return_tipo'] ?? ''));
        $loc = $selfUrl;
        if ($rt === 'traspaso') {
            $loc = $selfUrl . '?tipo=traspaso';
        } elseif ($rt === 'carnet' || $rt === 'carnet_afiliacion') {
            $loc = $selfUrl . '?tipo=carnet_afiliacion';
        }
        header('Location: ' . $loc);
        exit;
    } catch (Throwable $e) {
        $fvd_error = $e->getMessage();
        error_log('[solicitudes_delegado] ' . $fvd_error);
    }
}

$fvd_page_title = 'Solicitudes delegados';
if ($soloTipo === 'traspaso') {
    $fvd_page_title = 'Solicitudes de traspaso';
} elseif ($soloTipo === 'carnet_afiliacion') {
    $fvd_page_title = 'Solicitudes de carnet y afiliación';
}
$pendientes = DelegadoSolicitudService::listarPendientes($pdo, $soloTipo);

require FVD_MASTER_ROOT . '/includes/layout_header.php';
?>
<h1><?= htmlspecialchars($fvd_page_title, ENT_QUOTES, 'UTF-8') ?></h1>
<?php if ($soloTipo === 'traspaso'): ?>
<p style="font-size:0.8125rem;color:var(--fvd-muted);max-width:42rem">Solo traspasos de asociación pendientes de aprobación FVD.</p>
<?php elseif ($soloTipo === 'carnet_afiliacion'): ?>
<p style="font-size:0.8125rem;color:var(--fvd-muted);max-width:42rem">Carnets y afiliaciones enviados por delegados; al aprobar se ejecuta la misma lógica que en el panel FVD.</p>
<?php else: ?>
<p style="font-size:0.8125rem;color:var(--fvd-muted);max-width:42rem">Traspasos, carnets y afiliaciones enviados por delegados; al aprobar se ejecuta la misma lógica que en el panel FVD.</p>
<?php endif; ?>
<?php if ($fvd_error !== ''): ?><p class="fvd-mod-msg"><?= htmlspecialchars($fvd_error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

<div class="fvd-mod-table-wrap">
    <table class="fvd-mod-table fvd-mod-table--nowrap" style="font-size:0.8125rem">
        <thead>
        <tr>
            <th>ID</th>
            <th>Tipo</th>
            <th>Atleta</th>
            <th>Origen</th>
            <th>Destino</th>
            <th>Nota</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($pendientes as $p): ?>
            <tr>
                <td><?= (int) ($p['id'] ?? 0) ?></td>
                <td><?= htmlspecialchars((string) ($p['tipo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($p['atleta_nombre'] ?? '') . ' · ' . (string) ($p['atleta_cedula'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($p['asoc_origen_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($p['asoc_destino_nombre'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($p['nota'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td style="white-space:nowrap">
                    <form method="post" action="" style="display:inline">
                        <input type="hidden" name="_action" value="aprobar">
                        <input type="hidden" name="id" value="<?= (int) ($p['id'] ?? 0) ?>">
                        <input type="hidden" name="return_tipo" value="<?= htmlspecialchars($tipoFiltro, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="fvd-btn-primary" style="padding:4px 8px;font-size:0.75rem">Aprobar</button>
                    </form>
                    <form method="post" action="" style="display:inline;margin-left:4px">
                        <input type="hidden" name="_action" value="rechazar">
                        <input type="hidden" name="id" value="<?= (int) ($p['id'] ?? 0) ?>">
                        <input type="hidden" name="return_tipo" value="<?= htmlspecialchars($tipoFiltro, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="fvd-input" style="padding:4px 8px;font-size:0.75rem" onclick="return confirm('¿Rechazar esta solicitud?');">Rechazar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($pendientes === []): ?>
            <tr><td colspan="7" style="padding:12px">No hay solicitudes pendientes.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
