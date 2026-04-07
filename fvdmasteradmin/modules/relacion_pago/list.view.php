<?php
/** @var array $result */
/** @var string $selfUrl */
?>

<h1>Pagos registrados</h1>
<?php if (!empty($fvd_error)): ?><p class="fvd-mod-msg"><?= htmlspecialchars($fvd_error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

<div class="fvd-mod-toolbar">
    <a href="<?= htmlspecialchars($selfUrl . '?action=form', ENT_QUOTES, 'UTF-8') ?>" class="fvd-btn-primary">Registrar pago</a>
</div>

<div class="fvd-mod-table-wrap">
    <table class="fvd-mod-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>Asociación</th>
            <th>Torneo</th>
            <th>Moneda</th>
            <th>Monto</th>
            <th>USD</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($result['rows'] as $r): ?>
            <tr>
                <td><?= (int) $r['id'] ?></td>
                <td><?= htmlspecialchars((string) ($r['fecha'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['asoc_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['torneo_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['moneda'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['monto_total'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['monto_dolares'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td style="white-space:nowrap">
                    <a href="<?= htmlspecialchars($selfUrl . '?action=form&id=' . (int) $r['id'], ENT_QUOTES, 'UTF-8') ?>">Editar</a>
                    &nbsp;|&nbsp;<a href="<?= htmlspecialchars($selfUrl . '?action=delete&id=' . (int) $r['id'], ENT_QUOTES, 'UTF-8') ?>" onclick="return confirm('¿Eliminar?');">Eliminar</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($result['rows'] === []): ?>
            <tr><td colspan="8" style="padding:12px">Sin registros.</td></tr>
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
