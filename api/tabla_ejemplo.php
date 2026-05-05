<?php

declare(strict_types=1);

/**
 * Datos de ejemplo para demostrar carga asíncrona (sin tocar la BD).
 */
require_once __DIR__ . '/_bootstrap.php';

$rows = [
    ['id' => 1, 'concepto' => 'Inscripción torneo', 'monto' => 45.00, 'estado' => 'pagado'],
    ['id' => 2, 'concepto' => 'Carnet atleta', 'monto' => 12.50, 'estado' => 'pendiente'],
    ['id' => 3, 'concepto' => 'Afiliación anual', 'monto' => 30.00, 'estado' => 'pagado'],
];

fvd_api_json_out([
    'ok' => true,
    'rows' => $rows,
    'meta' => ['fuente' => 'ejemplo_estático'],
]);
