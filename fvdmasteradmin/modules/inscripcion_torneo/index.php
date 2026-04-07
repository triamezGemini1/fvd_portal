<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/_init.php';
fvd_module_require_roles();

require_once __DIR__ . '/Controller.php';
require_once FVD_PROJECT_ROOT . '/src/Services/InscripcionService.php';

use FvdPortal\Services\InscripcionService;

$ctrl = new InscripcionTorneoController();

$fvd_page_title = 'Administrador de inscripciones';
$selfUrl = fvd_module_url('inscripcion_torneo/index.php');
$fvd_error = '';

$vistaBanderaDelegado = AuthService::isDelegadoAsociacion();

if ($vistaBanderaDelegado) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'retirar_bandera') {
        $tidP = (int) ($_POST['torneo_id'] ?? 0);
        $aidP = (int) ($_POST['atleta_id'] ?? 0);
        $asocP = AuthService::idAsociacion();
        try {
            if ($tidP <= 0 || $aidP <= 0 || $asocP === null || (int) $asocP <= 0) {
                throw new InvalidArgumentException('Datos incompletos para retirar.');
            }
            InscripcionService::retirarInscripcionBandera(fvd_db(), $tidP, (int) $asocP, $aidP);
        } catch (Throwable $e) {
            $fvd_error = $e->getMessage();
            error_log('[inscripcion_torneo bandera] ' . $fvd_error);
        }
        if ($fvd_error === '') {
            header('Location: ' . $selfUrl . ($tidP > 0 ? '?torneo_id=' . $tidP : ''));
            exit;
        }
    }

    $page = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
    $perPage = 25;
    $filterT = isset($_GET['torneo_id']) ? (int) $_GET['torneo_id'] : 0;
    $ctxTor = AuthService::delegadoTorneoContextId();
    if ($ctxTor !== null && $ctxTor > 0) {
        if ($filterT <= 0 || $filterT !== $ctxTor) {
            header('Location: ' . $selfUrl . '?torneo_id=' . $ctxTor);
            exit;
        }
        $filterT = $ctxTor;
    }
    $result = $ctrl->paginateListBandera($page, $perPage, $filterT);
    $torneosF = $ctrl->listTorneosForFilter();
    $vistaBanderaDelegado = true;

    require FVD_MASTER_ROOT . '/includes/layout_header.php';
    include __DIR__ . '/list.view.php';
    require FVD_MASTER_ROOT . '/includes/layout_footer.php';
    exit;
}

if (!$ctrl->tableExists()) {
    require FVD_MASTER_ROOT . '/includes/layout_header.php';
    ?>
    <h1>Inscripciones por torneo</h1>
    <div class="fvd-card">
        <p>La tabla <code>inscripcion_torneo</code> no existe en esta base de datos.</p>
        <p>Ejecute el script: <code>fvdmasteradmin/sql/install_inscripcion_torneo.sql</code></p>
        <p><a href="<?= htmlspecialchars(fvd_module_url('inscripciones/index.php'), ENT_QUOTES, 'UTF-8') ?>">Volver a Inscripciones</a></p>
    </div>
    <?php
    require FVD_MASTER_ROOT . '/includes/layout_footer.php';
    exit;
}

$page = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
$perPage = 25;
$filterT = isset($_GET['torneo_id']) ? (int) $_GET['torneo_id'] : 0;
$result = $ctrl->paginateList($page, $perPage, $filterT);
$torneosF = $ctrl->listTorneosForFilter();
$vistaBanderaDelegado = false;
$fvd_error = '';

require FVD_MASTER_ROOT . '/includes/layout_header.php';
include __DIR__ . '/list.view.php';
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
