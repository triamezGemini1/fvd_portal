<?php

declare(strict_types=1);

/**
 * Bloque UI: indicadores de servicio (atletas) y costos estimados por tarifa vigente.
 *
 * @var array $fvdIndicadoresCostos resultado de StatsService::indicadoresServicioConCostosEstimados
 * @var string $fvdIndicadoresCostosVariant 'delegado'|'full'
 * @var string|null $fvdReporteIndicadoresUrl enlace opcional al reporte detallado
 */

if (!isset($fvdIndicadoresCostos) || !is_array($fvdIndicadoresCostos)) {
    return;
}

require_once dirname(__DIR__, 2) . '/src/Services/IndicadoresTablaDefs.php';

use FvdPortal\Services\IndicadoresTablaDefs;

$ic = $fvdIndicadoresCostos;
$variant = isset($fvdIndicadoresCostosVariant) && $fvdIndicadoresCostosVariant === 'delegado' ? 'delegado' : 'full';
$repUrl = isset($fvdReporteIndicadoresUrl) ? (string) $fvdReporteIndicadoresUrl : '';

$tarifa = $ic['tarifa'] ?? null;
$tot = $ic['totales'] ?? [];
$counts = is_array($tot['counts'] ?? null) ? $tot['counts'] : [];
$montoGrand = (float) ($tot['monto_total'] ?? 0);
$porAsoc = is_array($ic['por_asociacion'] ?? null) ? $ic['por_asociacion'] : [];

$fvdAsocDetailApiUrl = isset($fvdAsocDetailApiUrl) ? (string) $fvdAsocDetailApiUrl : '';
$fvdIndCols = IndicadoresTablaDefs::columnasMetricas();
$fvdAsocDetailColspan = 4 + count($fvdIndCols);
$countsVals = IndicadoresTablaDefs::valoresMetricasInt($counts, 'indicadores dashboard resumen');

$fmtN = static function (float $v): string {
    return \function_exists('fvd_format_contable') ? fvd_format_contable($v) : number_format($v, 2, ',', '.');
};

$fechaTar = '';
if (is_array($tarifa) && isset($tarifa['fecha'])) {
    $fechaTar = substr((string) $tarifa['fecha'], 0, 10);
}

$wrapStyle = $variant === 'delegado'
    ? 'margin:0 0 1rem;padding:12px 14px;border-radius:10px;border:1px solid #e2e8f0;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,0.06);max-width:72rem;color:#0f172a'
    : 'margin:0 0 1.25rem;padding:12px 14px;border-radius:10px;border:1px solid var(--fvd-border, rgba(255,255,255,0.12));background:rgba(255,255,255,0.04);max-width:72rem';
$mutedColor = $variant === 'delegado' ? '#64748b' : 'var(--fvd-muted,#94a3b8)';
$linkColor = $variant === 'delegado' ? '#2563eb' : 'var(--fvd-amarillo,#facc15)';

