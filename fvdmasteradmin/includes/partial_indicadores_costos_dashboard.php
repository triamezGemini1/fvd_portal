<?php

declare(strict_types=1);

/**
 * Bloque UI: indicadores de servicio (atletas) y costos estimados por tarifa vigente.
 *
 * @var array $fvdIndicadoresCostos resultado de StatsService::indicadoresServicioConCostosEstimados
 * @var string $fvdIndicadoresCostosVariant 'delegado'|'full'
 * @var string|null $fvdReporteIndicadoresUrl enlace opcional al reporte detallado
 */

if (!isset($fvdIndicadoresCostos) || !is_array($fvdIndicadoresCostos)) {
    return;
}

if (!class_exists(\AuthService::class, false)) {
    require_once dirname(__DIR__) . '/services/AuthService.php';
}

$ic = $fvdIndicadoresCostos;
$variant = isset($fvdIndicadoresCostosVariant) && $fvdIndicadoresCostosVariant === 'delegado' ? 'delegado' : 'full';
$repUrl = isset($fvdReporteIndicadoresUrl) ? (string) $fvdReporteIndicadoresUrl : '';

$tarifa = $ic['tarifa'] ?? null;
$tot = $ic['totales'] ?? [];
$counts = is_array($tot['counts'] ?? null) ? $tot['counts'] : [];
$montoGrand = (float) ($tot['monto_total'] ?? 0);
$porAsoc = is_array($ic['por_asociacion'] ?? null) ? $ic['por_asociacion'] : [];

$fmtN = static function (float $v): string {
    return \function_exists('fvd_format_contable') ? fvd_format_contable($v) : number_format($v, 2, ',', '.');
};

$fechaTar = '';
if (is_array($tarifa) && isset($tarifa['fecha'])) {
    $fechaTar = substr((string) $tarifa['fecha'], 0, 10);
}

$wrapStyle = $variant === 'delegado'
    ? 'margin:0 0 1rem;padding:12px 14px;border-radius:10px;border:1px solid #e2e8f0;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,0.06);max-width:72rem;color:#0f172a'
    : 'margin:0 0 1.25rem;padding:12px 14px;border-radius:10px;border:1px solid var(--fvd-border, rgba(255,255,255,0.12));background:rgba(255,255,255,0.04);max-width:72rem';
$mutedColor = $variant === 'delegado' ? '#64748b' : 'var(--fvd-muted,#94a3b8)';
$linkColor = $variant === 'delegado' ? '#2563eb' : 'var(--fvd-amarillo,#facc15)';

