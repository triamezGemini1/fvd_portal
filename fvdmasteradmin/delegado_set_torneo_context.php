<?php

declare(strict_types=1);

/**
 * Fija torneo (y grupo) en sesión para el delegado y redirige a la pantalla actual u origen seguro.
 */

require_once __DIR__ . '/services/AuthService.php';
require_once __DIR__ . '/config/db.php';
require_once dirname(__DIR__) . '/config/paths.php';
require_once __DIR__ . '/includes/fvd_delegado_internal_nav.php';
require_once dirname(__DIR__) . '/src/Services/FvdAdminService.php';

AuthService::ensureSession();
AuthService::requireLogin();
if (!AuthService::isDelegadoAsociacion()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Solo delegados de asociación pueden fijar el contexto de torneo.';
    exit;
}

$asocId = (int) (AuthService::idAsociacion() ?? 0);
$tid = isset($_GET['torneo_id']) ? (int) $_GET['torneo_id'] : 0;
$fallback = url('fvdmasteradmin/delegado_dashboard_new.php');

if ($tid <= 0 || $asocId <= 0) {
    header('Location: ' . $fallback, true, 302);
    exit;
}

$svc = new FvdAdminService();
if (!$svc->delegadoPuedeFijarTorneoContext($asocId, $tid)) {
    header('Location: ' . $fallback, true, 302);
    exit;
}

AuthService::setDelegadoTorneoContext($tid);
$gSet = 0;
try {
    $pdo = fvd_db();
    $stg = $pdo->prepare('SELECT grupo_evento_id FROM torneosact WHERE torneo = :t LIMIT 1');
    $stg->execute([':t' => $tid]);
    $rawG = $stg->fetchColumn();
    if ($rawG !== false && $rawG !== null && (int) $rawG > 0) {
        $gSet = (int) $rawG;
    }
} catch (Throwable $e) {
    /* sin grupo_evento_id */
}
if ($gSet > 0) {
    AuthService::setDelegadoCampeonatoGrupo($gSet);
}

$retRaw = isset($_GET['return']) ? (string) $_GET['return'] : '';
$retPath = fvd_delegado_safe_return_path($retRaw);
if ($retPath === '') {
    header('Location: ' . $fallback, true, 302);
    exit;
}
if ($gSet > 0) {
    $retPath = fvd_delegado_merge_campeonato_into_path($retPath, $gSet);
}

header('Location: ' . fvd_delegado_absolute_redirect_url($retPath), true, 302);
exit;
