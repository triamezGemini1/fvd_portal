<?php
declare(strict_types=1);
/** @var array<string,mixed> $torneoMeta */
/** @var string $inscripcionApiUrl */
/** @var int $torneoSel */
/** @var int $asocId */
/** @var bool $esFvd */
/** @var bool $fvd_inscripcion_bandera_modo */
/** @var list<array{atleta_id:int,nombre:string,cedula:string,numfvd:int,cedula_num:int}> $fvdSitioDisponibles */
/** @var list<array{atleta_id:int,nombre:string,cedula:string,numfvd:int,cedula_num:int,equipo:int,retirar_mode?:string}> $fvdSitioInscritos */
/** @var list<array<string,mixed>> $fvdSitioInscritosGrupos */
/** @var int $fvd_sitio_clase */
/** @var string $fvdSitioNuevoAtletaUrl */
/** @var list<array<string,mixed>> $fvdDelegadoGrupoTorneos torneos del campeonato (invitación) */
/** @var int $fvd_campeonato_grupo */
/** @var string $selfUrl URL canónica del módulo (redirecciones / actualizar) */
/** @var string $fvd_campeonato_q query &campeonato_id=… */
$fvdDelegadoGrupoTorneos = $fvdDelegadoGrupoTorneos ?? [];
$fvd_campeonato_grupo = isset($fvd_campeonato_grupo) ? (int) $fvd_campeonato_grupo : 0;
$selfUrl = isset($selfUrl) ? (string) $selfUrl : '';
$fvd_campeonato_q = isset($fvd_campeonato_q) ? (string) $fvd_campeonato_q : '';
$fvdSitioInscritosGrupos = $fvdSitioInscritosGrupos ?? [];
$fvd_sitio_clase = isset($fvd_sitio_clase) ? (int) $fvd_sitio_clase : 0;
$clSitio = $fvd_sitio_clase > 0 ? $fvd_sitio_clase : (int) ($torneoMeta['clase'] ?? 1);
$tnom = htmlspecialchars((string) ($torneoMeta['torneo']['nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
$esInd = $clSitio === 1;
$fvdMtfClaseEtiqueta = $clSitio === 2 ? 'Parejas' : ($clSitio === 3 ? 'Equipos' : 'Individual');
$fvdMtfPillMod = $clSitio === 2 ? 'parejas' : ($clSitio === 3 ? 'equipos' : 'individual');
$fvdMtfCupoLine = '';
if (!empty($torneoMeta['cupo']) && is_array($torneoMeta['cupo'])) {
    $cuM = $torneoMeta['cupo'];
    $usM = (int) ($cuM['usado'] ?? 0);
    $mxM = $cuM['max'] ?? null;
    if ($mxM === null || (int) $mxM <= 0) {
        $fvdMtfCupoLine = 'Plazas usadas (club): ' . $usM . ' (sin cupo máximo en convocatoria)';
    } else {
        $mxI = (int) $mxM;
        $reM = isset($cuM['restante']) && $cuM['restante'] !== null ? (int) $cuM['restante'] : null;
        $fvdMtfCupoLine = 'Cupo club: ' . $usM . ' / ' . $mxI . ($reM !== null ? ' — restante: ' . $reM : '');
    }
}
$vdDeleg = $torneoMeta['ventana_delegado'] ?? null;
$accesoDeleg = $torneoMeta['acceso_delegado'] ?? null;
$fvdNominaSoloLectura = is_array($accesoDeleg) && !empty($accesoDeleg['nomina_solo_lectura']);
$fvdFechaLimiteNominaFmt = is_array($accesoDeleg) ? trim((string) ($accesoDeleg['fecha_limite_cambios_formato'] ?? '')) : '';
$fvdDelegadoInscripcionCerradaVentana = !empty($fvd_inscripcion_bandera_modo)
    && is_array($vdDeleg)
    && !($vdDeleg['fase2_inscripciones'] ?? false);
$fvdSitioSoloLectura = $fvdDelegadoInscripcionCerradaVentana || $fvdNominaSoloLectura;
$fvdDelegadoVentanaMsg = is_array($vdDeleg) ? (string) ($vdDeleg['etiqueta_fase'] ?? '') : '';
$esPareOEq = ($clSitio === 2 || $clSitio === 3);
$nInscSlot = $esPareOEq ? count($fvdSitioInscritosGrupos) : count($fvdSitioInscritos);
$fvdInscMaxNomina = 80;
if ($clSitio === 2) {
    $fvdInscMaxNomina = 2;
} elseif ($clSitio === 3) {
    $fvdInscMaxNomina = (int) ($torneoMeta['integrantes_equipo'] ?? 4);
    if ($fvdInscMaxNomina < 2) {
        $fvdInscMaxNomina = 4;
    }
}
$navGrupo = (int) $fvd_campeonato_grupo;
$navLista = is_array($fvdDelegadoGrupoTorneos) ? $fvdDelegadoGrupoTorneos : [];
$gruposJson = array_map(
    static function (array $g): array {
        $mem = [];
        foreach ($g['integrantes'] ?? [] as $i) {
            $mem[] = [
                'id' => (int) ($i['atleta_id'] ?? 0),
                'nombre' => (string) ($i['nombre'] ?? ''),
                'cedula' => (string) ($i['cedula'] ?? ''),
                'numfvd' => (int) ($i['numfvd'] ?? 0),
            ];
        }

        return [
            'equipo' => (int) ($g['equipo'] ?? 0),
            'nombre_equipo' => (string) ($g['nombre_equipo'] ?? ''),
            'es_legacy' => !empty($g['es_legacy_solo_bandera']),
            'integrantes' => $mem,
        ];
    },
    $fvdSitioInscritosGrupos
);
?>
<link rel="stylesheet" href="<?= htmlspecialchars(url('assets/css/fvd-inscripcion-sitio.css'), ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(url('assets/css/fvd-insc-forms-panel.css'), ENT_QUOTES, 'UTF-8') ?>">
<?php if ($fvdSitioSoloLectura): ?>
<style>
.fvd-insc-sitio--solo-lectura .fvd-insc-sitio__btn,
.fvd-insc-sitio--solo-lectura .fvd-insc-sitio__btn--ok,
.fvd-insc-sitio--solo-lectura .fvd-insc-sitio__btn--sec { opacity: 0.45; pointer-events: none; cursor: not-allowed; }
.fvd-insc-sitio--solo-lectura .fvd-insc-sitio__table tbody tr,
.fvd-insc-sitio--solo-lectura .fvd-mod-table tbody tr { cursor: default; }
.fvd-insc-sitio__lockcell { width: 2rem; text-align: center; font-size: 0.85rem; }
</style>
<?php endif; ?>

<section id="fvd-insc-sitio-panel" class="fvd-insc-sitio<?= $fvdSitioSoloLectura ? ' fvd-insc-sitio--solo-lectura' : '' ?>" aria-label="<?= $tnom ?>" data-torneo-id="<?= (int) $torneoSel ?>">
    <div class="fvd-mtf-shell">
        <div class="fvd-mtf-card fvd-mtf-card--sitio">
            <header class="fvd-mtf-card__head fvd-mtf-card__head--sitio">
                <div class="fvd-mtf-card__head-main">
                    <h2 class="fvd-mtf-card__title"><?= $tnom !== '' ? $tnom : 'Inscripción al torneo' ?></h2>
                    <span class="fvd-mtf-pill<?= $fvdMtfPillMod === 'parejas' ? ' fvd-mtf-pill--parejas' : ($fvdMtfPillMod === 'equipos' ? ' fvd-mtf-pill--equipos' : '') ?>"><?= htmlspecialchars($fvdMtfClaseEtiqueta, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php if ($fvdMtfCupoLine !== ''): ?>
                    <div class="fvd-mtf-card__meta"><?= htmlspecialchars($fvdMtfCupoLine, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
            </header>
            <div class="fvd-mtf-card__body">
    <div class="fvd-insc-sitio__body">
        <?php
        $fvdInscStatsGen = $fvdInscStatsGen ?? null;
        $fvdInscCtxTorneos = $fvdInscCtxTorneos ?? [];
        $fvdInscTorneosChips = $fvdInscTorneosChips ?? [];
        $fvdInscEsDelegado = !empty($fvdInscEsDelegado);
        $fvdInscEsFvdAdmin = !empty($fvdInscEsFvdAdmin);
        $fvdInscAsocIdQuery = isset($fvdInscAsocIdQuery) ? (int) $fvdInscAsocIdQuery : 0;
        $fvdInscCampeonatoId = isset($fvdInscCampeonatoId) ? (int) $fvdInscCampeonatoId : 0;
        $fvdInscSelfUrl = isset($fvdInscSelfUrl) ? (string) $fvdInscSelfUrl : '';
        $fvdInscFilterTorneoId = isset($fvdInscFilterTorneoId) ? (int) $fvdInscFilterTorneoId : (int) $torneoSel;
        $fvdInscTorneoNombre = isset($fvdInscTorneoNombre)
            ? (string) $fvdInscTorneoNombre
            : trim((string) ($torneoMeta['torneo']['nombre'] ?? ''));
        require FVD_PROJECT_ROOT . '/fvdmasteradmin/modules/inscripcion_torneo/partial_insc_ctx_estadisticas.php';
        ?>
        <?php if ($fvdNominaSoloLectura): ?>
            <p class="fvd-mod-msg" style="margin:0 0 0.75rem;font-size:0.875rem;border-left:4px solid #94a3b8;padding-left:10px;background:rgba(241,245,249,0.95);color:#334155">
                ⚠️ Periodo de cambios finalizado el <?= htmlspecialchars($fvdFechaLimiteNominaFmt !== '' ? $fvdFechaLimiteNominaFmt : '—', ENT_QUOTES, 'UTF-8') ?> — Vista de consulta solamente.
            </p>
        <?php elseif ($fvdDelegadoInscripcionCerradaVentana): ?>
            <p class="fvd-mod-msg" style="margin:0 0 0.75rem;font-size:0.875rem;border-left:4px solid #f59e0c;padding-left:10px">
                <strong>Calendario del torneo:</strong> <?= htmlspecialchars($fvdDelegadoVentanaMsg !== '' ? $fvdDelegadoVentanaMsg : 'Fuera del periodo de inscripciones y retiros solo puede consultar listados y registrar pagos.', ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <div id="fvd-insc-sitio-inscribir" class="fvd-insc-sitio__inscribir-zone" tabindex="-1">
        <input type="hidden" id="fvd-insc-max-nomina" value="<?= (int) $fvdInscMaxNomina ?>">

        <?php if ($esInd): ?>
        <div class="fvd-insc-sitio__fila" id="fvd-sitio-linea">
            <div class="fvd-insc-sitio__campo fvd-insc-sitio__nac">
                <label class="fvd-mtf-form-label" for="fvd-sitio-nac">Nac.</label>
                <input type="text" id="fvd-sitio-nac" class="fvd-input" maxlength="1" value="V" autocomplete="off" title="V, E, J o P">
            </div>
            <div class="fvd-insc-sitio__campo fvd-insc-sitio__ced">
                <label class="fvd-mtf-form-label" for="fvd-sitio-ced">Cédula <span style="color:#f87171">*</span></label>
                <input type="text" id="fvd-sitio-ced" class="fvd-input" inputmode="numeric" maxlength="15" placeholder="Solo números" autocomplete="off">
            </div>
            <div class="fvd-insc-sitio__campo fvd-insc-sitio__res is-hidden" id="fvd-sitio-wrap-nombre">
                <label class="fvd-mtf-form-label" for="fvd-sitio-nombre">Nombre</label>
                <input type="text" id="fvd-sitio-nombre" class="fvd-input" readonly>
            </div>
            <div class="fvd-insc-sitio__campo fvd-insc-sitio__sexo is-hidden" id="fvd-sitio-wrap-sexo">
                <label class="fvd-mtf-form-label" for="fvd-sitio-sexo">Sexo</label>
                <select id="fvd-sitio-sexo" class="fvd-input">
                    <option value="M">M</option>
                    <option value="F">F</option>
                    <option value="O">O</option>
                </select>
            </div>
            <div class="fvd-insc-sitio__acc is-hidden" id="fvd-sitio-wrap-btns">
                <button type="button" class="fvd-insc-sitio__btn fvd-insc-sitio__btn--ok" id="fvd-sitio-inscribir">Inscribir</button>
                <button type="button" class="fvd-insc-sitio__btn fvd-insc-sitio__btn--sec" id="fvd-sitio-limpiar" title="Otra búsqueda">↻</button>
            </div>
        </div>
        <p id="fvd-sitio-msg" class="fvd-insc-sitio__msg" role="status" aria-live="polite"></p>
        <p class="fvd-insc-sitio__hint" id="fvd-sitio-noenc" style="display:none">
            <a href="<?= htmlspecialchars($fvdSitioNuevoAtletaUrl, ENT_QUOTES, 'UTF-8') ?>">Registrar nuevo atleta</a>
        </p>
        <?php endif; ?>
        </div>

        <div class="fvd-insc-sitio__nomina-strip-outer">
            <?php require __DIR__ . '/inscribir_nomina_busqueda.php'; ?>
        </div>

        <div class="fvd-insc-sitio__main">
            <div class="fvd-insc-sitio__main-left">
                <div class="fvd-insc-sitio__card fvd-insc-sitio__card--disp-only">
                    <div class="fvd-insc-sitio__card-h fvd-insc-sitio__card-h--disp">
                        Disponibles
                        <span class="fvd-insc-sitio__badge" id="fvd-sitio-n-disp"><?= count($fvdSitioDisponibles) ?></span>
                    </div>
                    <?php if (!$esInd): ?>
                    <p class="fvd-insc-sitio__disp-hint" style="margin:0 0 0.4rem;font-size:0.72rem;font-weight:700;color:var(--fvd-muted,#64748b)">Pulse una fila para añadirla a la nómina del equipo (o use la búsqueda arriba).</p>
                    <?php endif; ?>
                    <div class="fvd-mod-table-wrap fvd-insc-sitio__table-wrap">
                        <table class="fvd-mod-table fvd-insc-sitio__table">
                            <thead><tr><th>Nombre</th><th>Nº FVD</th><th>CI</th><?php if ($fvdNominaSoloLectura): ?><th class="fvd-insc-sitio__lockcell" title="Solo consulta">🔒</th><?php endif; ?></tr></thead>
                            <tbody id="fvd-sitio-tbody-disp">
                                <?php foreach ($fvdSitioDisponibles as $u): ?>
                                <tr data-aid="<?= (int) $u['atleta_id'] ?>" data-nombre="<?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-cedula-num="<?= (int) ($u['cedula_num'] ?? 0) ?>"
                                    data-cedula="<?= htmlspecialchars((string) ($u['cedula'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    data-numfvd="<?= (int) ($u['numfvd'] ?? 0) ?>">
                                    <td><strong><?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                    <td><?= (int) $u['numfvd'] ?></td>
                                    <td><?= htmlspecialchars($u['cedula'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <?php if ($fvdNominaSoloLectura): ?><td class="fvd-insc-sitio__lockcell" title="Solo consulta">🔒</td><?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div id="fvd-insc-sitio-admin" class="fvd-insc-sitio__main-right" tabindex="-1">
                <div class="fvd-insc-sitio__card fvd-insc-sitio__card--insc-only">
                    <div class="fvd-insc-sitio__card-h fvd-insc-sitio__card-h--insc">
                        Inscritos<?= $esPareOEq ? ' (pareja / equipo)' : '' ?>
                        <span class="fvd-insc-sitio__badge" id="fvd-sitio-n-insc"><?= $nInscSlot ?></span>
                    </div>
                    <?php if ($esPareOEq): ?>
                    <p class="fvd-insc-sitio__hint" style="margin:0 0 0.5rem;font-size:0.72rem;color:var(--fvd-muted,#64748b)">
                        Cada fila es una inscripción de pareja o equipo. Use <em>Editar</em> para cargar la nómina y sustituir integrantes, luego <em>Guardar equipo</em> arriba.
                    </p>
                    <div class="fvd-mod-table-wrap fvd-insc-sitio__table-wrap fvd-insc-sitio__table-wrap--insc">
                        <table class="fvd-mod-table fvd-insc-sitio__table fvd-insc-sitio__table--grupos">
                            <thead>
                            <tr>
                                <th><?= $clSitio === 2 ? 'Pareja' : 'Equipo' ?></th>
                                <th>Integrantes (resumen)</th>
                                <th style="text-align:right;white-space:nowrap">Acciones</th>
                            </tr>
                            </thead>
                            <tbody id="fvd-sitio-tbody-insc-grupos">
                                <?php foreach ($fvdSitioInscritosGrupos as $g):
                                    $eqG = (int) ($g['equipo'] ?? 0);
                                    $leg = !empty($g['es_legacy_solo_bandera']);
                                    $nomEq = trim((string) ($g['nombre_equipo'] ?? ''));
                                    $integ = $g['integrantes'] ?? [];
                                    $resumen = [];
                                    foreach ($integ as $m) {
                                        $resumen[] = trim((string) ($m['nombre'] ?? '')) . ' (FVD ' . (int) ($m['numfvd'] ?? 0) . ')';
                                    }
                                    $resumenTxt = $resumen !== [] ? implode(' · ', $resumen) : '—';
                                    ?>
                                <tr data-equipo="<?= $eqG ?>" data-legacy="<?= $leg ? '1' : '0' ?>">
                                    <td><strong><?= htmlspecialchars($nomEq !== '' ? $nomEq : '—', ENT_QUOTES, 'UTF-8') ?></strong>
                                        <?php if ($leg): ?><br><span style="font-size:0.68rem;font-weight:600;color:#b45309">Solo bandera</span><?php endif; ?>
                                    </td>
                                    <td style="font-size:0.78rem;max-width:16rem"><?= htmlspecialchars($resumenTxt, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td style="text-align:right;white-space:nowrap">
                                        <button type="button" class="fvd-insc-sitio__btn fvd-insc-sitio__btn--sm fvd-sitio-eq-ver" style="padding:2px 8px;font-size:0.75rem" data-equipo-key="<?= (int) $eqG ?>">Ver</button>
                                        <?php if (!$leg && $eqG > 0 && !$fvdSitioSoloLectura): ?>
                                        <button type="button" class="fvd-insc-sitio__btn fvd-insc-sitio__btn--ok fvd-sitio-eq-edit" style="padding:2px 8px;font-size:0.75rem" data-equipo-key="<?= (int) $eqG ?>">Editar</button>
                                        <button type="button" class="fvd-insc-sitio__btn fvd-insc-sitio__btn--sec fvd-sitio-eq-quit" style="padding:2px 8px;font-size:0.75rem" data-equipo-key="<?= (int) $eqG ?>">Retirar</button>
                                        <?php elseif ($leg && !$fvdSitioSoloLectura && isset($integ[0])): ?>
                                        <span style="font-size:0.7rem;color:var(--fvd-muted)">Use retiro en detalle o vuelva a inscribir</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div id="fvd-sitio-dlg-miembros" class="fvd-insc-sitio__dlg" style="display:none" role="dialog" aria-label="Integrantes inscritos">
                        <div class="fvd-insc-sitio__dlg-c">
                            <div class="fvd-insc-sitio__dlg-h">
                                <span id="fvd-sitio-dlg-miembros-tit">Integrantes</span>
                                <button type="button" class="fvd-insc-sitio__btn" id="fvd-sitio-dlg-miembros-cerrar" style="padding:2px 8px">Cerrar</button>
                            </div>
                            <ul id="fvd-sitio-dlg-miembros-list" class="fvd-insc-sitio__dlg-list" style="margin:0;padding-left:1.1rem"></ul>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="fvd-mod-table-wrap fvd-insc-sitio__table-wrap fvd-insc-sitio__table-wrap--insc">
                        <table class="fvd-mod-table fvd-insc-sitio__table">
                            <thead><tr><th>Nombre</th><th style="text-align:right">Nº FVD</th><?php if ($fvdNominaSoloLectura): ?><th class="fvd-insc-sitio__lockcell" title="Solo consulta">🔒</th><?php endif; ?></tr></thead>
                            <tbody id="fvd-sitio-tbody-insc">
                                <?php foreach ($fvdSitioInscritos as $i):
                                    $iAid = (int) $i['atleta_id'];
                                    $iCedN = (int) ($i['cedula_num'] ?? 0);
                                    $iEq = (int) ($i['equipo'] ?? 0);
                                    if (isset($i['retirar_mode']) && (string) $i['retirar_mode'] !== '') {
                                        $retMode = (string) $i['retirar_mode'];
                                    } else {
                                        $retMode = $fvd_inscripcion_bandera_modo && $iAid > 0
                                            ? 'bandera'
                                            : (!$fvd_inscripcion_bandera_modo && $iCedN > 0 && $iEq === 0 ? 'tabla' : '0');
                                    }
                                    ?>
                                <tr data-aid="<?= $iAid ?>" data-nombre="<?= htmlspecialchars($i['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-cedula-num="<?= $iCedN ?>"
                                    data-retirar="<?= htmlspecialchars($retMode, ENT_QUOTES, 'UTF-8') ?>">
                                    <td><strong><?= htmlspecialchars($i['nombre'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                    <td style="text-align:right"><?= (int) $i['numfvd'] ?></td>
                                    <?php if ($fvdNominaSoloLectura): ?><td class="fvd-insc-sitio__lockcell" title="Solo consulta">🔒</td><?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="fvd-insc-sitio__finish" style="margin-top:1rem;display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap">
            <button type="button" class="fvd-insc-sitio__btn fvd-insc-sitio__btn--sec" id="fvd-sitio-finalizar">Finalizar</button>
            <small class="fvd-insc-sitio__hint" id="fvd-sitio-finish-msg" style="margin:0"></small>
        </div>
    </div>
            </div>
        </div>
    </div>
</section>

<style>.is-hidden{display:none!important}</style>
<script>
(function () {
    var api = <?= json_encode($inscripcionApiUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var torneoIdInit = <?= (int) $torneoSel ?>;
    function sitioLiveTorneoId() {
        var p = document.getElementById('fvd-insc-sitio-panel');
        var t = p ? parseInt(p.getAttribute('data-torneo-id') || '0', 10) : 0;
        return t > 0 ? t : torneoIdInit;
    }
    var asocId = <?= (int) $asocId ?>;
    var esFvd = <?= $esFvd ? 'true' : 'false' ?>;
    var banderaMode = <?= $fvd_inscripcion_bandera_modo ? 'true' : 'false' ?>;
    var esInd = <?= $esInd ? 'true' : 'false' ?>;
    try {
        window._FVD_SITIO_GRUPOS = <?= json_encode($gruposJson, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    } catch (e0) { window._FVD_SITIO_GRUPOS = []; }
    function fvdBuildGruposMapForJs(grupos) {
        return (grupos || []).map(function (g) {
            return {
                equipo: g.equipo | 0,
                nombre_equipo: g.nombre_equipo != null ? String(g.nombre_equipo) : '',
                es_legacy: !!g.es_legacy_solo_bandera,
                integrantes: (g.integrantes || []).map(function (i) {
                    return {
                        id: i.atleta_id | 0,
                        nombre: i.nombre != null ? String(i.nombre) : '',
                        cedula: i.cedula != null ? String(i.cedula) : '',
                        numfvd: i.numfvd != null ? (i.numfvd | 0) : 0
                    };
                })
            };
        });
    }
    function fvdFillInscGruposDom(grupos) {
        var tb = document.getElementById('fvd-sitio-tbody-insc-grupos');
        if (!tb) return;
        window._FVD_SITIO_GRUPOS = fvdBuildGruposMapForJs(grupos);
        tb.innerHTML = '';
        (grupos || []).forEach(function (g) {
            var tr = document.createElement('tr');
            var eq = g.equipo | 0;
            var leg = !!g.es_legacy_solo_bandera;
            tr.setAttribute('data-equipo', String(eq));
            tr.setAttribute('data-legacy', leg ? '1' : '0');
            var res = [];
            (g.integrantes || []).forEach(function (m) {
                res.push(escHtml(m.nombre) + ' (FVD ' + escHtml(m.numfvd) + ')');
            });
            var nom = g.nombre_equipo != null ? String(g.nombre_equipo) : '—';
            var acc = '<button type="button" class="fvd-insc-sitio__btn fvd-insc-sitio__btn--sm fvd-sitio-eq-ver" style="padding:2px 8px;font-size:0.75rem" data-equipo-key="' + eq + '">Ver</button>';
            if (!leg && eq > 0) {
                acc += ' <button type="button" class="fvd-insc-sitio__btn fvd-insc-sitio__btn--ok fvd-sitio-eq-edit" style="padding:2px 8px;font-size:0.75rem" data-equipo-key="' + eq + '">Editar</button>';
                acc += ' <button type="button" class="fvd-insc-sitio__btn fvd-insc-sitio__btn--sec fvd-sitio-eq-quit" style="padding:2px 8px;font-size:0.75rem" data-equipo-key="' + eq + '">Retirar</button>';
            } else {
                acc += ' <span style="font-size:0.7rem;color:var(--fvd-muted)">Solo bandera</span>';
            }
            tr.innerHTML = '<td><strong>' + escHtml(nom) + '</strong></td><td style="font-size:0.78rem;max-width:16rem">' + (res.length ? res.join(' · ') : '—') + '</td><td style="text-align:right;white-space:nowrap">' + acc + '</td>';
            tb.appendChild(tr);
        });
        var ni = document.getElementById('fvd-sitio-n-insc');
        if (ni) ni.textContent = String((grupos || []).length);
    }
    function fvdFillInscIndividualesDom(rows) {
        var tb = document.getElementById('fvd-sitio-tbody-insc');
        if (!tb) return;
        tb.innerHTML = '';
        (rows || []).forEach(function (row) {
            var tr = document.createElement('tr');
            var rm = row.retirar_mode != null ? String(row.retirar_mode) : '0';
            tr.setAttribute('data-aid', String(row.atleta_id || 0));
            tr.setAttribute('data-nombre', String(row.nombre || ''));
            tr.setAttribute('data-cedula-num', String(row.cedula_num || 0));
            tr.setAttribute('data-retirar', rm);
            tr.innerHTML = '<td><strong>' + escHtml(row.nombre) + '</strong></td><td style="text-align:right">' + escHtml(row.numfvd) + '</td>';
            tb.appendChild(tr);
        });
        var nix = document.getElementById('fvd-sitio-n-insc');
        if (nix) nix.textContent = String((rows || []).length);
    }
    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }
    window._FVD_SITIO_API_REFILL = function (d) {
        if (!d) return;
        if ((d.fvd_sitio_clase | 0) === 2 || (d.fvd_sitio_clase | 0) === 3) {
            fvdFillInscGruposDom(d.fvdSitioInscritosGrupos || []);
        } else {
            fvdFillInscIndividualesDom(d.fvdSitioInscritos || []);
        }
    };
    var delegadoInscripcionCerrada = <?= $fvdSitioSoloLectura ? 'true' : 'false' ?>;
    var nominaSoloLectura = <?= $fvdNominaSoloLectura ? 'true' : 'false' ?>;
    var usuarioEncontrado = null;

    function qs(id) { return document.getElementById(id); }
    function apiQs(extra) {
        var s = api.indexOf('?') >= 0 ? '&' : '?';
        var x = extra || '';
        if (esFvd && asocId) x += (x ? '&' : '') + 'asociacion_id=' + encodeURIComponent(String(asocId));
        return s + x;
    }
    function msg(txt, kind) {
        var el = qs('fvd-sitio-msg');
        if (!el) return;
        el.textContent = txt || '';
        el.className = 'fvd-insc-sitio__msg fvd-insc-sitio__msg--show' + (kind ? ' fvd-insc-sitio__msg--' + kind : '');
        if (!txt) el.classList.remove('fvd-insc-sitio__msg--show');
    }
    function setHidden(id, on) {
        var w = qs(id);
        if (w) w.classList.toggle('is-hidden', !!on);
    }
    function limpiarLinea() {
        var c = qs('fvd-sitio-ced');
        if (c) c.value = '';
        var n = qs('fvd-sitio-nac');
        if (n) n.value = 'V';
        if (qs('fvd-sitio-nombre')) qs('fvd-sitio-nombre').value = '';
        usuarioEncontrado = null;
        setHidden('fvd-sitio-wrap-nombre', true);
        setHidden('fvd-sitio-wrap-sexo', true);
        setHidden('fvd-sitio-wrap-btns', true);
        msg('', '');
        var ne = qs('fvd-sitio-noenc');
        if (ne) ne.style.display = 'none';
    }
    function normNac(v) {
        v = (v || '').trim().toUpperCase();
        return ['V','E','J','P'].indexOf(v) >= 0 ? v : 'V';
    }
    function buscarCedula() {
        if (!esInd) return;
        var nac = normNac(qs('fvd-sitio-nac') && qs('fvd-sitio-nac').value);
        if (qs('fvd-sitio-nac')) qs('fvd-sitio-nac').value = nac;
        var ced = (qs('fvd-sitio-ced') && qs('fvd-sitio-ced').value) || '';
        ced = ced.replace(/\D/g, '');
        if (ced.length < 4) return;
        msg('Buscando…', 'info');
        usuarioEncontrado = null;
        setHidden('fvd-sitio-wrap-nombre', true);
        setHidden('fvd-sitio-wrap-sexo', true);
        setHidden('fvd-sitio-wrap-btns', true);
        var ne = qs('fvd-sitio-noenc');
        if (ne) ne.style.display = 'none';
        var u = api + apiQs('action=buscar_cedula_sitio&torneo_id=' + encodeURIComponent(String(sitioLiveTorneoId()))
            + '&nacionalidad=' + encodeURIComponent(nac) + '&cedula=' + encodeURIComponent(ced));
        fetch(u, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.ok) {
                    msg((d && d.error) ? d.error : 'Error.', 'err');
                    return;
                }
                var res = d.resultado || '';
                if (res === 'ya_inscrito') {
                    msg(d.mensaje || 'Ya inscrito.', 'warn');
                    limpiarLinea();
                    return;
                }
                if (res === 'no_encontrado') {
                    msg(d.mensaje || 'No encontrado.', 'warn');
                    if (ne) ne.style.display = 'block';
                    return;
                }
                if (res === 'atleta' && d.atleta) {
                    usuarioEncontrado = d.atleta;
                    if (qs('fvd-sitio-nombre')) qs('fvd-sitio-nombre').value = d.atleta.nombre || '';
                    var sx = qs('fvd-sitio-sexo');
                    if (sx) sx.value = (d.atleta.sexo || 'M').toUpperCase();
                    setHidden('fvd-sitio-wrap-nombre', false);
                    setHidden('fvd-sitio-wrap-sexo', false);
                    setHidden('fvd-sitio-wrap-btns', false);
                    msg('Atleta encontrado. Pulse Inscribir.', 'ok');
                    return;
                }
                msg('Respuesta inesperada.', 'err');
            })
            .catch(function () { msg('Error de red.', 'err'); });
    }
    function postInscribir(aid) {
        if (nominaSoloLectura) {
            return Promise.resolve({ ok: false, error: 'Periodo de cambios de nómina finalizado: solo consulta.' });
        }
        if (banderaMode && delegadoInscripcionCerrada) {
            return Promise.resolve({ ok: false, error: 'Periodo de inscripción cerrado para delegados según calendario del torneo.' });
        }
        var body = { action: 'inscribir', torneo_id: sitioLiveTorneoId(), tipo: 'individual', atleta_ids: [aid] };
        if (esFvd) body.asociacion_id = asocId;
        return fetch(api, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); });
    }
    function postRetirar(aid) {
        if (nominaSoloLectura) {
            return Promise.resolve({ ok: false, error: 'Periodo de cambios de nómina finalizado: solo consulta.' });
        }
        if (banderaMode && delegadoInscripcionCerrada) {
            return Promise.resolve({ ok: false, error: 'Periodo de retiros cerrado según calendario del torneo.' });
        }
        return fetch(api, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ action: 'retirar', torneo_id: sitioLiveTorneoId(), atleta_id: aid })
        }).then(function (r) { return r.json(); });
    }
    function postRetirarTabla(cedulaNum) {
        if (nominaSoloLectura) {
            return Promise.resolve({ ok: false, error: 'Periodo de cambios de nómina finalizado: solo consulta.' });
        }
        var body = { action: 'retirar_tabla', torneo_id: sitioLiveTorneoId(), cedula: cedulaNum };
        if (esFvd) body.asociacion_id = asocId;
        return fetch(api, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); });
    }
    function updateBadges() {
        var nd = document.querySelectorAll('#fvd-sitio-tbody-disp tr').length;
        var ni = document.querySelectorAll('#fvd-sitio-tbody-insc tr').length;
        var bd = qs('fvd-sitio-n-disp');
        var bi = qs('fvd-sitio-n-insc');
        if (bd) bd.textContent = String(nd);
        if (bi) bi.textContent = String(ni);
    }
    function retirarModeAfterInscripcion() {
        return banderaMode ? 'bandera' : 'tabla';
    }
    function marcarFilaInscrita(tr) {
        var cedAttr = parseInt(tr.getAttribute('data-cedula-num') || '0', 10);
        if (!cedAttr) {
            var cells = tr.querySelectorAll('td');
            var cedText = cells.length > 2 ? (cells[2].textContent || '').replace(/\D/g, '') : '';
            cedAttr = parseInt(cedText, 10) || 0;
            if (cedAttr) tr.setAttribute('data-cedula-num', String(cedAttr));
        }
        tr.setAttribute('data-retirar', retirarModeAfterInscripcion());
    }
    function appendInscRowDesdeAtleta(atleta) {
        if (!atleta || !atleta.id) return;
        var tbi = qs('fvd-sitio-tbody-insc');
        if (!tbi) return;
        var cedStr = String(atleta.cedula || '');
        var cedNum = parseInt(String(cedStr).replace(/\D/g, ''), 10) || 0;
        var tr = document.createElement('tr');
        tr.setAttribute('data-aid', String(atleta.id));
        tr.setAttribute('data-nombre', String(atleta.nombre || ''));
        tr.setAttribute('data-cedula-num', String(cedNum));
        tr.setAttribute('data-retirar', retirarModeAfterInscripcion());
        var t0 = document.createElement('td');
        var strong = document.createElement('strong');
        strong.textContent = String(atleta.nombre || '');
        t0.appendChild(strong);
        var t1 = document.createElement('td');
        t1.textContent = String(atleta.numfvd != null ? atleta.numfvd : '');
        var t2 = document.createElement('td');
        t2.textContent = cedStr;
        tr.appendChild(t0);
        tr.appendChild(t1);
        tr.appendChild(t2);
        if (nominaSoloLectura) {
            var t3 = document.createElement('td');
            t3.className = 'fvd-insc-sitio__lockcell';
            t3.title = 'Solo consulta';
            t3.textContent = '🔒';
            tr.appendChild(t3);
        }
        tbi.appendChild(tr);
        updateBadges();
    }

    document.addEventListener('DOMContentLoaded', function () {
        var cedEl = qs('fvd-sitio-ced');
        if (esInd && cedEl) {
            cedEl.addEventListener('blur', buscarCedula);
            cedEl.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); buscarCedula(); }
            });
        }
        var btnL = qs('fvd-sitio-limpiar');
        if (btnL) btnL.addEventListener('click', limpiarLinea);
        var btnI = qs('fvd-sitio-inscribir');
        if (btnI) btnI.addEventListener('click', function () {
            if (!usuarioEncontrado || !usuarioEncontrado.id) {
                msg('Busque primero por cédula.', 'warn');
                return;
            }
            msg('Inscribiendo…', 'info');
            btnI.disabled = true;
            postInscribir(usuarioEncontrado.id).then(function (d) {
                btnI.disabled = false;
                if (d && d.ok) {
                    var n = typeof d.inscritos === 'number' ? d.inscritos : 1;
                    if (n < 1) {
                        msg('No se registró cambio (p. ej. ya inscrito).', 'warn');
                        return;
                    }
                    msg('Inscripción registrada.', 'ok');
                    var tbd = qs('fvd-sitio-tbody-disp');
                    var trEx = tbd ? tbd.querySelector('tr[data-aid="' + String(usuarioEncontrado.id) + '"]') : null;
                    if (trEx && tbd) {
                        var tbi = qs('fvd-sitio-tbody-insc');
                        if (tbi) {
                            marcarFilaInscrita(trEx);
                            tbi.appendChild(trEx);
                            updateBadges();
                        }
                    } else {
                        appendInscRowDesdeAtleta(usuarioEncontrado);
                    }
                    limpiarLinea();
                } else {
                    msg((d && d.error) ? d.error : 'No se pudo inscribir.', 'err');
                }
            }).catch(function () { btnI.disabled = false; msg('Error de red.', 'err'); });
        });

        var tbd = qs('fvd-sitio-tbody-disp');
        if (tbd) {
            tbd.addEventListener('click', function (e) {
                if (nominaSoloLectura) {
                    msg('Periodo de cambios de nómina finalizado: solo consulta.', 'warn');
                    return;
                }
                if (banderaMode && delegadoInscripcionCerrada) {
                    msg('Periodo de inscripción cerrado para delegados según calendario del torneo.', 'warn');
                    return;
                }
                var tr = e.target.closest('tr');
                if (!tr || !tr.getAttribute('data-aid')) return;
                var aid = parseInt(tr.getAttribute('data-aid'), 10);
                if (!aid) return;

                if (!esInd) {
                    var fvd = window.FVD_INSC || {};
                    var cl = fvd.clase != null ? (fvd.clase | 0) : 0;
                    if (cl === 2 || cl === 3) {
                        if (typeof fvd.addToNomina !== 'function') {
                            msg('Espere a que cargue el formulario de nómina.', 'warn');
                            return;
                        }
                        var nom = tr.getAttribute('data-nombre') || '';
                        var ced = tr.getAttribute('data-cedula') || '';
                        var nfv = parseInt(tr.getAttribute('data-numfvd') || '0', 10) || 0;
                        var ok = fvd.addToNomina({
                            id: aid,
                            nombre: nom,
                            cedula: ced,
                            numfvd: nfv,
                            foto: '',
                            asociacion_nombre: ''
                        });
                        if (ok) {
                            msg('Añadido a la nómina. Complete el equipo y pulse Inscribir o Guardar equipo.', 'ok');
                        }
                        return;
                    }
                }

                tr.style.opacity = '0.6';
                postInscribir(aid).then(function (d) {
                    tr.style.opacity = '1';
                    if (d && d.ok) {
                        var n = typeof d.inscritos === 'number' ? d.inscritos : 1;
                        if (n < 1) {
                            msg('No se registró cambio (p. ej. ya inscrito).', 'warn');
                            return;
                        }
                        var tbi = qs('fvd-sitio-tbody-insc');
                        if (tbi) {
                            marcarFilaInscrita(tr);
                            tbi.appendChild(tr);
                            updateBadges();
                            msg('Atleta pasó a inscritos.', 'ok');
                        }
                    } else {
                        msg((d && d.error) ? d.error : 'Error al inscribir.', 'err');
                    }
                }).catch(function () { tr.style.opacity = '1'; msg('Error de red.', 'err'); });
            });
        }
        var tbi = qs('fvd-sitio-tbody-insc');
        if (tbi) {
            tbi.addEventListener('click', function (e) {
                if (nominaSoloLectura) {
                    msg('Periodo de cambios de nómina finalizado: solo consulta.', 'warn');
                    return;
                }
                var tr = e.target.closest('tr');
                if (!tr || !tr.getAttribute('data-aid')) return;
                var mode = tr.getAttribute('data-retirar') || '0';
                if (mode === '0') {
                    msg('Esta fila no admite retiro desde aquí (p. ej. pareja o equipo).', 'info');
                    return;
                }
                var nom = tr.getAttribute('data-nombre') || '';
                if (!window.confirm('¿Retirar inscripción de ' + nom + '?')) return;
                tr.style.opacity = '0.6';
                var done = function (d, netOk) {
                    tr.style.opacity = '1';
                    if (d && d.ok && netOk) {
                        tr.removeAttribute('data-retirar');
                        if (tbd) tbd.insertBefore(tr, tbd.firstChild);
                        updateBadges();
                        msg('Atleta vuelve a disponibles.', 'ok');
                    } else {
                        msg((d && d.error) ? d.error : 'No se pudo retirar.', 'err');
                    }
                };
                if (mode === 'bandera') {
                    var aid = parseInt(tr.getAttribute('data-aid'), 10);
                    if (!aid) { tr.style.opacity = '1'; return; }
                    postRetirar(aid).then(function (d) {
                        done(d, d && d.retirado);
                    }).catch(function () { tr.style.opacity = '1'; msg('Error de red.', 'err'); });
                } else if (mode === 'tabla') {
                    var ced = parseInt(tr.getAttribute('data-cedula-num') || '0', 10);
                    if (!ced) { tr.style.opacity = '1'; msg('Falta cédula numérica en la fila.', 'err'); return; }
                    postRetirarTabla(ced).then(function (d) {
                        done(d, d && d.retirado);
                    }).catch(function () { tr.style.opacity = '1'; msg('Error de red.', 'err'); });
                } else {
                    tr.style.opacity = '1';
                }
            });
        }

        var btnFin = qs('fvd-sitio-finalizar');
        var finMsg = qs('fvd-sitio-finish-msg');
        if (btnFin) {
            btnFin.addEventListener('click', function () {
                if (finMsg) finMsg.textContent = 'Cambios guardados.';
                msg('', '');
            });
        }

        function findGrupoSitio(eqKey) {
            var arr = window._FVD_SITIO_GRUPOS || [];
            for (var j = 0; j < arr.length; j++) {
                if ((arr[j].equipo | 0) === (eqKey | 0)) return arr[j];
            }
            return null;
        }
        var tbG = document.getElementById('fvd-sitio-tbody-insc-grupos');
        if (tbG) {
            tbG.addEventListener('click', function (e) {
                if (nominaSoloLectura || (delegadoInscripcionCerrada && (e.target.closest('.fvd-sitio-eq-edit') || e.target.closest('.fvd-sitio-eq-quit')))) {
                    msg('No puede modificar o retirar inscripciones en este periodo.', 'warn');
                    return;
                }
                var bV = e.target.closest('.fvd-sitio-eq-ver');
                var bE = e.target.closest('.fvd-sitio-eq-edit');
                var bQ = e.target.closest('.fvd-sitio-eq-quit');
                var k = 0;
                if (bV) k = parseInt(bV.getAttribute('data-equipo-key') || '0', 10);
                if (bE) k = parseInt(bE.getAttribute('data-equipo-key') || '0', 10);
                if (bQ) k = parseInt(bQ.getAttribute('data-equipo-key') || '0', 10);
                var g = k ? findGrupoSitio(k) : null;
                if (bV && g) {
                    var ul = document.getElementById('fvd-sitio-dlg-miembros-list');
                    var tit = document.getElementById('fvd-sitio-dlg-miembros-tit');
                    if (tit) tit.textContent = g.nombre_equipo || 'Integrantes';
                    if (ul) {
                        ul.innerHTML = '';
                        (g.integrantes || []).forEach(function (m) {
                            var li = document.createElement('li');
                            li.textContent = (m.nombre || '') + ' — Nº FVD ' + (m.numfvd != null ? m.numfvd : '—') + (m.cedula ? (' — ' + m.cedula) : '');
                            ul.appendChild(li);
                        });
                    }
                    var dlg = document.getElementById('fvd-sitio-dlg-miembros');
                    if (dlg) { dlg.style.display = 'block'; }
                    return;
                }
                if (bE && g && k > 0 && !g.es_legacy && typeof window.fvdCargarEdicionEquipo === 'function') {
                    window.fvdCargarEdicionEquipo(k, g.nombre_equipo, g.integrantes);
                    msg('Nómina cargada arriba. Pulse Guardar equipo para confirmar el cambio.', 'info');
                    return;
                }
                if (bQ && g && k > 0 && !g.es_legacy) {
                    if (!window.confirm('¿Retirar toda la inscripción de esta pareja o equipo?')) return;
                    var bodyQ = { action: 'retirar_equipo', torneo_id: sitioLiveTorneoId(), equipo: k };
                    if (esFvd && asocId) bodyQ.asociacion_id = asocId;
                    fetch(api, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(bodyQ) })
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            if (d && d.ok && d.retirado) { window.location.reload(); }
                            else { msg((d && d.error) ? d.error : 'No se pudo retirar el equipo.', 'err'); }
                        })
                        .catch(function () { msg('Error de red.', 'err'); });
                }
            });
            var cdlg = document.getElementById('fvd-sitio-dlg-miembros-cerrar');
            if (cdlg) cdlg.addEventListener('click', function () {
                var d = document.getElementById('fvd-sitio-dlg-miembros');
                if (d) d.style.display = 'none';
            });
        }
    });
})();
</script>
