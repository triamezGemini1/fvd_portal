<?php

declare(strict_types=1);

/**
 * Reporte consolidado de deudas por asociación (admin general).
 * Vista embebida en Panel Maestro: ?embedded=1 (sin menú lateral).
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

$ajax = (string) ($_GET['ajax'] ?? '');
if ($ajax !== '') {
    header('Content-Type: application/json; charset=UTF-8');
    if ($ajax === 'torneos') {
        $aid = max(0, (int) ($_GET['asociacion_id'] ?? 0));
        if ($aid <= 0) {
            echo json_encode(['ok' => false, 'error' => 'asociacion_id']);
            exit;
        }
        $list = \FvdPortal\Services\StatsService::torneosDeudaPorAsociacion($pdo, $aid);
        echo json_encode(['ok' => true, 'torneos' => $list], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($ajax === 'renglones') {
        $aid = max(0, (int) ($_GET['asociacion_id'] ?? 0));
        $tid = max(0, (int) ($_GET['torneo_id'] ?? 0));
        if ($aid <= 0 || $tid <= 0) {
            echo json_encode(['ok' => false, 'error' => 'params']);
            exit;
        }
        try {
            $chk = $pdo->prepare('SELECT 1 FROM deuda_asociaciones WHERE torneo_id = :t AND asociacion_id = :a LIMIT 1');
            $chk->execute([':t' => $tid, ':a' => $aid]);
            if (!$chk->fetchColumn()) {
                echo json_encode(['ok' => false, 'error' => 'not_found']);
                exit;
            }
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => 'db']);
            exit;
        }
        $seg = \FvdPortal\Services\StatsService::segmentosRenglonesTorneoAsociacion($pdo, $tid, $aid);
        echo json_encode(['ok' => true, 'segmentos' => $seg], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['ok' => false, 'error' => 'ajax']);
    exit;
}

$report = \FvdPortal\Services\StatsService::reporteConsolidadoDeudasPorAsociacion($pdo);
$rows = $report['rows'] ?? [];
$tot = $report['totales'] ?? [];
$usaEur = (bool) ($report['usa_eur'] ?? false);
$monedaEt = $usaEur ? 'EUR' : 'Bs';

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="consolidado_deudas_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    if ($out !== false) {
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Asociación', 'Deuda afiliación', 'Deuda inscripciones', 'Deuda traspasos', 'Carnets', 'Anualidad', 'Total deuda', 'Pagos (' . $monedaEt . ')', 'Saldo'], ';');
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['nombre'] ?? '',
                $r['monto_afiliacion'] ?? 0,
                $r['monto_inscripciones'] ?? 0,
                $r['monto_traspasos'] ?? 0,
                $r['monto_carnets'] ?? 0,
                $r['monto_anualidad'] ?? 0,
                $r['deuda_total'] ?? 0,
                $r['pagos_eur'] ?? 0,
                $r['saldo'] ?? '',
            ], ';');
        }
        fputcsv($out, [
            'TOTAL',
            $tot['monto_afiliacion'] ?? 0,
            $tot['monto_inscripciones'] ?? 0,
            $tot['monto_traspasos'] ?? 0,
            $tot['monto_carnets'] ?? 0,
            $tot['monto_anualidad'] ?? 0,
            $tot['deuda_total'] ?? 0,
            $tot['pagos_eur'] ?? 0,
            $tot['saldo'] ?? '',
        ], ';');
        fclose($out);
    }
    exit;
}

$fmt = static function (float $v): string {
    return number_format($v, 2, ',', '.');
};

$fvdUiCss = url('assets/css/fvd-ui-mistorneos.css');
$selfUrl = url('fvdmasteradmin/reportes/consolidado_finanzas.php');
$detalleBase = url('fvdmasteradmin/reportes/asociacion_detalle.php');
$jsEmbed = $embedded ? ['embedded' => '1', 'fvd_master_embed' => '1'] : [];
$csvQ = array_merge($_GET, ['export' => 'csv']);
$csvUrl = $selfUrl . '?' . http_build_query($csvQ);

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es" class="<?= $embedded ? 'h-full' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consolidado de deudas — FVD</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($fvdUiCss, ENT_QUOTES, 'UTF-8') ?>">
    <style>
        .fvd-consol-wrap { margin: 0; padding: 0; width: 100%; max-width: none; box-sizing: border-box; font-family: Inter, system-ui, sans-serif; background: #f8fafc; }
        .fvd-consol-wrap.embedded { min-height: 100%; padding: 0.5rem 0.35rem 1rem; }
        .fvd-consol-wrap:not(.embedded) { padding: 1rem; }
        .fvd-consol-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 0.75rem; margin: 0 0 0.75rem; }
        .fvd-consol-head h1 { margin: 0; font-size: 1rem; font-weight: 800; color: #0f172a; }
        .fvd-consol-meta { font-size: 0.75rem; color: #64748b; max-width: 42rem; line-height: 1.4; }
        .fvd-consol-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
        .fvd-consol-actions a, .fvd-consol-actions button {
            display: inline-flex; align-items: center; gap: 0.35rem;
            padding: 0.4rem 0.75rem; font-size: 0.75rem; font-weight: 700;
            border-radius: 0.375rem; border: 1px solid #cbd5e1; background: #fff; color: #0f172a;
            text-decoration: none; cursor: pointer;
        }
        .fvd-consol-actions a:hover, .fvd-consol-actions button:hover { background: #f1f5f9; }
        .fvd-consol-table-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border: 1px solid #e2e8f0; border-radius: 0.5rem; background: #fff; }
        .fvd-consol-table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
        .fvd-consol-table th, .fvd-consol-table td { padding: 0.4rem 0.5rem; text-align: right; border-bottom: 1px solid #f1f5f9; white-space: nowrap; }
        .fvd-consol-table th:first-child, .fvd-consol-table td:first-child { text-align: left; min-width: 10rem; }
        .fvd-consol-table thead th { background: #1e293b; color: #f8fafc; font-weight: 700; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.03em; }
        .fvd-consol-table tbody tr.fvd-acc-main:hover { background: #f1f5f9; }
        .fvd-consol-table tbody tr:not(.fvd-acc-main):not(.fvd-acc-assoc-panel):hover { background: #f8fafc; }
        .fvd-consol-table tfoot td { background: #ecfdf5; font-weight: 800; border-top: 2px solid #10b981; }
        .fvd-acc-main { cursor: pointer; user-select: none; }
        .fvd-acc-main td:first-child { white-space: normal; }
        .fvd-acc-chev { display: inline-block; width: 1rem; transition: transform 0.15s ease; color: #64748b; font-size: 0.65rem; vertical-align: middle; }
        .fvd-acc-main.is-open .fvd-acc-chev { transform: rotate(90deg); color: #0f172a; }
        .fvd-acc-assoc-panel td { text-align: left !important; vertical-align: top; background: #f8fafc; border-bottom: 1px solid #e2e8f0 !important; padding: 0.5rem 0.45rem !important; white-space: normal !important; }
        .fvd-sub-torneos { width: 100%; border-collapse: collapse; font-size: 0.75rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 0.375rem; overflow: hidden; }
        .fvd-sub-torneos th, .fvd-sub-torneos td { padding: 0.35rem 0.45rem; border-bottom: 1px solid #f1f5f9; text-align: left; }
        .fvd-sub-torneos th { background: #e2e8f0; font-weight: 700; font-size: 0.65rem; text-transform: uppercase; color: #334155; }
        .fvd-sub-torneos .num { text-align: right; white-space: nowrap; }
        .fvd-sub-torneos tr:last-child td { border-bottom: none; }
        .fvd-reng-panel td { background: #fafafa !important; padding: 0.5rem !important; }
        .fvd-seg-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: 0.5rem; }
        .fvd-seg-card { border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.45rem 0.55rem; background: #fff; }
        .fvd-seg-card h4 { margin: 0 0 0.35rem; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; font-weight: 800; }
        .fvd-seg-card p { margin: 0; font-size: 0.75rem; color: #0f172a; }
        .fvd-seg-card a { display: inline-block; margin-top: 0.35rem; font-size: 0.7rem; font-weight: 700; color: #1d4ed8; text-decoration: none; }
        .fvd-seg-card a:hover { text-decoration: underline; }
        .fvd-btn-reng { font-size: 0.7rem; font-weight: 700; padding: 0.25rem 0.5rem; border-radius: 0.35rem; border: 1px solid #cbd5e1; background: #fff; cursor: pointer; }
        .fvd-btn-reng:hover { background: #f1f5f9; }
        .fvd-acc-placeholder { margin: 0.25rem 0; font-size: 0.75rem; color: #64748b; }
        @media print {
            .fvd-consol-actions { display: none; }
            .fvd-consol-wrap { background: #fff; }
            .fvd-acc-assoc-panel { display: table-row !important; }
        }
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
<div class="fvd-consol-wrap<?= $embedded ? ' embedded' : '' ?>">
    <div class="fvd-consol-head">
        <div>
            <h1>Reporte consolidado de deudas</h1>
            <p class="fvd-consol-meta">
                Agrupado por asociación. Montos desde <code>deuda_asociaciones</code> (suma de torneos):
                afiliación, inscripciones a torneos, traspasos, carnets y anualidad.
                Pagos: suma de <code>relacion_pagos.monto_dolares</code> por club. Unidad de totales: <?= htmlspecialchars($monedaEt, ENT_QUOTES, 'UTF-8') ?>.
            </p>
        </div>
        <div class="fvd-consol-actions">
            <a href="<?= htmlspecialchars($csvUrl, ENT_QUOTES, 'UTF-8') ?>">Exportar Excel (CSV)</a>
            <button type="button" onclick="window.print()">Exportar PDF (imprimir)</button>
            <?php if (!$embedded): ?>
            <a href="<?= htmlspecialchars(url('fvdmasteradmin/master_panel.php'), ENT_QUOTES, 'UTF-8') ?>">Panel maestro</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="fvd-consol-table-wrap">
        <table class="fvd-consol-table" id="fvd-consol-table">
            <thead>
            <tr>
                <th>Asociación</th>
                <th>Afiliación</th>
                <th>Inscripciones</th>
                <th>Traspasos</th>
                <th>Carnets</th>
                <th>Anualidad</th>
                <th>Total deuda</th>
                <th>Pagos</th>
                <th>Saldo</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <?php $aid = (int) ($r['asociacion_id'] ?? 0); ?>
                <tr class="fvd-acc-main" data-asoc-id="<?= $aid ?>" role="button" tabindex="0" aria-expanded="false">
                    <td>
                        <span class="fvd-acc-chev" aria-hidden="true">▸</span>
                        <?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td><?= htmlspecialchars($fmt((float) ($r['monto_afiliacion'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($fmt((float) ($r['monto_inscripciones'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($fmt((float) ($r['monto_traspasos'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($fmt((float) ($r['monto_carnets'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($fmt((float) ($r['monto_anualidad'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($fmt((float) ($r['deuda_total'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($fmt((float) ($r['pagos_eur'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= $r['saldo'] === null ? '—' : htmlspecialchars($fmt((float) $r['saldo']), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr class="fvd-acc-assoc-panel" id="fvd-panel-asoc-<?= $aid ?>" hidden>
                    <td colspan="9">
                        <div class="fvd-acc-panel-inner" data-asoc-panel="<?= $aid ?>">
                            <p class="fvd-acc-placeholder">Despliegue la fila para cargar torneos con deuda registrada.</p>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="9" style="text-align:center;padding:1.5rem;color:#64748b">Sin filas de deuda registradas. Sincronice estados de cuenta en Finanzas — Deudas.</td></tr>
            <?php endif; ?>
            </tbody>
            <?php if ($rows !== []): ?>
            <tfoot>
            <tr>
                <td>TOTAL</td>
                <td><?= htmlspecialchars($fmt((float) ($tot['monto_afiliacion'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($fmt((float) ($tot['monto_inscripciones'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($fmt((float) ($tot['monto_traspasos'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($fmt((float) ($tot['monto_carnets'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($fmt((float) ($tot['monto_anualidad'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($fmt((float) ($tot['deuda_total'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($fmt((float) ($tot['pagos_eur'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= ($tot['saldo'] ?? null) === null ? '—' : htmlspecialchars($fmt((float) $tot['saldo']), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>
<script>
(function () {
    var cfg = <?= json_encode([
        'ajaxUrl' => $selfUrl,
        'detalleBase' => $detalleBase,
        'embed' => $jsEmbed,
        'moneda' => $monedaEt,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var cacheTorneos = {};
    var cacheRenglones = {};

    function qs(u) {
        var q = [];
        Object.keys(cfg.embed || {}).forEach(function (k) {
            q.push(encodeURIComponent(k) + '=' + encodeURIComponent(cfg.embed[k]));
        });
        return q.length ? '&' + q.join('&') : '';
    }

    function detalleUrl(aid, tid, tab) {
        var p = new URLSearchParams();
        Object.keys(cfg.embed || {}).forEach(function (k) {
            p.set(k, cfg.embed[k]);
        });
        p.set('asociacion_id', String(aid));
        p.set('torneo_id', String(tid));
        p.set('tab', tab);
        return cfg.detalleBase + '?' + p.toString();
    }

    function fmt(n) {
        var x = Number(n);
        if (!isFinite(x)) return '0,00';
        return x.toFixed(2).replace('.', ',');
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function renderTorneos(aid, list) {
        if (!list || list.length === 0) {
            return '<p class="fvd-acc-placeholder">No hay filas en <code>deuda_asociaciones</code> para esta asociación.</p>';
        }
        var html = '<table class="fvd-sub-torneos"><thead><tr><th>Torneo</th><th>Estatus pago</th><th class="num">Deuda</th><th class="num">Pagos</th><th></th></tr></thead><tbody>';
        list.forEach(function (t) {
            var key = aid + ':' + t.torneo_id;
            var rengId = 'fvd-reng-' + key.replace(/[^a-zA-Z0-9]/g, '_');
            html += '<tr class="fvd-torneo-row" data-aid="' + aid + '" data-tid="' + t.torneo_id + '">';
            html += '<td>' + esc(t.torneo_nombre || '') + '</td>';
            html += '<td><span title="Deuda vs pagos del torneo">' + esc(t.estatus_pago || '') + '</span></td>';
            html += '<td class="num">' + fmt(t.deuda) + '</td>';
            html += '<td class="num">' + fmt(t.pagos) + '</td>';
            html += '<td style="white-space:nowrap"><button type="button" class="fvd-btn-reng" data-aid="' + aid + '" data-tid="' + t.torneo_id + '" aria-expanded="false">Ver renglones</button></td>';
            html += '</tr>';
            html += '<tr class="fvd-reng-panel" id="' + rengId + '" hidden><td colspan="5"><div class="fvd-reng-body" data-reng="' + key + '"></div></td></tr>';
        });
        html += '</tbody></table>';
        return html;
    }

    function hrefAttr(u) {
        return String(u).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
    }

    function renderSegmentos(segs, aid, tid) {
        var a = segs.afiliados || { count: 0, monto: 0 };
        var i = segs.inscritos || { count: 0, monto: 0 };
        var c = segs.carnets || { count: 0, monto: 0 };
        return '<div class="fvd-seg-grid">' +
            '<div class="fvd-seg-card"><h4>Afiliados</h4><p>' + a.count + ' · ' + fmt(a.monto) + ' ' + cfg.moneda + '</p><a href="' + hrefAttr(detalleUrl(aid, tid, 'afiliados')) + '">Ver detalles</a></div>' +
            '<div class="fvd-seg-card"><h4>Inscritos</h4><p>' + i.count + ' · ' + fmt(i.monto) + ' ' + cfg.moneda + '</p><a href="' + hrefAttr(detalleUrl(aid, tid, 'inscritos')) + '">Ver detalles</a></div>' +
            '<div class="fvd-seg-card"><h4>Carnets</h4><p>' + c.count + ' · ' + fmt(c.monto) + ' ' + cfg.moneda + '</p><a href="' + hrefAttr(detalleUrl(aid, tid, 'carnets')) + '">Ver detalles</a></div>' +
            '</div>';
    }

    document.getElementById('fvd-consol-table').addEventListener('click', function (ev) {
        var btn = ev.target.closest('.fvd-btn-reng');
        if (!btn) return;
        ev.stopPropagation();
        var aid = parseInt(btn.getAttribute('data-aid'), 10);
        var tid = parseInt(btn.getAttribute('data-tid'), 10);
        var row = btn.closest('tr');
        if (!row) return;
        var rengRow = row.nextElementSibling;
        if (!rengRow || !rengRow.classList.contains('fvd-reng-panel')) return;
        var body = rengRow.querySelector('.fvd-reng-body');
        if (!body) return;
        var open = btn.getAttribute('aria-expanded') === 'true';
        if (open) {
            rengRow.hidden = true;
            btn.setAttribute('aria-expanded', 'false');
            return;
        }
        var ck = aid + ':' + tid;
        btn.setAttribute('aria-expanded', 'true');
        rengRow.hidden = false;
        if (cacheRenglones[ck]) {
            body.innerHTML = renderSegmentos(cacheRenglones[ck], aid, tid);
            return;
        }
        body.innerHTML = '<p class="fvd-acc-placeholder">Cargando…</p>';
        fetch(cfg.ajaxUrl + '?ajax=renglones&asociacion_id=' + aid + '&torneo_id=' + tid + qs(), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok || !data.segmentos) {
                    body.innerHTML = '<p class="fvd-acc-placeholder">No se pudieron cargar los renglones.</p>';
                    return;
                }
                cacheRenglones[ck] = data.segmentos;
                body.innerHTML = renderSegmentos(data.segmentos, aid, tid);
            })
            .catch(function () {
                body.innerHTML = '<p class="fvd-acc-placeholder">Error de red.</p>';
            });
    });

    document.querySelectorAll('tr.fvd-acc-main').forEach(function (mainRow) {
        function toggle() {
            var aid = parseInt(mainRow.getAttribute('data-asoc-id'), 10);
            var panel = mainRow.nextElementSibling;
            if (!panel || !panel.classList.contains('fvd-acc-assoc-panel')) return;
            var open = !panel.hidden;
            if (open) {
                panel.hidden = true;
                mainRow.classList.remove('is-open');
                mainRow.setAttribute('aria-expanded', 'false');
                return;
            }
            panel.hidden = false;
            mainRow.classList.add('is-open');
            mainRow.setAttribute('aria-expanded', 'true');
            var inner = panel.querySelector('[data-asoc-panel="' + aid + '"]');
            if (!inner) return;
            if (inner.getAttribute('data-loaded') === '1') return;
            inner.innerHTML = '<p class="fvd-acc-placeholder">Cargando torneos…</p>';
            fetch(cfg.ajaxUrl + '?ajax=torneos&asociacion_id=' + aid + qs(), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        inner.innerHTML = '<p class="fvd-acc-placeholder">No se pudieron cargar los torneos.</p>';
                        return;
                    }
                    cacheTorneos[aid] = data.torneos;
                    inner.innerHTML = renderTorneos(aid, data.torneos);
                    inner.setAttribute('data-loaded', '1');
                })
                .catch(function () {
                    inner.innerHTML = '<p class="fvd-acc-placeholder">Error de red.</p>';
                });
        }
        mainRow.addEventListener('click', toggle);
        mainRow.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggle();
            }
        });
    });
})();
</script>
</body>
</html>
