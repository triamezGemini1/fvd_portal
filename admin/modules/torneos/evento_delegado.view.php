<?php
declare(strict_types=1);
/** @var array<string,mixed>|null $eventoTorneo */
/** @var int $id */
/** @var list<array<string,mixed>> $convocatoriaMiAsoc */
/** @var string $fvd_url_salir_torneo_ctx */
/** @var string $fvd_url_pdf_invitacion */
/** @var int $fvd_delegado_notif_id */
/** @var string $fvd_delegado_invitacion_archivo */
/** @var string $fvdDelegadoUrlInscribirTorneo */
/** @var string $fvdDelegadoUrlAdminInscritos */
/** @var string $fvdDelegadoUrlAfiliadosCarnet */
/** @var string $fvdDelegadoUrlAfiliadosTraspaso */
/** @var string $fvdDelegadoUrlAfiliadosAfiliacion */
/** @var string $fvdDelegadoUrlNuevoAtleta */
/** @var string $fvdDelegadoDashUrl */
/** @var bool $fvdDelegadoFase1Ok */
/** @var bool $fvdDelegadoFase2Ok */
/** @var string $fvdDelegadoMotivoFase1 */
/** @var string $fvdDelegadoMotivoFase2 */
/** @var bool $fvdDelegadoCampeonatoOk */
/** @var string $fvdDelegadoAvisoCampeonato */
/** @var list<array<string,mixed>> $fvdDelegadoTorneosAlternativas */

$t = $eventoTorneo;
$tidTorneo = (int) $id;
$urlPublicTorneo = url('torneo_publico.php?id=' . $tidTorneo);

$fvdDelegadoFase1Ok = $fvdDelegadoFase1Ok ?? true;
$fvdDelegadoFase2Ok = $fvdDelegadoFase2Ok ?? true;
$fvdDelegadoMotivoFase1 = $fvdDelegadoMotivoFase1 ?? '';
$fvdDelegadoMotivoFase2 = $fvdDelegadoMotivoFase2 ?? '';
$fvdDelegadoCampeonatoOk = $fvdDelegadoCampeonatoOk ?? true;
$fvdDelegadoAvisoCampeonato = $fvdDelegadoAvisoCampeonato ?? '';
$qAf = ['action' => 'list', 'alcance' => 'asociacion', 'asociacion_id' => 0];
$fvdDelegadoUrlAfiliadosCarnet = $fvdDelegadoUrlAfiliadosCarnet ?? fvd_master_module_url('atletas/index.php?' . http_build_query($qAf + ['marcador' => 'carnet']));
$fvdDelegadoUrlAfiliadosTraspaso = $fvdDelegadoUrlAfiliadosTraspaso ?? fvd_master_module_url('atletas/index.php?' . http_build_query($qAf + ['marcador' => 'traspaso']));
$fvdDelegadoUrlAfiliadosAfiliacion = $fvdDelegadoUrlAfiliadosAfiliacion ?? fvd_master_module_url('atletas/index.php?' . http_build_query($qAf + ['marcador' => 'afiliacion']));
$fvdDelegadoUrlNuevoAtleta = $fvdDelegadoUrlNuevoAtleta ?? fvd_master_module_url('atletas/index.php?action=form');
$fvdDelegadoUrlInscribirTorneo = $fvdDelegadoUrlInscribirTorneo ?? fvd_master_module_url('torneo_inscripcion/index.php?torneo_id=' . $tidTorneo);
$fvdDelegadoTorneosAlternativas = isset($fvdDelegadoTorneosAlternativas) && is_array($fvdDelegadoTorneosAlternativas) ? $fvdDelegadoTorneosAlternativas : [];
$fvdDelegadoUrlAdminInscritos = $fvdDelegadoUrlAdminInscritos ?? fvd_master_module_url('inscripcion_torneo/index.php?torneo_id=' . $tidTorneo);
$fvdDelegadoDashUrl = $fvdDelegadoDashUrl ?? url('fvdmasteradmin/delegado_dashboard_new.php');

