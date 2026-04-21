<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';
require_once FVD_PROJECT_ROOT . '/src/Services/QueryHelper.php';
require_once FVD_PROJECT_ROOT . '/src/Services/IndicadoresTablaDefs.php';

use FvdPortal\Services\IndicadoresTablaDefs;
use FvdPortal\Services\QueryHelper;

fvd_admin_require_roles();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'reset_marcador_atletas') {
    $rolSesion = trim((string) (AuthService::role() ?? ''));
    if ($rolSesion !== AuthService::ROLE_FVD_ADMIN) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Sin permiso para reiniciar marcadores.';
        exit;
    }
    $retPost = isset($_POST['ret']) && is_string($_POST['ret']) ? fvd_return_sanitize($_POST['ret']) : null;
    $qsRet = $retPost !== null ? '&ret=' . rawurlencode($retPost) : '';
    $campo = trim((string) ($_POST['marcador'] ?? ''));
    $svc = new FvdAdminService();
    try {
        $n = $svc->atletasResetMarcadorMasivo($campo);
        header('Location: ' . fvd_atletas_reporte_indicadores_self_url() . '?msg=reset_ok&n=' . (int) $n . '&campo=' . rawurlencode($campo) . $qsRet);
    } catch (Throwable $e) {
        error_log('[reporte_indicadores reset] ' . $e->getMessage());
        header('Location: ' . fvd_atletas_reporte_indicadores_self_url() . '?msg=reset_err' . $qsRet);
    }
    exit;
}

$modo = isset($_GET['modo']) ? trim((string) $_GET['modo']) : 'cualquiera';
if ($modo !== 'todos' && $modo !== 'cualquiera') {
    $modo = 'cualquiera';
}

$cedula = isset($_GET['cedula']) ? trim((string) $_GET['cedula']) : '';
$nombre = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

$marcadoresInforme = ['afiliacion', 'anualidad', 'carnet', 'traspaso', 'inscripcion', 'afiliacion_anualidad'];
$marcadorGet = isset($_GET['marcador']) ? trim((string) $_GET['marcador']) : '';
$marcadorFijo = $marcadorGet !== '' && in_array($marcadorGet, $marcadoresInforme, true) ? $marcadorGet : null;

$rows = QueryHelper::selectAtletasPorIndicadoresServicioFull($modo, $cedula, $nombre, fvd_db(), $marcadorFijo);

if ($marcadorFijo === null) {
    $totalesAlcance = QueryHelper::aggregateIndicadoresAtletasTotales(fvd_db());
    $porAsociacion = QueryHelper::aggregateIndicadoresAtletasPorAsociacion(fvd_db());
} else {
    $totalesAlcance = [];
    $porAsociacion = [];
}

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

$titulosPorMarcador = [
    'afiliacion'           => 'Afiliación (atletas.afiliacion = 1)',
    'anualidad'            => 'Anualidad (atletas.anualidad = 1)',
    'carnet'               => 'Carnet solicitado (atletas.carnet = 1)',
    'traspaso'             => 'Traspaso marcado (atletas.traspaso = 1)',
    'inscripcion'          => 'Inscripción (atletas.inscripcion = 1)',
    'afiliacion_anualidad' => 'Afiliación y anualidad (afiliacion = 1 y anualidad = 1)',
];
$fvd_page_title = $marcadorFijo !== null
    ? ($titulosPorMarcador[$marcadorFijo] ?? 'Indicadores (atletas)')
    : 'Indicadores de servicio (atletas)';
