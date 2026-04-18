<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/load_fvd_bootstrap.php';
require_once __DIR__ . '/includes/fvd_public_layout.php';

$pageProx = max(1, (int) ($_GET['px'] ?? 1));
$pagePast = max(1, (int) ($_GET['ps'] ?? 1));

$data = [
    'proximos' => ['total' => 0, 'page' => 1, 'per_page' => 25, 'pages' => 1, 'rows' => []],
    'pasados'  => ['total' => 0, 'page' => 1, 'per_page' => 40, 'pages' => 1, 'rows' => []],
];
$dbError = '';
try {
    $data = PublicSiteData::calendarioPaginado($pageProx, $pagePast, 25, 40);
} catch (Throwable $e) {
    $dbError = 'No se pudo cargar el calendario.';
    if (function_exists('env') && in_array(strtolower((string) env('APP_DEBUG', '')), ['1', 'true', 'yes'], true)) {
        $dbError .= ' (' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . ')';
    }
}

$linkPage = static function (int $px, int $ps): string {
    return htmlspecialchars(url('calendario.php?px=' . $px . '&ps=' . $ps), ENT_QUOTES, 'UTF-8');
};

fvd_public_header('Calendario y resultados', 'calendario');
?>

<h1 class="text-2xl font-bold text-inst-blue mb-2">Calendario y resultados</h1>
<p class="text-sm text-slate-600 mb-8">Torneos próximos y historial de competencias registradas en el sistema.</p>

<?php if ($dbError !== ''): ?>
    <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 text-amber-900 px-4 py-3 text-sm"><?= $dbError ?></div>
<?php endif; ?>

