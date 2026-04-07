<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';
require_once FVD_PROJECT_ROOT . '/src/Services/TorneoService.php';
require_once FVD_PROJECT_ROOT . '/src/Services/DelegadoTorneoNotifService.php';

use FvdPortal\Services\DelegadoTorneoNotifService;
use FvdPortal\Services\TorneoService;

fvd_admin_require_roles();

if (AuthService::isDelegadoAsociacion() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Los delegados no pueden ejecutar esta acci?n en el m?dulo de torneos.';
    exit;
}

$svc = new FvdAdminService();
$selfUrl = fvd_crud_self_url('torneos');
$fvd_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'notificar_delegados') {
    AuthService::ensureSession();
    try {
        $tidN = (int) ($_POST['torneo_id'] ?? 0);
        $out = TorneoService::enviarInvitacionMasiva(fvd_db(), $tidN);
        $_SESSION['fvd_torneo_notif_flash'] = sprintf(
            'Notificaci?n enviada: %d/%d correos. Avisos en panel web para delegados: %d registro(s). Enlace WhatsApp copiable abajo.',
            $out['emails_enviados'],
            $out['emails_total'],
            (int) ($out['web_notif_delegados'] ?? 0)
        );
        $_SESSION['fvd_torneo_wa_url'] = $out['wa_url'];
        header('Location: ' . $selfUrl . '?action=evento&id=' . $tidN . '&msg=notif_delegados');
        exit;
    } catch (Throwable $e) {
        AuthService::ensureSession();
        $_SESSION['fvd_torneo_evento_flash'] = $e->getMessage();
        $tidN = (int) ($_POST['torneo_id'] ?? 0);
        header('Location: ' . $selfUrl . '?action=evento&id=' . max(1, $tidN));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAct = (string) ($_POST['_action'] ?? '');
    if (str_starts_with($postAct, 'convocatoria_')) {
        try {
            $svc->torneosEventoRequireFvdAdmin();
            if (!$svc->torneosConvocatoriaTableExists()) {
                throw new RuntimeException('Ejecute en MySQL: fvdmasteradmin/sql/install_torneo_convocatoria_y_publicacion.sql');
            }
            $tid = (int) ($_POST['torneo_id'] ?? 0);
            if ($tid <= 0) {
                throw new InvalidArgumentException('Torneo no v?lido.');
            }
            if ($postAct === 'convocatoria_invitar') {
                $aid = (int) ($_POST['asociacion_id'] ?? 0);
                if ($aid <= 0) {
                    throw new InvalidArgumentException('Asociaci?n no v?lida.');
                }
                $svc->torneosConvocatoriaInvitar($tid, $aid);
            } elseif ($postAct === 'convocatoria_todas') {
                $svc->torneosConvocatoriaInvitarTodas($tid);
            } elseif ($postAct === 'convocatoria_invitar_seleccionadas') {
                $ids = $_POST['asociacion_id'] ?? [];
                if (!is_array($ids)) {
                    $ids = [];
                }
                $svc->torneosConvocatoriaInvitarSeleccion($tid, $ids);
            } elseif ($postAct === 'convocatoria_estado') {
                $aid = (int) ($_POST['asociacion_id'] ?? 0);
                $est = (string) ($_POST['estado_respuesta'] ?? '');
                if ($aid <= 0) {
                    throw new InvalidArgumentException('Asociaci?n no v?lida.');
                }
                $svc->torneosConvocatoriaSetRespuesta($tid, $aid, $est);
            } else {
                throw new InvalidArgumentException('Acci?n no reconocida.');
            }
            header('Location: ' . $selfUrl . '?action=evento&id=' . $tid . '&msg=ok');
            exit;
        } catch (Throwable $e) {
            AuthService::ensureSession();
            $_SESSION['fvd_torneo_evento_flash'] = $e->getMessage();
            error_log('[admin/torneos convocatoria] ' . $e->getMessage());
            $redirT = (int) ($_POST['torneo_id'] ?? 0);
            header('Location: ' . $selfUrl . '?action=evento&id=' . max(1, $redirT));
            exit;
        }
    }
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    $svc->torneosDelete((int) $_GET['id']);
    header('Location: ' . $selfUrl);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'save') {
    try {
        $tid = isset($_POST['torneo']) && $_POST['torneo'] !== '' ? (int) $_POST['torneo'] : null;
        $savedId = $svc->torneosSave($tid, $_POST, $_FILES);
        $wasNew = $tid === null;
        if ($wasNew && AuthService::role() === AuthService::ROLE_FVD_ADMIN) {
            header('Location: ' . $selfUrl . '?action=evento&id=' . $savedId . '&msg=torneo_creado_invitaciones');
            exit;
        }
        if (!empty($_POST['invitar_todas_al_guardar']) && AuthService::role() === AuthService::ROLE_FVD_ADMIN) {
            $msg = $svc->torneosConvocatoriaTableExists() ? 'invitadas' : 'sin_tabla_convocatoria';
            header('Location: ' . $selfUrl . '?action=evento&id=' . $savedId . '&msg=' . $msg);
            exit;
        }
        header('Location: ' . $selfUrl);
        exit;
    } catch (Throwable $e) {
        $fvd_error = $e->getMessage();
        error_log('[admin/torneos] ' . $fvd_error);
    }
}

$fvd_page_title = 'Torneos';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

if (AuthService::isDelegadoAsociacion() && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $ctx = AuthService::delegadoTorneoContextId();
    if ($ctx !== null && $ctx > 0) {
        if ($action !== 'evento' || $id === null || (int) $id !== $ctx) {
            header('Location: ' . $selfUrl . '?action=evento&id=' . $ctx);
            exit;
        }
    } elseif ($action !== 'evento' || $id === null || $id <= 0) {
        $base = rtrim((string) env('APP_BASE_PATH', ''), '/');
        header('Location: ' . $base . '/fvdmasteradmin/index.php');
        exit;
    }
}

if ($action === 'evento_status_json' && $id !== null && $id > 0) {
    if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Solo administrador FVD.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    if ($svc->torneosFind($id) === null) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Torneo no encontrado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!$svc->torneosConvocatoriaTableExists()) {
        echo json_encode(['ok' => false, 'error' => 'Sin tabla de convocatorias.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $inscOk = $svc->torneosInscripcionTorneoTableExists();
    $filas = $inscOk ? $svc->torneosConvocatoriaFilas($id) : $svc->torneosConvocatoriaFilasSinInscripciones($id);
    $asociaciones = [];
    foreach ($filas as $r) {
        $asociaciones[] = [
            'asociacion_id' => (int) ($r['asociacion_id'] ?? 0),
            'invitado_en' => $r['invitado_en'] !== null && $r['invitado_en'] !== '' ? (string) $r['invitado_en'] : null,
            'estado_respuesta' => (string) ($r['estado_respuesta'] ?? 'pendiente'),
            'num_inscritos' => (int) ($r['num_inscritos'] ?? 0),
        ];
    }
    $conInv = 0;
    $sumInsc = 0;
    $asocConInscritos = 0;
    foreach ($asociaciones as $a) {
        if ($a['invitado_en'] !== null) {
            ++$conInv;
        }
        $sumInsc += $a['num_inscritos'];
        if ($a['num_inscritos'] > 0) {
            ++$asocConInscritos;
        }
    }
    echo json_encode([
        'ok' => true,
        'torneo_id' => $id,
        'inscripciones_tabla_ok' => $inscOk,
        'asociaciones' => $asociaciones,
        'resumen' => [
            'total_asociaciones' => count($asociaciones),
            'con_invitacion_registrada' => $conInv,
            'asociaciones_con_inscritos' => $asocConInscritos,
            'total_inscritos_torneo' => $sumInsc,
        ],
        'server_time' => date('c'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'tarjetas_zip' && $id !== null && $id > 0) {
    AuthService::ensureSession();
    AuthService::requireLogin();
    if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Solo administrador FVD.';
        exit;
    }
    if ($svc->torneosFind($id) === null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Torneo no encontrado.';
        exit;
    }
    if (!extension_loaded('zip')) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'La extensi?n ZIP de PHP no est? habilitada.';
        exit;
    }
    $pdo = fvd_db();
    DelegadoTorneoNotifService::ensureTable($pdo);
    DelegadoTorneoNotifService::ensureTokenColumns($pdo);
    $st = $pdo->prepare(
        'SELECT n.id, n.tarjeta_pdf, COALESCE(n.asociacion_id, d.asociacion_id) AS aid, a.nombre AS asoc_nombre
         FROM fvd_delegado_notif_torneo n
         INNER JOIN delegados d ON d.id = n.delegado_id
         LEFT JOIN asociaciones a ON a.id = COALESCE(n.asociacion_id, d.asociacion_id)
         WHERE n.torneo_id = :t AND n.tarjeta_pdf IS NOT NULL AND TRIM(n.tarjeta_pdf) <> \'\''
    );
    $st->execute([':t' => $id]);
    $filas = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $uploadDir = FVD_PROJECT_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
    $uploadDirReal = realpath($uploadDir);
    if ($uploadDirReal === false || !is_dir($uploadDirReal)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Carpeta uploads no disponible.';
        exit;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'fvd_tar_');
    if ($tmp === false) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'No se pudo crear archivo temporal.';
        exit;
    }
    @unlink($tmp);
    $zipPath = $tmp . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'No se pudo crear el ZIP.';
        exit;
    }
    $n = 0;
    foreach ($filas as $f) {
        $fn = basename((string) ($f['tarjeta_pdf'] ?? ''));
        if ($fn === '' || str_contains($fn, '..')) {
            continue;
        }
        $path = $uploadDirReal . DIRECTORY_SEPARATOR . $fn;
        $pathReal = realpath($path);
        if ($pathReal === false || !is_file($pathReal) || !is_readable($pathReal)) {
            continue;
        }
        $baseNorm = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $uploadDirReal), DIRECTORY_SEPARATOR);
        $pathNorm = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $pathReal), DIRECTORY_SEPARATOR);
        if ($baseNorm === '' || !str_starts_with($pathNorm, $baseNorm . DIRECTORY_SEPARATOR)) {
            continue;
        }
        $asoc = trim((string) ($f['asoc_nombre'] ?? 'asociacion'));
        $safe = preg_replace('/[^a-zA-Z0-9\x{00C0}-\x{024F}\s_-]+/u', '_', $asoc);
        $safe = trim(preg_replace('/_+/', '_', $safe) ?? '', '_');
        if ($safe === '') {
            $safe = 'asoc_' . (int) ($f['aid'] ?? 0);
        }
        $entry = 'tarjeta_' . $safe . '_n' . (int) ($f['id'] ?? 0) . '.pdf';
        $zip->addFile($pathReal, $entry);
        ++$n;
    }
    $zip->close();
    if ($n === 0) {
        @unlink($zipPath);
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'No hay tarjetas PDF generadas para este torneo. Instale Dompdf (composer) y cree el torneo de nuevo o use ?Notificar delegados? para regenerarlas.';
        exit;
    }
    $bin = @file_get_contents($zipPath);
    @unlink($zipPath);
    if ($bin === false || $bin === '') {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Error al leer el ZIP.';
        exit;
    }
    $downloadName = 'tarjetas_torneo_' . $id . '.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . (string) strlen($bin));
    header('Cache-Control: no-store, must-revalidate');
    echo $bin;
    exit;
}

