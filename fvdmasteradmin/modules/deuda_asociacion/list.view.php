<?php
/** @var array $result */
/** @var string $selfUrl */
/** @var array<string, mixed>|null $fvd_deuda_masiva_result */
/** @var array<string, mixed>|null $fvd_preparar_torneo_result */
/** @var list<array<string, mixed>> $fvdTorneosSelectPreparar */
$fvd_deuda_masiva_result = $fvd_deuda_masiva_result ?? null;
$fvd_preparar_torneo_result = $fvd_preparar_torneo_result ?? null;
$fvdTorneosSelectPreparar = $fvdTorneosSelectPreparar ?? [];
$fvd_admin = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
$confirmMasivo = $fvd_admin
    ? '¿Sincronizar estados de cuenta para todas las asociaciones?'
    : '¿Sincronizar estados de cuenta solo para su asociación?';
$rows = $result['rows'] ?? [];
if (!function_exists('fvd_format_contable')) {
    require_once dirname(__DIR__, 3) . '/config/paths.php';
}
$fmtEur = static function ($value): string {
    return fvd_format_contable($value);
};
$fmtInt = static function ($value): string {
    if ($value === null || $value === '') {
        return '—';
    }

    return number_format((int) $value, 0, ',', '.');
};
?>

<h1>Finanzas — Estados de cuenta por torneo y asociación</h1>
<p>Relación de <code>deuda_asociaciones</code>: una fila por torneo + asociación, con totales de conceptos (sincronización desde <code>inscripcion_torneo</code> si existe la tabla; si no, desde <code>atletas</code>) y pagos acumulados.</p>
<p style="font-size:0.8125rem;margin:8px 0 12px;line-height:1.45">
    <a href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=estadisticas_inscripcion'), ENT_QUOTES, 'UTF-8') ?>">Estadísticas por torneo</a>
    — conteos por asociación desde <code>inscripcion_torneo</code> (preferido) o <code>atletas.torneo_id</code>. La sincronización de montos usa la misma fuente que el generador de deuda.