<section class="mb-12">
    <h2 class="text-lg font-semibold text-inst-blue mb-4 flex items-center gap-2">
        <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Próximos torneos
    </h2>
    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white text-xs sm:text-sm">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-100 text-slate-600">
                    <th class="px-2 sm:px-3 py-2 font-semibold whitespace-nowrap">Fecha</th>
                    <th class="px-2 sm:px-3 py-2 font-semibold">Evento</th>
                    <th class="px-2 sm:px-3 py-2 font-semibold hidden md:table-cell">Lugar</th>
                    <th class="px-2 sm:px-3 py-2 font-semibold hidden lg:table-cell">Organiza</th>
                    <th class="px-2 sm:px-3 py-2 font-semibold whitespace-nowrap">Tipo</th>
                    <th class="px-2 sm:px-3 py-2 font-semibold whitespace-nowrap text-right">Consulta</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($data['proximos']['rows'])): ?>
                    <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">No hay torneos futuros registrados.</td></tr>
                <?php else: ?>
                    <?php foreach ($data['proximos']['rows'] as $t): ?>
                        <?php
                        $fecha = !empty($t['fechator']) ? date('d/m/Y', strtotime((string) $t['fechator'])) : '—';
                        $urlPub = url('torneo_publico.php?id=' . (int) ($t['torneo'] ?? 0));
                        ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-2 sm:px-3 py-2 text-slate-500 whitespace-nowrap"><?= htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-2 sm:px-3 py-2 font-medium text-inst-blue"><?= htmlspecialchars((string) ($t['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-2 sm:px-3 py-2 text-slate-600 hidden md:table-cell"><?= htmlspecialchars((string) ($t['lugar'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-2 sm:px-3 py-2 text-slate-500 text-xs hidden lg:table-cell"><?= htmlspecialchars((string) ($t['org_nombre'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-2 sm:px-3 py-2 text-xs text-slate-500"><?= htmlspecialchars(fvd_public_torneo_tipo_label($t['tipo'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-2 sm:px-3 py-2 text-right whitespace-nowrap"><a class="text-inst-blue font-medium text-xs hover:underline" href="<?= htmlspecialchars($urlPub, ENT_QUOTES, 'UTF-8') ?>">Ficha</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($data['proximos']['pages'] > 1): ?>
        <nav class="mt-3 flex flex-wrap gap-1 justify-end text-xs">
            <?php if ($data['proximos']['page'] > 1): ?>
                <a class="px-2 py-1 rounded border border-slate-200 hover:bg-slate-100" href="<?= $linkPage($data['proximos']['page'] - 1, $pagePast) ?>">Anterior</a>
            <?php endif; ?>
            <span class="px-2 py-1 text-slate-500">Pág. <?= (int) $data['proximos']['page'] ?> / <?= (int) $data['proximos']['pages'] ?></span>
            <?php if ($data['proximos']['page'] < $data['proximos']['pages']): ?>
                <a class="px-2 py-1 rounded border border-slate-200 hover:bg-slate-100" href="<?= $linkPage($data['proximos']['page'] + 1, $pagePast) ?>">Siguiente</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</section>

<section>
    <h2 class="text-lg font-semibold text-inst-blue mb-4 flex items-center gap-2">
        <span class="h-2 w-2 rounded-full bg-slate-400"></span> Torneos realizados
    </h2>
    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white text-xs sm:text-sm">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-100 text-slate-600">
                    <th class="px-2 sm:px-3 py-2 font-semibold whitespace-nowrap">Fecha</th>
                    <th class="px-2 sm:px-3 py-2 font-semibold">Evento</th>
                    <th class="px-2 sm:px-3 py-2 font-semibold hidden md:table-cell">Lugar</th>
                    <th class="px-2 sm:px-3 py-2 font-semibold hidden lg:table-cell">Organiza</th>
                    <th class="px-2 sm:px-3 py-2 font-semibold whitespace-nowrap">Modalidad</th>
                    <th class="px-2 sm:px-3 py-2 font-semibold whitespace-nowrap text-right">Consulta</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($data['pasados']['rows'])): ?>
                    <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">Sin torneos pasados en el registro.</td></tr>
                <?php else: ?>
                    <?php foreach ($data['pasados']['rows'] as $t): ?>
                        <?php
                        $fecha = !empty($t['fechator']) ? date('d/m/Y', strtotime((string) $t['fechator'])) : '—';
                        $urlPubP = url('torneo_publico.php?id=' . (int) ($t['torneo'] ?? 0));
                        ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-2 sm:px-3 py-2 text-slate-500 whitespace-nowrap"><?= htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-2 sm:px-3 py-2 font-medium text-inst-blue"><?= htmlspecialchars((string) ($t['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-2 sm:px-3 py-2 text-slate-600 hidden md:table-cell"><?= htmlspecialchars((string) ($t['lugar'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-2 sm:px-3 py-2 text-slate-500 text-xs hidden lg:table-cell"><?= htmlspecialchars((string) ($t['org_nombre'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-2 sm:px-3 py-2 text-xs text-slate-500"><?= htmlspecialchars(fvd_public_torneo_clase_label($t['clase'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-2 sm:px-3 py-2 text-right whitespace-nowrap"><a class="text-inst-blue font-medium text-xs hover:underline" href="<?= htmlspecialchars($urlPubP, ENT_QUOTES, 'UTF-8') ?>">Ficha</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($data['pasados']['pages'] > 1): ?>
        <nav class="mt-3 flex flex-wrap gap-1 justify-end text-xs">
            <?php if ($data['pasados']['page'] > 1): ?>
                <a class="px-2 py-1 rounded border border-slate-200 hover:bg-slate-100" href="<?= $linkPage($pageProx, $data['pasados']['page'] - 1) ?>">Anterior</a>
            <?php endif; ?>
            <span class="px-2 py-1 text-slate-500">Pág. <?= (int) $data['pasados']['page'] ?> / <?= (int) $data['pasados']['pages'] ?></span>
            <?php if ($data['pasados']['page'] < $data['pasados']['pages']): ?>
                <a class="px-2 py-1 rounded border border-slate-200 hover:bg-slate-100" href="<?= $linkPage($pageProx, $data['pasados']['page'] + 1) ?>">Siguiente</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</section>

<?php
fvd_public_footer();
