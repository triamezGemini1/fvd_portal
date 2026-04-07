<?php

declare(strict_types=1);

/**
 * API JSON: búsqueda paginada de atletas del club, metadatos de torneo e inscripción por modalidad.
 */

require_once __DIR__ . '/services/AuthService.php';
require_once __DIR__ . '/config/db.php';
require_once dirname(__DIR__) . '/src/Services/FvdAdminService.php';
require_once dirname(__DIR__) . '/src/Services/InscripcionService.php';

use FvdPortal\Services\InscripcionService;

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

AuthService::ensureSession();
AuthService::requireLogin();

if (!AuthService::checkAccess([
    AuthService::ROLE_ASO_ADMIN,
    AuthService::ROLE_DELEGADO_ASOC,
    AuthService::ROLE_FVD_ADMIN,
])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin permisos para esta API.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$svc = new FvdAdminService();
$pdo = fvd_db();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
/** @var array<string, mixed>|null */
$postData = null;
if ($method === 'POST') {
    $rawIn = file_get_contents('php://input');
    $postData = is_string($rawIn) && $rawIn !== '' ? json_decode($rawIn, true) : null;
    if (!is_array($postData)) {
        $postData = $_POST;
    }
}

$asoc = AuthService::idAsociacion();
if ($asoc === null || $asoc <= 0) {
    $asoc = 0;
}
if ($asoc <= 0 && AuthService::isSuperAdmin()) {
    $asoc = (int) ($_GET['asociacion_id'] ?? 0);
    if ($asoc <= 0 && is_array($postData)) {
        $asoc = (int) ($postData['asociacion_id'] ?? 0);
    }
}

if ($asoc <= 0) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin asociación en sesión o parámetro asociacion_id (FVD).'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!AuthService::canManageAsociacion($asoc)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin permiso para esta asociación.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($method === 'GET' && ($_GET['action'] ?? '') === 'buscar') {
        $q = isset($_GET['q']) ? (string) $_GET['q'] : '';
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? max(1, min(24, (int) $_GET['per_page'])) : 8;
        $tidBus = isset($_GET['torneo_id']) ? (int) $_GET['torneo_id'] : 0;
        $omitirTor = (AuthService::isDelegadoAsociacion() && $tidBus > 0) ? $tidBus : null;
        $pack = $svc->atletasBuscarInscripcionPaginado($asoc, $q, $page, $perPage, $omitirTor);
        echo json_encode(['ok' => true] + $pack, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'GET' && ($_GET['action'] ?? '') === 'torneo_meta') {
        $tid = isset($_GET['torneo_id']) ? (int) $_GET['torneo_id'] : 0;
        if ($tid <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'torneo_id requerido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (AuthService::isDelegadoAsociacion()) {
            $ctx = AuthService::delegadoTorneoContextId();
            if ($ctx !== null && $ctx > 0 && $ctx !== $tid) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'En modo torneo solo el evento activo.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        $meta = $svc->torneoInscripcionMetaParaVista($tid, $asoc);
        if ($meta === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Torneo no encontrado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode(['ok' => true, 'meta' => $meta], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST') {
        $data = is_array($postData) ? $postData : [];
        $asocPost = (int) ($data['asociacion_id'] ?? 0);
        if (AuthService::isSuperAdmin() && $asocPost > 0) {
            $asoc = $asocPost;
            if (!AuthService::canManageAsociacion($asoc)) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Sin permiso para esta asociación.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        $action = (string) ($data['action'] ?? '');
        $torneoId = (int) ($data['torneo_id'] ?? 0);
        if ($torneoId <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'torneo_id requerido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (AuthService::isDelegadoAsociacion()) {
            $ctx = AuthService::delegadoTorneoContextId();
            if ($ctx !== null && $ctx > 0 && $ctx !== $torneoId) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'En modo torneo solo puede inscribir en el evento activo.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        if ($action === 'inscribir' && isset($data['atleta_id']) && !isset($data['atleta_ids'])) {
            $data['atleta_ids'] = [(int) $data['atleta_id']];
            $data['tipo'] = 'individual';
        }

        if ($action === 'inscribir' || $action === 'inscribir_lote') {
            $tipo = strtolower(trim((string) ($data['tipo'] ?? 'individual')));
            $ids = $data['atleta_ids'] ?? [];
            if (!is_array($ids)) {
                $ids = [];
            }
            $ids = array_values(array_filter(array_map('intval', $ids), static function (int $x): bool {
                return $x > 0;
            }));

            $soloBandera = AuthService::isDelegadoAsociacion();

            if ($action === 'inscribir_lote' || ($tipo === 'individual' && count($ids) > 1)) {
                $n = $soloBandera
                    ? InscripcionService::registrarMultiplesIndividualesBandera($pdo, $torneoId, $asoc, $ids)
                    : InscripcionService::registrarMultiplesIndividuales($pdo, $torneoId, $asoc, $ids);
                echo json_encode(['ok' => true, 'inscritos' => $n, 'tipo' => 'individual_lote', 'modo' => $soloBandera ? 'bandera' : 'tabla'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $n = $soloBandera
                ? InscripcionService::registrarInscripcionBandera($pdo, $torneoId, $asoc, $tipo, $ids)
                : InscripcionService::registrarInscripcion($pdo, $torneoId, $asoc, $tipo, $ids);
            echo json_encode(['ok' => true, 'inscritos' => $n, 'tipo' => $tipo, 'modo' => $soloBandera ? 'bandera' : 'tabla'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'retirar') {
            if (!AuthService::isDelegadoAsociacion()) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Solo delegados usan retiro por bandera desde esta API.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $aid = (int) ($data['atleta_id'] ?? 0);
            if ($aid <= 0) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'atleta_id requerido.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $ok = InscripcionService::retirarInscripcionBandera($pdo, $torneoId, $asoc, $aid);
            echo json_encode(['ok' => true, 'retirado' => $ok], JSON_UNESCAPED_UNICODE);
            exit;
        }

        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Acción no válida.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    error_log('[delegado_inscripcion_api] ' . $e->getMessage());
}
