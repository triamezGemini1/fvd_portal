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
        string $viteTags = ''
    ): void
    {
        $cards = [
            ['key' => 'atletas_afiliados', 'title' => 'Atletas afiliados', 'icon' => 'fa-users', 'tone' => 'text-blue-700', 'btnClass' => 'bg-blue-700 hover:bg-blue-800 text-white', 'btnLabel' => 'Ver atletas'],
            ['key' => 'afiliaciones', 'title' => 'Afiliaciones', 'icon' => 'fa-id-badge', 'tone' => 'text-indigo-700', 'btnClass' => 'bg-indigo-700 hover:bg-indigo-800 text-white', 'btnLabel' => 'Gestionar afiliaciones'],
            ['key' => 'carnets', 'title' => 'Carnets', 'icon' => 'fa-address-card', 'tone' => 'text-violet-700', 'btnClass' => 'bg-violet-700 hover:bg-violet-800 text-white', 'btnLabel' => 'Solicitar carnets'],
            ['key' => 'anualidades', 'title' => 'Anualidades', 'icon' => 'fa-calendar-check', 'tone' => 'text-amber-700', 'btnClass' => 'bg-amber-600 hover:bg-amber-700 text-white', 'btnLabel' => 'Ver anualidades'],
            ['key' => 'traspasos', 'title' => 'Traspasos', 'icon' => 'fa-right-left', 'tone' => 'text-rose-700', 'btnClass' => 'bg-rose-700 hover:bg-rose-800 text-white', 'btnLabel' => 'Revisar traspasos'],
            ['key' => 'inscritos', 'title' => 'Inscritos', 'icon' => 'fa-trophy', 'tone' => 'text-emerald-700', 'btnClass' => 'bg-emerald-700 hover:bg-emerald-800 text-white', 'btnLabel' => 'Abrir inscripciones'],
        ];
        $showTorneoBreakdown = count($torneoStats) >= 2;

        header('Content-Type: text/html; charset=UTF-8');
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Gestión - Miranda 4</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWix+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkR4j8R7Y+R0JQXnR6QeJ8V+RaHcRUW2KI2w==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        /* Header unificado (base visual de admin gral), independiente de Tailwind */
        :root { --fvd-azul:#0b3a63; --fvd-amarillo:#fff200; --fvd-border:rgba(255,255,255,.18); --fvd-text:#f8fafc; --fvd-muted:#cbd5e1; }
        body { margin: 0; color: #0f172a; }
        .fvd-topbar { position: sticky; top: 0; z-index: 100; border-bottom: 2px solid var(--fvd-amarillo); background: var(--fvd-azul); color: var(--fvd-text); }
        .fvd-topbar__inner { max-width: 80rem; margin: 0 auto; padding: .65rem 1rem; display: grid; grid-template-columns: 1fr auto 1fr; gap: .75rem; align-items: center; }
        .fvd-topbar__brand { justify-self: start; display: flex; align-items: center; gap: .6rem; min-width: 0; text-decoration: none; color: inherit; }
        .fvd-topbar__fvd-logo { height: 38px; width: auto; object-fit: contain; }
        .fvd-topbar__page-title { font-size: .95rem; font-weight: 700; letter-spacing: .01em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .fvd-topbar__center { justify-self: center; }
        .fvd-topbar__asoc-name { font-size: .78rem; color: var(--fvd-muted); background: rgba(255,255,255,.08); padding: .22rem .55rem; border: 1px solid var(--fvd-border); border-radius: 999px; }
        .fvd-topbar__actions { justify-self: end; display: flex; gap: .45rem; align-items: center; }
        .fvd-topbar__user { font-size: .78rem; color: #fff; background: rgba(255,255,255,.12); border: 1px solid var(--fvd-border); border-radius: 999px; padding: .28rem .55rem; max-width: 16rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .fvd-topbar__link { text-decoration: none; color: #fff; border: 1px solid var(--fvd-border); border-radius: 7px; padding: .3rem .6rem; font-size: .76rem; font-weight: 600; }
        .fvd-topbar__link:hover { border-color: var(--fvd-amarillo); color: var(--fvd-amarillo); }
        .fvd-main-board { background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%); }
        .fvd-kpi-strip { border: 1px solid #dbeafe; background: #ffffff; box-shadow: 0 6px 16px rgba(15, 23, 42, .06); border-radius: 16px; padding: 12px; }
        .fvd-kpi-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 10px; }
        .fvd-kpi-card { border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; padding: 10px; min-height: 108px; display: flex; flex-direction: column; justify-content: space-between; }
        .fvd-kpi-title { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 800; color: #334155; line-height: 1.2; }
        .fvd-kpi-value { font-size: 1.45rem; line-height: 1; font-weight: 900; letter-spacing: -.02em; }
        .fvd-kpi-sub { font-size: 10px; font-weight: 800; text-transform: uppercase; color: #94a3b8; letter-spacing: .08em; }
        .fvd-kpi-tournament-list { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
        .fvd-kpi-tournament-pill { font-size: 10px; font-weight: 800; border: 1px solid #cbd5e1; color: #475569; border-radius: 999px; padding: 2px 6px; background: #fff; }
        .fvd-admin-card { border: 1px solid #cbd5e1; border-radius: 14px; box-shadow: 0 8px 18px rgba(15, 23, 42, .08); background: #fff; overflow: hidden; }
        .fvd-admin-card__head { background: linear-gradient(90deg, #f8fafc 0%, #eef2ff 100%); border-bottom: 1px solid #e2e8f0; padding: 14px 16px; }
        .fvd-admin-card__body { padding: 16px; }
        .fvd-admin-btn { display: inline-flex; align-items: center; justify-content: flex-start; width: 100%; padding: .68rem .9rem; border-radius: .62rem; font-size: .9rem; font-weight: 800; text-decoration: none; border: 1px solid transparent; transition: all .15s ease; }
        .fvd-admin-btn--afiliaciones { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
        .fvd-admin-btn--afiliaciones:hover { background: #dbeafe; color: #1e3a8a; }
        .fvd-admin-btn--carnets { background: #f5f3ff; border-color: #ddd6fe; color: #5b21b6; }
        .fvd-admin-btn--carnets:hover { background: #ede9fe; color: #4c1d95; }
        .fvd-admin-btn--traspasos { background: #fff1f2; border-color: #fecdd3; color: #be123c; }
        .fvd-admin-btn--traspasos:hover { background: #ffe4e6; color: #9f1239; }
        .fvd-admin-btn--inscribir { background: #dbeafe; border-color: #93c5fd; color: #1d4ed8; }
        .fvd-admin-btn--inscribir:hover { background: #bfdbfe; color: #1e40af; }
        .fvd-admin-btn--admin-insc { background: #ecfdf5; border-color: #86efac; color: #15803d; }
        .fvd-admin-btn--admin-insc:hover { background: #dcfce7; color: #166534; }
        @media (max-width: 1366px) {
            .fvd-kpi-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        @media (max-width: 820px) {
            .fvd-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>
    <?php if ($viteTags !== ''): ?>
    <?= $viteTags ?>
    <?php endif; ?>
</head>
<body>
<div class="min-h-screen bg-slate-50 flex flex-col">
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
    <main class="fvd-main-board flex-1 max-w-7xl mx-auto w-full p-6">
        <section class="fvd-kpi-strip mb-6">
            <div class="fvd-kpi-grid">
                <?php foreach ($cards as $card): ?>
                    <?php
                    $key = (string) $card['key'];
                    $value = (int) ($stats[$key] ?? 0);
                    ?>
                    <article class="fvd-kpi-card">
                        <div class="fvd-kpi-title">
                            <i class="fas <?= htmlspecialchars((string) $card['icon'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string) $card['tone'], ENT_QUOTES, 'UTF-8') ?>"></i>
                            <span><?= htmlspecialchars((string) $card['title'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <p class="fvd-kpi-value <?= htmlspecialchars((string) $card['tone'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="fvd-kpi-sub">Total general</p>
                        <?php if ($showTorneoBreakdown): ?>
                            <div class="fvd-kpi-tournament-list">
                                <?php foreach ($torneoStats as $ts): ?>
                                    <span class="fvd-kpi-tournament-pill">T#<?= (int) ($ts['torneo_id'] ?? 0) ?>: <?= (int) ($ts[$key] ?? 0) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <article class="card fvd-admin-card flex flex-col">
                <div class="fvd-admin-card__head">
                    <h3 class="font-bold text-slate-800 flex items-center gap-2 text-base">
                        <i class="fas fa-user-shield text-blue-700"></i> Gestión Administrativa
                    </h3>
                </div>
                <div class="fvd-admin-card__body flex-1 flex flex-col gap-3">
                    <a class="fvd-admin-btn fvd-admin-btn--afiliaciones" href="<?= htmlspecialchars((string) ($actionUrls['afiliaciones'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>">Afiliaciones</a>
                    <a class="fvd-admin-btn fvd-admin-btn--carnets" href="<?= htmlspecialchars((string) ($actionUrls['carnets'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>">Solicitud de Carnets</a>
                    <a class="fvd-admin-btn fvd-admin-btn--traspasos" href="<?= htmlspecialchars((string) ($actionUrls['transferencias'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>">Solicitud de Transferencias</a>
                </div>
            </article>

            <article class="card fvd-admin-card flex flex-col">
                <div class="fvd-admin-card__head">
                    <h3 class="font-bold text-slate-800 flex items-center gap-2 text-base">
                        <i class="fas fa-trophy text-amber-500"></i> Torneos y Eventos
                    </h3>
                </div>
                <div class="fvd-admin-card__body flex-1 flex flex-col">
                    <div class="mb-4">
                        <label class="text-[10px] uppercase tracking-wider text-slate-400 font-bold">Torneo Activo</label>
                        <p class="text-sm font-semibold text-slate-700 truncate"><?= isset($torneoStats[0]['torneo_nombre']) ? htmlspecialchars((string) $torneoStats[0]['torneo_nombre'], ENT_QUOTES, 'UTF-8') : 'Sin torneo activo'; ?></p>
                    </div>
                    <div class="mt-auto flex flex-col gap-3">
                        <a class="fvd-admin-btn fvd-admin-btn--inscribir" href="<?= htmlspecialchars((string) ($actionUrls['inscribir_torneo'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>">
                            Realizar inscripciones
                        </a>
                        <a class="fvd-admin-btn fvd-admin-btn--admin-insc" href="<?= htmlspecialchars((string) ($actionUrls['administrar_inscripciones'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>">
                            Administrar inscripciones (cambios)
                        </a>
                    </div>
                </div>
            </article>

            <article class="card fvd-admin-card flex flex-col">
                <div class="fvd-admin-card__head">
                    <h3 class="font-bold text-slate-800 flex items-center gap-2 text-base">
                        <i class="fas fa-wallet text-emerald-500"></i> Balance Financiero
                    </h3>
                </div>
                <div class="fvd-admin-card__body flex-1 flex flex-col justify-center text-center">
                    <div class="mb-4">
                        <span class="text-[10px] uppercase tracking-wider text-slate-400 font-bold">Deuda Pendiente</span>
                        <p class="text-3xl font-black text-slate-900">$ 0.00</p>
                    </div>
                    <button class="w-full rounded-lg border border-emerald-500 bg-white py-2.5 text-sm font-extrabold text-emerald-700 transition hover:bg-emerald-50">
                        Ver Estado de Cuenta
                    </button>
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
