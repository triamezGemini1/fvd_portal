<?php

declare(strict_types=1);

require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../config/db.php';
require_once dirname(__DIR__, 2) . '/src/Services/NotificacionService.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

AuthService::ensureSession();
AuthService::requireLogin();

if (!AuthService::checkAccess([AuthService::ROLE_ASO_ADMIN, AuthService::ROLE_DELEGADO_ASOC])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin permisos para solicitar carnets.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$payload = is_string($raw) && $raw !== '' ? json_decode($raw, true) : $_POST;
if (!is_array($payload)) {
    $payload = [];
}
$idsIn = $payload['atleta_ids'] ?? [];
if (!is_array($idsIn)) {
    $idsIn = [];
}

$ids = [];
foreach ($idsIn as $v) {
    $id = (int) $v;
    if ($id > 0) {
        $ids[$id] = $id;
    }
}
$ids = array_values($ids);
if ($ids === []) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Debe enviar atleta_ids.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$asocId = (int) (AuthService::idAsociacion() ?? 0);
if ($asocId <= 0) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin asociación en sesión.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = fvd_db();
    $ph = implode(', ', array_fill(0, count($ids), '?'));
    $params = $ids;
    array_unshift($params, $asocId);

    $sql = 'UPDATE atletas
            SET carnet = 1
            WHERE asociacion = ? AND id IN (' . $ph . ')';
    $pdo->prepare($sql)->execute($params);

    try {
        $stCol = $pdo->query("SHOW COLUMNS FROM atletas LIKE 'carnet_status'");
        if ($stCol !== false && $stCol->fetchColumn() !== false) {
            $pdo->prepare(
                'UPDATE atletas
                 SET carnet_status = \'SOLICITADO\'
                 WHERE asociacion = ? AND id IN (' . $ph . ')'
            )->execute($params);
        }
    } catch (Throwable $e) {
        // compatibilidad
    }

    try {
        $stCol2 = $pdo->query("SHOW COLUMNS FROM atletas LIKE 'carnet_solicitud_fecha'");
        if ($stCol2 !== false && $stCol2->fetchColumn() !== false) {
            $pdo->prepare(
                'UPDATE atletas
                 SET carnet_solicitud_fecha = NOW()
                 WHERE asociacion = ? AND id IN (' . $ph . ')'
            )->execute($params);
        }
    } catch (Throwable $e) {
        // compatibilidad
    }

    $asocNombre = 'Una asociación';
    try {
        $stAs = $pdo->prepare('SELECT nombre FROM asociaciones WHERE id = :id LIMIT 1');
        $stAs->execute([':id' => $asocId]);
        $nm = trim((string) ($stAs->fetchColumn() ?: ''));
        if ($nm !== '') {
            $asocNombre = $nm;
        }
    } catch (Throwable $e) {
        // fallback
    }

    $adminId = \FvdPortal\Services\NotificacionService::resolverAdminGeneralId($pdo);
    \FvdPortal\Services\NotificacionService::crear(
        $pdo,
        $adminId,
        'SOLICITUD_CARNET',
        $asocNombre . ' solicita carnets para ' . count($ids) . ' atletas.'
    );

    echo json_encode(['ok' => true, 'updated' => count($ids)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo procesar la solicitud.'], JSON_UNESCAPED_UNICODE);
}
