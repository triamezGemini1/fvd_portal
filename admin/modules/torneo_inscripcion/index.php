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
$fvd_campeonato_grupo = 0;
$fvd_error_campeonato = '';
if ($tablasOk && $asocId > 0) {
    $torneosAbiertos = $svc->torneosAbiertosInscripcionParaAsociacion($asocId);
}

if ($esDelegadoBandera && $tablasOk && $asocId > 0) {
    $campGet = isset($_GET['campeonato_id']) ? (int) $_GET['campeonato_id'] : 0;
    if ($campGet <= 0) {
        $sessG = AuthService::delegadoCampeonatoGrupoId();
        $campGet = $sessG !== null && $sessG > 0 ? $sessG : 0;
    }
    if ($campGet <= 0) {
        $fvd_error_campeonato = 'Debe indicar el campeonato (parámetro obligatorio campeonato_id en la URL). Use el ID de grupo de evento o el ID de uno de los torneos del campeonato.';
    } else {
        $grupoRes = $svc->resolverGrupoDesdeCampeonatoParam($campGet);
        if ($grupoRes === null || $grupoRes <= 0) {
            $fvd_error_campeonato = 'No se pudo resolver el campeonato. Compruebe que exista grupo_evento_id en torneos y que el ID sea válido.';
        } else {
            AuthService::setDelegadoCampeonatoGrupo($grupoRes);
            $fvd_campeonato_grupo = $grupoRes;
            $fvdDelegadoGrupoTorneos = $svc->torneosPorGrupoCampeonato($asocId, $grupoRes);
            if ($fvdDelegadoGrupoTorneos === []) {
                $fvd_error_campeonato = 'No hay torneos de este campeonato con convocatoria para su asociación.';
            } else {
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
                    if (!$enAbiertos) {
                        $firstBad = (int) ($fvdDelegadoGrupoTorneos[0]['torneo'] ?? 0);
                        header('Location: ' . $selfUrl . '?' . http_build_query([
                            'campeonato_id' => $grupoRes,
                            'torneo_id'     => $firstBad > 0 ? $firstBad : $torneoSel,
                        ]));
                        exit;
                    }
                }
                if ($torneoSel > 0 && isset($allowedIds[$torneoSel])) {
                    AuthService::setDelegadoTorneoContext($torneoSel);
                }
                if ($torneoSel <= 0) {
                    $first = (int) ($fvdDelegadoGrupoTorneos[0]['torneo'] ?? 0);
                    if ($first > 0) {
                        header('Location: ' . $selfUrl . '?' . http_build_query([
                            'campeonato_id' => $grupoRes,
                            'torneo_id'     => $first,
                        ]));
                        exit;
                    }
                }
            }
        }
    }
}

foreach ($fvdDelegadoGrupoTorneos as &$fvd_gt_row) {
    $fvd_gt_row['nombre_corta'] = $svc->nombreCortaTorneoCampeonato((string) ($fvd_gt_row['nombre'] ?? ''));
}
unset($fvd_gt_row);

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

$fvdSitioDisponibles = [];
$fvdSitioInscritos = [];
if ($tablasOk && $torneoSel > 0 && $asocId > 0) {
    require_once __DIR__ . '/sitio_arrays.inc.php';
    $sitio = fvd_torneo_inscripcion_build_sitio_arrays($svc, fvd_db(), $torneoSel, $asocId, $tieneColsBandera);
    $fvdSitioDisponibles = $sitio['fvdSitioDisponibles'];
    $fvdSitioInscritos = $sitio['fvdSitioInscritos'];
}

$fvdSitioNuevoAtletaUrl = rtrim($appBase, '/') . '/modules/atletas/index.php?action=form';
$fvd_campeonato_q = ($esDelegadoBandera && $fvd_campeonato_grupo > 0) ? ('&campeonato_id=' . $fvd_campeonato_grupo) : '';

require FVD_MASTER_ROOT . '/includes/layout_header.php';
include __DIR__ . '/inscribir.view.php';
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
