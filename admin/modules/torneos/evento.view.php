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
$tp = $torneoPanelStats;
$msg = (string) ($_GET['msg'] ?? '');
$msgOk = $msg === 'ok';
$msgInvitadas = $msg === 'invitadas';
$msgSinTablaConv = $msg === 'sin_tabla_convocatoria';
$msgCreadoInv = $msg === 'torneo_creado_invitaciones';
?>
<div class="fvd-torneo-evento"<?php if ($t !== null && !empty($convocatoriaOk)): ?> data-status-json="<?= htmlspecialchars($selfUrl . '?action=evento_status_json&id=' . (int) ($t['torneo'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>>
<?php if ($t === null): ?>
<h1 class="fvd-torneo-evento__title">Administración del torneo</h1>
<?php endif; ?>
<?php if ($msgCreadoInv): ?>
    <p class="fvd-torneo-evento__ok">Torneo <strong>creado</strong>. Se invitó a todas las asociaciones (si existe la tabla de convocatorias), se generaron avisos en el panel de cada <strong>delegado</strong> y, si Dompdf está instalado (<code class="fvd-torneo-evento__code">composer require dompdf/dompdf</code>), una <strong>tarjeta PDF</strong> por club con enlace de acceso seguro al torneo. Cada enlace solo funciona iniciando sesión con el usuario delegado de esa asociación.</p>
<?php elseif ($msgInvitadas): ?>
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
    <p><a class="fvd-torneo-evento__link" href="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>">Volver al listado</a></p>
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
$urlAtletas = fvd_crud_self_url('atletas');
$urlInscTorneo = fvd_master_module_url('inscripcion_torneo/index.php?torneo_id=' . $tidTorneo);
$urlTorneoInsc = admin_module_url('torneo_inscripcion/index.php?torneo_id=' . $tidTorneo);
$urlInscripciones = fvd_master_module_url('inscripciones/index.php?torneo_id=' . $tidTorneo);
$urlDeudas = fvd_master_module_url('deuda_asociacion/index.php');
$urlPagos = fvd_master_module_url('relacion_pago/index.php');
$urlAsoc = fvd_crud_self_url('asociaciones');
$urlPublicTorneo = url('torneo_publico.php?id=' . $tidTorneo);
$fvdTipoRaw = (int) ($t['tipo'] ?? 1);
$fvdTipoLab = [1 => 'Torneo', 2 => 'Campeonato', 3 => 'Mixto (hist.)'][$fvdTipoRaw] ?? '—';
$fvdClaseLab = [1 => 'Individual', 2 => 'Parejas', 3 => 'Equipos'][(int) ($t['clase'] ?? 1)] ?? '—';
?>

