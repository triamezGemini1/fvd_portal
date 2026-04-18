<?php
/** @var array{total:int,page:int,per_page:int,pages:int,rows:list} $result */
/** @var string $selfUrl */
/** @var string $q */
/** @var string $filtroEstatus */
$fvd_is_admin = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
$listQs = [];
if ($result['page'] > 1) {
    $listQs['page'] = (int) $result['page'];
}
if ($q !== '') {
    $listQs['q'] = $q;
}
if ($filtroEstatus !== 'todas') {
    $listQs['estado'] = $filtroEstatus;
}
$listQuerySuffix = $listQs === [] ? '' : '&' . http_build_query($listQs);
?>

<h1>Asociaciones</h1>
<?php if (!empty($fvd_error ?? '')): ?><p class="fvd-mod-msg"><?= htmlspecialchars((string) $fvd_error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

<div class="fvd-mod-toolbar">
    <form method="get" action="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-admin-filter-form" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
        <input type="hidden" name="action" value="list">
        <?php if (isset($_GET['ret']) && is_string($_GET['ret']) && fvd_return_sanitize($_GET['ret']) !== null): ?>
        <input type="hidden" name="ret" value="<?= htmlspecialchars($_GET['ret'], ENT_QUOTES, 'UTF-8') ?>">
        <?php elseif (isset($_GET['return']) && is_string($_GET['return']) && fvd_return_sanitize($_GET['return']) !== null): ?>
        <input type="hidden" name="return" value="<?= htmlspecialchars($_GET['return'], ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <div>
            <label style="font-size:.8125rem;color:var(--fvd-muted);display:block">Buscar por nombre</label>
            <input class="fvd-input" type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nombre" style="max-width:14rem">
        </div>
        <div>
            <label for="asoc-filtro-estado" style="font-size:.8125rem;color:var(--fvd-muted);display:block">Estatus</label>
            <select class="fvd-input" id="asoc-filtro-estado" name="estado" style="max-width:12rem" onchange="this.form.submit()">
                <option value="todas"<?= $filtroEstatus === 'todas' ? ' selected' : '' ?>>Todas</option>
                <option value="activas"<?= $filtroEstatus === 'activas' ? ' selected' : '' ?>>Activas</option>
                <option value="inactivas"<?= $filtroEstatus === 'inactivas' ? ' selected' : '' ?>>Inactivas</option>
            </select>
        </div>
        <button type="submit" class="fvd-input" style="width:auto;align-self:flex-end;padding:6px 12px">Buscar</button>
        <a href="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-input" style="width:auto;align-self:flex-end;padding:6px 12px;display:inline-flex;align-items:center;text-decoration:none;box-sizing:border-box">Limpiar</a>
    </form>
    <?php if ($fvd_is_admin): ?>
        <a href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=form'), ENT_QUOTES, 'UTF-8') ?>" class="fvd-btn-primary">Nueva</a>
    <?php endif; ?>
</div>

<div class="fvd-mod-table-wrap">
    <table class="fvd-mod-table fvd-mod-table--nowrap">
        <thead>
        <tr>
            <th>ID</th>
            <th>Logo</th>
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
            <tr>
                <td><?= (int) $r['id'] ?></td>
                <td>
                    <?php if (!empty($r['logo'])): ?>
                        <img src="<?= htmlspecialchars(upload_url((string) $r['logo']), ENT_QUOTES, 'UTF-8') ?>" alt="" width="32" height="32" style="object-fit:contain;border-radius:4px;vertical-align:middle" loading="lazy">
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td title="<?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['delegado'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['telefono'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?php
                    $esActiva = FvdAdminService::asociacionEstatusEsActiva($r);
                    echo $esActiva ? 'Activa' : 'Inactiva';
                ?></td>
                <td style="white-space:nowrap;font-size:0.8125rem">
                    <a href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=form&id=' . (int) $r['id']), ENT_QUOTES, 'UTF-8') ?>">Ver</a>
                    &nbsp;|&nbsp;<a href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=form&id=' . (int) $r['id']), ENT_QUOTES, 'UTF-8') ?>">Editar</a>
                    <?php if ($fvd_is_admin): ?>
                        &nbsp;|&nbsp;<a href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=toggle_estatus&id=' . (int) $r['id'] . $listQuerySuffix), ENT_QUOTES, 'UTF-8') ?>" title="Conmutar activa/inactiva" onclick="return confirm('¿<?= $esActiva ? 'Desactivar' : 'Activar' ?> esta asociación?');"><?= $esActiva ? 'Desactivar' : 'Activar' ?></a>
                        &nbsp;|&nbsp;<a href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=delete&id=' . (int) $r['id']), ENT_QUOTES, 'UTF-8') ?>" onclick="return confirm('¿Eliminar esta asociación?');">Eliminar</a>
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
$navBase = [];
if ($q !== '') {
    $navBase['q'] = $q;
}
if ($filtroEstatus !== 'todas') {
    $navBase['estado'] = $filtroEstatus;
}
$navBase = fvd_return_merge_get_params($navBase);
$hrefAsocPage = function (int $pg) use ($selfUrl, $navBase): string {
    $params = $navBase;
    if ($pg > 1) {
        $params['page'] = $pg;
    }
    return $params === [] ? $selfUrl : $selfUrl . '?' . http_build_query($params);
};
?>
<nav class="fvd-mod-pager">
    <span><?= (int) $result['total'] ?> reg. · pág. <?= $p ?>/<?= $pages ?></span>
    <?php if ($p > 1): ?><a href="<?= htmlspecialchars($hrefAsocPage($p - 1), ENT_QUOTES, 'UTF-8') ?>">Anterior</a><?php endif; ?>
    <?php if ($p < $pages): ?><a href="<?= htmlspecialchars($hrefAsocPage($p + 1), ENT_QUOTES, 'UTF-8') ?>">Siguiente</a><?php endif; ?>
</nav>
