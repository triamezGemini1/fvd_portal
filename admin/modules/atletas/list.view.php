<?php
/** @var array{total:int,page:int,per_page:int,pages:int,rows:list} $result */
/** @var string $selfUrl */
/** @var string $cedula */
/** @var string $q */
/** @var bool $fvd_puede_traspaso */
/** @var string $atletasSearchApiUrl */
/** @var string $atletasExportUrl */
/** @var string $atletasReportBaseUrl */
/** @var string $fvd_atletas_pager_html */
/** @var bool $fvd_atletas_show_asociacion_col */
/** @var string $fvd_atletas_alcance */
/** @var string $fvd_atletas_tipo */
/** @var int $asociacionFiltroId */
/** @var array<string,mixed>|null $fvd_asociacion_header */
/** @var list<array<string,mixed>> $fvd_asociaciones_list_filter */
/** @var bool $fvd_atletas_puede_elegir_alcance true = admin FVD: selector federación / asociación con listado completo */
/** @var array<string, int|string> $fvd_atletas_widget */
$atletasFormNuevoUrl = fvd_return_append_to_url($selfUrl . '?action=form');
$fvd_atletas_widget = isset($fvd_atletas_widget) && is_array($fvd_atletas_widget) ? $fvd_atletas_widget : [
    'etiqueta' => '', 'total_atletas' => 0, 'total_afiliados' => 0,
    'sexo_m' => 0, 'sexo_f' => 0, 'sexo_sin' => 0, 'torneos' => 0, 'participacion' => 0,
];
$appBaseAtletas = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');
$fvd_url_solicitud_carnet_base = $appBaseAtletas !== '' ? $appBaseAtletas . '/fvdmasteradmin/solicitud_carnet.php' : '/fvdmasteradmin/solicitud_carnet.php';
$fvd_url_solicitud_traspaso_base = $appBaseAtletas !== '' ? $appBaseAtletas . '/fvdmasteradmin/solicitud_traspaso.php' : '/fvdmasteradmin/solicitud_traspaso.php';
$fvd_atletas_show_asociacion_col = $fvd_atletas_show_asociacion_col ?? true;
$fvd_atletas_alcance = $fvd_atletas_alcance ?? 'todos';
$fvd_atletas_tipo = $fvd_atletas_tipo ?? 'normal';
$asociacionFiltroId = isset($asociacionFiltroId) ? (int) $asociacionFiltroId : 0;
$fvd_asociaciones_list_filter = isset($fvd_asociaciones_list_filter) && is_array($fvd_asociaciones_list_filter) ? $fvd_asociaciones_list_filter : [];
$fvd_atletas_puede_elegir_alcance = $fvd_atletas_puede_elegir_alcance ?? false;
$nOpcionesAsoc = count($fvd_asociaciones_list_filter);
require_once FVD_PROJECT_ROOT . '/fvdmasteradmin/includes/fvd_asociacion_helpers.php';
$rolAtletasUi = trim((string) (\AuthService::role() ?? ''));
$fvdEsDelegadoAsocUi = $rolAtletasUi === \AuthService::ROLE_DELEGADO_ASOC;
$fvdRetornoUrl = '';
if (isset($_GET['ret']) && is_string($_GET['ret']) && fvd_return_sanitize($_GET['ret']) !== null) {
    $fvdRetornoUrl = (string) $_GET['ret'];
} elseif (isset($_GET['return']) && is_string($_GET['return']) && fvd_return_sanitize($_GET['return']) !== null) {
    $fvdRetornoUrl = (string) $_GET['return'];
} elseif ($fvdEsDelegadoAsocUi) {
    $fvdRetornoUrl = \AuthService::homeUrl();
}
$widgetStatsTitulo = $rolAtletasUi === \AuthService::ROLE_ASO_ADMIN
    ? 'Estadísticas'
    : ('Estadísticas — ' . htmlspecialchars((string) ($fvd_atletas_widget['etiqueta'] ?? ''), ENT_QUOTES, 'UTF-8'));