<div class="fvd-mis-panel">
    <nav class="fvd-mis-panel__crumb" aria-label="Ruta">
        <a href="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>">← Gestión de torneos</a>
    </nav>

    <header class="fvd-mis-panel__hero">
        <div class="fvd-torneo-evento__notif-row" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0 0 10px">
            <form method="post" action="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-torneo-evento__notif-form" style="margin:0">
                <input type="hidden" name="_action" value="notificar_delegados">
                <input type="hidden" name="torneo_id" value="<?= $tidTorneo ?>">
                <button type="submit" class="fvd-input" style="width:auto;padding:6px 12px;font-size:0.8125rem;font-weight:600" title="Envía correo a delegados activos">Notificar delegados (correo masivo)</button>
            </form>
            <a class="fvd-input" style="width:auto;padding:6px 12px;font-size:0.8125rem;font-weight:600;text-decoration:none;display:inline-block;box-sizing:border-box" href="<?= htmlspecialchars($selfUrl . '?action=tarjetas_zip&id=' . $tidTorneo, ENT_QUOTES, 'UTF-8') ?>" title="ZIP con una tarjeta PDF por delegado (registros con archivo en disco)">Descargar ZIP de tarjetas PDF</a>
            <a class="fvd-input" style="width:auto;padding:6px 12px;font-size:0.8125rem;font-weight:600;text-decoration:none;display:inline-block;box-sizing:border-box" href="<?= htmlspecialchars($selfUrl . '?action=historico_torneo&id=' . $tidTorneo, ENT_QUOTES, 'UTF-8') ?>">Histórico del torneo</a>
            <?php if (!$fvdTorneoFinalizado): ?>
            <form method="post" action="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" style="margin:0;display:inline" onsubmit="return confirm('¿Dar este torneo por concluido? Se registrará el histórico y se limpiarán inscripciones y marcas en los atletas que participaron (bandera y tabla).');">
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
            <span class="fvd-mis-panel__strip-label">Tiempo real</span>
            <p class="fvd-mis-panel__strip-text">Sondeo cada 8 s en la tabla inferior (si hay convocatoria activa).</p>
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
                <div class="fvd-mis-quick">
                    <a href="#fvd-tpa-inv-masivas" class="fvd-mis-btn fvd-mis-btn--cyan">Masivas / selección</a>
                    <a href="#fvd-tpa-inv-unitaria" class="fvd-mis-btn fvd-mis-btn--cyan">Por asociación</a>
                    <a href="#fvd-tpa-avance" class="fvd-mis-btn fvd-mis-btn--cyan">Avance en vivo</a>
                </div>
                <?php if (!$convocatoriaOk): ?>
                    <div class="fvd-mis-card__warn" id="fvd-tpa-inv-masivas" style="scroll-margin-top:1rem">
                        <strong>Falta la tabla de convocatorias.</strong> Ejecute en MySQL:
                        <code class="fvd-torneo-evento__code">fvdmasteradmin/sql/install_torneo_convocatoria_y_publicacion.sql</code>
                        Luego vuelva aquí para invitaciones masivas y seguimiento por asociación.
                    </div>
                <?php else: ?>
                    <p class="fvd-mis-card__hint" id="fvd-tpa-inv-masivas" style="scroll-margin-top:1rem">
                        Invite a todas o marque filas en la tabla y use <strong class="fvd-torneo-evento__strong">Invitar solo las marcadas</strong>. Las asociaciones inscriben desde <strong class="fvd-torneo-evento__strong">Inscribir al torneo</strong>.
                    </p>
                    <div class="fvd-mis-card__actions" style="display:flex;flex-wrap:wrap;flex-direction:row;gap:10px;align-items:center">
                        <form method="post" action="<?= htmlspecialchars($selfUrl . '?action=evento&id=' . (int) $t['torneo'], ENT_QUOTES, 'UTF-8') ?>" style="display:inline" onsubmit="return confirm('¿Registrar invitación para todas las asociaciones a la vez?');">
                            <input type="hidden" name="_action" value="convocatoria_todas">
                            <input type="hidden" name="torneo_id" value="<?= (int) $t['torneo'] ?>">
                            <button type="submit" class="fvd-input" style="width:auto;padding:8px 14px;cursor:pointer">Invitar a todas (simultáneo)</button>
                        </form>
                        <button type="button" class="fvd-input" id="fvd-ev-btn-bulk" style="width:auto;padding:8px 14px;cursor:pointer">Invitar solo las marcadas (simultáneo)</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="fvd-mis-card">
            <div class="fvd-mis-card__head fvd-mis-card__head--ops">Operaciones</div>
            <div class="fvd-mis-card__body fvd-mis-card__actions">
                <a class="fvd-mis-btn fvd-mis-btn--blue" href="<?= htmlspecialchars($selfUrl . '?action=form&id=' . (int) $t['torneo'], ENT_QUOTES, 'UTF-8') ?>">Editar datos del torneo</a>
                <?php if ($inscOk): ?>
                    <a class="fvd-mis-btn fvd-mis-btn--indigo" href="<?= htmlspecialchars($urlInscTorneo, ENT_QUOTES, 'UTF-8') ?>">Inscripciones (detalle) · #<?= $tidTorneo ?></a>
                <?php endif; ?>
                <a class="fvd-mis-btn fvd-mis-btn--amber" href="<?= htmlspecialchars($urlTorneoInsc, ENT_QUOTES, 'UTF-8') ?>">Inscribir al torneo (portal asociaciones)</a>
                <a class="fvd-mis-btn fvd-mis-btn--slate" href="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>">Volver al listado de torneos</a>
            </div>
        </div>
        <div class="fvd-mis-card">
            <div class="fvd-mis-card__head fvd-mis-card__head--res">Consultas</div>
            <div class="fvd-mis-card__body fvd-mis-card__actions">
                <a class="fvd-mis-btn fvd-mis-btn--violet" href="<?= htmlspecialchars($urlInscTorneo, ENT_QUOTES, 'UTF-8') ?>">Inscripciones por torneo <span style="font-weight:600;opacity:.9">#<?= $tidTorneo ?></span></a>
                <a class="fvd-mis-btn fvd-mis-btn--teal" href="<?= htmlspecialchars($urlAtletas, ENT_QUOTES, 'UTF-8') ?>">Atletas</a>
                <a class="fvd-mis-btn fvd-mis-btn--cyan" href="<?= htmlspecialchars($urlAsoc, ENT_QUOTES, 'UTF-8') ?>">Asociaciones</a>
                <a class="fvd-mis-btn fvd-mis-btn--indigo" href="<?= htmlspecialchars($urlInscripciones, ENT_QUOTES, 'UTF-8') ?>">Inscripciones (módulo)</a>
                <a class="fvd-mis-btn fvd-mis-btn--rose" href="<?= htmlspecialchars($urlDeudas, ENT_QUOTES, 'UTF-8') ?>">Deudas</a>
                <a class="fvd-mis-btn fvd-mis-btn--amber" href="<?= htmlspecialchars($urlPagos, ENT_QUOTES, 'UTF-8') ?>">Pagos</a>
            </div>
        </div>
    </div>