$fvdTipoGenRaw = $t !== null ? (int) ($t['tipo'] ?? 1) : 1;
$fvdTipoGenLab = [1 => 'Masculino', 2 => 'Femenino', 3 => 'Mixto'][$fvdTipoGenRaw] ?? '—';
$fvdEsCampeonatoEv = $t !== null && isset($t['es_campeonato']) && (int) $t['es_campeonato'] === 1;
$fvdTipoLab = $fvdTipoGenLab . ($fvdEsCampeonatoEv ? ' · Campeonato' : '');
$fvdClaseLab = $t !== null ? ([1 => 'Individual', 2 => 'Parejas', 3 => 'Equipos'][(int) ($t['clase'] ?? 1)] ?? '—') : '—';

$numInscritosMiAsoc = 0;
$invitadoMi = '';
foreach ($convocatoriaMiAsoc as $cm) {
    $numInscritosMiAsoc += (int) ($cm['num_inscritos'] ?? 0);
    if ($invitadoMi === '' && !empty($cm['invitado_en'])) {
        $invitadoMi = (string) $cm['invitado_en'];
    }
}
?>
<div class="fvd-torneo-evento fvd-torneo-evento--delegado">
    <div class="fvd-mis-panel" style="margin-bottom:12px;padding:10px 12px;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.35);border-radius:8px;max-width:56rem">
        <strong>Modo torneo</strong> — Panel reducido al evento de su invitación.
        <a href="<?= htmlspecialchars($fvdDelegadoDashUrl, ENT_QUOTES, 'UTF-8') ?>">Resumen delegado</a>
        ·
        <a href="<?= htmlspecialchars($fvd_url_salir_torneo_ctx, ENT_QUOTES, 'UTF-8') ?>">Salir del modo torneo</a>
    </div>

<?php if ($t === null): ?>
    <h1 class="fvd-torneo-evento__title">Torneo no encontrado</h1>
    <p><a class="fvd-torneo-evento__link" href="<?= htmlspecialchars($fvdDelegadoDashUrl, ENT_QUOTES, 'UTF-8') ?>">Volver al panel</a></p>
