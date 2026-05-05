<?php



declare(strict_types=1);



/**

 * Chips para fijar el torneo en contexto (delegado).

 * Opcional: $fvd_delegado_torneo_strip_embed = true → envoltorio compacto (p. ej. barra de filtros atletas).

 * Requiere: AuthService, url(), fvd_db(), FvdAdminService, fvd_delegado_*.

 */

if (!AuthService::isDelegadoAsociacion()) {

    return;

}

$aidStrip = AuthService::idAsociacion();

if ($aidStrip === null || (int) $aidStrip <= 0) {

    return;

}

if (!function_exists('fvd_db')) {

    require_once dirname(__DIR__, 2) . '/config/db.php';

}

if (!class_exists(FvdAdminService::class, false)) {

    require_once dirname(__DIR__, 3) . '/src/Services/FvdAdminService.php';

}

$pdoStrip = fvd_db();

$svcStrip = new FvdAdminService($pdoStrip);

$delegListaTorStrip = $svcStrip->delegadoListaTorneosParaStrip((int) $aidStrip);

$delegTorneoActivoStrip = (int) (AuthService::delegadoTorneoContextId() ?? 0);

$delegPickBaseStrip = url('fvdmasteradmin/delegado_set_torneo_context.php');

$returnPathStrip = fvd_delegado_build_return_from_current_request();

$returnEncStrip = rawurlencode($returnPathStrip);

$tieneTorneosRelStrip = $delegListaTorStrip !== [];

$fvdTorStripEmbed = !empty($fvd_delegado_torneo_strip_embed);

$tagStrip = $fvdTorStripEmbed ? 'div' : 'section';

$classStrip = 'fvd-dd-torneos-strip ' . ($fvdTorStripEmbed ? 'fvd-dd-torneos-strip--toolbar' : 'fvd-dd-torneos-strip--layout');

?>

<<?= $tagStrip ?> class="<?= htmlspecialchars($classStrip, ENT_QUOTES, 'UTF-8') ?>" aria-label="Torneo en contexto">

    <?php if ($tieneTorneosRelStrip): ?>

    <div class="fvd-dd-torneos-strip__list">

        <?php foreach ($delegListaTorStrip as $filaTorStrip): ?>

            <?php

            $tidChipS = (int) ($filaTorStrip['torneo_id'] ?? 0);

            if ($tidChipS <= 0) {

                continue;

            }

            $nomChipS = trim((string) ($filaTorStrip['torneo_nombre'] ?? ''));

            if ($nomChipS === '') {

                $nomChipS = 'Torneo #' . $tidChipS;

            }

            $gChipS = (int) ($filaTorStrip['grupo_evento_id'] ?? 0);

            $tipoChipS = (int) ($filaTorStrip['tipo'] ?? 0);

            $genChipS = $svcStrip->delegadoEtiquetaGeneroTipo($tipoChipS);

            $hrefPickS = $delegPickBaseStrip . '?torneo_id=' . $tidChipS . '&return=' . $returnEncStrip;

            $esActivoS = $delegTorneoActivoStrip > 0 && $tidChipS === $delegTorneoActivoStrip;

            ?>

            <a class="fvd-dd-torneo-chip<?= $esActivoS ? ' fvd-dd-torneo-chip--activo' : '' ?>"

               href="<?= htmlspecialchars($hrefPickS, ENT_QUOTES, 'UTF-8') ?>"

               title="<?= $esActivoS ? 'Torneo activo del panel' : 'Usar este torneo en el panel' ?>">

                <span class="fvd-dd-torneo-chip__id">Torneo #<?= $tidChipS ?></span>

                <span class="fvd-dd-torneo-chip__name"><?= htmlspecialchars($nomChipS, ENT_QUOTES, 'UTF-8') ?></span>

                <?php if ($genChipS !== ''): ?>

                    <span class="fvd-dd-torneo-chip__genero" title="Género del torneo (filtro de ramas asociadas)"><?= htmlspecialchars($genChipS, ENT_QUOTES, 'UTF-8') ?></span>

                <?php endif; ?>

                <?php if ($gChipS > 0): ?>

                    <span class="fvd-dd-torneo-chip__meta">Grupo de evento #<?= $gChipS ?></span>

                <?php endif; ?>

            </a>

        <?php endforeach; ?>

    </div>

    <?php else: ?>

    <p class="fvd-dd-torneos-strip--empty">Sin torneos con invitación activa para su asociación.</p>

    <?php endif; ?>

</<?= $tagStrip ?>>

