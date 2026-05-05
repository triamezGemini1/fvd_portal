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
$asocClearParams = ['action' => 'list'];
$asocClearParams = fvd_return_merge_get_params($asocClearParams);
$asocClearUrl = $selfUrl . '?' . http_build_query($asocClearParams);
?>

<h1>Asociaciones</h1>
<?php if (!empty($fvd_error ?? '')): ?><p class="fvd-mod-msg"><?= htmlspecialchars((string) $fvd_error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if (isset($_GET['bulk_ok']) && (string) $_GET['bulk_ok'] === '1' && $fvd_is_admin): ?>
    <p class="fvd-mod-msg" style="background:#ecfdf5;border-color:#10b981">Actualización masiva: <?= (int) ($_GET['bulk_n'] ?? 0) ?> fila(s) afectada(s).</p>
<?php endif; ?>

<div class="fvd-mod-toolbar fvd-asoc-admin-strip" style="flex-wrap:wrap;align-items:flex-end;gap:12px;padding:12px;border:1px solid var(--fvd-border,#e5e7eb);border-radius:8px;background:var(--fvd-surface-alt,#f9fafb);margin-bottom:12px">
    <form method="get" action="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-admin-filter-form" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;flex:1;min-width:min(100%,320px)">
        <input type="hidden" name="action" value="list">
        <?php if (isset($_GET['ret']) && is_string($_GET['ret']) && fvd_return_sanitize($_GET['ret']) !== null): ?>
        <input type="hidden" name="ret" value="<?= htmlspecialchars($_GET['ret'], ENT_QUOTES, 'UTF-8') ?>">
        <?php elseif (isset($_GET['return']) && is_string($_GET['return']) && fvd_return_sanitize($_GET['return']) !== null): ?>
        <input type="hidden" name="return" value="<?= htmlspecialchars($_GET['return'], ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <?php if (isset($_GET['embedded']) && (string) $_GET['embedded'] === '1'): ?>
        <input type="hidden" name="embedded" value="1">
        <?php endif; ?>
        <?php if (isset($_GET['fvd_master_embed']) && (string) $_GET['fvd_master_embed'] === '1'): ?>
        <input type="hidden" name="fvd_master_embed" value="1">
        <?php endif; ?>
        <div>
            <label for="asoc-filtro-estado" style="font-size:.8125rem;color:var(--fvd-muted);display:block;font-weight:600">Mostrar asociaciones</label>
            <select class="fvd-input" id="asoc-filtro-estado" name="estado" style="min-width:14rem;max-width:100%" onchange="this.form.submit()" title="Filtrar por estatus para marcar filas y activar o desactivar en bloque">
                <option value="todas"<?= $filtroEstatus === 'todas' ? ' selected' : '' ?>>Todas (activas e inactivas)</option>
                <option value="activas"<?= $filtroEstatus === 'activas' ? ' selected' : '' ?>>Solo activas</option>
                <option value="inactivas"<?= $filtroEstatus === 'inactivas' ? ' selected' : '' ?>>Solo inactivas</option>
            </select>
        </div>
        <div>
            <label style="font-size:.8125rem;color:var(--fvd-muted);display:block">Buscar por nombre</label>
            <input class="fvd-input" type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nombre" style="max-width:14rem">
        </div>
        <button type="submit" class="fvd-input" style="width:auto;align-self:flex-end;padding:6px 12px">Buscar</button>
        <a href="<?= htmlspecialchars($asocClearUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-input" style="width:auto;align-self:flex-end;padding:6px 12px;display:inline-flex;align-items:center;text-decoration:none;box-sizing:border-box">Limpiar</a>
        <?php if ($fvd_is_admin): ?>
        <span style="align-self:flex-end;width:1px;height:28px;background:var(--fvd-border,#e5e7eb);margin:0 4px;display:inline-block" aria-hidden="true"></span>
        <button type="submit" form="fvd-asoc-bulk-form" class="fvd-btn-primary" style="align-self:flex-end;padding:6px 12px;font-size:.875rem" data-bulk="1">Activar marcadas</button>
        <button type="submit" form="fvd-asoc-bulk-form" class="fvd-input" style="width:auto;align-self:flex-end;padding:6px 12px" data-bulk="0">Desactivar marcadas</button>
        <?php endif; ?>
    </form>
    <?php if ($fvd_is_admin): ?>
        <a href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=form'), ENT_QUOTES, 'UTF-8') ?>" class="fvd-btn-primary" style="align-self:flex-end;white-space:nowrap">Nueva</a>
    <?php endif; ?>
</div>
<?php if ($fvd_is_admin): ?>
<p class="fvd-mod-hint" style="margin:-4px 0 12px;font-size:.8125rem;color:var(--fvd-muted)">Elija «Solo inactivas» o «Solo activas», marque las filas de esta página y use «Activar marcadas» o «Desactivar marcadas».</p>
<?php endif; ?>

<?php if ($fvd_is_admin): ?>
<form method="post" action="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" id="fvd-asoc-bulk-form" onsubmit="return fvdAsocBulkConfirm();">
    <input type="hidden" name="_action" value="bulk_estatus">
    <input type="hidden" name="bulk_estatus_val" id="fvd-asoc-bulk-val" value="">
    <input type="hidden" name="ret_page" value="<?= (int) $result['page'] ?>">
    <input type="hidden" name="ret_q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="ret_estado" value="<?= htmlspecialchars($filtroEstatus, ENT_QUOTES, 'UTF-8') ?>">
    <?php if (isset($_GET['embedded']) && (string) $_GET['embedded'] === '1'): ?>
    <input type="hidden" name="embedded" value="1">
    <?php endif; ?>
    <?php if (isset($_GET['fvd_master_embed']) && (string) $_GET['fvd_master_embed'] === '1'): ?>
    <input type="hidden" name="fvd_master_embed" value="1">
    <?php endif; ?>
<?php endif; ?>

<div class="fvd-mod-table-wrap">
    <table class="fvd-mod-table fvd-mod-table--nowrap">
        <thead>
        <tr>
            <?php if ($fvd_is_admin): ?>
            <th style="width:2.5rem;text-align:center"><input type="checkbox" id="fvd-asoc-chk-all" title="Seleccionar todas en esta página" aria-label="Seleccionar todas"></th>
            <?php endif; ?>
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
            <?php
            $esActiva = FvdAdminService::asociacionEstatusEsActiva($r);
            $rowInactiveStyle = !$esActiva ? ' style="background:#f1f5f9;color:#64748b"' : '';
            ?>
            <tr<?= $rowInactiveStyle ?>>
                <?php if ($fvd_is_admin): ?>
                <td style="text-align:center"><input class="fvd-asoc-chk-row" type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" aria-label="Seleccionar asociación <?= (int) $r['id'] ?>"></td>
                <?php endif; ?>
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
                <td><?= $esActiva ? 'Activa' : 'Inactiva' ?></td>
                <td style="white-space:nowrap;font-size:0.8125rem">
                    <a href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=form&id=' . (int) $r['id']), ENT_QUOTES, 'UTF-8') ?>">Ver</a>
                    &nbsp;|&nbsp;<a href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=form&id=' . (int) $r['id']), ENT_QUOTES, 'UTF-8') ?>">Editar</a>
                    <?php if ($fvd_is_admin): ?>
                        &nbsp;|&nbsp;<a href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=toggle_estatus&id=' . (int) $r['id'] . $listQuerySuffix), ENT_QUOTES, 'UTF-8') ?>" title="Conmutar activa/inactiva" onclick="return confirm('¿<?= $esActiva ? 'Desactivar' : 'Activar' ?> esta asociación?');"><?= $esActiva ? 'Desactivar' : 'Activar' ?></a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($result['rows'] === []): ?>
            <tr><td colspan="<?= $fvd_is_admin ? '9' : '8' ?>" style="padding:12px">Sin registros.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php if ($fvd_is_admin): ?>
</form>
<script>
(function () {
    var all = document.getElementById('fvd-asoc-chk-all');
    if (!all) return;
    all.addEventListener('change', function () {
        document.querySelectorAll('.fvd-asoc-chk-row').forEach(function (c) { c.checked = all.checked; });
    });
})();
function fvdAsocBulkConfirm() {
    var form = document.getElementById('fvd-asoc-bulk-form');
    var hid = document.getElementById('fvd-asoc-bulk-val');
    if (!form || !hid) return true;
    var v = hid.value;
    if (v !== '0' && v !== '1') {
        alert('Use «Activar seleccionadas» o «Desactivar seleccionadas».');
        return false;
    }
    var n = form.querySelectorAll('.fvd-asoc-chk-row:checked').length;
    if (n === 0) {
        alert('Marque al menos una asociación.');
        return false;
    }
    var msg = v === '1' ? '¿Activar (estatus = 1) las asociaciones seleccionadas?' : '¿Desactivar (estatus = 0) las asociaciones seleccionadas?';
    return confirm(msg);
}
(function () {
    document.querySelectorAll('button[type="submit"][data-bulk]').forEach(function (btn) {
        var owner = btn.form;
        if (!owner || owner.id !== 'fvd-asoc-bulk-form') {
            return;
        }
        btn.addEventListener('click', function () {
            var h = document.getElementById('fvd-asoc-bulk-val');
            if (h) {
                h.value = btn.getAttribute('data-bulk') || '';
            }
        });
    });
})();
</script>
<?php endif; ?>

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
unset($navBase['bulk_ok'], $navBase['bulk_n']);
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
