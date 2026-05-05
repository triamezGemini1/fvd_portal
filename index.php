<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/load_fvd_bootstrap.php';
require_once __DIR__ . '/includes/public_header.php';

$topM = [];
$topF = [];
$eventos = [];
$resultados = [];
$asociaciones = [];
$enVivo = [];
$dbError = '';

try {
    $topM = PublicSiteData::landingTop5NacionalQueryHelper('M');
    $topF = PublicSiteData::landingTop5NacionalQueryHelper('F');
    $eventos = PublicSiteData::torneosProximos(9);
    $resultados = PublicSiteData::torneosPasados(6);
    $asociaciones = PublicSiteData::landingAsociacionesCardsQueryHelper(12);
    $enVivo = PublicSiteData::torneosEnVivo(8);
} catch (Throwable $e) {
    error_log('[fvd_portal index.php] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $dbError = 'No se pudieron cargar los datos. Compruebe que exista un archivo <code>.env</code> en la raíz del proyecto en el servidor con <strong>DB_HOST</strong>, <strong>DB_DATABASE</strong>, <strong>DB_USERNAME</strong> y <strong>DB_PASSWORD</strong> correctos (cPanel → MySQL). También puede usar <code>FVD_DB_*</code> si así lo tiene configurado. El detalle del error queda registrado en el log de PHP del hosting.';
    if (function_exists('env') && in_array(strtolower((string) env('APP_DEBUG', '')), ['1', 'true', 'yes'], true)) {
        $dbError .= ' <span class="block mt-2 font-mono text-xs">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

public_layout_head('Inicio');
public_layout_header('inicio');
public_layout_main_open();

$logoLanding = public_layout_logo_url();
/* Login canónico en la raíz del proyecto. */
$urlLogin = function_exists('url') ? url('login.php') : '/fvd_portal/login.php';
?>

<?php if ($dbError !== ''): ?>
    <div class="fvd-p-card fvd-p-alert mb-10 p-4 text-sm fvd-p-muted"><?= $dbError ?></div>
<?php endif; ?>

<?php if ($enVivo !== []): ?>
    <div class="fvd-p-live-alert mb-10 px-4 py-3 sm:px-5 sm:py-4" role="alert">
        <p class="text-xs font-semibold uppercase tracking-wider text-red-200">Torneo en vivo</p>
        <ul class="mt-2 space-y-2 text-sm">
            <?php foreach ($enVivo as $ev): ?>
                <?php $urlEv = url('torneo_publico.php?id=' . (int) ($ev['torneo'] ?? 0)); ?>
                <li class="leading-snug">
                    <a href="<?= htmlspecialchars($urlEv, ENT_QUOTES, 'UTF-8') ?>" class="font-medium text-white hover:underline"><?= htmlspecialchars((string) ($ev['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
                    <?php if (!empty($ev['lugar'])): ?>
                        <span class="fvd-p-muted"> — <?= htmlspecialchars((string) $ev['lugar'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Identificación + hero -->
<section class="fvd-p-hero mx-auto max-w-4xl px-2 text-center sm:px-4" aria-labelledby="hero-title">
    <?php if ($logoLanding !== ''): ?>
        <div class="fvd-p-landing-id__logo-wrap">
            <img src="<?= htmlspecialchars($logoLanding, ENT_QUOTES, 'UTF-8') ?>" alt="Logotipo — Federación Venezolana del Dominó" class="fvd-p-landing-id__logo mx-auto" width="320" height="200" decoding="async" fetchpriority="high">
        </div>
    <?php else: ?>
        <div class="mx-auto flex max-w-xs justify-center py-4">
            <span class="fvd-p-header__mark flex h-24 w-24 items-center justify-center rounded-2xl text-lg font-bold tracking-tight sm:h-28 sm:w-28 sm:text-xl">FVD</span>
        </div>
    <?php endif; ?>
    <h1 id="hero-title" class="fvd-p-landing-id__name mt-2 px-2">
        Federación Venezolana del Dominó
    </h1>
    <p class="fvd-p-landing-id__siglas">FVD · Venezuela</p>
    <p class="fvd-p-hero__kicker mt-5 text-xs font-semibold uppercase tracking-[0.2em] opacity-90">Sitio oficial · Torneos y rankings</p>
    <p class="fvd-p-muted mx-auto mt-6 max-w-xl text-sm leading-relaxed sm:text-[0.9375rem]">
        Excelencia y comunidad en el dominó venezolano. Información para atletas, clubes, asociaciones y público general.
    </p>
    <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
        <a href="#actividades" class="fvd-p-btn-dorado">
            Próximas actividades
        </a>
        <a href="<?= htmlspecialchars(url('ranking_publico.php'), ENT_QUOTES, 'UTF-8') ?>" class="fvd-p-link text-sm font-medium">
            Rankings completos →
        </a>
    </div>
</section>

<!-- Accesos zona administrativa y perfil -->
<section id="acceso" class="scroll-mt-24 pb-16 pt-4" aria-labelledby="acceso-title">
    <h2 id="acceso-title" class="fvd-p-section-title mb-2 text-center text-lg sm:text-xl">Accesos privados</h2>
    <p class="fvd-p-muted mx-auto mb-10 max-w-2xl text-center text-sm leading-relaxed">
        Entrada a paneles según su perfil. Use las credenciales que le haya facilitado la FVD o su asociación.
    </p>
    <div class="mx-auto grid max-w-5xl grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <a class="fvd-p-access-card" href="<?= htmlspecialchars($urlLogin, ENT_QUOTES, 'UTF-8') ?>">
            <h3 class="fvd-p-access-card__title">Administración general FVD</h3>
            <p class="fvd-p-access-card__desc">Panel nacional Master Admin: configuración federativa, usuarios centrales y módulos de gestión FVD.</p>
            <span class="fvd-p-access-card__cta">Entrar al panel FVD</span>
        </a>
        <a class="fvd-p-access-card" href="<?= htmlspecialchars($urlLogin, ENT_QUOTES, 'UTF-8') ?>">
            <h3 class="fvd-p-access-card__title">Administración de asociación</h3>
            <p class="fvd-p-access-card__desc">Delegados y gestores regionales: mismo acceso FVD Master Admin con rol de asociación (credenciales emitidas por la FVD).</p>
            <span class="fvd-p-access-card__cta">Acceso asociación</span>
        </a>
        <a class="fvd-p-access-card sm:col-span-2 lg:col-span-1" href="<?= htmlspecialchars($urlLogin, ENT_QUOTES, 'UTF-8') ?>">
            <h3 class="fvd-p-access-card__title">Delegados</h3>
            <p class="fvd-p-access-card__desc">Ingreso de delegados al mismo acceso administrativo unificado de la plataforma FVD.</p>
            <span class="fvd-p-access-card__cta">Acceso delegado</span>
        </a>
    </div>
    <p class="fvd-p-muted mx-auto mt-8 max-w-xl text-center text-xs leading-relaxed opacity-90">
        Torneos, atletas, finanzas e inscripciones se gestionan en
        <a href="<?= htmlspecialchars($urlLogin, ENT_QUOTES, 'UTF-8') ?>" class="fvd-p-link font-medium underline decoration-white/25 underline-offset-2">FVD Master Admin</a>
        (tabla <code>fvd_usuarios</code>).
    </p>
</section>

<!-- Próximas actividades (calendario) -->
<section id="actividades" class="scroll-mt-24 pb-16 pt-6">
    <div class="mb-10 text-center">
        <h2 class="fvd-p-section-title text-xl sm:text-2xl">Próximas actividades</h2>
        <p class="fvd-p-muted mx-auto mt-4 max-w-md text-sm leading-relaxed">Calendario de competencias federadas</p>
    </div>
    <?php if ($eventos === []): ?>
        <p class="fvd-p-card fvd-p-muted rounded-xl py-14 text-center text-sm">No hay eventos programados por ahora.</p>
    <?php else: ?>
        <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($eventos as $t): ?>
                <?php
                $fecha = !empty($t['fechator']) ? date('d/m/Y', strtotime((string) $t['fechator'])) : '—';
                $afiche = !empty($t['afiche']) ? upload_url((string) $t['afiche']) : '';
                $urlFichaTorneo = url('torneo_publico.php?id=' . (int) ($t['torneo'] ?? 0));
                ?>
                <article class="fvd-p-card--glass group flex flex-col overflow-hidden rounded-xl transition">
                    <?php if ($afiche !== ''): ?>
                        <div class="aspect-[16/9] overflow-hidden bg-black/20">
                            <img src="<?= htmlspecialchars($afiche, ENT_QUOTES, 'UTF-8') ?>" alt="" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]">
                        </div>
                    <?php else: ?>
                        <div class="aspect-[16/9] rounded-t-xl bg-gradient-to-br from-white/10 to-white/5"></div>
                    <?php endif; ?>
                    <div class="flex flex-1 flex-col p-6">
                        <p class="fvd-p-event-date"><?= htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8') ?></p>
                        <h3 class="fvd-p-card-title mt-3 text-base font-medium leading-snug">
                            <a href="<?= htmlspecialchars($urlFichaTorneo, ENT_QUOTES, 'UTF-8') ?>" class="fvd-p-link decoration-white/30 underline-offset-2 hover:underline"><?= htmlspecialchars((string) ($t['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
                        </h3>
                        <p class="fvd-p-muted mt-3 flex-1 text-sm leading-relaxed"><?= htmlspecialchars((string) ($t['lugar'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if (!empty($t['org_nombre'])): ?>
                            <p class="fvd-p-muted mt-4 text-xs opacity-80"><?= htmlspecialchars((string) $t['org_nombre'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <p class="mt-4"><a href="<?= htmlspecialchars($urlFichaTorneo, ENT_QUOTES, 'UTF-8') ?>" class="fvd-p-link text-xs font-semibold">Consultar en sitio público →</a></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <p class="mt-12 text-center">
        <a href="<?= htmlspecialchars(url('calendario.php'), ENT_QUOTES, 'UTF-8') ?>" class="fvd-p-link text-sm font-medium">Calendario completo</a>
    </p>
</section>

<!-- Resultados / competencias recientes -->
<section id="resultados" class="scroll-mt-24 pb-16">
    <div class="mb-10 text-center">
        <h2 class="fvd-p-section-title text-xl sm:text-2xl">Resultados recientes</h2>
        <p class="fvd-p-muted mx-auto mt-4 max-w-lg text-sm leading-relaxed">Últimas competencias registradas en el sistema (fecha de cierre)</p>
    </div>
    <?php if ($resultados === []): ?>
        <p class="fvd-p-card fvd-p-muted rounded-xl py-12 text-center text-sm">No hay torneos finalizados para mostrar.</p>
    <?php else: ?>
        <div class="grid gap-6 sm:grid-cols-2">
            <?php foreach ($resultados as $t): ?>
                <?php
                $fecha = !empty($t['fechator']) ? date('d/m/Y', strtotime((string) $t['fechator'])) : '—';
                $urlFichaRes = url('torneo_publico.php?id=' . (int) ($t['torneo'] ?? 0));
                ?>
                <article class="fvd-p-card rounded-xl p-6">
                    <p class="fvd-p-event-date text-xs"><?= htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8') ?></p>
                    <h3 class="fvd-p-card-title mt-2 text-base font-medium leading-snug">
                        <a href="<?= htmlspecialchars($urlFichaRes, ENT_QUOTES, 'UTF-8') ?>" class="fvd-p-link decoration-white/30 underline-offset-2 hover:underline"><?= htmlspecialchars((string) ($t['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
                    </h3>
                    <p class="fvd-p-muted mt-2 text-sm"><?= htmlspecialchars((string) ($t['lugar'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if (!empty($t['org_nombre'])): ?>
                        <p class="fvd-p-muted mt-3 text-xs opacity-80"><?= htmlspecialchars((string) $t['org_nombre'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <p class="mt-3"><a href="<?= htmlspecialchars($urlFichaRes, ENT_QUOTES, 'UTF-8') ?>" class="fvd-p-link text-xs font-semibold">Ver ficha pública →</a></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Asociaciones -->
<section id="asociaciones" class="scroll-mt-24 pb-16">
    <div class="mb-10 text-center">
        <h2 class="fvd-p-section-title text-xl sm:text-2xl">Asociaciones</h2>
        <p class="fvd-p-muted mx-auto mt-4 max-w-md text-sm leading-relaxed">Nombre, contacto y sede regional</p>
    </div>
    <?php if ($asociaciones === []): ?>
        <p class="fvd-p-card fvd-p-muted rounded-xl py-12 text-center text-sm">No hay asociaciones activas para mostrar.</p>
    <?php else: ?>
        <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($asociaciones as $a): ?>
                <?php $logoU = public_assoc_logo_url(isset($a['logo']) ? (string) $a['logo'] : null); ?>
                <article class="fvd-p-card flex flex-col rounded-xl p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white/10">
                            <?php if ($logoU !== ''): ?>
                                <img src="<?= htmlspecialchars($logoU, ENT_QUOTES, 'UTF-8') ?>" alt="" class="max-h-16 max-w-16 object-contain" width="64" height="64" loading="lazy" decoding="async">
                            <?php else: ?>
                                <span class="text-xs font-semibold text-white/50">FVD</span>
                            <?php endif; ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="fvd-p-card-title text-base font-medium leading-snug"><?= htmlspecialchars((string) ($a['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                            <div class="fvd-p-muted mt-3 space-y-2 text-sm leading-relaxed">
                                <?php if (!empty($a['delegado'])): ?>
                                    <p><span class="text-white/50">Contacto:</span> <?= htmlspecialchars((string) $a['delegado'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <?php if (!empty($a['telefono'])): ?>
                                    <p>
                                        <a class="fvd-p-link underline decoration-white/20 underline-offset-2 hover:decoration-[var(--fvd-amarillo)]" href="tel:<?= htmlspecialchars(preg_replace('/\s+/', '', (string) $a['telefono']), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $a['telefono'], ENT_QUOTES, 'UTF-8') ?></a>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($a['email'])): ?>
                                    <p class="break-all">
                                        <a class="fvd-p-link underline decoration-white/20 underline-offset-2 hover:decoration-[var(--fvd-amarillo)]" href="mailto:<?= htmlspecialchars((string) $a['email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $a['email'], ENT_QUOTES, 'UTF-8') ?></a>
                                    </p>
                                <?php endif; ?>
                                <?php if (empty($a['delegado']) && empty($a['telefono']) && empty($a['email'])): ?>
                                    <p class="text-white/40">Sin datos de contacto registrados</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <p class="mt-12 text-center">
        <a href="<?= htmlspecialchars(url('asociaciones.php'), ENT_QUOTES, 'UTF-8') ?>" class="fvd-p-link text-sm font-medium">Ver todas las asociaciones</a>
    </p>
</section>

<!-- Rankings (vista previa) -->
<section id="rankings" class="fvd-p-band scroll-mt-20 px-4 py-14 sm:px-6 sm:py-16">
    <div class="mx-auto max-w-6xl">
        <div class="mb-10 text-center">
            <h2 class="fvd-p-section-title text-xl sm:text-2xl">Top 5 nacional</h2>
            <p class="fvd-p-muted mx-auto mt-4 max-w-lg text-sm leading-relaxed">Referencia por número FVD (categoría libre, adultos)</p>
        </div>
        <div class="grid w-full gap-10 lg:grid-cols-2">
            <?php
            $renderGenero = static function (string $titulo, array $rows): void {
                ?>
                <div class="fvd-p-card min-w-0 rounded-xl p-5 sm:p-6">
                    <h3 class="fvd-p-card-title border-b border-white/10 pb-3 text-xs font-semibold uppercase tracking-wide"><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></h3>
                    <?php if ($rows === []): ?>
                        <p class="fvd-p-muted py-8 text-center text-sm">Sin registros</p>
                    <?php else: ?>
                        <div class="mt-5 grid w-full grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-3 xl:grid-cols-5">
                            <?php $n = 0; ?>
                            <?php foreach ($rows as $r): ?>
                                <?php ++$n; ?>
                                <article class="flex min-w-0 flex-col rounded-xl border border-white/10 bg-black/15 p-3 sm:p-4">
                                    <div class="mb-2 flex items-center justify-between gap-2">
                                        <span class="fvd-p-rank-badge shrink-0"><?= $n ?></span>
                                        <span class="fvd-p-hero__kicker font-mono text-xs tabular-nums sm:text-sm"><?= htmlspecialchars((string) ($r['numfvd'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <p class="fvd-p-card-title break-words text-xs font-medium leading-snug sm:text-sm"><?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php
            };
            $renderGenero('Masculino', $topM);
            $renderGenero('Femenino', $topF);
            ?>
        </div>
        <p class="mt-12 text-center">
            <a href="<?= htmlspecialchars(url('ranking_publico.php'), ENT_QUOTES, 'UTF-8') ?>" class="fvd-p-btn-dorado">Rankings por categoría y género</a>
        </p>
    </div>
</section>

<section class="py-14 text-center">
    <a href="<?= htmlspecialchars(url('afiliacion.php'), ENT_QUOTES, 'UTF-8') ?>" class="fvd-p-btn-ghost rounded-xl px-6 py-3 text-sm font-medium">
        Información de afiliación
    </a>
</section>

<?php
public_layout_footer();