$selfReport = fvd_atletas_reporte_indicadores_self_url();
$atletasUrl = fvd_crud_self_url('atletas');
$retOrigen = fvd_return_from_request();
$atletasBackUrl = $retOrigen !== null ? $retOrigen : ($atletasUrl . '?action=list');
$fvdRetPreserve = '';
if ($retOrigen !== null) {
    if (isset($_GET['ret']) && is_string($_GET['ret']) && fvd_return_sanitize($_GET['ret']) !== null) {
        $fvdRetPreserve = $_GET['ret'];
    } elseif (isset($_GET['return']) && is_string($_GET['return']) && fvd_return_sanitize($_GET['return']) !== null) {
        $fvdRetPreserve = $_GET['return'];
    } else {
        $fvdRetPreserve = rawurlencode($retOrigen);
    }
}
$h1Reporte = $marcadorFijo !== null
    ? ($titulosPorMarcador[$marcadorFijo] ?? 'Indicadores (atletas)')
    : 'Atletas — indicadores de servicio (datos completos)';

$msgUi = isset($_GET['msg']) ? trim((string) $_GET['msg']) : '';
$resetNAfectados = isset($_GET['n']) ? (int) $_GET['n'] : 0;
$resetCampoKey = isset($_GET['campo']) ? trim((string) $_GET['campo']) : '';
$resetEtiquetas = [
    'carnet' => 'carnet',
    'traspaso' => 'traspaso',
    'anualidad' => 'anualidad',
    'afiliacion' => 'afiliación',
    'inscripcion' => 'inscripción (+ torneo_id)',
];
$rolSesionUi = trim((string) (AuthService::role() ?? ''));
$fvdEsDelegadoAsoc = $rolSesionUi === AuthService::ROLE_DELEGADO_ASOC;
$fvdPuedeResetMarcadores = $rolSesionUi === AuthService::ROLE_FVD_ADMIN;
$fvdEsAdminAsociacion = $rolSesionUi === AuthService::ROLE_ASO_ADMIN;

$columnas = $rows !== [] ? array_keys($rows[0]) : [];

$fvdIndCols = IndicadoresTablaDefs::columnasMetricas();
$totalesVals = $totalesAlcance !== []
    ? IndicadoresTablaDefs::valoresMetricasInt($totalesAlcance, 'totales alcance')
    : [];