?>
<section class="fvd-ic-dash" aria-label="Indicadores de servicio y costos estimados" style="<?= htmlspecialchars($wrapStyle, ENT_QUOTES, 'UTF-8') ?>">
    <div style="display:flex;flex-wrap:wrap;align-items:baseline;justify-content:space-between;gap:8px;margin:0 0 10px">
        <h2 style="margin:0;font-size:<?= $variant === 'delegado' ? '0.95rem' : '1rem' ?>">Indicadores de servicio y costos (vista <?= $variant === 'delegado' ? 'club' : 'nacional' ?>)</h2>
        <?php if ($repUrl !== ''): ?>
            <a href="<?= htmlspecialchars($repUrl, ENT_QUOTES, 'UTF-8') ?>" style="font-size:.75rem;color:<?= htmlspecialchars($linkColor, ENT_QUOTES, 'UTF-8') ?>;text-decoration:underline">Reporte detallado</a>
        <?php endif; ?>
    </div>
    <p style="margin:0 0 12px;font-size:.72rem;color:<?= htmlspecialchars($mutedColor, ENT_QUOTES, 'UTF-8') ?>;line-height:1.45">
        Cada columna cuenta atletas con ese campo en <strong>1</strong>, de forma independiente (sin filtrar por otros indicadores).
        Montos = conteo × última tarifa en <code>costos</code><?= $fechaTar !== '' ? ' (fecha ' . htmlspecialchars($fechaTar, ENT_QUOTES, 'UTF-8') . ')' : '' ?>.
        <?php if ($tarifa === null): ?><strong style="color:#f87171"> No hay filas en <code>costos</code>; solo se muestran cantidades.</strong><?php endif; ?>
    </p>

    <div style="overflow-x:auto;margin-bottom:12px">
        <table class="fvd-mod-table" style="font-size:<?= $variant === 'delegado' ? '.78rem' : '.8rem' ?>;min-width:520px">
            <thead>
            <tr>
                <th scope="col"><?= $variant === 'delegado' ? 'Resumen' : 'Ámbito' ?></th>
                <?php foreach ($fvdIndCols as $col): ?>
                <th scope="col" style="text-align:right" title="<?= htmlspecialchars($col['title'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($col['label'], ENT_QUOTES, 'UTF-8') ?></th>
                <?php endforeach; ?>
                <th scope="col" style="text-align:right">Total est.</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <th scope="row">General</th>
                <?php foreach ($fvdIndCols as $col): ?>
                <td style="text-align:right"><?= (int) ($countsVals[$col['key']] ?? 0) ?></td>
                <?php endforeach; ?>
                <td style="text-align:right;font-weight:600"><?= $fmtN($montoGrand) ?></td>
            </tr>
            </tbody>
        </table>
    </div>

    <?php if ($porAsoc !== []): ?>
    <h3 style="margin:0 0 8px;font-size:.85rem">Por asociación</h3>
    <p style="margin:0 0 8px;font-size:.72rem;color:<?= htmlspecialchars($mutedColor, ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($fvdAsocDetailApiUrl !== '' && $variant === 'full'): ?>
            Use <strong>Ver ficha</strong> para desplegar datos de contacto, deudas por torneo y pagos recientes, con enlaces a cada expediente.
        <?php endif; ?>
    </p>
    <div style="overflow-x:auto;max-height:min(48vh,520px);overflow-y:auto">
        <table class="fvd-mod-table" style="font-size:<?= $variant === 'delegado' ? '.75rem' : '.78rem' ?>">
            <thead>
            <tr>
                <th scope="col">ID</th>
                <th scope="col">Asociación</th>
                <?php foreach ($fvdIndCols as $col): ?>
                <th scope="col" style="text-align:right" title="<?= htmlspecialchars($col['title'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($col['labelShort'], ENT_QUOTES, 'UTF-8') ?></th>
                <?php endforeach; ?>
                <th scope="col" style="text-align:right">Total est.</th>
                <?php if ($fvdAsocDetailApiUrl !== '' && $variant === 'full'): ?>
                <th scope="col" style="white-space:nowrap">Ficha</th>
                <?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($porAsoc as $pa): ?>
                <?php
                $mt = (float) ($pa['monto_total'] ?? 0);
                $paId = (int) ($pa['asociacion_id'] ?? 0);
                $paVals = IndicadoresTablaDefs::valoresMetricasInt($pa, 'indicadores dashboard asoc id=' . $paId);
                ?>
                <tr>
                    <td style="white-space:nowrap"><?= $paId ?></td>
                    <td><?= htmlspecialchars((string) ($pa['asociacion_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <?php foreach ($fvdIndCols as $col): ?>
                    <td style="text-align:right"><?= (int) ($paVals[$col['key']] ?? 0) ?></td>
                    <?php endforeach; ?>
                    <td style="text-align:right;font-weight:600"><?= $fmtN($mt) ?></td>
                    <?php if ($fvdAsocDetailApiUrl !== '' && $variant === 'full'): ?>
                    <td style="white-space:nowrap">
                        <button type="button" class="fvd-ic-dash__btn-detalle fvd-input" style="padding:4px 10px;font-size:.75rem;cursor:pointer;width:auto"
                            data-asoc-id="<?= (int) $paId ?>" aria-expanded="false" aria-controls="fvd-asoc-detail-<?= (int) $paId ?>">Ver ficha</button>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php if ($fvdAsocDetailApiUrl !== '' && $variant === 'full'): ?>
                <tr class="fvd-asoc-dash-detail" id="fvd-asoc-detail-<?= (int) $paId ?>" hidden>
                    <td colspan="<?= (int) $fvdAsocDetailColspan ?>" style="background:rgba(0,0,0,.12);padding:10px 12px;vertical-align:top">
                        <div class="fvd-asoc-dash-detail__inner" data-loaded="0" data-asoc-id="<?= (int) $paId ?>" style="font-size:.78rem;line-height:1.45;color:#e2e8f0">
                            Pulse «Ver ficha» para cargar datos.
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($fvdAsocDetailApiUrl !== '' && $variant === 'full' && $porAsoc !== []): ?>
    <script>
    (function () {
        var api = <?= json_encode($fvdAsocDetailApiUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        function esc(s) {
            if (s == null) return '';
            var d = document.createElement('div');
            d.textContent = String(s);
            return d.innerHTML;
        }
        function fmtNum(v) {
            var n = parseFloat(v);
            if (isNaN(n)) return '—';
            return n.toFixed(2).replace('.', ',');
        }
        function buildHtml(j) {
            var a = j.asociacion || {};
            var u = j.urls || {};
            var h = '';
            h += '<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:10px">';
            h += '<strong style="font-size:.85rem">Asociación #' + esc(a.id) + '</strong>';
            if (u.editar_asociacion) {
                h += '<a href="' + esc(u.editar_asociacion) + '" style="color:#facc15;text-decoration:underline">Editar ficha (CRUD)</a>';
            }
            if (u.lista_deudas) {
                h += '<a href="' + esc(u.lista_deudas) + '" style="color:#facc15;text-decoration:underline">Módulo deudas</a>';
            }
            if (u.lista_pagos) {
                h += '<a href="' + esc(u.lista_pagos) + '" style="color:#facc15;text-decoration:underline">Módulo pagos</a>';
            }
            h += '</div>';
            h += '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px;margin-bottom:12px;font-size:.76rem">';
            var lab = { nombre: 'Nombre', delegado: 'Delegado', telefono: 'Teléfono', email: 'Email', direccion: 'Dirección', numreg: 'Nº registro' };
            ['nombre', 'delegado', 'telefono', 'email', 'direccion', 'numreg'].forEach(function (k) {
                if (a[k] != null && String(a[k]).trim() !== '') {
                    h += '<div><span style="opacity:.75">' + esc(lab[k] || k) + ':</span> ' + esc(a[k]) + '</div>';
                }
            });
            h += '</div>';
            h += '<h4 style="margin:10px 0 6px;font-size:.8rem">Deudas por torneo</h4>';
            if (!j.deudas || !j.deudas.length) {
                h += '<p style="margin:0;opacity:.85">Sin filas en deuda para esta asociación.</p>';
            } else {
                h += '<div style="overflow-x:auto"><table class="fvd-mod-table" style="font-size:.72rem"><thead><tr>';
                h += '<th>Torneo</th><th style="text-align:right">Total</th><th style="text-align:right">EUR</th><th></th></tr></thead><tbody>';
                j.deudas.forEach(function (d) {
                    h += '<tr><td>' + esc(d.torneo_nombre || ('ID ' + d.torneo_id)) + '</td>';
                    h += '<td style="text-align:right">' + fmtNum(d.monto_total) + '</td>';
                    h += '<td style="text-align:right">' + (d.monto_total_eur != null ? fmtNum(d.monto_total_eur) : '—') + '</td>';
                    h += '<td style="white-space:nowrap">';
                    if (d.url_detalle) {
                        h += '<a href="' + esc(d.url_detalle) + '" style="color:#facc15">Detalle / conceptos</a>';
                    }
                    h += '</td></tr>';
                });
                h += '</tbody></table></div>';
            }
            h += '<h4 style="margin:12px 0 6px;font-size:.8rem">Pagos recientes</h4>';
            if (!j.pagos || !j.pagos.length) {
                h += '<p style="margin:0;opacity:.85">Sin pagos registrados para esta asociación.</p>';
            } else {
                h += '<div style="overflow-x:auto"><table class="fvd-mod-table" style="font-size:.72rem"><thead><tr>';
                h += '<th>Fecha</th><th>Torneo</th><th style="text-align:right">EUR</th><th style="text-align:right">Bs ref.</th><th></th></tr></thead><tbody>';
                j.pagos.forEach(function (p) {
                    h += '<tr><td>' + esc(p.fecha) + '</td><td>' + esc(p.torneo_nombre || ('ID ' + p.torneo_id)) + '</td>';
                    h += '<td style="text-align:right">' + fmtNum(p.monto_dolares) + '</td>';
                    h += '<td style="text-align:right">' + fmtNum(p.monto_total) + '</td>';
                    h += '<td style="white-space:nowrap">';
                    if (p.url_detalle) {
                        h += '<a href="' + esc(p.url_detalle) + '" style="color:#facc15">Ver recibo</a>';
                    }
                    h += '</td></tr>';
                });
                h += '</tbody></table></div>';
            }
            return h;
        }
        document.querySelectorAll('.fvd-ic-dash__btn-detalle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-asoc-id');
                var row = document.getElementById('fvd-asoc-detail-' + id);
                if (!row) return;
                var inner = row.querySelector('.fvd-asoc-dash-detail__inner');
                var open = !row.hidden;
                if (open) {
                    row.hidden = true;
                    btn.setAttribute('aria-expanded', 'false');
                    btn.textContent = 'Ver ficha';
                    return;
                }
                row.hidden = false;
                btn.setAttribute('aria-expanded', 'true');
                btn.textContent = 'Ocultar';
                if (inner.getAttribute('data-loaded') === '1') return;
                inner.innerHTML = 'Cargando…';
                fetch(api + '?id=' + encodeURIComponent(id), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        inner.setAttribute('data-loaded', '1');
                        if (!j.ok) {
                            inner.textContent = j.error || 'Error al cargar.';
                            return;
                        }
                        inner.innerHTML = buildHtml(j);
                    })
                    .catch(function () {
                        inner.textContent = 'No se pudo cargar el detalle.';
                    });
            });
        });
    })();
    </script>
    <?php endif; ?>
    <?php endif; ?>
</section>
