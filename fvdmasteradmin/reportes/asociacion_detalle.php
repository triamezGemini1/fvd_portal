<?php

declare(strict_types=1);

/**
 * Detalle por torneo + asociación: nominal (afiliados, inscritos, carnets),
 * deuda y saldo (`deuda_asociaciones` vs `relacion_pagos`), listado de pagos
 * y proceso de inscripción (cohorte `inscripcion_torneo` o `atletas`).
 * Embebido en Panel Maestro: ?embedded=1
 */

require_once dirname(__DIR__) . '/services/AuthService.php';
AuthService::ensureSession();
AuthService::requireLogin();
AuthService::requireRoles([AuthService::ROLE_FVD_ADMIN]);

$fvdRoot = dirname(__DIR__);
$projRoot = dirname($fvdRoot);
if (!function_exists('url')) {
    require_once $projRoot . '/config/paths.php';
}

require_once $projRoot . '/fvdmasteradmin/config/db.php';
require_once $projRoot . '/src/Services/StatsService.php';
require_once $projRoot . '/src/Services/FvdAdminService.php';

$pdo = fvd_db();
$embedded = (isset($_GET['embedded']) && (string) $_GET['embedded'] === '1')
    || (isset($_GET['fvd_master_embed']) && (string) $_GET['fvd_master_embed'] === '1');

$asociacionId = max(0, (int) ($_GET['asociacion_id'] ?? 0));
$torneoId = max(0, (int) ($_GET['torneo_id'] ?? 0));
$tab = trim((string) ($_GET['tab'] ?? 'afiliados'));
$allowedTabs = [
    'afiliados' => true, 'inscritos' => true, 'carnets' => true,
    'deuda' => true, 'pagos' => true, 'inscripciones' => true,
];
if (!isset($allowedTabs[$tab])) {
    $tab = 'afiliados';
}

$usaEur = \FvdPortal\Services\StatsService::deudaAsociacionesUsaEur($pdo);
$monedaEt = $usaEur ? 'EUR' : 'Bs';

