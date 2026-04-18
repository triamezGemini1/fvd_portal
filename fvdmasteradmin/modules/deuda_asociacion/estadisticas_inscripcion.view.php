<?php
/** @var string $selfUrl */
/** @var list<array<string, mixed>> $fvdTorneosSelect */
/** @var int $tidStats */
/** @var array{tabla_ok:bool, rows:list<array<string, mixed>>, fuente?:string} $fvdEstadisticasInscripcion */
/** @var string $fvdTorneoNombreStats */
$fvdTorneosSelect = $fvdTorneosSelect ?? [];
$tidStats = $tidStats ?? 0;
$fvdEstadisticasInscripcion = $fvdEstadisticasInscripcion ?? ['tabla_ok' => false, 'rows' => []];
$fvdTorneoNombreStats = $fvdTorneoNombreStats ?? '';
$fvd_admin = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
?>

<h1>Estadísticas por torneo — <?= htmlspecialchars(($fvdEstadisticasInscripcion['fuente'] ?? '') === 'inscripcion_torneo' ? 'inscripcion_torneo' : 'atletas', ENT_QUOTES, 'UTF-8') ?></h1>
<p class="muted" style="font-size:0.875rem;line-height:1.5;margin-bottom:16px">
    <?php if (($fvdEstadisticasInscripcion['fuente'] ?? '') === 'inscripcion_torneo'): ?>
        Filas del torneo en <code>inscripcion_torneo</code>. <code>inscripcion</code> en 1 o 2 cuenta como inscrito al evento (sitio o movimiento).
        <strong>Inscripción</strong> y <strong>anualidad</strong> muestran el mismo conteo de filas inscritas; <strong>afiliación</strong>, <strong>carnet</strong> y <strong>traspaso</strong> son independientes por columna.
    <?php else: ?>
        Fichas con <code>torneo_id</code> igual al torneo elegido (legado). Conteos con banderas en <code>atletas</code>.
        <strong>Inscripción</strong> y <strong>anualidad</strong> muestran el mismo número: inscritos en el torneo.
    <?php endif; ?>
    <strong>No</strong> se calculan montos ni deudas aquí. Fuera de las ventanas de calendario del torneo, delegados y asociaciones solo pueden <strong>consultar</strong> esta información.
</p>
<?php if (!$fvd_admin): ?>
    <p class="fvd-mod-msg" style="font-size:0.8125rem">Como delegado solo ve las filas de <strong>su asociación</strong>.</p>
<?php endif; ?>

<form method="get" action="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" style="margin-bottom:20px;display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
    <input type="hidden" name="action" value="estadisticas_inscripcion">
    <?php if (isset($_GET['ret']) && is_string($_GET['ret']) && fvd_return_sanitize($_GET['ret']) !== null): ?>
    <input type="hidden" name="ret" value="<?= htmlspecialchars($_GET['ret'], ENT_QUOTES, 'UTF-8') ?>">
    <?php elseif (isset($_GET['return']) && is_string($_GET['return']) && fvd_return_sanitize($_GET['return']) !== null): ?>
    <input type="hidden" name="return" value="<?= htmlspecialchars($_GET['return'], ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <label style="display:flex;flex-direction:column;gap:4px;font-size:0.875rem">
        <span>Torneo</span>
        <select name="tid" required style="min-width:220px;padding:6px 8px">
            <option value="">— Elija torneo —</option>
            <?php foreach ($fvdTorneosSelect as $t): ?>
                <?php $tidOpt = (int) ($t['id'] ?? 0); ?>
                <option value="<?= $tidOpt ?>" <?= $tidOpt === $tidStats ? ' selected' : '' ?>><?= htmlspecialchars((string) ($t['nombre'] ?? $tidOpt), ENT_QUOTES, 'UTF-8') ?> (<?= $tidOpt ?>)</option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit" class="fvd-btn fvd-btn--primary" style="padding:8px 16px">Ver estadísticas</button>
    <a class="fvd-btn fvd-btn--ghost" style="padding:8px 16px;text-decoration:none;display:inline-block" href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl), ENT_QUOTES, 'UTF-8') ?>">Volver a deudas</a>
</form>

<?php if ($tidStats > 0): ?>
    <h2 style="font-size:1rem;margin:0 0 10px"><?= htmlspecialchars($fvdTorneoNombreStats !== '' ? $fvdTorneoNombreStats : 'Torneo ' . $tidStats, ENT_QUOTES, 'UTF-8') ?></h2>
    <?php if (!$fvdEstadisticasInscripcion['tabla_ok']): ?>
        <p class="fvd-mod-msg">No está disponible la tabla <code>atletas</code> en esta base.</p>
    <?php elseif (($fvdEstadisticasInscripcion['rows'] ?? []) === []): ?>
        <p class="fvd-mod-msg">No hay datos para este torneo<?= $fvd_admin ? '' : ' en su asociación' ?>
            <?= ($fvdEstadisticasInscripcion['fuente'] ?? '') === 'inscripcion_torneo' ? '(tabla <code>inscripcion_torneo</code>)' : '(<code>atletas.torneo_id</code>)' ?>.</p>
    <?php else: ?>
        <div class="fvd-mod-table-wrap">
            <table class="fvd-mod-table fvd-mod-table--nowrap">
                <thead>
                <tr>
                    <th>Asociación</th>
                    <th class="num">Fichas (torneo)</th>
                    <th class="num">Inscripción</th>
                    <th class="num">Afiliación</th>
                    <th class="num">Anualidad</th>
                    <th class="num">Carnet</th>
                    <th class="num">Traspaso</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($fvdEstadisticasInscripcion['rows'] as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($r['asoc_nombre'] ?? $r['asociacion_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="num"><?= (int) ($r['filas_origen'] ?? 0) ?></td>
                        <td class="num"><?= (int) ($r['total_inscritos'] ?? 0) ?></td>
                        <td class="num"><?= (int) ($r['total_afiliados'] ?? 0) ?></td>
                        <td class="num"><?= (int) ($r['total_anualidad'] ?? 0) ?></td>
                        <td class="num"><?= (int) ($r['total_carnets'] ?? 0) ?></td>
                        <td class="num"><?= (int) ($r['total_traspasos'] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>
