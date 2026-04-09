<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';

fvd_admin_require_roles();

$legacy = FVD_PROJECT_ROOT . '/fvdmasteradmin/services/QueryHelper.php';
if (!class_exists('QueryHelper', false)) {
    require_once $legacy;
}

$format = isset($_GET['format']) ? strtolower(trim((string) $_GET['format'])) : 'html';
if ($format !== 'html' && $format !== 'csv') {
    $format = 'html';
}

$params = [];
$scope = QueryHelper::asociacionScopeSql('a.asociacion', $params);

$marcasWhere = '(
    COALESCE(a.afiliacion, 0) = 1
    OR COALESCE(a.anualidad, 0) = 1
    OR COALESCE(a.carnet, 0) = 1
    OR COALESCE(a.traspaso, 0) = 1
    OR COALESCE(a.inscripcion, 0) = 1
)';

$sql = 'SELECT a.*, s.nombre AS asociacion_nombre
    FROM atletas a
    LEFT JOIN asociaciones s ON s.id = a.asociacion
    WHERE ' . $marcasWhere . $scope . '
    ORDER BY a.id ASC
    LIMIT 15000';

$st = fvd_db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Resumen (ámbito ya aplicado en $rows)
$n = count($rows);
$sumAfi = 0;
$sumAnu = 0;
$sumCar = 0;
$sumTra = 0;
$sumIns = 0;
foreach ($rows as $rw) {
    if ((int) ($rw['afiliacion'] ?? 0) === 1) {
        ++$sumAfi;
    }
    if ((int) ($rw['anualidad'] ?? 0) === 1) {
        ++$sumAnu;
    }
    if ((int) ($rw['carnet'] ?? 0) === 1) {
        ++$sumCar;
    }
    if ((int) ($rw['traspaso'] ?? 0) === 1) {
        ++$sumTra;
    }
    if ((int) ($rw['inscripcion'] ?? 0) === 1) {
        ++$sumIns;
    }
}

$selfReport = fvd_crud_self_url('atletas/reporte_marcas_atletas.php');
$listUrl = fvd_crud_self_url('atletas') . '?action=list';

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    $fn = 'atletas_marcas_' . date('Y-m-d_His') . '.csv';
    header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $fn) . '"');
    $out = fopen('php://output', 'wb');
    if ($out === false) {
        http_response_code(500);
        echo 'Error al generar CSV.';
        exit;
    }
    fwrite($out, "\xEF\xBB\xBF");
    if ($rows !== []) {
        $keys = array_keys($rows[0]);
        fputcsv($out, $keys, ';');
        foreach ($rows as $r) {
            $line = [];
            foreach ($keys as $k) {
                $v = $r[$k] ?? null;
                $line[] = $v === null ? '' : (string) $v;
            }
            fputcsv($out, $line, ';');
        }
    } else {
        fputcsv($out, ['sin_registros'], ';');
    }
    fclose($out);
    exit;
}

$fvd_page_title = 'Atletas con marcas (ficha completa)';
require FVD_MASTER_ROOT . '/includes/layout_header.php';

$cols = $rows !== [] ? array_keys($rows[0]) : [];
?>
<div class="report-container" style="max-width:100%">
    <h1 class="fvd-atletas-title">Atletas con al menos una marca activa</h1>
    <p style="font-size:.8125rem;color:var(--fvd-muted);margin:0 0 .75rem;line-height:1.45">
        Criterio: <code>afiliacion</code>, <code>anualidad</code>, <code>carnet</code>, <code>traspaso</code> o <code>inscripcion</code> = 1.
        Se muestran <strong>todas las columnas</strong> devueltas por la consulta (incl. <code>asociacion_nombre</code>) para revisión y estadísticas por fila.
        Máximo <strong>15.000</strong> filas. Ámbito: su asociación (o todas si es administrador FVD).
    </p>
    <div class="fvd-marcas-resumen no-print" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(9rem,1fr));gap:.5rem;margin:0 0 1rem;font-size:.75rem">
        <div style="padding:.5rem .65rem;border-radius:.35rem;border:1px solid var(--fvd-border);background:var(--fvd-azul-card)">
            <strong>Filas</strong><br><span style="font-size:1.1rem"><?= (int) $n ?></span>
        </div>
        <div style="padding:.5rem .65rem;border-radius:.35rem;border:1px solid var(--fvd-border);background:var(--fvd-azul-card)">
            <strong>afiliacion=1</strong><br><?= (int) $sumAfi ?>
        </div>
        <div style="padding:.5rem .65rem;border-radius:.35rem;border:1px solid var(--fvd-border);background:var(--fvd-azul-card)">
            <strong>anualidad=1</strong><br><?= (int) $sumAnu ?>
        </div>
        <div style="padding:.5rem .65rem;border-radius:.35rem;border:1px solid var(--fvd-border);background:var(--fvd-azul-card)">
            <strong>carnet=1</strong><br><?= (int) $sumCar ?>
        </div>
        <div style="padding:.5rem .65rem;border-radius:.35rem;border:1px solid var(--fvd-border);background:var(--fvd-azul-card)">
            <strong>traspaso=1</strong><br><?= (int) $sumTra ?>
        </div>
        <div style="padding:.5rem .65rem;border-radius:.35rem;border:1px solid var(--fvd-border);background:var(--fvd-azul-card)">
            <strong>inscripcion=1</strong><br><?= (int) $sumIns ?>
        </div>
    </div>
    <p class="no-print" style="margin:0 0 1rem;display:flex;flex-wrap:wrap;gap:.5rem;align-items:center">
        <a class="fvd-input" style="width:auto;padding:6px 12px;text-decoration:none;display:inline-flex;align-items:center" href="<?= htmlspecialchars($listUrl, ENT_QUOTES, 'UTF-8') ?>">← Atletas</a>
        <a class="fvd-input" style="width:auto;padding:6px 12px;text-decoration:none;display:inline-flex;align-items:center;font-weight:600" href="<?= htmlspecialchars($selfReport . '?format=csv', ENT_QUOTES, 'UTF-8') ?>">Descargar Excel (CSV)</a>
    </p>
    <div class="fvd-mod-table-wrap" style="overflow-x:auto">
        <table class="fvd-mod-table tabla-atletas" style="font-size:.68rem;white-space:nowrap">
            <?php if ($cols !== []): ?>
            <thead>
            <tr>
                <?php foreach ($cols as $c): ?>
                    <th scope="col"><?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?></th>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <?php foreach ($cols as $c): ?>
                        <?php
                        $v = $r[$c] ?? null;
                        $cell = $v === null ? '' : (string) $v;
                        ?>
                        <td title="<?= htmlspecialchars($cell, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cell, ENT_QUOTES, 'UTF-8') ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <?php else: ?>
            <tbody><tr><td style="padding:12px">Sin registros que cumplan el criterio en su ámbito.</td></tr></tbody>
            <?php endif; ?>
        </table>
    </div>
</div>
<?php
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
