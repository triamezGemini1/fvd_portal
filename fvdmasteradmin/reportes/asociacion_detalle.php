<?php

declare(strict_types=1);

/**
 * Detalle nominal por torneo + asociación (afiliados, inscritos, carnets).
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

$pdo = fvd_db();
$embedded = (isset($_GET['embedded']) && (string) $_GET['embedded'] === '1')
    || (isset($_GET['fvd_master_embed']) && (string) $_GET['fvd_master_embed'] === '1');

$asociacionId = max(0, (int) ($_GET['asociacion_id'] ?? 0));
$torneoId = max(0, (int) ($_GET['torneo_id'] ?? 0));
$tab = trim((string) ($_GET['tab'] ?? 'afiliados'));
$allowedTabs = ['afiliados' => true, 'inscritos' => true, 'carnets' => true];
if (!isset($allowedTabs[$tab])) {
    $tab = 'afiliados';
}

$usaEur = \FvdPortal\Services\StatsService::deudaAsociacionesUsaEur($pdo);
$monedaEt = $usaEur ? 'EUR' : 'Bs';

$nombreAsoc = '';
$nombreTorneo = '';
if ($asociacionId > 0) {
    $st = $pdo->prepare('SELECT nombre FROM asociaciones WHERE id = :id LIMIT 1');
    $st->execute([':id' => $asociacionId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (is_array($r)) {
        $nombreAsoc = trim((string) ($r['nombre'] ?? ''));
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
if ($asociacionId > 0 && $torneoId > 0) {
    $rows = \FvdPortal\Services\StatsService::detalleNominalTorneoAsociacion($pdo, $torneoId, $asociacionId, $tab);
}

$fmt = static function (float $v): string {
    return number_format($v, 2, ',', '.');
};

$fvdUiCss = url('assets/css/fvd-ui-mistorneos.css');
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
                · Montos unitarios según última fila de <code>costos</code> · Unidad: <?= htmlspecialchars($monedaEt, ENT_QUOTES, 'UTF-8') ?>.
            </p>
        </div>
    </div>

    <nav class="fvd-det-tabs" aria-label="Segmento">
        <a href="<?= htmlspecialchars($tabUrl('afiliados'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $tab === 'afiliados' ? 'is-active' : '' ?>">Afiliados</a>
        <a href="<?= htmlspecialchars($tabUrl('inscritos'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $tab === 'inscritos' ? 'is-active' : '' ?>">Inscritos</a>
        <a href="<?= htmlspecialchars($tabUrl('carnets'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $tab === 'carnets' ? 'is-active' : '' ?>">Carnets</a>
    </nav>

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
</div>
</body>
</html>
