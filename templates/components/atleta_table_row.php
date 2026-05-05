<?php
declare(strict_types=1);
/** @var array<string, mixed> $r */
/** @var string $selfUrl */
if (!function_exists('fvd_return_append_to_url')) {
    require_once dirname(__DIR__, 2) . '/config/paths.php';
}
$fvd_puede_traspaso = $fvd_puede_traspaso ?? false;
$fvd_atletas_show_asociacion_col = $fvd_atletas_show_asociacion_col ?? true;
$fvd_delegado_traspaso_destinos = isset($fvd_delegado_traspaso_destinos) && is_array($fvd_delegado_traspaso_destinos)
    ? $fvd_delegado_traspaso_destinos
    : [];
$fvd_toggle_atleta = \AuthService::isSuperAdmin();
$fotoFn = isset($r['foto']) ? trim((string) $r['foto']) : '';
$aid = (int) $r['id'];
$mostrarSolDelegado = isset($fvd_url_solicitud_carnet_base) && is_string($fvd_url_solicitud_carnet_base) && $fvd_url_solicitud_carnet_base !== ''
    && \AuthService::checkAccess([\AuthService::ROLE_ASO_ADMIN, \AuthService::ROLE_DELEGADO_ASOC]);
$mostrarTraspasoDelegado = $mostrarSolDelegado
    && isset($fvd_url_solicitud_traspaso_base) && is_string($fvd_url_solicitud_traspaso_base) && $fvd_url_solicitud_traspaso_base !== '';
$dash = "\xE2\x80\x94";
$estAt = (int) ($r['estatus'] ?? 0);
$esBaja = $estAt === \FvdAdminService::ATLETA_ESTATUS_BAJA;
$formHref = htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=form&id=' . $aid), ENT_QUOTES, 'UTF-8');
$postActionUrl = htmlspecialchars(fvd_return_preserve_query_params($selfUrl), ENT_QUOTES, 'UTF-8');
$fvdRetRow = '';
if (isset($_GET['ret']) && is_string($_GET['ret']) && fvd_return_sanitize($_GET['ret']) !== null) {
    $fvdRetRow = $_GET['ret'];
} elseif (isset($_GET['return']) && is_string($_GET['return']) && fvd_return_sanitize($_GET['return']) !== null) {
    $fvdRetRow = $_GET['return'];
}
$fvdRetRowKey = isset($_GET['ret']) && is_string($_GET['ret']) && fvd_return_sanitize($_GET['ret']) !== null ? 'ret' : (isset($_GET['return']) && is_string($_GET['return']) && fvd_return_sanitize($_GET['return']) !== null ? 'return' : '');

