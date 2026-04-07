<?php
declare(strict_types=1);
/** @var int $fvd_insc_integrantes_equipo */
$n = isset($fvd_insc_integrantes_equipo) ? (int) $fvd_insc_integrantes_equipo : 4;
?>
<p class="fvd-insc-hint">
    Modalidad <strong>equipos</strong>. Debe completar la nómina con exactamente <strong><?= (int) $n ?></strong>
    integrantes (según reglamento del torneo: campo <code>pareclub</code> en el evento).
</p>
<input type="hidden" id="fvd-insc-max-nomina" value="<?= (int) $n ?>">