$nombreAsoc = '';
$nombreTorneo = '';
$fvdDetalleBloqueoMsg = '';
if ($asociacionId > 0) {
    $st = $pdo->prepare('SELECT nombre, estatus FROM asociaciones WHERE id = :id LIMIT 1');
    $st->execute([':id' => $asociacionId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (is_array($r)) {
        $nombreAsoc = trim((string) ($r['nombre'] ?? ''));
        if (!FvdAdminService::asociacionEstatusEsActiva($r)) {
            $fvdDetalleBloqueoMsg = 'Esta asociación no está activa. El consolidado financiero solo permite consultar clubes activos.';
        }
    }
}
if ($torneoId > 0) {
    $st = $pdo->prepare('SELECT nombre FROM torneosact WHERE torneo = :t LIMIT 1');
    $st->execute([':t' => $torneoId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (is_array($r)) {
        $nombreTorneo = trim((string) ($r['nombre'] ?? ''));
    }
}

$rows = [];
$resumenDeuda = null;
$pagosRows = [];
$procInscRows = [];
if ($asociacionId > 0 && $torneoId > 0 && $fvdDetalleBloqueoMsg === '') {
    if ($tab === 'deuda') {
        $resumenDeuda = \FvdPortal\Services\StatsService::resumenDeudaContableTorneoAsociacion($pdo, $torneoId, $asociacionId);
    } elseif ($tab === 'pagos') {
        $pagosRows = \FvdPortal\Services\StatsService::listPagosTorneoAsociacion($pdo, $torneoId, $asociacionId);
        $resumenDeuda = \FvdPortal\Services\StatsService::resumenDeudaContableTorneoAsociacion($pdo, $torneoId, $asociacionId);
    } elseif ($tab === 'inscripciones') {
        $procInscRows = \FvdPortal\Services\StatsService::procesoInscripcionFilasTorneoAsociacion($pdo, $torneoId, $asociacionId);
    } else {
        $rows = \FvdPortal\Services\StatsService::detalleNominalTorneoAsociacion($pdo, $torneoId, $asociacionId, $tab);
    }
}

$modDeudaUrl = url('fvdmasteradmin/modules/deuda_asociacion/index.php?action=form&tid=' . $torneoId . '&aid=' . $asociacionId);
$modPagosUrl = url('fvdmasteradmin/modules/relacion_pago/index.php?asociacion_id=' . $asociacionId);
$fvdSiNo = static function (int $v): string {
    return $v === 1 ? 'Sí' : ($v === 2 ? 'Sí*' : 'No');
};

$fmt = static function (float $v): string {
    return number_format($v, 2, ',', '.');
};

$fvdUiCss = url('assets/css/fvd-ui-portal.css');
$selfUrl = url('fvdmasteradmin/reportes/asociacion_detalle.php');
$consolidadoUrl = url('fvdmasteradmin/reportes/consolidado_finanzas.php');

function fvd_det_embed_qs(): array
{
    $q = [];
    if (isset($_GET['embedded'])) {
        $q['embedded'] = (string) $_GET['embedded'];
    }
    if (isset($_GET['fvd_master_embed'])) {
        $q['fvd_master_embed'] = (string) $_GET['fvd_master_embed'];
    }

    return $q;
}

$embedQs = fvd_det_embed_qs();
$tabUrl = static function (string $t) use ($selfUrl, $asociacionId, $torneoId, $embedQs): string {
    $q = array_merge($embedQs, [
        'asociacion_id' => $asociacionId,
        'torneo_id'     => $torneoId,
        'tab'           => $t,
    ]);

    return $selfUrl . '?' . http_build_query($q);
};

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es" class="<?= $embedded ? 'h-full' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle financiero — FVD</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($fvdUiCss, ENT_QUOTES, 'UTF-8') ?>">
    <style>
        .fvd-det-wrap { margin: 0; padding: 0; width: 100%; max-width: none; box-sizing: border-box; font-family: Inter, system-ui, sans-serif; background: #f8fafc; }
        .fvd-det-wrap.embedded { min-height: 100%; padding: 0.35rem 0.25rem 1rem; }
        .fvd-det-wrap:not(.embedded) { padding: 1rem; }
        .fvd-det-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 0.75rem; margin: 0 0 0.75rem; }
        .fvd-det-head h1 { margin: 0; font-size: 1rem; font-weight: 800; color: #0f172a; }
        .fvd-det-meta { font-size: 0.75rem; color: #64748b; line-height: 1.4; }
        .fvd-det-tabs { display: flex; flex-wrap: wrap; gap: 0.35rem; margin: 0 0 0.75rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.35rem; }
        .fvd-det-tabs a {
            display: inline-flex; align-items: center; padding: 0.35rem 0.65rem; font-size: 0.75rem; font-weight: 700;
            border-radius: 0.375rem; text-decoration: none; color: #475569; border: 1px solid transparent;
        }
        .fvd-det-tabs a:hover { background: #f1f5f9; color: #0f172a; }
        .fvd-det-tabs a.is-active { background: #1e293b; color: #f8fafc; border-color: #1e293b; }
        .fvd-det-table-wrap { width: 100%; overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 0.5rem; background: #fff; }
        .fvd-det-table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
        .fvd-det-table th, .fvd-det-table td { padding: 0.4rem 0.5rem; text-align: left; border-bottom: 1px solid #f1f5f9; }
        .fvd-det-table th { background: #f8fafc; font-weight: 700; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.03em; color: #475569; }
        .fvd-det-table td.num { text-align: right; white-space: nowrap; }
        .fvd-det-back { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; font-weight: 700; color: #1d4ed8; text-decoration: none; margin-bottom: 0.5rem; }
        .fvd-det-back:hover { text-decoration: underline; }
    </style>
</head>
<body class="<?= $embedded ? 'is-embedded m-0' : '' ?>" style="<?= $embedded ? 'margin:0;background:#f8fafc' : '' ?>">
<script>
(function () {
    if (window.location.search.includes('embedded=1')) {
        document.documentElement.classList.add('is-embedded-view');
        document.body.classList.add('is-embedded');
    }
})();
</script>
<div class="fvd-det-wrap<?= $embedded ? ' embedded' : '' ?>">
    <a class="fvd-det-back" href="<?= htmlspecialchars($consolidadoUrl . (count($embedQs) ? '?' . http_build_query($embedQs) : ''), ENT_QUOTES, 'UTF-8') ?>">← Volver al consolidado</a>

    <div class="fvd-det-head">
        <div>
            <h1>Relación detallada</h1>
            <p class="fvd-det-meta">
                <strong><?= htmlspecialchars($nombreAsoc !== '' ? $nombreAsoc : 'Asociación #' . $asociacionId, ENT_QUOTES, 'UTF-8') ?></strong>
                · Torneo: <strong><?= htmlspecialchars($nombreTorneo !== '' ? $nombreTorneo : '#' . $torneoId, ENT_QUOTES, 'UTF-8') ?></strong>
                <?php if (in_array($tab, ['afiliados', 'inscritos', 'carnets'], true)): ?>
                · Montos unitarios según última fila de <code>costos</code> · Unidad: <?= htmlspecialchars($monedaEt, ENT_QUOTES, 'UTF-8') ?>.
                <?php else: ?>
                · Datos desde <code>deuda_asociaciones</code>, <code>relacion_pagos</code> o proceso de inscripción según la pestaña.
                <?php endif; ?>
            </p>
        </div>
    </div>

    <nav class="fvd-det-tabs" aria-label="Segmento">
        <a href="<?= htmlspecialchars($tabUrl('afiliados'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $tab === 'afiliados' ? 'is-active' : '' ?>">Afiliados</a>
        <a href="<?= htmlspecialchars($tabUrl('inscritos'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $tab === 'inscritos' ? 'is-active' : '' ?>">Inscritos</a>
        <a href="<?= htmlspecialchars($tabUrl('carnets'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $tab === 'carnets' ? 'is-active' : '' ?>">Carnets</a>
        <a href="<?= htmlspecialchars($tabUrl('deuda'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $tab === 'deuda' ? 'is-active' : '' ?>">Deuda y saldo</a>
        <a href="<?= htmlspecialchars($tabUrl('pagos'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $tab === 'pagos' ? 'is-active' : '' ?>">Pagos</a>
        <a href="<?= htmlspecialchars($tabUrl('inscripciones'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $tab === 'inscripciones' ? 'is-active' : '' ?>">Proceso inscripción</a>
    </nav>

    <?php if ($tab === 'deuda' && $asociacionId > 0 && $torneoId > 0 && $fvdDetalleBloqueoMsg === ''): ?>
    <div class="fvd-det-table-wrap" style="padding:0.65rem 0.75rem;background:#fff;border:1px solid #e2e8f0;border-radius:0.5rem;margin-bottom:0.75rem;font-size:0.8125rem">
        <?php if ($resumenDeuda !== null && !($resumenDeuda['tiene_deuda'] ?? false)): ?>
            <p style="margin:0 0 0.5rem;color:#64748b">No hay fila en <code>deuda_asociaciones</code> para este torneo y asociación.</p>
            <a class="fvd-det-back" style="margin:0" href="<?= htmlspecialchars($modDeudaUrl, ENT_QUOTES, 'UTF-8') ?>">Ir al módulo de deudas</a>
        <?php elseif ($resumenDeuda !== null): ?>
            <?php
            $rd = $resumenDeuda;
            $est = (string) ($rd['estatus_pago'] ?? '');
            $badgeBg = $est === 'Liquidado' ? '#dcfce7' : ($est === 'Parcial' ? '#fef9c3' : ($est === 'Pendiente' ? '#fee2e2' : '#f1f5f9'));
            $badgeFg = $est === 'Liquidado' ? '#166534' : ($est === 'Parcial' ? '#854d0e' : ($est === 'Pendiente' ? '#991b1b' : '#475569'));
            ?>
            <p style="margin:0 0 0.65rem;display:flex;flex-wrap:wrap;align-items:center;gap:0.5rem">
                <span style="font-weight:800;color:#0f172a">Estatus:</span>
                <span style="padding:0.2rem 0.5rem;border-radius:0.35rem;font-weight:800;background:<?= htmlspecialchars($badgeBg, ENT_QUOTES, 'UTF-8') ?>;color:<?= htmlspecialchars($badgeFg, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($est, ENT_QUOTES, 'UTF-8') ?></span>
                <span style="color:#64748b">· Total deuda: <strong><?= htmlspecialchars($fmt((float) ($rd['deuda_total'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></strong> <?= htmlspecialchars($monedaEt, ENT_QUOTES, 'UTF-8') ?></span>
                <span style="color:#64748b">· Pagos: <strong><?= htmlspecialchars($fmt((float) ($rd['pagos_total'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></strong></span>
                <span style="color:#64748b">· Saldo: <strong><?= ($rd['saldo'] ?? null) === null ? '—' : htmlspecialchars($fmt((float) $rd['saldo']), ENT_QUOTES, 'UTF-8') ?></strong></span>
            </p>
            <table class="fvd-det-table" style="margin:0">
                <thead><tr>
                    <th>Concepto</th><th class="num">Cant.</th><th class="num">Monto (<?= htmlspecialchars($monedaEt, ENT_QUOTES, 'UTF-8') ?>)</th>
                </tr></thead>
                <tbody>
                <tr><td>Afiliación</td><td class="num"><?= (int) ($rd['total_afiliados'] ?? 0) ?></td><td class="num"><?= htmlspecialchars($fmt((float) ($rd['monto_afiliados'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td></tr>
                <tr><td>Inscripciones</td><td class="num"><?= (int) ($rd['total_inscritos'] ?? 0) ?></td><td class="num"><?= htmlspecialchars($fmt((float) ($rd['monto_inscritos'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td></tr>
                <tr><td>Carnets</td><td class="num"><?= (int) ($rd['total_carnets'] ?? 0) ?></td><td class="num"><?= htmlspecialchars($fmt((float) ($rd['monto_carnets'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td></tr>
                <tr><td>Traspasos</td><td class="num"><?= (int) ($rd['total_traspasos'] ?? 0) ?></td><td class="num"><?= htmlspecialchars($fmt((float) ($rd['monto_traspasos'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td></tr>
                <tr><td>Anualidad</td><td class="num"><?= (int) ($rd['total_anualidad'] ?? 0) ?></td><td class="num"><?= htmlspecialchars($fmt((float) ($rd['monto_anualidad'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td></tr>
                </tbody>
            </table>
            <p style="margin:0.65rem 0 0;font-size:0.75rem">
                <a href="<?= htmlspecialchars($modDeudaUrl, ENT_QUOTES, 'UTF-8') ?>" style="font-weight:700;color:#1d4ed8">Editar deuda en el módulo</a>
                · <a href="<?= htmlspecialchars($tabUrl('pagos'), ENT_QUOTES, 'UTF-8') ?>" style="font-weight:700;color:#1d4ed8">Ver pagos</a>
            </p>
        <?php endif; ?>
    </div>
    <?php elseif ($tab === 'pagos' && $asociacionId > 0 && $torneoId > 0 && $fvdDetalleBloqueoMsg === ''): ?>
    <?php if ($resumenDeuda !== null && ($resumenDeuda['tiene_deuda'] ?? false)): ?>
    <p class="fvd-det-meta" style="margin:0 0 0.5rem">
        Deuda <?= htmlspecialchars($fmt((float) ($resumenDeuda['deuda_total'] ?? 0)), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($monedaEt, ENT_QUOTES, 'UTF-8') ?>
        · Pagado <?= htmlspecialchars($fmt((float) ($resumenDeuda['pagos_total'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>
        · <?= htmlspecialchars((string) ($resumenDeuda['estatus_pago'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
    </p>
    <?php endif; ?>
    <div class="fvd-det-table-wrap">
        <table class="fvd-det-table">
            <thead>
            <tr>
                <th>ID</th><th>Fecha</th><th>Tipo</th><th class="num">EUR (contable)</th><th class="num">Bs ref.</th><th class="num">Tasa</th><th>Ref.</th><th>Obs.</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($pagosRows === []): ?>
                <tr><td colspan="8" style="text-align:center;padding:1.25rem;color:#64748b">Sin recibos en <code>relacion_pagos</code> para este torneo.</td></tr>
            <?php else: ?>
                <?php foreach ($pagosRows as $pr): ?>
                <tr>
                    <td><?= (int) ($pr['id'] ?? 0) ?></td>
                    <td><?= htmlspecialchars((string) ($pr['fecha'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(\FvdPortal\Services\StatsService::etiquetaTipoPagoRelacion((string) ($pr['tipo_pago'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="num"><?= htmlspecialchars($fmt((float) ($pr['monto_dolares'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="num"><?= htmlspecialchars($fmt((float) ($pr['monto_total'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="num"><?= htmlspecialchars($fmt((float) ($pr['tasa_cambio'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(trim((string) ($pr['referencia'] ?? '') . ' ' . (string) ($pr['banco'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?php
                        $obsP = (string) ($pr['observaciones'] ?? '');
                    echo htmlspecialchars(strlen($obsP) > 48 ? substr($obsP, 0, 45) . '...' : $obsP, ENT_QUOTES, 'UTF-8');
                    ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="fvd-det-meta" style="margin:0.5rem 0 0"><a href="<?= htmlspecialchars($modPagosUrl, ENT_QUOTES, 'UTF-8') ?>" style="font-weight:700;color:#1d4ed8">Módulo de pagos (filtrado por asociación)</a></p>
    <?php elseif ($tab === 'inscripciones' && $asociacionId > 0 && $torneoId > 0 && $fvdDetalleBloqueoMsg === ''): ?>
    <div class="fvd-det-table-wrap">
        <table class="fvd-det-table">
            <thead>
            <tr>
                <th>Cédula</th><th>Nombre</th><th>Equipo</th>
                <th>Afil.</th><th>Inscr.</th><th>Carnet</th><th>Trasp.</th><th>Anual.</th><th>Fecha ref.</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($procInscRows === []): ?>
                <tr><td colspan="9" style="text-align:center;padding:1.25rem;color:#64748b">Sin filas de inscripción para esta cohorte (tabla <code>inscripcion_torneo</code> o <code>atletas</code> según configuración).</td></tr>
            <?php else: ?>
                <?php foreach ($procInscRows as $ir): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($ir['cedula'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($ir['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= $ir['nombre_equipo'] !== null ? htmlspecialchars((string) $ir['nombre_equipo'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td><?= htmlspecialchars($fvdSiNo((int) ($ir['afiliacion'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($fvdSiNo((int) ($ir['inscripcion'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($fvdSiNo((int) ($ir['carnet'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($fvdSiNo((int) ($ir['traspaso'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($fvdSiNo((int) ($ir['anualidad'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?php $fr = $ir['fecha_ref'] ?? null; echo $fr !== null && $fr !== '' ? htmlspecialchars((string) $fr, ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="fvd-det-table-wrap">
        <table class="fvd-det-table">
            <thead>
            <tr>
                <th>Cédula</th>
                <th>Nombre</th>
                <th class="num">Monto (<?= htmlspecialchars($monedaEt, ENT_QUOTES, 'UTF-8') ?>)</th>
                <th>Fecha ref.</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($asociacionId <= 0 || $torneoId <= 0): ?>
                <tr><td colspan="4" style="text-align:center;padding:1.25rem;color:#64748b">Indique asociación y torneo válidos en la URL.</td></tr>
            <?php elseif ($fvdDetalleBloqueoMsg !== ''): ?>
                <tr><td colspan="4" style="text-align:center;padding:1.25rem;color:#b45309"><?= htmlspecialchars($fvdDetalleBloqueoMsg, ENT_QUOTES, 'UTF-8') ?></td></tr>
            <?php elseif ($rows === []): ?>
                <tr><td colspan="4" style="text-align:center;padding:1.25rem;color:#64748b">Sin registros para este segmento o sin tarifas en <code>costos</code>.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($row['cedula'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($row['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="num"><?= htmlspecialchars($fmt((float) ($row['monto'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?php
                            $fd = $row['fecha'] ?? null;
                        echo $fd !== null && $fd !== '' ? htmlspecialchars((string) $fd, ENT_QUOTES, 'UTF-8') : '—';
                        ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
