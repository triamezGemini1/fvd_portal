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
/** @var bool $fvd_atletas_puede_reset_marcadores */
/** @var string $fvd_atletas_reset_msg */
/** @var int $fvd_atletas_reset_n */
/** @var string $fvd_atletas_reset_campo */
/** @var array<string, int|string> $fvd_atletas_widget */
$atletasFormNuevoUrl = $selfUrl . '?action=form';
$fvd_atletas_widget = isset($fvd_atletas_widget) && is_array($fvd_atletas_widget) ? $fvd_atletas_widget : [
    'etiqueta' => '', 'total_atletas' => 0, 'total_afiliados' => 0,
    'sexo_m' => 0, 'sexo_f' => 0, 'sexo_sin' => 0, 'torneos' => 0, 'participacion' => 0,
];
$fvd_atletas_puede_reset_marcadores = $fvd_atletas_puede_reset_marcadores ?? false;
$fvd_atletas_reset_msg = $fvd_atletas_reset_msg ?? '';
$fvd_atletas_reset_n = isset($fvd_atletas_reset_n) ? (int) $fvd_atletas_reset_n : 0;
$fvd_atletas_reset_campo = $fvd_atletas_reset_campo ?? '';
$fvd_atletas_reset_etiquetas = [
    'carnet' => 'carnet',
    'traspaso' => 'traspaso',
    'anualidad' => 'anualidad',
    'afiliacion' => 'afiliación',
    'inscripcion' => 'inscripción (+ torneo_id)',
];
$appBaseAtletas = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');
$fvd_url_solicitud_carnet_base = $appBaseAtletas !== '' ? $appBaseAtletas . '/fvdmasteradmin/solicitud_carnet.php' : '/fvdmasteradmin/solicitud_carnet.php';
$fvd_atletas_show_asociacion_col = $fvd_atletas_show_asociacion_col ?? true;
$fvd_atletas_alcance = $fvd_atletas_alcance ?? 'todos';
$fvd_atletas_tipo = $fvd_atletas_tipo ?? 'normal';
$asociacionFiltroId = isset($asociacionFiltroId) ? (int) $asociacionFiltroId : 0;
$fvd_asociaciones_list_filter = isset($fvd_asociaciones_list_filter) && is_array($fvd_asociaciones_list_filter) ? $fvd_asociaciones_list_filter : [];
$fvd_atletas_puede_elegir_alcance = $fvd_atletas_puede_elegir_alcance ?? false;
$nOpcionesAsoc = count($fvd_asociaciones_list_filter);
require_once FVD_PROJECT_ROOT . '/fvdmasteradmin/includes/fvd_asociacion_helpers.php';
?>
<h1 class="fvd-atletas-title">Atletas</h1>

