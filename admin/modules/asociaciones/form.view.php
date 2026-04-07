<?php
/** @var ?array $row */
/** @var ?int $id */
/** @var string $selfUrl */
$isEdit = $row !== null;
$r = $row ?? [];
$logoUrl = '';
if (!empty($r['logo']) && is_string($r['logo']) && $r['logo'] !== '') {
    $logoUrl = upload_url($r['logo']);
}
?>

<div class="fvd-asoc-form-page">
<h1 class="fvd-asoc-form-page__title"><?= $isEdit ? 'Editar asociación' : 'Nueva asociación' ?></h1>

<form class="fvd-mod-form fvd-mod-form--wide fvd-mod-form--asoc-2col fvd-mod-form--asoc-bordered" method="post" enctype="multipart/form-data" action="">
    <input type="hidden" name="_action" value="save">
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><?php endif; ?>

    <div class="fvd-asoc-form-layout">
        <div class="fvd-asoc-grid" aria-label="Datos de la asociación">
            <div class="fvd-row">
                <label for="nombre">Nombre</label>
                <input class="fvd-input" id="nombre" name="nombre" required value="<?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="fvd-row">
                <label for="delegado">Delegado</label>
                <input class="fvd-input" id="delegado" name="delegado" value="<?= htmlspecialchars((string) ($r['delegado'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="fvd-row">
                <label for="telefono">Teléfono</label>
                <input class="fvd-input" id="telefono" name="telefono" value="<?= htmlspecialchars((string) ($r['telefono'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="fvd-row">
                <label for="email">Email</label>
                <input class="fvd-input" type="email" id="email" name="email" value="<?= htmlspecialchars((string) ($r['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="fvd-row">
                <label for="numreg">Nº registro (numreg)</label>
                <input class="fvd-input" id="numreg" name="numreg" value="<?= htmlspecialchars((string) ($r['numreg'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="fvd-row">
                <label for="providencia">Providencia</label>
                <input class="fvd-input" id="providencia" name="providencia" value="<?= htmlspecialchars((string) ($r['providencia'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="fvd-row fvd-row--span2">
                <label for="direccion">Dirección</label>
                <input class="fvd-input" id="direccion" name="direccion" value="<?= htmlspecialchars((string) ($r['direccion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="fvd-row">
                <label for="fechreg">Fecha registro</label>
                <input class="fvd-input" type="date" id="fechreg" name="fechreg" value="<?= htmlspecialchars(substr((string) ($r['fechreg'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="fvd-row">
                <label for="fechprovi">Fecha providencia</label>
                <input class="fvd-input" type="date" id="fechprovi" name="fechprovi" value="<?= htmlspecialchars(substr((string) ($r['fechprovi'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="fvd-row">
                <label for="ultelECC">Última fecha ECC</label>
                <input class="fvd-input" type="date" id="ultelECC" name="ultelECC" value="<?= htmlspecialchars(substr((string) ($r['ultelECC'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>

        <aside class="fvd-asoc-logo-panel" aria-label="Logo de la asociación">
            <label for="logo">Logo</label>
            <input class="fvd-input" type="file" id="logo" name="logo" accept="image/*" style="padding:4px">
            <div class="fvd-asoc-logo-preview" id="fvd_preview_logo">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Logo actual" width="200" height="200" style="max-width:100%;max-height:220px;width:auto;height:auto;object-fit:contain;border-radius:8px">
                <?php else: ?>
                    <span class="fvd-asoc-logo-preview__placeholder">Vista previa del logo</span>
                <?php endif; ?>
            </div>
            <?php if (!empty($r['logo'])): ?>
                <p class="fvd-asoc-logo-meta fvd-atl-muted">
                    Archivo: <?= htmlspecialchars((string) $r['logo'], ENT_QUOTES, 'UTF-8') ?>
                    <?php if ($logoUrl !== ''): ?>
                        · <a href="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Abrir</a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </aside>
    </div>

    <div class="fvd-mod-actions">
        <button type="submit">Guardar</button>
        <a href="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>">Volver</a>
    </div>
</form>
</div>

<script src="<?= htmlspecialchars(url('assets/js/file-preview.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script>
(function () {
    if (typeof window.filePreview === 'undefined') return;
    window.filePreview.init('logo', 'fvd_preview_logo', 'image', { previewSize: 220 });
})();
</script>
