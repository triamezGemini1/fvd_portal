<?php

declare(strict_types=1);

/**
 * Vista única: relación de campeonatos del club (mismo grupo_evento_id).
 * Delegado o administrador de asociación; requiere columna grupo_evento_id en torneosact.
 */

require_once __DIR__ . '/services/AuthService.php';
require_once dirname(__DIR__) . '/config/paths.php';

AuthService::ensureSession();
AuthService::requireLogin();

if (!AuthService::checkAccess([AuthService::ROLE_DELEGADO_ASOC, AuthService::ROLE_ASO_ADMIN])) {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Acceso</title></head><body><p>No tiene permisos para esta sección.</p></body></html>';
    exit;
}

$asocId = (int) (AuthService::idAsociacion() ?? 0);
if ($asocId <= 0) {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Acceso</title></head><body><p>Su usuario no tiene asociación asignada.</p></body></html>';
    exit;
}

require_once dirname(__DIR__) . '/src/Services/FvdAdminService.php';

$svc = new FvdAdminService();
$dashboardUrl = url('fvdmasteradmin/delegado_dashboard_new.php');

if (isset($_GET['set_ctx']) && AuthService::isDelegadoAsociacion()) {
    $tidCtx = (int) $_GET['set_ctx'];
    if ($tidCtx > 0) {
        $allowed = false;
        foreach (array_merge($svc->relacionTorneosMasterRelacionados($asocId), $svc->relacionTorneosMasterCandidatos($asocId)) as $rw) {
            if ((int) ($rw['torneo'] ?? 0) === $tidCtx) {
                $allowed = true;
                break;
            }
        }
        if ($allowed) {
            AuthService::setDelegadoTorneoContext($tidCtx);
        }
    }
    header('Location: ' . url('fvdmasteradmin/relacion_torneos_master.php'));
    exit;
}

