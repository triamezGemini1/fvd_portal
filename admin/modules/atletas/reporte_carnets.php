<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';
require_once FVD_PROJECT_ROOT . '/src/Services/QueryHelper.php';

use FvdPortal\Services\QueryHelper;

fvd_admin_require_roles();

$tipo = isset($_GET['tipo']) ? trim((string) $_GET['tipo']) : 'pendientes';
if ($tipo === 'emitidos') {
    $tipo = 'solicitados';
}
$carnetEq = $tipo === 'solicitados' ? 1 : 0;
$titulo = $tipo === 'solicitados'
    ? 'Carnets solicitados (atletas.carnet = 1)'
    : 'Elaboración de carnets — pendientes (atletas.carnet = 0)';

$rows = QueryHelper::selectAtletasAdminAll('', '', fvd_db(), $carnetEq);
$fvd_page_title = 'Reporte carnets';
$selfReport = admin_module_url('atletas/reporte_carnets.php');

require_once FVD_PROJECT_ROOT . '/fvdmasteradmin/includes/fvd_asociacion_helpers.php';

$repOmitAsocCol = false;
$repAsocNombreCorto = '';
$repAsocLogoUrl = null;
$aidHdr = AuthService::idAsociacion();
if ($aidHdr !== null && $aidHdr > 0) {
    $repOmitAsocCol = true;
    $stH = fvd_db()->prepare('SELECT nombre, logo FROM asociaciones WHERE id = :id LIMIT 1');
    $stH->execute([':id' => $aidHdr]);
    $rH = $stH->fetch(PDO::FETCH_ASSOC);
    if (is_array($rH)) {
        $repAsocNombreCorto = fvd_asoc_nombre_sin_prefijo((string) ($rH['nombre'] ?? ''));
        $appB = rtrim((string) env('APP_BASE_PATH', ''), '/');
        $repAsocLogoUrl = fvd_asociacion_logo_public_url($appB, FVD_PROJECT_ROOT, isset($rH['logo']) ? (string) $rH['logo'] : null);
    }
} else {
    $asocIds = [];
    foreach ($rows as $rw) {
        $ax = (int) ($rw['asociacion'] ?? 0);
        if ($ax > 0) {
            $asocIds[$ax] = true;
        }
    }
    if (count($asocIds) === 1) {
        $onlyId = 0;
        foreach (array_keys($asocIds) as $kAsoc) {
            $onlyId = (int) $kAsoc;
            break;
        }
        $repOmitAsocCol = true;
        $stH = fvd_db()->prepare('SELECT nombre, logo FROM asociaciones WHERE id = :id LIMIT 1');
        $stH->execute([':id' => $onlyId]);
        $rH = $stH->fetch(PDO::FETCH_ASSOC);
        if (is_array($rH)) {
            $repAsocNombreCorto = fvd_asoc_nombre_sin_prefijo((string) ($rH['nombre'] ?? ''));
            $appB = rtrim((string) env('APP_BASE_PATH', ''), '/');
            $repAsocLogoUrl = fvd_asociacion_logo_public_url($appB, FVD_PROJECT_ROOT, isset($rH['logo']) ? (string) $rH['logo'] : null);
        }
    }
}

require FVD_MASTER_ROOT . '/includes/layout_header.php';
?>
<div class="report-container" style="max-width:56rem">
    <?php if ($repAsocNombreCorto !== '' || $repAsocLogoUrl !== null): ?>
    <div class="fvd-rep-carnets-head" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin:0 0 14px;padding-bottom:12px;border-bottom:2px solid var(--fvd-amarillo)">
        <?php if ($repAsocLogoUrl !== null): ?>
            <img src="<?= htmlspecialchars($repAsocLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" style="max-height:52px;max-width:100px;width:auto;object-fit:contain">
        <?php endif; ?>
        <?php if ($repAsocNombreCorto !== ''): ?>
            <span style="font-size:clamp(1rem,2.5vw,1.25rem);font-weight:700;color:var(--fvd-amarillo);flex:1;text-align:center;min-width:min(100%,12rem)"><?= htmlspecialchars($repAsocNombreCorto, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <h1 class="fvd-atletas-title"><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></h1>
    <p style="font-size:.8125rem;color:var(--fvd-muted);margin:0 0 1rem">
        Listado según el marcador <code>atletas.carnet</code> (0 = pendiente de solicitud, 1 = solicitado). Total: <strong><?= count($rows) ?></strong>
    </p>
    <p class="no-print" style="margin:0 0 1rem;display:flex;flex-wrap:wrap;gap:8px">
        <a class="fvd-input" style="width:auto;padding:6px 12px;text-decoration:none;display:inline-flex;align-items:center" href="<?= htmlspecialchars($selfReport . '?tipo=pendientes', ENT_QUOTES, 'UTF-8') ?>">Pendientes (carnet=0)</a>
        <a class="fvd-input" style="width:auto;padding:6px 12px;text-decoration:none;display:inline-flex;align-items:center" href="<?= htmlspecialchars($selfReport . '?tipo=solicitados', ENT_QUOTES, 'UTF-8') ?>">Solicitados (carnet=1)</a>
        <a class="fvd-input" style="width:auto;padding:6px 12px;text-decoration:none;display:inline-flex;align-items:center" href="<?= htmlspecialchars(fvd_crud_self_url('atletas') . '?tab=ficha', ENT_QUOTES, 'UTF-8') ?>">← Atletas</a>
    </p>
    <div class="fvd-mod-table-wrap">
        <table class="fvd-mod-table tabla-atletas">
            <thead>
            <tr>
                <th>ID</th>
                <th>Cédula</th>
                <th>Nombre</th>
                <?php if (!$repOmitAsocCol): ?><th>Asociación</th><?php endif; ?>
                <th>Nº FVD</th>
                <th>carnet</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= (int) ($r['id'] ?? 0) ?></td>
                    <td><?= htmlspecialchars((string) ($r['cedula'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <?php if (!$repOmitAsocCol): ?>
                    <td><?= htmlspecialchars((string) ($r['asociacion_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <?php endif; ?>
                    <td><?= (int) ($r['numfvd'] ?? 0) ?></td>
                    <td><?= (int) ($r['carnet'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="<?= $repOmitAsocCol ? '5' : '6' ?>" style="padding:12px">Sin registros.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
