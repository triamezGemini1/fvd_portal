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
require_once dirname(__DIR__) . '/src/Services/StatsService.php';

$pdo = fvd_db();
$rows = \FvdPortal\Services\StatsService::listadoAtletasActivosCarnetPendiente($pdo, (int) $aid, 800);

$fvd_sidebar_active = 'delegado_carnet';
$fvd_page_title = 'Estado de carnetización';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="fvd-dash" style="max-width:56rem">
    <h1>Estado de carnetización</h1>
    <p style="font-size:0.8125rem;color:var(--fvd-muted);margin:0 0 1rem">Atletas <strong>activos</strong> de su asociación que aún no tienen carnet solicitado (<code>carnet</code> vacío o 0).</p>
    <div class="fvd-mod-table-wrap">
        <table class="fvd-mod-table fvd-mod-table--nowrap" style="font-size:0.8125rem">
            <thead>
            <tr>
                <th>Nombre</th>
                <th>CI</th>
                <th>Nº FVD</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($r['cedula'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int) ($r['numfvd'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="3" style="padding:12px">No hay atletas activos pendientes de carnet en su asociación.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
require __DIR__ . '/includes/layout_footer.php';
