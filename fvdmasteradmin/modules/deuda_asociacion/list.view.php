<?php
/** @var array $result */
/** @var string $selfUrl */
$fvd_admin = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
?>

<h1>Deudas (asociación / torneo)</h1>
<?php if (!empty($fvd_error)): ?><p class="fvd-mod-msg"><?= htmlspecialchars($fvd_error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

<div class="fvd-mod-table-wrap">
    <table class="fvd-mod-table fvd-mod-table--nowrap">
        <thead>
        <tr>
            <th>Torneo</th>
            <th>Asociación</th>
            <th>Monto total</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($result['rows'] as $r): ?>
            <tr>
                <td><?= htmlspecialchars((string) ($r['torneo_nombre'] ?? $r['torneo_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['asoc_nombre'] ?? $r['asociacion_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['monto_total'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <a href="<?= htmlspecialchars($selfUrl . '?action=form&tid=' . (int) $r['torneo_id'] . '&aid=' . (int) $r['asociacion_id'], ENT_QUOTES, 'UTF-8') ?>">Editar montos</a>
                    <?php if ($fvd_admin): ?>
                        &nbsp;|&nbsp;<a href="<?= htmlspecialchars($selfUrl . '?action=delete&tid=' . (int) $r['torneo_id'] . '&aid=' . (int) $r['asociacion_id'], ENT_QUOTES, 'UTF-8') ?>" onclick="return confirm('¿Eliminar deuda?');">Eliminar</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($result['rows'] === []): ?>
            <tr><td colspan="4" style="padding:12px">Sin registros.</td></tr>
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
