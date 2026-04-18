<?php
declare(strict_types=1);
/** @var array<string, mixed> $card */
$carnetUnSolo = $carnetUnSolo ?? false;
$cEmit = !empty($card['carnet_solicitado']);
$imgPx = $carnetUnSolo ? 132 : 108;
?>
<div class="fvd-carnet-card" data-atleta-id="<?= (int) $card['atleta_id'] ?>">
    <div class="fvd-carnet-card__inner">
        <div class="fvd-carnet-card__photo">
            <?php if (!empty($card['foto_url'])): ?>
                <img<?= $carnetUnSolo ? ' id="fvd-carnet-live-photo"' : '' ?> src="<?= htmlspecialchars((string) $card['foto_url'], ENT_QUOTES, 'UTF-8') ?>" alt="" width="<?= (int) $imgPx ?>" height="<?= (int) $imgPx ?>" loading="lazy">
            <?php else: ?>
                <span class="fvd-carnet-card__ph" aria-hidden="true"<?= $carnetUnSolo ? ' id="fvd-carnet-live-photo-ph"' : '' ?>>FVD</span>
            <?php endif; ?>
        </div>
        <div class="fvd-carnet-card__data">
            <div class="fvd-carnet-markers fvd-carnet-no-print" aria-label="Marcadores en tabla atletas">
                <span class="fvd-carnet-marker<?= $cEmit ? ' fvd-carnet-marker--ok' : ' fvd-carnet-marker--pend' ?>">carnet: <?= $cEmit ? '1 solicitado' : '0 pendiente' ?></span>
                <?php if (!empty($card['traspaso_marcado'])): ?>
                    <span class="fvd-carnet-marker fvd-carnet-marker--tr">traspaso: 1</span>
                <?php endif; ?>
            </div>
            <strong class="fvd-carnet-card__name"><?= htmlspecialchars((string) $card['nombre'], ENT_QUOTES, 'UTF-8') ?></strong>
            <span class="fvd-carnet-card__line">CI <?= htmlspecialchars((string) $card['cedula'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="fvd-carnet-card__line">Nº FVD <?= htmlspecialchars((string) $card['numfvd'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="fvd-carnet-card__asoc"><?= htmlspecialchars((string) $card['asociacion'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>
</div>
