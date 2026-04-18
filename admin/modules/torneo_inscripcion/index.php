<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';

fvd_admin_require_roles();

$svc = new FvdAdminService();
$selfUrl = fvd_master_module_url('torneo_inscripcion/index.php');
$fvd_page_title = AuthService::isDelegadoAsociacion() ? 'Inscripciones al torneo' : 'Inscripción';
$fvd_error = '';
$fvd_ok = '';

$asocId = AuthService::idAsociacion();
$esFvd = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
if ($esFvd) {
    $asocId = isset($_GET['asociacion_id']) ? (int) $_GET['asociacion_id'] : (isset($_POST['asociacion_id']) ? (int) $_POST['asociacion_id'] : 0);
}

$esDelegadoBandera = AuthService::isDelegadoAsociacion();
$tablasOk = $svc->torneosConvocatoriaTableExists()
    && ($esDelegadoBandera
        ? $svc->atletasTieneColumnasInscripcionTorneo()
        : $svc->torneosInscripcionTorneoTableExists());

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'inscribir_atletas') {
    try {
        $tor = (int) ($_POST['torneo_id'] ?? 0);
        $ids = isset($_POST['atleta_id']) && is_array($_POST['atleta_id']) ? array_map('intval', $_POST['atleta_id']) : [];
        if ($tor <= 0 || $asocId <= 0) {
            throw new InvalidArgumentException('Seleccione torneo' . ($esFvd ? ' y asociación' : '') . '.');
        }
        if (!$tablasOk) {
            throw new RuntimeException(
                $esDelegadoBandera
                    ? 'Faltan requisitos: convocatoria y columnas inscripcion/torneo_id en atletas.'
                    : 'Faltan tablas: ejecute install_inscripcion_torneo.sql y install_torneo_convocatoria_y_publicacion.sql'
            );
        }
        if ($esDelegadoBandera && !$svc->delegadoTorneoInscripcionPermitido($asocId, $tor)) {
            throw new InvalidArgumentException('Torneo no permitido para su evento actual.');
        }
        if ($esDelegadoBandera) {
            $n = \FvdPortal\Services\InscripcionService::registrarMultiplesIndividualesBandera(fvd_db(), $tor, $asocId, $ids);
        } else {
            $n = $svc->torneosInscribirAtletas($tor, $asocId, $ids);
        }
        $fvd_ok = $n > 0 ? "Se inscribieron {$n} atleta(s)." : 'No hubo inscripciones nuevas (p. ej. ya inscritos o cédula inválida).';
    } catch (Throwable $e) {
        $fvd_error = $e->getMessage();
        error_log('[torneo_inscripcion] ' . $fvd_error);
    }
}

$torneoSel = isset($_GET['torneo_id']) ? (int) $_GET['torneo_id'] : (isset($_POST['torneo_id']) ? (int) $_POST['torneo_id'] : 0);

$torneosAbiertos = [];
$asociacionesSelect = [];
$fvdDelegadoGrupoTorneos = [];
if ($tablasOk && $asocId > 0) {
    $torneosAbiertos = $svc->torneosAbiertosInscripcionParaAsociacion($asocId);
}

if (AuthService::isDelegadoAsociacion()) {
    $ctxTor = AuthService::delegadoTorneoContextId();
    if ($ctxTor !== null && $ctxTor > 0 && $tablasOk && $asocId > 0) {
        $fvdDelegadoGrupoTorneos = $svc->torneosDelegadoGrupoInscripcion($asocId, $ctxTor);
        $allowedIds = [];
        foreach ($fvdDelegadoGrupoTorneos as $r) {
            $tid = (int) ($r['torneo'] ?? 0);
            if ($tid > 0) {
                $allowedIds[$tid] = true;
            }
        }
        if ($torneoSel > 0 && $allowedIds !== [] && !isset($allowedIds[$torneoSel])) {
            $enAbiertos = false;
            foreach ($torneosAbiertos as $ta) {
                if ((int) ($ta['torneo'] ?? 0) === $torneoSel) {
                    $enAbiertos = true;
                    break;
                }
            }
            if (!$enAbiertos && $ctxTor !== null && $ctxTor > 0) {
                header('Location: ' . $selfUrl . '?torneo_id=' . $ctxTor);
                exit;
            }
        }
        if ($torneoSel > 0 && isset($allowedIds[$torneoSel])) {
            AuthService::setDelegadoTorneoContext($torneoSel);
        }
        if ($torneoSel <= 0 && $fvdDelegadoGrupoTorneos !== []) {
            $first = (int) ($fvdDelegadoGrupoTorneos[0]['torneo'] ?? 0);
            if ($first > 0) {
                header('Location: ' . $selfUrl . '?torneo_id=' . $first);
                exit;
            }
        }
    }
}

