<?php
/** @var array<string,mixed>|null $eventoTorneo */
/** @var string $selfUrl */
/** @var list<array<string,mixed>> $convocatoriaFilas */
/** @var bool $convocatoriaOk */
/** @var bool $inscOk */
/** @var int $id */
/** @var string $fvd_error */
/** @var array<string,int> $torneoPanelStats inscripciones_torneo y banderas = conteos con valor 1; atletas_en_ambito = filas del torneo */
/** @var string $torneoNotifFlash */
/** @var string $torneoWaUrl */

$t = $eventoTorneo;
$torneoNotifFlash = $torneoNotifFlash ?? '';
$torneoWaUrl = $torneoWaUrl ?? '';
$torneoPanelStats = $torneoPanelStats ?? [];
if (!function_exists('fvd_return_append_to_url')) {
    require_once FVD_PROJECT_ROOT . '/config/fvd_navigation_return.php';
}
$tp = $torneoPanelStats;
$msg = (string) ($_GET['msg'] ?? '');
$msgOk = $msg === 'ok';
$msgInvitadas = $msg === 'invitadas';
$msgSinTablaConv = $msg === 'sin_tabla_convocatoria';
?>
<div class="fvd-torneo-evento">
<?php if ($t === null): ?>
<h1 class="fvd-torneo-evento__title">Administración del torneo</h1>
<?php endif; ?>
<?php if ($msgInvitadas): ?>
    <p class="fvd-torneo-evento__ok">Torneo guardado. Se registró la invitación a todas las asociaciones; pueden usar <strong class="fvd-torneo-evento__strong">Inscribir al torneo</strong> para enviar inscritos.</p>
<?php elseif ($msgSinTablaConv): ?>
    <p class="fvd-torneo-evento__warn">Torneo guardado, pero no existe la tabla de convocatorias: no se pudieron registrar invitaciones. Ejecute en MySQL <code class="fvd-torneo-evento__code">fvdmasteradmin/sql/install_torneo_convocatoria_y_publicacion.sql</code> y use «Invitar a todas las asociaciones» en esta pantalla.</p>
<?php elseif ($msgOk): ?>
    <p class="fvd-torneo-evento__ok">Operación realizada.</p>
<?php elseif (($msg ?? '') === 'notif_delegados' && $torneoNotifFlash !== ''): ?>
    <p class="fvd-torneo-evento__ok"><?= htmlspecialchars($torneoNotifFlash, ENT_QUOTES, 'UTF-8') ?></p>
    <?php if ($torneoWaUrl !== ''): ?>
        <p class="fvd-torneo-evento__text"><a class="fvd-torneo-evento__link" href="<?= htmlspecialchars($torneoWaUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Abrir WhatsApp con el mismo mensaje</a></p>
    <?php endif; ?>
