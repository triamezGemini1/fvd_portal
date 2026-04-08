<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/_init.php';
fvd_module_require_roles();

require_once __DIR__ . '/Controller.php';
$ctrl = new InscripcionesController();

$myAid = AuthService::idAsociacion();
$ctxTorneo = AuthService::delegadoTorneoContextId();
$tidGet = isset($_GET['torneo_id']) ? (int) $_GET['torneo_id'] : 0;
$fvdRepTorneos = $ctrl->listTorneosParaSelector(
    $myAid !== null && (int) $myAid > 0 ? (int) $myAid : null,
    $ctxTorneo
);
if ($fvdRepTorneos === [] && $ctxTorneo !== null && $ctxTorneo > 0) {
    $one = $ctrl->fetchTorneoActo($ctxTorneo);
    if ($one !== null) {
        $fvdRepTorneos = [$one];
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
// Prioridad estricta: URL > contexto delegado > primer torneo del listado.
$fvdRepDefaultTorneo = $tidGet > 0 ? $tidGet : ($ctxTorneo !== null && $ctxTorneo > 0 ? $ctxTorneo : 0);
if ($fvdRepDefaultTorneo <= 0 && $fvdRepTorneos !== []) {
    $fvdRepDefaultTorneo = (int) ($fvdRepTorneos[0]['torneo'] ?? 0);
}
$fvdRepDefaultAsoc = $myAid !== null && (int) $myAid > 0 ? (int) $myAid : 0;
if (AuthService::role() === AuthService::ROLE_FVD_ADMIN) {
    $aidGet = isset($_GET['asociacion_id']) ? (int) $_GET['asociacion_id'] : 0;
    $fvdRepDefaultAsoc = $aidGet > 0 ? $aidGet : ($fvdRepAsociaciones !== [] ? (int) ($fvdRepAsociaciones[0]['id'] ?? 0) : 0);
}

$fvd_page_title = 'Reportes de inscripciones y finanzas';
require FVD_MASTER_ROOT . '/includes/layout_header.php';
include __DIR__ . '/list.view.php';
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
