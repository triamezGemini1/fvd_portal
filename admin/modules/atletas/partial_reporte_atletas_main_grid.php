<?php

declare(strict_types=1);

/**
 * Tabla principal + panel «Movimientos solicitados» (misma estructura que informe de afiliados).
 *
 * Variables requeridas: $rows, $columnas, $fvdRepIndicadoresColEtiquetaPorKey, $fvdRepIndicadoresLogicalLcPorKey,
 *   $movimientosSolicitados, $atletasUrl
 * Opcional: $fvd_rep_show_accion (bool, default true)
 */

if (!isset($rows) || !is_array($rows)) {
    $rows = [];
}
if (!isset($columnas) || !is_array($columnas)) {
    $columnas = [];
}
if (!isset($fvdRepIndicadoresColEtiquetaPorKey) || !is_array($fvdRepIndicadoresColEtiquetaPorKey)) {
    $fvdRepIndicadoresColEtiquetaPorKey = [];
}
if (!isset($fvdRepIndicadoresLogicalLcPorKey) || !is_array($fvdRepIndicadoresLogicalLcPorKey)) {
    $fvdRepIndicadoresLogicalLcPorKey = [];
}
if (!isset($movimientosSolicitados) || !is_array($movimientosSolicitados)) {
    $movimientosSolicitados = [];
}
$atletasUrl = isset($atletasUrl) ? (string) $atletasUrl : fvd_crud_self_url('atletas');
$fvd_rep_show_accion = !isset($fvd_rep_show_accion) || (bool) $fvd_rep_show_accion;
$fvd_rep_row_id_field = isset($fvd_rep_row_id_field) ? (string) $fvd_rep_row_id_field : 'id';

?>
<div class="fvd-rep-indicadores__grid" style="display:grid;grid-template-columns:minmax(0,1fr);gap:12px;align-items:start;width:100%;max-width:100%;box-sizing:border-box">
    <div class="fvd-mod-table-wrap fvd-rep-table-wrap--paginated" style="width:100%;max-width:100%;box-sizing:border-box;overflow-x:auto;-webkit-overflow-scrolling:touch">
        <table class="fvd-mod-table tabla-atletas" style="font-size:.72rem;width:max-content;min-width:100%;table-layout:auto">
            <thead>
            <tr>
                <?php foreach ($columnas as $col): ?>
                    <?php $thLab = $fvdRepIndicadoresColEtiquetaPorKey[$col] ?? $col; ?>
                    <th scope="col" style="white-space:nowrap;position:sticky;top:0;background:var(--fvd-azul-card,#1e293b);z-index:1"><?= htmlspecialchars($thLab, ENT_QUOTES, 'UTF-8') ?></th>
                <?php endforeach; ?>
                <?php if ($fvd_rep_show_accion): ?>
                    <th scope="col" class="no-print" style="position:sticky;top:0;background:var(--fvd-azul-card,#1e293b);z-index:1">Acción</th>
                <?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <?php foreach ($columnas as $col): ?>
                        <?php
                        $logicalLc = $fvdRepIndicadoresLogicalLcPorKey[$col] ?? strtolower((string) $col);
                        $cell = is_array($r) ? ($r[$col] ?? null) : null;
                        ?>
                        <?php if ($logicalLc === 'foto'):
                            $fv = trim((string) ($cell ?? ''));
                        ?>
                        <?php if ($fv !== ''):
                            $imgUrl = htmlspecialchars(url('crud_atletas/uploads/' . ltrim($fv, '/')), ENT_QUOTES, 'UTF-8');
                        ?>
                        <td style="vertical-align:middle;text-align:center;width:52px">
                            <img src="<?= $imgUrl ?>" alt="" width="40" height="40" loading="lazy" style="object-fit:cover;border-radius:4px;max-width:40px;max-height:40px;display:block;margin:0 auto" />
                        </td>
                        <?php else: ?>
                        <td style="vertical-align:middle;text-align:center">—</td>
                        <?php endif; ?>
                        <?php elseif ($logicalLc === 'estatus'):
                            $est = (int) ($cell ?? 0);
                            $nfEst = is_array($r) && isset($r['numfvd']) ? (int) $r['numfvd'] : null;
                            $disp = FvdAdminService::atletasEstatusEtiqueta($est, $nfEst);
                        ?>
                        <td style="max-width:min(28rem,32vw);overflow:hidden;text-overflow:ellipsis;vertical-align:top" title="<?= htmlspecialchars($disp, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($disp, ENT_QUOTES, 'UTF-8') ?></td>
                        <?php elseif ($logicalLc === 'numfvd'):
                            $nf = (int) ($cell ?? 0);
                            $disp = $nf > 0 ? (string) $nf : '—';
                        ?>
                        <td style="max-width:min(28rem,32vw);overflow:hidden;text-overflow:ellipsis;vertical-align:top" title="<?= htmlspecialchars($disp, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($disp, ENT_QUOTES, 'UTF-8') ?></td>
                        <?php else:
                            $disp = $cell === null || $cell === '' ? '—' : (is_scalar($cell) || $cell instanceof \Stringable ? (string) $cell : '');
                        ?>
                        <td style="max-width:min(28rem,32vw);overflow:hidden;text-overflow:ellipsis;vertical-align:top" title="<?= htmlspecialchars($disp, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($disp, ENT_QUOTES, 'UTF-8') ?></td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($fvd_rep_show_accion): ?>
                        <td class="no-print" style="white-space:nowrap">
                            <a href="<?= htmlspecialchars($atletasUrl . '?action=form&id=' . (int) (is_array($r) ? ($r[$fvd_rep_row_id_field] ?? 0) : 0), ENT_QUOTES, 'UTF-8') ?>">Editar</a>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="<?= max(1, count($columnas) + ($fvd_rep_show_accion ? 1 : 0)) ?>" style="padding:12px">Sin registros con este criterio.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    if (!empty($fvd_repPaginator) && is_array($fvd_repPaginator) && !empty($fvd_repPaginatorSelf)) {
        require FVD_PROJECT_ROOT . '/includes/partial_fvd_report_paginator.php';
    }
    ?>
    <aside style="width:100%;max-width:100%;box-sizing:border-box;border:1px solid var(--fvd-border);border-radius:8px;padding:10px;background:rgba(255,255,255,0.04);max-height:min(40vh,560px);overflow:auto">
        <h3 style="margin:0 0 .5rem;font-size:.88rem">Movimientos solicitados</h3>
        <p style="margin:0 0 .6rem;font-size:.72rem;color:var(--fvd-muted)">
            Resumen compacto para control operativo: Nº FVD o cédula, nombre y operación solicitada.
        </p>
        <table class="fvd-mod-table" style="font-size:.72rem">
            <thead>
            <tr>
                <th scope="col">Carnet/Cédula</th>
                <th scope="col">Nombre</th>
                <th scope="col">Operación</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($movimientosSolicitados as $mov): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($mov['doc'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($mov['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($mov['operacion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($movimientosSolicitados === []): ?>
                <tr><td colspan="3" style="padding:10px">Sin movimientos solicitados en el filtro actual.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </aside>
</div>