<?php else: ?>
    <div class="fvd-mis-panel">
        <nav class="fvd-mis-panel__crumb" aria-label="Ruta">
            <a href="<?= htmlspecialchars($fvdDelegadoDashUrl, ENT_QUOTES, 'UTF-8') ?>">← Resumen delegado</a>
        </nav>

        <?php if ($fvdDelegadoTorneosAlternativas !== []): ?>
        <div class="fvd-mis-panel__strip" style="margin-top:10px">
            <div class="fvd-mis-panel__strip-col" style="max-width:none;flex:1">
                <span class="fvd-mis-panel__strip-label">Elegir torneo (misma invitación / grupo)</span>
                <div class="fvd-mis-card__actions" style="margin-top:8px;flex-wrap:wrap">
                    <?php foreach ($fvdDelegadoTorneosAlternativas as $altTor): ?>
                        <?php
                        $oid = (int) ($altTor['torneo_id'] ?? 0);
                        if ($oid <= 0) {
                            continue;
                        }
                        $onom = trim((string) ($altTor['torneo_nombre'] ?? ''));
                        if ($onom === '') {
                            $onom = 'Torneo #' . $oid;
                        }
                        $tpChip = (int) ($altTor['tipo'] ?? 0);
                        $labGen = $tpChip === 2 ? 'F' : ($tpChip === 3 ? 'Mixto' : ($tpChip === 1 ? 'M' : ''));
                        $esAct = $oid === $tidTorneo;
                        $gAlt = (int) ($altTor['grupo_evento_id'] ?? 0);
                        $uEv = fvd_torneo_evento_url($oid, $gAlt > 0 ? $gAlt : 0);
                        ?>
                        <a href="<?= htmlspecialchars($uEv, ENT_QUOTES, 'UTF-8') ?>"
                           class="fvd-mis-btn <?= $esAct ? 'fvd-mis-btn--indigo' : 'fvd-mis-btn--slate' ?>"
                           style="width:auto;padding:7px 12px;font-size:0.8125rem"
                           <?php if ($esAct): ?>aria-current="page"<?php endif; ?>
                           title="<?= $esAct ? 'Torneo actual' : 'Cambiar a este torneo' ?>">
                            #<?= $oid ?><?= $labGen !== '' ? ' · ' . htmlspecialchars($labGen, ENT_QUOTES, 'UTF-8') : '' ?>
                            — <?= htmlspecialchars($onom, ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($fvdDelegadoAvisoCampeonato !== ''): ?>
            <p class="fvd-mod-msg fvd-torneo-evento__warn" style="margin-top:8px"><?= htmlspecialchars($fvdDelegadoAvisoCampeonato, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <div class="fvd-mis-panel__grid" style="margin-top:10px">
            <div class="fvd-mis-card fvd-mis-panel__wide" style="grid-column:1/-1">
                <div class="fvd-mis-card__head fvd-mis-card__head--mesas">Torneo (invitación)</div>
                <div class="fvd-mis-card__body">
                    <header class="fvd-mis-panel__hero" style="padding:0;margin:0 0 12px;border:0;background:transparent">
                        <?php
                        $fvdEvDelegHeroEsH2 = function_exists('fvd_delegado_inner_heading_visible') && !fvd_delegado_inner_heading_visible();
                        $fvdEvDelegHeroNombre = htmlspecialchars((string) ($t['nombre'] ?? 'Torneo'), ENT_QUOTES, 'UTF-8');
                        ?>
                        <?php if ($fvdEvDelegHeroEsH2): ?>
                        <h2 class="fvd-mis-panel__title" style="font-size:1.35rem"><?= $fvdEvDelegHeroNombre ?></h2>
                        <?php else: ?>
                        <h1 class="fvd-mis-panel__title" style="font-size:1.35rem"><?= $fvdEvDelegHeroNombre ?></h1>
                        <?php endif; ?>
                        <div class="fvd-mis-panel__hero-meta">
                            <span>ID #<?= $tidTorneo ?></span>
                            <span><?= htmlspecialchars(substr((string) ($t['fechator'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if (!empty($t['lugar'])): ?><span><?= htmlspecialchars((string) $t['lugar'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                            <span><?= htmlspecialchars($fvdTipoLab, ENT_QUOTES, 'UTF-8') ?></span>
                            <span><?= htmlspecialchars($fvdClaseLab, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </header>
                    <p class="fvd-mis-card__hint" style="margin-top:0">
                        <?php if ($invitadoMi !== ''): ?>
                            Invitación registrada: <strong><?= htmlspecialchars($invitadoMi, ENT_QUOTES, 'UTF-8') ?></strong>
                            · Inscritos (convocatoria): <strong><?= (int) $numInscritosMiAsoc ?></strong>
                        <?php else: ?>
                            Sin fecha de invitación en convocatoria para su club; si debe constar, contacte a la FVD.
                        <?php endif; ?>
                        · <a class="fvd-mis-panel__strip-link" href="<?= htmlspecialchars($urlPublicTorneo, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Ficha pública</a>
                    </p>
                    <?php if ($fvd_delegado_notif_id > 0 && trim($fvd_delegado_invitacion_archivo) !== ''): ?>
                        <p style="margin:0 0 14px">
                            <a class="fvd-mis-btn fvd-mis-btn--cyan" style="width:auto;display:inline-flex" href="<?= htmlspecialchars($fvd_url_pdf_invitacion, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">PDF de invitación</a>
                        </p>
                    <?php endif; ?>

                    <p class="fvd-mis-card__hint" style="margin-bottom:10px">
                        Los dos primeros botones abren el <strong>listado de afiliados de su asociación</strong> con el marcador correspondiente y el <strong>filtro por género del torneo</strong> (M / F / mixto) cuando aplica. El tercero abre el mismo listado filtrado por <strong>afiliación pendiente</strong>; el enlace auxiliar lleva al <strong>alta de un atleta nuevo</strong>.
                    </p>
                    <div class="fvd-mis-card__actions">
                        <?php if ($fvdDelegadoFase1Ok): ?>
                            <a class="fvd-mis-btn fvd-mis-btn--violet" href="<?= htmlspecialchars($fvdDelegadoUrlAfiliadosCarnet, ENT_QUOTES, 'UTF-8') ?>">Solicitar carnets</a>
                            <a class="fvd-mis-btn fvd-mis-btn--rose" href="<?= htmlspecialchars($fvdDelegadoUrlAfiliadosTraspaso, ENT_QUOTES, 'UTF-8') ?>">Traspasos</a>
                            <a class="fvd-mis-btn fvd-mis-btn--indigo" href="<?= htmlspecialchars($fvdDelegadoUrlAfiliadosAfiliacion, ENT_QUOTES, 'UTF-8') ?>">Afiliaciones / nuevo atleta</a>
                        <?php else: ?>
                            <span class="fvd-mis-btn fvd-mis-btn--violet" style="opacity:.55;cursor:not-allowed" title="<?= htmlspecialchars($fvdDelegadoMotivoFase1, ENT_QUOTES, 'UTF-8') ?>">Solicitar carnets</span>
                            <span class="fvd-mis-btn fvd-mis-btn--rose" style="opacity:.55;cursor:not-allowed" title="<?= htmlspecialchars($fvdDelegadoMotivoFase1, ENT_QUOTES, 'UTF-8') ?>">Traspasos</span>
                            <span class="fvd-mis-btn fvd-mis-btn--indigo" style="opacity:.55;cursor:not-allowed" title="<?= htmlspecialchars($fvdDelegadoMotivoFase1, ENT_QUOTES, 'UTF-8') ?>">Afiliaciones / nuevo atleta</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($fvdDelegadoFase1Ok): ?>
                        <p style="margin:10px 0 0;font-size:0.8125rem">
                            <a href="<?= htmlspecialchars($fvdDelegadoUrlNuevoAtleta, ENT_QUOTES, 'UTF-8') ?>" class="fvd-mis-panel__strip-link">Formulario de alta de atleta (sin pasar por el listado) →</a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="fvd-mis-card fvd-mis-panel__wide" style="grid-column:1/-1">
                <div class="fvd-mis-card__head fvd-mis-card__head--ops">Operaciones</div>
                <div class="fvd-mis-card__body fvd-mis-card__actions">
                    <p class="fvd-mis-card__hint" style="margin-top:0">
                        Inscripción como en el <strong>sitio</strong> (disponibles e inscritos) y administración de cambios y retiros, siempre respecto al <strong>torneo activo</strong> y al tipo de torneo (filtros de elegibles e inscritos según género del evento cuando aplica).
                    </p>
                    <?php if ($fvdDelegadoFase2Ok && $fvdDelegadoCampeonatoOk): ?>
                        <a class="fvd-mis-btn fvd-mis-btn--amber" href="<?= htmlspecialchars($fvdDelegadoUrlInscribirTorneo . (str_contains($fvdDelegadoUrlInscribirTorneo, '#') ? '' : '#fvd-insc-sitio-inscribir'), ENT_QUOTES, 'UTF-8') ?>">Inscribir jugadores</a>
                    <?php else: ?>
                        <span class="fvd-mis-btn fvd-mis-btn--amber" style="opacity:.55;cursor:not-allowed" title="<?= htmlspecialchars(!$fvdDelegadoFase2Ok ? $fvdDelegadoMotivoFase2 : 'Falta campeonato en contexto (grupo de evento).', ENT_QUOTES, 'UTF-8') ?>">Inscribir jugadores</span>
                    <?php endif; ?>
                    <?php if ($fvdDelegadoFase2Ok): ?>
                        <a class="fvd-mis-btn fvd-mis-btn--indigo" href="<?= htmlspecialchars($fvdDelegadoUrlAdminInscritos, ENT_QUOTES, 'UTF-8') ?>">Administrador de inscripciones</a>
                    <?php else: ?>
                        <span class="fvd-mis-btn fvd-mis-btn--indigo" style="opacity:.55;cursor:not-allowed" title="<?= htmlspecialchars($fvdDelegadoMotivoFase2, ENT_QUOTES, 'UTF-8') ?>">Administrador de inscripciones</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>
