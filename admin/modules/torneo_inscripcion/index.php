<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';

fvd_admin_require_roles();

$svc = new FvdAdminService();
$selfUrl = fvd_torneo_inscripcion_self_url();
$fvd_page_title = AuthService::isDelegadoAsociacion() ? 'Inscripciones al torneo' : 'Inscripción';
$fvd_error = '';
$fvd_ok = '';

$asocId = (int) (AuthService::idAsociacion() ?? 0);
$esFvd = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
if ($esFvd) {
    $asocId = isset($_GET['asociacion_id']) ? (int) $_GET['asociacion_id'] : (isset($_POST['asociacion_id']) ? (int) $_POST['asociacion_id'] : 0);
} elseif ($asocId <= 0 && AuthService::isSuperAdmin()) {
    $portalA = (int) (AuthService::adminPortalDelegadoAsociacionId() ?? 0);
    if ($portalA > 0) {
        $asocId = $portalA;
    }
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
    /*
     * Contexto delegado: el torneo activo (`torneo_id`) es la referencia principal.
     * Si el torneo pertenece a un campeonato (`grupo_evento_id`), se sincroniza sesión y
     * se cargan las ramas del mismo grupo para el selector del encabezado; no se exige
     * `campeonato_id` en la URL.
     */
    $fvd_error_campeonato = '';
    $fvdDelegadoGrupoTorneos = [];
    $fvd_campeonato_grupo = 0;

    if ($torneoSel > 0) {
        if (!$svc->delegadoPuedeFijarTorneoContext($asocId, $torneoSel)) {
            $fvd_error_campeonato = 'No tiene convocatoria o permisos para trabajar con este torneo.';
        } else {
            AuthService::setDelegadoTorneoContext($torneoSel);
            $gDelTor = $svc->torneoGrupoEventoId($torneoSel);
            if ($gDelTor !== null && $gDelTor > 0) {
                AuthService::setDelegadoCampeonatoGrupo((int) $gDelTor);
                $fvd_campeonato_grupo = (int) $gDelTor;
                $fvdDelegadoGrupoTorneos = $svc->torneosPorGrupoCampeonato($asocId, $fvd_campeonato_grupo, $torneoSel);
                if ($fvdDelegadoGrupoTorneos === []) {
                    $filaT = $svc->torneosFind($torneoSel);
                    if (is_array($filaT)) {
                        $fvdDelegadoGrupoTorneos = [$filaT];
                    }
                }
            } else {
                AuthService::setDelegadoCampeonatoGrupo(null);
                $filaT = $svc->torneosFind($torneoSel);
                if (is_array($filaT)) {
                    $fvdDelegadoGrupoTorneos = [$filaT];
                }
            }
        }
    } else {
        $campGet = isset($_GET['campeonato_id']) ? (int) $_GET['campeonato_id'] : 0;
        if ($campGet <= 0) {
            $sessG = AuthService::delegadoCampeonatoGrupoId();
            $campGet = $sessG !== null && $sessG > 0 ? $sessG : 0;
        }
        if ($campGet > 0) {
            $grupoRes = $svc->resolverGrupoDesdeCampeonatoParam($campGet);
            if ($grupoRes !== null && $grupoRes > 0) {
                AuthService::setDelegadoCampeonatoGrupo($grupoRes);
                $fvd_campeonato_grupo = $grupoRes;
                $ctx = (int) (AuthService::delegadoTorneoContextId() ?? 0);
                $fvdDelegadoGrupoTorneos = $svc->torneosPorGrupoCampeonato(
                    $asocId,
                    $grupoRes,
                    $ctx > 0 ? $ctx : null
                );
            }
        }
    }
}

