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
        string $invitacionesAppBase = '',
        array $delegadoListaTorneos = [],
        int $delegadoTorneoActivoId = 0,
        string $delegadoTorneoPickUrl = '',
        bool $vistaOperativaSimple = false,
        int $novedadesDelegadosUnread = 0,
        string $delegadoNotifPollUrl = '',
        bool $suppressMasterShellChrome = false,
        array $afiliadosAfiliacionPorGenero = []
    ): void
    {
        $vistaOperativaSimple = $vistaOperativaSimple && !$adminPortalDelegado;
        $afiliadosAfiliacionPorGenero += ['M' => 0, 'F' => 0, 'O' => 0];
        $nAfM = (int) ($afiliadosAfiliacionPorGenero['M'] ?? 0);
        $nAfF = (int) ($afiliadosAfiliacionPorGenero['F'] ?? 0);
        $nAfO = (int) ($afiliadosAfiliacionPorGenero['O'] ?? 0);
        $urlAfiliadosCab = trim((string) ($actionUrls['detalle_atletas_afiliados'] ?? ''));
        $cards = [
            ['key' => 'afiliaciones', 'title' => 'Afiliaciones', 'icon' => 'fa-id-badge', 'tone' => 'text-indigo-700', 'detailKey' => 'detalle_afiliaciones'],
            ['key' => 'carnets', 'title' => 'Carnets', 'icon' => 'fa-address-card', 'tone' => 'text-violet-700', 'detailKey' => 'detalle_carnets'],
            ['key' => 'anualidades', 'title' => 'Anualidades', 'icon' => 'fa-calendar-check', 'tone' => 'text-amber-700', 'detailKey' => 'detalle_anualidades'],
            ['key' => 'traspasos', 'title' => 'Traspasos', 'icon' => 'fa-right-left', 'tone' => 'text-rose-700', 'detailKey' => 'detalle_traspasos'],
            ['key' => 'inscritos', 'title' => 'Inscritos', 'icon' => 'fa-trophy', 'tone' => 'text-emerald-700', 'detailKey' => 'detalle_inscritos'],
        ];
        $showTorneoBreakdown = count($torneoStats) >= 2;
        $kpiAlcanceLabel = $delegadoTorneoActivoId > 0
            ? 'Torneo y rama seleccionados'
            : 'Totales del club (sin torneo en contexto)';

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
        .fvd-topbar__center { justify-self: center; min-width: 0; }
        .fvd-topbar__center--with-afiliados {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .32rem;
            max-width: min(100%, 34rem);
        }
        .fvd-topbar__asoc-name { font-size: .78rem; color: #1e1b4b; background: rgba(255, 242, 0, .92); padding: .22rem .55rem; border: 1px solid rgba(46, 48, 146, .35); border-radius: 999px; font-weight: 700; }
        .fvd-topbar__afiliados-sexo {
            display: flex;
            flex-wrap: wrap;
            gap: .28rem;
            align-items: center;
            justify-content: center;
        }
        .fvd-topbar__afiliados-pill {
            font-size: .65rem;
            font-weight: 800;
            letter-spacing: .02em;
            color: #1e1b4b;
            background: rgba(255, 255, 255, .94);
            border: 1px solid rgba(46, 48, 146, .38);
            border-radius: 999px;
            padding: .12rem .42rem;
            text-decoration: none;
            white-space: nowrap;
            transition: background .15s ease, border-color .15s ease;
        }
        .fvd-topbar__afiliados-pill:hover {
            background: #fff;
            border-color: var(--fvd-amarillo);
        }
        .fvd-topbar__afiliados-pill span { font-weight: 900; color: #0f172a; margin-left: .12rem; }
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
        /* Cinco KPI en una sola fila; en pantallas estrechas scroll horizontal */
        .fvd-dd-kpis .fvd-kpi-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 10px;
        }
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
        .fvd-kpi-grid .fvd-kpi-card:nth-child(1) { --kpi-accent: #3a3eb5; }
        .fvd-kpi-grid .fvd-kpi-card:nth-child(2) { --kpi-accent: #4c52d4; }
        .fvd-kpi-grid .fvd-kpi-card:nth-child(3) { --kpi-accent: #c9a227; }
        .fvd-kpi-grid .fvd-kpi-card:nth-child(4) { --kpi-accent: #be123c; }
        .fvd-kpi-grid .fvd-kpi-card:nth-child(5) { --kpi-accent: #252f7a; }
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
        .fvd-dd-btn--disabled,
        .fvd-dd-btn--disabled:hover,
        .fvd-dd-btn--disabled:active {
            opacity: .55;
            cursor: not-allowed;
            transform: none;
            filter: none;
            box-shadow: none;
        }

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

        @media (max-width: 1100px) {
            .fvd-dd-kpis .fvd-kpi-grid {
                grid-template-columns: repeat(5, minmax(118px, 1fr));
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 4px;
            }
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
        .fvd-dd-torneos-strip {
            margin: 0 0 1.25rem;
            padding: 1rem 1.1rem 1.05rem;
            border-radius: var(--dd-radius);
            border: 1px solid rgba(46, 48, 146, .22);
            background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
            box-shadow: 0 4px 16px rgba(15, 23, 42, .06);
            text-align: center;
        }
        .fvd-dd-torneos-strip__head {
            margin-bottom: .65rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .fvd-dd-torneos-strip__title {
            display: block;
            font-size: .72rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: #2e3092;
            margin-bottom: .2rem;
        }
        .fvd-dd-torneos-strip__hint {
            margin: 0;
            font-size: .78rem;
            font-weight: 600;
            color: var(--dd-ink-soft);
            line-height: 1.45;
        }
        .fvd-dd-torneos-strip__list {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            align-items: stretch;
            justify-content: center;
            max-height: 11rem;
            overflow-y: auto;
            padding-top: .15rem;
        }
        .fvd-dd-torneo-chip {
            flex: 0 1 auto;
            min-width: 0;
            max-width: 100%;
            text-decoration: none;
            text-align: left;
            padding: .55rem .75rem .5rem;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            background: #fff;
            color: var(--dd-ink);
            font-size: .8125rem;
            font-weight: 700;
            line-height: 1.3;
            transition: border-color .15s ease, box-shadow .15s ease, background .15s ease, color .15s ease;
            cursor: pointer;
            box-sizing: border-box;
        }
        .fvd-dd-torneo-chip:hover {
            border-color: rgba(46, 48, 146, .45);
            box-shadow: 0 4px 12px rgba(46, 48, 146, .12);
        }
        .fvd-dd-torneo-chip--activo {
            border-color: var(--fvd-amarillo);
            background: linear-gradient(135deg, #2e3092 0%, #3a3eb5 100%);
            color: #fff;
            box-shadow: 0 6px 18px rgba(46, 48, 146, .28);
        }
        .fvd-dd-torneo-chip--activo:hover {
            border-color: #fde047;
            color: #fff;
        }
        .fvd-dd-torneo-chip__id {
            display: block;
            font-size: .62rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #64748b;
            margin-bottom: .15rem;
        }
        .fvd-dd-torneo-chip--activo .fvd-dd-torneo-chip__id { color: rgba(255, 242, 0, .9); }
        .fvd-dd-torneo-chip__name { word-break: break-word; }
        .fvd-dd-torneo-chip__meta {
            display: block;
            margin-top: .25rem;
            font-size: .65rem;
            font-weight: 600;
            color: #94a3b8;
        }
        .fvd-dd-torneo-chip--activo .fvd-dd-torneo-chip__meta { color: rgba(248, 250, 252, .85); }
        .fvd-dd-torneo-chip__genero {
            display: inline-block;
            margin-top: .22rem;
            font-size: .62rem;
            font-weight: 800;
            letter-spacing: .04em;
            color: #2e3092;
            border: 1px solid rgba(46, 48, 146, .28);
            border-radius: 6px;
            padding: .1rem .32rem;
            background: #eef2ff;
        }
        .fvd-dd-torneo-chip--activo .fvd-dd-torneo-chip__genero {
            color: #fef9c3;
            border-color: rgba(255, 255, 255, .45);
            background: rgba(255, 255, 255, .12);
        }
        .fvd-dd-torneos-strip--empty {
            margin: 0;
            font-size: .8125rem;
            font-weight: 600;
            color: #94a3b8;
            line-height: 1.45;
        }
        .fvd-topbar__link--notif { position: relative; padding-right: .85rem !important; }
        .fvd-topbar__notif-dot {
            position: absolute;
            top: .12rem;
            right: .2rem;
            width: .45rem;
            height: .45rem;
            border-radius: 999px;
            background: #f43f5e;
            box-shadow: 0 0 0 2px rgba(15, 23, 42, .35);
            animation: fvd-dd-notif-pulse 1.6s ease-in-out infinite;
        }
        @keyframes fvd-dd-notif-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: .75; transform: scale(1.15); }
        }
    </style>
    <?php if ($viteTags !== ''): ?>
    <?= $viteTags ?>
    <?php endif; ?>
</head>
<body class="antialiased fvd-dd-body<?= $suppressMasterShellChrome ? ' fvd-dd-body--embed-master' : '' ?>" style="-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;">
<div class="fvd-dd-page">
    <?php if (!$suppressMasterShellChrome): ?>
    <header class="fvd-topbar">
        <div class="fvd-topbar__inner">
            <a class="fvd-topbar__brand" href="<?= htmlspecialchars($panelUrl, ENT_QUOTES, 'UTF-8') ?>" title="Ir al panel">
                <?php if ($brandLogoUrl !== ''): ?>
                    <img class="fvd-topbar__fvd-logo" src="<?= htmlspecialchars($brandLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="FVD">
                <?php endif; ?>
                <span class="fvd-topbar__page-title">FVD Master Admin</span>
            </a>
            <div class="fvd-topbar__center fvd-topbar__center--with-afiliados">
                <span class="fvd-topbar__asoc-name"><?= htmlspecialchars($asociacionLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ($urlAfiliadosCab !== ''): ?>
                <div class="fvd-topbar__afiliados-sexo" role="group" aria-label="Afiliados por género (club)">
                    <a class="fvd-topbar__afiliados-pill" href="<?= htmlspecialchars($urlAfiliadosCab, ENT_QUOTES, 'UTF-8') ?>" title="Listado de atletas — masculino">M<span><?= $nAfM ?></span></a>
                    <a class="fvd-topbar__afiliados-pill" href="<?= htmlspecialchars($urlAfiliadosCab, ENT_QUOTES, 'UTF-8') ?>" title="Listado de atletas — femenino">F<span><?= $nAfF ?></span></a>
                    <?php if ($nAfO > 0): ?>
                    <a class="fvd-topbar__afiliados-pill" href="<?= htmlspecialchars($urlAfiliadosCab, ENT_QUOTES, 'UTF-8') ?>" title="Listado de atletas — otro / sin género">O<span><?= $nAfO ?></span></a>
                    <?php endif; ?>
                </div>
                <?php elseif ($nAfM > 0 || $nAfF > 0 || $nAfO > 0): ?>
                <div class="fvd-topbar__afiliados-sexo" role="group" aria-label="Afiliados por género (club)">
                    <span class="fvd-topbar__afiliados-pill" style="cursor:default">M<span><?= $nAfM ?></span></span>
                    <span class="fvd-topbar__afiliados-pill" style="cursor:default">F<span><?= $nAfF ?></span></span>
                    <?php if ($nAfO > 0): ?>
                    <span class="fvd-topbar__afiliados-pill" style="cursor:default">O<span><?= $nAfO ?></span></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="fvd-topbar__actions">
                <span class="fvd-topbar__user"><?= htmlspecialchars($userDisplayName, ENT_QUOTES, 'UTF-8') ?></span>
                <?php
                $invPendTop = (int) $invitacionesPendientes;
                $invHayListaTop = is_array($invitacionesAgrupadas) && $invitacionesAgrupadas !== [];
                $delegInvitEntrarUltima = rtrim((string) $invitacionesAppBase, '/') . '/fvdmasteradmin/delegado_entrar_torneo.php?ultima=1';
                $novedTop = max(0, (int) $novedadesDelegadosUnread);
                $ackBase = trim((string) $delegadoTorneoPickUrl);
                $ackNovedUrl = htmlspecialchars(
                    $ackBase !== '' ? ($ackBase . (str_contains($ackBase, '?') ? '&' : '?') . 'ack_novedades=1') : '',
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
                <?php if ($invHayListaTop): ?>
                    <a class="fvd-topbar__link<?= ($invPendTop > 0 || $novedTop > 0) ? ' fvd-topbar__link--notif' : '' ?>"
                       href="<?= htmlspecialchars($delegInvitEntrarUltima, ENT_QUOTES, 'UTF-8') ?>"
                       title="<?= $invPendTop > 0 ? 'Abrir el panel maestro en el contexto de la invitación pendiente (torneo y grupo si aplica)' : 'Entrar al panel por invitación' ?>">Invitaciones<?php if ($invPendTop > 0 || $novedTop > 0): ?><span class="fvd-topbar__notif-dot" id="fvd-dd-invites-dot" aria-hidden="true"></span><?php endif; ?></a>
                <?php endif; ?>
                <?php if ($novedTop > 0 && !$adminPortalDelegado && $ackNovedUrl !== ''): ?>
                    <a class="fvd-topbar__link fvd-topbar__link--notif" href="<?= $ackNovedUrl ?>"
                       title="Marcar como leídos los avisos de nuevos torneos">Novedades<span class="fvd-topbar__notif-dot" id="fvd-dd-novedades-dot" aria-hidden="true"></span></a>
                <?php elseif (!$adminPortalDelegado && $delegadoNotifPollUrl !== ''): ?>
                    <span class="fvd-topbar__link" style="opacity:.65;cursor:default;border-style:dashed" title="Sin novedades sin leer">Novedades<span class="fvd-topbar__notif-dot" id="fvd-dd-novedades-dot" style="display:none" aria-hidden="true"></span></span>
                <?php endif; ?>
                <a class="fvd-topbar__link" href="<?= htmlspecialchars($perfilUrl, ENT_QUOTES, 'UTF-8') ?>">Mi perfil</a>
                <a class="fvd-topbar__link" href="<?= htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8') ?>">Cerrar sesión</a>
            </div>
        </div>
    </header>
    <?php endif; ?>
        <?php if ($adminPortalDelegado): ?>
        <div class="fvd-dd-admin-portal-banner" role="status">
            Está viendo este panel como administrador general, con los datos de la asociación indicada.
            <?php if ($adminPortalCambiarAsocUrl !== ''): ?>
                <a href="<?= htmlspecialchars($adminPortalCambiarAsocUrl, ENT_QUOTES, 'UTF-8') ?>">Cambiar asociación</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <main class="fvd-dd-main">
        <?php if ($suppressMasterShellChrome && ($urlAfiliadosCab !== '' || $nAfM > 0 || $nAfF > 0 || $nAfO > 0)): ?>
        <p class="fvd-dd-embed-afiliados-hint" style="margin:0 0 .75rem;font-size:.72rem;font-weight:700;color:#334155;text-align:center">
            Afiliados (club):
            <?php if ($urlAfiliadosCab !== ''): ?>
                <a href="<?= htmlspecialchars($urlAfiliadosCab, ENT_QUOTES, 'UTF-8') ?>" style="color:#2e3092;font-weight:800">M <?= $nAfM ?></a>
                · <a href="<?= htmlspecialchars($urlAfiliadosCab, ENT_QUOTES, 'UTF-8') ?>" style="color:#2e3092;font-weight:800">F <?= $nAfF ?></a><?php if ($nAfO > 0): ?>
                · <a href="<?= htmlspecialchars($urlAfiliadosCab, ENT_QUOTES, 'UTF-8') ?>" style="color:#2e3092;font-weight:800">O <?= $nAfO ?></a><?php endif; ?>
            <?php else: ?>
                M <?= $nAfM ?> · F <?= $nAfF ?><?= $nAfO > 0 ? ' · O ' . $nAfO : '' ?>
            <?php endif; ?>
        </p>
        <?php endif; ?>
        <?php
        $delegPickBase = trim((string) $delegadoTorneoPickUrl);
        $delegTorneoActivo = max(0, $delegadoTorneoActivoId);
        $delegListaTor = is_array($delegadoListaTorneos) ? $delegadoListaTorneos : [];
        $tieneTorneosRelacionados = $delegListaTor !== [];
        ?>
        <?php if ($delegPickBase !== ''): ?>
        <section class="fvd-dd-torneos-strip" aria-label="Torneos asociados a su club">
            <div class="fvd-dd-torneos-strip__head">
                <span class="fvd-dd-torneos-strip__title"><?= $tieneTorneosRelacionados ? 'Torneos asociados' : 'Torneos' ?></span>
            </div>
            <?php if ($tieneTorneosRelacionados): ?>
            <div class="fvd-dd-torneos-strip__list">
                <?php foreach ($delegListaTor as $filaTor): ?>
                    <?php
                    $tidChip = (int) ($filaTor['torneo_id'] ?? 0);
                    if ($tidChip <= 0) {
                        continue;
                    }
                    $nomChip = trim((string) ($filaTor['torneo_nombre'] ?? ''));
                    if ($nomChip === '') {
                        $nomChip = 'Torneo #' . $tidChip;
                    }
                    $gChip = (int) ($filaTor['grupo_evento_id'] ?? 0);
                    $tipoChip = (int) ($filaTor['tipo'] ?? 0);
                    $genChip = '';
                    if ($tipoChip === 1) {
                        $genChip = 'M';
                    } elseif ($tipoChip === 2) {
                        $genChip = 'F';
                    } elseif ($tipoChip === 3) {
                        $genChip = 'Mixto';
                    }
                    $hrefPick = \fvd_torneo_evento_url($tidChip, $gChip > 0 ? $gChip : 0);
                    $esActivo = $delegTorneoActivo > 0 && $tidChip === $delegTorneoActivo;
                    ?>
                    <a class="fvd-dd-torneo-chip<?= $esActivo ? ' fvd-dd-torneo-chip--activo' : '' ?>"
                       href="<?= htmlspecialchars($hrefPick, ENT_QUOTES, 'UTF-8') ?>"
                       title="<?= $esActivo ? 'Torneo activo: panel del evento' : 'Abrir panel del torneo (vista evento)' ?>">
                        <span class="fvd-dd-torneo-chip__id">Torneo #<?= $tidChip ?></span>
                        <span class="fvd-dd-torneo-chip__name"><?= htmlspecialchars($nomChip, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if ($genChip !== ''): ?>
                            <span class="fvd-dd-torneo-chip__genero" title="Género del torneo (filtro de ramas asociadas)"><?= htmlspecialchars($genChip, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <?php if ($gChip > 0): ?>
                            <span class="fvd-dd-torneo-chip__meta">Grupo de evento #<?= $gChip ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="fvd-dd-torneos-strip--empty">No hay torneos con convocatoria invitada para su asociación. Cuando la federación le envíe una invitación, verá ese torneo y, si es campeonato con grupo de evento, otros del mismo grupo solo si comparten la misma fecha de realización.</p>
            <?php endif; ?>
        </section>
        <?php endif; ?>
        <?php if (!$vistaOperativaSimple): ?>
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
                    <<?= $cardTag ?> class="fvd-kpi-card<?= $hasDetail ? ' fvd-kpi-card--link' : '' ?>"<?= $hasDetail ? ' href="' . htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                        <div class="fvd-kpi-title">
                            <i class="fas <?= htmlspecialchars((string) $card['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                            <span><?= htmlspecialchars((string) $card['title'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <p class="fvd-kpi-value"><?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="fvd-kpi-sub"><?= htmlspecialchars($kpiAlcanceLabel, ENT_QUOTES, 'UTF-8') ?></p>
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
        <?php endif; ?>

        <?php
        $fase1Habilitada = (bool) ($inscripcionesCtx['fase1_habilitada'] ?? true);
        $fase2Habilitada = (bool) ($inscripcionesCtx['fase2_habilitada'] ?? true);
        $motivoFase1Bloqueo = trim((string) ($inscripcionesCtx['motivo_fase1_bloqueo'] ?? 'Esta opción está temporalmente inhabilitada.'));
        $motivoFase2Bloqueo = trim((string) ($inscripcionesCtx['motivo_fase2_bloqueo'] ?? 'Esta opción está temporalmente inhabilitada.'));
        $urlPanelCompleto = $delegadoTorneoPickUrl;
        if (str_contains($urlPanelCompleto, 'vista=operativo')) {
            $urlPanelCompleto = str_replace(['?vista=operativo&', '&vista=operativo', '?vista=operativo'], ['?', '', ''], $urlPanelCompleto);
            $urlPanelCompleto = rtrim($urlPanelCompleto, '?&');
        }
        ?>
        <?php if ($vistaOperativaSimple): ?>
        <section class="fvd-dd-operativo-simple" aria-label="Operaciones delegado" style="margin-bottom:1.75rem">
            <h2 style="margin:0 0 10px;font-size:1.05rem;font-weight:900;color:#0f172a">Operaciones</h2>
            <div class="fvd-dd-actions-col" style="max-width:26rem;display:flex;flex-direction:column;gap:10px">
                <?php if ($fase1Habilitada): ?>
                    <a class="fvd-dd-btn fvd-dd-btn--gestion" href="<?= htmlspecialchars((string) ($actionUrls['afiliar_atleta'] ?? ($actionUrls['afiliaciones'] ?? '#')), ENT_QUOTES, 'UTF-8') ?>">Afiliar atleta</a>
                    <a class="fvd-dd-btn fvd-dd-btn--gestion" href="<?= htmlspecialchars((string) ($actionUrls['carnets'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>">Solicitar carnet</a>
                    <a class="fvd-dd-btn fvd-dd-btn--gestion" href="<?= htmlspecialchars((string) ($actionUrls['traspasos'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>">Solicitar traspaso</a>
                <?php else: ?>
                    <span class="fvd-dd-btn fvd-dd-btn--gestion fvd-dd-btn--disabled" title="<?= htmlspecialchars($motivoFase1Bloqueo, ENT_QUOTES, 'UTF-8') ?>">Afiliar atleta / carnet / traspaso no disponibles</span>
                <?php endif; ?>
                <?php
                $inscDestOp = trim((string) ($inscripcionesCtx['url_destino'] ?? ''));
                $inscHrefOp = $inscDestOp !== '' ? ($inscDestOp . (str_contains($inscDestOp, '#') ? '' : '#fvd-insc-sitio-inscribir')) : '';
                ?>
                <?php if ($inscHrefOp !== '' && $fase2Habilitada): ?>
                    <a class="fvd-dd-btn fvd-dd-btn--inscripciones" href="<?= htmlspecialchars($inscHrefOp, ENT_QUOTES, 'UTF-8') ?>">Inscribir en torneo</a>
                <?php else: ?>
                    <span class="fvd-dd-btn fvd-dd-btn--inscripciones fvd-dd-btn--disabled" title="<?= !$fase2Habilitada ? htmlspecialchars($motivoFase2Bloqueo, ENT_QUOTES, 'UTF-8') : 'Defina torneo en contexto.' ?>">Inscribir en torneo</span>
                <?php endif; ?>
            </div>
            <?php if ($urlPanelCompleto !== ''): ?>
                <p style="margin:14px 0 0;font-size:.78rem"><a href="<?= htmlspecialchars($urlPanelCompleto, ENT_QUOTES, 'UTF-8') ?>" style="color:#2563eb;font-weight:700">Abrir panel completo</a> (finanzas, indicadores e invitaciones)</p>
            <?php endif; ?>
        </section>
        <?php else: ?>
        <section class="fvd-dd-actions-grid">
            <article class="fvd-dd-card fvd-dd-card--gestion">
                <div class="fvd-dd-card__head">
                    <i class="fas fa-user-shield" aria-hidden="true"></i>
                    <h3>Gestión administrativa</h3>
                </div>
                <div class="fvd-dd-card__body">
                    <?php if ($fase1Habilitada): ?>
                        <a class="fvd-dd-btn fvd-dd-btn--gestion"
                           href="<?= htmlspecialchars((string) ($actionUrls['afiliaciones'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>"
                           title="Listado de atletas: afiliación, solicitud de carnets y transferencias">
                            Afiliación, solicitud de carnets y transferencias
                        </a>
                    <?php else: ?>
                        <span class="fvd-dd-btn fvd-dd-btn--gestion fvd-dd-btn--disabled"
                              title="<?= htmlspecialchars($motivoFase1Bloqueo, ENT_QUOTES, 'UTF-8') ?>">
                            Afiliación, solicitud de carnets y transferencias
                        </span>
                    <?php endif; ?>
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
                        $tnAct = 'Sin torneo activo';
                    }
                    ?>
                    <div class="fvd-dd-torneo">
                        <div class="fvd-dd-torneo__label">Torneo actual</div>
                        <p class="fvd-dd-torneo__name"><?= htmlspecialchars($tnAct, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <?php
                    $inscDest = trim((string) ($inscripcionesCtx['url_destino'] ?? ''));
                    $inscHref = '';
                    if ($inscDest !== '') {
                        $inscHref = $inscDest . (str_contains($inscDest, '#') ? '' : '#fvd-insc-sitio-inscribir');
                    }
                    $adminInscHref = trim((string) ($actionUrls['administrar_inscripciones'] ?? ''));
                    $adminInscOk = $adminInscHref !== '';
                    $adminInscDuplicado = $adminInscOk && $inscHref !== '' && $adminInscHref === $inscHref;
                    ?>
                    <div class="fvd-dd-actions-col">
                        <?php if ($inscHref !== '' && $fase2Habilitada): ?>
                            <a
                                class="fvd-dd-btn fvd-dd-btn--inscripciones"
                                href="<?= htmlspecialchars($inscHref, ENT_QUOTES, 'UTF-8') ?>"
                                title="Abre el formulario de inscripción al torneo (línea de cédula y tablas Disponibles / Inscritos en el portal FVD)"
                            >Inscripciones</a>
                        <?php else: ?>
                            <span
                                class="fvd-dd-btn fvd-dd-btn--inscripciones fvd-dd-btn--disabled"
                                title="<?= !$fase2Habilitada
                                    ? htmlspecialchars($motivoFase2Bloqueo, ENT_QUOTES, 'UTF-8')
                                    : 'Acceso no disponible en este momento.' ?>"
                            >Inscripciones</span>
                        <?php endif; ?>
                        <?php if (!$adminInscDuplicado): ?>
                            <?php if ($fase2Habilitada && $adminInscOk): ?>
                            <a class="fvd-dd-btn fvd-dd-btn--admin-insc" href="<?= htmlspecialchars($adminInscHref, ENT_QUOTES, 'UTF-8') ?>">
                                Administración de inscritos
                            </a>
                            <?php else: ?>
                            <span class="fvd-dd-btn fvd-dd-btn--admin-insc fvd-dd-btn--disabled"
                                  title="<?= !$fase2Habilitada
                                      ? htmlspecialchars($motivoFase2Bloqueo, ENT_QUOTES, 'UTF-8')
                                      : 'Acceso no disponible en este momento.' ?>">
                                Administración de inscritos
                            </span>
                            <?php endif; ?>
                        <?php endif; ?>
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
        <?php endif; ?>
    </main>
</div>
<?php if ($delegadoNotifPollUrl !== '' && !$adminPortalDelegado): ?>
<script>
(function () {
  var pollUrl = <?= json_encode($delegadoNotifPollUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
  if (!pollUrl) return;
  function tick() {
    fetch(pollUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.ok) return;
        var n = parseInt(d.novedades_unread, 10) || 0;
        var el = document.getElementById('fvd-dd-novedades-dot');
        if (!el) return;
        el.style.display = n > 0 ? 'inline-block' : 'none';
        var row = el.closest('a.fvd-topbar__link, span.fvd-topbar__link');
        if (row && n > 0) {
          row.classList.add('fvd-topbar__link--notif');
        }
      })
      .catch(function () {});
  }
  setInterval(tick, 42000);
  tick();
})();
</script>
<?php endif; ?>
</body>
</html><?php
    }
}