<section class="fvd-atletas-widget no-print" aria-label="Estadísticas del contexto" style="margin:0 0 14px;padding:12px 14px;border-radius:10px;border:1px solid var(--fvd-border, #334155);background:linear-gradient(135deg, rgba(46,48,146,.25) 0%, rgba(15,23,42,.6) 100%);max-width:56rem">
    <h2 style="margin:0 0 10px;font-size:.95rem;font-weight:700;color:var(--fvd-amarillo, #fff200)">Estadísticas — <?= htmlspecialchars((string) ($fvd_atletas_widget['etiqueta'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
    <p style="margin:0 0 10px;font-size:.68rem;color:var(--fvd-muted);line-height:1.4">Cifras alineadas con este listado (tipo «general» sin bajas; sin filtrar por cédula/nombre). Torneos: eventos en <code>torneosact</code> donde la asociación es organizadora. Participación: inscripciones en <code>inscripcion_torneo</code> (o marcador en <code>atletas</code> si la tabla no existe).</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(9.5rem, 1fr));gap:10px;font-size:.8rem">
        <div style="padding:8px 10px;border-radius:8px;background:rgba(0,0,0,.2);border:1px solid rgba(255,255,255,.08)">
            <div style="color:var(--fvd-muted);font-size:.65rem;text-transform:uppercase;letter-spacing:.04em">Atletas</div>
            <div style="font-size:1.35rem;font-weight:800;color:#e2e8f0"><?= number_format((int) ($fvd_atletas_widget['total_atletas'] ?? 0), 0, ',', '.') ?></div>
        </div>
        <div style="padding:8px 10px;border-radius:8px;background:rgba(0,0,0,.2);border:1px solid rgba(255,255,255,.08)">
            <div style="color:var(--fvd-muted);font-size:.65rem;text-transform:uppercase;letter-spacing:.04em">Afiliados (marc.)</div>
            <div style="font-size:1.35rem;font-weight:800;color:#86efac"><?= number_format((int) ($fvd_atletas_widget['total_afiliados'] ?? 0), 0, ',', '.') ?></div>
        </div>
        <div style="padding:8px 10px;border-radius:8px;background:rgba(0,0,0,.2);border:1px solid rgba(255,255,255,.08)">
            <div style="color:var(--fvd-muted);font-size:.65rem;text-transform:uppercase;letter-spacing:.04em">Género M / F / —</div>
            <div style="font-weight:700;color:#e2e8f0;line-height:1.35">
                <?= (int) ($fvd_atletas_widget['sexo_m'] ?? 0) ?> &nbsp;/&nbsp; <?= (int) ($fvd_atletas_widget['sexo_f'] ?? 0) ?> &nbsp;/&nbsp; <?= (int) ($fvd_atletas_widget['sexo_sin'] ?? 0) ?>
            </div>
        </div>
        <div style="padding:8px 10px;border-radius:8px;background:rgba(0,0,0,.2);border:1px solid rgba(255,255,255,.08)">
            <div style="color:var(--fvd-muted);font-size:.65rem;text-transform:uppercase;letter-spacing:.04em">Torneos</div>
            <div style="font-size:1.35rem;font-weight:800;color:#93c5fd"><?= number_format((int) ($fvd_atletas_widget['torneos'] ?? 0), 0, ',', '.') ?></div>
        </div>
        <div style="padding:8px 10px;border-radius:8px;background:rgba(0,0,0,.2);border:1px solid rgba(255,255,255,.08)">
            <div style="color:var(--fvd-muted);font-size:.65rem;text-transform:uppercase;letter-spacing:.04em">Participación</div>
            <div style="font-size:1.35rem;font-weight:800;color:#fcd34d"><?= number_format((int) ($fvd_atletas_widget['participacion'] ?? 0), 0, ',', '.') ?></div>
        </div>
    </div>
</section>

<?php
if (is_array($fvd_asociacion_header ?? null) && ($fvd_asociacion_header['id'] ?? 0) > 0):
    $ahLogo = isset($fvd_asociacion_header['logo']) ? fvd_asociacion_logo_public_url($appBaseAtletas !== '' ? $appBaseAtletas : '', FVD_PROJECT_ROOT, (string) $fvd_asociacion_header['logo']) : null;
    $ahDelegado = trim((string) ($fvd_asociacion_header['delegado'] ?? ''));
    ?>
<div class="fvd-atletas-asoc-head no-print" style="display:flex;align-items:center;gap:14px;margin:0 0 12px;padding:10px 12px;border:1px solid var(--fvd-border, #334155);border-radius:8px;background:var(--fvd-card, #1e293b)">
    <?php if ($ahLogo !== null && $ahLogo !== ''): ?>
        <div class="fvd-atletas-asoc-head__logo" style="flex-shrink:0"><img src="<?= htmlspecialchars($ahLogo, ENT_QUOTES, 'UTF-8') ?>" alt="" style="max-height:56px;max-width:100px;object-fit:contain"></div>
    <?php endif; ?>
    <div class="fvd-atletas-asoc-head__meta" style="font-size:0.9rem;line-height:1.35">
        <?php if ($ahDelegado !== ''): ?>
            <div><strong>Delegado:</strong> <?= htmlspecialchars($ahDelegado, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<p class="fvd-atletas-intro no-print hide-on-13" style="font-size:0.8125rem;color:var(--fvd-muted);margin:0 0 0.75rem">Busque por <strong>cédula</strong> (coincidencia por inicio) o refine por nombre. La tabla se actualiza al escribir (espera breve). Pantalla optimizada para 13".</p>
<?php if (!empty($fvd_error ?? '')): ?><p class="fvd-mod-msg"><?= htmlspecialchars((string) $fvd_error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($fvd_atletas_reset_msg === 'reset_ok'): ?>
    <p class="fvd-mod-msg no-print" style="margin:0 0 .75rem;background:rgba(22,163,74,.15);border-color:#15803d">
        Reinicio aplicado: <strong><?= (int) $fvd_atletas_reset_n ?></strong> fila(s) en
        <code><?= htmlspecialchars((string) ($fvd_atletas_reset_etiquetas[$fvd_atletas_reset_campo] ?? $fvd_atletas_reset_campo), ENT_QUOTES, 'UTF-8') ?></code>.
    </p>
<?php elseif ($fvd_atletas_reset_msg === 'reset_err'): ?>
    <p class="fvd-mod-msg no-print" style="margin:0 0 .75rem">No se pudo completar el reinicio.</p>
<?php endif; ?>

<div id="fvd-atletas-root" class="fvd-atletas-root">

<div class="report-container">
<div class="fvd-mod-toolbar no-print" style="flex-wrap:wrap;align-items:flex-end;gap:10px">
    <form method="get" action="" class="no-print" id="fvd-atletas-filter-form" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
        <input type="hidden" name="action" value="list">
        <?php if ($fvd_atletas_puede_elegir_alcance): ?>
        <div>
            <label style="font-size:.8125rem;color:var(--fvd-muted);display:block;font-weight:600">Alcance</label>
            <select id="fvd-atletas-alcance" class="fvd-input" name="alcance" style="max-width:16rem" aria-describedby="fvd-atletas-alcance-hint">
                <option value="todos"<?= $fvd_atletas_alcance === 'todos' ? ' selected' : '' ?>>Todos (toda la federación)</option>
                <option value="asociacion"<?= $fvd_atletas_alcance === 'asociacion' ? ' selected' : '' ?>>Una asociación concreta…</option>
            </select>
            <p id="fvd-atletas-alcance-hint" class="no-print" style="font-size:.68rem;color:var(--fvd-muted);margin:4px 0 0;max-width:20rem">«Todos» lista atletas de todas las asociaciones. «Una asociación» exige elegir cuál en el siguiente campo.</p>
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
        <a href="<?= htmlspecialchars($selfUrl . '?action=list', ENT_QUOTES, 'UTF-8') ?>" class="fvd-input" style="width:auto;padding:6px 12px;display:inline-flex;align-items:center;text-decoration:none;box-sizing:border-box">Limpiar</a>
    </form>
    <div class="fvd-atletas-report-links no-print" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
        <?php
        if (!function_exists('admin_module_url')) {
            require_once FVD_PROJECT_ROOT . '/config/paths.php';
        }
        $repBase = isset($atletasReportBaseUrl) && is_string($atletasReportBaseUrl) && $atletasReportBaseUrl !== ''
            ? $atletasReportBaseUrl
            : admin_module_url('atletas/');
        $uRepPen = $repBase . 'reporte_carnets.php?tipo=pendientes';
        $uRepEmi = $repBase . 'reporte_carnets.php?tipo=solicitados';
        $uRepTr = $repBase . 'reporte_traspasos.php';
        $uRepInd = $repBase . 'reporte_indicadores.php';
        ?>
        <span style="font-size:.7rem;color:var(--fvd-muted);font-weight:600">Informes:</span>
        <a class="fvd-input" style="width:auto;padding:4px 10px;font-size:.75rem;text-decoration:none;display:inline-flex;align-items:center;box-sizing:border-box;font-weight:600" href="<?= htmlspecialchars($uRepInd, ENT_QUOTES, 'UTF-8') ?>" title="Totales, ficha completa y reinicio masivo de marcadores">Indicadores + reset</a>
        <a class="fvd-input" style="width:auto;padding:4px 10px;font-size:.75rem;text-decoration:none;display:inline-flex;align-items:center;box-sizing:border-box" href="<?= htmlspecialchars($uRepPen, ENT_QUOTES, 'UTF-8') ?>">Elaboración carnets</a>
        <a class="fvd-input" style="width:auto;padding:4px 10px;font-size:.75rem;text-decoration:none;display:inline-flex;align-items:center;box-sizing:border-box" href="<?= htmlspecialchars($uRepEmi, ENT_QUOTES, 'UTF-8') ?>">Carnets solicitados</a>
        <a class="fvd-input" style="width:auto;padding:4px 10px;font-size:.75rem;text-decoration:none;display:inline-flex;align-items:center;box-sizing:border-box" href="<?= htmlspecialchars($uRepTr, ENT_QUOTES, 'UTF-8') ?>">Traspasos</a>
    </div>
    <div class="fvd-atletas-export no-print" role="group" aria-label="Exportar listado">
        <span class="fvd-atletas-export__label" style="font-size:.75rem;color:var(--fvd-muted);display:block;margin-bottom:4px;font-weight:600">Exportar</span>
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
            <button type="button" class="fvd-input" id="fvd-export-csv" style="width:auto;padding:6px 12px">Excel (CSV)</button>
            <button type="button" class="fvd-input" id="fvd-export-pdf" style="width:auto;padding:6px 12px">PDF</button>
        </div>
    </div>
    <a href="<?= htmlspecialchars($atletasFormNuevoUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-btn-primary no-print" style="text-decoration:none;box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center">Nuevo atleta</a>
</div>

<?php if ($fvd_atletas_puede_reset_marcadores): ?>
<section class="no-print fvd-atletas-reset-marcadores" aria-label="Reinicio masivo de marcadores" style="margin:0 0 12px;padding:12px 14px;border-radius:8px;border:2px solid #991b1b;background:rgba(127,29,29,.18)">
    <h2 style="margin:0 0 6px;font-size:1rem;font-weight:800;color:#fecaca">Reiniciar marcadores (poner en 0)</h2>
    <p style="margin:0 0 10px;font-size:.72rem;color:var(--fvd-muted);line-height:1.45">
        Masivo sobre <code>atletas</code> en su <strong>alcance de sesión</strong> (FVD: todos; asociación/delegado: solo ese club).
        <strong>Inscripción</strong> también pone <code>torneo_id = 0</code>.
    </p>
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
        <?php
        $accionesResetList = [
            ['carnet', 'Reset carnets', '¿Poner en 0 el marcador de carnet en todos los atletas de su alcance?'],
            ['traspaso', 'Reset traspasos', '¿Poner en 0 el marcador de traspaso en todos los atletas de su alcance?'],
            ['anualidad', 'Reset anualidad', '¿Poner en 0 el marcador de anualidad en todos los atletas de su alcance?'],
            ['afiliacion', 'Reset afiliación', '¿Poner en 0 el marcador de afiliación en todos los atletas de su alcance?'],
            ['inscripcion', 'Reset inscripciones', '¿Poner en 0 inscripción y torneo_id en todos los atletas de su alcance?'],
        ];
        foreach ($accionesResetList as $arL):
            [$mkL, $mlabL, $mconfirmL] = $arL;
        ?>
        <form method="post" action="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" style="margin:0" onsubmit="return confirm(<?= htmlspecialchars(json_encode($mconfirmL, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>);">
            <input type="hidden" name="_action" value="reset_marcador_atletas">
            <input type="hidden" name="marcador" value="<?= htmlspecialchars($mkL, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="fvd-input" style="width:auto;padding:6px 12px;font-size:.75rem;cursor:pointer;background:#7f1d1d;color:#fecaca;border-color:#991b1b"><?= htmlspecialchars($mlabL, ENT_QUOTES, 'UTF-8') ?></button>
        </form>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<div class="fvd-atletas-view-seg no-print" role="tablist" aria-label="Vista de columnas">
    <button type="button" class="fvd-atletas-seg__btn fvd-atletas-seg__btn--on" data-fvd-atletas-view="basic" role="tab">Vista básica (contacto)</button>
    <button type="button" class="fvd-atletas-seg__btn" data-fvd-atletas-view="tech" role="tab">Vista técnica (FVD)</button>
</div>

<p id="fvd-atletas-live-hint" class="fvd-atletas-live-hint no-print" aria-live="polite"></p>

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
            <th class="fvd-col-sol-carnet">Solicitar carnet</th>
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
            <tr><td colspan="<?= $fvd_atletas_show_asociacion_col ? '13' : '12' ?>" style="padding:12px">Sin registros con los filtros actuales.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
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
