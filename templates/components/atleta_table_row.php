<?php
declare(strict_types=1);
/** @var array<string, mixed> $r */
/** @var string $selfUrl */
$fvd_puede_traspaso = $fvd_puede_traspaso ?? false;
$fvd_toggle_atleta = \AuthService::isSuperAdmin();
$fotoFn = isset($r['foto']) ? trim((string) $r['foto']) : '';
$cel = isset($r['celular']) ? trim((string) $r['celular']) : '';
$em = isset($r['email']) ? trim((string) $r['email']) : '';
$aid = (int) $r['id'];
$dash = "\xE2\x80\x94";
$formHref = htmlspecialchars($selfUrl . '?action=form&id=' . $aid, ENT_QUOTES, 'UTF-8');
?>
<tr>
    <td class="fvd-col-check fvd-only-ficha-tab">
        <input type="checkbox" class="fvd-atleta-check" value="<?= $aid ?>" aria-label="Seleccionar para carnets en lote">
    </td>
    <td class="fvd-col-id"><?= $aid ?></td>
    <td class="fvd-col-foto">
        <?php if ($fotoFn !== ''): ?>
            <img class="atleta-img-preview" src="<?= htmlspecialchars(url('crud_atletas/uploads/' . ltrim($fotoFn, '/')), ENT_QUOTES, 'UTF-8') ?>" alt="" width="40" height="40" loading="lazy">
        <?php else: ?>
            <span class="atleta-img-preview atleta-img-preview--placeholder" aria-hidden="true"></span>
        <?php endif; ?>
    </td>
    <td class="fvd-col-ced"><strong><a class="fvd-atleta-row-ficha" href="<?= $formHref ?>" title="Ver o editar ficha"><?= htmlspecialchars((string) ($r['cedula'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a></strong></td>
    <td class="fvd-col-nom" title="<?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><a class="fvd-atleta-row-ficha" href="<?= $formHref ?>" title="Ver o editar ficha"><?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a></td>
    <td class="fvd-col-contact fvd-col-cel"><?= $cel !== '' ? htmlspecialchars($cel, ENT_QUOTES, 'UTF-8') : $dash ?></td>
    <td class="fvd-col-contact fvd-col-email"><?= $em !== '' ? htmlspecialchars($em, ENT_QUOTES, 'UTF-8') : $dash ?></td>
    <td class="fvd-col-tech fvd-col-sexo"><?= (int) ($r['sexo'] ?? 0) ?></td>
    <td class="fvd-col-tech fvd-col-numfvd"><?php $nf = (int) ($r['numfvd'] ?? 0); echo $nf > 0 ? (string) $nf : $dash; ?></td>
    <td class="fvd-col-asoc" title="<?= htmlspecialchars((string) ($r['asociacion_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($r['asociacion_nombre'] ?? $dash), ENT_QUOTES, 'UTF-8') ?></td>
    <td class="fvd-col-tech fvd-col-categ"><?= htmlspecialchars(FvdAdminService::atletasCategoriaEtiquetaPorCodigo((int) ($r['categ'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
    <td class="fvd-col-tech fvd-col-estatus"><?= htmlspecialchars(FvdAdminService::atletasEstatusEtiqueta((int) ($r['estatus'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
    <td class="fvd-col-actions fvd-col-actions--icons">
        <span class="fvd-action-icons" role="group" aria-label="Acciones">
            <a class="fvd-atleta-ficha-txt" href="<?= $formHref ?>">Ver</a>
            <a class="fvd-atleta-ficha-txt" href="<?= $formHref ?>">Editar</a>
            <?php if ($fvd_toggle_atleta): ?>
                <?php
                $estAt = (int) ($r['estatus'] ?? 0);
                $activoAt = $estAt === \FvdAdminService::ATLETA_ESTATUS_ACTIVO;
                ?>
                <form method="post" action="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" style="display:inline;margin:0">
                    <input type="hidden" name="_action" value="toggle_activo">
                    <input type="hidden" name="id" value="<?= $aid ?>">
                    <button type="submit" class="fvd-atleta-ficha-txt" style="background:none;border:none;padding:0;cursor:pointer;color:inherit;font:inherit;text-decoration:underline" title="Conmutar activo / inactivo"><?= $activoAt ? 'Desactivar' : 'Activar' ?></button>
                </form>
            <?php endif; ?>
            <a class="fvd-icon-btn fvd-icon-btn--danger" href="<?= htmlspecialchars($selfUrl . '?action=delete&id=' . $aid, ENT_QUOTES, 'UTF-8') ?>" title="Eliminar" onclick="return confirm('¿Eliminar atleta?');">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </a>
            <a class="fvd-icon-btn fvd-action-ficha-only" href="<?= htmlspecialchars($selfUrl . '?action=carnets&ids=' . $aid, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" title="Imprimir carnet">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V4a2 2 0 114 0v2m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
            </a>
            <?php if ($fvd_puede_traspaso): ?>
                <a class="fvd-icon-btn fvd-action-ficha-only" href="<?= htmlspecialchars($selfUrl . '?action=traspaso&id=' . $aid, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" title="Solicitar traspaso de asociación">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                </a>
            <?php endif; ?>
        </span>
    </td>
</tr>
