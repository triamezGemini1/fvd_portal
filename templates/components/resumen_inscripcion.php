<?php
declare(strict_types=1);
/**
 * Revisión final: tarjetas con nombre, cédula, club y foto.
 *
 * @var list<array{nombre:string, cedula:string, club:string, foto_url:?string}> $fvd_resumen_inscripcion
 * @var string|null $fvd_resumen_titulo
 */
$fvd_resumen_inscripcion = $fvd_resumen_inscripcion ?? [];
$fvd_resumen_titulo = isset($fvd_resumen_titulo) && is_string($fvd_resumen_titulo) && $fvd_resumen_titulo !== ''
    ? $fvd_resumen_titulo
    : 'Integrantes seleccionados';
?>
<div class="fvd-insc-nomina-title"><?= htmlspecialchars($fvd_resumen_titulo, ENT_QUOTES, 'UTF-8') ?></div>
<?php if ($fvd_resumen_inscripcion === []): ?>
    <p class="fvd-insc-hint" id="fvd-insc-resumen-empty">Añada atletas desde el buscador.</p>
<?php endif; ?>
<div class="fvd-insc-resumen-grid" id="fvd-insc-resumen-grid"<?= $fvd_resumen_inscripcion === [] ? ' hidden' : '' ?>>
    <?php foreach ($fvd_resumen_inscripcion as $t): ?>
        <?php
        $nom = htmlspecialchars((string) ($t['nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
        $ced = htmlspecialchars((string) ($t['cedula'] ?? ''), ENT_QUOTES, 'UTF-8');
        $club = htmlspecialchars((string) ($t['club'] ?? ''), ENT_QUOTES, 'UTF-8');
        $fu = isset($t['foto_url']) && is_string($t['foto_url']) && $t['foto_url'] !== '' ? htmlspecialchars($t['foto_url'], ENT_QUOTES, 'UTF-8') : '';
        ?>
        <article class="fvd-insc-card" data-fvd-resumen-static="1">
            <?php if ($fu !== ''): ?>
                <img class="fvd-insc-card__photo" src="<?= $fu ?>" alt="" loading="lazy" width="120" height="120">
            <?php else: ?>
                <div class="fvd-insc-card__ph">Sin foto</div>
            <?php endif; ?>
            <div class="fvd-insc-card__name"><?= $nom ?></div>
            <div>CI <?= $ced ?></div>
            <div style="color:var(--fvd-muted,#94a3b8);margin-top:2px"><?= $club ?></div>
        </article>
    <?php endforeach; ?>
</div>
