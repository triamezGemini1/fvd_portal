<?php
/** @var array $result */
/** @var string $selfUrl */
$fvd_admin = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
if (!function_exists('fvd_format_contable')) {
    require_once dirname(__DIR__, 3) . '/config/paths.php';
}
?>

<h1>Tarifas (costos)</h1>
<p class="fvd-atl-muted" style="margin-top:0">Tabla global sin filtro por asociación.</p>
<?php if (!empty($fvd_error)): ?><p class="fvd-mod-msg"><?= htmlspecialchars($fvd_error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

<?php if ($fvd_admin): ?>
<div class="fvd-mod-toolbar">
    <a href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=form'), ENT_QUOTES, 'UTF-8') ?>" class="fvd-btn-primary">Nuevo</a>
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
                <td><?= htmlspecialchars(fvd_format_contable($r['afiliacion'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars(fvd_format_contable($r['anualidad'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars(fvd_format_contable($r['carnets'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars(fvd_format_contable($r['traspasos'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars(fvd_format_contable($r['inscripciones'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <?php if ($fvd_admin): ?>
                        <a href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=form&id=' . (int) $r['id']), ENT_QUOTES, 'UTF-8') ?>">Editar</a>
                        &nbsp;|&nbsp;<a href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=delete&id=' . (int) $r['id']), ENT_QUOTES, 'UTF-8') ?>" onclick="return confirm('¿Eliminar?');">Eliminar</a>
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

<?php
$p = (int) $result['page'];
$pages = (int) $result['pages'];
$costosRetArg = '';
if (isset($_GET['ret']) && is_string($_GET['ret']) && fvd_return_sanitize($_GET['ret']) !== null) {
    $costosRetArg = '&ret=' . rawurlencode($_GET['ret']);
} elseif (isset($_GET['return']) && is_string($_GET['return']) && fvd_return_sanitize($_GET['return']) !== null) {
    $costosRetArg = '&return=' . rawurlencode($_GET['return']);
}
?>
<nav class="fvd-mod-pager">
    <span><?= (int) $result['total'] ?> reg. · pág. <?= $p ?>/<?= $pages ?></span>
    <?php if ($p > 1): ?><a href="<?= htmlspecialchars($selfUrl . '?page=' . ($p - 1) . $costosRetArg, ENT_QUOTES, 'UTF-8') ?>">Anterior</a><?php endif; ?>
    <?php if ($p < $pages): ?><a href="<?= htmlspecialchars($selfUrl . '?page=' . ($p + 1) . $costosRetArg, ENT_QUOTES, 'UTF-8') ?>">Siguiente</a><?php endif; ?>
</nav>
