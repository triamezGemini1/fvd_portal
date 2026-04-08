<?php

declare(strict_types=1);

/**
 * Procesa POST delegado_solicitud si coincide con $fvd_delegado_sol_tipo_requerido.
 * Requiere: AuthService, $fvd_delegado_sol_redirect (URL absoluta path), $fvd_delegado_sol_tipo_requerido.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($_POST['_action'] ?? '') !== 'delegado_solicitud') {
    return;
}

if (!isset($fvd_delegado_sol_tipo_requerido, $fvd_delegado_sol_redirect)) {
    return;
}

if (!in_array($fvd_delegado_sol_tipo_requerido, ['traspaso', 'carnet', 'afiliacion'], true)) {
    return;
}

if ((string) ($_POST['tipo'] ?? '') !== $fvd_delegado_sol_tipo_requerido) {
    return;
}

if (!AuthService::checkAccess([AuthService::ROLE_ASO_ADMIN, AuthService::ROLE_DELEGADO_ASOC])) {
    return;
}

$aidSol = AuthService::idAsociacion();
if ($aidSol === null || (int) $aidSol <= 0) {
    return;
}

$projRoot = dirname(__DIR__, 2);
require_once $projRoot . '/fvdmasteradmin/config/db.php';
require_once $projRoot . '/src/Services/DelegadoSolicitudService.php';
require_once $projRoot . '/src/Services/DelegadoTorneoVentanasService.php';

try {
    if (\FvdPortal\Services\DelegadoTorneoVentanasService::aplicaRestriccionDelegado()) {
        $ctxT = \AuthService::delegadoTorneoContextId();
        if ($ctxT === null || (int) $ctxT <= 0) {
            throw new \RuntimeException(
                'Debe seleccionar el torneo desde el panel (entrada por invitación) para enviar solicitudes según el calendario del evento.'
            );
        }
        \FvdPortal\Services\DelegadoTorneoVentanasService::assertPuedeFase1Administrativa(fvd_db(), (int) $ctxT);
    }
    \FvdPortal\Services\DelegadoSolicitudService::ensureTable(fvd_db());
    \FvdPortal\Services\DelegadoSolicitudService::crear(
        fvd_db(),
        (string) ($_POST['tipo'] ?? ''),
        (int) ($_POST['atleta_id'] ?? 0),
        isset($_POST['asociacion_destino_id']) && $_POST['asociacion_destino_id'] !== '' ? (int) $_POST['asociacion_destino_id'] : null,
        isset($_POST['nota']) ? (string) $_POST['nota'] : null
    );
    header('Location: ' . $fvd_delegado_sol_redirect . '?msg=sol_ok');
    exit;
} catch (Throwable $e) {
    AuthService::ensureSession();
    $_SESSION['fvd_delegado_sol_err'] = $e->getMessage();
    header('Location: ' . $fvd_delegado_sol_redirect);
    exit;
}
