<?php
/** @var array $result */
/** @var string $selfUrl */
/** @var string $q */
$fvd_es_fvd = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
$fvd_puede_gestion_torneo = $fvd_es_fvd || AuthService::role() === AuthService::ROLE_ASO_ADMIN;
$fvd_list_flash = '';
if (!empty($_SESSION['fvd_torneo_list_flash'])) {
    $fvd_list_flash = (string) $_SESSION['fvd_torneo_list_flash'];
    unset($_SESSION['fvd_torneo_list_flash']);
}
?>

<h1>Torneos</h1>
<p style="font-size:0.8125rem;color:var(--fvd-muted);margin:0 0 0.75rem">Eventos en <code style="color:var(--fvd-amarillo)">torneosact</code> (clave primaria <code>torneo</code>). Tabla con scroll horizontal en pantallas estrechas.</p>
<?php if (!empty($fvd_error)): ?><p class="fvd-mod-msg"><?= htmlspecialchars($fvd_error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($fvd_list_flash !== ''): ?><p class="fvd-mod-msg" style="background:#ecfdf5;border-color:#10b981"><?= htmlspecialchars($fvd_list_flash, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

<div class="fvd-mod-toolbar">
    <form method="get" action="" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
        <input type="hidden" name="action" value="list">
        <?php if (isset($_GET['ret']) && is_string($_GET['ret']) && fvd_return_sanitize($_GET['ret']) !== null): ?>
        <input type="hidden" name="ret" value="<?= htmlspecialchars($_GET['ret'], ENT_QUOTES, 'UTF-8') ?>">
        <?php elseif (isset($_GET['return']) && is_string($_GET['return']) && fvd_return_sanitize($_GET['return']) !== null): ?>
        <input type="hidden" name="return" value="<?= htmlspecialchars($_GET['return'], ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <div>
            <label style="font-size:.8125rem;color:var(--fvd-muted);display:block">Buscar</label>
            <input class="fvd-input" type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nombre o lugar" style="max-width:14rem">
        </div>
        <button type="submit" class="fvd-input" style="width:auto;padding:6px 12px">Ir</button>
        <a href="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-input" style="width:auto;padding:6px 12px;display:inline-flex;align-items:center;text-decoration:none;box-sizing:border-box">Limpiar</a>
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
            <?php if ($fvd_es_fvd): ?><th>Despacho FVD</th><?php endif; ?>
            <?php if ($fvd_puede_gestion_torneo): ?><th></th><?php endif; ?>
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
                <?php if ($fvd_es_fvd):
                    $invDesp = (int) ($r['invitaciones_despachadas'] ?? 0);
                    $nEnv = (int) ($r['despacho_enviados'] ?? 0);
                    $nTotDel = (int) ($r['despacho_total_delegados'] ?? 0);
                    ?>
                <td style="white-space:nowrap">
                    <div class="fvd-despacho-cell" style="display:flex;flex-wrap:wrap;align-items:center;gap:6px 10px">
                        <?php if ($invDesp === 1): ?>
                        <span class="fvd-despacho-sent" title="Convocatoria nacional ya despachada">
                            <span aria-hidden="true" style="font-weight:700">✓</span> Lote Enviado
                        </span>
                        <span style="font-size:0.7rem;color:var(--fvd-muted)">[Enviados: <?= $nEnv ?>/<?= $nTotDel ?>]</span>
                        <span class="fvd-despacho-hover">
                            <form method="post" action="<?= htmlspecialchars($selfUrl . '?action=list', ENT_QUOTES, 'UTF-8') ?>" style="display:inline;margin:0" onsubmit="return confirm('¿Re-enviar acceso solo a delegados nuevos (sin fila en el monitor de despacho para este torneo)?');">
                                <input type="hidden" name="_action" value="lanzar_convocatoria_pendientes">
                                <input type="hidden" name="torneo_id" value="<?= (int) $r['torneo'] ?>">
                                <button type="submit" class="fvd-input" style="width:auto;padding:4px 10px;font-size:0.7rem;cursor:pointer">Re-enviar a Pendientes</button>
                            </form>
                        </span>
                        <?php else: ?>
                        <form method="post" action="<?= htmlspecialchars($selfUrl . '?action=list', ENT_QUOTES, 'UTF-8') ?>" style="display:inline;margin:0" onsubmit="return confirm('¿Enviar lote nacional de convocatoria y accesos a todos los delegados activos?');">
                            <input type="hidden" name="_action" value="lanzar_convocatoria_nacional">
                            <input type="hidden" name="torneo_id" value="<?= (int) $r['torneo'] ?>">
                            <button type="submit" class="fvd-input" style="width:auto;padding:4px 10px;font-size:0.75rem;cursor:pointer">Enviar Lote</button>
                        </form>
                        <span style="font-size:0.7rem;color:var(--fvd-muted)">[Enviados: <?= $nEnv ?>/<?= $nTotDel ?>]</span>
                        <?php endif; ?>
                    </div>
                </td>
                <?php endif; ?>
                <?php if ($fvd_puede_gestion_torneo): ?>
                <td style="white-space:nowrap;font-size:0.8125rem">
                    <a href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=form&id=' . (int) $r['torneo']), ENT_QUOTES, 'UTF-8') ?>">Ver</a>
                    &nbsp;|&nbsp;<a href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=form&id=' . (int) $r['torneo']), ENT_QUOTES, 'UTF-8') ?>">Editar</a>
                    &nbsp;|&nbsp;<a href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=delete&id=' . (int) $r['torneo']), ENT_QUOTES, 'UTF-8') ?>" onclick="return confirm('¿Eliminar torneo?');">Eliminar</a>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php if ($result['rows'] === []): ?>
            <tr><td colspan="<?= 6 + ($fvd_puede_gestion_torneo ? 2 : 0) + ($fvd_es_fvd ? 1 : 0) ?>" style="padding:12px">Sin registros.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.fvd-despacho-cell { position: relative; align-items: flex-start !important; }
.fvd-despacho-sent {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  font-size: 0.75rem;
  border-radius: 6px;
  background: #ecfdf5;
  border: 1px solid #10b981;
  color: #047857;
  font-weight: 600;
}
.fvd-despacho-hover { display: none; }
.fvd-despacho-cell:hover .fvd-despacho-hover { display: inline-flex; }
</style>
<?php
$qArg = $q !== '' ? '&q=' . rawurlencode($q) : '';
if (isset($_GET['ret']) && is_string($_GET['ret']) && fvd_return_sanitize($_GET['ret']) !== null) {
    $qArg .= '&ret=' . rawurlencode($_GET['ret']);
} elseif (isset($_GET['return']) && is_string($_GET['return']) && fvd_return_sanitize($_GET['return']) !== null) {
    $qArg .= '&return=' . rawurlencode($_GET['return']);
}
$p = (int) $result['page'];
$pages = (int) $result['pages'];
?>
<nav class="fvd-mod-pager">
    <span><?= (int) $result['total'] ?> reg. · pág. <?= $p ?>/<?= $pages ?></span>
    <?php if ($p > 1): ?><a href="<?= htmlspecialchars($selfUrl . '?page=' . ($p - 1) . $qArg, ENT_QUOTES, 'UTF-8') ?>">Anterior</a><?php endif; ?>
    <?php if ($p < $pages): ?><a href="<?= htmlspecialchars($selfUrl . '?page=' . ($p + 1) . $qArg, ENT_QUOTES, 'UTF-8') ?>">Siguiente</a><?php endif; ?>
</nav>
