<?php
declare(strict_types=1);
/**
 * Modalidad individual: una nómina de uno o más atletas (cada uno se inscribe por separado en lote).
 *
 * @var int $fvd_insc_max_nomina por defecto sin límite práctico (el cupo limita)
 */
$fvd_insc_max_nomina = isset($fvd_insc_max_nomina) ? (int) $fvd_insc_max_nomina : 80;
?>
<p class="fvd-mtf-alert__text fvd-insc-hint">
    Modalidad <strong>individual</strong>. El nombre del torneo se muestra tal como está registrado (sin formato impuesto).
    Busque por nombre o cédula, añada a la nómina y confirme; puede enviar varios atletas en un solo lote hasta el cupo disponible.
</p>
<input type="hidden" id="fvd-insc-max-nomina" value="<?= (int) $fvd_insc_max_nomina ?>">