</p>
<?php if (!empty($fvd_error)): ?><p class="fvd-mod-msg"><?= htmlspecialchars($fvd_error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if (($_GET['msg'] ?? '') === 'preparar_torneo' && is_array($fvd_preparar_torneo_result)): ?>
    <p class="fvd-mod-msg" style="color:#166534;">
        Preparación de torneo: notificaciones marcadas <strong><?= (int) ($fvd_preparar_torneo_result['notificaciones_marcadas'] ?? 0) ?></strong>;
        filas de movimiento sincronizadas <strong><?= (int) ($fvd_preparar_torneo_result['filas_sincronizadas'] ?? 0) ?></strong>
        (<?= (int) ($fvd_preparar_torneo_result['asociaciones'] ?? 0) ?> asociación(es)).
    </p>
<?php endif; ?>
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
            Recalcula montos desde <strong>inscripcion_torneo</strong> (si existe) o <strong>atletas</strong> para <strong>todas las asociaciones</strong> con fila de deuda (torneos no finalizados).
        <?php else: ?>
            Recalcula desde la misma fuente que el generador solo para <strong>su asociación</strong> (torneos con deuda y no finalizados).
        <?php endif; ?>
        Use el enlace de estadísticas arriba para el desglose por renglón.
    </p>
    <form method="post" action="<?= htmlspecialchars(fvd_return_preserve_query_params($selfUrl), ENT_QUOTES, 'UTF-8') ?>" style="display:inline;margin:0" onsubmit="return confirm(<?= json_encode($confirmMasivo, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);">
        <input type="hidden" name="_action" value="actualizar_deudas_masivo">
        <?php if (isset($_GET['ret']) && is_string($_GET['ret']) && fvd_return_sanitize($_GET['ret']) !== null): ?>
        <input type="hidden" name="ret" value="<?= htmlspecialchars($_GET['ret'], ENT_QUOTES, 'UTF-8') ?>">
        <?php elseif (isset($_GET['return']) && is_string($_GET['return']) && fvd_return_sanitize($_GET['return']) !== null): ?>
        <input type="hidden" name="return" value="<?= htmlspecialchars($_GET['return'], ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <button type="submit" class="fvd-btn fvd-btn--primary" style="padding:8px 18px;font-size:0.9rem">
            <?= $fvd_admin ? 'Sincronizar todas las asociaciones' : 'Sincronizar mi asociación' ?>
        </button>
    </form>
</div>

<?php if ($fvd_admin && $fvdTorneosSelectPreparar !== []): ?>
<div class="fvd-deuda-toolbar" style="margin:12px 0;padding:12px;border:1px solid var(--fvd-border,#e5e7eb);border-radius:8px;background:#f0fdf4">
    <p style="margin:0 0 8px;font-size:0.875rem;font-weight:600">Antes de abrir inscripciones (solo administración general)</p>
    <p style="margin:0 0 10px;font-size:0.8125rem;color:var(--fvd-muted,#64748b);line-height:1.45">
        Marca como vistas las notificaciones de delegados para el torneo y ejecuta la sincronización de filas de <strong>movimiento</strong> (canal <code>inscripcion=2</code> en <code>inscripcion_torneo</code>) con los datos actuales de <code>atletas</code>.
    </p>
    <form method="post" action="<?= htmlspecialchars(fvd_return_preserve_query_params($selfUrl), ENT_QUOTES, 'UTF-8') ?>" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end"
          onsubmit="return confirm('¿Procesar notificaciones y sincronizar movimientos para el torneo elegido?');">
        <input type="hidden" name="_action" value="preparar_torneo_inscripciones">
        <?php if (isset($_GET['ret']) && is_string($_GET['ret']) && fvd_return_sanitize($_GET['ret']) !== null): ?>
        <input type="hidden" name="ret" value="<?= htmlspecialchars($_GET['ret'], ENT_QUOTES, 'UTF-8') ?>">
        <?php elseif (isset($_GET['return']) && is_string($_GET['return']) && fvd_return_sanitize($_GET['return']) !== null): ?>
        <input type="hidden" name="return" value="<?= htmlspecialchars($_GET['return'], ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <label style="display:flex;flex-direction:column;gap:4px;font-size:0.875rem">
            <span>Torneo</span>
            <select name="torneo_id" required style="min-width:220px;padding:6px 8px">
                <option value="">— Elija torneo —</option>
                <?php foreach ($fvdTorneosSelectPreparar as $t): ?>
                    <?php $tidOpt = (int) ($t['id'] ?? 0); ?>
                    <option value="<?= $tidOpt ?>"><?= htmlspecialchars((string) ($t['nombre'] ?? $tidOpt), ENT_QUOTES, 'UTF-8') ?> (<?= $tidOpt ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="fvd-btn fvd-btn--primary" style="padding:8px 18px;font-size:0.9rem">Preparar inscripciones</button>
    </form>
</div>
<?php endif; ?>

<div class="fvd-mod-table-wrap" style="overflow-x:auto">
    <table class="fvd-mod-table fvd-mod-table--nowrap" style="font-size:0.78rem">
        <thead>
        <tr>
            <th>Torneo</th>
            <th>Asociación</th>
            <th title="Cant. atletas">Insc.</th>
            <th>Afil.</th>
            <th>Anual.</th>
            <th>Carn.</th>
            <th>Trasp.</th>
            <th>Monto total</th>
            <th>Pagos</th>
            <th>Saldo</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= htmlspecialchars(trim((string) ($r['torneo_nombre'] ?? '') . ' #' . (int) ($r['torneo_id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['asoc_nombre'] ?? $r['asociacion_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($fmtInt($r['total_inscritos'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($fmtInt($r['total_afiliados'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($fmtInt($r['total_anualidad'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($fmtInt($r['total_carnets'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($fmtInt($r['total_traspasos'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($fmtEur($r['monto_total_eur'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($fmtEur($r['pagado_eur'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                <td title="<?= (($r['saldo_eur'] ?? null) === null) ? 'Defina deuda total EUR para calcular saldo.' : '' ?>"><?= htmlspecialchars($fmtEur($r['saldo_eur'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                <td style="white-space:normal;min-width:11rem">
                    <a href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=form&tid=' . (int) $r['torneo_id'] . '&aid=' . (int) $r['asociacion_id']), ENT_QUOTES, 'UTF-8') ?>">Ver detalle</a>
                    <form method="post" action="<?= htmlspecialchars(fvd_return_preserve_query_params($selfUrl), ENT_QUOTES, 'UTF-8') ?>" style="display:inline;margin-left:6px">
                        <input type="hidden" name="_action" value="actualizar_deuda">
                        <input type="hidden" name="torneo_id" value="<?= (int) $r['torneo_id'] ?>">
                        <input type="hidden" name="asociacion_id" value="<?= (int) $r['asociacion_id'] ?>">
                        <input type="hidden" name="redirect" value="list">
                        <?php if (isset($_GET['ret']) && is_string($_GET['ret']) && fvd_return_sanitize($_GET['ret']) !== null): ?>
                        <input type="hidden" name="ret" value="<?= htmlspecialchars($_GET['ret'], ENT_QUOTES, 'UTF-8') ?>">
                        <?php elseif (isset($_GET['return']) && is_string($_GET['return']) && fvd_return_sanitize($_GET['return']) !== null): ?>
                        <input type="hidden" name="return" value="<?= htmlspecialchars($_GET['return'], ENT_QUOTES, 'UTF-8') ?>">
                        <?php endif; ?>
                        <button type="submit" class="fvd-btn fvd-btn--secondary" style="padding:4px 10px;font-size:0.85rem;vertical-align:middle" title="Recalcular montos (inscripcion_torneo si existe; si no, atletas)">Sincronizar</button>
                    </form>
                    <?php if ($fvd_admin): ?>
                        &nbsp;|&nbsp;<a href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=delete&tid=' . (int) $r['torneo_id'] . '&aid=' . (int) $r['asociacion_id']), ENT_QUOTES, 'UTF-8') ?>" onclick="return confirm('¿Eliminar deuda?');">Eliminar</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($result['rows'] === []): ?>
            <tr><td colspan="11" style="padding:12px">Sin registros.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$p = (int) $result['page'];
$pages = (int) $result['pages'];
$deudaRetArg = '';
if (isset($_GET['ret']) && is_string($_GET['ret']) && fvd_return_sanitize($_GET['ret']) !== null) {
    $deudaRetArg = '&ret=' . rawurlencode($_GET['ret']);
} elseif (isset($_GET['return']) && is_string($_GET['return']) && fvd_return_sanitize($_GET['return']) !== null) {
    $deudaRetArg = '&return=' . rawurlencode($_GET['return']);
}
?>
<nav class="fvd-mod-pager">
    <span><?= (int) $result['total'] ?> reg. · pág. <?= $p ?>/<?= $pages ?></span>
    <?php if ($p > 1): ?><a href="<?= htmlspecialchars($selfUrl . '?page=' . ($p - 1) . $deudaRetArg, ENT_QUOTES, 'UTF-8') ?>">Anterior</a><?php endif; ?>
    <?php if ($p < $pages): ?><a href="<?= htmlspecialchars($selfUrl . '?page=' . ($p + 1) . $deudaRetArg, ENT_QUOTES, 'UTF-8') ?>">Siguiente</a><?php endif; ?>
</nav>
