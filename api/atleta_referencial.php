<?php

declare(strict_types=1);

/**
 * Consulta al referencial nacional de personas (32M / RNE) por cédula.
 * Misma lógica que {@see PersonaReferencialService}; expuesto como endpoint dedicado para el formulario de afiliado.
 */

require_once dirname(__DIR__) . '/admin/_init.php';
fvd_admin_require_roles();

require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/src/Services/PersonaReferencialService.php';

use FvdPortal\Services\PersonaReferencialService;

$cedula = isset($_GET['cedula']) ? trim((string) $_GET['cedula']) : '';

try {
    $row = PersonaReferencialService::lookupByCedula($cedula);
    fvd_api_json_out([
        'ok'    => true,
        'found' => $row !== null,
        'data'  => $row,
    ]);
} catch (Throwable $e) {
    error_log('[atleta_referencial] ' . $e->getMessage());
    fvd_api_json_out(['ok' => false, 'error' => 'Error al consultar el referencial.'], 500);
}
