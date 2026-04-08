<?php
/** @var string $selfUrl */
/** @var list<array<string, mixed>> $fvdTorneosSelect */
/** @var int $tidStats */
/** @var array{tabla_ok:bool, rows:list<array<string, mixed>>} $fvdEstadisticasInscripcion */
/** @var string $fvdTorneoNombreStats */
$fvdTorneosSelect = $fvdTorneosSelect ?? [];
$tidStats = $tidStats ?? 0;
$fvdEstadisticasInscripcion = $fvdEstadisticasInscripcion ?? ['tabla_ok' => false, 'rows' => []];
$fvdTorneoNombreStats = $fvdTorneoNombreStats ?? '';
$fvd_admin = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
?>

<h1>Estadísticas por origen — <code>inscripcion_torneo</code></h1>
<p class="muted" style="font-size:0.875rem;line-height:1.5;margin-bottom:16px">
    Aquí el <strong>origen</strong> es la tabla de inscripciones al torneo (<code>inscripcion_torneo</code>), equivalente al criterio de la tabla de inscripciones del sistema legado.
    <strong>No</strong> se calculan montos ni deudas: solo conteos por renglón (concepto) y por asociación.
    La ficha en <code>atletas</code> es el <strong>destino</strong> del portal; para comparar con estos totales use el detalle de deuda o el listado de atletas.
</p>
<?php if (!$fvd_admin): ?>
    <p class="fvd-mod-msg" style="font-size:0.8125rem">Como delegado solo ve las filas de <strong>su asociación</strong>.</p>
<?php endif; ?>

<form method="get" action="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" style="margin-bottom:20px;display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
    <input type="hidden" name="action" value="estadisticas_inscripcion">
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
    <a class="fvd-btn fvd-btn--ghost" style="padding:8px 16px;text-decoration:none;display:inline-block" href="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>">Volver a deudas</a>
</form>

<?php if ($tidStats > 0): ?>
    <h2 style="font-size:1rem;margin:0 0 10px"><?= htmlspecialchars($fvdTorneoNombreStats !== '' ? $fvdTorneoNombreStats : 'Torneo ' . $tidStats, ENT_QUOTES, 'UTF-8') ?></h2>
    <?php if (!$fvdEstadisticasInscripcion['tabla_ok']): ?>
        <p class="fvd-mod-msg">La tabla <code>inscripcion_torneo</code> no existe en esta base. Ejecute <code>fvdmasteradmin/sql/install_inscripcion_torneo.sql</code>.</p>
    <?php elseif (($fvdEstadisticasInscripcion['rows'] ?? []) === []): ?>
        <p class="fvd-mod-msg">No hay filas en <code>inscripcion_torneo</code> para este torneo<?= $fvd_admin ? '' : ' y su asociación' ?>.</p>
    <?php else: ?>
        <div class="fvd-mod-table-wrap">
            <table class="fvd-mod-table fvd-mod-table--nowrap">
                <thead>
                <tr>
                    <th>Asociación</th>
                    <th class="num">Filas origen</th>
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
