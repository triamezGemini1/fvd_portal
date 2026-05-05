<?php

declare(strict_types=1);

/** @var array $delegSnap */
/** @var string $appBase */
/** @var list<array<string,mixed>> $delegNotifs */
/** @var int $delegNotifNoVistas */
/** @var int $campeonatoIdInt */
/** @var string $nombreCampeonatoNominal */
/** @var list<array<string,mixed>> $fvdDelegadoGrupoTorneos */
/** @var array<string,mixed>|null $balanceCampeonato */
/** @var int $totalAtletasAsoc */
/** @var int $nPendTrasp */
/** @var int $carnetFaltan */
/** @var string $inscripcionApiUrl */
/** @var string $fvd_campeonato_q */

$tidInt = (int) ($delegSnap['torneo_id'] ?? 0);
$tnom = (string) ($delegSnap['torneo_nombre'] ?? '');

$myAidNav = AuthService::idAsociacion();
$myAidInt = ($myAidNav !== null && (int) $myAidNav > 0) ? (int) $myAidNav : 0;

$qTorneoVinc = [];
if ($campeonatoIdInt > 0) {
    $qTorneoVinc['campeonato_id'] = $campeonatoIdInt;
}
$urlTorneosVinculados = fvd_module_url(
    'torneo_inscripcion/index.php' . ($qTorneoVinc !== [] ? '?' . http_build_query($qTorneoVinc) : '')
) . '#fvd-insc-sitio-inscribir';

$fvd_q_nuevo = ['action' => 'form'];
$fvd_q_lista = ['action' => 'list'];
if ($myAidInt > 0) {
    $fvd_q_nuevo['asociacion_id'] = $myAidInt;
    $fvd_q_lista['asociacion_id'] = $myAidInt;
}
$urlNuevoAtleta = fvd_module_url('atletas/index.php?' . http_build_query($fvd_q_nuevo));
$urlListadoAtletas = fvd_module_url('atletas/index.php?' . http_build_query($fvd_q_lista));

$urlBandejaTraspasos = $appBase . '/fvdmasteradmin/delegado_bandeja_traspasos.php'
    . (isset($fvd_deleg_asoc_q) ? (string) $fvd_deleg_asoc_q : '');
$urlEstadoCarnets = $appBase . '/fvdmasteradmin/delegado_estado_carnetizacion.php'
    . (isset($fvd_deleg_asoc_q) ? (string) $fvd_deleg_asoc_q : '');

$fvdFmtN = static fn (float $v): string => number_format($v, 2, ',', '.');

$totFin = is_array($balanceCampeonato) ? ($balanceCampeonato['totales'] ?? []) : [];
$deudaEur = $totFin['monto_total_eur'] ?? null;
$pagadoEur = isset($totFin['pagado_eur']) ? (float) $totFin['pagado_eur'] : 0.0;
$saldoEurFin = $totFin['saldo_eur'] ?? null;
$deudaBs = isset($totFin['monto_total_bs']) ? (float) $totFin['monto_total_bs'] : 0.0;

