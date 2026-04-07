<?php
/** @var array $result */
/** @var string $selfUrl */
/** @var string $q */
?>

<h1>Atletas</h1>
<?php if (!empty($fvd_error)): ?><p class="fvd-mod-msg"><?= htmlspecialchars($fvd_error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

<div class="fvd-mod-toolbar">
    <form method="get" action="" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
        <input type="hidden" name="action" value="list">
        <div>
            <label style="font-size:.8125rem;color:var(--fvd-muted);display:block">Buscar</label>
            <input class="fvd-input" type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Cédula o nombre" style="max-width:14rem">
        </div>
        <button type="submit" class="fvd-input" style="width:auto;padding:6px 12px">Ir</button>
        <a href="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-input" style="width:auto;padding:6px 12px;display:inline-flex;align-items:center;text-decoration:none;box-sizing:border-box">Limpiar</a>
    </form>
    <a href="<?= htmlspecialchars($selfUrl . '?action=form', ENT_QUOTES, 'UTF-8') ?>" class="fvd-btn-primary">Nuevo</a>
</div>

<div class="fvd-mod-table-wrap">
    <table class="fvd-mod-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>Cédula</th>
            <th>Nombre</th>
            <th>Sexo</th>
            <th>Nº FVD</th>
            <th>Asociación</th>
            <th>Estatus</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($result['rows'] as $r): ?>
            <?php
            $est = trim((string) ($r['estatus'] ?? ''));
            $susp = strcasecmp($est, 'Suspendido') === 0;
            ?>
            <tr class="<?= $susp ? 'fvd-susp' : '' ?>">
                <td><?= (int) $r['id'] ?></td>
                <td><?= htmlspecialchars((string) ($r['cedula'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['sexo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['numfvd'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['asociacion_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($est, ENT_QUOTES, 'UTF-8') ?></td>
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
