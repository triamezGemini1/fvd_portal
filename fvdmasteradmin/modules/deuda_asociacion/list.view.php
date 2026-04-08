<?php
/** @var array $result */
/** @var string $selfUrl */
$fvd_admin = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
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
<?php if (!empty($fvd_error)): ?><p class="fvd-mod-msg"><?= htmlspecialchars($fvd_error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if (($_GET['msg'] ?? '') === 'deuda_actualizada'): ?><p class="fvd-mod-msg" style="color:#166534;">Deuda actualizada desde atletas.</p><?php endif; ?>

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
                        <button type="submit" class="fvd-btn fvd-btn--secondary" style="padding:4px 10px;font-size:0.85rem;vertical-align:middle" title="Recalcular montos desde la tabla de atletas">Sincronizar deuda</button>
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
