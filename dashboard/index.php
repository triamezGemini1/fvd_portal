<?php

declare(strict_types=1);

$fvdMaster = dirname(__DIR__) . '/fvdmasteradmin';

require_once dirname(__DIR__) . '/config/paths.php';
require_once $fvdMaster . '/config/db.php';
require_once $fvdMaster . '/services/AuthService.php';
require_once dirname(__DIR__) . '/src/Services/StatsService.php';

use FvdPortal\Services\StatsService;

AuthService::ensureSession();
AuthService::requireLogin();

if (AuthService::isAthletePortalUser()) {
    $base = rtrim((string) env('APP_BASE_PATH', '/fvd_portal'), '/');
    header('Location: ' . $base . '/fvdmasteradmin/atleta/mi_ficha.php');
    exit;
}

$fvd_page_title = 'Dashboard analítico';
$pdo = fvd_db();
$dash = StatsService::snapshotDashboard($pdo);
$ai = $dash['activos_inactivos'];

$dashYearRaw = isset($_GET['year']) ? (int) preg_replace('/\D/', '', (string) $_GET['year']) : (int) date('Y');
$dashYear = ($dashYearRaw >= 2000 && $dashYearRaw <= 2100) ? $dashYearRaw : (int) date('Y');
$inscGen = StatsService::inscripcionesPorGeneroPorTorneoAno($pdo, $dashYear);
$inscGenTorneos = $inscGen['torneos'];
$inscGenAnual = $inscGen['total_anual'];

$urlAtletas = fvd_crud_self_url('atletas');
if (!function_exists('admin_module_url')) {
    require_once dirname(__DIR__) . '/config/paths.php';
}
$statsTortaApiUrl = admin_module_url('stats_torta_api.php');

$lineLabels = [];
$lineValues = [];
$byMonth = [];
foreach ($dash['crecimiento_mensual'] as $row) {
    $byMonth[$row['mes']] = $row['cnt'];
}
$mesCorto = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
for ($i = 11; $i >= 0; --$i) {
    $ts = strtotime('-' . $i . ' months');
    $key = date('Y-m', $ts);
    $mi = (int) date('n', $ts) - 1;
    $lineLabels[] = $mesCorto[$mi] . ' ' . date('Y', $ts);
    $lineValues[] = isset($byMonth[$key]) ? (int) $byMonth[$key] : 0;
}

$torta = $dash['torta_asociacion'];
$chartPalette = ['#2e3092', '#fff200', '#be123c', '#3a3eb5', '#e8e9f4', '#6b7280', '#111827', '#f59e0b', '#10b981', '#6366f1'];

require $fvdMaster . '/includes/layout_header.php';
?>

