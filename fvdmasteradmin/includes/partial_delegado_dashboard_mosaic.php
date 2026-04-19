<?php

declare(strict_types=1);

/** @var array $delegSnap */
/** @var string $appBase */
/** @var string $urlRegistrarAtleta */
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

$urlTorneoInscripcion = fvd_module_url('torneo_inscripcion/index.php')
    . (isset($fvd_deleg_torneo_q) && $fvd_deleg_torneo_q !== ''
        ? $fvd_deleg_torneo_q
        : ($tidInt > 0 ? '?torneo_id=' . $tidInt : ''));

$fvd_q_afiliados = ['action' => 'form'];
$myAidNav = AuthService::idAsociacion();
if ($myAidNav !== null && (int) $myAidNav > 0) {
    $fvd_q_afiliados['asociacion_id'] = (int) $myAidNav;
}
$urlAfiliados = fvd_module_url('atletas/index.php?' . http_build_query($fvd_q_afiliados));

$urlBandejaTraspasos = $appBase . '/fvdmasteradmin/delegado_bandeja_traspasos.php'
    . (isset($fvd_deleg_asoc_q) ? (string) $fvd_deleg_asoc_q : '');
$urlEstadoCarnets = $appBase . '/fvdmasteradmin/delegado_estado_carnetizacion.php'
    . (isset($fvd_deleg_asoc_q) ? (string) $fvd_deleg_asoc_q : '');

$saldoLinea = 'Sin datos de saldo';
if ($campeonatoIdInt > 0 && is_array($balanceCampeonato)) {
    $totB = $balanceCampeonato['totales'] ?? [];
    $saldoEur = $totB['saldo_eur'] ?? null;
    if ($saldoEur !== null) {
        $saldoLinea = 'Saldo pendiente: ' . number_format((float) $saldoEur, 2, ',', '.') . ' €';
    } else {
        $saldoLinea = 'Revise deudas y pagos en inscripción al torneo';
    }
} elseif ($campeonatoIdInt <= 0) {
    $saldoLinea = 'Fije un campeonato en contexto (inscripciones) para ver el balance';
}

$fvdFmtN = static fn (float $v): string => number_format($v, 2, ',', '.');