</div>

<?php if (!$convocatoriaOk): ?>
<?php else: ?>

<?php if (!$inscOk): ?>
    <p class="fvd-mod-msg fvd-torneo-evento__warn">La tabla <code class="fvd-torneo-evento__code">inscripcion_torneo</code> no existe: el conteo de inscritos mostrará 0 hasta que ejecute <code class="fvd-torneo-evento__code">install_inscripcion_torneo.sql</code>.</p>
<?php endif; ?>

<form id="fvd-ev-bulk-invite" method="post" action="<?= htmlspecialchars($selfUrl . '?action=evento&id=' . (int) $t['torneo'], ENT_QUOTES, 'UTF-8') ?>" hidden>
    <input type="hidden" name="_action" value="convocatoria_invitar_seleccionadas">
    <input type="hidden" name="torneo_id" value="<?= (int) $t['torneo'] ?>">
</form>

<div class="fvd-mis-panel__wide">
<p class="fvd-torneo-evento__resumen" id="fvd-ev-resumen" aria-live="polite">
    <span><strong id="fvd-ev-r-total"><?= (int) $fvdEvTotalAsoc ?></strong> asociaciones</span>
    <span><strong id="fvd-ev-r-inv"><?= (int) $fvdEvResInv ?></strong> con invitación registrada</span>
    <span><strong id="fvd-ev-r-asoc-insc"><?= (int) $fvdEvResAsocInsc ?></strong> con inscritos</span>
    <span><strong id="fvd-ev-r-sum"><?= (int) $fvdEvResSum ?></strong> inscripciones totales</span>
</p>

<h2 class="fvd-torneo-evento__h2" id="fvd-tpa-inv-unitaria" style="scroll-margin-top:1rem">Invitación por asociación y respuestas</h2>
<p class="fvd-torneo-evento__lead" style="margin:0 0 8px">En la tabla puede registrar una invitación por fila, marcar varias para envío simultáneo o actualizar el estado de respuesta.</p>

<div class="fvd-torneo-evento__livebar" id="fvd-ev-livebar">
    <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;user-select:none">
        <input type="checkbox" id="fvd-ev-live-toggle" checked>
        <span>Actualización en vivo</span>
    </label>
    <span class="fvd-torneo-evento__live-dot fvd-torneo-evento__live-dot--on" id="fvd-ev-live-dot" title="Estado del sondeo"></span>
    <span id="fvd-ev-live-status">cada 8 s</span>
    <span class="fvd-torneo-evento__live-hint" id="fvd-ev-live-last">—</span>
</div>