require FVD_MASTER_ROOT . '/includes/layout_header.php';
?>
<div class="report-container fvd-rep-indicadores" style="max-width:100%">
    <p class="no-print" style="margin:0 0 .85rem">
        <a href="/fvd_portal/fvdmasteradmin/delegado_dashboard_new.php"
           class="inline-flex items-center text-black font-bold border-2 border-black px-4 py-2 rounded hover:bg-black hover:text-white transition-colors"
           style="display:inline-flex;align-items:center;gap:.45rem;color:#000;font-weight:800;border:2px solid #000;padding:.5rem .9rem;border-radius:.45rem;text-decoration:none;transition:all .15s ease">
            <i class="fas fa-arrow-left mr-2"></i> VOLVER AL PANEL
        </a>
    </p>
    <!-- fvd indicadores: bloque reinicio masivo v2026-04 -->
    <h1 class="fvd-atletas-title"><?= htmlspecialchars($h1Reporte, ENT_QUOTES, 'UTF-8') ?></h1>
    <?php if ($fvdPuedeResetMarcadores): ?>
    <p class="no-print" style="margin:0 0 .75rem;padding:10px 12px;border:2px solid #b91c1c;border-radius:8px;background:rgba(254,226,226,.35);font-size:.875rem;line-height:1.45">
        <strong>Reinicio masivo de marcadores:</strong> use los botones rojos más abajo (carnet, traspaso, anualidad, afiliación, inscripciones).
        Alcance según su sesión (FVD: todos los atletas; asociación/delegado: solo su club).
    </p>
    <?php endif; ?>
    <?php if ($marcadorFijo !== null && !$fvdEsDelegadoAsoc): ?>
    <p style="font-size:.8125rem;color:var(--fvd-muted);margin:0 0 .75rem;line-height:1.45">
        Listado de <code>atletas</code> con el marcador indicado en <strong>1</strong> (y su alcance regional).
        Cada fila de la tabla coincide con la condición del título.
    </p>
    <?php elseif (!$fvdEsDelegadoAsoc): ?>
    <p style="font-size:.8125rem;color:var(--fvd-muted);margin:0 0 .75rem;line-height:1.45">
        Se listan filas de <code>atletas</code> con los campos <strong>afiliación, anualidad, carnet, traspaso e inscripción</strong> según el modo elegido.
        <strong>Cualquiera</strong>: al menos un indicador en 1. <strong>Todos</strong>: los cinco en 1.
        El alcance es el de su sesión (asociación o FVD completo).
    </p>
    <?php endif; ?>

    <?php if ($msgUi === 'reset_ok'): ?>
        <p class="fvd-mod-msg" style="margin:0 0 .75rem;background:rgba(22,163,74,.15);border-color:#15803d">
            Reinicio aplicado: <strong><?= (int) $resetNAfectados ?></strong> fila(s) en
            <code><?= htmlspecialchars($resetEtiquetas[$resetCampoKey] ?? $resetCampoKey, ENT_QUOTES, 'UTF-8') ?></code>.
        </p>
    <?php elseif ($msgUi === 'reset_err'): ?>
        <p class="fvd-mod-msg" style="margin:0 0 .75rem">No se pudo completar el reinicio. Revise el registro del servidor o permisos.</p>
    <?php endif; ?>

    <?php if ($fvdPuedeResetMarcadores): ?>
    <section class="fvd-rep-indicadores__reset no-print" data-fvd-reset-marcadores="1" aria-label="Reinicio masivo de marcadores" style="margin:0 0 1.25rem;padding:14px;border-radius:8px;border:2px solid #991b1b;background:rgba(127,29,29,.18)">
        <h2 style="margin:0 0 .35rem;font-size:1.05rem;font-weight:800;color:#fecaca">Reiniciar marcadores (poner en 0)</h2>
        <p style="margin:0 0 .75rem;font-size:.72rem;color:var(--fvd-muted);line-height:1.45">
            Operación masiva sobre <code>atletas</code> en <strong>su alcance</strong> (FVD: toda la federación; delegado: solo esa asociación).
            Cada botón ejecuta <code>UPDATE … SET campo = 0</code>. <strong>Inscripción</strong> también pone <code>torneo_id = 0</code>.
        </p>
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
            <?php
            $accionesReset = [
                ['carnet', 'Reset carnets', '¿Poner en 0 el marcador de carnet en todos los atletas de su alcance?'],
                ['traspaso', 'Reset traspasos', '¿Poner en 0 el marcador de traspaso en todos los atletas de su alcance?'],
                ['anualidad', 'Reset anualidad', '¿Poner en 0 el marcador de anualidad en todos los atletas de su alcance?'],
                ['afiliacion', 'Reset afiliación', '¿Poner en 0 el marcador de afiliación en todos los atletas de su alcance?'],
                ['inscripcion', 'Reset inscripciones', '¿Poner en 0 inscripción y torneo_id en todos los atletas de su alcance?'],
            ];
            foreach ($accionesReset as $ar):
                [$mk, $mlab, $mconfirm] = $ar;
            ?>
            <form method="post" action="<?= htmlspecialchars($selfReport, ENT_QUOTES, 'UTF-8') ?>" style="margin:0" onsubmit="return confirm(<?= htmlspecialchars(json_encode($mconfirm, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>);">
                <input type="hidden" name="_action" value="reset_marcador_atletas">
                <input type="hidden" name="marcador" value="<?= htmlspecialchars($mk, ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($fvdRetPreserve !== ''): ?>
                <input type="hidden" name="ret" value="<?= htmlspecialchars($fvdRetPreserve, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
                <button type="submit" class="fvd-input" style="width:auto;padding:6px 12px;font-size:.75rem;cursor:pointer;background:#7f1d1d;color:#fecaca;border-color:#991b1b"><?= htmlspecialchars($mlab, ENT_QUOTES, 'UTF-8') ?></button>
            </form>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($marcadorFijo === null): ?>
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
        <?php if ($porAsociacion !== [] && !$fvdEsAdminAsociacion): ?>
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
    <?php endif; ?>

    <form method="get" action="" class="no-print" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin:0 0 1rem">
        <?php if ($fvdRetPreserve !== ''): ?>
        <input type="hidden" name="ret" value="<?= htmlspecialchars($fvdRetPreserve, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <?php if ($marcadorFijo !== null): ?>
        <input type="hidden" name="marcador" value="<?= htmlspecialchars($marcadorFijo, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="modo" value="cualquiera">
        <?php if (!$fvdEsDelegadoAsoc): ?>
        <p style="margin:0;font-size:.75rem;color:var(--fvd-muted);max-width:28rem">Filtro fijo por marcador; no aplica el modo «cualquiera / todos».</p>
        <?php endif; ?>
        <?php else: ?>
        <div>
            <label style="font-size:.75rem;color:var(--fvd-muted);display:block">Modo</label>
            <select name="modo" class="fvd-input" style="min-width:12rem">
                <option value="cualquiera"<?= $modo === 'cualquiera' ? ' selected' : '' ?>>Al menos un indicador = 1</option>
                <option value="todos"<?= $modo === 'todos' ? ' selected' : '' ?>>Los cinco indicadores = 1</option>
            </select>
        </div>
        <?php endif; ?>
        <div>
            <label style="font-size:.75rem;color:var(--fvd-muted);display:block">Cédula (opc.)</label>
            <input class="fvd-input" type="search" name="cedula" value="<?= htmlspecialchars($cedula, ENT_QUOTES, 'UTF-8') ?>" placeholder="Filtrar…" style="max-width:11rem">
        </div>
        <div>
            <label style="font-size:.75rem;color:var(--fvd-muted);display:block">Nombre (opc.)</label>
            <input class="fvd-input" type="search" name="q" value="<?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?>" placeholder="Contiene…" style="max-width:12rem">
        </div>
        <button type="submit" class="fvd-input" style="width:auto;padding:6px 12px">Aplicar</button>
        <?php
        $qsReset = [];
        if ($fvdRetPreserve !== '') {
            $qsReset['ret'] = $fvdRetPreserve;
        }
        $urlRestablecer = $selfReport . ($qsReset !== [] ? '?' . http_build_query($qsReset, '', '&', PHP_QUERY_RFC3986) : '');
        ?>
        <a class="fvd-input" style="width:auto;padding:6px 12px;text-decoration:none;display:inline-flex;align-items:center;box-sizing:border-box" href="<?= htmlspecialchars($urlRestablecer, ENT_QUOTES, 'UTF-8') ?>">Restablecer</a>
        <?php
        $qsCsv = ['format' => 'csv', 'modo' => $modo];
        if ($cedula !== '') {
            $qsCsv['cedula'] = $cedula;
        }
        if ($nombre !== '') {
            $qsCsv['q'] = $nombre;
        }
        if ($marcadorFijo !== null) {
            $qsCsv['marcador'] = $marcadorFijo;
        }
        if ($fvdRetPreserve !== '') {
            $qsCsv['ret'] = $fvdRetPreserve;
        }
        $urlCsv = $selfReport . '?' . http_build_query($qsCsv, '', '&', PHP_QUERY_RFC3986);
        ?>
        <a class="fvd-input" style="width:auto;padding:6px 12px;text-decoration:none;display:inline-flex;align-items:center;box-sizing:border-box;font-weight:600" href="<?= htmlspecialchars($urlCsv, ENT_QUOTES, 'UTF-8') ?>">Descargar CSV</a>
        <?php if ($retOrigen === null): ?>
        <a class="fvd-input" style="width:auto;padding:6px 12px;text-decoration:none;display:inline-flex;align-items:center;box-sizing:border-box" href="<?= htmlspecialchars($atletasBackUrl, ENT_QUOTES, 'UTF-8') ?>">← Listado atletas</a>
        <?php endif; ?>
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
