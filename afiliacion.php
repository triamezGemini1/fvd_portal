<?php
declare(strict_types=1);

require_once __DIR__ . '/fvdmasteradmin/bootstrap.php';
require_once __DIR__ . '/includes/fvd_public_layout.php';

fvd_public_header('Afiliación a la FVD', 'afiliacion');
?>

<h1 class="text-2xl font-bold text-inst-blue mb-2">Afiliación institucional</h1>
<p class="text-sm text-slate-600 mb-10 max-w-3xl">Requisitos generales y pasos orientativos para que una asociación o club se vincule a la Federación Venezolana del Dominó. Los montos y formularios definitivos se confirman con la secretaría de la FVD.</p>

<div class="space-y-10">
    <section class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-inst-blue mb-4">Marco legal y documentación</h2>
        <ul class="list-disc pl-5 space-y-2 text-sm text-slate-700 leading-relaxed">
            <li>Personalidad jurídica o reconocimiento deportivo conforme a la normativa nacional aplicable al deporte federado.</li>
            <li>Acta constitutiva o estatutos sociales actualizados y registro ante el ente que corresponda.</li>
            <li>Designación formal del delegado o representante legal ante la FVD.</li>
            <li>Cumplimiento de las disposiciones internas de la federación (reglamentos de competencia, ética y disciplina deportiva).</li>
            <li>Compromiso de aportar la documentación de atletas y torneos según los formatos oficiales de la FVD.</li>
        </ul>
    </section>

    <section class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-inst-blue mb-4">Pasos para solicitar la afiliación</h2>
        <ol class="list-decimal pl-5 space-y-3 text-sm text-slate-700 leading-relaxed">
            <li><strong class="text-slate-800">Contacto inicial:</strong> comunicarse con la secretaría de la FVD o con la asociación regional que corresponda a su región (consulte la sección <a class="text-blue-600 hover:underline" href="<?= htmlspecialchars(url('asociaciones.php'), ENT_QUOTES, 'UTF-8') ?>">Asociaciones</a>).</li>
            <li><strong class="text-slate-800">Expediente:</strong> reunir la documentación legal, datos del delegado, listado provisional de clubes o atletas y comprobante de pago de tasas cuando aplique.</li>
            <li><strong class="text-slate-800">Evaluación:</strong> el comité correspondiente revisa el expediente y puede solicitar subsanaciones o entrevistas.</li>
            <li><strong class="text-slate-800">Resolución:</strong> una vez aprobada la solicitud, se registra la asociación en el sistema de la FVD y se habilitan los accesos de gestión según el rol asignado.</li>
            <li><strong class="text-slate-800">Mantenimiento:</strong> anualidades, reportes de torneos y actualización de datos de contacto deben mantenerse al día para conservar el estatus activo.</li>
        </ol>
    </section>

    <section class="rounded-xl border border-amber-200 bg-amber-50/80 p-6">
        <h2 class="text-lg font-semibold text-amber-900 mb-2">Atención al público</h2>
        <p class="text-sm text-amber-950/90 leading-relaxed">
            Esta página es informativa. Para trámites formales, costos vigentes y citas, utilice los canales oficiales indicados por la federación. El personal autorizado puede gestionar registros a través del
            <a class="font-medium text-amber-950 underline hover:no-underline" href="<?= htmlspecialchars(url('gestion_modulos.php'), ENT_QUOTES, 'UTF-8') ?>">portal de gestión interna</a>
            o del panel <a class="font-medium text-amber-950 underline hover:no-underline" href="<?= htmlspecialchars(url('fvdmasteradmin/login.php'), ENT_QUOTES, 'UTF-8') ?>">FVD Master Admin</a>.
        </p>
    </section>
</div>

<?php
fvd_public_footer();
