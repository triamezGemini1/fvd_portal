<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';
require_once FVD_PROJECT_ROOT . '/src/Services/TraspasoService.php';

use FvdPortal\Services\TraspasoService;

fvd_admin_require_roles();

TraspasoService::ensureLogTable(fvd_db());

$legacy = FVD_PROJECT_ROOT . '/fvdmasteradmin/services/QueryHelper.php';
if (!class_exists('QueryHelper', false)) {
    require_once $legacy;
}

$params = [];
$scope = QueryHelper::asociacionScopeSql('a.asociacion', $params);
$sql = 'SELECT l.id, l.creado_en, l.atleta_id, l.asociacion_origen_id, l.asociacion_destino_id,
    a.cedula, a.nombre, a.traspaso AS atleta_traspaso_marcador,
    o.nombre AS asoc_origen, d.nombre AS asoc_destino
    FROM log_traspasos l
    INNER JOIN atletas a ON a.id = l.atleta_id
    LEFT JOIN asociaciones o ON o.id = l.asociacion_origen_id
    LEFT JOIN asociaciones d ON d.id = l.asociacion_destino_id
    WHERE 1=1 ' . $scope . '
    ORDER BY l.creado_en DESC
    LIMIT 500';

$st = fvd_db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$fvd_page_title = 'Reporte traspasos';
require FVD_MASTER_ROOT . '/includes/layout_header.php';
?>
<div class="report-container" style="max-width:64rem">
    <h1 class="fvd-atletas-title">Historial de traspasos</h1>
    <p style="font-size:.8125rem;color:var(--fvd-muted);margin:0 0 1rem">
        Movimientos registrados en <code>log_traspasos</code>; el atleta queda con <code>atletas.traspaso = 1</code>. Registros: <strong><?= count($rows) ?></strong> (máx. 500).
    </p>
    <p class="no-print" style="margin:0 0 1rem">
        <a class="fvd-input" style="width:auto;padding:6px 12px;text-decoration:none;display:inline-flex;align-items:center" href="<?= htmlspecialchars(fvd_crud_self_url('atletas') . '?tab=ficha', ENT_QUOTES, 'UTF-8') ?>">← Atletas</a>
    </p>
    <div class="fvd-mod-table-wrap">
        <table class="fvd-mod-table tabla-atletas">
            <thead>
            <tr>
                <th>Fecha</th>
                <th>Atleta</th>
                <th>Cédula</th>
                <th>Origen</th>
                <th>Destino</th>
                <th>traspaso</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($r['creado_en'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($r['cedula'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($r['asoc_origen'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($r['asoc_destino'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int) ($r['atleta_traspaso_marcador'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="6" style="padding:12px">Sin traspasos registrados en su ámbito.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
