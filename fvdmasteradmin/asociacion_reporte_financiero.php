<?php

declare(strict_types=1);

/**
 * Reporte financiero por asociación: resumen de indicadores/costos, pagos y listados por métrica.
 * Solo administración general FVD (super admin).
 */

require_once __DIR__ . '/services/AuthService.php';
AuthService::ensureSession();
AuthService::requireLogin();

if (!AuthService::isSuperAdmin()) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Acceso denegado</title></head><body><p>Solo el administrador general FVD puede ver este informe.</p></body></html>';
    exit;
}

$projRoot = dirname(__DIR__);
require_once $projRoot . '/config/paths.php';
require_once __DIR__ . '/config/db.php';
require_once $projRoot . '/src/Services/StatsService.php';
require_once $projRoot . '/src/Services/IndicadoresTablaDefs.php';
require_once __DIR__ . '/includes/fvd_asociacion_helpers.php';

use FvdPortal\Services\IndicadoresTablaDefs;
use FvdPortal\Services\StatsService;

$aid = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$detalleRaw = isset($_GET['detalle']) ? (string) $_GET['detalle'] : '';
$metricasPermitidas = ['afiliacion', 'anualidad', 'carnet', 'traspaso', 'inscripcion'];
$detalle = \in_array($detalleRaw, $metricasPermitidas, true) ? $detalleRaw : '';

$pdo = fvd_db();
$bundle = StatsService::dashboardDetalleAsociacion($pdo, $aid, false);
if ($bundle === null || ($bundle['asociacion'] ?? null) === null) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>No encontrado</title></head><body><p>Asociación no encontrada.</p></body></html>';
    exit;
}

$asoc = $bundle['asociacion'];
$pagos = \is_array($bundle['pagos'] ?? null) ? $bundle['pagos'] : [];

$ic = StatsService::indicadoresServicioConCostosEstimados($pdo);
$filaResumen = null;
foreach ($ic['por_asociacion'] ?? [] as $row) {
    if ((int) ($row['asociacion_id'] ?? 0) === $aid) {
        $filaResumen = $row;
        break;
    }
}

$tarifa = $ic['tarifa'] ?? null;
$cols = IndicadoresTablaDefs::columnasMetricasContable();
$countsResumen = $filaResumen !== null
    ? IndicadoresTablaDefs::valoresMetricasInt($filaResumen, 'reporte financiero asoc ' . $aid, $cols)
    : IndicadoresTablaDefs::valoresMetricasInt([
        'afiliacion'    => 0,
        'anualidad'     => 0,
        'carnet'        => 0,
        'traspaso'      => 0,
        'inscripcion'   => 0,
    ], 'reporte financiero vacío', $cols);
$montoTotalEst = $filaResumen !== null ? (float) ($filaResumen['monto_total'] ?? 0) : 0.0;
$montosPorRenglon = $filaResumen !== null && isset($filaResumen['montos']) && \is_array($filaResumen['montos'])
    ? $filaResumen['montos']
    : ['afiliacion' => 0.0, 'anualidad' => 0.0, 'carnet' => 0.0, 'traspaso' => 0.0, 'inscripcion' => 0.0];

$rowsDetalle = $detalle !== ''
    ? StatsService::listadoAtletasPorAsociacionMetrica($pdo, $aid, $detalle, 800, false)
    : [];

$appBase = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');
if ($appBase !== '' && preg_match('#/fvdmasteradmin$#i', $appBase)) {
    $appBase = rtrim((string) preg_replace('#/fvdmasteradmin$#i', '', $appBase), '/');
}
if ($appBase === '') {
    $sn = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $pos = strpos($sn, '/fvdmasteradmin/');
    if ($pos > 0) {
        $appBase = rtrim(substr($sn, 0, $pos), '/');
    }
}

$logoUrl = fvd_asociacion_logo_public_url($appBase, $projRoot, isset($asoc['logo']) ? (string) $asoc['logo'] : null);
$asocNombre = trim((string) ($asoc['nombre'] ?? ''));
if ($asocNombre === '') {
    $asocNombre = 'Asociación #' . $aid;
}

