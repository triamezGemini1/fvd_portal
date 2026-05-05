<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';
require_once FVD_PROJECT_ROOT . '/src/Services/DelegadoSolicitudService.php';

use FvdPortal\Services\DelegadoSolicitudService;

fvd_admin_require_roles([AuthService::ROLE_ASO_ADMIN, AuthService::ROLE_DELEGADO_ASOC, AuthService::ROLE_FVD_ADMIN]);

$pdo = fvd_db();
DelegadoSolicitudService::ensureTable($pdo);

$asocId = (int) (AuthService::idAsociacion() ?? 0);
if (AuthService::isSuperAdmin()) {
    $portalA = (int) (AuthService::adminPortalDelegadoAsociacionId() ?? 0);
    if ($portalA > 0) {
        $asocId = $portalA;
    }
}

$clubOk = AuthService::isDelegadoAsociacion() || AuthService::role() === AuthService::ROLE_ASO_ADMIN;
$emulOk = AuthService::isSuperAdmin()
    && $asocId > 0
    && (int) (AuthService::adminPortalDelegadoAsociacionId() ?? 0) === $asocId;

if (!$clubOk && !$emulOk) {
    http_response_code(403);
    echo 'Acceso solo para administración de club o emulación de asociación desde el panel maestro.';
    exit;
}

if ($asocId <= 0) {
    http_response_code(400);
    echo 'No hay asociación en contexto para listar solicitudes.';
    exit;
}

$movimientos = DelegadoSolicitudService::listarMovimientosParaAsociacion($pdo, $asocId, 500);
$fvd_page_title = 'Mis solicitudes (seguimiento FVD)';

require FVD_MASTER_ROOT . '/includes/layout_header.php';
?>
<style>
.fvd-sol-est { font-weight: 800; font-size: 0.75rem; }
.fvd-sol-est--pendiente { color: #fcd34d; }
.fvd-sol-est--aprobada { color: #86efac; }
.fvd-sol-est--rechazada { color: #fca5a5; }
</style>
<h1><?= htmlspecialchars($fvd_page_title, ENT_QUOTES, 'UTF-8') ?></h1>
<p style="font-size:0.8125rem;color:var(--fvd-muted);max-width:46rem">
    Solicitudes enviadas por su asociación (carnet, afiliación, traspaso) y traspasos donde participa como destino.
    La aprobación o rechazo la realiza el administrador general FVD; aquí solo consulta el estado y la fecha de resolución.
</p>

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
            <th>Creado</th>
            <th>Resuelto</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($movimientos as $p): ?>
            <?php
            $estSol = (string) ($p['estado'] ?? '');
            $labEst = $estSol === 'aprobada' ? 'Aprobada' : ($estSol === 'rechazada' ? 'Rechazada' : ($estSol === 'pendiente' ? 'Pendiente' : ($estSol !== '' ? $estSol : '—')));
            ?>
            <tr>
                <td><?= (int) ($p['id'] ?? 0) ?></td>
                <td><?= htmlspecialchars((string) ($p['tipo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><span class="fvd-sol-est fvd-sol-est--<?= htmlspecialchars($estSol, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($labEst, ENT_QUOTES, 'UTF-8') ?></span></td>
                <td><?= htmlspecialchars((string) ($p['atleta_nombre'] ?? '') . ' · ' . (string) ($p['atleta_cedula'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($p['asoc_origen_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($p['asoc_destino_nombre'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($p['nota'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td style="white-space:nowrap;font-size:0.72rem"><?= htmlspecialchars((string) ($p['creado_en'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td style="white-space:nowrap;font-size:0.72rem"><?= $estSol === 'pendiente' ? '—' : htmlspecialchars((string) ($p['resuelto_en'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($movimientos === []): ?>
            <tr><td colspan="9" style="padding:12px">No hay solicitudes registradas para su asociación.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
