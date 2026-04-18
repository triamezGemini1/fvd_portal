<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';
require_once FVD_PROJECT_ROOT . '/src/Services/QueryHelper.php';
require_once FVD_PROJECT_ROOT . '/src/Services/IndicadoresTablaDefs.php';

use FvdPortal\Services\IndicadoresTablaDefs;
use FvdPortal\Services\QueryHelper;

fvd_admin_require_roles();

$modo = isset($_GET['modo']) ? trim((string) $_GET['modo']) : 'cualquiera';
if ($modo !== 'todos' && $modo !== 'cualquiera') {
    $modo = 'cualquiera';
}

$cedula = isset($_GET['cedula']) ? trim((string) $_GET['cedula']) : '';
$nombre = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

$rows = QueryHelper::selectAtletasPorIndicadoresServicioFull($modo, $cedula, $nombre, fvd_db());

$totalesAlcance = QueryHelper::aggregateIndicadoresAtletasTotales(fvd_db());
$porAsociacion = QueryHelper::aggregateIndicadoresAtletasPorAsociacion(fvd_db());

$stats = [
    'total'       => count($rows),
    'afiliacion'  => 0,
    'anualidad'   => 0,
    'carnet'      => 0,
    'traspaso'    => 0,
    'inscripcion' => 0,
];
foreach ($rows as $r) {
    foreach (['afiliacion', 'anualidad', 'carnet', 'traspaso', 'inscripcion'] as $k) {
        if ((int) ($r[$k] ?? 0) === 1) {
            ++$stats[$k];
        }
    }
}

$format = isset($_GET['format']) ? strtolower(trim((string) $_GET['format'])) : '';
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="atletas_indicadores_' . date('Y-m-d_His') . '.csv"');
    header('X-Content-Type-Options: nosniff');
    $out = fopen('php://output', 'wb');
    if ($out === false) {
        http_response_code(500);
        echo 'Error al generar CSV.';
        exit;
    }
    fwrite($out, "\xEF\xBB\xBF");
    if ($rows !== []) {
        $cols = array_keys($rows[0]);
        fputcsv($out, $cols, ';');
        foreach ($rows as $r) {
            $line = [];
            foreach ($cols as $c) {
                $v = $r[$c] ?? null;
                if ($v === null) {
                    $line[] = '';
                } elseif (is_scalar($v) || $v instanceof \Stringable) {
                    $line[] = (string) $v;
                } else {
                    $line[] = '';
                }
            }
            fputcsv($out, $line, ';');
        }
    } else {
        fputcsv($out, ['sin_registros'], ';');
    }
    fclose($out);
    exit;
}

$fvd_page_title = 'Indicadores de servicio (atletas)';
$selfReport = admin_module_url('atletas/reporte_indicadores.php');
$atletasUrl = fvd_crud_self_url('atletas');

$columnas = $rows !== [] ? array_keys($rows[0]) : [];

$fvdIndCols = IndicadoresTablaDefs::columnasMetricas();
$totalesVals = IndicadoresTablaDefs::valoresMetricasInt($totalesAlcance, 'totales alcance');