<?php endif; ?>
<?php if (!empty($fvd_error)): ?>
    <p class="fvd-mod-msg fvd-torneo-evento__err"><?= htmlspecialchars($fvd_error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<?php if ($t === null): ?>
    <p class="fvd-torneo-evento__text">No se encontró el torneo.</p>
    <p><a class="fvd-torneo-evento__link" href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=list'), ENT_QUOTES, 'UTF-8') ?>">Volver al listado</a></p>
<?php else: ?>
<?php
$fvdEvResInv = 0;
$fvdEvResAsocInsc = 0;
$fvdEvResSum = 0;
foreach ($convocatoriaFilas as $rf) {
    if (!empty($rf['invitado_en'])) {
        ++$fvdEvResInv;
    }
    $ni = (int) ($rf['num_inscritos'] ?? 0);
    if ($ni > 0) {
        ++$fvdEvResAsocInsc;
    }
    $fvdEvResSum += $ni;
}
$fvdEvTotalAsoc = count($convocatoriaFilas);
$tidTorneo = (int) ($t['torneo'] ?? 0);
$fvdTorneoFinalizado = !empty($t['finalizado_en']);
$fvdEvSelfQ = static function (string $querySinInterrogacion) use ($selfUrl): string {
    return fvd_return_append_to_url($selfUrl . '?' . $querySinInterrogacion);
};
$urlAtletas = fvd_return_append_to_url(fvd_crud_self_url('atletas'));
$urlInscTorneo = fvd_return_append_to_url(fvd_master_module_url('inscripcion_torneo/index.php?torneo_id=' . $tidTorneo));
$urlTorneoInsc = fvd_return_append_to_url(admin_module_url('torneo_inscripcion/index.php?torneo_id=' . $tidTorneo));
$urlInscripciones = fvd_return_append_to_url(fvd_master_module_url('inscripciones/index.php?torneo_id=' . $tidTorneo));
$urlDeudas = fvd_return_append_to_url(fvd_master_module_url('deuda_asociacion/index.php?torneo_id=' . $tidTorneo));
$urlPagos = fvd_return_append_to_url(fvd_master_module_url('relacion_pago/index.php?torneo_id=' . $tidTorneo));
$urlPublicTorneo = url('torneo_publico.php?id=' . $tidTorneo);
$fvdTipoGenRaw = (int) ($t['tipo'] ?? 1);
$fvdTipoGenLab = [1 => 'Masculino', 2 => 'Femenino', 3 => 'Mixto'][$fvdTipoGenRaw] ?? '—';
$fvdEsCampeonatoEv = isset($t['es_campeonato']) && (int) $t['es_campeonato'] === 1;
$fvdTipoLab = $fvdTipoGenLab . ($fvdEsCampeonatoEv ? ' · Campeonato' : '');
$fvdClaseLab = [1 => 'Individual', 2 => 'Parejas', 3 => 'Equipos'][(int) ($t['clase'] ?? 1)] ?? '—';
?>

<div class="fvd-mis-panel">
    <nav class="fvd-mis-panel__crumb" aria-label="Ruta">
        <a href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=list'), ENT_QUOTES, 'UTF-8') ?>">← Gestión de torneos</a>
    </nav>

    <?php if ($fvdTorneoFinalizado): ?>
    <p class="fvd-mod-msg" style="margin:0 0 10px;background:#eff6ff;border-color:#3b82f6;color:#1e3a8a;font-size:0.8125rem">
        <strong>Torneo concluido</strong> (<?= htmlspecialchars(substr((string) ($t['finalizado_en'] ?? ''), 0, 19), ENT_QUOTES, 'UTF-8') ?>): vista de consulta con convocatorias, indicadores y enlaces. Los datos mostrados corresponden al estado actual en base de datos.
    </p>
    <?php endif; ?>

    <header class="fvd-mis-panel__hero">
        <div class="fvd-torneo-evento__notif-row" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0 0 10px">
            <form method="post" action="<?= htmlspecialchars(fvd_return_preserve_query_params($selfUrl), ENT_QUOTES, 'UTF-8') ?>" class="fvd-torneo-evento__notif-form" style="margin:0">
                <input type="hidden" name="_action" value="notificar_delegados">
                <input type="hidden" name="torneo_id" value="<?= $tidTorneo ?>">
                <button type="submit" class="fvd-input" style="width:auto;padding:6px 12px;font-size:0.8125rem;font-weight:600" title="Envía correo a delegados activos">Notificar delegados (correo masivo)</button>
            </form>
            <a class="fvd-input" style="width:auto;padding:6px 12px;font-size:0.8125rem;font-weight:600;text-decoration:none;display:inline-block;box-sizing:border-box" href="<?= htmlspecialchars($fvdEvSelfQ('action=tarjetas_zip&id=' . $tidTorneo), ENT_QUOTES, 'UTF-8') ?>" title="ZIP con una tarjeta PDF por delegado (registros con archivo en disco)">Descargar ZIP de tarjetas PDF</a>
            <a class="fvd-input" style="width:auto;padding:6px 12px;font-size:0.8125rem;font-weight:600;text-decoration:none;display:inline-block;box-sizing:border-box" href="<?= htmlspecialchars($fvdEvSelfQ('action=historico_torneo&id=' . $tidTorneo), ENT_QUOTES, 'UTF-8') ?>">Histórico del torneo</a>
            <?php if (!$fvdTorneoFinalizado): ?>
            <form method="post" action="<?= htmlspecialchars(fvd_return_preserve_query_params($selfUrl), ENT_QUOTES, 'UTF-8') ?>" style="margin:0;display:inline" onsubmit="return confirm('¿Dar este torneo por concluido? Se registrará el histórico y se limpiarán inscripciones y marcas en los atletas que participaron (bandera y tabla).');">
                <input type="hidden" name="_action" value="torneo_finalizar">
                <input type="hidden" name="torneo_id" value="<?= $tidTorneo ?>">
                <button type="submit" class="fvd-input" style="width:auto;padding:6px 12px;font-size:0.8125rem;font-weight:600;background:#7f1d1d;color:#fecaca;border-color:#991b1b">Dar torneo por concluido</button>
            </form>
            <?php else: ?>
            <span class="fvd-atl-muted" style="font-size:0.8125rem">Concluido <?= htmlspecialchars(substr((string) ($t['finalizado_en'] ?? ''), 0, 19), ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </div>
        <h1 class="fvd-mis-panel__title"><?= htmlspecialchars((string) ($t['nombre'] ?? 'Torneo'), ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="fvd-mis-panel__hero-meta">
            <span>ID #<?= $tidTorneo ?></span>
            <span><?= htmlspecialchars(substr((string) ($t['fechator'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8') ?></span>
            <?php if (!empty($t['lugar'])): ?><span><?= htmlspecialchars((string) $t['lugar'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
            <span><?= htmlspecialchars($fvdTipoLab, ENT_QUOTES, 'UTF-8') ?></span>
            <span><?= htmlspecialchars($fvdClaseLab, ENT_QUOTES, 'UTF-8') ?></span>
            <span><?= (int) ($t['rondas'] ?? 0) ?> rondas</span>
            <span>Estatus <?= (int) ($t['estatus'] ?? 0) ?></span>
        </div>
    </header>

    <div class="fvd-mis-panel__strip">
        <div class="fvd-mis-panel__strip-col">
            <span class="fvd-mis-panel__strip-label">Convocatoria</span>
            <div class="fvd-mis-panel__badges">
                <div class="fvd-mis-badge fvd-mis-badge--emerald" title="Invitaciones enviadas (convocatoria con fecha registrada)"><span class="fvd-mis-badge__k">Inv.</span><span class="fvd-mis-badge__v"><?= (int) $fvdEvResInv ?></span></div>
                <div class="fvd-mis-badge fvd-mis-badge--blue" title="Asociaciones con al menos un inscrito en este torneo"><span class="fvd-mis-badge__k">Asoc+</span><span class="fvd-mis-badge__v"><?= (int) $fvdEvResAsocInsc ?></span></div>
            </div>
        </div>
        <div class="fvd-mis-panel__strip-col">
            <span class="fvd-mis-panel__strip-label">Público</span>
            <a href="<?= htmlspecialchars($urlPublicTorneo, ENT_QUOTES, 'UTF-8') ?>" class="fvd-mis-panel__strip-link" target="_blank" rel="noopener">Ver ficha pública del torneo →</a>
        </div>
        <div class="fvd-mis-panel__strip-col">
            <span class="fvd-mis-panel__strip-label">Inscripciones al torneo</span>
            <div class="fvd-mis-panel__badges">
                <div class="fvd-mis-badge fvd-mis-badge--rose" title="Atletas con bandera inscripción = 1 en este torneo"><span class="fvd-mis-badge__k">Ins.</span><span class="fvd-mis-badge__v"><?= (int) ($tp['inscripciones_torneo'] ?? 0) ?></span></div>
                <div class="fvd-mis-badge fvd-mis-badge--amber" title="Suma por asociación"><span class="fvd-mis-badge__k">Σ</span><span class="fvd-mis-badge__v"><?= (int) $fvdEvResSum ?></span></div>
            </div>
        </div>
    </div>

    <p class="fvd-mis-card__hint" style="margin:0 0 0.5rem">Conteos sobre atletas de <strong>este torneo</strong> (<code class="fvd-torneo-evento__code">torneo_id</code>): banderas en <strong>1</strong> (inscripción, afiliación, anualidad, carnet, traspaso). Convocatoria: solo invitaciones ya enviadas.</p>

    <div class="fvd-mis-panel__mini" aria-label="Indicadores operativos">
        <div class="fvd-mis-mini" title="Atletas con inscripción = 1"><span class="fvd-mis-mini__k">Ins. torneo</span><span class="fvd-mis-mini__v"><?= number_format((int) ($tp['inscripciones_torneo'] ?? 0), 0, ',', '.') ?></span></div>
        <div class="fvd-mis-mini" title="Solo invitaciones ya enviadas (registro en convocatoria); una por asociación alcanzada"><span class="fvd-mis-mini__k">Inv. / asoc.</span><span class="fvd-mis-mini__v"><?= (int) $fvdEvResInv ?></span></div>
        <div class="fvd-mis-mini" title="Afiliación = 1"><span class="fvd-mis-mini__k">Afiliación</span><span class="fvd-mis-mini__v"><?= number_format((int) ($tp['afiliacion_pendiente'] ?? 0), 0, ',', '.') ?></span></div>
        <div class="fvd-mis-mini" title="Anualidad = 1"><span class="fvd-mis-mini__k">Anualidad</span><span class="fvd-mis-mini__v"><?= number_format((int) ($tp['anualidad_pendiente'] ?? 0), 0, ',', '.') ?></span></div>
        <div class="fvd-mis-mini" title="Carnet = 1"><span class="fvd-mis-mini__k">Carnet</span><span class="fvd-mis-mini__v"><?= number_format((int) ($tp['carnet_pendiente'] ?? 0), 0, ',', '.') ?></span></div>
        <div class="fvd-mis-mini" title="Traspaso = 1"><span class="fvd-mis-mini__k">Traspaso</span><span class="fvd-mis-mini__v"><?= number_format((int) ($tp['traspaso_pendiente'] ?? 0), 0, ',', '.') ?></span></div>
        <div class="fvd-mis-mini" title="Todos los atletas de este torneo (ámbito de rol)"><span class="fvd-mis-mini__k">Atletas (torneo)</span><span class="fvd-mis-mini__v"><?= number_format((int) ($tp['atletas_en_ambito'] ?? 0), 0, ',', '.') ?></span></div>
    </div>

    <div class="fvd-mis-panel__grid">
        <div class="fvd-mis-card">
            <div class="fvd-mis-card__head fvd-mis-card__head--mesas">Invitaciones</div>
            <div class="fvd-mis-card__body">
                <?php if (!$convocatoriaOk): ?>
                    <div class="fvd-mis-card__warn" style="scroll-margin-top:1rem">
                        <strong>Falta la tabla de convocatorias.</strong> Ejecute en MySQL:
                        <code class="fvd-torneo-evento__code">fvdmasteradmin/sql/install_torneo_convocatoria_y_publicacion.sql</code>
                        Luego use el botón inferior para invitar a todas las asociaciones en un solo paso.
                    </div>
                <?php else: ?>
                    <p class="fvd-mis-card__hint" style="scroll-margin-top:1rem">
                        Tras cargar los datos del torneo (y <strong class="fvd-torneo-evento__strong">relacionar campeonatos</strong> si aplica), registre aquí la convocatoria masiva. Luego puede <strong class="fvd-torneo-evento__strong">notificar delegados</strong> y usar <strong class="fvd-torneo-evento__strong">Inscribir al torneo</strong> para el portal de clubes.
                    </p>
                    <div class="fvd-mis-card__actions" style="display:flex;flex-wrap:wrap;flex-direction:row;gap:10px;align-items:center">
                        <form method="post" action="<?= htmlspecialchars(fvd_return_preserve_query_params($selfUrl . '?action=evento&id=' . (int) $t['torneo']), ENT_QUOTES, 'UTF-8') ?>" style="display:inline" onsubmit="return confirm('¿Registrar invitación para todas las asociaciones a la vez?');">
                            <input type="hidden" name="_action" value="convocatoria_todas">
                            <input type="hidden" name="torneo_id" value="<?= (int) $t['torneo'] ?>">
                            <button type="submit" class="fvd-input" style="width:auto;padding:8px 14px;cursor:pointer">Invitar a todas (simultáneo)</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="fvd-mis-card">
            <div class="fvd-mis-card__head fvd-mis-card__head--ops">Operaciones</div>
            <div class="fvd-mis-card__body fvd-mis-card__actions">
                <a class="fvd-mis-btn fvd-mis-btn--blue" href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=form&id=' . (int) $t['torneo']), ENT_QUOTES, 'UTF-8') ?>">Editar datos del torneo</a>
                <?php if ($inscOk): ?>
                    <a class="fvd-mis-btn fvd-mis-btn--indigo" href="<?= htmlspecialchars($urlInscTorneo, ENT_QUOTES, 'UTF-8') ?>">Inscripciones (detalle) · #<?= $tidTorneo ?></a>
                <?php endif; ?>
                <a class="fvd-mis-btn fvd-mis-btn--amber" href="<?= htmlspecialchars($urlTorneoInsc, ENT_QUOTES, 'UTF-8') ?>">Inscribir al torneo (portal asociaciones)</a>
                <a class="fvd-mis-btn fvd-mis-btn--slate" href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=list'), ENT_QUOTES, 'UTF-8') ?>">Volver al listado de torneos</a>
            </div>
        </div>
        <div class="fvd-mis-card">
            <div class="fvd-mis-card__head fvd-mis-card__head--res">Consultas</div>
            <div class="fvd-mis-card__body fvd-mis-card__actions">
                <a class="fvd-mis-btn fvd-mis-btn--violet" href="<?= htmlspecialchars($urlInscTorneo, ENT_QUOTES, 'UTF-8') ?>">Inscripciones por torneo <span style="font-weight:600;opacity:.9">#<?= $tidTorneo ?></span></a>
                <a class="fvd-mis-btn fvd-mis-btn--teal" href="<?= htmlspecialchars($urlAtletas, ENT_QUOTES, 'UTF-8') ?>">Atletas</a>
                <a class="fvd-mis-btn fvd-mis-btn--indigo" href="<?= htmlspecialchars($urlInscripciones, ENT_QUOTES, 'UTF-8') ?>">Inscripciones (módulo)</a>
                <a class="fvd-mis-btn fvd-mis-btn--rose" href="<?= htmlspecialchars($urlDeudas, ENT_QUOTES, 'UTF-8') ?>">Deudas</a>
                <a class="fvd-mis-btn fvd-mis-btn--amber" href="<?= htmlspecialchars($urlPagos, ENT_QUOTES, 'UTF-8') ?>">Pagos</a>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($convocatoriaOk) && !$inscOk): ?>
    <p class="fvd-mod-msg fvd-torneo-evento__warn">La tabla <code class="fvd-torneo-evento__code">inscripcion_torneo</code> no existe: el conteo de inscritos mostrará 0 hasta que ejecute <code class="fvd-torneo-evento__code">install_inscripcion_torneo.sql</code>.</p>
<?php endif; ?>

<?php endif; ?>
</div>