if ($action === 'evento' && $id !== null && $id > 0) {
    AuthService::ensureSession();
    $pdoEv = fvd_db();
    DelegadoTorneoNotifService::ensureTable($pdoEv);

    if (AuthService::isDelegadoAsociacion()) {
        $delegadoId = (int) AuthService::userId();
        if (!DelegadoTorneoNotifService::delegadoTieneNotificacionTorneo($pdoEv, $delegadoId, (int) $id)) {
            http_response_code(403);
            exit('No tiene una invitaci?n web registrada para este torneo.');
        }
        AuthService::setDelegadoTorneoContext((int) $id);
        $eventoTorneo = $svc->torneosFind($id);
        if ($eventoTorneo === null) {
            http_response_code(404);
            $fvd_page_title = 'Torneo no encontrado';
        } else {
            $fvd_page_title = 'Torneo ? administraci?n (delegado)';
        }
        $convocatoriaOk = $svc->torneosConvocatoriaTableExists();
        $inscOk = $svc->torneosInscripcionTorneoTableExists();
        $convocatoriaFilas = $convocatoriaOk
            ? ($inscOk ? $svc->torneosConvocatoriaFilas($id) : $svc->torneosConvocatoriaFilasSinInscripciones($id))
            : [];
        $miAsoc = AuthService::idAsociacion();
        $convocatoriaMiAsoc = [];
        foreach ($convocatoriaFilas as $cf) {
            if ($miAsoc !== null && (int) ($cf['asociacion_id'] ?? 0) === (int) $miAsoc) {
                $convocatoriaMiAsoc[] = $cf;
            }
        }
        $stN = $pdoEv->prepare(
            'SELECT id, invitacion_archivo FROM fvd_delegado_notif_torneo WHERE delegado_id = :d AND torneo_id = :t LIMIT 1'
        );
        $stN->execute([':d' => $delegadoId, ':t' => (int) $id]);
        $notifRow = $stN->fetch(PDO::FETCH_ASSOC) ?: [];
        $fvd_delegado_notif_id = (int) ($notifRow['id'] ?? 0);
        $fvd_delegado_invitacion_archivo = isset($notifRow['invitacion_archivo']) ? (string) $notifRow['invitacion_archivo'] : '';
        $appBase = rtrim((string) env('APP_BASE_PATH', ''), '/');
        $fvd_url_salir_torneo_ctx = $appBase . '/fvdmasteradmin/delegado_salir_torneo.php';
        $fvd_url_pdf_invitacion = $appBase . '/fvdmasteradmin/delegado_invitacion_pdf.php?notif_id=' . $fvd_delegado_notif_id;
        require FVD_MASTER_ROOT . '/includes/layout_header.php';
        include __DIR__ . '/evento_delegado.view.php';
        require FVD_MASTER_ROOT . '/includes/layout_footer.php';
        exit;
    }

    if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
        http_response_code(403);
        exit('Solo el administrador general FVD puede abrir el panel de evento.');
    }
    if (!empty($_SESSION['fvd_torneo_evento_flash'])) {
        $fvd_error = (string) $_SESSION['fvd_torneo_evento_flash'];
        unset($_SESSION['fvd_torneo_evento_flash']);
    }
    $torneoNotifFlash = '';
    $torneoWaUrl = '';
    if (!empty($_SESSION['fvd_torneo_notif_flash'])) {
        $torneoNotifFlash = (string) $_SESSION['fvd_torneo_notif_flash'];
        unset($_SESSION['fvd_torneo_notif_flash']);
    }
    if (!empty($_SESSION['fvd_torneo_wa_url'])) {
        $torneoWaUrl = (string) $_SESSION['fvd_torneo_wa_url'];
        unset($_SESSION['fvd_torneo_wa_url']);
    }
    $eventoTorneo = $svc->torneosFind($id);
    if ($eventoTorneo === null) {
        http_response_code(404);
        $fvd_page_title = 'Torneo no encontrado';
    } else {
        $fvd_page_title = 'Administraci?n del torneo';
    }
    $convocatoriaOk = $svc->torneosConvocatoriaTableExists();
    $inscOk = $svc->torneosInscripcionTorneoTableExists();
    $convocatoriaFilas = $convocatoriaOk
        ? ($inscOk ? $svc->torneosConvocatoriaFilas($id) : $svc->torneosConvocatoriaFilasSinInscripciones($id))
        : [];
    $torneoPanelStats = $eventoTorneo !== null ? $svc->torneosPanelEstadisticas($id) : [];
    require FVD_MASTER_ROOT . '/includes/layout_header.php';
    include __DIR__ . '/evento.view.php';
    require FVD_MASTER_ROOT . '/includes/layout_footer.php';
    exit;
}

if ($action === 'form') {
    $row = $svc->torneosFind($id);
    $asociaciones = $svc->torneosListAsociacionesForSelect();
    if ($id !== null && $row === null) {
        http_response_code(404);
        $fvd_page_title = 'No encontrado';
    }
    require FVD_MASTER_ROOT . '/includes/layout_header.php';
    include __DIR__ . '/form.view.php';
    require FVD_MASTER_ROOT . '/includes/layout_footer.php';
    exit;
}

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$result = $svc->torneosPaginateList($page, 15, $q);

require FVD_MASTER_ROOT . '/includes/layout_header.php';
include __DIR__ . '/list.view.php';
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
