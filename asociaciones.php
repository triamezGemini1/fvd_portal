<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/load_fvd_bootstrap.php';
require_once __DIR__ . '/includes/fvd_public_layout.php';

$page = max(1, (int) ($_GET['p'] ?? 1));
$result = [
    'total' => 0, 'page' => 1, 'per_page' => 60, 'pages' => 1, 'rows' => [],
];
$dbError = '';
try {
    $result = PublicSiteData::asociacionesPaginadas($page, 60);
} catch (Throwable $e) {
    $dbError = 'No se pudieron cargar las asociaciones.';
    if (function_exists('env') && in_array(strtolower((string) env('APP_DEBUG', '')), ['1', 'true', 'yes'], true)) {
        $dbError .= ' (' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . ')';
    }
}

fvd_public_header('Asociaciones federadas', 'asociaciones');
?>

<h1 class="text-2xl font-bold text-inst-blue mb-2">Asociaciones federadas</h1>
<p class="text-sm text-slate-600 mb-8 max-w-2xl">Datos de contacto de las asociaciones regionales registradas. La información proviene del registro oficial de la FVD.</p>

<?php if ($dbError !== ''): ?>
    <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 text-amber-900 px-4 py-3 text-sm"><?= $dbError ?></div>
<?php endif; ?>

<?php if ($result['total'] === 0 && $dbError === ''): ?>
    <p class="text-slate-500 text-sm">No hay asociaciones registradas o activas para mostrar.</p>
<?php else: ?>
    <div class="grid sm:grid-cols-2 gap-4 mb-8">
        <?php foreach ($result['rows'] as $a): ?>
            <article id="asoc-<?= (int) ($a['id'] ?? 0) ?>" class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition-shadow scroll-mt-24">
                <h2 class="font-semibold text-lg text-inst-blue mb-3"><?= htmlspecialchars((string) ($a['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
                <?php if (!empty($a['delegado'])): ?>
                    <p class="text-xs text-slate-500 mb-2">Delegado: <span class="text-slate-700"><?= htmlspecialchars((string) $a['delegado'], ENT_QUOTES, 'UTF-8') ?></span></p>
                <?php endif; ?>
                <dl class="space-y-2 text-sm">
                    <?php if (!empty($a['telefono'])): ?>
                        <div class="flex gap-2">
                            <dt class="text-slate-400 shrink-0 w-20">Teléfono</dt>
                            <dd><a class="text-blue-600 hover:underline" href="tel:<?= htmlspecialchars(preg_replace('/\s+/', '', (string) $a['telefono']), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $a['telefono'], ENT_QUOTES, 'UTF-8') ?></a></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($a['email'])): ?>
                        <div class="flex gap-2">
                            <dt class="text-slate-400 shrink-0 w-20">Correo</dt>
                            <dd class="break-all"><a class="text-blue-600 hover:underline" href="mailto:<?= htmlspecialchars((string) $a['email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $a['email'], ENT_QUOTES, 'UTF-8') ?></a></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($a['direccion'])): ?>
                        <div class="flex gap-2">
                            <dt class="text-slate-400 shrink-0 w-20">Dirección</dt>
                            <dd class="text-slate-700"><?= htmlspecialchars((string) $a['direccion'], ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($a['numreg'])): ?>
                        <div class="flex gap-2">
                            <dt class="text-slate-400 shrink-0 w-20">Registro</dt>
                            <dd class="text-slate-600 text-xs"><?= htmlspecialchars((string) $a['numreg'], ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if ($result['pages'] > 1): ?>
        <nav class="flex flex-wrap gap-2 justify-center text-sm" aria-label="Paginación">
            <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
                <?php if ($i === $result['page']): ?>
                    <span class="px-3 py-1 rounded-md bg-inst-blue text-white"><?= $i ?></span>
                <?php else: ?>
                    <a class="px-3 py-1 rounded-md border border-slate-200 hover:bg-slate-100" href="?p=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php
fvd_public_footer();
