<?php
/** @var array $result */
/** @var string $selfUrl */
$fvd_admin = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
?>

<h1>Tarifas (costos)</h1>
<p class="fvd-atl-muted" style="margin-top:0">Tabla global sin filtro por asociación.</p>
<?php if (!empty($fvd_error)): ?><p class="fvd-mod-msg"><?= htmlspecialchars($fvd_error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

<?php if ($fvd_admin): ?>
<div class="fvd-mod-toolbar">
    <a href="<?= htmlspecialchars($selfUrl . '?action=form', ENT_QUOTES, 'UTF-8') ?>" class="fvd-btn-primary">Nuevo</a>
</div>
<?php endif; ?>

<div class="fvd-mod-table-wrap">
    <table class="fvd-mod-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>Afil.</th>
            <th>Anual.</th>
            <th>Carnets</th>
            <th>Trasp.</th>
            <th>Inscr.</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($result['rows'] as $r): ?>
            <tr>
                <td><?= (int) $r['id'] ?></td>
                <td><?= htmlspecialchars((string) ($r['fecha'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['afiliacion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['anualidad'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['carnets'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['traspasos'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['inscripciones'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <?php if ($fvd_admin): ?>
                        <a href="<?= htmlspecialchars($selfUrl . '?action=form&id=' . (int) $r['id'], ENT_QUOTES, 'UTF-8') ?>">Editar</a>
                        &nbsp;|&nbsp;<a href="<?= htmlspecialchars($selfUrl . '?action=delete&id=' . (int) $r['id'], ENT_QUOTES, 'UTF-8') ?>" onclick="return confirm('¿Eliminar?');">Eliminar</a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
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