$selfUrl = $appBase . '/fvdmasteradmin/asociacion_reporte_financiero.php';
$urlPanel = $appBase . '/fvdmasteradmin/index.php';
$urlPagosMod = '';
$urlPagoNuevo = '';
if (function_exists('fvd_master_module_url') && $aid > 0) {
    $urlPagosMod = fvd_master_module_url('relacion_pago/index.php?aid=' . $aid);
    $urlPagoNuevo = fvd_master_module_url('relacion_pago/index.php?action=form&asociacion_id=' . $aid);
}
$urlEditarAsoc = function_exists('admin_module_url') ? admin_module_url('asociaciones/index.php?action=form&id=' . $aid) : '';

$fmtN = static function (float $v): string {
    return \function_exists('fvd_format_contable') ? fvd_format_contable($v) : number_format($v, 2, ',', '.');
};

$fechaTar = '';
if (\is_array($tarifa) && isset($tarifa['fecha'])) {
    $fechaTar = substr((string) $tarifa['fecha'], 0, 10);
}

$fvd_page_title = 'Reporte financiero — ' . $asocNombre;
$fvd_required_roles = [AuthService::ROLE_FVD_ADMIN];
require __DIR__ . '/includes/layout_header.php';

$detalleLabel = '';
if ($detalle !== '') {
    foreach ($cols as $c) {
        if (($c['key'] ?? '') === $detalle) {
            $detalleLabel = (string) ($c['label'] ?? $detalle);
            break;
        }
    }
}

