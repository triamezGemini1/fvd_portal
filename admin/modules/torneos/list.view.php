<?php
/** @var array $result */
/** @var string $selfUrl */
/** @var string $q */
$fvd_es_fvd = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
?>

<h1>Torneos</h1>
<p style="font-size:0.8125rem;color:var(--fvd-muted);margin:0 0 0.75rem">Eventos en <code style="color:var(--fvd-amarillo)">torneosact</code> (clave primaria <code>torneo</code>). Tabla con scroll horizontal en pantallas estrechas.</p>
<?php if (!empty($fvd_error)): ?><p class="fvd-mod-msg"><?= htmlspecialchars($fvd_error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

<div class="fvd-mod-toolbar">
    <form method="get" action="" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
        <input type="hidden" name="action" value="list">
        <div>
            <label style="font-size:.8125rem;color:var(--fvd-muted);display:block">Buscar</label>
            <input class="fvd-input" type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nombre o lugar" style="max-width:14rem">
        </div>
        <button type="submit" class="fvd-input" style="width:auto;padding:6px 12px">Ir</button>
        <a href="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-input" style="width:auto;padding:6px 12px;display:inline-flex;align-items:center;text-decoration:none;box-sizing:border-box">Limpiar</a>
    </form>
    <a href="<?= htmlspecialchars($selfUrl . '?action=form', ENT_QUOTES, 'UTF-8') ?>" class="fvd-btn-primary">Nuevo torneo</a>
</div>

<div class="fvd-mod-table-wrap">
    <table class="fvd-mod-table fvd-mod-table--nowrap">
        <thead>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Lugar</th>
            <th>Fecha</th>
            <th>Organización</th>
            <th>Estatus</th>
            <?php if ($fvd_es_fvd): ?><th>Admin.</th><?php endif; ?>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($result['rows'] as $r): ?>
            <tr>
                <td><?= (int) $r['torneo'] ?></td>
                <td title="<?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['lugar'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars(substr((string) ($r['fechator'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['org_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= (int) ($r['estatus'] ?? 0) ?></td>
                <?php if ($fvd_es_fvd): ?>
                <td style="white-space:nowrap"><a href="<?= htmlspecialchars($selfUrl . '?action=evento&id=' . (int) $r['torneo'], ENT_QUOTES, 'UTF-8') ?>">Admin torneo</a></td>
                <?php endif; ?>
                <td style="white-space:nowrap;font-size:0.8125rem">
                    <a href="<?= htmlspecialchars($selfUrl . '?action=form&id=' . (int) $r['torneo'], ENT_QUOTES, 'UTF-8') ?>">Ver</a>
                    &nbsp;|&nbsp;<a href="<?= htmlspecialchars($selfUrl . '?action=form&id=' . (int) $r['torneo'], ENT_QUOTES, 'UTF-8') ?>">Editar</a>
                    &nbsp;|&nbsp;<a href="<?= htmlspecialchars($selfUrl . '?action=delete&id=' . (int) $r['torneo'], ENT_QUOTES, 'UTF-8') ?>" onclick="return confirm('¿Eliminar torneo?');">Eliminar</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($result['rows'] === []): ?>
            <tr><td colspan="<?= $fvd_es_fvd ? '8' : '7' ?>" style="padding:12px">Sin registros.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$qArg = $q !== '' ? '&q=' . rawurlencode($q) : '';
$p = (int) $result['page'];
$pages = (int) $result['pages'];
?>
<nav class="fvd-mod-pager">
    <span><?= (int) $result['total'] ?> reg. · pág. <?= $p ?>/<?= $pages ?></span>
    <?php if ($p > 1): ?><a href="<?= htmlspecialchars($selfUrl . '?page=' . ($p - 1) . $qArg, ENT_QUOTES, 'UTF-8') ?>">Anterior</a><?php endif; ?>
    <?php if ($p < $pages): ?><a href="<?= htmlspecialchars($selfUrl . '?page=' . ($p + 1) . $qArg, ENT_QUOTES, 'UTF-8') ?>">Siguiente</a><?php endif; ?>
</nav>
