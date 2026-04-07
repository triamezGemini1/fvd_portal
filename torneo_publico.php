<?php
declare(strict_types=1);

require_once __DIR__ . '/fvdmasteradmin/bootstrap.php';
require_once __DIR__ . '/includes/fvd_public_layout.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$torneo = null;
$dbError = '';
try {
    $torneo = PublicSiteData::torneoPublicoPorId($id);
} catch (Throwable $e) {
    $dbError = 'No se pudo cargar el evento.';
    if (function_exists('env') && in_array(strtolower((string) env('APP_DEBUG', '')), ['1', 'true', 'yes'], true)) {
        $dbError .= ' (' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . ')';
    }
}

if ($dbError !== '') {
    fvd_public_header('Torneo', 'calendario');
    echo '<div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 text-amber-900 px-4 py-3 text-sm">' . $dbError . '</div>';
    fvd_public_footer();
    exit;
}

if ($torneo === null) {
    fvd_public_header('Torneo no disponible', 'calendario');
    http_response_code(404);
    ?>
    <h1 class="text-2xl font-bold text-inst-blue mb-2">Evento no encontrado</h1>
    <p class="text-sm text-slate-600 mb-6">No existe o no está publicado en el sitio público.</p>
    <p><a href="<?= htmlspecialchars(url('calendario.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-inst-blue font-medium underline">Volver al calendario</a></p>
    <?php
    fvd_public_footer();
    exit;
}

$nombre = (string) ($torneo['nombre'] ?? 'Torneo');
fvd_public_header($nombre, 'calendario');

$fecha = !empty($torneo['fechator']) ? date('d/m/Y', strtotime((string) $torneo['fechator'])) : '—';
$afiche = !empty($torneo['afiche']) ? upload_url((string) $torneo['afiche']) : '';
$invFile = !empty($torneo['invitacion']) ? (string) $torneo['invitacion'] : '';
$invExt = $invFile !== '' ? strtolower(pathinfo($invFile, PATHINFO_EXTENSION)) : '';
$invIsImage = $invExt !== '' && in_array($invExt, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true);
$invUrlDescarga = url('torneo_invitacion_descarga.php?id=' . $id);
$invUrlLeer = url('torneo_invitacion_descarga.php?id=' . $id . '&inline=1');
$estatus = (int) ($torneo['estatus'] ?? 0);
$estLabel = $estatus === 1 ? 'En proceso' : ($estatus === 0 ? 'Planificado / próximo' : 'Estatus ' . $estatus);
?>

<nav class="mb-6 text-sm">
    <a href="<?= htmlspecialchars(url('index.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-slate-500 hover:text-inst-blue">Inicio</a>
    <span class="text-slate-400 mx-1">/</span>
    <a href="<?= htmlspecialchars(url('calendario.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-slate-500 hover:text-inst-blue">Calendario</a>
    <span class="text-slate-400 mx-1">/</span>
    <span class="text-slate-700"><?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?></span>
</nav>