if (!is_array($fvdDelegadoGrupoTorneos)) {
    $fvdDelegadoGrupoTorneos = [];
}
foreach ($fvdDelegadoGrupoTorneos as &$fvd_gt_row) {
    try {
        $fvd_gt_row['nombre_corta'] = $svc->nombreCortaTorneoCampeonato((string) ($fvd_gt_row['nombre'] ?? ''));
    } catch (Throwable $e) {
        $fvd_gt_row['nombre_corta'] = (string) ($fvd_gt_row['nombre'] ?? '');
    }
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

$webBase = fvd_infer_web_base_path();
$inscripcionApiUrl = ($webBase !== ''
    ? rtrim($webBase, '/')
    : rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/')) . '/fvdmasteradmin/delegado_inscripcion_api.php';
$uploadsPublicBase = url('crud_atletas/uploads/');
$torneoMeta = null;
if ($tablasOk && $torneoSel > 0 && $asocId > 0) {
    try {
        $torneoMeta = $svc->torneoInscripcionMetaParaVista($torneoSel, $asocId, $esDelegadoBandera ? true : null);
    } catch (Throwable $e) {
        error_log('[torneo_inscripcion] torneoInscripcionMetaParaVista: ' . $e->getMessage());
        $fvd_error = $fvd_error !== ''
            ? $fvd_error
            : 'No se pudieron cargar los datos del torneo. Revise la conexión a la base de datos o ejecute los scripts SQL pendientes.';
    }
}

if ($torneoMeta !== null && ($torneoMeta['torneo']['nombre'] ?? '') !== '') {
    $fvd_page_title = (string) $torneoMeta['torneo']['nombre'];
}

$fvd_inscripcion_bandera_modo = $esDelegadoBandera;
try {
    $tieneColsBandera = $svc->atletasTieneColumnasInscripcionTorneo();
} catch (Throwable $e) {
    error_log('[torneo_inscripcion] atletasTieneColumnasInscripcionTorneo: ' . $e->getMessage());
    $tieneColsBandera = false;
}

$fvdSitioDisponibles = [];
$fvdSitioInscritos = [];
$fvdSitioInscritosGrupos = [];
$fvd_sitio_clase = 1;
if ($tablasOk && $torneoSel > 0 && $asocId > 0) {
    try {
        require_once __DIR__ . '/sitio_arrays.inc.php';
        $sitio = fvd_torneo_inscripcion_build_sitio_arrays($svc, fvd_db(), $torneoSel, $asocId, $tieneColsBandera);
        $fvdSitioDisponibles = $sitio['fvdSitioDisponibles'];
        $fvdSitioInscritos = $sitio['fvdSitioInscritos'];
        $fvdSitioInscritosGrupos = $sitio['fvdSitioInscritosGrupos'] ?? [];
        $fvd_sitio_clase = (int) ($sitio['fvd_sitio_clase'] ?? 1);
    } catch (Throwable $e) {
        error_log('[torneo_inscripcion] build_sitio_arrays: ' . $e->getMessage());
        $fvdSitioDisponibles = [];
        $fvdSitioInscritos = [];
        $fvdSitioInscritosGrupos = [];
        $fvd_sitio_clase = 1;
    }
}

$fvdSitioNuevoAtletaUrl = fvd_master_module_url('atletas/index.php?action=form');
$fvd_campeonato_q = ($esDelegadoBandera && $fvd_campeonato_grupo > 0) ? ('&campeonato_id=' . $fvd_campeonato_grupo) : '';

$fvd_asoc_nombre = '';
if ($fvd_asoc_nombre === '' && $asocId > 0) {
    foreach ($asociacionesSelect as $a) {
        if ((int) ($a['id'] ?? 0) === $asocId) {
            $fvd_asoc_nombre = trim((string) ($a['nombre'] ?? ''));
            break;
        }
    }
}
if ($fvd_asoc_nombre === '' && $asocId > 0 && $tablasOk) {
    try {
        $stAso = fvd_db()->prepare('SELECT nombre FROM asociaciones WHERE id = :id LIMIT 1');
        $stAso->execute([':id' => $asocId]);
        $fvd_asoc_nombre = trim((string) ($stAso->fetchColumn() ?: ''));
    } catch (Throwable $e) {
        // ignore
    }
}

require_once FVD_PROJECT_ROOT . '/src/Services/InscripcionService.php';

$fvdInscStatsGen = null;
$fvdInscCtxTorneos = is_array($fvdDelegadoGrupoTorneos) ? $fvdDelegadoGrupoTorneos : [];
$fvdInscTorneosChips = [];
$fvdInscEsDelegado = $esDelegadoBandera;
$fvdInscEsFvdAdmin = $esFvd;
$fvdInscAsocIdQuery = $asocId;
$fvdInscCampeonatoId = (int) $fvd_campeonato_grupo;
$fvdInscSelfUrl = $selfUrl;
$fvdInscFilterTorneoId = $torneoSel;
$fvdInscTorneoNombre = '';
if ($torneoMeta !== null) {
    $fvdInscTorneoNombre = trim((string) ($torneoMeta['torneo']['nombre'] ?? ''));
}
if ($tablasOk && $torneoSel > 0 && $asocId > 0) {
    try {
        $fvdInscStatsGen = \FvdPortal\Services\InscripcionService::estadisticasPorGeneroTorneoAsociacion(
            fvd_db(),
            $torneoSel,
            $asocId
        );
    } catch (Throwable $e) {
        error_log('[torneo_inscripcion] estadisticasPorGenero: ' . $e->getMessage());
    }
}
if (!$esDelegadoBandera) {
    foreach ($torneosAbiertos as $rAb) {
        $tidAb = (int) ($rAb['torneo'] ?? 0);
        if ($tidAb > 0) {
            $fvdInscTorneosChips[] = [
                'id'     => $tidAb,
                'nombre' => (string) ($rAb['nombre'] ?? ''),
            ];
        }
    }
}

/** Enlace al administrador de filas en `inscripcion_torneo` (admin FVD / admin asoc.; no delegado bandera). */
$fvd_url_admin_inscripciones_tabla = '';
if (!$esDelegadoBandera && $tablasOk && $torneoSel > 0 && $asocId > 0 && $svc->torneosInscripcionTorneoTableExists()) {
    $qAdmInsc = ['torneo_id' => $torneoSel];
    if ($esFvd) {
        $qAdmInsc['asociacion_id'] = $asocId;
    }
    $fvd_url_admin_inscripciones_tabla = fvd_master_module_url('inscripcion_torneo/index.php?' . http_build_query($qAdmInsc));
}

require FVD_MASTER_ROOT . '/includes/layout_header.php';
include __DIR__ . '/inscribir.view.php';
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
