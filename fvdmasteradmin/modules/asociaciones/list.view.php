<?php
/** @var array{total:int,page:int,per_page:int,pages:int,rows:list} $result */
/** @var string $selfUrl */
/** @var string $q */
require_once dirname(__DIR__, 3) . '/src/Services/FvdAdminService.php';
$fvd_is_admin = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
?>

<?php if (function_exists('fvd_delegado_inner_heading_visible') && fvd_delegado_inner_heading_visible()): ?>
<h1>Asociaciones</h1>
<?php endif; ?>
<?php if (!empty($fvd_error ?? '')): ?><p class="fvd-mod-msg"><?= htmlspecialchars((string) $fvd_error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

<div class="fvd-mod-toolbar">
    <form method="get" action="" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
        <input type="hidden" name="action" value="list">
        <div>
            <label style="font-size:.8125rem;color:var(--fvd-muted);display:block">Buscar</label>
            <input class="fvd-input" type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nombre" style="max-width:14rem">
        </div>
        <button type="submit" class="fvd-input" style="width:auto;padding:6px 12px">Ir</button>
        <a href="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-input" style="width:auto;padding:6px 12px;display:inline-flex;align-items:center;text-decoration:none;box-sizing:border-box">Limpiar</a>
    </form>
    <?php if ($fvd_is_admin): ?>
        <a href="<?= htmlspecialchars($selfUrl . '?action=form', ENT_QUOTES, 'UTF-8') ?>" class="fvd-btn-primary">Nueva</a>
    <?php endif; ?>
</div>

<div class="fvd-mod-table-wrap">
    <table class="fvd-mod-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Delegado</th>
            <th>Teléfono</th>
            <th>Email</th>
            <th>Estatus</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($result['rows'] as $r): ?>
            <?php
            $esActivaRow = FvdAdminService::asociacionEstatusEsActiva($r);
            $rowInactiveStyle = !$esActivaRow ? ' style="background:#f1f5f9;color:#64748b"' : '';
            ?>
            <tr<?= $rowInactiveStyle ?>>
                <td><?= (int) $r['id'] ?></td>
                <td><?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['delegado'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['telefono'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['estatus'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td style="white-space:nowrap">
                    <a href="<?= htmlspecialchars($selfUrl . '?action=form&id=' . (int) $r['id'], ENT_QUOTES, 'UTF-8') ?>">Editar</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($result['rows'] === []): ?>
            <tr><td colspan="7" style="padding:12px">Sin registros.</td></tr>
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
