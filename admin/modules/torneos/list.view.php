<?php
/** @var array $result */
/** @var string $selfUrl */
/** @var string $q */
/** @var int $fvdTorneoListAnio año calendario opcional (GET anio) */
/** @var string $fvdTorneoDesde fecha Y-m-d opcional */
/** @var string $fvdTorneoHasta fecha Y-m-d opcional */
$fvdTorneoListAnio = (int) ($fvdTorneoListAnio ?? 0);
$fvdTorneoDesde = isset($fvdTorneoDesde) ? (string) $fvdTorneoDesde : '';
$fvdTorneoHasta = isset($fvdTorneoHasta) ? (string) $fvdTorneoHasta : '';
if (!function_exists('fvd_return_merge_get_params')) {
    require_once FVD_PROJECT_ROOT . '/config/fvd_navigation_return.php';
}
$fvdTorneoListQs = static function (array $extra) use ($selfUrl, $q, $fvdTorneoListAnio, $fvdTorneoDesde, $fvdTorneoHasta): string {
    $m = fvd_return_merge_get_params(array_merge(['action' => 'list'], $extra));
    if ($q !== '') {
        $m['q'] = $q;
    }
    if ($fvdTorneoListAnio > 0) {
        $m['anio'] = (string) $fvdTorneoListAnio;
    }
    if ($fvdTorneoDesde !== '') {
        $m['desde'] = $fvdTorneoDesde;
    }
    if ($fvdTorneoHasta !== '') {
        $m['hasta'] = $fvdTorneoHasta;
    }

    return $selfUrl . '?' . http_build_query($m, '', '&', PHP_QUERY_RFC3986);
};
$fvd_es_fvd = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
$fvd_puede_gestion_torneo = $fvd_es_fvd || AuthService::role() === AuthService::ROLE_ASO_ADMIN;
$fvd_list_flash = '';
if (!empty($_SESSION['fvd_torneo_list_flash'])) {
    $fvd_list_flash = (string) $_SESSION['fvd_torneo_list_flash'];
    unset($_SESSION['fvd_torneo_list_flash']);
}
$fvd_list_err = '';
if (!empty($_SESSION['fvd_torneo_list_err'])) {
    $fvd_list_err = (string) $_SESSION['fvd_torneo_list_err'];
    unset($_SESSION['fvd_torneo_list_err']);
}
$fvd_torneo_list_puede_finalizar = !empty($fvd_torneo_list_puede_finalizar ?? false);
if (!function_exists('fvd_master_embed_active')) {
    require_once FVD_PROJECT_ROOT . '/config/fvd_navigation_return.php';
}
$fvdTorneosListHideH1 = fvd_master_embed_active();
?>

<?php if (empty($fvdTorneosListHideH1)): ?>
<?php if (function_exists('fvd_delegado_inner_heading_visible') && fvd_delegado_inner_heading_visible()): ?>
<h1>Torneos</h1>
<?php endif; ?>
<?php endif; ?>
<p style="font-size:0.8125rem;color:var(--fvd-muted);margin:0 0 0.75rem">
    Eventos en <code style="color:var(--fvd-amarillo)">torneosact</code> (clave <code>torneo</code>).
    Filtro por <strong>año</strong> o rango <strong>desde / hasta</strong> (fecha de evento o alta si falta fecha).
    Consultas multi-nivel: vista SQL <code>v_fvd_torneos_consulta_periodo</code> (instalar <code>fvdmasteradmin/sql/install_v_torneo_consulta_periodo.sql</code>).