<div class="fvd-dash fvd-dash-analytics dashboard-grid report-container">
    <h1>Dashboard analítico</h1>
    <p class="fvd-dash__intro fvd-dash-analytics__intro">
        Resumen de atletas y asociaciones según su perfil (FVD 80% · 15% · 5%).
    </p>

    <div class="fvd-metric-grid" aria-label="Métricas principales">
        <article class="fvd-metric-card fvd-metric-card--azul">
            <div class="fvd-metric-card__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="fvd-metric-card__body">
                <p class="fvd-metric-card__label">Atletas activos</p>
                <p class="fvd-metric-card__value"><?= number_format($ai['activos'], 0, ',', '.') ?></p>
                <p class="fvd-metric-card__hint">Estatus aprobado (FVD)</p>
            </div>
        </article>
        <article class="fvd-metric-card fvd-metric-card--amarillo">
            <div class="fvd-metric-card__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="fvd-metric-card__body">
                <p class="fvd-metric-card__label">Atletas inactivos / pendientes</p>
                <p class="fvd-metric-card__value"><?= number_format($ai['inactivos'], 0, ',', '.') ?></p>
                <p class="fvd-metric-card__hint">Fuera de estatus activo</p>
            </div>
        </article>
        <article class="fvd-metric-card fvd-metric-card--rojo">
            <div class="fvd-metric-card__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            </div>
            <div class="fvd-metric-card__body">
                <p class="fvd-metric-card__label">Movimiento 30 días</p>
                <p class="fvd-metric-card__value"><?= number_format($dash['ultimos_30_dias'], 0, ',', '.') ?></p>
                <p class="fvd-metric-card__hint">Fichas con actividad reciente</p>
            </div>
        </article>
        <article class="fvd-metric-card fvd-metric-card--azul-muted">
            <div class="fvd-metric-card__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
            <div class="fvd-metric-card__body">
                <p class="fvd-metric-card__label">Total atletas</p>
                <p class="fvd-metric-card__value"><?= number_format($ai['total'], 0, ',', '.') ?></p>
                <p class="fvd-metric-card__hint">En su ámbito</p>
            </div>
        </article>
    </div>

    <section class="fvd-dash-analytics__charts" aria-label="Gráficos">
        <div class="fvd-chart-panel">
            <h2 class="fvd-chart-panel__title">Atletas por asociación</h2>
            <div class="fvd-chart-panel__canvas-wrap">
                <canvas id="fvd-chart-torta" aria-label="Gráfico circular"></canvas>
            </div>
        </div>
        <div class="fvd-chart-panel">
            <h2 class="fvd-chart-panel__title">Crecimiento mensual</h2>
            <div class="fvd-chart-panel__canvas-wrap">
                <canvas id="fvd-chart-linea" aria-label="Gráfico de líneas"></canvas>
            </div>
        </div>
    </section>

    <section class="fvd-dash-gen" aria-labelledby="fvd-dash-gen-heading">
        <div class="fvd-dash-gen__head">
            <h2 id="fvd-dash-gen-heading" class="fvd-dash-gen__title">Inscripciones por género</h2>
            <form class="fvd-dash-gen__year-form" method="get" action="">
                <label for="fvd-dash-year">Año</label>
                <select id="fvd-dash-year" name="year" class="fvd-dash-gen__year-select" onchange="this.form.submit()">
                    <?php
                    $yCur = (int) date('Y');
                    for ($yy = $yCur - 6; $yy <= $yCur + 1; ++$yy):
                    ?>
                        <option value="<?= $yy ?>"<?= $yy === $dashYear ? ' selected' : '' ?>><?= $yy ?></option>
                    <?php endfor; ?>
                </select>
            </form>
        </div>
        <p class="fvd-dash-gen__hint">
            Torneos del año según fecha del torneo (<code>fechator</code> o, si falta, alta en sistema). Inscritos: atletas con <code>inscripcion = 1</code> y <code>torneo_id</code> coincidente. Género según ficha: M, F; resto u omitido en «Otros».
        </p>
        <div class="fvd-dash-gen__grid">
            <div class="fvd-chart-panel fvd-chart-panel--compact">
                <h3 class="fvd-chart-panel__title">Total <?= (int) $dashYear ?> (todos los torneos del año)</h3>
                <?php if ((int) $inscGenAnual['total'] > 0): ?>
                <div class="fvd-chart-panel__canvas-wrap fvd-chart-panel__canvas-wrap--sm">
                    <canvas id="fvd-chart-gen-ano" aria-label="Inscripciones por género, total anual"></canvas>
                </div>
                <?php else: ?>
                <p class="fvd-dash-gen__no-data">Sin inscripciones con desglose por género en este año (en su ámbito).</p>
                <?php endif; ?>
            </div>
            <div class="fvd-dash-gen__table-panel">
                <h3 class="fvd-dash-gen__table-h">Por torneo</h3>
                <div class="fvd-dash-gen__table-scroll">
                    <table class="fvd-dash-gen__table">
                        <thead>
                            <tr>
                                <th scope="col">Torneo</th>
                                <th scope="col">Fecha</th>
                                <th scope="col" class="fvd-dash-gen__num">M</th>
                                <th scope="col" class="fvd-dash-gen__num">F</th>
                                <th scope="col" class="fvd-dash-gen__num">Otros</th>
                                <th scope="col" class="fvd-dash-gen__num">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inscGenTorneos as $tg): ?>
                                <tr>
                                    <td class="fvd-dash-gen__torneo"><?= htmlspecialchars($tg['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= $tg['fecha'] !== null ? htmlspecialchars($tg['fecha'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                                    <td class="fvd-dash-gen__num"><?= (int) $tg['m'] ?></td>
                                    <td class="fvd-dash-gen__num"><?= (int) $tg['f'] ?></td>
                                    <td class="fvd-dash-gen__num"><?= (int) $tg['otros'] ?></td>
                                    <td class="fvd-dash-gen__num fvd-dash-gen__num--strong"><?= (int) $tg['total'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($inscGenTorneos === []): ?>
                                <tr>
                                    <td colspan="6" class="fvd-dash-gen__empty">No hay torneos con año <?= (int) $dashYear ?> en el calendario.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <?php if ($inscGenTorneos !== []): ?>
                            <tfoot>
                                <tr>
                                    <th scope="row" colspan="2">Total año</th>
                                    <td class="fvd-dash-gen__num"><?= (int) $inscGenAnual['m'] ?></td>
                                    <td class="fvd-dash-gen__num"><?= (int) $inscGenAnual['f'] ?></td>
                                    <td class="fvd-dash-gen__num"><?= (int) $inscGenAnual['otros'] ?></td>
                                    <td class="fvd-dash-gen__num fvd-dash-gen__num--strong"><?= (int) $inscGenAnual['total'] ?></td>
                                </tr>
                            </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <div class="fvd-dash-analytics__two-col">
        <section class="fvd-dash-analytics__top5" aria-labelledby="fvd-top5-heading">
            <h2 id="fvd-top5-heading">Top 5 asociaciones</h2>
            <ol class="fvd-top5-list">
                <?php foreach ($dash['top_asociaciones'] as $idx => $t): ?>
                    <li>
                        <span class="fvd-top5-list__rank"><?= $idx + 1 ?></span>
                        <span class="fvd-top5-list__name"><?= htmlspecialchars($t['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="fvd-top5-list__cnt"><?= (int) $t['cnt'] ?></span>
                    </li>
                <?php endforeach; ?>
                <?php if ($dash['top_asociaciones'] === []): ?>
                    <li class="fvd-top5-list--empty">Sin datos en el ámbito actual.</li>
                <?php endif; ?>
            </ol>
        </section>

        <section class="fvd-dash-analytics__recientes" aria-labelledby="fvd-rec-heading">
            <h2 id="fvd-rec-heading">Últimos atletas registrados</h2>
            <ul class="fvd-recientes-list">
                <?php foreach ($dash['ultimos_atletas'] as $u): ?>
                    <?php
                    $fotoFn = isset($u['foto']) ? trim((string) $u['foto']) : '';
                    $nomRaw = trim((string) ($u['nombre'] ?? ''));
                    $nom = htmlspecialchars($nomRaw, ENT_QUOTES, 'UTF-8');
                    $ced = htmlspecialchars((string) ($u['cedula'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $href = htmlspecialchars($urlAtletas . '?action=form&id=' . (int) $u['id'], ENT_QUOTES, 'UTF-8');
                    $iniL = '';
                    if ($nomRaw !== '') {
                        $iniL = function_exists('mb_substr') ? mb_substr($nomRaw, 0, 1, 'UTF-8') : substr($nomRaw, 0, 1);
                    }
                    ?>
                    <li>
                        <a class="fvd-recientes-list__link" href="<?= $href ?>">
                            <?php if ($fotoFn !== ''): ?>
                                <img class="atleta-img-preview" src="<?= htmlspecialchars(url('crud_atletas/uploads/' . ltrim($fotoFn, '/')), ENT_QUOTES, 'UTF-8') ?>" alt="" width="40" height="40" loading="lazy">
                            <?php else: ?>
                                <span class="atleta-img-preview atleta-img-preview--placeholder fvd-recientes-list__ph" title="<?= $nom ?>"><?= $iniL !== '' ? htmlspecialchars($iniL, ENT_QUOTES, 'UTF-8') : '—' ?></span>
                            <?php endif; ?>
                            <span class="fvd-recientes-list__meta">
                                <strong><?= $nom !== '' ? $nom : '—' ?></strong>
                                <span class="fvd-recientes-list__ced"><?= $ced !== '' ? $ced : '—' ?></span>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
                <?php if ($dash['ultimos_atletas'] === []): ?>
                    <li class="fvd-top5-list--empty">No hay registros recientes.</li>
                <?php endif; ?>
            </ul>
        </section>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script>
(function () {
    var torta = <?= json_encode($torta, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var lineLabels = <?= json_encode($lineLabels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var lineValues = <?= json_encode($lineValues, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var palette = <?= json_encode($chartPalette, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    var commonOpts = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: { color: '#e2e8f0', font: { size: 11 } }
            }
        }
    };

    var statsTortaUrl = <?= json_encode($statsTortaApiUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var elPie = document.getElementById('fvd-chart-torta');
    var pieChart = null;
    if (elPie && torta && torta.labels && torta.labels.length && typeof Chart !== 'undefined') {
        pieChart = new Chart(elPie, {
            type: 'pie',
            data: {
                labels: torta.labels,
                datasets: [{
                    data: torta.counts,
                    backgroundColor: torta.labels.map(function (_, i) {
                        return palette[i % palette.length];
                    }),
                    borderColor: 'rgba(15,23,42,0.35)',
                    borderWidth: 1
                }]
            },
            options: commonOpts
        });
    }

    function refreshTortaFromApi() {
        if (!pieChart || !statsTortaUrl) {
            return;
        }
        fetch(statsTortaUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.ok || !d.torta_asociacion) {
                    return;
                }
                var t = d.torta_asociacion;
                if (!t.labels || !t.counts) {
                    return;
                }
                pieChart.data.labels = t.labels;
                pieChart.data.datasets[0].data = t.counts;
                pieChart.data.datasets[0].backgroundColor = t.labels.map(function (_, i) {
                    return palette[i % palette.length];
                });
                pieChart.update();
            })
            .catch(function () {});
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            refreshTortaFromApi();
        }
    });

    var elLine = document.getElementById('fvd-chart-linea');
    if (elLine && lineLabels.length && typeof Chart !== 'undefined') {
        new Chart(elLine, {
            type: 'line',
            data: {
                labels: lineLabels,
                datasets: [{
                    label: 'Atletas',
                    data: lineValues,
                    borderColor: '#fff200',
                    backgroundColor: 'rgba(255,242,0,0.15)',
                    tension: 0.25,
                    fill: true,
                    pointBackgroundColor: '#2e3092',
                    pointBorderColor: '#fff200'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        ticks: { color: '#cbd5e1', maxRotation: 45, minRotation: 0, font: { size: 10 } },
                        grid: { color: 'rgba(255,255,255,0.06)' }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { color: '#cbd5e1', font: { size: 10 } },
                        grid: { color: 'rgba(255,255,255,0.06)' }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    var genAno = <?= json_encode([
        'm'     => (int) $inscGenAnual['m'],
        'f'     => (int) $inscGenAnual['f'],
        'otros' => (int) $inscGenAnual['otros'],
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var elGen = document.getElementById('fvd-chart-gen-ano');
    if (elGen && typeof Chart !== 'undefined') {
        var gm = genAno.m | 0;
        var gf = genAno.f | 0;
        var go = genAno.otros | 0;
        if (gm + gf + go > 0) {
            new Chart(elGen, {
                type: 'doughnut',
                data: {
                    labels: ['Masculino', 'Femenino', 'Otros'],
                    datasets: [{
                        data: [gm, gf, go],
                        backgroundColor: ['#2563eb', '#e11d48', '#64748b'],
                        borderColor: 'rgba(15,23,42,0.35)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: { color: '#e2e8f0', font: { size: 11 } }
                        }
                    }
                }
            });
        }
    }
})();
</script>

<?php
require $fvdMaster . '/includes/layout_footer.php';
