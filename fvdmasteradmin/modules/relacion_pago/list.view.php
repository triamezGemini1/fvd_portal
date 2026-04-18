<?php
/** @var array $result */
/** @var string $selfUrl */
/** @var array<string, int|string> $fvdRpPreservar */
$fvdRpPreservar = isset($fvdRpPreservar) && is_array($fvdRpPreservar) ? $fvdRpPreservar : [];
$fvdReporteOrigenUrl = isset($fvdReporteOrigenUrl) ? $fvdReporteOrigenUrl : null;
$fvdRpPanelUrl = isset($fvdRpPanelUrl) ? (string) $fvdRpPanelUrl : '';
$fvdRpQuitarFiltroAidUrl = isset($fvdRpQuitarFiltroAidUrl) ? (string) $fvdRpQuitarFiltroAidUrl : $selfUrl;

$fvdFmtNum = static function ($value, int $decimals): string {
    if ($value === '' || $value === null) {
        return '';
    }

    return number_format((float) $value, $decimals, ',', '.');
};
?>

<h1>Pagos registrados</h1>
<?php
$fvdFiltroAsociacionId = isset($fvdFiltroAsociacionId) ? (int) $fvdFiltroAsociacionId : 0;
?>
<div class="no-print" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0 0 0.75rem">
    <?php if ($fvdReporteOrigenUrl !== null && $fvdReporteOrigenUrl !== ''): ?>
        <a class="fvd-input" style="width:auto;padding:6px 12px;text-decoration:none;display:inline-flex;align-items:center;box-sizing:border-box;font-size:.8rem" href="<?= htmlspecialchars($fvdReporteOrigenUrl, ENT_QUOTES, 'UTF-8') ?>">← Reporte financiero (origen)</a>
    <?php endif; ?>
    <?php if ($fvdRpPanelUrl !== ''): ?>
        <a class="fvd-input" style="width:auto;padding:6px 12px;text-decoration:none;display:inline-flex;align-items:center;box-sizing:border-box;font-size:.8rem" href="<?= htmlspecialchars($fvdRpPanelUrl, ENT_QUOTES, 'UTF-8') ?>">← Panel general</a>
    <?php endif; ?>
</div>
<?php if ($fvdFiltroAsociacionId > 0): ?>
    <p class="fvd-mod-msg" style="margin:0 0 0.75rem;font-size:0.82rem;background:rgba(37,99,235,.12);border-color:#2563eb;color:#1e3a8a">
        Listado acotado a la asociación activa en consulta: solo pagos con <code>asociacion_id</code> = <strong><?= (int) $fvdFiltroAsociacionId ?></strong>.
        <a href="<?= htmlspecialchars($fvdRpQuitarFiltroAidUrl, ENT_QUOTES, 'UTF-8') ?>" style="color:#1d4ed8;text-decoration:underline">Quitar filtro de asociación</a>
    </p>
<?php endif; ?>
<p class="fvd-mod-msg" style="color:#334155;font-size:0.9rem;margin:0 0 0.75rem;max-width:52rem">
    Los importes en <strong>EUR</strong> son los contables frente a la deuda. Los <strong>Bs</strong> y la <strong>tasa BCV</strong> quedan guardados como referencia del cambio del día, para verificación y arqueo de caja.
</p>
<?php if (!empty($fvd_error)): ?><p class="fvd-mod-msg"><?= htmlspecialchars($fvd_error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if (isset($_GET['msg']) && $_GET['msg'] === 'no_eliminar'): ?>
    <p class="fvd-mod-msg" role="status">No está permitido eliminar pagos; solo consulta o registrar uno nuevo.</p>
<?php endif; ?>

<div class="fvd-mod-toolbar">
    <?php
    $qNuevo = array_merge(['action' => 'form'], $fvdRpPreservar);
    if ($fvdFiltroAsociacionId > 0) {
        $qNuevo['asociacion_id'] = $fvdFiltroAsociacionId;
    }
    $urlNuevoPago = $selfUrl . '?' . http_build_query($qNuevo);
    ?>
    <a href="<?= htmlspecialchars($urlNuevoPago, ENT_QUOTES, 'UTF-8') ?>" class="fvd-btn-primary">Registrar pago</a>
</div>

<div class="fvd-mod-table-wrap">
    <table class="fvd-mod-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>Asociación</th>
            <th>Torneo</th>
            <th>Pago</th>
            <th>Tasa BCV</th>
            <th>Bs (ref.)</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($result['rows'] as $r): ?>
            <tr>
                <td><?= (int) $r['id'] ?></td>
                <td><?= htmlspecialchars((string) ($r['fecha'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['asoc_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($r['torneo_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($fvdFmtNum($r['monto_dolares'] ?? null, 2), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($fvdFmtNum($r['tasa_cambio'] ?? null, 2), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($fvdFmtNum($r['monto_total'] ?? null, 2), ENT_QUOTES, 'UTF-8') ?></td>
                <td style="white-space:nowrap">
                    <?php
                    $qVer = array_merge(['action' => 'form', 'id' => (int) $r['id']], $fvdRpPreservar);
                    $urlVer = $selfUrl . '?' . http_build_query($qVer);
                    ?>
                    <a href="<?= htmlspecialchars($urlVer, ENT_QUOTES, 'UTF-8') ?>">Ver</a>
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
$qsPager = function (int $pageNum) use ($selfUrl, $fvdRpPreservar): string {
    $q = array_merge($fvdRpPreservar, ['page' => $pageNum]);

    return $selfUrl . '?' . http_build_query($q);
};
?>
<nav class="fvd-mod-pager">
    <span><?= (int) $result['total'] ?> reg. · pág. <?= $p ?>/<?= $pages ?></span>
    <?php if ($p > 1): ?><a href="<?= htmlspecialchars($qsPager($p - 1), ENT_QUOTES, 'UTF-8') ?>">Anterior</a><?php endif; ?>
    <?php if ($p < $pages): ?><a href="<?= htmlspecialchars($qsPager($p + 1), ENT_QUOTES, 'UTF-8') ?>">Siguiente</a><?php endif; ?>
</nav>
