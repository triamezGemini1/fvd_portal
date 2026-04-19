<?php
declare(strict_types=1);
/** @var array<string,mixed>|null $eventoTorneo */
/** @var int $id */
/** @var list<array<string,mixed>> $convocatoriaMiAsoc */
/** @var string $fvd_url_salir_torneo_ctx */
/** @var string $fvd_url_pdf_invitacion */
/** @var int $fvd_delegado_notif_id */
/** @var string $fvd_delegado_invitacion_archivo */

$t = $eventoTorneo;
$tidTorneo = (int) $id;
$appBase = rtrim((string) env('APP_BASE_PATH', ''), '/');
$urlTorneoInsc = admin_module_url('torneo_inscripcion/index.php?torneo_id=' . $tidTorneo);
$urlPublicTorneo = url('torneo_publico.php?id=' . $tidTorneo);
?>
<div class="fvd-torneo-evento fvd-torneo-evento--delegado">
    <div class="fvd-mis-panel" style="margin-bottom:12px;padding:10px 12px;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.35);border-radius:8px;max-width:56rem">
        <strong>Modo torneo</strong> — Las acciones aquí se centran en este evento. Para volver al panel general de su asociación, use <a href="<?= htmlspecialchars($fvd_url_salir_torneo_ctx, ENT_QUOTES, 'UTF-8') ?>">Salir del modo torneo</a>.
    </div>

<?php if ($t === null): ?>
    <h1 class="fvd-torneo-evento__title">Torneo no encontrado</h1>
    <p><a class="fvd-torneo-evento__link" href="<?= htmlspecialchars($appBase . '/fvdmasteradmin/delegado_dashboard.php', ENT_QUOTES, 'UTF-8') ?>">Volver al panel</a></p>
<?php else: ?>
    <div class="fvd-mis-panel">
        <nav class="fvd-mis-panel__crumb" aria-label="Ruta">
            <a href="<?= htmlspecialchars($fvd_url_salir_torneo_ctx, ENT_QUOTES, 'UTF-8') ?>">← Panel de asociación</a>
        </nav>
        <header class="fvd-mis-panel__hero">
            <h1 class="fvd-mis-panel__title"><?= htmlspecialchars((string) ($t['nombre'] ?? 'Torneo'), ENT_QUOTES, 'UTF-8') ?></h1>
            <div class="fvd-mis-panel__hero-meta">
                <span>ID #<?= $tidTorneo ?></span>
                <span><?= htmlspecialchars(substr((string) ($t['fechator'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8') ?></span>
                <?php if (!empty($t['lugar'])): ?><span><?= htmlspecialchars((string) $t['lugar'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
            </div>
        </header>

        <div class="fvd-mis-panel__grid" style="margin-top:14px">
            <div class="fvd-mis-card">
                <div class="fvd-mis-card__head fvd-mis-card__head--mesas">Invitación (PDF)</div>
                <div class="fvd-mis-card__body">
                    <?php if ($fvd_delegado_notif_id > 0 && trim($fvd_delegado_invitacion_archivo) !== ''): ?>
                        <p class="fvd-mis-card__hint" style="margin-top:0">Documento adjunto al torneo cuando se envió el aviso a delegados.</p>
                        <a class="fvd-mis-btn fvd-mis-btn--cyan" href="<?= htmlspecialchars($fvd_url_pdf_invitacion, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Abrir / descargar PDF</a>
                    <?php else: ?>
                        <p class="fvd-mis-card__warn">No hay archivo de invitación registrado en este aviso. Si debe existir, contacte a la FVD.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="fvd-mis-card">
                <div class="fvd-mis-card__head fvd-mis-card__head--ops">Inscripción de su asociación</div>
                <div class="fvd-mis-card__body fvd-mis-card__actions">
                    <p class="fvd-mis-card__hint" style="margin-top:0">Inscriba atletas de su club en este torneo (requiere invitación registrada en convocatoria).</p>
                    <a class="fvd-mis-btn fvd-mis-btn--amber" href="<?= htmlspecialchars($urlTorneoInsc, ENT_QUOTES, 'UTF-8') ?>">Inscribir al torneo</a>
                    <a class="fvd-mis-btn fvd-mis-btn--slate" href="<?= htmlspecialchars($urlPublicTorneo, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Ficha pública del torneo</a>
                </div>
            </div>
        </div>

        <h2 class="fvd-torneo-evento__h2" style="margin-top:1.25rem">Su asociación en la convocatoria</h2>
        <?php if ($convocatoriaMiAsoc === []): ?>
            <p class="fvd-torneo-evento__text">No hay fila de convocatoria para su asociación. La FVD debe registrar la invitación para que pueda inscribir.</p>
        <?php else: ?>
            <div class="fvd-mod-table-wrap">
                <table class="fvd-mod-table" style="font-size:0.8125rem">
                    <thead>
                    <tr><th>Invitación registrada</th><th>Respuesta</th><th>Inscritos</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($convocatoriaMiAsoc as $row): ?>
                        <tr>
                            <td><?= !empty($row['invitado_en']) ? htmlspecialchars((string) $row['invitado_en'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                            <td><?= htmlspecialchars((string) ($row['estado_respuesta'] ?? 'pendiente'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><strong><?= (int) ($row['num_inscritos'] ?? 0) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
</div>