?>
<div class="fvd-dd-root container-fluid px-0" id="fvd-deleg-guia-inscripcion">

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'notif_no'): ?>
        <p class="fvd-mod-msg">No se encontró la notificación o ya no aplica.</p>
    <?php endif; ?>

    <?php if (($delegNotifNoVistas ?? 0) > 0): ?>
        <div class="fvd-deleg-alert-invites mb-3" role="status" aria-live="polite">
            <span class="fvd-deleg-alert-invites__text"><strong>Nueva invitación a torneo.</strong> Tiene <?= (int) $delegNotifNoVistas ?> notificación(es) sin abrir. Revise el bloque <a href="#fvd-deleg-torneos-invites">Invitaciones</a> o el enlace en la barra superior.</span>
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

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
        <div class="col d-flex">
            <a class="fvd-dd-card fvd-dd-card--insc" href="<?= htmlspecialchars($urlTorneoInscripcion, ENT_QUOTES, 'UTF-8') ?>">
                <div class="fvd-dd-card__row">
                    <div class="fvd-dd-card__icon" aria-hidden="true"><i class="fa-solid fa-trophy"></i></div>
                    <div class="fvd-dd-card__body">
                        <p class="fvd-dd-card__label">Inscripciones</p>
                        <h2 class="fvd-dd-card__title">Inscribir atletas</h2>
                        <p class="fvd-dd-card__meta">Preparación e inscripción al torneo en curso.</p>
                    </div>
                </div>
                <div class="fvd-dd-card__cta"><span>Acceso rápido</span></div>
            </a>
        </div>
        <div class="col d-flex">
            <a class="fvd-dd-card fvd-dd-card--afi" href="<?= htmlspecialchars($urlAfiliados, ENT_QUOTES, 'UTF-8') ?>">
                <div class="fvd-dd-card__row">
                    <div class="fvd-dd-card__icon" aria-hidden="true"><i class="fa-solid fa-user"></i></div>
                    <div class="fvd-dd-card__body">
                        <p class="fvd-dd-card__label">Afiliados</p>
                        <h2 class="fvd-dd-card__title">Gestión de afiliados</h2>
                        <p class="fvd-dd-card__meta">Total atletas en su asociación: <strong><?= number_format($totalAtletasAsoc, 0, ',', '.') ?></strong></p>
                    </div>
                </div>
                <div class="fvd-dd-card__cta"><span>Acceso rápido</span></div>
            </a>
        </div>
        <div class="col d-flex">
            <a class="fvd-dd-card fvd-dd-card--tras" href="<?= htmlspecialchars($urlBandejaTraspasos, ENT_QUOTES, 'UTF-8') ?>">
                <div class="fvd-dd-card__row">
                    <div class="fvd-dd-card__icon" aria-hidden="true"><i class="fa-solid fa-right-left"></i></div>
                    <div class="fvd-dd-card__body">
                        <p class="fvd-dd-card__label">Traspasos</p>
                        <h2 class="fvd-dd-card__title">Bandeja de traspasos<?php if ($nPendTrasp > 0): ?><span class="fvd-dd-badge" aria-label="Pendientes"><?= (int) $nPendTrasp ?></span><?php endif; ?></h2>
                        <p class="fvd-dd-card__meta">Solicitudes pendientes de su club.</p>
                    </div>
                </div>
                <div class="fvd-dd-card__cta"><span>Acceso rápido</span></div>
            </a>
        </div>
        <div class="col d-flex">
            <a class="fvd-dd-card fvd-dd-card--car" href="<?= htmlspecialchars($urlEstadoCarnets, ENT_QUOTES, 'UTF-8') ?>">
                <div class="fvd-dd-card__row">
                    <div class="fvd-dd-card__icon" aria-hidden="true"><i class="fa-solid fa-id-card"></i></div>
                    <div class="fvd-dd-card__body">
                        <p class="fvd-dd-card__label">Carnetización</p>
                        <h2 class="fvd-dd-card__title">Estado de carnets</h2>
                        <p class="fvd-dd-card__meta">Activos sin carnet procesado: <strong><?= (int) $carnetFaltan ?></strong></p>
                    </div>
                </div>
                <div class="fvd-dd-card__cta"><span>Acceso rápido</span></div>
            </a>
        </div>
        <div class="col d-flex">
            <a class="fvd-dd-card fvd-dd-card--fin" href="<?= htmlspecialchars($urlTorneoInscripcion, ENT_QUOTES, 'UTF-8') ?>">
                <div class="fvd-dd-card__row">
                    <div class="fvd-dd-card__icon" aria-hidden="true"><i class="fa-solid fa-scale-balanced"></i></div>
                    <div class="fvd-dd-card__body">
                        <p class="fvd-dd-card__label">Finanzas</p>
                        <h2 class="fvd-dd-card__title">Balance global</h2>
                        <p class="fvd-dd-card__meta"><?= htmlspecialchars($saldoLinea, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if (is_array($balanceCampeonato) && $campeonatoIdInt > 0): ?>
                            <?php $totD = $balanceCampeonato['totales'] ?? []; ?>
                            <p class="fvd-dd-card__meta" style="margin-top:0.35rem;font-size:0.75rem;opacity:0.9">
                                Deuda ref. Bs: <?= $fvdFmtN((float) ($totD['monto_total_bs'] ?? 0)) ?>
                                <?php if (isset($totD['monto_total_eur']) && $totD['monto_total_eur'] !== null && (float) $totD['monto_total_eur'] > 0): ?>
                                    · Total EUR: <?= $fvdFmtN((float) $totD['monto_total_eur']) ?> €
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="fvd-dd-card__cta"><span>Ver en inscripción al torneo</span></div>
            </a>
        </div>
    </div>

    <?php if (($delegNotifs ?? []) !== []): ?>
        <section id="fvd-deleg-torneos-invites" class="fvd-dd-invites fvd-deleg-notif-wrap" aria-label="Invitaciones a torneos">
            <h2 class="fvd-deleg-notif-wrap__h">Invitaciones a torneos <?= ($delegNotifNoVistas ?? 0) > 0 ? ' (' . (int) $delegNotifNoVistas . ' sin abrir)' : '' ?></h2>
            <p class="fvd-deleg-notif-wrap__p" style="font-size:0.8125rem">PDF y acceso al panel del torneo.</p>
            <ul class="fvd-deleg-notif-wrap__ul">
                <?php foreach ($delegNotifs as $nf): ?>
                    <?php
                    $nid = (int) ($nf['id'] ?? 0);
                    $tn = htmlspecialchars((string) ($nf['torneo_nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $ramaRaw = trim((string) ($nf['torneo_rama_nombre'] ?? ''));
                    $tnRaw = trim((string) ($nf['torneo_nombre'] ?? ''));
                    $fd = htmlspecialchars(substr((string) ($nf['fechator'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8');
                    $sinAbrir = empty($nf['visto_en']);
                    $entrar = $appBase . '/fvdmasteradmin/delegado_entrar_torneo.php?notif_id=' . $nid;
                    $pdf = $appBase . '/fvdmasteradmin/delegado_invitacion_pdf.php?notif_id=' . $nid;
                    ?>
                    <li class="fvd-deleg-notif-wrap__li">
                        <span class="fvd-deleg-notif-wrap__tn"><?= $tn ?></span>
                        <?php if ($ramaRaw !== '' && strcasecmp($ramaRaw, $tnRaw) !== 0): ?>
                            <span class="fvd-deleg-notif-wrap__rama" style="display:block;font-size:0.8125rem;color:var(--fvd-muted);margin-top:0.15rem">Rama: <?= htmlspecialchars($ramaRaw, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <span class="fvd-deleg-notif-wrap__fd"><?= $fd ?></span>
                        <?php if ($sinAbrir): ?><span class="fvd-deleg-notif-wrap__new">Nuevo</span><?php endif; ?>
                        <span class="fvd-deleg-notif-wrap__sp"></span>
                        <?php if (!empty($nf['invitacion_archivo'])): ?>
                            <a class="fvd-btn fvd-btn--secondary fvd-deleg-notif-wrap__btn" href="<?= htmlspecialchars($pdf, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">PDF</a>
                        <?php endif; ?>
                        <a class="fvd-btn fvd-btn--primary fvd-deleg-notif-wrap__btn" href="<?= htmlspecialchars($entrar, ENT_QUOTES, 'UTF-8') ?>">Panel del torneo</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
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
