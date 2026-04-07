<?php
/** @var array{total:int,page:int,per_page:int,pages:int,rows:list<array<string,mixed>>} $result */
/** @var list<array<string,mixed>> $torneosF */
/** @var string $selfUrl */
/** @var int $filterT */
/** @var bool $vistaBanderaDelegado */
/** @var string $fvd_error */
$vistaBanderaDelegado = !empty($vistaBanderaDelegado);
$fvd_error = isset($fvd_error) ? (string) $fvd_error : '';
?>
<h1><?= $vistaBanderaDelegado ? 'Administrador de inscripciones' : 'Inscripciones (tabla <code>inscripcion_torneo</code>)' ?></h1>
<?php if ($fvd_error !== ''): ?>
    <p class="fvd-mod-msg"><?= htmlspecialchars($fvd_error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($vistaBanderaDelegado): ?>
    <p class="fvd-dash__intro">Solo atletas con <code>inscripcion=1</code> y <code>torneo_id</code> del filtro. Retirar pone <code>inscripcion=0</code> y <code>torneo_id=0</code>. No se usa la tabla <code>inscripcion_torneo</code>.</p>
<?php else: ?>
    <p class="fvd-dash__intro">Registros por torneo y asociación, según el modelo del volcado de base de datos. Complementa el flujo que actualiza <code>atletas.torneo_id</code>.</p>
<?php endif; ?>

<form method="get" action="" class="fvd-card" style="margin-bottom:1rem;display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
    <div>
        <label for="torneo_id">Filtrar por torneo</label>
        <select class="fvd-input" id="torneo_id" name="torneo_id" style="max-width:22rem">
            <option value="0">— Todos (en su ámbito) —</option>
            <?php foreach ($torneosF as $t): ?>
                <option value="<?= (int) ($t['id'] ?? 0) ?>" <?= $filterT === (int) ($t['id'] ?? 0) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) ($t['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="fvd-input" style="width:auto;max-width:8rem;cursor:pointer;">Aplicar</button>
    <a href="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-input" style="width:auto;padding:6px 12px;display:inline-flex;align-items:center;text-decoration:none;box-sizing:border-box;cursor:pointer">Limpiar</a>
</form>

<?php if ($result['rows'] === []): ?>
    <p class="fvd-muted">No hay inscripciones en esta vista.</p>
<?php elseif ($vistaBanderaDelegado): ?>
    <div class="fvd-card" style="overflow-x:auto;">
        <table class="fvd-table">
            <thead>
            <tr>
                <th>ID atleta</th>
                <th>Torneo</th>
                <th>Cédula</th>
                <th>Nombre</th>
                <th>Nº FVD</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($result['rows'] as $r): ?>
                <tr>
                    <td><?= (int) ($r['id'] ?? 0) ?></td>
                    <td><?= htmlspecialchars((string) ($r['torneo_nombre'] ?? $r['torneo_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($r['cedula'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($r['numfvd'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <form method="post" action="" style="display:inline" onsubmit="return confirm('¿Retirar inscripción?');">
                            <input type="hidden" name="_action" value="retirar_bandera">
                            <input type="hidden" name="torneo_id" value="<?= (int) ($r['torneo_id'] ?? 0) ?>">
                            <input type="hidden" name="atleta_id" value="<?= (int) ($r['id'] ?? 0) ?>">
                            <button type="submit" class="fvd-input" style="padding:4px 10px;font-size:0.75rem;cursor:pointer">Retirar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="fvd-card" style="overflow-x:auto;">
        <table class="fvd-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Torneo</th>
                <th>Asociación</th>
                <th>Cédula</th>
                <th>Nombre</th>
                <th>Nº FVD</th>
                <th>Equipo</th>
                <th>Fecha</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($result['rows'] as $r): ?>
                <tr>
                    <td><?= (int) ($r['id'] ?? 0) ?></td>
                    <td><?= htmlspecialchars((string) ($r['torneo_nombre'] ?? $r['torneo_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($r['asoc_nombre'] ?? $r['asociacion_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($r['cedula'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($r['numfvd'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($r['equipo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($r['fecha_inscripcion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    $base = $selfUrl . ($filterT > 0 ? '?torneo_id=' . $filterT . '&' : '?');
    if ($result['pages'] > 1): ?>
        <nav class="fvd-actions" style="margin-top:1rem;">
            <?php if ($result['page'] > 1): ?>
                <a href="<?= htmlspecialchars($base . 'p=' . ($result['page'] - 1), ENT_QUOTES, 'UTF-8') ?>">← Anterior</a>
            <?php endif; ?>
            <span class="fvd-muted">Página <?= (int) $result['page'] ?> / <?= (int) $result['pages'] ?> (<?= (int) $result['total'] ?> registros)</span>
            <?php if ($result['page'] < $result['pages']): ?>
                <a href="<?= htmlspecialchars($base . 'p=' . ($result['page'] + 1), ENT_QUOTES, 'UTF-8') ?>">Siguiente →</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<p class="fvd-actions" style="margin-top:1.5rem;">
    <a href="<?= htmlspecialchars(fvd_module_url('inscripciones/index.php'), ENT_QUOTES, 'UTF-8') ?>">Módulo Inscripciones (legado)</a>
</p>