?>
<style>
#fvd-atletas-root .report-container.fvd-atletas-list-page {
    max-height: none;
    overflow: visible;
    padding-right: 0;
}
#fvd-atletas-root .fvd-mod-toolbar {
    position: sticky;
    top: 72px;
    z-index: 40;
    margin-bottom: 0 !important;
    border-radius: 12px 12px 0 0 !important;
    color: #000 !important;
    font-weight: 700 !important;
}
#fvd-atletas-root .fvd-atletas-list-scroll {
    max-height: min(72vh, calc(100vh - 200px));
    overflow-x: auto;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    border: 1px solid rgba(46, 48, 146, 0.32);
    border-top: none;
    border-radius: 0 0 12px 12px;
    background: #fff;
}
#fvd-atletas-root .fvd-atletas-list-scroll .fvd-mod-table-wrap {
    margin-top: 0;
    border-top-left-radius: 0;
    border-top-right-radius: 0;
    overflow: visible;
    border: 0;
    box-shadow: none;
}
#fvd-atletas-root .fvd-atletas-view-seg--in-toolbar {
    margin: 0 !important;
    width: 100%;
    flex-basis: 100%;
    padding-top: 6px;
    border-top: 1px solid rgba(46, 48, 146, 0.22);
}
#fvd-atletas-root .fvd-atletas-view-seg--in-toolbar .fvd-atletas-seg__btn {
    border-color: rgba(46, 48, 146, 0.38) !important;
    background: #fff !important;
    color: #0f172a !important;
}
#fvd-atletas-root .fvd-atletas-view-seg--in-toolbar .fvd-atletas-seg__btn--on {
    border-color: #2e3092 !important;
    background: rgba(255, 242, 0, 0.22) !important;
    color: #1e1b4b !important;
}
#fvd-atletas-root .fvd-atletas-live-hint--in-toolbar {
    width: 100%;
    flex-basis: 100%;
    margin: 2px 0 0 !important;
    min-height: 0;
    font-size: 0.72rem;
    line-height: 1.25;
}
#fvd-atletas-root #fvd-tabla-atletas thead th {
    position: sticky;
    top: 0;
    z-index: 5;
    background: #e2e8f0 !important;
    color: #000 !important;
    font-weight: 800 !important;
    box-shadow: inset 0 -1px 0 #94a3b8;
}
#fvd-atletas-root #fvd-tabla-atletas tbody tr:nth-child(even) {
    background: #f8fafc;
}
#fvd-atletas-root #fvd-tabla-atletas tbody tr:nth-child(odd) {
    background: #ffffff;
}
#fvd-atletas-root .fvd-mod-toolbar label,
#fvd-atletas-root .fvd-mod-toolbar input,
#fvd-atletas-root .fvd-mod-toolbar select,
#fvd-atletas-root .fvd-mod-toolbar button,
#fvd-atletas-root .fvd-mod-toolbar a,
#fvd-atletas-root .fvd-atletas-export__label {
    color: #000 !important;
    font-weight: 700 !important;
}
#fvd-atletas-root .fvd-mod-toolbar .fvd-input {
    background: #fff !important;
    border-color: rgba(46, 48, 146, 0.32) !important;
    color: #0f172a !important;
    font-weight: 700 !important;
}
#fvd-atletas-root #fvd-tabla-atletas tbody td,
#fvd-atletas-root #fvd-tabla-atletas tbody td * {
    color: #000 !important;
    font-weight: 700 !important;
}
/* Estadísticas: mitad de altura aprox., ancho completo para alinear todos los KPIs */
.fvd-atletas-widget--strip {
    width: 100%;
    max-width: none;
    box-sizing: border-box;
    margin: 0 0 8px !important;
    padding: 5px 8px !important;
    border-radius: 8px;
    border: 1px solid var(--fvd-border, #334155);
    background: linear-gradient(135deg, rgba(46, 48, 146, 0.25) 0%, rgba(15, 23, 42, 0.6) 100%);
}
.fvd-atletas-widget--strip .fvd-atletas-widget__title {
    margin: 0 0 3px !important;
    font-size: 0.72rem !important;
    font-weight: 700;
    line-height: 1.15;
    color: var(--fvd-amarillo, #fff200);
}
.fvd-atletas-widget--strip .fvd-atletas-widget__row {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    align-items: stretch;
}
.fvd-atletas-widget--strip .fvd-atletas-asoc-head {
    flex: 0 1 auto;
    min-width: min(10.5rem, 100%);
    padding: 3px 6px !important;
    gap: 5px !important;
    min-height: 0 !important;
    border-radius: 6px !important;
}
.fvd-atletas-widget--strip .fvd-atletas-asoc-head__logo img {
    max-height: 26px !important;
    max-width: 48px !important;
}
.fvd-atletas-widget--strip .fvd-atletas-asoc-head__meta {
    font-size: 0.62rem !important;
    line-height: 1.15 !important;
}
.fvd-atletas-widget--strip .fvd-atletas-kpi-grid {
    flex: 1 1 18rem;
    min-width: 0;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(6.25rem, 1fr));
    gap: 5px;
    align-content: stretch;
}
@media (min-width: 900px) {
    .fvd-atletas-widget--strip .fvd-atletas-kpi-grid {
        grid-template-columns: repeat(5, minmax(0, 1fr));
    }
}
.fvd-atletas-widget--strip .fvd-atletas-kpi {
    padding: 3px 6px !important;
    border-radius: 6px !important;
    background: rgba(0, 0, 0, 0.2) !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
}
.fvd-atletas-widget--strip .fvd-atletas-kpi__label {
    color: var(--fvd-muted);
    font-size: 0.55rem !important;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    line-height: 1.1;
}
.fvd-atletas-widget--strip .fvd-atletas-kpi__value {
    font-size: 0.95rem !important;
    font-weight: 800;
    line-height: 1.15;
}
.fvd-atletas-widget--strip .fvd-atletas-kpi__value--inline {
    font-size: 0.72rem !important;
    font-weight: 700;
    line-height: 1.2;
}
</style>
<h1 class="fvd-atletas-title">Atletas</h1>
<?php if ($fvdEsDelegadoAsocUi): ?>
<p class="no-print" style="margin:0 0 .85rem">
    <a href="<?= htmlspecialchars(\AuthService::homeUrl(), ENT_QUOTES, 'UTF-8') ?>"
       class="inline-flex items-center text-black font-bold border-2 border-black px-4 py-2 rounded hover:bg-black hover:text-white transition-colors"
       style="display:inline-flex;align-items:center;gap:.45rem;color:#000;font-weight:800;border:2px solid #000;padding:.5rem .9rem;border-radius:.45rem;text-decoration:none;transition:all .15s ease">
        <i class="fas fa-arrow-left mr-2"></i> VOLVER AL PANEL
    </a>
</p>
<?php endif; ?>

<section class="fvd-atletas-widget fvd-atletas-widget--strip no-print" aria-label="Estadísticas del contexto">
    <h2 class="fvd-atletas-widget__title"><?= $widgetStatsTitulo ?></h2>
    <div class="fvd-atletas-widget__row">
        <?php
        if (is_array($fvd_asociacion_header ?? null) && ($fvd_asociacion_header['id'] ?? 0) > 0):
            $ahLogo = isset($fvd_asociacion_header['logo']) ? fvd_asociacion_logo_public_url($appBaseAtletas !== '' ? $appBaseAtletas : '', FVD_PROJECT_ROOT, (string) $fvd_asociacion_header['logo']) : null;
            $ahDelegado = trim((string) ($fvd_asociacion_header['delegado'] ?? ''));
            $ahNombre = trim((string) ($fvd_asociacion_header['nombre'] ?? ''));
            $ahHasLogo = $ahLogo !== null && $ahLogo !== '';
            if ($ahHasLogo || $ahNombre !== '' || $ahDelegado !== ''):
            ?>
        <div class="fvd-atletas-asoc-head no-print" style="display:flex;align-items:center;margin:0;border:1px solid rgba(255,255,255,.18);background:rgba(0,0,0,.22)">
            <?php if ($ahHasLogo): ?>
                <div class="fvd-atletas-asoc-head__logo" style="flex-shrink:0"><img src="<?= htmlspecialchars($ahLogo, ENT_QUOTES, 'UTF-8') ?>" alt="" style="object-fit:contain"></div>
            <?php endif; ?>
            <div class="fvd-atletas-asoc-head__meta" style="color:#e2e8f0;min-width:0">
                <?php if ($ahNombre !== ''): ?>
                    <div style="font-weight:800;opacity:.95;word-break:break-word"><?= htmlspecialchars($ahNombre, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if ($ahDelegado !== ''): ?>
                    <div style="font-weight:700;margin-top:1px"><span style="opacity:.85">Delegado</span> <?= htmlspecialchars($ahDelegado, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
            endif;
        endif; ?>
        <div class="fvd-atletas-kpi-grid">
        <div class="fvd-atletas-kpi">
            <div class="fvd-atletas-kpi__label">Atletas</div>
            <div class="fvd-atletas-kpi__value" style="color:#e2e8f0"><?= number_format((int) ($fvd_atletas_widget['total_atletas'] ?? 0), 0, ',', '.') ?></div>
        </div>
        <div class="fvd-atletas-kpi">
            <div class="fvd-atletas-kpi__label">Afiliados (marc.)</div>
            <div class="fvd-atletas-kpi__value" style="color:#86efac"><?= number_format((int) ($fvd_atletas_widget['total_afiliados'] ?? 0), 0, ',', '.') ?></div>
        </div>
        <div class="fvd-atletas-kpi">
            <div class="fvd-atletas-kpi__label">Género M / F / —</div>
            <div class="fvd-atletas-kpi__value fvd-atletas-kpi__value--inline" style="color:#e2e8f0">
                <?= (int) ($fvd_atletas_widget['sexo_m'] ?? 0) ?> / <?= (int) ($fvd_atletas_widget['sexo_f'] ?? 0) ?> / <?= (int) ($fvd_atletas_widget['sexo_sin'] ?? 0) ?>
            </div>
        </div>
        <div class="fvd-atletas-kpi">
            <div class="fvd-atletas-kpi__label">Torneos</div>
            <div class="fvd-atletas-kpi__value" style="color:#3a3eb5"><?= number_format((int) ($fvd_atletas_widget['torneos'] ?? 0), 0, ',', '.') ?></div>
        </div>
        <div class="fvd-atletas-kpi">
            <div class="fvd-atletas-kpi__label">Participación</div>
            <div class="fvd-atletas-kpi__value" style="color:#fcd34d"><?= number_format((int) ($fvd_atletas_widget['participacion'] ?? 0), 0, ',', '.') ?></div>
        </div>
        </div>
    </div>
</section>
<div id="fvd-atletas-root" class="fvd-atletas-root">

<div class="report-container fvd-atletas-list-page">
<?php if (!empty($fvd_error ?? '')): ?><p class="fvd-mod-msg" style="margin:0 0 8px"><?= htmlspecialchars((string) $fvd_error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

<div class="fvd-mod-toolbar no-print" style="flex-wrap:wrap;align-items:flex-end;gap:10px;padding:12px 14px;border-radius:12px;border:1px solid rgba(46,48,146,.28);border-top:3px solid #fff200;background:#f8fafc;box-shadow:0 8px 18px rgba(15,23,42,.12)">
    <form method="get" action="" class="no-print" id="fvd-atletas-filter-form" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;padding:10px;border-radius:10px;border:1px solid rgba(46,48,146,.22);background:#ffffff">
        <input type="hidden" name="action" value="list">
        <?php if (isset($_GET['ret']) && is_string($_GET['ret']) && fvd_return_sanitize($_GET['ret']) !== null): ?>
        <input type="hidden" name="ret" value="<?= htmlspecialchars($_GET['ret'], ENT_QUOTES, 'UTF-8') ?>">
        <?php elseif (isset($_GET['return']) && is_string($_GET['return']) && fvd_return_sanitize($_GET['return']) !== null): ?>
        <input type="hidden" name="return" value="<?= htmlspecialchars($_GET['return'], ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <?php if ($fvd_atletas_puede_elegir_alcance): ?>
        <div>
            <label style="font-size:.8125rem;color:var(--fvd-muted);display:block;font-weight:600">Alcance</label>
            <select id="fvd-atletas-alcance" class="fvd-input" name="alcance" style="max-width:16rem">
                <option value="todos"<?= $fvd_atletas_alcance === 'todos' ? ' selected' : '' ?>>Todos (toda la federación)</option>
                <option value="asociacion"<?= $fvd_atletas_alcance === 'asociacion' ? ' selected' : '' ?>>Una asociación concreta…</option>
            </select>
        </div>
        <div id="fvd-atletas-asoc-wrap" class="no-print" style="display:<?= $fvd_atletas_alcance === 'asociacion' ? 'block' : 'none' ?>">
            <label for="fvd-atletas-asoc-id" style="font-size:.8125rem;color:var(--fvd-muted);display:block;font-weight:600">¿Qué asociación?</label>
            <select id="fvd-atletas-asoc-id" class="fvd-input" name="asociacion_id" style="min-width:min(22rem, 92vw);max-width:28rem"<?= $fvd_atletas_alcance === 'asociacion' ? ' required' : '' ?> aria-label="Asociación a listar">
                <option value="0"><?= $fvd_atletas_alcance === 'asociacion' ? '— Seleccione asociación —' : '— (active «Una asociación» arriba) —' ?></option>
                <?php foreach ($fvd_asociaciones_list_filter as $aso): ?>
                    <?php $aidOpt = (int) ($aso['id'] ?? 0); if ($aidOpt <= 0) { continue; } ?>
                    <option value="<?= $aidOpt ?>"<?= $asociacionFiltroId === $aidOpt ? ' selected' : '' ?> title="<?= htmlspecialchars((string) ($aso['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($aso['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($nOpcionesAsoc === 0): ?>
                <p class="fvd-mod-msg no-print" style="font-size:.78rem;margin:6px 0 0">No hay asociaciones registradas. Cree asociaciones en el módulo correspondiente para poder filtrar.</p>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <input type="hidden" name="alcance" value="todos">
        <?php endif; ?>
        <div>
            <label style="font-size:.8125rem;color:var(--fvd-muted);display:block;font-weight:600">Tipo de listado</label>
            <select id="fvd-atletas-tipo" class="fvd-input" name="tipo" style="max-width:16rem">
                <option value="normal"<?= $fvd_atletas_tipo === 'normal' ? ' selected' : '' ?>>Listado general</option>
                <option value="ultimos"<?= $fvd_atletas_tipo === 'ultimos' ? ' selected' : '' ?>>Últimos afiliados</option>
                <option value="no_activos"<?= $fvd_atletas_tipo === 'no_activos' ? ' selected' : '' ?>>No activos (pendientes)</option>
                <option value="bajas"<?= $fvd_atletas_tipo === 'bajas' ? ' selected' : '' ?>>Dados de baja</option>
            </select>
        </div>
        <div>
            <label style="font-size:.8125rem;color:var(--fvd-muted);display:block;font-weight:600">Cédula</label>
            <input id="fvd-atleta-cedula" class="fvd-input" type="search" name="cedula" value="<?= htmlspecialchars($cedula, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej. 30399011" style="max-width:11rem" autocomplete="off">
        </div>
        <div>
            <label style="font-size:.8125rem;color:var(--fvd-muted);display:block">Nombre</label>
            <input id="fvd-atleta-q" class="fvd-input" type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Contiene…" style="max-width:12rem">
        </div>
        <button type="submit" class="fvd-input" style="width:auto;padding:6px 12px">Buscar</button>
        <a href="<?= htmlspecialchars(fvd_return_preserve_query_params($selfUrl . '?action=list'), ENT_QUOTES, 'UTF-8') ?>" class="fvd-input" style="width:auto;padding:6px 12px;display:inline-flex;align-items:center;text-decoration:none;box-sizing:border-box">Limpiar</a>
    </form>
    <?php if ($fvdRetornoUrl !== ''): ?>
    <a href="<?= htmlspecialchars($fvdRetornoUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-input no-print" style="width:auto;padding:8px 12px;display:inline-flex;align-items:center;text-decoration:none;box-sizing:border-box;border-color:#000;background:#fff;color:#000;font-weight:800">
        ← Retornar
    </a>
    <?php endif; ?>
    <?php if (!$fvdEsDelegadoAsocUi): ?>
    <div class="fvd-atletas-report-links no-print" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
        <?php
        if (!function_exists('admin_module_url')) {
            require_once FVD_PROJECT_ROOT . '/config/paths.php';
        }
        $repBase = isset($atletasReportBaseUrl) && is_string($atletasReportBaseUrl) && $atletasReportBaseUrl !== ''
            ? $atletasReportBaseUrl
            : admin_module_url('atletas/');
        $uRepAfiliacion = fvd_return_append_to_url($repBase . 'reporte_indicadores.php?marcador=afiliacion');
        $uRepSolCarnet = fvd_return_append_to_url($repBase . 'reporte_carnets.php');
        $uRepAfilAnual = fvd_return_append_to_url($repBase . 'reporte_indicadores.php?marcador=afiliacion_anualidad');
        $uRepTr = fvd_return_append_to_url($repBase . 'reporte_traspasos.php');
        ?>
        <nav class="fvd-atletas-informes-nav no-print" aria-label="Informes y reportes" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:10px 12px;border-radius:10px;border:1px solid rgba(250,204,21,.38);background:rgba(15,23,42,.55);box-shadow:0 1px 0 rgba(255,255,255,.06) inset">
        <span class="fvd-atletas-informes-nav__label" style="font-size:.82rem;font-weight:800;color:var(--fvd-amarillo,#facc15);letter-spacing:.06em;text-transform:uppercase">Informes</span>
        <a class="fvd-atletas-informes-nav__link" style="font-size:.88rem;font-weight:700;color:#f8fafc;text-decoration:none;padding:7px 13px;border-radius:8px;border:1px solid rgba(148,163,184,.5);background:rgba(30,41,59,.75);display:inline-flex;align-items:center;box-sizing:border-box;line-height:1.2" href="<?= htmlspecialchars($uRepAfiliacion, ENT_QUOTES, 'UTF-8') ?>" title="Filas con atletas.afiliacion = 1">Afiliación</a>
        <a class="fvd-atletas-informes-nav__link" style="font-size:.88rem;font-weight:700;color:#f8fafc;text-decoration:none;padding:7px 13px;border-radius:8px;border:1px solid rgba(148,163,184,.5);background:rgba(30,41,59,.75);display:inline-flex;align-items:center;box-sizing:border-box;line-height:1.2" href="<?= htmlspecialchars($uRepSolCarnet, ENT_QUOTES, 'UTF-8') ?>" title="Solo filas con atletas.carnet = 1 (el 0 no es indicador de informe)">Solicitud carnets</a>
        <a class="fvd-atletas-informes-nav__link" style="font-size:.88rem;font-weight:700;color:#f8fafc;text-decoration:none;padding:7px 13px;border-radius:8px;border:1px solid rgba(148,163,184,.5);background:rgba(30,41,59,.75);display:inline-flex;align-items:center;box-sizing:border-box;line-height:1.2" href="<?= htmlspecialchars($uRepAfilAnual, ENT_QUOTES, 'UTF-8') ?>" title="Afiliación y anualidad en 1 (atletas.afiliacion = 1 y atletas.anualidad = 1)">Afiliación y anualidad</a>
        <a class="fvd-atletas-informes-nav__link" style="font-size:.88rem;font-weight:700;color:#f8fafc;text-decoration:none;padding:7px 13px;border-radius:8px;border:1px solid rgba(148,163,184,.5);background:rgba(30,41,59,.75);display:inline-flex;align-items:center;box-sizing:border-box;line-height:1.2" href="<?= htmlspecialchars($uRepTr, ENT_QUOTES, 'UTF-8') ?>" title="Historial de traspasos; marcador atletas.traspaso = 1">Traspasos</a>
        </nav>
    </div>
    <?php endif; ?>
    <div class="fvd-atletas-export no-print" role="group" aria-label="Exportar listado">
        <span class="fvd-atletas-export__label" style="font-size:.75rem;color:var(--fvd-muted);display:block;margin-bottom:4px;font-weight:600">Exportar</span>
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
            <button type="button" class="fvd-input" id="fvd-export-csv" style="width:auto;padding:6px 12px">Excel (CSV)</button>
            <button type="button" class="fvd-input" id="fvd-export-pdf" style="width:auto;padding:6px 12px">PDF</button>
        </div>
    </div>
    <a href="<?= htmlspecialchars($atletasFormNuevoUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-btn-primary no-print" style="text-decoration:none;box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center">Nuevo atleta</a>
    <div class="fvd-atletas-view-seg fvd-atletas-view-seg--in-toolbar no-print" role="tablist" aria-label="Vista de columnas">
        <button type="button" class="fvd-atletas-seg__btn fvd-atletas-seg__btn--on" data-fvd-atletas-view="basic" role="tab">Vista básica (contacto)</button>
        <button type="button" class="fvd-atletas-seg__btn" data-fvd-atletas-view="tech" role="tab">Vista técnica (FVD)</button>
    </div>
    <p id="fvd-atletas-live-hint" class="fvd-atletas-live-hint fvd-atletas-live-hint--in-toolbar no-print" aria-live="polite"></p>
</div>

<div class="fvd-atletas-list-scroll">

<div class="fvd-mod-table-wrap">
    <table id="fvd-tabla-atletas" class="fvd-mod-table fvd-mod-table--nowrap tabla-atletas fvd-atletas-view--basic<?= $fvd_atletas_show_asociacion_col ? '' : ' fvd-atletas-table--no-asoc-col' ?>" data-atletas-view="basic" data-show-asoc-col="<?= $fvd_atletas_show_asociacion_col ? '1' : '0' ?>">
        <thead>
        <tr>
            <th class="fvd-col-id">ID</th>
            <th class="fvd-col-foto">Foto</th>
            <th class="fvd-col-ced">Cédula</th>
            <th class="fvd-col-nom">Nombre</th>
            <?php if ($fvd_atletas_show_asociacion_col): ?>
            <th class="fvd-col-asoc">Asociación</th>
            <?php endif; ?>
            <th class="fvd-col-contact fvd-col-cel">Celular</th>
            <th class="fvd-col-tech fvd-col-sexo">Sexo</th>
            <th class="fvd-col-tech fvd-col-numfvd">Nº FVD</th>
            <th class="fvd-col-tech fvd-col-categ">Categ.</th>
            <th class="fvd-col-tech fvd-col-estatus">Estatus</th>
            <th class="fvd-col-actions"></th>
        </tr>
        </thead>
        <tbody>
        <?php
        $atletaRowTpl = FVD_PROJECT_ROOT . '/templates/components/atleta_table_row.php';
        foreach ($result['rows'] as $r):
            require $atletaRowTpl;
        endforeach;
        ?>
        <?php if ($result['rows'] === []): ?>
            <tr><td colspan="<?= $fvd_atletas_show_asociacion_col ? '11' : '10' ?>" style="padding:12px">Sin registros con los filtros actuales.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

</div>

<?= $fvd_atletas_pager_html ?? '' ?>

</div>

</div>

<script>
(function () {
    var apiUrl = <?= json_encode($atletasSearchApiUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var exportUrl = <?= json_encode($atletasExportUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var cedulaEl = document.getElementById('fvd-atleta-cedula');
    var qEl = document.getElementById('fvd-atleta-q');
    var table = document.getElementById('fvd-tabla-atletas');
    var tbody = table ? table.querySelector('tbody') : null;
    var pager = document.getElementById('fvd-atletas-pager');
    var hint = document.getElementById('fvd-atletas-live-hint');
    var fetchPage = function () {};

    function applyAtletasTableView(mode) {
        if (!table) {
            return;
        }
        table.classList.remove('fvd-atletas-view--basic', 'fvd-atletas-view--tech');
        table.classList.add(mode === 'tech' ? 'fvd-atletas-view--tech' : 'fvd-atletas-view--basic');
        table.setAttribute('data-atletas-view', mode);
        document.querySelectorAll('[data-fvd-atletas-view]').forEach(function (b) {
            var on = b.getAttribute('data-fvd-atletas-view') === mode;
            b.classList.toggle('fvd-atletas-seg__btn--on', on);
        });
    }

    document.querySelectorAll('[data-fvd-atletas-view]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            applyAtletasTableView(btn.getAttribute('data-fvd-atletas-view'));
        });
    });

    if (!cedulaEl || !qEl) {
        return;
    }

    function buildExportUrl(format) {
        if (!exportUrl) {
            return '';
        }
        var sep = exportUrl.indexOf('?') >= 0 ? '&' : '?';
        var alcEl = document.getElementById('fvd-atletas-alcance');
        var tipoEl = document.getElementById('fvd-atletas-tipo');
        var asEl = document.getElementById('fvd-atletas-asoc-id');
        var alc = alcEl ? alcEl.value : 'todos';
        var tipo = tipoEl ? tipoEl.value : 'normal';
        var aid = asEl ? String(asEl.value || '0') : '0';
        return exportUrl + sep + 'format=' + encodeURIComponent(format)
            + '&cedula=' + encodeURIComponent(cedulaEl.value.trim())
            + '&q=' + encodeURIComponent(qEl.value.trim())
            + '&alcance=' + encodeURIComponent(alc)
            + '&tipo=' + encodeURIComponent(tipo)
            + '&asociacion_id=' + encodeURIComponent(aid);
    }

    if (apiUrl && tbody && pager && table) {
        var debounceMs = 300;
        var timer = null;

        function setLoading(on) {
            if (on) {
                table.classList.add('is-loading');
                if (hint) {
                    hint.textContent = 'Cargando…';
                }
            } else {
                table.classList.remove('is-loading');
                if (hint) {
                    hint.textContent = '';
                }
            }
        }

        function buildQuery(page) {
            var p = new URLSearchParams();
            p.set('page', String(page));
            p.set('cedula', cedulaEl.value.trim());
            p.set('q', qEl.value.trim());
            var alcEl = document.getElementById('fvd-atletas-alcance');
            var tipoEl = document.getElementById('fvd-atletas-tipo');
            var asEl = document.getElementById('fvd-atletas-asoc-id');
            if (alcEl) {
                p.set('alcance', alcEl.value);
            }
            if (tipoEl) {
                p.set('tipo', tipoEl.value);
            }
            if (asEl) {
                p.set('asociacion_id', asEl.value || '0');
            }
            return p.toString();
        }

        fetchPage = function (page) {
            setLoading(true);
            var url = apiUrl + (apiUrl.indexOf('?') >= 0 ? '&' : '?') + buildQuery(page);
            fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    setLoading(false);
                    if (!data || !data.ok) {
                        if (hint) {
                            hint.textContent = 'No se pudieron cargar los datos.';
                        }
                        return;
                    }
                    tbody.innerHTML = data.tbody_html;
                    pager.innerHTML = data.pager_html;
                    applyAtletasTableView(table.getAttribute('data-atletas-view') || 'basic');
                })
                .catch(function () {
                    setLoading(false);
                    if (hint) {
                        hint.textContent = 'Error de red. Intente de nuevo.';
                    }
                });
        };

        function scheduleFetch() {
            if (timer) {
                clearTimeout(timer);
            }
            timer = setTimeout(function () {
                timer = null;
                fetchPage(1);
            }, debounceMs);
        }

        cedulaEl.addEventListener('input', scheduleFetch);
        qEl.addEventListener('input', scheduleFetch);

        var alcEl = document.getElementById('fvd-atletas-alcance');
        var tipoEl = document.getElementById('fvd-atletas-tipo');
        var asEl = document.getElementById('fvd-atletas-asoc-id');
        var filtForm = document.getElementById('fvd-atletas-filter-form');
        var asocWrap = document.getElementById('fvd-atletas-asoc-wrap');
        function submitFilterForm() {
            if (filtForm) {
                filtForm.submit();
            }
        }
        function syncAsocUi() {
            if (!alcEl || !asocWrap || !asEl) {
                return;
            }
            var on = alcEl.value === 'asociacion';
            asocWrap.style.display = on ? 'block' : 'none';
            asEl.required = on;
            if (!on) {
                asEl.setCustomValidity('');
            }
        }
        if (alcEl && asocWrap && asEl) {
            syncAsocUi();
            alcEl.addEventListener('change', syncAsocUi);
        }
        if (tipoEl) {
            tipoEl.addEventListener('change', submitFilterForm);
        }
        if (asEl) {
            asEl.addEventListener('change', submitFilterForm);
        }

        pager.addEventListener('click', function (e) {
            var t = e.target;
            if (!t || !t.closest) {
                return;
            }
            var a = t.closest('a[data-fvd-page]');
            if (!a) {
                return;
            }
            e.preventDefault();
            var np = parseInt(a.getAttribute('data-fvd-page'), 10);
            if (!isNaN(np) && np >= 1) {
                fetchPage(np);
            }
        });
    }

    var btnCsv = document.getElementById('fvd-export-csv');
    var btnPdf = document.getElementById('fvd-export-pdf');
    if (btnCsv) {
        btnCsv.addEventListener('click', function () {
            var u = buildExportUrl('csv');
            if (u) {
                window.location.href = u;
            }
        });
    }
    if (btnPdf) {
        btnPdf.addEventListener('click', function () {
            var u = buildExportUrl('pdf');
            if (u) {
                window.location.href = u;
            }
        });
    }
})();
</script>
