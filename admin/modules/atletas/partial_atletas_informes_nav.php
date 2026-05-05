<?php

declare(strict_types=1);

/**
 * Barra de acceso a informes (misma apariencia que en el listado de atletas).
 * Requiere FVD_PROJECT_ROOT y admin/_init (paths, fvd_return_append_to_url).
 */
$rolInformesNav = trim((string) (AuthService::role() ?? ''));
if ($rolInformesNav === AuthService::ROLE_DELEGADO_ASOC) {
    return;
}
if (!function_exists('admin_module_url')) {
    require_once FVD_PROJECT_ROOT . '/config/paths.php';
}
$repBase = admin_module_url('atletas/');
$aidNav = isset($_GET['asociacion_id']) ? (int) $_GET['asociacion_id'] : 0;
$qsRepAsoc = ($aidNav > 0 && $rolInformesNav === AuthService::ROLE_FVD_ADMIN) ? '&asociacion_id=' . $aidNav : '';
$qsRepAsocFirst = ($aidNav > 0 && $rolInformesNav === AuthService::ROLE_FVD_ADMIN) ? '?asociacion_id=' . $aidNav : '';
$uRepAfiliacion = fvd_return_append_to_url($repBase . 'reporte_indicadores.php?marcador=afiliacion' . $qsRepAsoc);
$uRepSolCarnet = fvd_return_append_to_url($repBase . 'reporte_carnets.php' . $qsRepAsocFirst);
$uRepAfilAnual = fvd_return_append_to_url($repBase . 'reporte_indicadores.php?marcador=afiliacion_anualidad' . $qsRepAsoc);
$uRepTr = fvd_return_append_to_url($repBase . 'reporte_traspasos.php' . $qsRepAsocFirst);
$uRepMarcas = fvd_return_append_to_url($repBase . 'reporte_marcas_atletas.php' . $qsRepAsocFirst);
$uRepIndicadores = fvd_return_append_to_url($repBase . 'reporte_indicadores.php' . $qsRepAsocFirst);
?>
<nav class="fvd-atletas-informes-nav no-print" aria-label="Accesos a reportes" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:10px 12px;border-radius:10px;border:1px solid rgba(250,204,21,.38);background:rgba(15,23,42,.55);box-shadow:0 1px 0 rgba(255,255,255,.06) inset;margin:0 0 1rem">
    <a class="fvd-atletas-informes-nav__link" style="font-size:.88rem;font-weight:700;color:#f8fafc;text-decoration:none;padding:7px 13px;border-radius:8px;border:1px solid rgba(148,163,184,.5);background:rgba(30,41,59,.75);display:inline-flex;align-items:center;box-sizing:border-box;line-height:1.2" href="<?= htmlspecialchars($uRepAfiliacion, ENT_QUOTES, 'UTF-8') ?>" title="Filas con atletas.afiliacion = 1">Afiliación</a>
    <a class="fvd-atletas-informes-nav__link" style="font-size:.88rem;font-weight:700;color:#f8fafc;text-decoration:none;padding:7px 13px;border-radius:8px;border:1px solid rgba(148,163,184,.5);background:rgba(30,41,59,.75);display:inline-flex;align-items:center;box-sizing:border-box;line-height:1.2" href="<?= htmlspecialchars($uRepSolCarnet, ENT_QUOTES, 'UTF-8') ?>" title="Solo filas con atletas.carnet = 1">Solicitud carnets</a>
    <a class="fvd-atletas-informes-nav__link" style="font-size:.88rem;font-weight:700;color:#f8fafc;text-decoration:none;padding:7px 13px;border-radius:8px;border:1px solid rgba(148,163,184,.5);background:rgba(30,41,59,.75);display:inline-flex;align-items:center;box-sizing:border-box;line-height:1.2" href="<?= htmlspecialchars($uRepAfilAnual, ENT_QUOTES, 'UTF-8') ?>" title="Afiliación y anualidad en 1">Afiliación y anualidad</a>
    <a class="fvd-atletas-informes-nav__link" style="font-size:.88rem;font-weight:700;color:#f8fafc;text-decoration:none;padding:7px 13px;border-radius:8px;border:1px solid rgba(148,163,184,.5);background:rgba(30,41,59,.75);display:inline-flex;align-items:center;box-sizing:border-box;line-height:1.2" href="<?= htmlspecialchars($uRepTr, ENT_QUOTES, 'UTF-8') ?>" title="Historial de traspasos">Traspasos</a>
    <a class="fvd-atletas-informes-nav__link" style="font-size:.88rem;font-weight:700;color:#f8fafc;text-decoration:none;padding:7px 13px;border-radius:8px;border:1px solid rgba(148,163,184,.5);background:rgba(30,41,59,.75);display:inline-flex;align-items:center;box-sizing:border-box;line-height:1.2" href="<?= htmlspecialchars($uRepMarcas, ENT_QUOTES, 'UTF-8') ?>" title="Atletas con al menos una marca en 1">Marcas activas</a>
    <a class="fvd-atletas-informes-nav__link" style="font-size:.88rem;font-weight:700;color:#f8fafc;text-decoration:none;padding:7px 13px;border-radius:8px;border:1px solid rgba(148,163,184,.5);background:rgba(30,41,59,.75);display:inline-flex;align-items:center;box-sizing:border-box;line-height:1.2" href="<?= htmlspecialchars($uRepIndicadores, ENT_QUOTES, 'UTF-8') ?>" title="Indicadores de servicio (vista completa)">Indicadores</a>
</nav>
