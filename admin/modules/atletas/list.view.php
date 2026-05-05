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
/** @var string $fvd_atletas_marcador */
$fvd_atletas_marcador = isset($fvd_atletas_marcador) ? trim((string) $fvd_atletas_marcador) : '';
$atletasFormNuevoUrl = fvd_return_append_to_url($selfUrl . '?action=form');
$fvd_atletas_widget = isset($fvd_atletas_widget) && is_array($fvd_atletas_widget) ? $fvd_atletas_widget : [
    'etiqueta' => '', 'total_atletas' => 0, 'total_afiliados' => 0,
    'sexo_m' => 0, 'sexo_f' => 0, 'sexo_sin' => 0, 'torneos' => 0, 'participacion' => 0,
];
if (!function_exists('url')) {
    require_once FVD_PROJECT_ROOT . '/config/paths.php';
}
if (!function_exists('fvd_master_embed_active')) {
    require_once FVD_PROJECT_ROOT . '/config/fvd_navigation_return.php';
}
$fvdAtletasListHideTitle = fvd_master_embed_active()
    || !empty($GLOBALS['fvd_delegado_suppress_inner_page_heading'] ?? null);
$fvd_url_solicitud_carnet_base = url('fvdmasteradmin/solicitud_carnet.php');
$fvd_url_solicitud_traspaso_base = url('fvdmasteradmin/solicitud_traspaso.php');
$fvd_atletas_show_asociacion_col = $fvd_atletas_show_asociacion_col ?? true;
$fvd_atletas_alcance = $fvd_atletas_alcance ?? 'todos';
$fvd_atletas_tipo = $fvd_atletas_tipo ?? 'normal';
$asociacionFiltroId = isset($asociacionFiltroId) ? (int) $asociacionFiltroId : 0;
$fvd_asociaciones_list_filter = isset($fvd_asociaciones_list_filter) && is_array($fvd_asociaciones_list_filter) ? $fvd_asociaciones_list_filter : [];
$fvd_atletas_puede_elegir_alcance = $fvd_atletas_puede_elegir_alcance ?? false;
$nOpcionesAsoc = count($fvd_asociaciones_list_filter);
/** Base URL pública (p. ej. /fvd_portal) para logos de asociación; antes se usaba sin definir. */
$appBaseAtletas = isset($appBaseAtletas) && is_string($appBaseAtletas)
    ? $appBaseAtletas
    : rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');
require_once FVD_PROJECT_ROOT . '/fvdmasteradmin/includes/fvd_asociacion_helpers.php';
$rolAtletasUi = trim((string) (\AuthService::role() ?? ''));
$fvdEsDelegadoAsocUi = $rolAtletasUi === \AuthService::ROLE_DELEGADO_ASOC;
$fvdAtletasListEmbedBrand = !$fvdEsDelegadoAsocUi
    && function_exists('fvd_master_embed_active')
    && fvd_master_embed_active();