</p>
<?php if (!empty($fvd_error)): ?><p class="fvd-mod-msg"><?= htmlspecialchars($fvd_error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($fvd_list_flash !== ''): ?><p class="fvd-mod-msg" style="background:#ecfdf5;border-color:#10b981"><?= htmlspecialchars($fvd_list_flash, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($fvd_list_err !== ''): ?><p class="fvd-mod-msg fvd-tf-form-page__err"><?= htmlspecialchars($fvd_list_err, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

<div class="fvd-mod-toolbar">
    <form method="get" action="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
        <input type="hidden" name="action" value="list">
        <?php if (isset($_GET['ret']) && is_string($_GET['ret']) && fvd_return_sanitize($_GET['ret']) !== null): ?>
        <input type="hidden" name="ret" value="<?= htmlspecialchars($_GET['ret'], ENT_QUOTES, 'UTF-8') ?>">
        <?php elseif (isset($_GET['return']) && is_string($_GET['return']) && fvd_return_sanitize($_GET['return']) !== null): ?>
        <input type="hidden" name="return" value="<?= htmlspecialchars($_GET['return'], ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <?php if (function_exists('fvd_master_embed_active') && fvd_master_embed_active()): ?>
        <input type="hidden" name="embedded" value="1">
        <input type="hidden" name="fvd_master_embed" value="1">
        <?php
        if (function_exists('fvd_master_panel_render_context_hiddens')) {
            fvd_master_panel_render_context_hiddens();
        }
        ?>
        <?php endif; ?>
        <div>
            <label style="font-size:.8125rem;color:var(--fvd-muted);display:block">Buscar</label>
            <input class="fvd-input" type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nombre o lugar" style="max-width:14rem">
        </div>
        <div>
            <label style="font-size:.8125rem;color:var(--fvd-muted);display:block">Año (opc.)</label>
            <input class="fvd-input" type="number" name="anio" min="1990" max="2100" step="1" value="<?= $fvdTorneoListAnio > 0 ? (int) $fvdTorneoListAnio : '' ?>" placeholder="p. ej. 2025" style="max-width:6rem">
        </div>
        <div>
            <label style="font-size:.8125rem;color:var(--fvd-muted);display:block">Desde</label>
            <input class="fvd-input" type="date" name="desde" value="<?= htmlspecialchars($fvdTorneoDesde, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label style="font-size:.8125rem;color:var(--fvd-muted);display:block">Hasta</label>
            <input class="fvd-input" type="date" name="hasta" value="<?= htmlspecialchars($fvdTorneoHasta, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <button type="submit" class="fvd-input" style="width:auto;padding:6px 12px">Ir</button>
        <a href="<?= htmlspecialchars(function_exists('fvd_return_append_to_url') ? fvd_return_append_to_url($selfUrl . '?action=list') : ($selfUrl . '?action=list'), ENT_QUOTES, 'UTF-8') ?>" class="fvd-input" style="width:auto;padding:6px 12px;display:inline-flex;align-items:center;text-decoration:none;box-sizing:border-box">Limpiar</a>
    </form>
    <?php if ($fvd_es_fvd): ?>
    <a href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=relacion_grupo'), ENT_QUOTES, 'UTF-8') ?>" class="fvd-input" style="width:auto;padding:6px 12px;display:inline-flex;align-items:center;text-decoration:none;box-sizing:border-box">Relacionar campeonatos</a>
    <?php endif; ?>
    <?php if ($fvd_puede_gestion_torneo): ?>
    <a href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=form'), ENT_QUOTES, 'UTF-8') ?>" class="fvd-btn-primary">Nuevo torneo</a>
    <?php endif; ?>
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
            <?php if ($fvd_puede_gestion_torneo): ?><th>Admin.</th><?php endif; ?>
            <?php if ($fvd_puede_gestion_torneo): ?><th>Acciones</th><?php endif; ?>
            <?php if ($fvd_puede_gestion_torneo): ?><th>Cierre</th><?php endif; ?>
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
                <?php if ($fvd_puede_gestion_torneo): ?>
                <td style="white-space:nowrap"><a href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=evento&id=' . (int) $r['torneo']), ENT_QUOTES, 'UTF-8') ?>">Admin torneo</a></td>
                <?php endif; ?>
                <?php if ($fvd_puede_gestion_torneo): ?>
                <td style="white-space:nowrap;font-size:0.8125rem">
                    <a href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=form&id=' . (int) $r['torneo']), ENT_QUOTES, 'UTF-8') ?>">Ver</a>
                    &nbsp;|&nbsp;<a href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=form&id=' . (int) $r['torneo']), ENT_QUOTES, 'UTF-8') ?>">Editar</a>
                    &nbsp;|&nbsp;<a href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=delete&id=' . (int) $r['torneo']), ENT_QUOTES, 'UTF-8') ?>" onclick="return confirm('¿Eliminar torneo?');">Eliminar</a>
                </td>
                <td style="white-space:nowrap;font-size:0.75rem;vertical-align:middle">
                    <?php
                    $tidRow = (int) ($r['torneo'] ?? 0);
                    $yaFinal = $fvd_torneo_list_puede_finalizar && !empty($r['finalizado_en']);
                    ?>
                    <?php if (!$fvd_torneo_list_puede_finalizar): ?>
                        <span class="fvd-atl-muted" title="Ejecute fvdmasteradmin/sql/install_torneo_grupo_historico.sql si necesita cierre desde el listado">—</span>
                    <?php elseif ($yaFinal): ?>
                        <span class="fvd-atl-muted" title="<?= htmlspecialchars(substr((string) ($r['finalizado_en'] ?? ''), 0, 19), ENT_QUOTES, 'UTF-8') ?>">Concluido</span>
                    <?php else: ?>
                        <form method="post" action="<?= htmlspecialchars(fvd_return_preserve_query_params($selfUrl), ENT_QUOTES, 'UTF-8') ?>" style="margin:0;display:inline" onsubmit="return confirm('¿Dar el torneo #<?= $tidRow ?> por concluido? Se registrará histórico y se limpiarán inscripciones y marcas en atletas que participaron.');">
                            <input type="hidden" name="_action" value="torneo_finalizar">
                            <input type="hidden" name="torneo_id" value="<?= $tidRow ?>">
                            <input type="hidden" name="_from" value="list">
                            <input type="hidden" name="list_page" value="<?= (int) ($result['page'] ?? 1) ?>">
                            <input type="hidden" name="list_q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="fvd-input" style="width:auto;padding:4px 8px;font-size:0.75rem;font-weight:600;background:#7f1d1d;color:#fecaca;border-color:#991b1b">Finalizar</button>
                        </form>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php if ($result['rows'] === []): ?>
            <tr><td colspan="<?= 6 + ($fvd_puede_gestion_torneo ? 3 : 0) ?>" style="padding:12px">Sin registros.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$p = (int) $result['page'];
$pages = (int) $result['pages'];
?>
<nav class="fvd-mod-pager">
    <span><?= (int) $result['total'] ?> reg. · pág. <?= $p ?>/<?= $pages ?></span>
    <?php if ($p > 1): ?><a href="<?= htmlspecialchars($fvdTorneoListQs(['page' => $p - 1]), ENT_QUOTES, 'UTF-8') ?>">Anterior</a><?php endif; ?>
    <?php if ($p < $pages): ?><a href="<?= htmlspecialchars($fvdTorneoListQs(['page' => $p + 1]), ENT_QUOTES, 'UTF-8') ?>">Siguiente</a><?php endif; ?>
</nav>