<div class="fvd-mod-table-wrap fvd-torneo-evento__tablewrap" id="fvd-tpa-avance" style="scroll-margin-top:1rem">
    <table class="fvd-mod-table fvd-mod-table--nowrap fvd-torneo-evento__table" id="fvd-ev-table">
        <thead>
        <tr>
            <th style="width:2.25rem;text-align:center" title="Marcar para invitación masiva">
                <input type="checkbox" id="fvd-ev-selall" aria-label="Marcar o desmarcar todas">
            </th>
            <th>Asociación</th>
            <th>Contacto</th>
            <th>Invitación</th>
            <th>Respuesta</th>
            <th>Inscritos</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($convocatoriaFilas as $row): ?>
            <?php
            $aid = (int) ($row['asociacion_id'] ?? 0);
            $inv = $row['invitado_en'] ?? null;
            $est = (string) ($row['estado_respuesta'] ?? 'pendiente');
            ?>
            <tr data-asoc-id="<?= $aid ?>">
                <td style="text-align:center">
                    <input type="checkbox" class="fvd-ev-sel-asoc" form="fvd-ev-bulk-invite" name="asociacion_id[]" value="<?= $aid ?>" aria-label="Seleccionar para invitación masiva">
                </td>
                <td><?= htmlspecialchars((string) ($row['asoc_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="fvd-torneo-evento__td-contacto">
                    <?php if (!empty($row['asoc_email'])): ?>
                        <span style="display:block"><?= htmlspecialchars((string) $row['asoc_email'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <?php if (!empty($row['asoc_tel'])): ?>
                        <span style="display:block"><?= htmlspecialchars((string) $row['asoc_tel'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <?= (empty($row['asoc_email']) && empty($row['asoc_tel'])) ? '—' : '' ?>
                </td>
                <td><span class="fvd-ev-cell-inv"><?= $inv ? htmlspecialchars((string) $inv, ENT_QUOTES, 'UTF-8') : '—' ?></span></td>
                <td><span class="fvd-ev-cell-est"><?= htmlspecialchars($est, ENT_QUOTES, 'UTF-8') ?></span></td>
                <td><strong class="fvd-ev-cell-ni"><?= (int) ($row['num_inscritos'] ?? 0) ?></strong></td>
                <td class="fvd-torneo-evento__td-actions">
                    <?php if (!$inv): ?>
                        <form method="post" action="<?= htmlspecialchars($selfUrl . '?action=evento&id=' . (int) $t['torneo'], ENT_QUOTES, 'UTF-8') ?>" style="display:inline">
                            <input type="hidden" name="_action" value="convocatoria_invitar">
                            <input type="hidden" name="torneo_id" value="<?= (int) $t['torneo'] ?>">
                            <input type="hidden" name="asociacion_id" value="<?= $aid ?>">
                            <button type="submit" class="fvd-input" style="width:auto;padding:4px 10px;font-size:0.8125rem;cursor:pointer">Registrar invitación</button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="<?= htmlspecialchars($selfUrl . '?action=evento&id=' . (int) $t['torneo'], ENT_QUOTES, 'UTF-8') ?>" style="display:inline">
                            <input type="hidden" name="_action" value="convocatoria_invitar">
                            <input type="hidden" name="torneo_id" value="<?= (int) $t['torneo'] ?>">
                            <input type="hidden" name="asociacion_id" value="<?= $aid ?>">
                            <button type="submit" class="fvd-input" style="width:auto;padding:4px 10px;font-size:0.8125rem;cursor:pointer" title="Actualiza la fecha de invitación">Reenviar registro</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= htmlspecialchars($selfUrl . '?action=evento&id=' . (int) $t['torneo'], ENT_QUOTES, 'UTF-8') ?>" style="display:inline;margin-left:6px">
                        <input type="hidden" name="_action" value="convocatoria_estado">
                        <input type="hidden" name="torneo_id" value="<?= (int) $t['torneo'] ?>">
                        <input type="hidden" name="asociacion_id" value="<?= $aid ?>">
                        <select name="estado_respuesta" class="fvd-input fvd-ev-sel-estado" style="max-width:7rem;padding:4px;font-size:0.75rem" onchange="this.form.submit()" aria-label="Estado de respuesta">
                            <?php foreach (['pendiente', 'aceptada', 'declinada'] as $es): ?>
                                <option value="<?= htmlspecialchars($es, ENT_QUOTES, 'UTF-8') ?>" <?= ($est === $es) ? 'selected' : '' ?>><?= htmlspecialchars($es, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
(function () {
  var root = document.querySelector('.fvd-torneo-evento[data-status-json]');
  if (!root) return;
  var url = root.getAttribute('data-status-json');
  var toggle = document.getElementById('fvd-ev-live-toggle');
  var dot = document.getElementById('fvd-ev-live-dot');
  var lastEl = document.getElementById('fvd-ev-live-last');
  var statusEl = document.getElementById('fvd-ev-live-status');
  var selAll = document.getElementById('fvd-ev-selall');
  var bulkBtn = document.getElementById('fvd-ev-btn-bulk');
  var bulkForm = document.getElementById('fvd-ev-bulk-invite');
  var prevCounts = {};
  document.querySelectorAll('#fvd-ev-table tbody tr[data-asoc-id]').forEach(function (tr) {
    var id = tr.getAttribute('data-asoc-id');
    var cell = tr.querySelector('.fvd-ev-cell-ni');
    prevCounts[id] = cell ? parseInt(cell.textContent, 10) || 0 : 0;
  });

  if (selAll) {
    selAll.addEventListener('change', function () {
      document.querySelectorAll('input.fvd-ev-sel-asoc').forEach(function (cb) { cb.checked = selAll.checked; });
    });
  }
  if (bulkBtn && bulkForm) {
    bulkBtn.addEventListener('click', function () {
      var n = document.querySelectorAll('input.fvd-ev-sel-asoc:checked').length;
      if (n === 0) {
        window.alert('Marque al menos una asociación en la primera columna.');
        return;
      }
      if (!window.confirm('¿Registrar invitación para las ' + n + ' asociaciones marcadas (una sola operación)?')) return;
      bulkForm.submit();
    });
  }

  function pad2(n) { return n < 10 ? '0' + n : '' + n; }
  function fmtTime(d) {
    return pad2(d.getHours()) + ':' + pad2(d.getMinutes()) + ':' + pad2(d.getSeconds());
  }

  function applyResumen(r) {
    if (!r) return;
    var el;
    el = document.getElementById('fvd-ev-r-total'); if (el) el.textContent = String(r.total_asociaciones);
    el = document.getElementById('fvd-ev-r-inv'); if (el) el.textContent = String(r.con_invitacion_registrada);
    el = document.getElementById('fvd-ev-r-asoc-insc'); if (el) el.textContent = String(r.asociaciones_con_inscritos);
    el = document.getElementById('fvd-ev-r-sum'); if (el) el.textContent = String(r.total_inscritos_torneo);
  }

  function flashRow(tr) {
    tr.classList.remove('fvd-torneo-evento__row-flash');
    void tr.offsetWidth;
    tr.classList.add('fvd-torneo-evento__row-flash');
    window.setTimeout(function () { tr.classList.remove('fvd-torneo-evento__row-flash'); }, 950);
  }

  function poll() {
    if (!toggle || !toggle.checked) {
      if (dot) dot.classList.remove('fvd-torneo-evento__live-dot--on');
      if (statusEl) statusEl.textContent = 'en pausa';
      return;
    }
    if (document.hidden) return;
    if (dot) dot.classList.add('fvd-torneo-evento__live-dot--on');
    if (statusEl) statusEl.textContent = 'cada 8 s';
    fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data || !data.ok || !data.asociaciones) {
          if (lastEl) lastEl.textContent = data && data.error ? ('Error: ' + data.error) : 'Sin datos';
          return;
        }
        applyResumen(data.resumen);
        data.asociaciones.forEach(function (a) {
          var tr = document.querySelector('#fvd-ev-table tbody tr[data-asoc-id="' + a.asociacion_id + '"]');
          if (!tr) return;
          var inv = tr.querySelector('.fvd-ev-cell-inv');
          var est = tr.querySelector('.fvd-ev-cell-est');
          var ni = tr.querySelector('.fvd-ev-cell-ni');
          var sel = tr.querySelector('.fvd-ev-sel-estado');
          var idStr = String(a.asociacion_id);
          var newCount = a.num_inscritos;
          if (inv) inv.textContent = a.invitado_en ? a.invitado_en : '—';
          if (est) est.textContent = a.estado_respuesta || 'pendiente';
          if (sel && ['pendiente', 'aceptada', 'declinada'].indexOf(a.estado_respuesta) >= 0) sel.value = a.estado_respuesta;
          if (ni) {
            var oldC = prevCounts[idStr] !== undefined ? prevCounts[idStr] : 0;
            ni.textContent = String(newCount);
            if (newCount > oldC) flashRow(tr);
            prevCounts[idStr] = newCount;
          }
        });
        if (lastEl) {
          try {
            var t = new Date(data.server_time);
            lastEl.textContent = 'Última lectura: ' + fmtTime(t);
          } catch (e) {
            lastEl.textContent = 'Actualizado';
          }
        }
      })
      .catch(function () {
        if (lastEl) lastEl.textContent = 'Error de red';
      });
  }

  window.setInterval(poll, 8000);
  window.setTimeout(poll, 1200);
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && toggle && toggle.checked) poll();
  });
  if (toggle) {
    toggle.addEventListener('change', function () {
      if (toggle.checked) poll();
    });
  }
})();
</script>
</div>

<?php endif; ?>
<?php endif; ?>
</div>