$fvd_delegado_solicitud_una_api_url = isset($fvd_delegado_solicitud_una_api_url) ? (string) $fvd_delegado_solicitud_una_api_url : '';
$fvd_atletas_delegado_line = $fvdEsDelegadoAsocUi;
$fvdPanelDelegadoUrl = '';
if ($fvdEsDelegadoAsocUi) {
    $pdu = \AuthService::delegadoPanelHomeUrl();
    $fvdPanelDelegadoUrl = ($pdu !== null && $pdu !== '') ? $pdu : url('fvdmasteradmin/delegado_dashboard_new.php');
}
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
$fvdMovimientosSolicitados = [];
foreach (($result['rows'] ?? []) as $rwMov) {
    if (!is_array($rwMov)) {
        continue;
    }
    $docMov = (int) ($rwMov['numfvd'] ?? 0) > 0
        ? (string) ((int) $rwMov['numfvd'])
        : trim((string) ($rwMov['cedula'] ?? ''));
    $nomMov = trim((string) ($rwMov['nombre'] ?? ''));
    $ops = [
        'carnet' => 'Carnet solicitado',
        'traspaso' => 'Traspaso solicitado',
        'afiliacion' => 'Afiliación solicitada',
        'anualidad' => 'Anualidad solicitada',
        'inscripcion' => 'Inscripción solicitada',
    ];
    foreach ($ops as $campoOp => $labelOp) {
        if ((int) ($rwMov[$campoOp] ?? 0) !== 1) {
            continue;
        }
        $nfMov = (int) ($rwMov['numfvd'] ?? 0);
        $fvdMovimientosSolicitados[] = [
            'numfvd' => $nfMov > 0 ? (string) $nfMov : "\xE2\x80\x94",
            'doc'    => $docMov !== '' ? $docMov : "\xE2\x80\x94",
            'nombre' => $nomMov !== '' ? $nomMov : "\xE2\x80\x94",
            'operacion' => $labelOp,
        ];
    }
}
?>
<style>
#fvd-atletas-root .report-container.fvd-atletas-list-page {
    max-height: none;
    overflow: visible;
    padding-right: 0;
    margin-top: 0;
    border-radius: 0 0 12px 12px;
}
/* Panel superior (KPI + filtros): sticky; la barra interna ya no lleva sticky propio */
#fvd-atletas-root .fvd-atletas-sticky-panel {
    position: sticky;
    top: 72px;
    z-index: 45;
    margin: 0 0 10px;
    padding-bottom: 2px;
    background: linear-gradient(180deg, #f1f5f9 0%, #f8fafc 100%);
    border-radius: 12px;
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.1);
}
#fvd-atletas-root .fvd-atletas-sticky-panel .fvd-atletas-widget--strip {
    margin: 0 0 8px !important;
    border-radius: 10px 10px 0 0;
}
#fvd-atletas-root .fvd-atletas-sticky-panel--embed-brand {
    background: linear-gradient(180deg, #e8eaf6 0%, #f1f5f9 100%);
    border: 1px solid rgba(46, 48, 146, 0.32);
    box-shadow: 0 8px 22px rgba(30, 27, 75, 0.12);
}
.fvd-atletas-widget__title-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    justify-content: space-between;
    gap: 10px 14px;
    margin: 0 0 6px;
    width: 100%;
    box-sizing: border-box;
}
.fvd-atletas-stats-asoc {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 0 1 auto;
    min-width: min(11.5rem, 46vw);
}
.fvd-atletas-stats-asoc__label {
    font-size: 0.72rem;
    font-weight: 700;
    color: #e2e8f0;
}
.fvd-atletas-stats-asoc__select {
    max-width: min(14rem, 46vw);
    min-width: 9rem;
    box-sizing: border-box;
}
.fvd-atletas-stats-asoc__empty {
    font-size: 0.65rem;
    margin: 0;
    color: #fecaca;
}
#fvd-atletas-root .fvd-atletas-widget--strip.fvd-atletas-widget--fvd-identity {
    background: linear-gradient(160deg, #2e3092 0%, #252876 46%, #1a1850 100%) !important;
    border: 1px solid rgba(255, 242, 0, 0.48) !important;
    box-shadow: 0 10px 28px rgba(30, 27, 75, 0.42);
}
#fvd-atletas-root .fvd-atletas-widget--fvd-identity .fvd-atletas-widget__title {
    color: #fff200 !important;
    text-shadow: 0 1px 0 rgba(0, 0, 0, 0.35);
    font-weight: 900;
    letter-spacing: 0.02em;
    margin: 0 !important;
}
#fvd-atletas-root .fvd-atletas-widget--fvd-identity .fvd-atletas-stats-asoc__label {
    color: rgba(254, 249, 195, 0.95) !important;
    font-size: 0.62rem !important;
    text-transform: uppercase;
    letter-spacing: 0.07em;
}
#fvd-atletas-root .fvd-atletas-widget--fvd-identity .fvd-atletas-stats-asoc__select {
    border: 2px solid rgba(255, 242, 0, 0.7) !important;
    background: #ffffff !important;
    color: #0f172a !important;
    font-weight: 800 !important;
    border-radius: 8px;
}
#fvd-atletas-root .fvd-atletas-widget--fvd-identity .fvd-atletas-kpi-grid {
    grid-template-columns: repeat(auto-fit, minmax(4.1rem, 1fr));
    gap: 6px;
}
@media (min-width: 900px) {
    #fvd-atletas-root .fvd-atletas-widget--fvd-identity .fvd-atletas-kpi-grid {
        grid-template-columns: repeat(5, minmax(0, 5rem));
    }
}
#fvd-atletas-root .fvd-atletas-widget--fvd-identity .fvd-atletas-kpi {
    max-width: 5.25rem;
    margin: 0 auto;
    width: 100%;
    padding: 4px 4px !important;
    border-radius: 8px !important;
    border: 1px solid rgba(255, 242, 0, 0.3) !important;
    background: rgba(0, 0, 0, 0.28) !important;
    box-sizing: border-box;
    text-align: center;
}
#fvd-atletas-root .fvd-atletas-widget--fvd-identity .fvd-atletas-kpi__label {
    color: #fef9c3 !important;
    font-size: 0.5rem !important;
    line-height: 1.1;
}
#fvd-atletas-root .fvd-atletas-widget--fvd-identity .fvd-atletas-kpi__value {
    font-size: 0.78rem !important;
    font-weight: 900 !important;
    color: #ffffff !important;
}
#fvd-atletas-root .fvd-atletas-widget--fvd-identity .fvd-atletas-kpi__value--inline {
    font-size: 0.62rem !important;
}
#fvd-atletas-root.fvd-atletas-root--fvd-embed-brand .report-container.fvd-atletas-list-page {
    background: linear-gradient(180deg, #eef2ff 0%, #f8fafc 55%, #ffffff 100%);
    border: 1px solid rgba(46, 48, 146, 0.22);
    border-top: none;
}
#fvd-atletas-root.fvd-atletas-root--fvd-embed-brand #fvd-tabla-atletas thead th {
    background: linear-gradient(180deg, #2e3092 0%, #232876 100%) !important;
    color: #fefce8 !important;
    box-shadow: inset 0 -2px 0 #fff200 !important;
}
#fvd-atletas-root.fvd-atletas-root--fvd-embed-brand #fvd-tabla-atletas tbody tr:nth-child(even) {
    background: rgba(238, 242, 255, 0.55);
}
#fvd-atletas-root.fvd-atletas-root--fvd-embed-brand #fvd-tabla-atletas tbody tr:nth-child(odd) {
    background: #ffffff;
}
#fvd-atletas-root.fvd-atletas-root--fvd-embed-brand #fvd-tabla-atletas tbody td,
#fvd-atletas-root.fvd-atletas-root--fvd-embed-brand #fvd-tabla-atletas tbody td * {
    color: #0f172a !important;
}
#fvd-atletas-root .fvd-atletas-sticky-panel .fvd-mod-toolbar {
    position: static;
    top: auto;
    z-index: auto;
    margin-bottom: 0 !important;
    margin-top: 0 !important;
    border-radius: 0 0 12px 12px !important;
    color: #000 !important;
    font-weight: 700 !important;
}
#fvd-atletas-root .fvd-mod-toolbar {
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
#fvd-atletas-root .fvd-atletas-list-layout {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-list-layout > .fvd-atletas-delegado-list-solicitudes-row {
    flex: 1 1 auto;
    min-height: 0;
}
#fvd-atletas-root .fvd-atletas-table-wrap {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}
#fvd-atletas-root #fvd-tabla-atletas {
    width: 100%;
    min-width: 100%;
    table-layout: auto;
}
#fvd-atletas-root #fvd-tabla-atletas td.fvd-col-ident,
#fvd-atletas-root #fvd-tabla-atletas th.fvd-col-ident {
    white-space: normal;
    vertical-align: middle;
    min-width: min(100%, 12rem);
}
#fvd-atletas-root #fvd-tabla-atletas .fvd-col-ident__link {
    display: inline-flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 0.35rem;
    text-decoration: none;
    color: inherit;
    line-height: 1.25;
}
#fvd-atletas-root #fvd-tabla-atletas .fvd-col-ident__ced {
    display: inline;
    font-weight: 800;
    white-space: nowrap;
}
#fvd-atletas-root #fvd-tabla-atletas .fvd-col-ident__sep {
    color: #64748b;
    font-weight: 700;
    user-select: none;
}
#fvd-atletas-root #fvd-tabla-atletas .fvd-col-ident__nom {
    display: inline;
    font-weight: 700;
    word-break: break-word;
    min-width: 0;
}
#fvd-atletas-root #fvd-tabla-atletas .fvd-col-tech.fvd-col-categ,
#fvd-atletas-root #fvd-tabla-atletas th.fvd-col-categ {
    text-align: right;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
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
#fvd-atletas-root .fvd-dd-torneos-strip--toolbar {
    width: 100%;
    flex-basis: 100%;
    margin: 0 0 4px;
    padding: 8px 10px;
    box-sizing: border-box;
    border-radius: 10px;
    border: 1px solid rgba(46, 48, 146, 0.22);
    background: #fff;
    color: #0f172a;
}
#fvd-atletas-root .fvd-dd-torneos-strip--toolbar .fvd-dd-torneos-strip__list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: stretch;
    justify-content: center;
    max-height: 9rem;
    overflow-y: auto;
}
#fvd-atletas-root .fvd-dd-torneos-strip--toolbar .fvd-dd-torneo-chip {
    flex: 0 1 auto;
    min-width: 0;
    text-decoration: none;
    text-align: left;
    padding: 0.4rem 0.55rem;
    border-radius: 8px;
    border: 2px solid #e2e8f0;
    background: #f8fafc;
    color: #0f172a;
    font-size: 0.75rem;
    font-weight: 700;
    line-height: 1.25;
    box-sizing: border-box;
}
#fvd-atletas-root .fvd-dd-torneos-strip--toolbar .fvd-dd-torneo-chip:hover {
    border-color: rgba(46, 48, 146, 0.45);
}
#fvd-atletas-root .fvd-dd-torneos-strip--toolbar .fvd-dd-torneo-chip--activo {
    border-color: #2e3092;
    background: linear-gradient(135deg, #2e3092 0%, #3a3eb5 100%);
    color: #fff;
}
#fvd-atletas-root .fvd-dd-torneos-strip--toolbar .fvd-dd-torneo-chip__id {
    display: block;
    font-size: 0.58rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    margin-bottom: 0.08rem;
}
#fvd-atletas-root .fvd-dd-torneos-strip--toolbar .fvd-dd-torneo-chip--activo .fvd-dd-torneo-chip__id {
    color: rgba(255, 242, 0, 0.95);
}
#fvd-atletas-root .fvd-dd-torneos-strip--toolbar .fvd-dd-torneo-chip__name {
    word-break: break-word;
}
#fvd-atletas-root .fvd-dd-torneos-strip--toolbar .fvd-dd-torneo-chip__genero {
    display: inline-block;
    margin-top: 0.15rem;
    font-size: 0.58rem;
    font-weight: 700;
    color: #2e3092;
    border: 1px solid rgba(46, 48, 146, 0.25);
    border-radius: 4px;
    padding: 0.06rem 0.28rem;
    background: #eef2ff;
}
#fvd-atletas-root .fvd-dd-torneos-strip--toolbar .fvd-dd-torneo-chip--activo .fvd-dd-torneo-chip__genero {
    color: #fef9c3;
    border-color: rgba(255, 255, 255, 0.4);
    background: rgba(255, 255, 255, 0.12);
}
#fvd-atletas-root .fvd-dd-torneos-strip--toolbar .fvd-dd-torneo-chip__meta {
    display: block;
    margin-top: 0.12rem;
    font-size: 0.6rem;
    font-weight: 600;
    color: #94a3b8;
}
#fvd-atletas-root .fvd-dd-torneos-strip--toolbar .fvd-dd-torneo-chip--activo .fvd-dd-torneo-chip__meta {
    color: rgba(248, 250, 252, 0.9);
}
#fvd-atletas-root .fvd-dd-torneos-strip--toolbar .fvd-dd-torneos-strip--empty {
    margin: 0;
    font-size: 0.78rem;
    font-weight: 600;
    color: #64748b;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd {
    --fvd-azul: #2e3092;
    --fvd-azul-osc: #232876;
    --fvd-amarillo: #fff200;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-mod-toolbar.fvd-atletas-toolbar--delegado-fvd {
    background: linear-gradient(165deg, #2e3092 0%, #232876 55%, #1a1c5e 100%);
    border: 2px solid rgba(255, 242, 0, 0.85);
    border-top-width: 4px;
    border-top-color: var(--fvd-amarillo);
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.35);
    color: #f8fafc;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-mod-toolbar.fvd-atletas-toolbar--delegado-fvd form#fvd-atletas-filter-form {
    background: #f8fafc !important;
    border: 2px solid rgba(255, 242, 0, 0.55) !important;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.18);
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-mod-toolbar.fvd-atletas-toolbar--delegado-fvd form#fvd-atletas-filter-form label {
    color: #1e1b4b !important;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-delegado-toolbar-main {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 10px;
    width: 100%;
    flex-basis: 100%;
    box-sizing: border-box;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-delegado-toolbar-main #fvd-atletas-filter-form.fvd-atletas-filter-form--delegado-en-fila {
    flex: 1 1 280px;
    min-width: min(100%, 240px);
    margin: 0;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-query-row--delegado-en-fila {
    width: auto !important;
    max-width: 100%;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-delegado-toolbar-main .fvd-atletas-panel-secundario {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    flex: 0 0 auto;
    width: auto;
    min-width: 10.5rem;
    box-sizing: border-box;
    padding: 10px 12px;
    border-radius: 10px;
    border: 2px solid rgba(255, 242, 0, 0.55);
    background: #f8fafc;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-panel-secundario__title {
    margin: 0 0 4px;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #2e3092;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-panel-secundario__row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-delegado-toolbar-main__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    flex: 0 0 auto;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-delegado-list-solicitudes-row {
    display: grid;
    grid-template-columns: minmax(0, 53%) minmax(0, 43%);
    gap: 12px;
    align-items: stretch;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}
@media (max-width: 960px) {
    #fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-delegado-list-solicitudes-row {
        grid-template-columns: 1fr;
    }
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-delegado-list-solicitudes-row > .fvd-atletas-list-scroll {
    min-width: 0;
    min-height: 0;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-solicitudes-toolbar {
    box-sizing: border-box;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    min-height: 0;
    height: 100%;
    padding: 8px 10px;
    border-radius: 10px;
    border: 2px solid rgba(255, 242, 0, 0.55);
    background: #f8fafc;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
    display: flex;
    flex-direction: column;
    align-self: stretch;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-solicitudes-toolbar__title {
    margin: 0 0 6px;
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #2e3092;
    flex-shrink: 0;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-solicitudes-toolbar__head {
    display: grid;
    grid-template-columns: minmax(3.25rem, 4rem) 1fr minmax(5.5rem, 1fr);
    gap: 4px 6px;
    font-size: 0.62rem;
    font-weight: 800;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding-bottom: 4px;
    border-bottom: 1px solid rgba(46, 48, 146, 0.2);
    flex-shrink: 0;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-solicitudes-toolbar__body {
    overflow: auto;
    flex: 1 1 auto;
    min-height: 0;
    padding-top: 4px;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-solicitudes-toolbar__line {
    display: grid;
    grid-template-columns: minmax(3.25rem, 4rem) 1fr minmax(5.5rem, 1fr);
    gap: 4px 6px;
    align-items: center;
    font-size: 0.72rem;
    line-height: 1.25;
    padding: 4px 0;
    border-bottom: 1px solid rgba(46, 48, 146, 0.1);
    color: #0f172a;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-solicitudes-toolbar__line:last-child {
    border-bottom: none;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-solicitudes-toolbar__nf {
    font-weight: 800;
    color: #1e1b4b;
    font-variant-numeric: tabular-nums;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-solicitudes-toolbar__nom {
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    min-width: 0;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-solicitudes-toolbar__op {
    font-size: 0.62rem;
    font-weight: 700;
    color: #1e3a5f;
    line-height: 1.2;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-solicitudes-toolbar__empty {
    margin: 0;
    font-size: 0.72rem;
    color: #64748b;
    font-weight: 600;
    padding: 4px 0;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-solicitudes-toolbar__hint {
    margin: 0 0 8px;
    font-size: 0.65rem;
    line-height: 1.35;
    color: #475569;
    font-weight: 600;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-solicitudes-toolbar__subhead {
    margin: 8px 0 6px;
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-btn-retornar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 14px;
    border-radius: 10px;
    border: 2px solid #0f172a;
    background: var(--fvd-amarillo);
    color: #0f172a !important;
    font-weight: 900;
    text-decoration: none;
    font-size: 0.8rem;
    box-shadow: 0 4px 0 #0f172a;
    letter-spacing: 0.02em;
    white-space: nowrap;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-btn-retornar:hover {
    filter: brightness(1.06);
    color: #0f172a !important;
}
.fvd-atletas-list-page--fvd-brand {
    border: 2px solid rgba(46, 48, 146, 0.35);
    border-radius: 14px;
    padding: 10px 12px 12px;
    box-sizing: border-box;
    background: linear-gradient(180deg, #eef2ff 0%, #f8fafc 38%, #ffffff 100%);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
}
.fvd-atletas-list-page--fvd-brand .fvd-atletas-list-scroll {
    border-color: rgba(46, 48, 146, 0.45);
    border-top: 3px solid #fff200;
}
.fvd-atletas-list-page--fvd-brand .fvd-atletas-list-scroll #fvd-tabla-atletas:not(.fvd-atletas-table--delegado-line) thead th {
    background: linear-gradient(180deg, #2e3092 0%, #232876 100%) !important;
    color: #fff !important;
    font-weight: 800 !important;
    border-bottom: 3px solid #fff200;
    box-shadow: none;
}
#fvd-atletas-root .fvd-mod-toolbar.fvd-atletas-toolbar--fvd-brand {
    border-color: rgba(46, 48, 146, 0.4) !important;
    border-top: 4px solid #fff200 !important;
    background: linear-gradient(188deg, #e8e9f4 0%, #f1f2fb 45%, #f8fafc 100%) !important;
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.12) !important;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-panel-secundario .fvd-input {
    background: #fff !important;
    border: 2px solid #2e3092 !important;
    color: #0f172a !important;
    font-weight: 700 !important;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-btn-nuevo {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 14px;
    border-radius: 8px;
    border: 2px solid #fde047;
    background: linear-gradient(135deg, #3a3eb5 0%, #2e3092 100%);
    color: #fff !important;
    font-weight: 800;
    text-decoration: none;
    font-size: 0.8rem;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd #fvd-tabla-atletas.fvd-atletas-table--delegado-line thead th {
    background: linear-gradient(180deg, #2e3092 0%, #232876 100%) !important;
    color: #fff !important;
    border-bottom: 3px solid var(--fvd-amarillo);
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd #fvd-tabla-atletas.fvd-atletas-table--delegado-line tbody tr.fvd-atleta-row--delegado-line td {
    vertical-align: middle;
    white-space: nowrap;
    font-size: 0.8125rem;
    line-height: 1.2;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd #fvd-tabla-atletas.fvd-atletas-table--delegado-line .fvd-dl-cednom {
    display: inline-flex;
    align-items: center;
    flex-wrap: nowrap;
    gap: 0.35rem;
    max-width: min(28rem, 52vw);
    overflow: hidden;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd #fvd-tabla-atletas.fvd-atletas-table--delegado-line .fvd-dl-cednom .fvd-dl-nom {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    min-width: 0;
    font-weight: 700;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd #fvd-tabla-atletas.fvd-atletas-table--delegado-line .fvd-dl-cednom .fvd-dl-ced {
    flex-shrink: 0;
    font-weight: 800;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd #fvd-tabla-atletas.fvd-atletas-table--delegado-line .fvd-dl-cednom-sep {
    flex-shrink: 0;
    opacity: 0.45;
    font-weight: 700;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd #fvd-tabla-atletas.fvd-atletas-table--delegado-line .fvd-dl-th-act,
#fvd-atletas-root.fvd-atletas-root--delegado-fvd #fvd-tabla-atletas.fvd-atletas-table--delegado-line td.fvd-dl-act {
    width: 1%;
    max-width: 9.5rem;
    padding-left: 4px;
    padding-right: 4px;
    box-sizing: border-box;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd #fvd-tabla-atletas.fvd-atletas-table--delegado-line .fvd-action-icons--delegado-line {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 4px;
    align-items: center;
    max-width: 100%;
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd #fvd-tabla-atletas.fvd-atletas-table--delegado-line .fvd-dl-foto .atleta-img-preview--dl {
    display: block;
    border-radius: 6px;
    border: 1px solid rgba(46, 48, 146, 0.35);
}
#fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-title {
    color: #1e1b4b;
    border-left: 5px solid var(--fvd-amarillo);
    padding-left: 10px;
}
section.fvd-atletas-widget--strip.fvd-atletas-widget--delegado-fvd {
    border: 2px solid rgba(255, 242, 0, 0.65) !important;
    background: linear-gradient(135deg, #2e3092 0%, #232876 55%, #1e1b4b 100%) !important;
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
/* Formulario FVD: contraste fuerte sobre fondo gris-azulado */
#fvd-atletas-root form#fvd-atletas-filter-form.fvd-atletas-filter-form--fvd-wrap {
    background: linear-gradient(180deg, #e2e8f0 0%, #f1f5f9 100%) !important;
    border: 2px solid #1e3a5f !important;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7), 0 4px 14px rgba(15, 23, 42, 0.1);
}
#fvd-atletas-root form#fvd-atletas-filter-form.fvd-atletas-filter-form--fvd-wrap .fvd-input,
#fvd-atletas-root form#fvd-atletas-filter-form.fvd-atletas-filter-form--fvd-wrap select.fvd-input,
#fvd-atletas-root form#fvd-atletas-filter-form.fvd-atletas-filter-form--fvd-wrap button.fvd-input {
    background: #fff !important;
    border: 2px solid #334155 !important;
    color: #020617 !important;
    font-weight: 700 !important;
    min-height: 2.65rem;
    box-sizing: border-box;
}
#fvd-atletas-root form#fvd-atletas-filter-form.fvd-atletas-filter-form--fvd-wrap a.fvd-input {
    border: 2px solid #475569 !important;
    background: #fff !important;
    color: #0f172a !important;
    font-weight: 800 !important;
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
/* Embebido en panel maestro (iframe): tabla usa todo el alto útil */
body.is-embedded main.fvd-main:has(#fvd-atletas-root) {
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
body.is-embedded #fvd-atletas-root {
    flex: 1 1 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
body.is-embedded #fvd-atletas-root .report-container.fvd-atletas-list-page {
    flex: 1 1 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
body.is-embedded #fvd-atletas-root .fvd-atletas-sticky-panel {
    top: 0;
    flex-shrink: 0;
}
body.is-embedded #fvd-atletas-root .fvd-atletas-sticky-panel .fvd-mod-toolbar {
    position: static;
    top: auto;
}
body.is-embedded #fvd-atletas-root .fvd-atletas-list-scroll {
    max-height: none;
    flex: 1 1 0;
    min-height: 0;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
}
body.is-embedded #fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-delegado-list-solicitudes-row {
    flex: 1 1 0;
    min-height: 0;
}
body.is-embedded #fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-delegado-list-solicitudes-row > .fvd-atletas-list-scroll {
    max-height: none;
    min-height: 0;
    overflow: auto;
}
body.is-embedded #fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-delegado-list-solicitudes-row .fvd-atletas-solicitudes-toolbar {
    max-height: none;
    min-height: 0;
    overflow: hidden;
}
body.is-embedded #fvd-atletas-root.fvd-atletas-root--delegado-fvd .fvd-atletas-delegado-list-solicitudes-row .fvd-atletas-solicitudes-toolbar__body {
    flex: 1 1 auto;
    overflow: auto;
    min-height: 0;
    max-height: none;
}
/* Fila de acciones: puede envolver; cédula+nombre van en .fvd-atletas-ident-row (una sola fila lógica) */
#fvd-atletas-root .fvd-atletas-query-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 8px;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    overflow-x: auto;
    overflow-y: visible;
    -webkit-overflow-scrolling: touch;
    padding-bottom: 2px;
    scrollbar-width: thin;
}
#fvd-atletas-root .fvd-atletas-query-row > div {
    flex-shrink: 0;
}
#fvd-atletas-root .fvd-atletas-informes-inline {
    display: inline-flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
    margin-left: 2px;
    padding-left: 6px;
    border-left: 1px solid rgba(46, 48, 146, 0.22);
}
#fvd-atletas-root .fvd-atletas-informes-inline__link {
    font-size: 0.66rem;
    font-weight: 800;
    color: #0f172a !important;
    text-decoration: none;
    padding: 5px 7px;
    border-radius: 6px;
    border: 1px solid rgba(46, 48, 146, 0.35);
    background: #fff;
    display: inline-flex;
    align-items: center;
    box-sizing: border-box;
    line-height: 1.15;
    white-space: nowrap;
    flex-shrink: 0;
}
#fvd-atletas-root .fvd-atletas-informes-inline__link:hover {
    border-color: #2e3092;
    background: rgba(255, 242, 0, 0.35);
}
#fvd-atletas-root .fvd-atletas-ident-row {
    display: inline-flex;
    flex-wrap: nowrap;
    align-items: flex-end;
    gap: 8px;
    flex-shrink: 0;
}
#fvd-atletas-root .fvd-atletas-export--toolbar-inline {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    flex-shrink: 0;
    margin-left: 4px;
    padding-left: 8px;
    border-left: 1px solid rgba(46, 48, 146, 0.22);
}
#fvd-atletas-root .fvd-atletas-export--toolbar-inline .fvd-atletas-export__btns {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
}
#fvd-atletas-root .fvd-atletas-nuevo-atleta--toolbar {
    flex-shrink: 0;
    align-self: flex-end;
    margin-left: 4px;
}
#fvd-atletas-root form#fvd-atletas-filter-form.fvd-atletas-filter-form--fvd-wrap .fvd-atletas-nuevo-atleta--toolbar.fvd-btn-primary {
    min-height: 2.65rem;
    padding: 0 14px;
    font-size: 0.8rem;
}
</style>
<?php if (empty($fvdAtletasListHideTitle)): ?>
<h1 class="fvd-atletas-title">Atletas</h1>
<?php endif; ?>
<div id="fvd-atletas-root" class="fvd-atletas-root<?= $fvdEsDelegadoAsocUi ? ' fvd-atletas-root--delegado-fvd' : '' ?><?= $fvdAtletasListEmbedBrand ? ' fvd-atletas-root--fvd-embed-brand' : '' ?>" data-fvd-atletas-marcador="<?= htmlspecialchars($fvd_atletas_marcador, ENT_QUOTES, 'UTF-8') ?>">
<div class="fvd-atletas-sticky-panel no-print<?= $fvdAtletasListEmbedBrand ? ' fvd-atletas-sticky-panel--embed-brand' : '' ?>">
<section class="fvd-atletas-widget fvd-atletas-widget--strip no-print<?= $fvdEsDelegadoAsocUi ? ' fvd-atletas-widget--delegado-fvd' : '' ?><?= $fvdAtletasListEmbedBrand ? ' fvd-atletas-widget--fvd-identity' : '' ?>" aria-label="Estadísticas del contexto">
    <div class="fvd-atletas-widget__title-row">
        <h2 class="fvd-atletas-widget__title"><?= $widgetStatsTitulo ?></h2>
        <?php if ($fvd_atletas_puede_elegir_alcance): ?>
        <div class="fvd-atletas-stats-asoc no-print">
            <label class="fvd-atletas-stats-asoc__label" for="fvd-atletas-asoc-id">Asociación</label>
            <select id="fvd-atletas-asoc-id" class="fvd-input fvd-atletas-stats-asoc__select" name="asociacion_id" form="fvd-atletas-filter-form" aria-label="Asociación o FVD — todas" onchange="var f=document.getElementById('fvd-atletas-filter-form');if(f){f.requestSubmit();}">
                <option value="0"<?= ($fvd_atletas_alcance !== 'asociacion' || $asociacionFiltroId <= 0) ? ' selected' : '' ?>>FVD — todas</option>
                <?php foreach ($fvd_asociaciones_list_filter as $aso): ?>
                    <?php $aidOpt = (int) ($aso['id'] ?? 0); if ($aidOpt <= 0) { continue; } ?>
                    <option value="<?= $aidOpt ?>"<?= ($fvd_atletas_alcance === 'asociacion' && $asociacionFiltroId === $aidOpt) ? ' selected' : '' ?> title="<?= htmlspecialchars((string) ($aso['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($aso['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($nOpcionesAsoc === 0): ?>
                <p class="fvd-atletas-stats-asoc__empty no-print">No hay asociaciones registradas.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="fvd-atletas-widget__row">
        <?php
        if (
            (function_exists('fvd_delegado_inner_heading_visible') ? fvd_delegado_inner_heading_visible() : true)
            && is_array($fvd_asociacion_header ?? null) && ($fvd_asociacion_header['id'] ?? 0) > 0
        ):
            $ahLogo = isset($fvd_asociacion_header['logo']) ? fvd_asociacion_logo_public_url($appBaseAtletas, FVD_PROJECT_ROOT, (string) $fvd_asociacion_header['logo']) : null;
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
<?php
$uRepAfiliacion = '';
$uRepSolCarnet = '';
$uRepAfilAnual = '';
$uRepTr = '';
if (!$fvdEsDelegadoAsocUi) {
    if (!function_exists('admin_module_url')) {
        require_once FVD_PROJECT_ROOT . '/config/paths.php';
    }
    $repBase = isset($atletasReportBaseUrl) && is_string($atletasReportBaseUrl) && $atletasReportBaseUrl !== ''
        ? $atletasReportBaseUrl
        : admin_module_url('atletas/');
    $qsRepAsoc = ($asociacionFiltroId > 0) ? '&asociacion_id=' . (int) $asociacionFiltroId : '';
    $qsRepAsocFirst = ($asociacionFiltroId > 0) ? '?asociacion_id=' . (int) $asociacionFiltroId : '';
    $uRepAfiliacion = fvd_return_append_to_url($repBase . 'reporte_indicadores.php?marcador=afiliacion' . $qsRepAsoc);
    $uRepSolCarnet = fvd_return_append_to_url($repBase . 'reporte_carnets.php' . $qsRepAsocFirst);
    $uRepAfilAnual = fvd_return_append_to_url($repBase . 'reporte_indicadores.php?marcador=afiliacion_anualidad' . $qsRepAsoc);
    $uRepTr = fvd_return_append_to_url($repBase . 'reporte_traspasos.php' . $qsRepAsocFirst);
}
?>
<div class="fvd-mod-toolbar fvd-atletas-toolbar-unified fvd-atletas-toolbar--fvd-brand no-print<?= $fvdEsDelegadoAsocUi ? ' fvd-atletas-toolbar--delegado-fvd' : '' ?>" style="flex-wrap:wrap;align-items:flex-end;gap:10px;padding:12px 14px;border-radius:12px;border:1px solid rgba(46,48,146,.28);border-top:3px solid #fff200;background:#f8fafc;box-shadow:0 8px 18px rgba(15,23,42,.12)">
    <?php if ($fvdEsDelegadoAsocUi): ?>
    <div class="fvd-atletas-torneo-center no-print" style="width:100%;flex-basis:100%;display:flex;justify-content:center;box-sizing:border-box;">
        <?php
        if (!function_exists('fvd_delegado_build_return_from_current_request')) {
            require_once FVD_PROJECT_ROOT . '/fvdmasteradmin/includes/fvd_delegado_internal_nav.php';
        }
        $fvd_delegado_torneo_strip_embed = true;
        require FVD_PROJECT_ROOT . '/fvdmasteradmin/includes/partials/delegado_torneos_context_strip.php';
        unset($fvd_delegado_torneo_strip_embed);
        ?>
    </div>
    <?php endif; ?>
    <?php if ($fvdEsDelegadoAsocUi): ?>
    <div class="fvd-atletas-delegado-toolbar-main no-print">
    <?php endif; ?>
    <?php
    $fvdAtletasFilterFormAction = isset($fvd_atletas_filter_form_action) && is_string($fvd_atletas_filter_form_action) && $fvd_atletas_filter_form_action !== ''
        ? $fvd_atletas_filter_form_action
        : '';
    ?>
    <form method="get" action="<?= htmlspecialchars($fvdAtletasFilterFormAction, ENT_QUOTES, 'UTF-8') ?>" class="no-print<?= $fvd_atletas_puede_elegir_alcance ? ' fvd-atletas-filter-form--fvd-wrap' : '' ?><?= $fvdEsDelegadoAsocUi ? ' fvd-atletas-filter-form--delegado-en-fila' : '' ?>" id="fvd-atletas-filter-form" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;padding:10px;border-radius:10px;border:1px solid rgba(46,48,146,.22);background:#ffffff">
        <input type="hidden" name="action" value="list">
        <input type="hidden" id="fvd-atletas-tipo" name="tipo" value="<?= htmlspecialchars($fvd_atletas_tipo, ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($fvd_atletas_marcador !== ''): ?>
        <input type="hidden" id="fvd-atletas-marcador-field" name="marcador" value="<?= htmlspecialchars($fvd_atletas_marcador, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <?php if (isset($_GET['ret']) && is_string($_GET['ret']) && fvd_return_sanitize($_GET['ret']) !== null): ?>
        <input type="hidden" name="ret" value="<?= htmlspecialchars($_GET['ret'], ENT_QUOTES, 'UTF-8') ?>">
        <?php elseif (isset($_GET['return']) && is_string($_GET['return']) && fvd_return_sanitize($_GET['return']) !== null): ?>
        <input type="hidden" name="return" value="<?= htmlspecialchars($_GET['return'], ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <?php
        /**
         * Sin esto, el GET del filtro pierde embedded y layout_header redirige al admin FVD al panel maestro
         * (iframe en blanco / sin módulo de atletas). Admin FVD siempre debe reenviar estos flags en el listado.
         */
        $fvdIncluirEmbedEnFiltro = (\AuthService::role() === \AuthService::ROLE_FVD_ADMIN)
            || (function_exists('fvd_master_embed_active') && fvd_master_embed_active());
        if ($fvdIncluirEmbedEnFiltro) : ?>
        <input type="hidden" name="embedded" value="1">
        <input type="hidden" name="fvd_master_embed" value="1">
        <?php endif; ?>
        <?php if (function_exists('fvd_master_panel_render_context_hiddens')) {
            fvd_master_panel_render_context_hiddens();
        } ?>
        <?php if (isset($_GET['torneo_id']) && (int) $_GET['torneo_id'] > 0) : ?>
        <input type="hidden" name="torneo_id" value="<?= (int) $_GET['torneo_id'] ?>">
        <?php endif; ?>
        <?php if (isset($_GET['campeonato_id']) && (int) $_GET['campeonato_id'] > 0) : ?>
        <input type="hidden" name="campeonato_id" value="<?= (int) $_GET['campeonato_id'] ?>">
        <?php endif; ?>
        <?php if ($fvd_atletas_puede_elegir_alcance): ?>
        <div class="fvd-atletas-fvd-fields">
        <div class="fvd-atletas-query-row" style="width:100%;box-sizing:border-box">
        <div class="fvd-atletas-ident-row">
        <div>
            <label style="font-size:.8125rem;color:var(--fvd-muted);display:block;font-weight:600">Cédula</label>
            <input id="fvd-atleta-cedula" class="fvd-input" type="search" name="cedula" value="<?= htmlspecialchars($cedula, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej. 30399011" aria-label="Cédula" autocomplete="off" style="max-width:9.5rem">
        </div>
        <div>
            <label style="font-size:.8125rem;color:var(--fvd-muted);display:block;font-weight:600">Nombre</label>
            <input id="fvd-atleta-q" class="fvd-input" type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Contiene…" aria-label="Nombre" autocomplete="off" style="max-width:10rem">
        </div>
        </div>
        <button type="submit" class="fvd-input" style="width:auto;padding:5px 10px;flex-shrink:0;font-size:.8rem">Buscar</button>
        <a href="<?= htmlspecialchars(fvd_return_preserve_query_params($selfUrl . '?action=list'), ENT_QUOTES, 'UTF-8') ?>" class="fvd-input" style="width:auto;padding:5px 10px;display:inline-flex;align-items:center;text-decoration:none;box-sizing:border-box;min-height:2.4rem;white-space:nowrap;flex-shrink:0;font-size:.8rem" aria-label="Limpiar filtros">Limpiar</a>
        <?php if (!$fvdEsDelegadoAsocUi && $uRepAfiliacion !== ''): ?>
        <span class="fvd-atletas-informes-inline no-print" role="group" aria-label="Accesos a reportes">
            <a class="fvd-atletas-informes-inline__link" href="<?= htmlspecialchars($uRepAfiliacion, ENT_QUOTES, 'UTF-8') ?>" title="Filas con atletas.afiliacion = 1">Afiliación</a>
            <a class="fvd-atletas-informes-inline__link" href="<?= htmlspecialchars($uRepSolCarnet, ENT_QUOTES, 'UTF-8') ?>" title="Solo filas con atletas.carnet = 1 (el 0 no es indicador de informe)">Carnets</a>
            <a class="fvd-atletas-informes-inline__link" href="<?= htmlspecialchars($uRepAfilAnual, ENT_QUOTES, 'UTF-8') ?>" title="Afiliación y anualidad en 1 (atletas.afiliacion = 1 y atletas.anualidad = 1)">Afil.+anual.</a>
            <a class="fvd-atletas-informes-inline__link" href="<?= htmlspecialchars($uRepTr, ENT_QUOTES, 'UTF-8') ?>" title="Historial de traspasos; marcador atletas.traspaso = 1">Traspasos</a>
        </span>
        <?php endif; ?>
        <div class="fvd-atletas-export fvd-atletas-export--toolbar-inline no-print" role="group" aria-label="Exportar listado">
            <span class="fvd-atletas-export__label" style="font-size:.72rem;color:var(--fvd-muted);display:block;font-weight:700">Exportar</span>
            <div class="fvd-atletas-export__btns">
                <button type="button" class="fvd-input" id="fvd-export-csv" style="width:auto;padding:6px 12px;font-size:.8rem">Excel (CSV)</button>
                <button type="button" class="fvd-input" id="fvd-export-pdf" style="width:auto;padding:6px 12px;font-size:.8rem">PDF</button>
            </div>
        </div>
        <a href="<?= htmlspecialchars($atletasFormNuevoUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-btn-primary fvd-atletas-nuevo-atleta--toolbar no-print" style="text-decoration:none;box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center">Nuevo atleta</a>
        </div>
        </div>
        <?php else: ?>
        <?php if ($fvdEsDelegadoAsocUi): ?>
        <input type="hidden" name="alcance" value="asociacion">
        <input type="hidden" name="asociacion_id" value="<?= (int) $asociacionFiltroId ?>">
        <?php else: ?>
        <input type="hidden" name="alcance" value="todos">
        <?php endif; ?>
        <div class="fvd-atletas-query-row<?= $fvdEsDelegadoAsocUi ? ' fvd-atletas-query-row--delegado-en-fila' : '' ?>" style="<?= $fvdEsDelegadoAsocUi ? '' : 'width:100%;' ?>box-sizing:border-box">
        <div class="fvd-atletas-ident-row">
        <div>
            <label style="font-size:.8125rem;color:var(--fvd-muted);display:block;font-weight:600">Cédula</label>
            <input id="fvd-atleta-cedula" class="fvd-input" type="search" name="cedula" value="<?= htmlspecialchars($cedula, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej. 30399011" style="max-width:9.5rem" autocomplete="off">
        </div>
        <div>
            <label style="font-size:.8125rem;color:var(--fvd-muted);display:block;font-weight:600">Nombre</label>
            <input id="fvd-atleta-q" class="fvd-input" type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Contiene…" style="max-width:10rem">
        </div>
        </div>
        <button type="submit" class="fvd-input" style="width:auto;padding:5px 10px;flex-shrink:0;font-size:.8rem">Buscar</button>
        <a href="<?= htmlspecialchars(fvd_return_preserve_query_params($selfUrl . '?action=list'), ENT_QUOTES, 'UTF-8') ?>" class="fvd-input" style="width:auto;padding:5px 10px;display:inline-flex;align-items:center;text-decoration:none;box-sizing:border-box;flex-shrink:0;font-size:.8rem">Limpiar</a>
        <?php if (!$fvdEsDelegadoAsocUi && $uRepAfiliacion !== ''): ?>
        <span class="fvd-atletas-informes-inline no-print" role="group" aria-label="Accesos a reportes">
            <a class="fvd-atletas-informes-inline__link" href="<?= htmlspecialchars($uRepAfiliacion, ENT_QUOTES, 'UTF-8') ?>" title="Filas con atletas.afiliacion = 1">Afiliación</a>
            <a class="fvd-atletas-informes-inline__link" href="<?= htmlspecialchars($uRepSolCarnet, ENT_QUOTES, 'UTF-8') ?>" title="Solo filas con atletas.carnet = 1 (el 0 no es indicador de informe)">Carnets</a>
            <a class="fvd-atletas-informes-inline__link" href="<?= htmlspecialchars($uRepAfilAnual, ENT_QUOTES, 'UTF-8') ?>" title="Afiliación y anualidad en 1 (atletas.afiliacion = 1 y atletas.anualidad = 1)">Afil.+anual.</a>
            <a class="fvd-atletas-informes-inline__link" href="<?= htmlspecialchars($uRepTr, ENT_QUOTES, 'UTF-8') ?>" title="Historial de traspasos; marcador atletas.traspaso = 1">Traspasos</a>
        </span>
        <?php endif; ?>
        <?php if (!$fvdEsDelegadoAsocUi): ?>
        <div class="fvd-atletas-export fvd-atletas-export--toolbar-inline no-print" role="group" aria-label="Exportar listado">
            <span class="fvd-atletas-export__label" style="font-size:.72rem;color:var(--fvd-muted);display:block;font-weight:700">Exportar</span>
            <div class="fvd-atletas-export__btns">
                <button type="button" class="fvd-input" id="fvd-export-csv" style="width:auto;padding:6px 12px;font-size:.8rem">Excel (CSV)</button>
                <button type="button" class="fvd-input" id="fvd-export-pdf" style="width:auto;padding:6px 12px;font-size:.8rem">PDF</button>
            </div>
        </div>
        <a href="<?= htmlspecialchars($atletasFormNuevoUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-btn-primary fvd-atletas-nuevo-atleta--toolbar no-print" style="text-decoration:none;box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center">Nuevo atleta</a>
        <?php endif; ?>
        </div>
        <?php endif; ?>
    </form>
    <?php if ($fvdEsDelegadoAsocUi): ?>
        <div class="fvd-atletas-panel-secundario no-print" aria-label="Exportar listado">
            <div class="fvd-atletas-panel-secundario__title">Exportar</div>
            <div class="fvd-atletas-panel-secundario__row">
                <button type="button" class="fvd-input" id="fvd-export-csv" style="width:auto;padding:6px 12px">Excel (CSV)</button>
                <button type="button" class="fvd-input" id="fvd-export-pdf" style="width:auto;padding:6px 12px">PDF</button>
            </div>
        </div>
        <div class="fvd-atletas-delegado-toolbar-main__actions no-print">
            <a href="<?= htmlspecialchars($fvdPanelDelegadoUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-atletas-btn-retornar no-print">← Retornar</a>
            <a href="<?= htmlspecialchars($atletasFormNuevoUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-atletas-btn-nuevo no-print">Nuevo afiliado</a>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($fvdRetornoUrl !== '' && !$fvdEsDelegadoAsocUi): ?>
    <a href="<?= htmlspecialchars($fvdRetornoUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-input no-print" style="width:auto;padding:8px 12px;display:inline-flex;align-items:center;text-decoration:none;box-sizing:border-box;border-color:#000;background:#fff;color:#000;font-weight:800">
        ← Retornar
    </a>
    <?php endif; ?>
    <p id="fvd-atletas-live-hint" class="fvd-atletas-live-hint fvd-atletas-live-hint--in-toolbar no-print" aria-live="polite"></p>
</div>

</div>
<div class="report-container fvd-atletas-list-page fvd-atletas-list-page--fvd-brand">
<?php if (!empty($fvd_error ?? '')): ?><p class="fvd-mod-msg" style="margin:0 0 8px"><?= htmlspecialchars((string) $fvd_error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php
$fvdMarcadorEtiquetas = [
    'carnet' => 'Solicitud de carnet (carnet = 1)',
    'traspaso' => 'Traspaso solicitado (traspaso = 1)',
    'afiliacion' => 'Afiliación solicitada (afiliación = 1)',
    'anualidad' => 'Anualidad (anualidad = 1)',
    'inscripcion' => 'Inscripción (inscripción = 1)',
];
if ($fvd_atletas_marcador !== '' && isset($fvdMarcadorEtiquetas[$fvd_atletas_marcador])): ?>
    <p class="fvd-mod-msg no-print" style="margin:0 0 10px;font-size:0.8125rem;border-left:4px solid #2e3092;padding:8px 10px;background:#eef2ff">
        <strong>Filtro activo:</strong> <?= htmlspecialchars($fvdMarcadorEtiquetas[$fvd_atletas_marcador], ENT_QUOTES, 'UTF-8') ?>.
        <?php if (\AuthService::isDelegadoAsociacion() && (int) (\AuthService::delegadoTorneoContextId() ?? 0) > 0): ?>
            Con el torneo en contexto, el listado respeta además el género del torneo (M / F / mixto) cuando aplica.
        <?php endif; ?>
    </p>
<?php endif; ?>

<div class="fvd-atletas-list-layout" style="display:flex;flex-direction:column;gap:12px;width:100%;max-width:100%;box-sizing:border-box;align-items:stretch">
<?php if ($fvdEsDelegadoAsocUi): ?>
<div class="fvd-atletas-delegado-list-solicitudes-row">
<?php endif; ?>
<div id="fvd-atletas-list-block" class="fvd-atletas-list-scroll">

<div class="fvd-mod-table-wrap fvd-atletas-table-wrap">
    <?php if ($fvdEsDelegadoAsocUi): ?>
    <table id="fvd-tabla-atletas" class="fvd-mod-table fvd-mod-table--nowrap tabla-atletas fvd-atletas-table--delegado-line" data-atletas-view="delegado-line" data-show-asoc-col="0">
        <thead>
        <tr>
            <th class="fvd-dl-th-num">Nº FVD</th>
            <th class="fvd-dl-th-foto">Foto</th>
            <th class="fvd-dl-th-cednom">Cédula / nombre</th>
            <th class="fvd-dl-th-sexo">Sexo</th>
            <th class="fvd-dl-th-est">Estatus</th>
            <th class="fvd-dl-th-act fvd-col-actions">Acciones</th>
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
            <tr><td colspan="6" style="padding:12px">Sin registros con los filtros actuales.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php else: ?>
    <table id="fvd-tabla-atletas" class="fvd-mod-table fvd-mod-table--nowrap tabla-atletas fvd-atletas-view--tech<?= $fvd_atletas_show_asociacion_col ? '' : ' fvd-atletas-table--no-asoc-col' ?>" data-atletas-view="tech" data-show-asoc-col="<?= $fvd_atletas_show_asociacion_col ? '1' : '0' ?>">
        <thead>
        <tr>
            <th class="fvd-col-id">ID</th>
            <th class="fvd-col-foto">Foto</th>
            <th class="fvd-col-ident">Cédula / nombre</th>
            <?php if ($fvd_atletas_show_asociacion_col): ?>
            <th class="fvd-col-asoc">Asociación</th>
            <?php endif; ?>
            <th class="fvd-col-tech fvd-col-sexo">Sexo</th>
            <th class="fvd-col-tech fvd-col-numfvd">Nº FVD</th>
            <th class="fvd-col-tech fvd-col-categ" title="Solo código numérico: 1 Libre, 2 Sub-18, 3 Sub-15, 4 Sub-12; 0 sin categoría">Categ.</th>
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
            <tr><td colspan="<?= $fvd_atletas_show_asociacion_col ? '9' : '8' ?>" style="padding:12px">Sin registros con los filtros actuales.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

</div>
<?php if ($fvdEsDelegadoAsocUi): ?>
        <div class="fvd-atletas-solicitudes-toolbar no-print" aria-label="Solicitudes en el listado actual">
            <div class="fvd-atletas-solicitudes-toolbar__title">Solicitudes</div>
            <p class="fvd-atletas-solicitudes-toolbar__hint">Carnet y Traspaso registran la solicitud ante la FVD, avisan al administrador y ocultan la fila en este listado hasta que recargue.</p>
            <div class="fvd-atletas-solicitudes-toolbar__subhead">Marcadores en el listado</div>
            <div class="fvd-atletas-solicitudes-toolbar__head">
                <span>Nº&nbsp;FVD</span>
                <span>Nombre</span>
                <span>Solicitud</span>
            </div>
            <div class="fvd-atletas-solicitudes-toolbar__body">
                <?php foreach ($fvdMovimientosSolicitados as $mvSol): ?>
                    <div class="fvd-atletas-solicitudes-toolbar__line">
                        <span class="fvd-atletas-solicitudes-toolbar__nf"><?= htmlspecialchars((string) ($mvSol['numfvd'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="fvd-atletas-solicitudes-toolbar__nom" title="<?= htmlspecialchars((string) ($mvSol['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($mvSol['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="fvd-atletas-solicitudes-toolbar__op"><?= htmlspecialchars((string) ($mvSol['operacion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if ($fvdMovimientosSolicitados === []): ?>
                    <p class="fvd-atletas-solicitudes-toolbar__empty">Sin marcadores en este listado.</p>
                <?php endif; ?>
            </div>
        </div>
</div>
<?php endif; ?>
<?php if (!$fvdEsDelegadoAsocUi): ?>
<aside class="no-print" style="border:1px solid rgba(46,48,146,.26);border-radius:10px;background:#fff;padding:10px;max-height:min(72vh, calc(100vh - 200px));overflow:auto">
    <h3 style="margin:0 0 .4rem;font-size:.86rem;color:#0f172a">Movimientos solicitados</h3>
    <p style="margin:0 0 .6rem;font-size:.72rem;color:#475569;line-height:1.35">Resumen de atletas con solicitudes activas en el listado actual.</p>
    <table class="fvd-mod-table" style="font-size:.72rem">
        <thead>
        <tr>
            <th>Carnet/Cédula</th>
            <th>Nombre</th>
            <th>Operación</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($fvdMovimientosSolicitados as $mv): ?>
            <tr>
                <td><?= htmlspecialchars((string) ($mv['doc'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($mv['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($mv['operacion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($fvdMovimientosSolicitados === []): ?>
            <tr><td colspan="3" style="padding:10px">Sin movimientos solicitados en este filtro.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</aside>
<?php endif; ?>
</div>

<?= $fvd_atletas_pager_html ?? '' ?>

</div>

</div>

<script>
(function () {
    var fvdDelegadoLine = <?= $fvdEsDelegadoAsocUi ? 'true' : 'false' ?>;
    var fvdAtletasSpaParent = <?= !empty($fvd_atletas_spa_fragment) ? 'true' : 'false' ?>;
    var apiUrl = <?= json_encode($atletasSearchApiUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var exportUrl = <?= json_encode($atletasExportUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var filtFormEarly = document.getElementById('fvd-atletas-filter-form');
    var fvdListaSinBusquedaEnVivo = filtFormEarly && filtFormEarly.classList.contains('fvd-atletas-filter-form--fvd-wrap');
    var cedulaEl = document.getElementById('fvd-atleta-cedula');
    var qEl = document.getElementById('fvd-atleta-q');
    function cedulaVal() {
        return cedulaEl && cedulaEl.value ? String(cedulaEl.value).trim() : '';
    }
    function qVal() {
        return qEl && qEl.value ? String(qEl.value).trim() : '';
    }
    function alcanceFromAsocSelect() {
        var alcEl = document.getElementById('fvd-atletas-alcance');
        if (alcEl) {
            return alcEl.value;
        }
        var asEl = document.getElementById('fvd-atletas-asoc-id');
        if (asEl && String(asEl.value || '0') !== '0') {
            return 'asociacion';
        }
        return 'todos';
    }
    var table = document.getElementById('fvd-tabla-atletas');
    var tbody = table ? table.querySelector('tbody') : null;
    var pager = document.getElementById('fvd-atletas-pager');
    var hint = document.getElementById('fvd-atletas-live-hint');
    var fetchPage = function () {};

    function applyAtletasTableView(mode) {
        if (!table || fvdDelegadoLine) {
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

    if (!fvdDelegadoLine) {
        document.querySelectorAll('[data-fvd-atletas-view]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                applyAtletasTableView(btn.getAttribute('data-fvd-atletas-view'));
            });
        });
    }

    function buildExportUrl(format) {
        if (!exportUrl) {
            return '';
        }
        var sep = exportUrl.indexOf('?') >= 0 ? '&' : '?';
        var tipoEl = document.getElementById('fvd-atletas-tipo');
        var asEl = document.getElementById('fvd-atletas-asoc-id');
        var alc = alcanceFromAsocSelect();
        var hidAlc = document.querySelector('#fvd-atletas-filter-form input[name="alcance"][type="hidden"]');
        var hidAid = document.querySelector('#fvd-atletas-filter-form input[name="asociacion_id"][type="hidden"]');
        if (hidAlc && hidAlc.value) {
            alc = String(hidAlc.value);
        }
        var tipo = tipoEl ? tipoEl.value : 'ultimos';
        var aid = asEl ? String(asEl.value || '0') : '0';
        if ((!asEl || aid === '0') && hidAid && hidAid.value) {
            aid = String(hidAid.value || '0');
        }
        var marEl = document.getElementById('fvd-atletas-marcador-field');
        var mar = marEl && marEl.value ? String(marEl.value).trim() : '';
        var marQs = mar !== '' ? '&marcador=' + encodeURIComponent(mar) : '';
        return exportUrl + sep + 'format=' + encodeURIComponent(format)
            + '&cedula=' + encodeURIComponent(cedulaVal())
            + '&q=' + encodeURIComponent(qVal())
            + '&alcance=' + encodeURIComponent(alc)
            + '&tipo=' + encodeURIComponent(tipo)
            + '&asociacion_id=' + encodeURIComponent(aid)
            + marQs;
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
            p.set('cedula', cedulaVal());
            p.set('q', qVal());
            var alcEl = document.getElementById('fvd-atletas-alcance');
            var tipoEl = document.getElementById('fvd-atletas-tipo');
            var asEl = document.getElementById('fvd-atletas-asoc-id');
            var hidAlc2 = document.querySelector('#fvd-atletas-filter-form input[name="alcance"][type="hidden"]');
            var hidAid2 = document.querySelector('#fvd-atletas-filter-form input[name="asociacion_id"][type="hidden"]');
            var alcVal = alcEl ? alcEl.value : alcanceFromAsocSelect();
            if (hidAlc2 && hidAlc2.value) {
                alcVal = String(hidAlc2.value);
            }
            p.set('alcance', alcVal);
            if (tipoEl) {
                p.set('tipo', tipoEl.value);
            }
            if (asEl) {
                p.set('asociacion_id', asEl.value || '0');
            } else if (hidAid2 && hidAid2.value) {
                p.set('asociacion_id', String(hidAid2.value || '0'));
            }
            var marEl2 = document.getElementById('fvd-atletas-marcador-field');
            if (marEl2 && marEl2.value) {
                p.set('marcador', String(marEl2.value).trim());
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
                    if (!fvdDelegadoLine) {
                        applyAtletasTableView(table.getAttribute('data-atletas-view') || 'tech');
                    }
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

        if (!fvdListaSinBusquedaEnVivo) {
            if (cedulaEl) {
                cedulaEl.addEventListener('input', scheduleFetch);
            }
            if (qEl) {
                qEl.addEventListener('input', scheduleFetch);
            }
        }

        var alcEl = document.getElementById('fvd-atletas-alcance');
        var tipoEl = document.getElementById('fvd-atletas-tipo');
        var asEl = document.getElementById('fvd-atletas-asoc-id');
        var filtForm = document.getElementById('fvd-atletas-filter-form');
        var asocWrap = document.getElementById('fvd-atletas-asoc-wrap');
        function submitFilterForm() {
            if (!filtForm) {
                return;
            }
            if (typeof filtForm.requestSubmit === 'function') {
                filtForm.requestSubmit();
            } else {
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
            if (isNaN(np) || np < 1) {
                return;
            }
            if (fvdListaSinBusquedaEnVivo && filtFormEarly) {
                if (fvdAtletasSpaParent) {
                    fetchPage(np);
                    return;
                }
                try {
                    var fd = new FormData(filtFormEarly);
                    fd.set('page', String(np));
                    var u = new URL(window.location.href);
                    u.search = '?' + new URLSearchParams(fd).toString();
                    window.location.assign(u.toString());
                } catch (eNav) {
                    fetchPage(np);
                }
                return;
            }
            fetchPage(np);
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

    var fvdDelegadoSolUnaUrl = <?= json_encode($fvd_delegado_solicitud_una_api_url, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var fvdAtletasRootEl = document.getElementById('fvd-atletas-root');
    try {
        if (fvdDelegadoLine) {
            localStorage.removeItem('fvdDelegadoSolQueueV1');
        }
    } catch (eLs) { /* vacío */ }
    if (fvdDelegadoLine && fvdDelegadoSolUnaUrl && fvdAtletasRootEl && typeof fetch === 'function') {
        fvdAtletasRootEl.addEventListener('click', function (ev) {
            var t = ev.target;
            if (!t || !t.closest) {
                return;
            }
            var b = t.closest('.fvd-delegado-sol-direct');
            if (!b || !fvdAtletasRootEl.contains(b)) {
                return;
            }
            ev.preventDefault();
            if (b.getAttribute('data-fvd-sol-loading') === '1') {
                return;
            }
            var tipo = b.getAttribute('data-fvd-sol-tipo') || '';
            var aid = parseInt(b.getAttribute('data-fvd-atleta-id') || '0', 10);
            if (!tipo || aid <= 0) {
                return;
            }
            var body = { tipo: tipo, atleta_id: aid };
            if (tipo === 'traspaso') {
                var wrap = b.closest('.fvd-delegado-traspaso-queue-wrap');
                var sel = wrap ? wrap.querySelector('.fvd-delegado-traspaso-dest') : null;
                if (!sel) {
                    return;
                }
                var destId = parseInt(sel.value || '0', 10);
                if (destId <= 0) {
                    return;
                }
                body.asociacion_destino_id = destId;
            }
            b.setAttribute('data-fvd-sol-loading', '1');
            fetch(fvdDelegadoSolUnaUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(body)
            }).then(function (r) {
                return r.json().then(function (j) {
                    return { r: r, j: j };
                });
            }).then(function (pack) {
                b.removeAttribute('data-fvd-sol-loading');
                var data = pack.j;
                if (!data || !data.ok) {
                    if (hint) {
                        hint.textContent = (data && data.error) ? data.error : 'No se pudo registrar la solicitud.';
                    }
                    return;
                }
                if (hint) {
                    hint.textContent = '';
                }
                var tr = b.closest('tr');
                if (tr && tr.parentNode) {
                    tr.parentNode.removeChild(tr);
                }
            }).catch(function () {
                b.removeAttribute('data-fvd-sol-loading');
                if (hint) {
                    hint.textContent = 'Error de red.';
                }
            });
        });
    }

})();
</script>
