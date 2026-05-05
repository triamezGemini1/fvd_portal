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
        if (!function_exists('fvd_return_preserve_query_params')) {
            require_once FVD_PROJECT_ROOT . '/config/paths.php';
        }
        header('Location: ' . fvd_return_preserve_query_params($loc));
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
$pendientes = DelegadoSolicitudService::listarParaPanelAdministracion($pdo, $soloTipo, 500);

require FVD_MASTER_ROOT . '/includes/layout_header.php';
?>
<style>
.fvd-sol-est { font-weight: 800; font-size: 0.75rem; }
.fvd-sol-est--pendiente { color: #fcd34d; }
.fvd-sol-est--aprobada { color: #86efac; }
.fvd-sol-est--rechazada { color: #fca5a5; }
</style>
<h1><?= htmlspecialchars($fvd_page_title, ENT_QUOTES, 'UTF-8') ?></h1>
<?php if ($soloTipo === 'traspaso'): ?>
<p style="font-size:0.8125rem;color:var(--fvd-muted);max-width:42rem">Solo traspasos de asociación pendientes de aprobación FVD.</p>
<?php elseif ($soloTipo === 'carnet_afiliacion'): ?>
<p style="font-size:0.8125rem;color:var(--fvd-muted);max-width:42rem">Carnets y afiliaciones enviados por delegados; al aprobar se ejecuta la misma lógica que en el panel FVD.</p>
<?php else: ?>
<p style="font-size:0.8125rem;color:var(--fvd-muted);max-width:42rem">Traspasos, carnets y afiliaciones enviados por delegados; al aprobar se ejecuta la misma lógica que en el panel FVD. Listado con histórico (hasta 500 filas): cada fila muestra el estado actual (pendiente, aprobada o rechazada) y la fecha de resolución cuando aplica.</p>
<?php endif; ?>
<?php if ($fvd_error !== ''): ?><p class="fvd-mod-msg"><?= htmlspecialchars($fvd_error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

<div class="fvd-mod-table-wrap">
    <table class="fvd-mod-table fvd-mod-table--nowrap" style="font-size:0.8125rem">
        <thead>
        <tr>
            <th>ID</th>
            <th>Tipo</th>
            <th>Estado</th>
            <th>Atleta</th>
            <th>Origen</th>
            <th>Destino</th>
            <th>Nota</th>
            <th>Resuelto</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($pendientes as $p): ?>
            <?php
            $estSol = (string) ($p['estado'] ?? '');
            $esPend = $estSol === 'pendiente';
            $labEst = $estSol === 'aprobada' ? 'Aprobada' : ($estSol === 'rechazada' ? 'Rechazada' : ($estSol !== '' ? $estSol : '—'));
            ?>
            <tr>
                <td><?= (int) ($p['id'] ?? 0) ?></td>
                <td><?= htmlspecialchars((string) ($p['tipo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><span class="fvd-sol-est fvd-sol-est--<?= htmlspecialchars($estSol, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($labEst, ENT_QUOTES, 'UTF-8') ?></span></td>
                <td><?= htmlspecialchars((string) ($p['atleta_nombre'] ?? '') . ' · ' . (string) ($p['atleta_cedula'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($p['asoc_origen_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($p['asoc_destino_nombre'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($p['nota'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td style="white-space:nowrap;font-size:0.72rem"><?= $esPend ? '—' : htmlspecialchars((string) ($p['resuelto_en'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td style="white-space:nowrap">
                    <?php if ($esPend): ?>
                    <form method="post" action="" style="display:inline">
                        <input type="hidden" name="_action" value="aprobar">
                        <input type="hidden" name="id" value="<?= (int) ($p['id'] ?? 0) ?>">
                        <input type="hidden" name="return_tipo" value="<?= htmlspecialchars($tipoFiltro, ENT_QUOTES, 'UTF-8') ?>">
                        <?php if (function_exists('fvd_master_panel_render_context_hiddens')) {
                            fvd_master_panel_render_context_hiddens();
                        } ?>
                        <button type="submit" class="fvd-btn-primary" style="padding:4px 8px;font-size:0.75rem">Aprobar</button>
                    </form>
                    <form method="post" action="" style="display:inline;margin-left:4px">
                        <input type="hidden" name="_action" value="rechazar">
                        <input type="hidden" name="id" value="<?= (int) ($p['id'] ?? 0) ?>">
                        <input type="hidden" name="return_tipo" value="<?= htmlspecialchars($tipoFiltro, ENT_QUOTES, 'UTF-8') ?>">
                        <?php if (function_exists('fvd_master_panel_render_context_hiddens')) {
                            fvd_master_panel_render_context_hiddens();
                        } ?>
                        <button type="submit" class="fvd-input" style="padding:4px 8px;font-size:0.75rem" onclick="return confirm('¿Rechazar esta solicitud?');">Rechazar</button>
                    </form>
                    <?php else: ?>
                    <span style="font-size:0.72rem;color:var(--fvd-muted)">—</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($pendientes === []): ?>
            <tr><td colspan="9" style="padding:12px">No hay solicitudes registradas.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
