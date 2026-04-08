<?php
/** @var array $result */
/** @var string $selfUrl */
/** @var array<string, mixed>|null $fvd_deuda_masiva_result */
$fvd_deuda_masiva_result = $fvd_deuda_masiva_result ?? null;
$fvd_admin = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
$confirmMasivo = $fvd_admin
    ? '¿Sincronizar estados de cuenta para todas las asociaciones?'
    : '¿Sincronizar estados de cuenta solo para su asociación?';
$rows = $result['rows'] ?? [];
$tituloTorneo = 'Torneo';
if ($rows !== []) {
    $tituloTorneo = (string) ($rows[0]['torneo_nombre'] ?? $rows[0]['torneo_id'] ?? 'Torneo');
}
$fmtEur = static function ($value): string {
    if ($value === null || $value === '') {
        return '—';
    }
    return number_format((float) $value, 2, ',', '.');
};
?>

<h1><?= htmlspecialchars($tituloTorneo, ENT_QUOTES, 'UTF-8') ?></h1>
<p>Relacion de cuentas por asociacion y reporte general de asociaciones con informacion.</p>
<p style="font-size:0.8125rem;margin:8px 0 12px;line-height:1.45">
    <a href="<?= htmlspecialchars($selfUrl . '?action=estadisticas_inscripcion', ENT_QUOTES, 'UTF-8') ?>">Estadísticas por origen (<code>inscripcion_torneo</code>)</a>
    — conteos por asociación y concepto, sin calcular deudas. La sincronización de montos en esta pantalla sigue usando <code>atletas</code> como destino del portal.
</p>
<?php if (!empty($fvd_error)): ?><p class="fvd-mod-msg"><?= htmlspecialchars($fvd_error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if (($_GET['msg'] ?? '') === 'deuda_actualizada'): ?><p class="fvd-mod-msg" style="color:#166534;">Deuda actualizada desde atletas.</p><?php endif; ?>
<?php if (($_GET['msg'] ?? '') === 'deuda_masiva' && is_array($fvd_deuda_masiva_result)): ?>
    <?php
    $okM = (int) ($fvd_deuda_masiva_result['ok'] ?? 0);
    $omM = (int) ($fvd_deuda_masiva_result['omitidos'] ?? 0);
    $erM = (int) ($fvd_deuda_masiva_result['errores'] ?? 0);
    $msgs = $fvd_deuda_masiva_result['mensajes'] ?? [];
    ?>
    <div class="fvd-mod-msg" style="color:#166534;">Sincronización masiva: <strong><?= $okM ?></strong> actualizados; omitidos (torneo finalizado): <strong><?= $omM ?></strong>.
        <?php if ($erM > 0): ?> <span style="color:#991b1b;">Errores: <strong><?= $erM ?></strong> (ver detalle abajo).</span><?php endif; ?>
    </div>
    <?php if ($erM > 0 && is_array($msgs) && $msgs !== []): ?>
        <pre class="fvd-mod-msg" style="font-size:0.8rem;max-height:220px;overflow:auto;background:#fef2f2;"><?= htmlspecialchars(implode("\n", array_slice($msgs, 0, 40)), ENT_QUOTES, 'UTF-8') ?></pre>
    <?php endif; ?>
<?php endif; ?>

<div class="fvd-deuda-toolbar" style="margin:12px 0;padding:12px;border:1px solid var(--fvd-border,#e5e7eb);border-radius:8px;background:var(--fvd-muted-bg,#f9fafb)">
    <p style="margin:0 0 8px;font-size:0.875rem;font-weight:600">
        <?= $fvd_admin ? 'Administración general' : 'Delegado de club' ?> — actualizar estados de cuenta
    </p>
    <p style="margin:0 0 10px;font-size:0.8125rem;color:var(--fvd-muted,#64748b);line-height:1.45">
        <?php if ($fvd_admin): ?>
            Recalcula montos desde la tabla <strong>atletas</strong> (destino del portal) para <strong>todas las asociaciones</strong> con fila de deuda (torneos no finalizados).
        <?php else: ?>
            Recalcula desde <strong>atletas</strong> solo para <strong>su asociación</strong> (torneos con deuda y no finalizados).
        <?php endif; ?>
        Para ver totales según la tabla de <strong>inscripciones</strong> (origen), use el enlace de estadísticas arriba.
    </p>
    <form method="post" action="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" style="display:inline;margin:0" onsubmit="return confirm(<?= json_encode($confirmMasivo, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);">
        <input type="hidden" name="_action" value="actualizar_deudas_masivo">
        <button type="submit" class="fvd-btn fvd-btn--primary" style="padding:8px 18px;font-size:0.9rem">
            <?= $fvd_admin ? 'Sincronizar todas las asociaciones' : 'Sincronizar mi asociación' ?>
        </button>
    </form>
</div>

<div class="fvd-mod-table-wrap">
    <table class="fvd-mod-table fvd-mod-table--nowrap">
        <thead>
        <tr>
            <th>Asociación</th>
            <th>Monto total</th>
            <th>Pagos</th>
            <th>Saldo</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= htmlspecialchars((string) ($r['asoc_nombre'] ?? $r['asociacion_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($fmtEur($r['monto_total_eur'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($fmtEur($r['pagado_eur'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                <td title="<?= (($r['saldo_eur'] ?? null) === null) ? 'Defina deuda total EUR para calcular saldo.' : '' ?>"><?= htmlspecialchars($fmtEur($r['saldo_eur'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <a href="<?= htmlspecialchars($selfUrl . '?action=form&tid=' . (int) $r['torneo_id'] . '&aid=' . (int) $r['asociacion_id'], ENT_QUOTES, 'UTF-8') ?>">Ver detalle</a>
                    <form method="post" action="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" style="display:inline;margin-left:6px">
                        <input type="hidden" name="_action" value="actualizar_deuda">
                        <input type="hidden" name="torneo_id" value="<?= (int) $r['torneo_id'] ?>">
                        <input type="hidden" name="asociacion_id" value="<?= (int) $r['asociacion_id'] ?>">
                        <input type="hidden" name="redirect" value="list">
                        <button type="submit" class="fvd-btn fvd-btn--secondary" style="padding:4px 10px;font-size:0.85rem;vertical-align:middle" title="Recalcular montos desde atletas (destino portal), no desde inscripcion_torneo">Sincronizar deuda</button>
                    </form>
                    <?php if ($fvd_admin): ?>
                        &nbsp;|&nbsp;<a href="<?= htmlspecialchars($selfUrl . '?action=delete&tid=' . (int) $r['torneo_id'] . '&aid=' . (int) $r['asociacion_id'], ENT_QUOTES, 'UTF-8') ?>" onclick="return confirm('¿Eliminar deuda?');">Eliminar</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($result['rows'] === []): ?>
            <tr><td colspan="5" style="padding:12px">Sin registros.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php $p = (int) $result['page']; $pages = (int) $result['pages']; ?>
<nav class="fvd-mod-pager">
    <span><?= (int) $result['total'] ?> reg. · pág. <?= $p ?>/<?= $pages ?></span>
    <?php if ($p > 1): ?><a href="<?= htmlspecialchars($selfUrl . '?page=' . ($p - 1), ENT_QUOTES, 'UTF-8') ?>">Anterior</a><?php endif; ?>
    <?php if ($p < $pages): ?><a href="<?= htmlspecialchars($selfUrl . '?page=' . ($p + 1), ENT_QUOTES, 'UTF-8') ?>">Siguiente</a><?php endif; ?>
</nav>
