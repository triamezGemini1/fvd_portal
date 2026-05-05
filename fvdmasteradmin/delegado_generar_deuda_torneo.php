<?php

declare(strict_types=1);

/**
 * POST: genera/actualiza deuda_asociaciones para la asociación en sesión y un torneo.
 */

require_once __DIR__ . '/services/AuthService.php';
require_once __DIR__ . '/includes/fvd_app_base_path.php';
require_once dirname(__DIR__) . '/config/fvd_navigation_return.php';
require_once dirname(__DIR__) . '/src/Services/DeudaAsociacionGeneratorService.php';

use FvdPortal\Services\DeudaAsociacionGeneratorService;

AuthService::ensureSession();
AuthService::requireLogin();

if (!AuthService::checkAccess([AuthService::ROLE_DELEGADO_ASOC, AuthService::ROLE_ASO_ADMIN])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Sin permisos.';
    exit;
}

$redir = AuthService::homeUrl();
$originReturn = fvd_return_from_request();
if ($originReturn === null && isset($_SERVER['HTTP_REFERER']) && is_string($_SERVER['HTTP_REFERER'])) {
    $originReturn = fvd_return_sanitize($_SERVER['HTTP_REFERER']);
}
if ($originReturn !== null) {
    $redir = $originReturn;
}
$redirWithMsg = static function (string $url, string $msg): string {
    return $url . (str_contains($url, '?') ? '&' : '?') . 'msg=' . rawurlencode($msg);
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($_POST['_action'] ?? '') !== 'generar_deuda_torneo') {
    header('Location: ' . $redir);
    exit;
}

$asoc = AuthService::idAsociacion();
if ($asoc === null || (int) $asoc <= 0) {
    header('Location: ' . $redirWithMsg($redir, 'deuda_gen_sin_asoc'));
    exit;
}

$torneo = (int) ($_POST['torneo_id'] ?? 0);
if ($torneo <= 0 && AuthService::isDelegadoAsociacion()) {
    $ctx = AuthService::delegadoTorneoContextId();
    $torneo = $ctx !== null ? (int) $ctx : 0;
}

if ($torneo <= 0) {
    header('Location: ' . $redirWithMsg($redir, 'deuda_gen_sin_torneo'));
    exit;
}

require_once __DIR__ . '/config/db.php';

if (!AuthService::canManageAsociacion((int) $asoc)) {
    header('Location: ' . $redirWithMsg($redir, 'deuda_gen_denegado'));
    exit;
}

try {
    DeudaAsociacionGeneratorService::generarParaTorneoYAsociacion(fvd_db(), $torneo, (int) $asoc);
    header('Location: ' . $redirWithMsg($redir, 'deuda_generada'));
} catch (Throwable $e) {
    AuthService::ensureSession();
    $_SESSION['fvd_delegado_deuda_err'] = $e->getMessage();
    header('Location: ' . $redirWithMsg($redir, 'deuda_gen_error'));
}
exit;
