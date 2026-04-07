<?php
/** @var InscripcionesController $ctrl */
?>

<h1>Inscripciones a torneos</h1>
<div class="fvd-ins-box">
    <p>El modelo <code>Inscripcion</code> clásico actualiza campos en <strong>atletas</strong> (<code>inscripcion</code>, <code>torneo_id</code>). En esta instalación independiente (<code>fvd_portal</code>) el PHP legado <code>/inscripciones/</code> no está enlazado; use el listado por tabla o gestione datos desde los módulos de atletas/torneos.</p>
    <div class="fvd-ins-actions">
        <a class="primary" href="<?= htmlspecialchars(fvd_module_url('inscripcion_torneo/index.php'), ENT_QUOTES, 'UTF-8') ?>">Tabla <code>inscripcion_torneo</code></a>
        <a class="secondary" href="<?= htmlspecialchars(fvd_module_url('atletas/index.php?action=list'), ENT_QUOTES, 'UTF-8') ?>">Módulo Atletas</a>
    </div>
    <p class="fvd-sidebar-hint" style="margin-top:12px;">Si necesita el flujo completo <code>inscripciones/gestionar.php</code> del proyecto anterior, cópielo junto con su <code>bootstrap.php</code> y ajuste rutas, o mantenga ambos sitios en paralelo.</p>
</div>