?>
<div class="fvd-dash" style="max-width:56rem">
    <p style="margin:0 0 1rem">
        <a class="fvd-btn fvd-btn--secondary" href="<?= htmlspecialchars($urlPanel, ENT_QUOTES, 'UTF-8') ?>" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none">
            ← Volver al panel general
        </a>
    </p>

    <header style="display:flex;flex-wrap:wrap;align-items:center;gap:14px;margin-bottom:1.25rem;padding-bottom:1rem;border-bottom:1px solid var(--fvd-border, rgba(255,255,255,0.12))">
        <?php if ($logoUrl !== null && $logoUrl !== ''): ?>
            <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" width="72" height="72" style="object-fit:contain;border-radius:8px;background:#fff;padding:4px" loading="lazy">
        <?php else: ?>
            <div style="width:72px;height:72px;border-radius:8px;background:rgba(255,255,255,0.08);display:flex;align-items:center;justify-content:center;font-size:.7rem;color:var(--fvd-muted,#94a3b8)">Sin logo</div>
        <?php endif; ?>
        <div>
            <p style="margin:0;font-size:1.05rem;font-weight:600"><?= htmlspecialchars($asocNombre, ENT_QUOTES, 'UTF-8') ?></p>
            <h1 style="margin:4px 0 0;font-size:1.15rem;font-weight:700">Reporte financiero</h1>
            <p style="margin:6px 0 0;font-size:.75rem;color:var(--fvd-muted,#94a3b8)">ID asociación <?= (int) $aid ?><?php if ($fechaTar !== ''): ?> · Tarifa costos (<?= htmlspecialchars($fechaTar, ENT_QUOTES, 'UTF-8') ?>)<?php endif; ?></p>
            <?php if ($urlEditarAsoc !== ''): ?>
                <p style="margin:8px 0 0;font-size:.75rem"><a href="<?= htmlspecialchars($urlEditarAsoc, ENT_QUOTES, 'UTF-8') ?>" style="color:var(--fvd-amarillo,#facc15)">Editar ficha de la asociación (CRUD)</a></p>
            <?php endif; ?>
        </div>
    </header>

    <section aria-label="Resumen de movimiento" style="margin-bottom:1.25rem;padding:12px 14px;border-radius:10px;border:1px solid var(--fvd-border, rgba(255,255,255,0.12));background:rgba(255,255,255,0.04)">
        <h2 style="margin:0 0 10px;font-size:.95rem">Resumen de servicios y costo estimado</h2>
        <p style="margin:0 0 12px;font-size:.72rem;color:var(--fvd-muted,#94a3b8);line-height:1.45">
            Todos los datos son <strong>solo de atletas de esta asociación</strong> (<code>atletas.asociacion</code> = <?= (int) $aid ?>).
            Cada métrica cuenta registros con la bandera correspondiente en <strong>1</strong>, de forma independiente.
            Los importes son <strong>estimaciones</strong> (conteos × última tarifa en <code>costos</code>) y <strong>no generan deuda</strong> ni movimiento en el módulo contable de deudas.
            <?php if ($tarifa === null): ?><strong style="color:#f87171">No hay tarifa en <code>costos</code>; solo cantidades.</strong><?php endif; ?>
        </p>
        <div style="overflow-x:auto">
            <table class="fvd-mod-table" style="font-size:.8rem">
                <thead>
                <tr>
                    <?php foreach ($cols as $col): ?>
                        <th scope="col" style="text-align:right" title="<?= htmlspecialchars((string) ($col['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($col['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></th>
                    <?php endforeach; ?>
                    <th scope="col" style="text-align:right">Total est.</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <?php foreach ($cols as $col): ?>
                        <?php $k = (string) ($col['key'] ?? ''); ?>
                        <td style="text-align:right"><?= (int) ($countsResumen[$k] ?? 0) ?></td>
                    <?php endforeach; ?>
                    <td style="text-align:right;font-weight:600"><?= htmlspecialchars($fmtN($montoTotalEst), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section aria-label="Detalle por métrica" style="margin-bottom:1.25rem">
        <h2 style="margin:0 0 10px;font-size:.95rem">Detalle por renglón</h2>
        <p style="margin:0 0 10px;font-size:.72rem;color:var(--fvd-muted,#94a3b8)">Pulse un renglón para ver el listado de <strong>atletas de esta asociación</strong>; al elegir otro, se sustituye el listado.</p>
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px">
            <?php foreach ($cols as $col):
                $k = (string) ($col['key'] ?? '');
                $active = $detalle === $k;
                $cnt = (int) ($countsResumen[$k] ?? 0);
                $mReng = (float) ($montosPorRenglon[$k] ?? 0);
                $qs = http_build_query(['id' => $aid, 'detalle' => $k]);
                $href = htmlspecialchars($selfUrl . '?' . $qs, ENT_QUOTES, 'UTF-8');
                $bg = $active ? 'rgba(250,204,21,0.25)' : 'rgba(255,255,255,0.08)';
                $bd = $active ? '1px solid #facc15' : '1px solid rgba(255,255,255,0.15)';
                ?>
                <a href="<?= $href ?>" class="fvd-input" style="display:inline-block;padding:8px 12px;border-radius:999px;font-size:.75rem;text-decoration:none;color:inherit;border:<?= $bd ?>;background:<?= $bg ?>;white-space:nowrap"
                   aria-current="<?= $active ? 'true' : 'false' ?>">
                    <strong><?= htmlspecialchars((string) ($col['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                    <span style="opacity:.85"> · <?= $cnt ?> · <?= htmlspecialchars($fmtN($mReng), ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($detalle !== ''): ?>
            <div style="padding:12px 14px;border-radius:10px;border:1px solid var(--fvd-border, rgba(255,255,255,0.12));background:rgba(0,0,0,0.15)">
                <h3 style="margin:0 0 8px;font-size:.88rem"><?= htmlspecialchars($detalleLabel !== '' ? $detalleLabel : $detalle, ENT_QUOTES, 'UTF-8') ?></h3>
                <?php if ($rowsDetalle === []): ?>
                    <p style="margin:0;font-size:.8rem;opacity:.9">No hay atletas de esta asociación en este renglón.</p>
                <?php else: ?>
                    <p style="margin:0 0 8px;font-size:.72rem;color:var(--fvd-muted,#94a3b8)">Mostrando hasta <?= \count($rowsDetalle) ?> filas.</p>
                    <div style="overflow-x:auto">
                        <table class="fvd-mod-table" style="font-size:.76rem">
                            <thead>
                            <tr>
                                <th scope="col">Nº FVD</th>
                                <th scope="col">Cédula</th>
                                <th scope="col">Nombre</th>
                                <th scope="col" style="text-align:right">Torneo</th>
                                <th scope="col"></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rowsDetalle as $r):
                                $rid = (int) ($r['id'] ?? 0);
                                $urlAt = function_exists('admin_module_url') ? admin_module_url('atletas/index.php?action=form&id=' . $rid) : '';
                                ?>
                                <tr>
                                    <td><?= (int) ($r['numfvd'] ?? 0) ?></td>
                                    <td><?= htmlspecialchars((string) ($r['cedula'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td style="text-align:right"><?= (int) ($r['torneo_id'] ?? 0) ?></td>
                                    <td style="white-space:nowrap">
                                        <?php if ($urlAt !== ''): ?>
                                            <a href="<?= htmlspecialchars($urlAt, ENT_QUOTES, 'UTF-8') ?>" style="color:var(--fvd-amarillo,#facc15)">Expediente</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p style="margin:0;font-size:.8rem;opacity:.85">Seleccione un renglón arriba para cargar el detalle.</p>
        <?php endif; ?>
    </section>

    <section aria-label="Pagos" style="margin-bottom:1.5rem;padding:12px 14px;border-radius:10px;border:1px solid var(--fvd-border, rgba(255,255,255,0.12));background:rgba(255,255,255,0.04)">
        <h2 style="margin:0 0 10px;font-size:.95rem">Pagos</h2>
        <p style="margin:0 0 10px;font-size:.72rem;color:var(--fvd-muted,#94a3b8);line-height:1.45">
            Todo lo mostrado en este apartado corresponde <strong>exclusivamente</strong> a esta asociación activa en la consulta
            (<strong><?= htmlspecialchars($asocNombre, ENT_QUOTES, 'UTF-8') ?></strong>, <code>asociacion_id</code> = <?= (int) $aid ?>):
            filas de <code>relacion_pagos</code> con ese mismo <code>asociacion_id</code>. Los enlaces al módulo abren el listado o el alta con esa asociación ya acotada.
        </p>
        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:12px">
            <?php if ($urlPagosMod !== ''): ?>
                <a class="fvd-btn fvd-btn--secondary" href="<?= htmlspecialchars($urlPagosMod, ENT_QUOTES, 'UTF-8') ?>" style="text-decoration:none">Ver pagos registrados</a>
            <?php endif; ?>
            <?php if ($urlPagoNuevo !== ''): ?>
                <a class="fvd-btn fvd-btn--primary" href="<?= htmlspecialchars($urlPagoNuevo, ENT_QUOTES, 'UTF-8') ?>" style="text-decoration:none">Registrar pago</a>
            <?php endif; ?>
        </div>
        <h3 style="margin:0 0 6px;font-size:.82rem">Pagos recientes (esta asociación)</h3>
        <?php if ($pagos === []): ?>
            <p style="margin:0;font-size:.78rem;opacity:.85">Sin pagos registrados para esta asociación.</p>
        <?php else: ?>
            <div style="overflow-x:auto">
                <table class="fvd-mod-table" style="font-size:.74rem">
                    <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Torneo</th>
                        <th style="text-align:right">EUR</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pagos as $p):
                        $pid = (int) ($p['id'] ?? 0);
                        $urlRec = function_exists('fvd_master_module_url')
                            ? fvd_master_module_url('relacion_pago/index.php?action=form&id=' . $pid)
                            : '';
                        ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($p['fecha'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($p['torneo_nombre'] ?? ('ID ' . (int) ($p['torneo_id'] ?? 0))), ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="text-align:right"><?= htmlspecialchars($fmtN((float) ($p['monto_dolares'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="white-space:nowrap">
                                <?php if ($urlRec !== ''): ?>
                                    <a href="<?= htmlspecialchars($urlRec, ENT_QUOTES, 'UTF-8') ?>" style="color:var(--fvd-amarillo,#facc15)">Ver recibo</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php
require __DIR__ . '/includes/layout_footer.php';
