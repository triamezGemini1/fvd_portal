<?php

declare(strict_types=1);

namespace FvdPortal\Views\Delegado;

final class Dashboard
{
    public static function render(
        array $stats,
        array $torneoStats,
        string $asociacionLabel,
        string $userDisplayName,
        string $perfilUrl,
        string $logoutUrl,
        string $panelUrl,
        string $brandLogoUrl,
        array $actionUrls = [],
        string $viteTags = '',
        array $inscripcionesCtx = [],
        bool $adminPortalDelegado = false,
        string $adminPortalCambiarAsocUrl = '',
        array $invitacionesAgrupadas = [],
        int $invitacionesPendientes = 0,
        string $invitacionesAppBase = ''
    ): void
    {
        $cards = [
            ['key' => 'atletas_afiliados', 'title' => 'Atletas afiliados', 'icon' => 'fa-users', 'tone' => 'text-blue-700', 'detailKey' => 'detalle_atletas_afiliados'],
            ['key' => 'afiliaciones', 'title' => 'Afiliaciones', 'icon' => 'fa-id-badge', 'tone' => 'text-indigo-700', 'detailKey' => 'detalle_afiliaciones'],
            ['key' => 'carnets', 'title' => 'Carnets', 'icon' => 'fa-address-card', 'tone' => 'text-violet-700', 'detailKey' => 'detalle_carnets'],
            ['key' => 'anualidades', 'title' => 'Anualidades', 'icon' => 'fa-calendar-check', 'tone' => 'text-amber-700', 'detailKey' => 'detalle_anualidades'],
            ['key' => 'traspasos', 'title' => 'Traspasos', 'icon' => 'fa-right-left', 'tone' => 'text-rose-700', 'detailKey' => 'detalle_traspasos'],
            ['key' => 'inscritos', 'title' => 'Inscritos', 'icon' => 'fa-trophy', 'tone' => 'text-emerald-700', 'detailKey' => 'detalle_inscritos'],
        ];
        $showTorneoBreakdown = count($torneoStats) >= 2;

        header('Content-Type: text/html; charset=UTF-8');
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de gestión — <?= htmlspecialchars($asociacionLabel, ENT_QUOTES, 'UTF-8') ?> — FVD</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWix+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkR4j8R7Y+R0JQXnR6QeJ8V+RaHcRUW2KI2w==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        /* Panel delegado — tema legible, fondo contrastado, acciones por color */
        :root {
            /* Cromática oficial FVD (config/ui_settings.php) */
            --fvd-azul: #2e3092;
            --fvd-azul-osc: #232876;
            --fvd-azul-card: #3a3eb5;
            --fvd-amarillo: #fff200;
            --fvd-rojo: #be123c;
            --fvd-border: rgba(255, 255, 255, .22);
            --fvd-text: #f8fafc;
            --fvd-muted: #dbe5ff;
            --dd-ink: #0f172a;
            --dd-ink-soft: #334155;
            --dd-radius: 18px;
            --dd-shadow: 0 14px 40px rgba(15, 23, 42, .1);
            --dd-shadow-hover: 0 18px 48px rgba(15, 23, 42, .14);
        }
        body { margin: 0; color: var(--dd-ink); font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        .fvd-topbar {
            position: sticky; top: 0; z-index: 100;
            border-bottom: 3px solid var(--fvd-amarillo);
            background: linear-gradient(90deg, var(--fvd-azul) 0%, var(--fvd-azul-osc) 100%);
            color: var(--fvd-text);
            box-shadow: 0 6px 20px rgba(15, 23, 42, .28);
        }
        .fvd-topbar__inner { max-width: 80rem; margin: 0 auto; padding: .65rem 1rem; display: grid; grid-template-columns: 1fr auto 1fr; gap: .75rem; align-items: center; }
        .fvd-topbar__brand { justify-self: start; display: flex; align-items: center; gap: .6rem; min-width: 0; text-decoration: none; color: inherit; }
        .fvd-topbar__fvd-logo { height: 38px; width: auto; object-fit: contain; }
        .fvd-topbar__page-title { font-size: .95rem; font-weight: 700; letter-spacing: .01em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .fvd-topbar__center { justify-self: center; }
        .fvd-topbar__asoc-name { font-size: .78rem; color: #1e1b4b; background: rgba(255, 242, 0, .92); padding: .22rem .55rem; border: 1px solid rgba(46, 48, 146, .35); border-radius: 999px; font-weight: 700; }
        .fvd-topbar__actions { justify-self: end; display: flex; gap: .45rem; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
        .fvd-topbar__user { font-size: .78rem; color: #fff; background: rgba(255, 255, 255, .14); border: 1px solid var(--fvd-border); border-radius: 999px; padding: .28rem .55rem; max-width: 16rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .fvd-topbar__link { text-decoration: none; color: #fff; border: 1px solid var(--fvd-border); border-radius: 8px; padding: .32rem .65rem; font-size: .76rem; font-weight: 600; transition: background .15s ease, border-color .15s ease, color .15s ease; }
        .fvd-topbar__link:hover { border-color: var(--fvd-amarillo); color: var(--fvd-amarillo); background: rgba(255, 255, 255, .1); }

        .fvd-dd-page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: #e8e9f4;
            background-image:
                radial-gradient(900px 480px at 12% -8%, rgba(46, 48, 146, .2), transparent 55%),
                radial-gradient(700px 420px at 92% 8%, rgba(58, 62, 181, .16), transparent 50%),
                radial-gradient(600px 360px at 50% 100%, rgba(190, 18, 60, .06), transparent 45%),
                linear-gradient(180deg, #e8e9f4 0%, #f1f2fb 38%, #f8fafc 100%);
        }
        .fvd-dd-main {
            flex: 1;
            width: 100%;
            max-width: 80rem;
            margin: 0 auto;
            padding: clamp(1rem, 3vw, 1.75rem) clamp(1rem, 3vw, 1.5rem) 2.5rem;
            box-sizing: border-box;
        }

        .fvd-dd-kpis-wrap { margin-bottom: 1.5rem; }
        .fvd-kpi-strip.fvd-dd-kpis {
            border: 1px solid rgba(255, 255, 255, .75);
            background: rgba(255, 255, 255, .88);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: var(--dd-shadow);
            border-radius: var(--dd-radius);
            padding: 14px;
        }
        .fvd-kpi-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 12px; }
        .fvd-kpi-card {
            position: relative;
            border-radius: 14px;
            background: #fff;
            padding: 12px 12px 10px;
            min-height: 112px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(15, 23, 42, .05);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }
        .fvd-kpi-card::before {
            content: '';
            position: absolute;
            top: 0; left: 10px; right: 10px;
            height: 4px;
            border-radius: 0 0 6px 6px;
            background: var(--kpi-accent, #2e3092);
        }
        .fvd-kpi-grid .fvd-kpi-card:nth-child(1) { --kpi-accent: #2e3092; }
        .fvd-kpi-grid .fvd-kpi-card:nth-child(2) { --kpi-accent: #3a3eb5; }
        .fvd-kpi-grid .fvd-kpi-card:nth-child(3) { --kpi-accent: #4c52d4; }
        .fvd-kpi-grid .fvd-kpi-card:nth-child(4) { --kpi-accent: #c9a227; }
        .fvd-kpi-grid .fvd-kpi-card:nth-child(5) { --kpi-accent: #be123c; }
        .fvd-kpi-grid .fvd-kpi-card:nth-child(6) { --kpi-accent: #252f7a; }
        .fvd-kpi-card--link { text-decoration: none; color: inherit; cursor: pointer; }
        .fvd-kpi-card--link:hover {
            transform: translateY(-2px);
            box-shadow: var(--dd-shadow-hover);
            border-color: #cbd5e1;
        }
        .fvd-kpi-title { display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 800; color: var(--dd-ink-soft); line-height: 1.2; text-transform: uppercase; letter-spacing: .08em; }
        .fvd-kpi-title i { color: var(--kpi-accent, #2e3092); opacity: .95; }
        .fvd-kpi-value { font-size: 2.15rem; line-height: 1; font-weight: 900; letter-spacing: -.03em; color: var(--dd-ink) !important; }
        .fvd-kpi-sub { font-size: 10px; font-weight: 700; text-transform: uppercase; color: #94a3b8; letter-spacing: .07em; }
        .fvd-kpi-tournament-list { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 6px; }
        .fvd-kpi-tournament-pill { font-size: 10px; font-weight: 700; border: 1px solid #e2e8f0; color: #475569; border-radius: 999px; padding: 3px 8px; background: #f8fafc; }
        .fvd-kpi-card--link .fvd-kpi-detail { margin-top: 8px; font-size: 11px; font-weight: 800; color: #2e3092; }
        .fvd-kpi-card:not(.fvd-kpi-card--link) .fvd-kpi-detail--muted { margin-top: 8px; font-size: 11px; font-weight: 800; color: #94a3b8; }

        .fvd-dd-actions-grid {
            display: grid;
            gap: 1.35rem;
            margin-top: 1.5rem;
            grid-template-columns: 1fr;
        }
        @media (min-width: 1024px) {
            .fvd-dd-actions-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); align-items: stretch; }
        }

        .fvd-dd-card {
            border-radius: var(--dd-radius);
            overflow: hidden;
            background: #fff;
            border: 1px solid rgba(226, 232, 240, .95);
            box-shadow: var(--dd-shadow);
            display: flex;
            flex-direction: column;
            min-height: 100%;
            transition: box-shadow .2s ease, transform .2s ease;
        }
        .fvd-dd-card:hover { box-shadow: var(--dd-shadow-hover); }
        .fvd-dd-card__head {
            padding: 1rem 1.2rem;
            color: #fff;
            display: flex;
            align-items: center;
            gap: .55rem;
        }
        .fvd-dd-card__head h3 { margin: 0; font-size: 1.02rem; font-weight: 800; letter-spacing: .01em; text-shadow: 0 1px 2px rgba(0, 0, 0, .12); }
        .fvd-dd-card__head i { font-size: 1.1rem; opacity: .95; }
        .fvd-dd-card--gestion .fvd-dd-card__head { background: linear-gradient(128deg, #2e3092 0%, #3a3eb5 52%, #4c52d4 100%); }
        .fvd-dd-card--torneos .fvd-dd-card__head { background: linear-gradient(128deg, #232876 0%, #2e3092 45%, #c9a227 100%); }
        .fvd-dd-card--finanzas .fvd-dd-card__head { background: linear-gradient(128deg, #7f1d1d 0%, #be123c 48%, #e85a75 100%); }
        .fvd-dd-card__body {
            flex: 1;
            padding: 1.2rem 1.25rem 1.35rem;
            display: flex;
            flex-direction: column;
            gap: .85rem;
            background: linear-gradient(180deg, #fafbfc 0%, #ffffff 55%);
        }

        .fvd-dd-torneo {
            background: linear-gradient(135deg, #f1f5f9 0%, #fff 100%);
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: .85rem 1rem;
            margin-bottom: .15rem;
        }
        .fvd-dd-torneo__label { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; color: #5c6199; }
        .fvd-dd-torneo__name { margin: .4rem 0 0; font-size: 1rem; font-weight: 800; color: var(--dd-ink); line-height: 1.35; word-break: break-word; }

        .fvd-dd-lead { margin: 0 0 .15rem; font-size: .8125rem; font-weight: 600; color: var(--dd-ink-soft); line-height: 1.5; }

        .fvd-dd-actions-col { display: flex; flex-direction: column; gap: 12px; margin-top: auto; }

        /* Botones de acción: cada uno con color propio, texto claro sobre tinta */
        .fvd-dd-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 3.55rem;
            padding: .95rem 1rem;
            border: none;
            border-radius: 13px;
            font-size: .98rem;
            font-weight: 800;
            line-height: 1.3;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            font-family: inherit;
            color: #fff;
            letter-spacing: .01em;
            box-shadow: 0 6px 18px rgba(15, 23, 42, .18);
            transition: transform .16s ease, box-shadow .16s ease, filter .16s ease;
        }
        .fvd-dd-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 26px rgba(15, 23, 42, .22); filter: brightness(1.06); }
        .fvd-dd-btn:active { transform: translateY(0); filter: brightness(.98); }
        .fvd-dd-btn:focus-visible { outline: 3px solid var(--fvd-amarillo, #fff200); outline-offset: 3px; }

        .fvd-dd-btn--gestion {
            min-height: 4.35rem;
            background: linear-gradient(135deg, #232876 0%, #2e3092 50%, #3a3eb5 100%);
            box-shadow: 0 8px 22px rgba(46, 48, 146, .35);
        }
        .fvd-dd-btn--gestion:hover { box-shadow: 0 12px 28px rgba(46, 48, 146, .42); }

        .fvd-dd-btn--inscripciones {
            background: linear-gradient(135deg, #2e3092 0%, #4c52d4 55%, #5c6ae0 100%);
            box-shadow: 0 8px 22px rgba(46, 48, 146, .32);
        }
        .fvd-dd-btn--inscripciones:hover { box-shadow: 0 12px 28px rgba(46, 48, 146, .38); }

        .fvd-dd-btn--admin-insc {
            background: linear-gradient(135deg, #1a1d5c 0%, #2e3092 50%, #3a3eb5 100%);
            color: #fff;
            box-shadow: 0 8px 22px rgba(26, 29, 92, .3);
        }
        .fvd-dd-btn--admin-insc:hover { box-shadow: 0 12px 28px rgba(26, 29, 92, .36); }

        .fvd-dd-btn--fin-deuda {
            background: linear-gradient(135deg, #7f1d1d 0%, #be123c 50%, #dc2626 100%);
            box-shadow: 0 8px 22px rgba(127, 29, 29, .3);
        }
        .fvd-dd-btn--fin-deuda:hover { box-shadow: 0 12px 28px rgba(127, 29, 29, .36); }

        .fvd-dd-btn--fin-pagos {
            background: linear-gradient(135deg, #854d0e 0%, #ca8a04 45%, #eab308 100%);
            box-shadow: 0 8px 22px rgba(133, 77, 14, .28);
        }
        .fvd-dd-btn--fin-pagos:hover { box-shadow: 0 12px 28px rgba(133, 77, 14, .34); }

        @media (max-width: 1366px) {
            .fvd-kpi-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        @media (max-width: 820px) {
            .fvd-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 520px) {
            .fvd-kpi-grid { grid-template-columns: 1fr; }
        }
        .fvd-dd-admin-portal-banner {
            background: #fef3c7;
            border-bottom: 2px solid #f59e0b;
            color: #78350f;
            padding: .55rem 1rem;
            font-size: .8125rem;
            font-weight: 700;
            text-align: center;
            line-height: 1.45;
        }
        .fvd-dd-admin-portal-banner a {
            color: #92400e;
            text-decoration: underline;
            font-weight: 800;
            margin-left: .35rem;
        }
        .fvd-dd-torneo-aviso {
            margin: 0 0 1.1rem;
            padding: .75rem 1rem;
            border-radius: 12px;
            border: 1px solid #f59e0b;
            background: #fffbeb;
            color: #78350f;
            font-size: .875rem;
            font-weight: 700;
            line-height: 1.5;
            box-shadow: 0 4px 14px rgba(245, 158, 11, .12);
        }
    </style>
    <?php if ($viteTags !== ''): ?>
    <?= $viteTags ?>
    <?php endif; ?>
</head>
<body class="antialiased fvd-dd-body" style="-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;">
<div class="fvd-dd-page">
    <header class="fvd-topbar">
        <div class="fvd-topbar__inner">
            <a class="fvd-topbar__brand" href="<?= htmlspecialchars($panelUrl, ENT_QUOTES, 'UTF-8') ?>" title="Ir al panel">
                <?php if ($brandLogoUrl !== ''): ?>
                    <img class="fvd-topbar__fvd-logo" src="<?= htmlspecialchars($brandLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="FVD">
                <?php endif; ?>
                <span class="fvd-topbar__page-title">FVD Master Admin</span>
            </a>
            <div class="fvd-topbar__center">
                <span class="fvd-topbar__asoc-name"><?= htmlspecialchars($asociacionLabel, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="fvd-topbar__actions">
                <span class="fvd-topbar__user"><?= htmlspecialchars($userDisplayName, ENT_QUOTES, 'UTF-8') ?></span>
                <a class="fvd-topbar__link" href="<?= htmlspecialchars($perfilUrl, ENT_QUOTES, 'UTF-8') ?>">Mi perfil</a>
                <a class="fvd-topbar__link" href="<?= htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8') ?>">Cerrar sesión</a>
            </div>
        </div>
    </header>
        <?php if ($adminPortalDelegado): ?>
        <div class="fvd-dd-admin-portal-banner" role="status">
            Está viendo este panel como administrador general, con los datos de la asociación indicada.
            <?php if ($adminPortalCambiarAsocUrl !== ''): ?>
                <a href="<?= htmlspecialchars($adminPortalCambiarAsocUrl, ENT_QUOTES, 'UTF-8') ?>">Cambiar asociación</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <main class="fvd-dd-main">
        <?php
        $appBaseInv = rtrim($invitacionesAppBase, '/');
        ?>
        <?php if (!$adminPortalDelegado && $appBaseInv !== '' && is_array($invitacionesAgrupadas) && $invitacionesAgrupadas !== []): ?>
        <section class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" aria-label="Invitaciones a torneos" id="fvd-deleg-invites-dash">
            <h2 class="mb-1 text-sm font-extrabold uppercase tracking-wide text-slate-800">Invitaciones a torneos
                <?php if ($invitacionesPendientes > 0): ?>
                    <span class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-950"><?= (int) $invitacionesPendientes ?> pendiente(s)</span>
                <?php endif; ?>
            </h2>
            <p class="mb-3 text-xs font-medium text-slate-600">Si los campeonatos están <strong>asociados</strong> (mismo código de grupo), verá <strong>una sola</strong> invitación; el contexto del torneo y del campeonato se carga al entrar para usar inscripciones, administración de inscritos y finanzas.</p>
            <ul class="m-0 list-none space-y-2 p-0">
                <?php foreach ($invitacionesAgrupadas as $nf): ?>
                    <?php
                    $nid = (int) ($nf['id'] ?? 0);
                    $esGrupo = !empty($nf['es_grupo_agrupado']);
                    $nEnGrupo = (int) ($nf['n_en_grupo'] ?? 0);
                    $tn = htmlspecialchars((string) ($nf['torneo_nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $ramaSub = trim((string) ($nf['rama_subtitulo'] ?? ''));
                    $fd = htmlspecialchars(substr((string) ($nf['fechator'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8');
                    $sinAbrir = empty($nf['visto_en']);
                    $entrar = $appBaseInv . '/fvdmasteradmin/delegado_entrar_torneo.php?notif_id=' . $nid;
                    $pdf = $appBaseInv . '/fvdmasteradmin/delegado_invitacion_pdf.php?notif_id=' . $nid;
                    ?>
                    <li class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm">
                        <div class="min-w-0 flex-1">
                            <span class="font-bold text-slate-900"><?= $tn ?></span>
                            <?php if ($esGrupo && $nEnGrupo > 1): ?>
                                <span class="ml-2 rounded bg-indigo-100 px-1.5 py-0.5 text-[0.65rem] font-bold text-indigo-950">Grupo · <?= $nEnGrupo ?> categorías</span>
                            <?php endif; ?>
                            <?php if ($ramaSub !== ''): ?>
                                <span class="mt-0.5 block text-xs text-slate-600"><?= htmlspecialchars($ramaSub, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                            <span class="mt-0.5 block text-xs text-slate-500"><?= $fd ?></span>
                            <?php if ($sinAbrir): ?><span class="mt-1 inline-block rounded bg-amber-200 px-1.5 py-0.5 text-[0.7rem] font-bold text-amber-950">Sin abrir</span><?php endif; ?>
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2">
                            <?php if (!empty($nf['invitacion_archivo'])): ?>
                                <a class="inline-flex min-h-[2.5rem] items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800" href="<?= htmlspecialchars($pdf, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">PDF</a>
                            <?php endif; ?>
                            <a class="inline-flex min-h-[2.5rem] items-center justify-center rounded-lg bg-[#2e3092] px-3 text-xs font-bold text-white" href="<?= htmlspecialchars($entrar, ENT_QUOTES, 'UTF-8') ?>">Panel del torneo</a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>
        <?php
        $avisoTorneoPanel = trim((string) ($inscripcionesCtx['aviso_torneo_panel'] ?? ''));
        if ($avisoTorneoPanel !== ''): ?>
            <div class="fvd-dd-torneo-aviso" role="alert">
                <?= htmlspecialchars($avisoTorneoPanel, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <section class="fvd-kpi-strip fvd-dd-kpis fvd-dd-kpis-wrap">
            <div class="fvd-kpi-grid">
                <?php foreach ($cards as $card): ?>
                    <?php
                    $key = (string) $card['key'];
                    $value = (int) ($stats[$key] ?? 0);
                    ?>
                    <?php $detailUrl = (string) ($actionUrls[(string) ($card['detailKey'] ?? '')] ?? '#'); ?>
                    <?php
                    $hasDetail = $detailUrl !== '' && $detailUrl !== '#';
                    $cardTag = $hasDetail ? 'a' : 'article';
                    ?>
                    <<?= $cardTag ?> class="fvd-kpi-card<?= $hasDetail ? ' fvd-kpi-card--link hover:bg-slate-50 transition-all' : '' ?>"<?= $hasDetail ? ' href="' . htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                        <div class="fvd-kpi-title">
                            <i class="fas <?= htmlspecialchars((string) $card['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                            <span><?= htmlspecialchars((string) $card['title'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <p class="fvd-kpi-value"><?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="fvd-kpi-sub">Total general</p>
                        <?php if ($showTorneoBreakdown): ?>
                            <div class="fvd-kpi-tournament-list">
                                <?php foreach ($torneoStats as $ts): ?>
                                    <span class="fvd-kpi-tournament-pill">T#<?= (int) ($ts['torneo_id'] ?? 0) ?>: <?= (int) ($ts[$key] ?? 0) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($hasDetail): ?>
                        <span class="fvd-kpi-detail">Ver detalle</span>
                        <?php else: ?>
                        <span class="fvd-kpi-detail--muted">Indicador informativo</span>
                        <?php endif; ?>
                    </<?= $cardTag ?>>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="fvd-dd-actions-grid">
            <article class="fvd-dd-card fvd-dd-card--gestion">
                <div class="fvd-dd-card__head">
                    <i class="fas fa-user-shield" aria-hidden="true"></i>
                    <h3>Gestión administrativa</h3>
                </div>
                <div class="fvd-dd-card__body">
                    <a class="fvd-dd-btn fvd-dd-btn--gestion"
                       href="<?= htmlspecialchars((string) ($actionUrls['afiliaciones'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>"
                       title="Listado de atletas: afiliación, solicitud de carnets y transferencias">
                        Afiliación, solicitud de carnets y transferencias
                    </a>
                </div>
            </article>

            <article class="fvd-dd-card fvd-dd-card--torneos">
                <div class="fvd-dd-card__head">
                    <i class="fas fa-trophy" aria-hidden="true"></i>
                    <h3>Torneos y eventos</h3>
                </div>
                <div class="fvd-dd-card__body">
                    <?php
                    $tnAct = trim((string) ($inscripcionesCtx['torneo_nombre'] ?? ''));
                    if ($tnAct === '') {
                        $tnAct = isset($torneoStats[0]['torneo_nombre']) ? (string) $torneoStats[0]['torneo_nombre'] : 'Sin torneo activo';
                    }
                    ?>
                    <div class="fvd-dd-torneo">
                        <div class="fvd-dd-torneo__label">Torneo actual</div>
                        <p class="fvd-dd-torneo__name"><?= htmlspecialchars($tnAct, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <?php
                    $inscDest = trim((string) ($inscripcionesCtx['url_destino'] ?? ''));
                    $inscTid = (int) ($inscripcionesCtx['torneo_id'] ?? 0);
                    $inscCamp = (int) ($inscripcionesCtx['campeonato_en_url'] ?? 0);
                    $inscHref = '';
                    if ($inscDest !== '' && $inscTid > 0 && $inscCamp > 0) {
                        $inscHref = $inscDest . (str_contains($inscDest, '#') ? '' : '#fvd-insc-sitio-panel');
                    }
                    ?>
                    <div class="fvd-dd-actions-col">
                        <?php if ($inscHref !== ''): ?>
                            <a
                                class="fvd-dd-btn fvd-dd-btn--inscripciones"
                                href="<?= htmlspecialchars($inscHref, ENT_QUOTES, 'UTF-8') ?>"
                                title="Abre el formulario de inscripción al torneo (línea de cédula y tablas Disponibles / Inscritos, como en el sitio Mis Torneos)"
                            >Inscripciones</a>
                        <?php else: ?>
                            <span
                                class="fvd-dd-btn fvd-dd-btn--inscripciones"
                                style="opacity:0.55;cursor:not-allowed"
                                title="<?= $inscTid <= 0
                                    ? 'Sin torneo activo: vea el aviso superior.'
                                    : 'Falta campeonato en contexto: vea el aviso superior.' ?>"
                            >Inscripciones</span>
                        <?php endif; ?>
                        <a class="fvd-dd-btn fvd-dd-btn--admin-insc" href="<?= htmlspecialchars((string) ($actionUrls['administrar_inscripciones'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>">
                            Administración de inscritos
                        </a>
                    </div>
                </div>
            </article>

            <article class="fvd-dd-card fvd-dd-card--finanzas">
                <div class="fvd-dd-card__head">
                    <i class="fas fa-wallet" aria-hidden="true"></i>
                    <h3>Finanzas</h3>
                </div>
                <div class="fvd-dd-card__body">
                    <p class="fvd-dd-lead">Deuda generada y pagos registrados para su asociación en el torneo en contexto (módulos oficiales).</p>
                    <a class="fvd-dd-btn fvd-dd-btn--fin-deuda" href="<?= htmlspecialchars((string) ($actionUrls['finanzas_situacion'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>"
                       title="Estado de cuenta, conceptos y cuentas generadas (deuda_asociaciones)">
                        Situación en el torneo y cuentas generadas
                    </a>
                    <a class="fvd-dd-btn fvd-dd-btn--fin-pagos" href="<?= htmlspecialchars((string) ($actionUrls['finanzas_pagos'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>"
                       title="Registrar y consultar pagos (relación de pagos)">
                        Pagos
                    </a>
                </div>
            </article>
        </section>
    </main>
</div>
</body>
</html>
        <?php
    }
}