$fvd_flash = '';
$fvd_err = '';
if (!empty($_SESSION['fvd_rtm_flash'])) {
    $fvd_flash = (string) $_SESSION['fvd_rtm_flash'];
    unset($_SESSION['fvd_rtm_flash']);
}
if (!empty($_SESSION['fvd_rtm_err'])) {
    $fvd_err = (string) $_SESSION['fvd_rtm_err'];
    unset($_SESSION['fvd_rtm_err']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'rtm_vincular') {
    $ids = $_POST['torneos_ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }
    $nombre = trim((string) ($_POST['nombre_nominal_campeonato'] ?? ''));
    try {
        $svc->torneosRelacionGrupoAplicar($ids, $nombre);
        $_SESSION['fvd_rtm_flash'] = 'Relación aplicada correctamente.';
    } catch (Throwable $e) {
        $_SESSION['fvd_rtm_err'] = $e->getMessage();
    }
    header('Location: ' . url('fvdmasteradmin/relacion_torneos_master.php'));
    exit;
}

$candidatos = $svc->relacionTorneosMasterCandidatos($asocId);
$relacionados = $svc->relacionTorneosMasterRelacionados($asocId);
$ctxAct = AuthService::isDelegadoAsociacion() ? (int) (AuthService::delegadoTorneoContextId() ?? 0) : 0;

$fvd_estatus_torneo = static function ($v): string {
    $n = (int) $v;
    if ($n === 0) {
        return 'Borrador / cierre';
    }
    if ($n === 1) {
        return 'En proceso';
    }
    if ($n === 2) {
        return 'Finalizado';
    }

    return (string) $n;
};

$fvd_page_title = 'Relacionar campeonatos';
$fvd_sidebar_active = 'relacion_torneos_master';
$fvd_head_extra_html = '<script src="https://cdn.tailwindcss.com"></script>';
require __DIR__ . '/includes/layout_header.php';
?>

<div class="overflow-x-auto p-4 text-black">
    <h1 class="text-2xl font-bold text-black mb-2">Torneos relacionados — maestro</h1>
    <p class="text-sm font-bold text-black mb-4 max-w-3xl">
        Asociación en sesión: campeonatos que organiza o con convocatoria. Marque al menos dos filas y un nombre nominal para unificar en un mismo grupo de evento.
    </p>

    <?php if ($fvd_flash !== ''): ?>
        <p class="fvd-mod-msg mb-3"><?= htmlspecialchars($fvd_flash, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($fvd_err !== ''): ?>
        <p class="fvd-mod-msg fvd-tf-form-page__err mb-3"><?= htmlspecialchars($fvd_err, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <?php if (!$svc->torneosactGrupoEventoColumnExists()): ?>
        <p class="fvd-mod-msg">Falta la columna <code>grupo_evento_id</code> en <code>torneosact</code>. Ejecute los scripts SQL del módulo.</p>
    <?php else: ?>
        <?php if ($relacionados !== []): ?>
            <div class="mb-4 p-3 bg-white border-4 border-black">
                <p class="text-black font-bold text-sm mb-2">Torneos ya relacionados (grupo) — clic para fijar contexto del panel</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($relacionados as $rr): ?>
                        <?php
                        $tidR = (int) ($rr['torneo'] ?? 0);
                        if ($tidR <= 0) {
                            continue;
                        }
                        $isCtx = $ctxAct === $tidR;
                        $hrefCtx = htmlspecialchars(url('fvdmasteradmin/relacion_torneos_master.php?set_ctx=' . $tidR), ENT_QUOTES, 'UTF-8');
                        ?>
                        <?php if (AuthService::isDelegadoAsociacion()): ?>
                            <a href="<?= $hrefCtx ?>"
                               class="inline-flex items-center px-3 py-2 text-black font-bold border-4 border-black bg-white hover:bg-gray-100 <?= $isCtx ? 'ring-4 ring-yellow-400' : '' ?>"
                               title="Grupo #<?= (int) ($rr['grupo_evento_id'] ?? 0) ?>">
                                <?= htmlspecialchars((string) ($rr['nombre'] ?? 'Torneo'), ENT_QUOTES, 'UTF-8') ?>
                                <span class="ml-1 text-xs font-bold">#<?= $tidR ?></span>
                            </a>
                        <?php else: ?>
                            <span class="inline-flex items-center px-3 py-2 text-black font-bold border-4 border-black bg-white">
                                <?= htmlspecialchars((string) ($rr['nombre'] ?? 'Torneo'), ENT_QUOTES, 'UTF-8') ?>
                                <span class="ml-1 text-xs font-bold">#<?= $tidR ?></span>
                            </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php if ($ctxAct > 0): ?>
                    <p class="text-xs font-bold text-black mt-2">Contexto activo del panel: torneo #<?= $ctxAct ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= htmlspecialchars(url('fvdmasteradmin/relacion_torneos_master.php'), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="_action" value="rtm_vincular">

            <div class="mb-4 max-w-xl">
                <label for="rtm_nom" class="block text-black font-bold text-sm mb-1">Nombre nominal del campeonato (obligatorio)</label>
                <input type="text" name="nombre_nominal_campeonato" id="rtm_nom" required minlength="2" maxlength="255"
                       class="w-full border-4 border-black p-2 text-black font-bold bg-white"
                       placeholder="Ej. Nacional Miranda 2026">
            </div>

            <?php if ($candidatos === []): ?>
                <p class="text-black font-bold">No hay campeonatos disponibles para vincular con su asociación.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border-4 border-black">
                        <thead>
                        <tr class="bg-black text-white">
                            <th class="p-3 border-2 border-black text-left font-bold">SEL.</th>
                            <th class="p-3 border-2 border-black text-left font-bold">TORNEOS DISPONIBLES</th>
                            <th class="p-3 border-2 border-black text-left font-bold">FECHA</th>
                            <th class="p-3 border-2 border-black text-left font-bold">ESTATUS</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($candidatos as $r): ?>
                            <?php
                            $tid = (int) ($r['torneo'] ?? 0);
                            if ($tid <= 0) {
                                continue;
                            }
                            $fd = (string) ($r['fechator'] ?? '');
                            $fdFmt = $fd !== '' ? date('d/m/Y', strtotime($fd . ' 12:00:00')) : '—';
                            ?>
                            <tr class="hover:bg-slate-100">
                                <td class="p-3 border-2 border-black text-center">
                                    <input type="checkbox" name="torneos_ids[]" value="<?= $tid ?>" class="w-6 h-6 accent-black">
                                </td>
                                <td class="p-3 border-2 border-black text-black font-bold text-lg">
                                    <?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    <span class="block text-xs font-bold text-black">ID <?= $tid ?><?php
                                    $g = (int) ($r['grupo_evento_id'] ?? 0);
                                    echo $g > 0 ? ' · Grupo #' . $g : '';
                                    ?></span>
                                </td>
                                <td class="p-3 border-2 border-black text-black font-bold"><?= htmlspecialchars($fdFmt, ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="p-3 border-2 border-black text-black font-bold"><?= htmlspecialchars($fvd_estatus_torneo($r['estatus'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-between items-center flex-wrap gap-4">
                    <a href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-black font-bold border-4 border-black p-3 bg-white hover:bg-gray-100 inline-block">
                        ← VOLVER
                    </a>
                    <button type="submit"
                            class="bg-black text-white font-bold py-4 px-8 border-4 border-black hover:bg-white hover:text-black transition-all text-xl">
                        VINCULAR TORNEOS SELECCIONADOS
                    </button>
                </div>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>

<?php
require __DIR__ . '/includes/layout_footer.php';
