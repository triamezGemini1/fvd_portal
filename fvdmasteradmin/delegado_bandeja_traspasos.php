<?php

declare(strict_types=1);

require_once __DIR__ . '/services/AuthService.php';
AuthService::ensureSession();
AuthService::requireLogin();

if (!AuthService::isDelegadoAsociacion()) {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Acceso</title></head><body><p>No tiene permisos.</p></body></html>';
    exit;
}

$aid = AuthService::idAsociacion();
if ($aid === null || (int) $aid <= 0) {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Acceso</title></head><body><p>Su usuario no tiene asociación asignada.</p></body></html>';
    exit;
}

require_once __DIR__ . '/config/db.php';
require_once dirname(__DIR__) . '/src/Services/DelegadoSolicitudService.php';

$pdo = fvd_db();
\FvdPortal\Services\DelegadoSolicitudService::ensureTable($pdo);
$pendientes = \FvdPortal\Services\DelegadoSolicitudService::listarPendientesParaAsociacion($pdo, (int) $aid);

$fvd_sidebar_active = 'delegado_bandeja';
$fvd_page_title = 'Bandeja de traspasos';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="fvd-dash" style="max-width:56rem">
    <h1>Bandeja de traspasos</h1>
    <p style="font-size:0.8125rem;color:var(--fvd-muted);margin:0 0 1rem">Solicitudes pendientes donde su asociación es origen o destino (solo lectura).</p>
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
                </tr>
            <?php endforeach; ?>
            <?php if ($pendientes === []): ?>
                <tr><td colspan="6" style="padding:12px">No hay solicitudes pendientes para su asociación.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
require __DIR__ . '/includes/layout_footer.php';
