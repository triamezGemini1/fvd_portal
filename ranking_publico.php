<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/load_fvd_bootstrap.php';
require_once __DIR__ . '/includes/public_header.php';

$categorias = [
    'libre'  => 'Libre',
    'sub18'  => 'Sub-18',
    'sub15'  => 'Sub-15',
    'sub12'  => 'Sub-12',
];
$generos = [
    'm' => ['label' => 'Masculino', 'sexo' => 'M'],
    'f' => ['label' => 'Femenino', 'sexo' => 'F'],
];

$catRaw = strtolower((string) ($_GET['categoria'] ?? 'libre'));
if (!isset($categorias[$catRaw])) {
    $catRaw = 'libre';
}

$generoRaw = strtolower((string) ($_GET['genero'] ?? $_GET['rama'] ?? 'm'));
if ($generoRaw === 'femenino' || $generoRaw === 'f') {
    $generoRaw = 'f';
} else {
    $generoRaw = 'm';
}

$page = max(1, (int) ($_GET['p'] ?? 1));
$perPage = 50;

$result = [
    'total' => 0, 'page' => 1, 'per_page' => $perPage, 'pages' => 1, 'rows' => [],
];
$dbError = '';

try {
    $result = PublicSiteData::rankingPublicoPaginadoQueryHelper(
        $catRaw,
        $generos[$generoRaw]['sexo'],
        $page,
        $perPage
    );
} catch (Throwable $e) {
    $dbError = 'No se pudo cargar el ranking.';
    if (function_exists('env') && in_array(strtolower((string) env('APP_DEBUG', '')), ['1', 'true', 'yes'], true)) {
        $dbError .= ' (' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . ')';
    }
}

$rankingPublicUrl = static function (string $c, string $g, int $p = 1): string {
    $q = ['categoria' => $c, 'genero' => $g];
    if ($p > 1) {
        $q['p'] = $p;
    }

    return url('ranking_publico.php?' . http_build_query($q));
};

public_layout_head('Rankings');
public_layout_header('ranking');
public_layout_main_open();
?>

<div class="mb-10">
    <h1 class="fvd-p-section-title text-xl sm:text-2xl">Rankings públicos</h1>
    <p class="fvd-p-muted mt-4 max-w-2xl text-sm leading-relaxed">
        Datos reales de atletas y asociaciones. Filtre por categoría (edad) y género.
        <span class="block mt-2 text-xs opacity-90">Sub-12: menores de 12 años. Sub-15: 12 a 14. Sub-18: 15 a 17. Libre: 18 años o más, o sin fecha de nacimiento válida.</span>
    </p>
</div>

<?php if ($dbError !== ''): ?>
    <div class="fvd-p-card fvd-p-alert mb-8 p-4 text-sm fvd-p-muted" role="alert"><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="fvd-p-card mb-8 rounded-xl p-5 sm:p-6">
    <p class="fvd-p-muted mb-3 text-xs font-semibold uppercase tracking-wide">Categoría</p>
    <div class="grid w-full grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
        <?php foreach ($categorias as $key => $label): ?>
            <?php
            $active = $key === $catRaw;
            $cls = 'fvd-p-filter-pill w-full justify-center text-center' . ($active ? ' fvd-p-filter-pill--active' : '');
            ?>
            <a class="<?= $cls ?>" href="<?= htmlspecialchars($rankingPublicUrl($key, $generoRaw), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
    </div>
    <p class="fvd-p-muted mb-3 mt-8 text-xs font-semibold uppercase tracking-wide">Género</p>
    <div class="grid w-full grid-cols-2 gap-3 sm:gap-4">
        <?php foreach ($generos as $key => $info): ?>
            <?php
            $active = $key === $generoRaw;
            $cls = 'fvd-p-filter-pill w-full justify-center text-center' . ($active ? ' fvd-p-filter-pill--active' : '');
            ?>
            <a class="<?= $cls ?>" href="<?= htmlspecialchars($rankingPublicUrl($catRaw, $key), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($result['rows'] === []): ?>
    <div class="fvd-p-card rounded-xl p-10 text-center">
        <p class="fvd-p-muted text-sm">Sin registros con estos filtros.</p>
    </div>
<?php else: ?>
    <?php
    $offset = ($result['page'] - 1) * $result['per_page'];
    $n = $offset;
    ?>
    <div class="grid w-full grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
        <?php foreach ($result['rows'] as $r): ?>
            <?php ++$n; ?>
            <article class="fvd-p-card flex min-w-0 flex-col rounded-xl p-4 sm:p-5">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <span class="fvd-p-rank-badge shrink-0"><?= $n ?></span>
                    <span class="fvd-p-hero__kicker font-mono text-sm tabular-nums"><?= htmlspecialchars((string) ($r['numfvd'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <h2 class="fvd-p-card-title break-words text-sm font-semibold leading-snug"><?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
                <?php if (($r['cedula'] ?? '') !== ''): ?>
                    <p class="fvd-p-muted mt-2 text-xs">Cédula: <?= htmlspecialchars((string) $r['cedula'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <p class="fvd-p-muted mt-auto pt-3 text-xs leading-relaxed"><?= htmlspecialchars((string) ($r['asociacion_nombre'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($result['pages'] > 1 && $dbError === ''): ?>
    <nav class="mt-8 flex w-full flex-wrap justify-center gap-2 sm:justify-evenly sm:gap-3" aria-label="Paginación">
        <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
            <?php if ($i === $result['page']): ?>
                <span class="fvd-p-filter-pill fvd-p-filter-pill--active min-w-[2.5rem] cursor-default justify-center"><?= $i ?></span>
            <?php else: ?>
                <a class="fvd-p-filter-pill min-w-[2.5rem] justify-center" href="<?= htmlspecialchars($rankingPublicUrl($catRaw, $generoRaw, $i), ENT_QUOTES, 'UTF-8') ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </nav>
<?php endif; ?>

<p class="fvd-p-muted mt-10 text-center text-sm">
    <a href="<?= htmlspecialchars(url('index.php#rankings'), ENT_QUOTES, 'UTF-8') ?>" class="fvd-p-link font-medium">← Volver al inicio</a>
</p>

<?php
public_layout_footer();
