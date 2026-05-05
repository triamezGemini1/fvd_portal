<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/_init.php';
fvd_module_require_roles();

require_once __DIR__ . '/Controller.php';
require_once FVD_PROJECT_ROOT . '/src/Services/InscripcionService.php';
require_once FVD_PROJECT_ROOT . '/src/Services/FvdAdminService.php';
require_once FVD_MASTER_ROOT . '/services/QueryHelper.php';
require_once FVD_PROJECT_ROOT . '/src/Services/DeudaAsociacionGeneratorService.php';

use FvdPortal\Services\DeudaAsociacionGeneratorService;
use FvdPortal\Services\InscripcionService;

$ctrl = new InscripcionTorneoController();

$fvd_page_title = 'Administrador de inscripciones';
$selfUrl = fvd_module_url('inscripcion_torneo/index.php');
$fvd_error = '';

$fvd_inscripcion_torneo_tabla = $ctrl->tableExists();

$pdo = fvd_db();
$filterT = isset($_GET['torneo_id']) ? (int) $_GET['torneo_id'] : 0;
if (AuthService::isDelegadoAsociacion() && $filterT <= 0) {
    $ctxListTor = (int) (AuthService::delegadoTorneoContextId() ?? 0);
    if ($ctxListTor > 0) {
        $filterT = $ctxListTor;
    }
}
$fvd_error = isset($_GET['fvd_err']) ? trim((string) $_GET['fvd_err']) : '';
$fvd_ok = isset($_GET['fvd_ok']) ? trim((string) $_GET['fvd_ok']) : '';