if (!$esDelegadoBandera && $tablasOk && $asocId > 0 && $torneoSel <= 0 && $torneosAbiertos !== []) {
    $firstTid = (int) ($torneosAbiertos[0]['torneo'] ?? 0);
    if ($firstTid > 0) {
        $params = ['torneo_id' => $firstTid];
        if ($esFvd) {
            $params['asociacion_id'] = $asocId;
        }
        header('Location: ' . $selfUrl . '?' . http_build_query($params));
        exit;
    }
}

if ($esFvd && $tablasOk) {
    $qAs = $svc->asociacionesPaginateList(1, 500, '');
    $asociacionesSelect = $qAs['rows'] ?? [];
}

$atletasDisp = ($tablasOk && $torneoSel > 0 && $asocId > 0)
    ? $svc->torneosAtletasInscribibles($torneoSel, $asocId)
    : [];

$appBase = rtrim((string) env('APP_BASE_PATH', ''), '/');
$inscripcionApiUrl = $appBase . '/fvdmasteradmin/delegado_inscripcion_api.php';
$uploadsPublicBase = url('crud_atletas/uploads/');
$torneoMeta = ($tablasOk && $torneoSel > 0 && $asocId > 0)
    ? $svc->torneoInscripcionMetaParaVista($torneoSel, $asocId, $esDelegadoBandera ? true : null)
    : null;

if ($torneoMeta !== null && ($torneoMeta['torneo']['nombre'] ?? '') !== '') {
    $fvd_page_title = (string) $torneoMeta['torneo']['nombre'];
}

$fvd_inscripcion_bandera_modo = $esDelegadoBandera;
$tieneColsBandera = $svc->atletasTieneColumnasInscripcionTorneo();
$inscritosBandera = ($tablasOk && $torneoSel > 0 && $asocId > 0 && $tieneColsBandera)
    ? \FvdPortal\Services\InscripcionService::listarInscritosBandera(fvd_db(), $torneoSel, $asocId)
    : [];

$fvdSitioDisponibles = [];
$fvdSitioInscritos = [];
if ($tablasOk && $torneoSel > 0 && $asocId > 0) {
    foreach ($atletasDisp as $row) {
        $fvdSitioDisponibles[] = [
            'atleta_id' => (int) ($row['id'] ?? 0),
            'nombre' => (string) ($row['nombre'] ?? ''),
            'cedula' => (string) ($row['cedula'] ?? ''),
            'numfvd' => (int) ($row['numfvd'] ?? 0),
            'cedula_num' => (int) ($row['_cedula_num'] ?? 0),
        ];
    }

    $banderaPorAtletaId = [];
    $banderaCedulaIndiv = [];
    foreach ($inscritosBandera as $ib) {
        $aidB = (int) ($ib['id'] ?? 0);
        $cedN = (int) preg_replace('/\D+/', '', (string) ($ib['cedula'] ?? ''));
        if ($aidB > 0) {
            $banderaPorAtletaId[$aidB] = true;
        }
        if ($cedN > 0) {
            $banderaCedulaIndiv[$cedN] = true;
        }
        $fvdSitioInscritos[] = [
            'atleta_id' => $aidB,
            'nombre' => (string) ($ib['nombre'] ?? ''),
            'cedula' => (string) ($ib['cedula'] ?? ''),
            'numfvd' => (int) ($ib['numfvd'] ?? 0),
            'cedula_num' => $cedN,
            'equipo' => 0,
            'retirar_mode' => $aidB > 0 ? 'bandera' : '0',
        ];
    }

    if ($svc->torneosInscripcionTorneoTableExists()) {
        foreach ($svc->torneosInscritosInscripcionTorneo($torneoSel, $asocId) as $r) {
            $aidT = isset($r['atleta_id']) && $r['atleta_id'] !== null ? (int) $r['atleta_id'] : 0;
            $cedN = (int) preg_replace('/\D+/', '', (string) ($r['cedula'] ?? ''));
            $eq = (int) ($r['equipo'] ?? 0);
            if ($aidT > 0 && isset($banderaPorAtletaId[$aidT])) {
                continue;
            }
            if ($eq === 0 && $cedN > 0 && isset($banderaCedulaIndiv[$cedN])) {
                continue;
            }
            $rm = $eq === 0 && $cedN > 0 ? 'tabla' : '0';
            $fvdSitioInscritos[] = [
                'atleta_id' => $aidT,
                'nombre' => (string) ($r['nombre'] ?? ''),
                'cedula' => (string) ($r['cedula'] ?? ''),
                'numfvd' => (int) ($r['numfvd'] ?? 0),
                'cedula_num' => $cedN,
                'equipo' => $eq,
                'retirar_mode' => $rm,
            ];
        }
    }

    usort(
        $fvdSitioInscritos,
        static function (array $a, array $b): int {
            return strcasecmp((string) ($a['nombre'] ?? ''), (string) ($b['nombre'] ?? ''));
        }
    );
}

$fvdSitioNuevoAtletaUrl = rtrim($appBase, '/') . '/modules/atletas/index.php?action=form';

require FVD_MASTER_ROOT . '/includes/layout_header.php';
include __DIR__ . '/inscribir.view.php';
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