require FVD_MASTER_ROOT . '/includes/layout_header.php';
?>
<div class="report-container fvd-rep-indicadores" style="max-width:100%">
    <h1 class="fvd-atletas-title">Atletas — indicadores de servicio (datos completos)</h1>
    <p style="font-size:.8125rem;color:var(--fvd-muted);margin:0 0 .75rem;line-height:1.45">
        Se listan filas de <code>atletas</code> con los campos <strong>afiliación, anualidad, carnet, traspaso e inscripción</strong> según el modo elegido.
        <strong>Cualquiera</strong>: al menos un indicador en 1. <strong>Todos</strong>: los cinco en 1.
        El alcance es el de su sesión (asociación o FVD completo).
    </p>

    <section class="fvd-rep-indicadores__alcance" aria-label="Cuantificación total en su alcance" style="margin:0 0 1.25rem;padding:12px;border-radius:8px;border:1px solid var(--fvd-border);background:rgba(255,255,255,0.04)">
        <h2 style="margin:0 0 .5rem;font-size:.95rem">Totales generales (tabla <code>atletas</code>, su alcance)</h2>
        <p style="margin:0 0 .75rem;font-size:.72rem;color:var(--fvd-muted);line-height:1.45">
            Cada columna cuenta por separado las filas con ese campo en <strong>1</strong>, sin condiciones cruzadas; un mismo atleta puede sumar en varios indicadores.
            La unidad regional en datos es <strong>asociación</strong> (<code>atletas.asociacion</code>); no existe columna <code>club</code> en esta tabla.
        </p>
        <div style="overflow-x:auto">
            <table class="fvd-mod-table" style="font-size:.8rem;min-width:520px">
                <thead>
                <tr>
                    <th scope="col">Ámbito</th>
                    <?php foreach ($fvdIndCols as $col): ?>
                    <th scope="col" style="text-align:right" title="<?= htmlspecialchars($col['title'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($col['label'], ENT_QUOTES, 'UTF-8') ?></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <th scope="row">General</th>
                    <?php foreach ($fvdIndCols as $col): ?>
                    <td style="text-align:right"><?= (int) ($totalesVals[$col['key']] ?? 0) ?></td>
                    <?php endforeach; ?>
                </tr>
                </tbody>
            </table>
        </div>
        <?php if ($porAsociacion !== []): ?>
        <h3 style="margin:1rem 0 .5rem;font-size:.85rem">Por asociación</h3>
        <div style="overflow-x:auto;max-height:min(50vh,560px);overflow-y:auto">
            <table class="fvd-mod-table" style="font-size:.8rem">
                <thead>
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Asociación</th>
                    <?php foreach ($fvdIndCols as $col): ?>
                    <th scope="col" style="text-align:right" title="<?= htmlspecialchars($col['title'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($col['label'], ENT_QUOTES, 'UTF-8') ?></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($porAsociacion as $pa): ?>
                <?php
                    $aid = (int) ($pa['asociacion_id'] ?? 0);
                    $paVals = IndicadoresTablaDefs::valoresMetricasInt($pa, 'por asociación id=' . $aid);
                ?>
                <tr>
                    <td style="white-space:nowrap"><?= $aid ?></td>
                    <td><?= htmlspecialchars((string) ($pa['asociacion_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <?php foreach ($fvdIndCols as $col): ?>
                    <td style="text-align:right"><?= (int) ($paVals[$col['key']] ?? 0) ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <form method="get" action="" class="no-print" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin:0 0 1rem">
        <div>
            <label style="font-size:.75rem;color:var(--fvd-muted);display:block">Modo</label>
            <select name="modo" class="fvd-input" style="min-width:12rem">
                <option value="cualquiera"<?= $modo === 'cualquiera' ? ' selected' : '' ?>>Al menos un indicador = 1</option>
                <option value="todos"<?= $modo === 'todos' ? ' selected' : '' ?>>Los cinco indicadores = 1</option>
            </select>
        </div>
        <div>
            <label style="font-size:.75rem;color:var(--fvd-muted);display:block">Cédula (opc.)</label>
            <input class="fvd-input" type="search" name="cedula" value="<?= htmlspecialchars($cedula, ENT_QUOTES, 'UTF-8') ?>" placeholder="Filtrar…" style="max-width:11rem">
        </div>
        <div>
            <label style="font-size:.75rem;color:var(--fvd-muted);display:block">Nombre (opc.)</label>
            <input class="fvd-input" type="search" name="q" value="<?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?>" placeholder="Contiene…" style="max-width:12rem">
        </div>
        <button type="submit" class="fvd-input" style="width:auto;padding:6px 12px">Aplicar</button>
        <a class="fvd-input" style="width:auto;padding:6px 12px;text-decoration:none;display:inline-flex;align-items:center;box-sizing:border-box" href="<?= htmlspecialchars($selfReport, ENT_QUOTES, 'UTF-8') ?>">Restablecer</a>
        <a class="fvd-input" style="width:auto;padding:6px 12px;text-decoration:none;display:inline-flex;align-items:center;box-sizing:border-box;font-weight:600" href="<?= htmlspecialchars($selfReport . '?modo=' . rawurlencode($modo) . ($cedula !== '' ? '&cedula=' . rawurlencode($cedula) : '') . ($nombre !== '' ? '&q=' . rawurlencode($nombre) : '') . '&format=csv', ENT_QUOTES, 'UTF-8') ?>">Descargar CSV</a>
        <a class="fvd-input" style="width:auto;padding:6px 12px;text-decoration:none;display:inline-flex;align-items:center;box-sizing:border-box" href="<?= htmlspecialchars($atletasUrl . '?action=list', ENT_QUOTES, 'UTF-8') ?>">← Listado atletas</a>
    </form>

    <section class="fvd-rep-indicadores__stats" aria-label="Resumen por indicador" style="margin:0 0 1rem;padding:12px;border-radius:8px;border:1px solid var(--fvd-border);background:rgba(255,255,255,0.04)">
        <h2 style="margin:0 0 .5rem;font-size:.9rem">Estadísticas (sobre el conjunto filtrado)</h2>
        <p style="margin:0;font-size:.8125rem;line-height:1.6">
            <strong>Registros:</strong> <?= (int) $stats['total'] ?> &nbsp;|&nbsp;
            <span title="afiliacion=1"><strong>Afil.</strong> <?= (int) $stats['afiliacion'] ?></span> &nbsp;
            <span title="anualidad=1"><strong>Anual.</strong> <?= (int) $stats['anualidad'] ?></span> &nbsp;
            <span title="carnet=1"><strong>Carnet</strong> <?= (int) $stats['carnet'] ?></span> &nbsp;
            <span title="traspaso=1"><strong>Trasp.</strong> <?= (int) $stats['traspaso'] ?></span> &nbsp;
            <span title="inscripcion=1"><strong>Insc.</strong> <?= (int) $stats['inscripcion'] ?></span>
        </p>
        <p style="margin:.5rem 0 0;font-size:.72rem;color:var(--fvd-muted)">Los conteos por columna cuentan cuántas filas tienen ese marcador en 1 (un mismo atleta puede sumar en varios).</p>
    </section>

    <div class="fvd-mod-table-wrap" style="overflow-x:auto;max-height:min(70vh,900px);overflow-y:auto">
        <table class="fvd-mod-table tabla-atletas" style="font-size:.72rem">
            <thead>
            <tr>
                <?php foreach ($columnas as $col): ?>
                    <th scope="col" style="white-space:nowrap;position:sticky;top:0;background:var(--fvd-azul-card,#1e293b);z-index:1"><?= htmlspecialchars($col, ENT_QUOTES, 'UTF-8') ?></th>
                <?php endforeach; ?>
                <th scope="col" class="no-print" style="position:sticky;top:0;background:var(--fvd-azul-card,#1e293b);z-index:1">Acción</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <?php foreach ($columnas as $col): ?>
                        <?php
                        $cell = $r[$col] ?? null;
                        $disp = $cell === null || $cell === '' ? '—' : (is_scalar($cell) || $cell instanceof \Stringable ? (string) $cell : '');
                        ?>
                        <td style="max-width:14rem;overflow:hidden;text-overflow:ellipsis" title="<?= htmlspecialchars($disp, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($disp, ENT_QUOTES, 'UTF-8') ?></td>
                    <?php endforeach; ?>
                    <td class="no-print" style="white-space:nowrap">
                        <a href="<?= htmlspecialchars($atletasUrl . '?action=form&id=' . (int) ($r['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Editar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="<?= max(1, count($columnas) + 1) ?>" style="padding:12px">Sin registros con este criterio.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