if (AuthService::isDelegadoAsociacion() && $filterT > 0 && !isset($_GET['torneo_id'])) {
    $qCanon = $_GET;
    $qCanon['torneo_id'] = $filterT;
    header('Location: ' . $selfUrl . '?' . http_build_query($qCanon), true, 302);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'retirar_inscripcion_atleta') {
    try {
        $atletaId = isset($_POST['atleta_id']) ? (int) $_POST['atleta_id'] : 0;
        if ($atletaId <= 0) {
            throw new InvalidArgumentException('Atleta no válido.');
        }
        $postTor = (int) ($_POST['torneo_id'] ?? 0);
        $stV = $pdo->prepare(
            'SELECT id, asociacion, torneo_id FROM atletas WHERE id = :id LIMIT 1'
        );
        $stV->execute([':id' => $atletaId]);
        $metaA = $stV->fetch(PDO::FETCH_ASSOC);
        if ($metaA === false) {
            throw new RuntimeException('No se encontró el atleta.');
        }
        $torFromRow = (int) ($metaA['torneo_id'] ?? 0);
        $asocRow = (int) ($metaA['asociacion'] ?? 0);
        if ($postTor > 0 && $torFromRow > 0 && $postTor !== $torFromRow) {
            throw new InvalidArgumentException('Torneo no coincide con la ficha del atleta.');
        }
        if (!AuthService::isSuperAdmin() && !AuthService::canManageAsociacion($asocRow)) {
            throw new RuntimeException('No tiene permiso para retirar a este atleta.');
        }
        $torneoRet = $torFromRow > 0 ? $torFromRow : $postTor;
        if ($torneoRet <= 0) {
            throw new InvalidArgumentException('Torneo no válido para el retiro.');
        }
        $ok = InscripcionService::retirarInscripcionBandera($pdo, $torneoRet, $asocRow, $atletaId);
        if (!$ok) {
            throw new RuntimeException('No se pudo retirar (¿no estaba inscrito o es parte de un equipo?).');
        }
        $torRedir = $torneoRet;
        if ($torRedir <= 0 && AuthService::isDelegadoAsociacion()) {
            $torRedir = (int) (AuthService::delegadoTorneoContextId() ?? 0);
        }
        $msg = 'Inscripción retirada en la ficha del atleta. Se actualizó la deuda del torneo cuando aplica.';
        $qs = ['fvd_ok' => $msg];
        if ($torRedir > 0) {
            $qs['torneo_id'] = $torRedir;
        }
        header('Location: ' . $selfUrl . '?' . http_build_query($qs), true, 302);
        exit;
    } catch (Throwable $e) {
        $torRedir = (int) ($_POST['torneo_id'] ?? 0);
        if ($torRedir <= 0 && AuthService::isDelegadoAsociacion()) {
            $torRedir = (int) (AuthService::delegadoTorneoContextId() ?? 0);
        }
        $qs = ['fvd_err' => $e->getMessage()];
        if ($torRedir > 0) {
            $qs['torneo_id'] = $torRedir;
        }
        header('Location: ' . $selfUrl . '?' . http_build_query($qs), true, 302);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'sustituir_inscripcion_atleta') {
    try {
        $atletaSalida = isset($_POST['atleta_salida_id']) ? (int) $_POST['atleta_salida_id'] : 0;
        $atletaEntrada = isset($_POST['atleta_entrada_id']) ? (int) $_POST['atleta_entrada_id'] : 0;
        $postTor = (int) ($_POST['torneo_id'] ?? 0);
        if ($postTor <= 0 || $atletaSalida <= 0 || $atletaEntrada <= 0) {
            throw new InvalidArgumentException('Datos incompletos para la sustitución.');
        }
        $stS = $pdo->prepare('SELECT id, asociacion, torneo_id FROM atletas WHERE id = :id LIMIT 1');
        $stS->execute([':id' => $atletaSalida]);
        $rowS = $stS->fetch(PDO::FETCH_ASSOC);
        if ($rowS === false) {
            throw new RuntimeException('No se encontró el atleta que sale.');
        }
        $asoc = (int) ($rowS['asociacion'] ?? 0);
        $torSal = (int) ($rowS['torneo_id'] ?? 0);
        if ($torSal > 0 && $torSal !== $postTor) {
            throw new InvalidArgumentException('El torneo enviado no coincide con la inscripción del atleta que sale.');
        }
        if (!AuthService::canManageAsociacion($asoc)) {
            throw new RuntimeException('No tiene permiso para esta asociación.');
        }
        $stE = $pdo->prepare('SELECT id, asociacion FROM atletas WHERE id = :id LIMIT 1');
        $stE->execute([':id' => $atletaEntrada]);
        $rowE = $stE->fetch(PDO::FETCH_ASSOC);
        if ($rowE === false) {
            throw new RuntimeException('No se encontró el atleta de reemplazo.');
        }
        if ((int) ($rowE['asociacion'] ?? 0) !== $asoc) {
            throw new InvalidArgumentException('Ambos atletas deben pertenecer al mismo club.');
        }
        InscripcionService::sustituirInscripcionIndividualBandera($pdo, $postTor, $asoc, $atletaSalida, $atletaEntrada);
        $msg = 'Sustitución realizada: el nuevo atleta quedó inscrito en el torneo.';
        $qs = ['fvd_ok' => $msg, 'torneo_id' => $postTor];
        header('Location: ' . $selfUrl . '?' . http_build_query($qs), true, 302);
        exit;
    } catch (Throwable $e) {
        $torRedir = (int) ($_POST['torneo_id'] ?? 0);
        $qs = ['fvd_err' => $e->getMessage()];
        if ($torRedir > 0) {
            $qs['torneo_id'] = $torRedir;
        }
        header('Location: ' . $selfUrl . '?' . http_build_query($qs), true, 302);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'guardar_fila_inscripcion_torneo') {
    if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
        $torRedir = (int) ($_POST['torneo_id'] ?? 0);
        $qs = ['fvd_err' => 'La edición directa del volcado inscripcion_torneo solo está disponible para el administrador FVD. Use la ficha de atletas o la inscripción en sitio.'];
        if ($torRedir > 0) {
            $qs['torneo_id'] = $torRedir;
        }
        header('Location: ' . $selfUrl . '?' . http_build_query($qs), true, 302);
        exit;
    }
    $allowed = [
        'asociacion_id', 'torneo_id', 'equipo', 'cedula', 'nombre', 'nombre_equipo', 'numfvd', 'sexo',
        'telefono', 'email', 'afiliacion', 'anualidad', 'carnet', 'traspaso', 'inscripcion',
    ];
    try {
        $editPostId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $isNewPost = $editPostId <= 0;
        $sustituirDeId = isset($_POST['sustituir_de_id']) ? (int) $_POST['sustituir_de_id'] : 0;
        if ($sustituirDeId > 0 && !$isNewPost) {
            throw new InvalidArgumentException('Sustitución inválida: no envíe id de edición junto con sustituir.');
        }
        $torneoPost = (int) ($_POST['torneo_id'] ?? 0);
        if ($torneoPost <= 0) {
            throw new InvalidArgumentException('Torneo no válido.');
        }
        $torneoReg = InscripcionService::torneoReglas($pdo, $torneoPost);
        if ($torneoReg === null) {
            throw new InvalidArgumentException('No existe el torneo indicado.');
        }
        $torneoClaseP = InscripcionService::normalizarClaseTorneo($torneoReg);

        $asocP = (int) ($_POST['asociacion_id'] ?? 0);
        if (AuthService::role() === AuthService::ROLE_FVD_ADMIN) {
            if ($asocP <= 0) {
                throw new InvalidArgumentException('Seleccione asociación.');
            }
        } else {
            $asocP = (int) (AuthService::idAsociacion() ?? 0);
        }
        if ($asocP <= 0 || !AuthService::canManageAsociacion($asocP)) {
            throw new RuntimeException('Sin permiso para esta asociación.');
        }

        $cedDigits = preg_replace('/\D+/', '', (string) ($_POST['cedula'] ?? ''));
        $cedula = (int) $cedDigits;
        if ($cedula <= 0) {
            throw new InvalidArgumentException('La cédula debe ser numérica y mayor que cero.');
        }
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre es obligatorio.');
        }
        $neqRaw = trim((string) ($_POST['nombre_equipo'] ?? ''));
        $nombreEquipo = $neqRaw === '' ? null : $neqRaw;
        if (($torneoClaseP === InscripcionService::CLASE_PAREJAS || $torneoClaseP === InscripcionService::CLASE_EQUIPOS)
            && $nombreEquipo === null) {
            throw new InvalidArgumentException('En parejas o equipos el nombre de pareja/equipo es obligatorio.');
        }

        $exceptCedulaId = $isNewPost ? 0 : $editPostId;
        if ($isNewPost && $sustituirDeId > 0) {
            $exceptCedulaId = $sustituirDeId;
        }
        if ($ctrl->cedulaOcupadaEnTorneo($torneoPost, $cedula, $exceptCedulaId)) {
            throw new InvalidArgumentException('Ya existe una inscripción con esa cédula en este torneo (índice único torneo + cédula).');
        }

        $tel = trim((string) ($_POST['telefono'] ?? ''));
        $em = trim((string) ($_POST['email'] ?? ''));
        $data = [
            'asociacion_id' => $asocP,
            'torneo_id'     => $torneoPost,
            'equipo'        => max(0, (int) ($_POST['equipo'] ?? 0)),
            'cedula'        => $cedula,
            'nombre'        => $nombre,
            'nombre_equipo' => $nombreEquipo,
            'numfvd'        => max(0, (int) ($_POST['numfvd'] ?? 0)),
            'sexo'          => max(0, min(2, (int) ($_POST['sexo'] ?? 0))),
            'telefono'      => $tel === '' ? null : $tel,
            'email'         => $em === '' ? null : $em,
            'afiliacion'    => isset($_POST['afiliacion']) ? 1 : 0,
            'anualidad'     => isset($_POST['anualidad']) ? 1 : 0,
            'carnet'        => isset($_POST['carnet']) ? 1 : 0,
            'traspaso'      => isset($_POST['traspaso']) ? 1 : 0,
            'inscripcion'   => max(0, min(2, (int) ($_POST['inscripcion'] ?? 1))),
        ];

        if ($isNewPost && $sustituirDeId > 0) {
            $oldS = $ctrl->findByIdScoped($sustituirDeId);
            if ($oldS === null) {
                throw new RuntimeException('No se encontró la inscripción a sustituir o no tiene permiso.');
            }
            if ((int) ($oldS['torneo_id'] ?? 0) !== $torneoPost || (int) ($oldS['asociacion_id'] ?? 0) !== $asocP) {
                throw new InvalidArgumentException('La sustitución no coincide con el torneo o la asociación indicados.');
            }
            $pdo->beginTransaction();
            try {
                $delN = $ctrl->deleteByIdScoped($sustituirDeId);
                if ($delN <= 0) {
                    throw new RuntimeException('No se pudo retirar la inscripción anterior.');
                }
                QueryHelper::insert($pdo, InscripcionTorneoController::TABLE, $data, $allowed);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        } elseif ($isNewPost) {
            QueryHelper::insert($pdo, InscripcionTorneoController::TABLE, $data, $allowed);
        } else {
            $prev = $ctrl->findByIdScoped($editPostId);
            if ($prev === null) {
                throw new RuntimeException('Registro no encontrado o sin permiso.');
            }
            $paramsW = [':wid' => $editPostId];
            $scopeW = QueryHelper::asociacionScopeSql('asociacion_id', $paramsW);
            $n = QueryHelper::update(
                $pdo,
                InscripcionTorneoController::TABLE,
                $data,
                $allowed,
                'id = :wid' . $scopeW,
                $paramsW
            );
            if ($n <= 0) {
                throw new RuntimeException('No se actualizó ninguna fila (¿permisos o datos iguales?).');
            }
        }

        try {
            DeudaAsociacionGeneratorService::generarParaTorneoYAsociacion($pdo, $torneoPost, $asocP);
        } catch (Throwable $e) {
            error_log('[inscripcion_torneo guardar] deuda: ' . $e->getMessage());
        }

        if ($isNewPost && $sustituirDeId > 0) {
            $msg = 'Sustitución realizada: se retiró la inscripción anterior y se registró el nuevo atleta.';
        } else {
            $msg = $isNewPost ? 'Registro creado.' : 'Cambios guardados.';
        }
        $redir = $selfUrl . '?torneo_id=' . $torneoPost . '&fvd_ok=' . rawurlencode($msg);
        header('Location: ' . $redir, true, 302);
        exit;
    } catch (Throwable $e) {
        $torRedir = (int) ($_POST['torneo_id'] ?? 0);
        $qs = ['fvd_err' => $e->getMessage()];
        if ($torRedir > 0) {
            $qs['torneo_id'] = $torRedir;
        }
        header('Location: ' . $selfUrl . '?' . http_build_query($qs), true, 302);
        exit;
    }
}

$action = isset($_GET['action']) ? (string) $_GET['action'] : 'list';
if ($action === 'form' && AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
    $torRedir = $filterT > 0 ? $filterT : (int) ($_GET['torneo_id'] ?? 0);
    $qs = ['fvd_err' => 'El formulario del volcado inscripcion_torneo es solo para administrador FVD. Gestione inscripciones desde la ficha de atletas (marca inscripción al torneo) o desde inscripción en sitio.'];
    if ($torRedir > 0) {
        $qs['torneo_id'] = $torRedir;
    }
    header('Location: ' . $selfUrl . '?' . http_build_query($qs), true, 302);
    exit;
}
if ($action === 'form') {
    $editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $sustituirDeGet = isset($_GET['sustituir_de_id']) ? (int) $_GET['sustituir_de_id'] : 0;
    $formTorneo = $filterT;
    $row = null;
    $fvd_form_err = '';
    $fvd_sustituir_desde_id = 0;
    if ($editId > 0 && $sustituirDeGet > 0) {
        $fvd_form_err = 'Use editar o sustituir, no ambos en la misma URL.';
    }
    if ($fvd_form_err === '' && $editId > 0) {
        $row = $ctrl->findByIdScoped($editId);
        if ($row === null) {
            $fvd_form_err = 'No se encontró el registro o no tiene permiso.';
        } else {
            $formTorneo = (int) $row['torneo_id'];
        }
    }
    if ($fvd_form_err === '' && $editId <= 0 && $sustituirDeGet > 0) {
        $rowSust = $ctrl->findByIdScoped($sustituirDeGet);
        if ($rowSust === null) {
            $fvd_form_err = 'No se encontró la inscripción a sustituir o no tiene permiso.';
        } else {
            $fvd_sustituir_desde_id = $sustituirDeGet;
            $formTorneo = (int) ($rowSust['torneo_id'] ?? 0);
            $neqS = isset($rowSust['nombre_equipo']) && $rowSust['nombre_equipo'] !== null
                ? (string) $rowSust['nombre_equipo'] : '';
            $row = [
                'torneo_id'       => $formTorneo,
                'asociacion_id'   => (int) ($rowSust['asociacion_id'] ?? 0),
                'equipo'          => (int) ($rowSust['equipo'] ?? 0),
                'cedula'          => '',
                'nombre'          => '',
                'nombre_equipo'   => $neqS,
                'numfvd'          => 0,
                'sexo'            => 0,
                'telefono'        => '',
                'email'           => '',
                'afiliacion'      => 0,
                'anualidad'       => 0,
                'carnet'          => 0,
                'traspaso'        => 0,
                'inscripcion'     => InscripcionService::CANAL_INSCRIPCION_SITIO,
            ];
        }
    }
    if ($fvd_form_err === '' && $editId <= 0 && $sustituirDeGet <= 0 && $formTorneo <= 0) {
        $fvd_form_err = 'Seleccione un torneo en el listado, use «Sustituir» / «Nueva inscripción» o abra con ?action=form&torneo_id=…';
    }
    $torneoReg = InscripcionService::torneoReglas($pdo, $formTorneo);
    if ($fvd_form_err === '' && ($torneoReg === null || $formTorneo <= 0)) {
        $fvd_form_err = 'Torneo no válido.';
    }
    if ($fvd_form_err === '') {
        $torneoClase = InscripcionService::normalizarClaseTorneo($torneoReg);
        $integrantesEquipo = $torneoClase === InscripcionService::CLASE_EQUIPOS
            ? InscripcionService::integrantesEquipoRequeridos($torneoReg) : 4;
        $fvd_form_torneo_nombre = trim((string) ($torneoReg['nombre'] ?? ''));
        if ($row !== null) {
            $formRow = $row;
        } else {
            $defAsoc = AuthService::role() === AuthService::ROLE_FVD_ADMIN
                ? (isset($_GET['asociacion_id']) ? (int) $_GET['asociacion_id'] : 0)
                : (int) (AuthService::idAsociacion() ?? 0);
            $formRow = [
                'torneo_id'       => $formTorneo,
                'asociacion_id'   => $defAsoc,
                'equipo'          => 0,
                'cedula'          => '',
                'nombre'          => '',
                'nombre_equipo'   => '',
                'numfvd'          => 0,
                'sexo'            => 0,
                'telefono'        => '',
                'email'           => '',
                'afiliacion'      => 0,
                'anualidad'       => 0,
                'carnet'          => 0,
                'traspaso'        => 0,
                'inscripcion'     => InscripcionService::CANAL_INSCRIPCION_SITIO,
            ];
        }
        $asociacionesSelect = [];
        if (AuthService::role() === AuthService::ROLE_FVD_ADMIN) {
            require_once FVD_PROJECT_ROOT . '/src/Services/FvdAdminService.php';
            $adm = new FvdAdminService($pdo);
            $asociacionesSelect = $adm->asociacionesPaginateList(1, 500, '')['rows'] ?? [];
        }
        $fvd_url_inscripcion_sitio = '';
        if ($formTorneo > 0) {
            $qSitio = ['torneo_id' => $formTorneo];
            if (AuthService::role() === AuthService::ROLE_FVD_ADMIN) {
                $aidS = (int) ($formRow['asociacion_id'] ?? 0);
                if ($aidS > 0) {
                    $qSitio['asociacion_id'] = $aidS;
                }
            }
            $fvd_url_inscripcion_sitio = fvd_master_module_url('torneo_inscripcion/index.php?' . http_build_query($qSitio)) . '#fvd-insc-sitio-inscribir';
        }
        $isNew = $editId <= 0;
        $fvd_error = $fvd_error !== '' ? $fvd_error : '';
        $fvd_ok = $fvd_ok !== '' ? $fvd_ok : '';
        require FVD_MASTER_ROOT . '/includes/layout_header.php';
        include __DIR__ . '/form.view.php';
        require FVD_MASTER_ROOT . '/includes/layout_footer.php';
        exit;
    }
    $fvd_error = $fvd_form_err;
}

$page = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
$perPage = 100;
if ($filterT <= 0) {
    $result = [
        'total'    => 0,
        'page'     => 1,
        'per_page' => $perPage,
        'pages'    => 1,
        'rows'     => [],
    ];
} else {
    $result = $ctrl->paginateListBandera($page, $perPage, $filterT);
}
$torneosF = $ctrl->listTorneosForFilter();
$fvdTorneoActivoNombre = '';
$fvdCupoLinea = '';
if ($filterT > 0) {
    $trN = InscripcionService::torneoReglas($pdo, $filterT);
    $fvdTorneoActivoNombre = trim((string) ($trN['nombre'] ?? ''));
    $aidCupo = AuthService::role() === AuthService::ROLE_FVD_ADMIN
        ? (int) ($_GET['asociacion_id'] ?? 0)
        : (int) (AuthService::idAsociacion() ?? 0);
    if ($aidCupo > 0) {
        try {
            $cup = InscripcionService::estadoCupoAsociacion($pdo, $filterT, $aidCupo);
            $mx = $cup['max'];
            $fvdCupoLinea = 'Plazas usadas (atletas inscritos al torneo): ' . (int) $cup['usado']
                . ($mx === null ? '' : ' / cupo ' . (int) $mx);
        } catch (Throwable $e) {
            $fvdCupoLinea = '';
        }
    }
}
$fvd_ins_sin_torneo = $filterT <= 0;

$fvd_url_inscripcion_sitio = '';
if ($filterT > 0) {
    $qSitio = ['torneo_id' => $filterT];
    if (AuthService::role() === AuthService::ROLE_FVD_ADMIN) {
        $aidSitio = isset($_GET['asociacion_id']) ? (int) $_GET['asociacion_id'] : 0;
        if ($aidSitio > 0) {
            $qSitio['asociacion_id'] = $aidSitio;
        }
    }
    $campSitio = isset($_GET['campeonato_id']) ? (int) $_GET['campeonato_id'] : 0;
    if ($campSitio <= 0 && AuthService::isDelegadoAsociacion()) {
        $campSitio = (int) (AuthService::delegadoCampeonatoGrupoId() ?? 0);
    }
    if ($campSitio > 0) {
        $qSitio['campeonato_id'] = $campSitio;
    }
    $fvd_url_inscripcion_sitio = fvd_master_module_url('torneo_inscripcion/index.php?' . http_build_query($qSitio)) . '#fvd-insc-sitio-inscribir';
}

$webBaseIns = function_exists('fvd_infer_web_base_path') ? fvd_infer_web_base_path() : '';
$fvd_inscripcion_api_url = ($webBaseIns !== ''
    ? rtrim($webBaseIns, '/')
    : rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/')) . '/fvdmasteradmin/delegado_inscripcion_api.php';

$fvdAdminIns = new FvdAdminService($pdo);
$fvdInscAsocIdQuery = AuthService::role() === AuthService::ROLE_FVD_ADMIN
    ? (int) ($_GET['asociacion_id'] ?? 0)
    : (int) (AuthService::idAsociacion() ?? 0);
$fvdInscCampeonatoId = isset($_GET['campeonato_id']) ? (int) $_GET['campeonato_id'] : 0;
if ($fvdInscCampeonatoId <= 0 && AuthService::isDelegadoAsociacion()) {
    $fvdInscCampeonatoId = (int) (AuthService::delegadoCampeonatoGrupoId() ?? 0);
}
$fvdInscStatsGen = null;
$fvdInscCtxTorneos = [];
$fvdInscTorneosChips = $torneosF;
$fvdInscEsDelegado = AuthService::isDelegadoAsociacion();
$fvdInscEsFvdAdmin = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
$fvdInscSelfUrl = $selfUrl;
$fvdInscFilterTorneoId = $filterT;
$fvdInscTorneoNombre = $fvdTorneoActivoNombre;
if ($filterT > 0 && $fvdInscAsocIdQuery > 0) {
    try {
        $fvdInscStatsGen = InscripcionService::estadisticasPorGeneroTorneoAsociacion($pdo, $filterT, $fvdInscAsocIdQuery);
    } catch (Throwable $e) {
        error_log('[inscripcion_torneo] estadisticasPorGenero: ' . $e->getMessage());
    }
}
if ($fvdInscEsDelegado && $fvdInscCampeonatoId > 0 && $fvdInscAsocIdQuery > 0) {
    try {
        $ctxT = $filterT > 0 ? $filterT : (int) (AuthService::delegadoTorneoContextId() ?? 0);
        $fvdInscCtxTorneos = $fvdAdminIns->torneosPorGrupoCampeonato(
            $fvdInscAsocIdQuery,
            $fvdInscCampeonatoId,
            $ctxT > 0 ? $ctxT : null
        );
        foreach ($fvdInscCtxTorneos as &$fvd_gt_row) {
            try {
                $fvd_gt_row['nombre_corta'] = $fvdAdminIns->nombreCortaTorneoCampeonato((string) ($fvd_gt_row['nombre'] ?? ''));
            } catch (Throwable $e) {
                $fvd_gt_row['nombre_corta'] = (string) ($fvd_gt_row['nombre'] ?? '');
            }
        }
        unset($fvd_gt_row);
    } catch (Throwable $e) {
        error_log('[inscripcion_torneo] ctx torneos: ' . $e->getMessage());
        $fvdInscCtxTorneos = [];
    }
}
$fvdInscTorneoClase = InscripcionService::CLASE_INDIVIDUAL;
if ($filterT > 0) {
    $trC = InscripcionService::torneoReglas($pdo, $filterT);
    if ($trC !== null) {
        $fvdInscTorneoClase = InscripcionService::normalizarClaseTorneo($trC);
    }
}

require FVD_MASTER_ROOT . '/includes/layout_header.php';
include __DIR__ . '/list.view.php';
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