if (!empty($fvd_atletas_delegado_line)) {
    $nomRow = trim((string) ($r['nombre'] ?? ''));
    $cedRow = trim((string) ($r['cedula'] ?? ''));
    $nf = (int) ($r['numfvd'] ?? 0);
    $nfParaEst = isset($r['numfvd']) ? (int) $r['numfvd'] : null;
    $sx = (int) ($r['sexo'] ?? 0);
    $sxLab = $sx === 1 ? 'M' : ($sx === 2 ? 'F' : '—');
    $estEtq = htmlspecialchars(FvdAdminService::atletasEstatusEtiqueta((int) ($r['estatus'] ?? 0), $nfParaEst), ENT_QUOTES, 'UTF-8');
    ?>
<tr class="fvd-atleta-row--delegado-line">
    <td class="fvd-dl-numfvd"><?= $nf > 0 ? (string) $nf : $dash ?></td>
    <td class="fvd-dl-foto">
        <?php if ($fotoFn !== ''): ?>
            <img class="atleta-img-preview atleta-img-preview--dl" src="<?= htmlspecialchars(url('crud_atletas/uploads/' . ltrim($fotoFn, '/')), ENT_QUOTES, 'UTF-8') ?>" alt="" width="36" height="36" loading="lazy">
        <?php else: ?>
            <span class="atleta-img-preview atleta-img-preview--placeholder atleta-img-preview--dl" aria-hidden="true"></span>
        <?php endif; ?>
    </td>
    <td class="fvd-dl-cednom">
        <span class="fvd-dl-ced"><?= htmlspecialchars($cedRow !== '' ? $cedRow : $dash, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="fvd-dl-cednom-sep" aria-hidden="true">·</span>
        <a class="fvd-dl-nom fvd-atleta-row-ficha" href="<?= $formHref ?>" title="Ver o editar ficha"><?= htmlspecialchars($nomRow !== '' ? $nomRow : $dash, ENT_QUOTES, 'UTF-8') ?></a>
    </td>
    <td class="fvd-dl-sexo"><?= htmlspecialchars($sxLab, ENT_QUOTES, 'UTF-8') ?></td>
    <td class="fvd-dl-est"><?= $estEtq ?></td>
    <td class="fvd-dl-act fvd-col-actions fvd-col-actions--icons">
        <span class="fvd-action-icons fvd-action-icons--delegado-line" role="group" aria-label="Acciones">
            <?php if ($mostrarSolDelegado && !$esBaja): ?>
                <button type="button" class="fvd-atleta-ficha-txt fvd-delegado-sol-direct" style="background:none;border:none;padding:0;cursor:pointer;font:inherit;color:inherit;text-decoration:underline" data-fvd-sol-tipo="carnet" data-fvd-atleta-id="<?= (int) $aid ?>" data-fvd-numfvd="<?= $nf > 0 ? (string) $nf : '' ?>" data-fvd-nombre="<?= htmlspecialchars($nomRow, ENT_QUOTES, 'UTF-8') ?>">Carnet</button>
            <?php else: ?>
                <a class="fvd-icon-btn fvd-action-ficha-only" href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=carnets&ids=' . $aid), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" title="Vista carnet / ficha">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V4a2 2 0 114 0v2m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
                </a>
            <?php endif; ?>
            <?php if ($mostrarTraspasoDelegado && !$esBaja): ?>
                <?php if ($fvd_delegado_traspaso_destinos !== []): ?>
                <span class="fvd-delegado-traspaso-queue-wrap" style="display:inline-flex;flex-wrap:wrap;align-items:center;gap:4px;max-width:100%">
                    <label class="no-print" style="font-size:0.62rem;color:#475569;white-space:nowrap">Dest.</label>
                    <select class="fvd-input fvd-delegado-traspaso-dest no-print" title="Asociación destino" aria-label="Destino traspaso" style="max-width:6.5rem;font-size:0.65rem;padding:2px 4px;min-height:1.6rem">
                        <?php foreach ($fvd_delegado_traspaso_destinos as $dOpt): ?>
                            <?php $did = (int) ($dOpt['id'] ?? 0);
                            if ($did <= 0) {
                                continue;
                            }
                            $dNom = trim((string) ($dOpt['nombre'] ?? ''));
                            $dNomShort = strlen($dNom) > 26 ? substr($dNom, 0, 24) . '…' : $dNom;
                            ?>
                        <option value="<?= $did ?>"><?= htmlspecialchars($dNomShort, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="fvd-atleta-ficha-txt fvd-delegado-sol-direct" style="background:none;border:none;padding:0;cursor:pointer;font:inherit;color:inherit;text-decoration:underline" data-fvd-sol-tipo="traspaso" data-fvd-atleta-id="<?= (int) $aid ?>" data-fvd-numfvd="<?= $nf > 0 ? (string) $nf : '' ?>" data-fvd-nombre="<?= htmlspecialchars($nomRow, ENT_QUOTES, 'UTF-8') ?>">Traspaso</button>
                </span>
                <?php else: ?>
                <span class="fvd-atleta-ficha-txt" style="opacity:0.55;cursor:not-allowed" title="No hay otras asociaciones como destino">Traspaso</span>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($fvd_puede_traspaso): ?>
                <a class="fvd-icon-btn fvd-action-ficha-only" href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=traspaso&id=' . $aid), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" title="Solicitar traspaso de asociación">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                </a>
            <?php endif; ?>
        </span>
    </td>
</tr>
    <?php

    return;
}
?>
<tr>
    <td class="fvd-col-id"><?= $aid ?></td>
    <td class="fvd-col-foto">
        <?php if ($fotoFn !== ''): ?>
            <img class="atleta-img-preview" src="<?= htmlspecialchars(url('crud_atletas/uploads/' . ltrim($fotoFn, '/')), ENT_QUOTES, 'UTF-8') ?>" alt="" width="40" height="40" loading="lazy">
        <?php else: ?>
            <span class="atleta-img-preview atleta-img-preview--placeholder" aria-hidden="true"></span>
        <?php endif; ?>
    </td>
    <?php
    $nomRow = trim((string) ($r['nombre'] ?? ''));
    $cedRow = trim((string) ($r['cedula'] ?? ''));
    $tituloIdent = $cedRow !== '' && $nomRow !== '' ? $cedRow . ' — ' . $nomRow : ($nomRow !== '' ? $nomRow : $cedRow);
    $nfParaEst = isset($r['numfvd']) ? (int) $r['numfvd'] : null;
    ?>
    <td class="fvd-col-ident" title="<?= htmlspecialchars($tituloIdent, ENT_QUOTES, 'UTF-8') ?>">
        <a class="fvd-atleta-row-ficha fvd-col-ident__link" href="<?= $formHref ?>" title="Ver o editar ficha">
            <span class="fvd-col-ident__ced"><?= htmlspecialchars($cedRow !== '' ? $cedRow : $dash, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="fvd-col-ident__sep" aria-hidden="true">·</span>
            <span class="fvd-col-ident__nom"><?= htmlspecialchars($nomRow !== '' ? $nomRow : $dash, ENT_QUOTES, 'UTF-8') ?></span>
        </a>
    </td>
    <?php if ($fvd_atletas_show_asociacion_col): ?>
    <td class="fvd-col-asoc"><?php $an = isset($r['asociacion_nombre']) ? trim((string) $r['asociacion_nombre']) : ''; echo $an !== '' ? htmlspecialchars($an, ENT_QUOTES, 'UTF-8') : $dash; ?></td>
    <?php endif; ?>
    <td class="fvd-col-tech fvd-col-sexo"><?= (int) ($r['sexo'] ?? 0) ?></td>
    <td class="fvd-col-tech fvd-col-numfvd"><?php $nf = (int) ($r['numfvd'] ?? 0); echo $nf > 0 ? (string) $nf : $dash; ?></td>
    <td class="fvd-col-tech fvd-col-categ"><?= (string) (int) ($r['categ'] ?? 0) ?></td>
    <td class="fvd-col-tech fvd-col-estatus"><?= htmlspecialchars(FvdAdminService::atletasEstatusEtiqueta((int) ($r['estatus'] ?? 0), $nfParaEst), ENT_QUOTES, 'UTF-8') ?></td>
    <td class="fvd-col-actions fvd-col-actions--icons">
        <span class="fvd-action-icons" role="group" aria-label="Acciones">
            <a class="fvd-atleta-ficha-txt" href="<?= $formHref ?>">Ver</a>
            <a class="fvd-atleta-ficha-txt" href="<?= $formHref ?>">Editar</a>
            <?php if ($fvd_toggle_atleta && !$esBaja): ?>
                <?php $activoAt = $estAt === \FvdAdminService::ATLETA_ESTATUS_ACTIVO; ?>
                <form method="post" action="<?= $postActionUrl ?>" style="display:inline;margin:0">
                    <input type="hidden" name="_action" value="toggle_activo">
                    <input type="hidden" name="id" value="<?= $aid ?>">
                    <?php if ($fvdRetRow !== '' && $fvdRetRowKey !== ''): ?><input type="hidden" name="<?= htmlspecialchars($fvdRetRowKey, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($fvdRetRow, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
                    <button type="submit" class="fvd-atleta-ficha-txt" style="background:none;border:none;padding:0;cursor:pointer;color:inherit;font:inherit;text-decoration:underline" title="Conmutar activo / inactivo"><?= $activoAt ? 'Desactivar' : 'Activar' ?></button>
                </form>
            <?php endif; ?>
            <?php if ($esBaja): ?>
                <form method="post" action="<?= $postActionUrl ?>" style="display:inline;margin:0" onsubmit="return confirm('¿Restaurar este atleta al listado (estatus pendiente)?');">
                    <input type="hidden" name="_action" value="restaurar_atleta">
                    <input type="hidden" name="id" value="<?= $aid ?>">
                    <?php if ($fvdRetRow !== '' && $fvdRetRowKey !== ''): ?><input type="hidden" name="<?= htmlspecialchars($fvdRetRowKey, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($fvdRetRow, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
                    <button type="submit" class="fvd-atleta-ficha-txt" style="background:none;border:none;padding:0;cursor:pointer;color:inherit;font:inherit;text-decoration:underline" title="Quitar baja lógica">Restaurar</button>
                </form>
            <?php else: ?>
                <form method="post" action="<?= $postActionUrl ?>" style="display:inline;margin:0" onsubmit="return confirm('¿Dar de baja al atleta? No se borrará el registro.');">
                    <input type="hidden" name="_action" value="dar_baja">
                    <input type="hidden" name="id" value="<?= $aid ?>">
                    <?php if ($fvdRetRow !== '' && $fvdRetRowKey !== ''): ?><input type="hidden" name="<?= htmlspecialchars($fvdRetRowKey, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($fvdRetRow, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
                    <button type="submit" class="fvd-icon-btn fvd-icon-btn--danger" style="background:none;border:none;padding:0;cursor:pointer;color:inherit" title="Dar de baja (listado)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </form>
            <?php endif; ?>
            <?php if ($mostrarSolDelegado && !$esBaja): ?>
                <a class="fvd-atleta-ficha-txt" href="<?= htmlspecialchars(fvd_return_append_to_url($fvd_url_solicitud_carnet_base . '?atleta_id=' . $aid), ENT_QUOTES, 'UTF-8') ?>">Carnet</a>
            <?php else: ?>
                <a class="fvd-icon-btn fvd-action-ficha-only" href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=carnets&ids=' . $aid), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" title="Vista carnet / ficha">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V4a2 2 0 114 0v2m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
                </a>
            <?php endif; ?>
            <?php if ($mostrarTraspasoDelegado && !$esBaja): ?>
                <a class="fvd-atleta-ficha-txt" href="<?= htmlspecialchars(fvd_return_append_to_url($fvd_url_solicitud_traspaso_base . '?atleta_id=' . $aid), ENT_QUOTES, 'UTF-8') ?>">Transferencia</a>
            <?php endif; ?>
            <?php if ($fvd_puede_traspaso): ?>
                <a class="fvd-icon-btn fvd-action-ficha-only" href="<?= htmlspecialchars(fvd_return_append_to_url($selfUrl . '?action=traspaso&id=' . $aid), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" title="Solicitar traspaso de asociación">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                </a>
            <?php endif; ?>
        </span>
    </td>
</tr>