?>
<section class="fvd-ic-dash" aria-label="Indicadores de servicio y costos estimados" style="<?= htmlspecialchars($wrapStyle, ENT_QUOTES, 'UTF-8') ?>">
    <div style="display:flex;flex-wrap:wrap;align-items:baseline;justify-content:space-between;gap:8px;margin:0 0 10px">
        <h2 style="margin:0;font-size:<?= $variant === 'delegado' ? '0.95rem' : '1rem' ?>">Indicadores de servicio y costos (vista <?= $variant === 'delegado' ? 'club' : 'nacional' ?>)</h2>
        <?php if ($repUrl !== ''): ?>
            <a href="<?= htmlspecialchars($repUrl, ENT_QUOTES, 'UTF-8') ?>" style="font-size:.75rem;color:<?= htmlspecialchars($linkColor, ENT_QUOTES, 'UTF-8') ?>;text-decoration:underline">Reporte detallado</a>
        <?php endif; ?>
    </div>
    <p style="margin:0 0 12px;font-size:.72rem;color:<?= htmlspecialchars($mutedColor, ENT_QUOTES, 'UTF-8') ?>;line-height:1.45">
        <strong>Anualidad:</strong> <code>anualidad=1</code> y <code>afiliacion=1</code>.
        <strong>Inscritos:</strong> <code>inscripcion=1</code> y <code>afiliacion=0</code> (no se duplica con afiliado).
        <strong>Afiliados, carnet, traspaso:</strong> renglón en <strong>1</strong>. Montos = conteo × última tarifa en <code>costos</code><?= $fechaTar !== '' ? ' (fecha ' . htmlspecialchars($fechaTar, ENT_QUOTES, 'UTF-8') . ')' : '' ?>.
        <?php if ($tarifa === null): ?><strong style="color:#f87171"> No hay filas en <code>costos</code>; solo se muestran cantidades.</strong><?php endif; ?>
    </p>

    <div style="overflow-x:auto;margin-bottom:12px">
        <table class="fvd-mod-table" style="font-size:<?= $variant === 'delegado' ? '.78rem' : '.8rem' ?>;min-width:520px">
            <thead>
            <tr>
                <th scope="col"><?= $variant === 'delegado' ? 'Resumen' : 'Ámbito' ?></th>
                <th scope="col" style="text-align:right">Atletas</th>
                <th scope="col" style="text-align:right">Afiliados</th>
                <th scope="col" style="text-align:right">Anualidad</th>
                <th scope="col" style="text-align:right">Carnets</th>
                <th scope="col" style="text-align:right">Traspaso</th>
                <th scope="col" style="text-align:right">Inscritos</th>
                <th scope="col" style="text-align:right">Total est.</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <th scope="row">General</th>
                <td style="text-align:right"><?= (int) ($counts['total_atletas'] ?? 0) ?></td>
                <td style="text-align:right"><?= (int) ($counts['afiliacion'] ?? 0) ?></td>
                <td style="text-align:right"><?= (int) ($counts['anualidad'] ?? 0) ?></td>
                <td style="text-align:right"><?= (int) ($counts['carnet'] ?? 0) ?></td>
                <td style="text-align:right"><?= (int) ($counts['traspaso'] ?? 0) ?></td>
                <td style="text-align:right"><?= (int) ($counts['inscripcion'] ?? 0) ?></td>
                <td style="text-align:right;font-weight:600"><?= $fmtN($montoGrand) ?></td>
            </tr>
            </tbody>
        </table>
    </div>

    <?php if ($porAsoc !== [] && \AuthService::isSuperAdmin()): ?>
    <h3 style="margin:0 0 8px;font-size:.85rem">Por asociación</h3>
    <div style="overflow-x:auto;max-height:min(48vh,520px);overflow-y:auto">
        <table class="fvd-mod-table" style="font-size:<?= $variant === 'delegado' ? '.75rem' : '.78rem' ?>">
            <thead>
            <tr>
                <th scope="col">ID</th>
                <th scope="col">Asociación</th>
                <th scope="col" style="text-align:right">Atletas</th>
                <th scope="col" style="text-align:right">Afil.</th>
                <th scope="col" style="text-align:right">Anual.</th>
                <th scope="col" style="text-align:right">Carn.</th>
                <th scope="col" style="text-align:right">Trasp.</th>
                <th scope="col" style="text-align:right">Insc.</th>
                <th scope="col" style="text-align:right">Total est.</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($porAsoc as $pa): ?>
                <?php $mt = (float) ($pa['monto_total'] ?? 0); ?>
                <tr>
                    <td style="white-space:nowrap"><?= (int) ($pa['asociacion_id'] ?? 0) ?></td>
                    <td><?= htmlspecialchars((string) ($pa['asociacion_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td style="text-align:right"><?= (int) ($pa['total_atletas'] ?? 0) ?></td>
                    <td style="text-align:right"><?= (int) ($pa['afiliacion'] ?? 0) ?></td>
                    <td style="text-align:right"><?= (int) ($pa['anualidad'] ?? 0) ?></td>
                    <td style="text-align:right"><?= (int) ($pa['carnet'] ?? 0) ?></td>
                    <td style="text-align:right"><?= (int) ($pa['traspaso'] ?? 0) ?></td>
                    <td style="text-align:right"><?= (int) ($pa['inscripcion'] ?? 0) ?></td>
                    <td style="text-align:right;font-weight:600"><?= $fmtN($mt) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
