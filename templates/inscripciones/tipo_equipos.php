<?php
declare(strict_types=1);
/** @var int $fvd_insc_integrantes_equipo */
$n = isset($fvd_insc_integrantes_equipo) ? (int) $fvd_insc_integrantes_equipo : 4;
?>
<p class="fvd-mtf-alert__text fvd-insc-hint">
    Modalidad <strong>equipos</strong>. Indique el <strong>nombre del equipo</strong> y complete <strong><?= (int) $n ?></strong> cupos de integrante
    (reglamento del torneo). Use el buscador para asignar cada atleta a su posición en la nómina.
</p>
<input type="hidden" id="fvd-insc-max-nomina" value="<?= (int) $n ?>">