<article class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <?php if ($afiche !== ''): ?>
        <div class="aspect-[21/9] max-h-80 overflow-hidden bg-slate-100">
            <img src="<?= htmlspecialchars($afiche, ENT_QUOTES, 'UTF-8') ?>" alt="" class="h-full w-full object-cover">
        </div>
    <?php endif; ?>
    <div class="p-6 sm:p-8">
        <p class="text-xs font-semibold uppercase tracking-wider text-emerald-600"><?= htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8') ?></p>
        <h1 class="mt-2 text-2xl font-bold text-inst-blue leading-tight"><?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="mt-1 text-sm text-slate-500"><?= htmlspecialchars($estLabel, ENT_QUOTES, 'UTF-8') ?></p>

        <dl class="mt-8 grid gap-4 sm:grid-cols-2 text-sm">
            <div>
                <dt class="text-slate-500 font-medium">Lugar</dt>
                <dd class="mt-1 text-slate-800"><?= htmlspecialchars((string) ($torneo['lugar'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div>
                <dt class="text-slate-500 font-medium">Organiza</dt>
                <dd class="mt-1 text-slate-800"><?= htmlspecialchars((string) ($torneo['org_nombre'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div>
                <dt class="text-slate-500 font-medium">Tipo</dt>
                <dd class="mt-1 text-slate-800"><?= htmlspecialchars(fvd_public_torneo_tipo_label($torneo['tipo'] ?? null), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div>
                <dt class="text-slate-500 font-medium">Modalidad</dt>
                <dd class="mt-1 text-slate-800"><?= htmlspecialchars(fvd_public_torneo_clase_label($torneo['clase'] ?? null), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <?php if (!empty($torneo['clavetor'])): ?>
            <div>
                <dt class="text-slate-500 font-medium">Clave del evento</dt>
                <dd class="mt-1 font-mono text-slate-800"><?= htmlspecialchars((string) $torneo['clavetor'], ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <?php endif; ?>
            <?php
            $costo = $torneo['costotor'] ?? null;
            if ($costo !== null && $costo !== '' && (float) $costo != 0.0):
            ?>
            <div>
                <dt class="text-slate-500 font-medium">Costo referencial</dt>
                <dd class="mt-1 text-slate-800"><?= htmlspecialchars(number_format((float) $costo, 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <?php endif; ?>
        </dl>

        <?php if ($invFile !== ''): ?>
            <div class="mt-8 pt-6 border-t border-slate-100">
                <h2 class="text-sm font-semibold text-inst-blue mb-4">Invitación</h2>
                <div class="flex flex-col sm:flex-row gap-5 sm:items-start">
                    <div class="shrink-0 w-full sm:w-44" aria-hidden="true">
                        <?php if ($invIsImage): ?>
                            <div class="flex h-36 w-full sm:w-44 flex-col items-center justify-center rounded-lg border border-teal-200 bg-teal-50 text-teal-900 shadow-sm">
                                <svg class="h-12 w-12 opacity-85" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/>
                                    <circle cx="8.5" cy="10" r="1.5" fill="currentColor"/>
                                    <path d="M3 17l5-5 4 4 5-6v6H3v-5z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                </svg>
                                <span class="mt-2 text-xs font-bold uppercase tracking-wide">Imagen</span>
                            </div>
                        <?php elseif ($invExt === 'pdf'): ?>
                            <div class="flex h-36 w-full sm:w-44 flex-col items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-800 shadow-sm">
                                <svg class="h-14 w-14 opacity-90" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                    <path d="M14 2v6h6M8 13h8M8 17h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                                <span class="mt-2 text-xs font-bold uppercase tracking-wide">PDF</span>
                            </div>
                        <?php else: ?>
                            <div class="flex h-36 w-full sm:w-44 flex-col items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-slate-600">
                                <svg class="h-12 w-12 opacity-80" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z" stroke="currentColor" stroke-width="1.5"/>
                                    <path d="M14 2v6h6" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                                <span class="mt-2 text-xs font-semibold"><?= htmlspecialchars(strtoupper($invExt !== '' ? $invExt : 'archivo'), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-slate-600 mb-3">
                            El archivo de convocatoria no se muestra automáticamente. Use los botones cuando desee abrirlo o guardarlo.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <a href="<?= htmlspecialchars($invUrlLeer, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center justify-center rounded-lg border border-inst-blue/30 bg-white px-4 py-2.5 text-sm font-semibold text-inst-blue shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-inst-gold focus:ring-offset-2">
                                Ver en el navegador
                            </a>
                            <a href="<?= htmlspecialchars($invUrlDescarga, ENT_QUOTES, 'UTF-8') ?>"
                               class="inline-flex items-center justify-center rounded-lg bg-inst-blue px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-inst-blue/90 focus:outline-none focus:ring-2 focus:ring-inst-gold focus:ring-offset-2">
                                Descargar
                            </a>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">«Ver» abre el documento en una pestaña nueva; «Descargar» lo guarda en su equipo.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($torneo['org_email']) || !empty($torneo['org_telefono'])): ?>
            <div class="mt-8 pt-6 border-t border-slate-100">
                <h2 class="text-sm font-semibold text-inst-blue mb-3">Contacto organizador</h2>
                <ul class="text-sm text-slate-600 space-y-1">
                    <?php if (!empty($torneo['org_email'])): ?>
                        <li><a class="text-inst-blue hover:underline" href="mailto:<?= htmlspecialchars((string) $torneo['org_email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $torneo['org_email'], ENT_QUOTES, 'UTF-8') ?></a></li>
                    <?php endif; ?>
                    <?php if (!empty($torneo['org_telefono'])): ?>
                        <li><?= htmlspecialchars((string) $torneo['org_telefono'], ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</article>

<p class="mt-8 text-center">
    <a href="<?= htmlspecialchars(url('calendario.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-medium text-inst-blue hover:underline">← Calendario completo</a>
</p>

<?php
fvd_public_footer();