?>
<div class="fvd-card">
<div class="fvd-dd-root" id="fvd-deleg-guia-inscripcion">

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'notif_no'): ?>
        <p class="fvd-mod-msg">No se encontró la notificación o ya no aplica.</p>
    <?php endif; ?>

    <?php if (($delegNotifNoVistas ?? 0) > 0): ?>
        <div class="fvd-deleg-alert-invites fvd-dd-alert-invites" role="status" aria-live="polite">
            <span class="fvd-deleg-alert-invites__text"><strong>Nueva invitación a torneo.</strong> Tiene invitaciones pendientes de revisar. Revise el bloque <a href="#fvd-deleg-torneos-invites">Invitaciones</a> o el enlace en la barra superior.</span>
        </div>
    <?php endif; ?>

    <?php if ($campeonatoIdInt > 0 && $nombreCampeonatoNominal !== ''): ?>
        <section class="fvd-dd-banner" aria-label="Campeonato en contexto">
            <h1 class="fvd-dd-banner__title"><?= htmlspecialchars($nombreCampeonatoNominal, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="fvd-dd-banner__sub">Campeonato en sesión · torneo activo: <?= $tnom !== '' ? htmlspecialchars($tnom, ENT_QUOTES, 'UTF-8') : '—' ?></p>
            <?php if ($fvdDelegadoGrupoTorneos !== []): ?>
                <div class="fvd-dd-switch" role="group" aria-label="Elegir rama del campeonato">
                    <span style="font-size:.75rem;color:rgba(226,232,240,.85);display:block;margin-bottom:8px">Categoría (switcher)</span>
                    <div class="fvd-dd-switch__bg">
                        <?php foreach ($fvdDelegadoGrupoTorneos as $tg): ?>
                            <?php
                            $tgId = (int) ($tg['torneo'] ?? 0);
                            if ($tgId <= 0) {
                                continue;
                            }
                            $short = (string) ($tg['nombre_corta'] ?? $tg['nombre'] ?? '');
                            $isActive = $tidInt > 0 && $tidInt === $tgId;
                            ?>
                            <button type="button" class="fvd-dd-switch__btn<?= $isActive ? ' fvd-dd-switch__btn--on' : '' ?>"
                                data-torneo-id="<?= $tgId ?>"
                                data-campeonato-id="<?= (int) $campeonatoIdInt ?>">
                                <?= htmlspecialchars($short, ENT_QUOTES, 'UTF-8') ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    <?php elseif ($campeonatoIdInt > 0): ?>
        <section class="fvd-dd-banner" aria-label="Campeonato en contexto">
            <h1 class="fvd-dd-banner__title">Campeonato #<?= (int) $campeonatoIdInt ?></h1>
            <p class="fvd-dd-banner__sub">Sin nombre nominal en base de datos. Torneo en contexto: <?= $tnom !== '' ? htmlspecialchars($tnom, ENT_QUOTES, 'UTF-8') : '—' ?></p>
        </section>
    <?php endif; ?>

    <div class="fvd-dd-finance-outer">
            <section class="fvd-dd-finance fvd-dd-shadow-sm" aria-label="Balance global del campeonato">
                <div class="fvd-dd-finance__head">
                    <h2 class="fvd-dd-finance__title"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i> Balance del campeonato</h2>
                    <?php if ($campeonatoIdInt > 0 && $nombreCampeonatoNominal !== ''): ?>
                        <p class="fvd-dd-finance__sub"><?= htmlspecialchars($nombreCampeonatoNominal, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php elseif ($campeonatoIdInt > 0): ?>
                        <p class="fvd-dd-finance__sub">ID grupo: <?= (int) $campeonatoIdInt ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($campeonatoIdInt > 0 && is_array($balanceCampeonato) && $myAidInt > 0): ?>
                    <div class="fvd-dd-finance__grid">
                        <div class="fvd-dd-finance__box fvd-dd-finance__box--deuda">
                            <span class="fvd-dd-finance__k">Total deuda (referencia)</span>
                            <?php if ($deudaEur !== null && (float) $deudaEur > 0): ?>
                                <span class="fvd-dd-finance__v"><?= $fvdFmtN((float) $deudaEur) ?> €</span>
                            <?php else: ?>
                                <span class="fvd-dd-finance__v"><?= $fvdFmtN($deudaBs) ?> Bs</span>
                                <span class="fvd-dd-finance__hint">Sin total EUR en deuda_asociaciones</span>
                            <?php endif; ?>
                        </div>
                        <div class="fvd-dd-finance__box fvd-dd-finance__box--pagado">
                            <span class="fvd-dd-finance__k">Total pagado</span>
                            <span class="fvd-dd-finance__v"><?= $fvdFmtN($pagadoEur) ?> €</span>
                        </div>
                        <div class="fvd-dd-finance__box fvd-dd-finance__box--saldo">
                            <span class="fvd-dd-finance__k">Saldo pendiente</span>
                            <?php if ($saldoEurFin !== null): ?>
                                <span class="fvd-dd-finance__v"><?= $fvdFmtN((float) $saldoEurFin) ?> €</span>
                            <?php else: ?>
                                <span class="fvd-dd-finance__v">—</span>
                                <span class="fvd-dd-finance__hint">Defina montos EUR por torneo para ver saldo</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="fvd-dd-finance__empty">Seleccione un campeonato en contexto (desde inscripciones o el interruptor de rama) para ver deuda, pagos y saldo de su club.</p>
                <?php endif; ?>
            </section>
    </div>

    <div class="fvd-dd-mosaic">
        <div class="fvd-dd-mosaic__cell">
            <div class="fvd-dd-card fvd-dd-card--insc-light fvd-dd-shadow-sm">
                <div class="fvd-dd-card__row">
                    <div class="fvd-dd-card__icon fvd-dd-card__icon--xl" aria-hidden="true"><i class="fa-solid fa-trophy"></i></div>
                    <div class="fvd-dd-card__body">
                        <p class="fvd-dd-card__label">Inscripciones</p>
                        <h2 class="fvd-dd-card__title">Torneos del campeonato</h2>
                        <p class="fvd-dd-card__meta">Vincule ramas e inscriba con el formulario en sitio del torneo; la URL debe incluir <code class="fvd-dd-code">campeonato_id</code> (grupo de evento o ID de torneo del campeonato) y <code class="fvd-dd-code">torneo_id</code>.</p>
                    </div>
                </div>
                <div class="fvd-dd-card__actions">
                    <?php if ($campeonatoIdInt > 0): ?>
                        <a class="fvd-btn fvd-btn--primary" href="<?= htmlspecialchars($urlTorneosVinculados, ENT_QUOTES, 'UTF-8') ?>">Gestionar torneos vinculados</a>
                    <?php else: ?>
                        <span class="fvd-btn fvd-btn--secondary fvd-dd-btn--disabled" title="Fije primero un campeonato en contexto">Gestionar torneos vinculados</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="fvd-dd-mosaic__cell">
            <div class="fvd-dd-card fvd-dd-card--asoc fvd-dd-shadow-sm">
                <div class="fvd-dd-card__row">
                    <div class="fvd-dd-card__icon" aria-hidden="true"><i class="fa-solid fa-users"></i></div>
                    <div class="fvd-dd-card__body">
                        <p class="fvd-dd-card__label">Mi asociación</p>
                        <h2 class="fvd-dd-card__title">Atletas del club</h2>
                        <p class="fvd-dd-card__meta">Total en su asociación: <strong><?= number_format($totalAtletasAsoc, 0, ',', '.') ?></strong></p>
                    </div>
                </div>
                <div class="fvd-dd-card__actions fvd-dd-card__actions--split">
                    <a class="fvd-btn fvd-btn--primary" href="<?= htmlspecialchars($urlNuevoAtleta, ENT_QUOTES, 'UTF-8') ?>">Ingresar nuevo atleta</a>
                    <a class="fvd-btn fvd-btn--secondary" href="<?= htmlspecialchars($urlListadoAtletas, ENT_QUOTES, 'UTF-8') ?>">Ver listado</a>
                </div>
            </div>
        </div>
        <div class="fvd-dd-mosaic__cell">
            <div class="fvd-dd-card fvd-dd-card--tras fvd-dd-shadow-sm">
                <div class="fvd-dd-card__row">
                    <div class="fvd-dd-card__icon" aria-hidden="true"><i class="fa-solid fa-right-left"></i></div>
                    <div class="fvd-dd-card__body">
                        <p class="fvd-dd-card__label">Traspasos</p>
                        <h2 class="fvd-dd-card__title">
                            Solicitudes pendientes
                            <?php if ($nPendTrasp > 0): ?>
                                <span class="fvd-dd-badge" aria-label="Pendientes"><?= (int) $nPendTrasp ?></span>
                            <?php else: ?>
                                <span class="fvd-dd-badge fvd-dd-badge--muted" aria-label="Sin pendientes">0</span>
                            <?php endif; ?>
                        </h2>
                        <p class="fvd-dd-card__meta">Apruebe o rechace traspasos entrantes a su club.</p>
                    </div>
                </div>
                <div class="fvd-dd-card__actions">
                    <a class="fvd-btn fvd-btn--primary" href="<?= htmlspecialchars($urlBandejaTraspasos, ENT_QUOTES, 'UTF-8') ?>">Abrir bandeja</a>
                </div>
            </div>
        </div>
        <div class="fvd-dd-mosaic__cell">
            <div class="fvd-dd-card fvd-dd-card--car fvd-dd-shadow-sm">
                <div class="fvd-dd-card__row">
                    <div class="fvd-dd-card__icon" aria-hidden="true"><i class="fa-solid fa-id-card"></i></div>
                    <div class="fvd-dd-card__body">
                        <p class="fvd-dd-card__label">Carnets</p>
                        <h2 class="fvd-dd-card__title">Carnetización</h2>
                        <p class="fvd-dd-card__meta">Activos sin carnet procesado: <strong><?= (int) $carnetFaltan ?></strong></p>
                    </div>
                </div>
                <div class="fvd-dd-card__actions">
                    <a class="fvd-btn fvd-btn--primary" href="<?= htmlspecialchars($urlEstadoCarnets, ENT_QUOTES, 'UTF-8') ?>">Ver reporte de carnetización</a>
                </div>
            </div>
        </div>
    </div>

    <?php if (($delegNotifs ?? []) !== []): ?>
        <section id="fvd-deleg-torneos-invites" class="fvd-dd-invites fvd-deleg-notif-wrap" aria-label="Invitaciones a torneos">
            <h2 class="fvd-deleg-notif-wrap__h">Invitaciones a torneos<?= ($delegNotifNoVistas ?? 0) > 0 ? ' <span style="font-size:0.75rem;font-weight:700;color:var(--fvd-rojo,#b91c1c)">(pendientes)</span>' : '' ?></h2>
            <p class="fvd-deleg-notif-wrap__p" style="font-size:0.8125rem">PDF y acceso al panel del torneo.</p>
            <ul class="fvd-deleg-notif-wrap__ul">
                <?php foreach ($delegNotifs as $nf): ?>
                    <?php
                    $nid = (int) ($nf['id'] ?? 0);
                    $titUl = trim((string) ($nf['titulo_notificacion'] ?? ''));
                    if ($titUl === '') {
                        $titUl = trim((string) ($nf['torneo_nombre'] ?? ''));
                    }
                    $tn = htmlspecialchars($titUl !== '' ? $titUl : 'Invitación', ENT_QUOTES, 'UTF-8');
                    $esGr = !empty($nf['es_grupo_agrupado']);
                    $det = (isset($nf['detalle_torneos']) && is_array($nf['detalle_torneos'])) ? $nf['detalle_torneos'] : [];
                    $fd = htmlspecialchars(substr((string) ($nf['fechator'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8');
                    $sinAbrir = empty($nf['visto_en']);
                    $entrar = $appBase . '/fvdmasteradmin/delegado_entrar_torneo.php?notif_id=' . $nid;
                    $pdf = $appBase . '/fvdmasteradmin/delegado_invitacion_pdf.php?notif_id=' . $nid;
                    ?>
                    <li class="fvd-deleg-notif-wrap__li">
                        <span class="fvd-deleg-notif-wrap__tn"><?= $tn ?></span>
                        <span class="fvd-deleg-notif-wrap__fd"><?= $fd ?></span>
                        <?php if ($sinAbrir): ?><span class="fvd-deleg-notif-wrap__new">Nuevo</span><?php endif; ?>
                        <?php if ($esGr && count($det) > 1): ?>
                            <p style="margin:0.4rem 0 0;font-size:0.78rem;color:var(--fvd-muted);line-height:1.35"><?= (int) ($nf['n_en_grupo'] ?? count($det)) ?> categorías en este evento. Acceda al panel por la rama que corresponda:</p>
                            <div style="margin-top:0.45rem;display:flex;flex-wrap:wrap;gap:0.4rem;align-items:center;">
                                <?php if (!empty($nf['invitacion_archivo'])): ?>
                                    <a class="fvd-btn fvd-btn--secondary fvd-deleg-notif-wrap__btn" href="<?= htmlspecialchars($pdf, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">PDF</a>
                                <?php endif; ?>
                                <?php foreach ($det as $dtr): ?>
                                    <?php
                                    $nidD = (int) ($dtr['id'] ?? 0);
                                    $tidD = (int) ($dtr['torneo_id'] ?? 0);
                                    if ($nidD <= 0 || $tidD <= 0) {
                                        continue;
                                    }
                                    $lab = trim((string) ($dtr['rama'] ?? ''));
                                    if ($lab === '') {
                                        $lab = 'Torneo #' . $tidD;
                                    }
                                    $h = $appBase . '/fvdmasteradmin/delegado_entrar_torneo.php?notif_id=' . $nidD;
                                    ?>
                                    <a class="fvd-btn fvd-btn--primary fvd-deleg-notif-wrap__btn" href="<?= htmlspecialchars($h, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                        <span class="fvd-deleg-notif-wrap__sp"></span>
                        <?php if (!empty($nf['invitacion_archivo'])): ?>
                            <a class="fvd-btn fvd-btn--secondary fvd-deleg-notif-wrap__btn" href="<?= htmlspecialchars($pdf, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">PDF</a>
                        <?php endif; ?>
                        <a class="fvd-btn fvd-btn--primary fvd-deleg-notif-wrap__btn" href="<?= htmlspecialchars($entrar, ENT_QUOTES, 'UTF-8') ?>">Panel del torneo</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</div>
</div>

<?php if ($campeonatoIdInt > 0 && $fvdDelegadoGrupoTorneos !== []): ?>
<script>
(function () {
    var root = document.querySelector('.fvd-dd-switch');
    if (!root) return;
    var api = <?= json_encode($inscripcionApiUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var campeonatoId = <?= (int) $campeonatoIdInt ?>;
    root.querySelectorAll('.fvd-dd-switch__btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tid = parseInt(btn.getAttribute('data-torneo-id'), 10);
            if (!tid || !campeonatoId) return;
            var sep = api.indexOf('?') >= 0 ? '&' : '?';
            var u = api + sep + 'action=delegado_inscripcion_panel&torneo_id=' + encodeURIComponent(String(tid)) + '&campeonato_id=' + encodeURIComponent(String(campeonatoId));
            fetch(u, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d || !d.ok) {
                        window.alert((d && d.error) ? d.error : 'No se pudo cambiar de torneo.');
                        return;
                    }
                    window.location.reload();
                })
                .catch(function () {
                    window.alert('Error de red al cambiar de torneo.');
                });
        });
    });
})();
</script>
<?php endif; ?>
