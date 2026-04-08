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

/** @var array<string, mixed>|null Estadísticas torneo + asociación para badges superiores */
$fvdRepStats = null;
if ($fvdRepDefaultTorneo > 0 && $fvdRepDefaultAsoc > 0) {
    require_once FVD_MASTER_ROOT . '/config/db.php';
    $pdoSt = fvd_db();
    $tidSt = (int) $fvdRepDefaultTorneo;
    $aidSt = (int) $fvdRepDefaultAsoc;
    try {
        $st = $pdoSt->prepare(
            'SELECT
                SUM(CASE WHEN COALESCE(a.inscripcion,0)=1 THEN 1 ELSE 0 END) AS n_insc,
                SUM(CASE WHEN COALESCE(a.carnet,0)=1 THEN 1 ELSE 0 END) AS n_carn,
                SUM(CASE WHEN COALESCE(a.afiliacion,0)=1 THEN 1 ELSE 0 END) AS n_afi
            FROM atletas a
            WHERE a.torneo_id = :t AND a.asociacion = :a'
        );
        $st->execute([':t' => $tidSt, ':a' => $aidSt]);
        $rowA = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        $nomAsoc = '';
        $stN = $pdoSt->prepare('SELECT nombre FROM asociaciones WHERE id = :id LIMIT 1');
        $stN->execute([':id' => $aidSt]);
        $rN = $stN->fetch(PDO::FETCH_ASSOC);
        if (is_array($rN)) {
            $nomAsoc = trim((string) ($rN['nombre'] ?? ''));
        }

        $montoBs = 0.0;
        $montoEur = null;
        $rowD = null;
        $stD = $pdoSt->prepare('SELECT * FROM deuda_asociaciones WHERE torneo_id = :t AND asociacion_id = :a LIMIT 1');
        $stD->execute([':t' => $tidSt, ':a' => $aidSt]);
        $rowD = $stD->fetch(PDO::FETCH_ASSOC);
        if (is_array($rowD)) {
            $montoBs = (float) ($rowD['monto_total'] ?? 0);
            if (isset($rowD['monto_total_eur']) && $rowD['monto_total_eur'] !== null && $rowD['monto_total_eur'] !== '') {
                $montoEur = (float) $rowD['monto_total_eur'];
            }
        }

        $pagadoEur = 0.0;
        $stP = $pdoSt->prepare('SELECT COALESCE(SUM(monto_dolares),0) FROM relacion_pagos WHERE torneo_id = :t AND asociacion_id = :a');
        $stP->execute([':t' => $tidSt, ':a' => $aidSt]);
        $pagadoEur = (float) $stP->fetchColumn();

        $saldoEur = null;
        if ($montoEur !== null && $montoEur > 0) {
            $saldoEur = max(0.0, round($montoEur - $pagadoEur, 2));
        }

        $fvdRepStats = [
            'torneo_id'      => $tidSt,
            'asociacion_id'  => $aidSt,
            'asoc_nombre'    => $nomAsoc,
            'n_inscritos'    => (int) ($rowA['n_insc'] ?? 0),
            'n_carnets'      => (int) ($rowA['n_carn'] ?? 0),
            'n_afiliados'    => (int) ($rowA['n_afi'] ?? 0),
            'monto_total_bs' => $montoBs,
            'monto_total_eur'=> $montoEur,
            'pagado_eur'     => round($pagadoEur, 2),
            'saldo_eur'      => $saldoEur,
            'deuda'          => is_array($rowD) ? $rowD : null,
        ];
    } catch (Throwable $e) {
        error_log('[inscripciones/index] fvdRepStats: ' . $e->getMessage());
        $fvdRepStats = null;
    }
}

$fvd_page_title = 'Reportes de inscripciones y finanzas';
require FVD_MASTER_ROOT . '/includes/layout_header.php';
include __DIR__ . '/list.view.php';
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
