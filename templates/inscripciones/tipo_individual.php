<?php
declare(strict_types=1);
/**
 * Modalidad individual: una nómina de uno o más atletas (cada uno se inscribe por separado en lote).
 *
 * @var int $fvd_insc_max_nomina por defecto sin límite práctico (el cupo limita)
 */
$fvd_insc_max_nomina = isset($fvd_insc_max_nomina) ? (int) $fvd_insc_max_nomina : 80;
?>
<p class="fvd-insc-hint">
    Modalidad <strong>individual</strong>. Busque por nombre o cédula, añada a la nómina y confirme.
    Puede inscribir varios atletas en un solo envío (hasta el cupo disponible).
</p>
<input type="hidden" id="fvd-insc-max-nomina" value="<?= (int) $fvd_insc_max_nomina ?>">
