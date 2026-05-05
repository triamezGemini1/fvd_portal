<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/_init.php';
fvd_module_require_roles();

require_once __DIR__ . '/Controller.php';
$ctrl = new InscripcionesController();

$myAid = AuthService::idAsociacion();
$ctxTorneo = AuthService::delegadoTorneoContextId();
$tidGet = isset($_GET['torneo_id']) ? (int) $_GET['torneo_id'] : 0;
$campGet = isset($_GET['campeonato_id']) ? (int) $_GET['campeonato_id'] : 0;
if ($campGet <= 0) {
    $sessCg = AuthService::delegadoCampeonatoGrupoId();
    $campGet = $sessCg !== null && $sessCg > 0 ? $sessCg : 0;
}

$fvdRepTorneos = [];
$fvd_rep_campeonato_error = '';
if (AuthService::isDelegadoAsociacion() && $myAid !== null && (int) $myAid > 0) {
    if ($campGet <= 0) {
        $fvd_rep_campeonato_error = 'Indique campeonato_id (grupo de evento o ID de torneo del campeonato) en la URL.';
    } else {
        $ctxFiltroList = $tidGet > 0 ? $tidGet : ($ctxTorneo !== null && $ctxTorneo > 0 ? $ctxTorneo : null);
        $fvdRepTorneos = $ctrl->listTorneosPorCampeonatoParaDelegado(
            (int) $myAid,
            $campGet,
            $ctxFiltroList !== null && $ctxFiltroList > 0 ? $ctxFiltroList : null
        );
        if ($fvdRepTorneos === []) {
            $fvd_rep_campeonato_error = 'No hay torneos de este campeonato con convocatoria para su asociación, o el campeonato no es válido.';
        }
    }
} else {
    $fvdRepTorneos = $ctrl->listTorneosParaSelector(
        $myAid !== null && (int) $myAid > 0 ? (int) $myAid : null,
        $ctxTorneo,
        $tidGet
    );
    if ($fvdRepTorneos === [] && $ctxTorneo !== null && $ctxTorneo > 0) {
        $one = $ctrl->fetchTorneoActo($ctxTorneo);
        if ($one !== null) {
            $fvdRepTorneos = [$one];
        }
    }
}
// Si el usuario pasa ?torneo_id=X, ese torneo debe figurar en el selector aunque no haya atletas aún
// (sin esto el <select> ignora X y muestra el primero = suele ser el del contexto del delegado).
if ($tidGet > 0) {
    $yaEsta = false;
    foreach ($fvdRepTorneos as $tr) {
        if ((int) ($tr['torneo'] ?? 0) === $tidGet) {
            $yaEsta = true;
            break;
        }
    }
    if (!$yaEsta) {
        $extra = $ctrl->fetchTorneoActo($tidGet);
        if ($extra !== null) {
            array_unshift($fvdRepTorneos, $extra);
        }
    }
}
$fvdRepAsociaciones = $ctrl->listAsociacionesParaAdmin();
$fvdRepMaestroFinanzasEmb = function_exists('fvd_master_embed_active') && fvd_master_embed_active() && AuthService::isSuperAdmin();
// Prioridad estricta: URL > contexto delegado > primer torneo del listado.
$fvdRepDefaultTorneo = $tidGet > 0 ? $tidGet : ($ctxTorneo !== null && $ctxTorneo > 0 ? $ctxTorneo : 0);
if ($fvdRepDefaultTorneo <= 0 && $fvdRepTorneos !== []) {
    $fvdRepDefaultTorneo = (int) ($fvdRepTorneos[0]['torneo'] ?? 0);
}
$fvdRepDefaultAsoc = $myAid !== null && (int) $myAid > 0 ? (int) $myAid : 0;
if (AuthService::role() === AuthService::ROLE_FVD_ADMIN) {
    $aidGet = isset($_GET['asociacion_id']) ? (int) $_GET['asociacion_id'] : 0;
    if ($fvdRepMaestroFinanzasEmb) {
        $fvdRepDefaultAsoc = $aidGet;
    } else {
        $fvdRepDefaultAsoc = $aidGet > 0 ? $aidGet : ($fvdRepAsociaciones !== [] ? (int) ($fvdRepAsociaciones[0]['id'] ?? 0) : 0);
    }
}

/** @var array<string, mixed>|null Estadísticas torneo + asociación seleccionada (badges y PDF) */
$fvdRepStats = null;
/** @var array<int, array<string, mixed>> Por asociación (solo administrador FVD): una fila de estadísticas por club */
$fvdRepStatsPorAsoc = [];
if ($fvdRepDefaultTorneo > 0) {
    $tidSt = (int) $fvdRepDefaultTorneo;
    if (AuthService::role() === AuthService::ROLE_FVD_ADMIN && $fvdRepAsociaciones !== []) {
        foreach ($fvdRepAsociaciones as $ar) {
            $aidRow = (int) ($ar['id'] ?? 0);
            if ($aidRow <= 0) {
                continue;
            }
            $stRow = $ctrl->reportStatsTorneoAsociacion($tidSt, $aidRow);
            if ($stRow !== null) {
                $fvdRepStatsPorAsoc[$aidRow] = $stRow;
            }
        }
    }
    if ($fvdRepDefaultAsoc > 0) {
        $fvdRepStats = $ctrl->reportStatsTorneoAsociacion($tidSt, (int) $fvdRepDefaultAsoc);
    }
}

/** Modo embebido panel maestro: detalle EUR por asociación */
$fvdRepDeudaEurConceptos = null;
$fvdRepPagosEurRows = [];
if (!empty($fvdRepMaestroFinanzasEmb) && (int) $fvdRepDefaultAsoc > 0 && (int) $fvdRepDefaultTorneo > 0) {
    $d = is_array($fvdRepStats['deuda'] ?? null) ? $fvdRepStats['deuda'] : null;
    if ($d !== null) {
        $fvdRepDeudaEurConceptos = $ctrl->allocDeudaEurPorConcepto($d);
    } else {
        $fvdRepDeudaEurConceptos = [
            'inscripciones' => 0.0, 'afiliacion' => 0.0, 'carnets' => 0.0, 'traspasos' => 0.0, 'anualidad' => 0.0,
        ];
    }
    $fvdRepPagosEurRows = $ctrl->listPagosTorneoAsociacionEur(
        (int) $fvdRepDefaultTorneo,
        (int) $fvdRepDefaultAsoc
    );
}

$fvd_rep_campeonato_id = isset($campGet) ? (int) $campGet : 0;

if (!empty($fvdRepMaestroFinanzasEmb) && (int) $fvdRepDefaultAsoc > 0 && (int) $fvdRepDefaultTorneo > 0) {
    $fvd_page_title = 'Detalle del club (EUR) — inscripciones';
    require FVD_MASTER_ROOT . '/includes/layout_header.php';
    include __DIR__ . '/detalle_asociacion_embed.view.php';
    require FVD_MASTER_ROOT . '/includes/layout_footer.php';
} elseif (!empty($fvdRepMaestroFinanzasEmb)) {
    $fvd_page_title = 'Inscripciones y finanzas por asociación (EUR)';
    require FVD_MASTER_ROOT . '/includes/layout_header.php';
    include __DIR__ . '/lista_maestro_embed.view.php';
    require FVD_MASTER_ROOT . '/includes/layout_footer.php';
} else {
    $fvd_page_title = 'Reportes de inscripciones y finanzas';
    require FVD_MASTER_ROOT . '/includes/layout_header.php';
    include __DIR__ . '/list.view.php';
    require FVD_MASTER_ROOT . '/includes/layout_footer.php';
}
