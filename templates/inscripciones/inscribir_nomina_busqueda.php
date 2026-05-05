<?php
declare(strict_types=1);
/**
 * Búsqueda de atletas, nómina y acciones (tras zona de inscripción y selector de torneo en inscribir_sitio_panel.php).
 * Variables desde el padre: $torneoMeta, $torneoSel, $asocId, $esFvd, $selfUrl, $fvd_campeonato_q, $fvd_asoc_nombre
 */
$fvd_asoc_nombre = isset($fvd_asoc_nombre) ? trim((string) $fvd_asoc_nombre) : '';
$clInscNom = (int) ($torneoMeta['clase'] ?? 1);
if (isset($fvd_sitio_clase) && (int) $fvd_sitio_clase >= 1) {
    $clInscNom = (int) $fvd_sitio_clase;
}
$fvdUseInscSlots = ($clInscNom === 2 || $clInscNom === 3);
?>
    <div class="fvd-insc-wrap fvd-insc-wrap--sitio" id="fvd-insc-root">
        <div class="fvd-insc-sitio-compact-bar">
            <div class="fvd-insc-toolbar fvd-insc-toolbar--sitio-row fvd-mtf-toolbar">
                <div class="fvd-insc-search fvd-insc-search--sitio-inline">
                    <?php
                    $fvdLblBuscar = 'Nombre / CI';
                    $fvdLblBuscarTitle = 'Buscar atleta (nombre o cédula)';
                    if ($fvdUseInscSlots) {
                        $fvdLblBuscarTitle .= ' — o pulse una fila en Disponibles';
                    }
                    ?>
                    <label class="fvd-mtf-form-label" for="fvd-insc-q" title="<?= htmlspecialchars($fvdLblBuscarTitle, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($fvdLblBuscar, ENT_QUOTES, 'UTF-8') ?></label>
                    <input type="search" class="fvd-input fvd-insc-q-input" id="fvd-insc-q" placeholder="Mín. 2 car." autocomplete="off">
                </div>
                <button type="button" class="fvd-insc-btn fvd-insc-btn--comprobante" id="fvd-insc-buscar">Buscar</button>
            </div>
            <div class="fvd-insc-actions fvd-insc-actions--sitio-bar">
                <button type="button" class="fvd-insc-btn fvd-insc-btn--inscribir" id="fvd-insc-submit">Inscribir</button>
                <button type="button" class="fvd-insc-btn fvd-insc-btn--pendiente" id="fvd-insc-clear">Vaciar nómina</button>
                <a class="fvd-insc-btn fvd-insc-btn--comprobante" id="fvd-insc-ver-listado" href="<?= htmlspecialchars($selfUrl . '?torneo_id=' . $torneoSel . ($fvd_campeonato_q !== '' ? $fvd_campeonato_q : '') . ($esFvd ? '&asociacion_id=' . $asocId : ''), ENT_QUOTES, 'UTF-8') ?>">Actualizar</a>
            </div>
        </div>
        <div class="fvd-insc-pager" id="fvd-insc-pager" hidden></div>
        <div class="fvd-insc-results" id="fvd-insc-results" hidden></div>
        <p class="fvd-insc-hint" id="fvd-insc-msg" aria-live="polite"></p>

        <?php if ($fvdUseInscSlots): ?>
        <?php
        $nEqSlots = $clInscNom === 2 ? 2 : (int) ($torneoMeta['integrantes_equipo'] ?? 4);
        if ($nEqSlots < 2) {
            $nEqSlots = 2;
        }
        ?>
        <div class="fvd-insc-eq-card fvd-insc-teamform fvd-mtf-subcard card-formulario-inscripcion-body" id="fvd-insc-teamform">
            <div class="fvd-insc-eq-form" id="fvd-insc-eq-form">
                <input type="hidden" id="fvd-insc-eq-torneo-id" value="<?= (int) $torneoSel ?>">

                <div class="fvd-insc-eq-dos-filas fvd-insc-eq-dos-filas--sitio-row formulario-equipo-dos-filas">
                    <div class="fvd-insc-eq-fila-1 fila-form-equipo-1">
                        <?php if ($fvd_asoc_nombre !== ''): ?>
                        <div class="fvd-insc-eq-campo-club campo-club">
                            <label class="fvd-insc-eq-lbl form-label" for="fvd-insc-eq-club-display">Club / asociación</label>
                            <div id="fvd-insc-eq-club-display" class="fvd-insc-eq-readonly fvd-input" tabindex="0"><?= htmlspecialchars($fvd_asoc_nombre, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <?php endif; ?>
                        <div id="fvd-insc-eq-codigo-wrap" class="fvd-insc-eq-codigo-wrap wrap_codigo_equipo_barra is-hidden" aria-hidden="true">
                            <span class="fvd-insc-eq-codigo-lbl">Cód.</span>
                            <span id="fvd-insc-eq-codigo-badge" class="fvd-insc-eq-codigo-badge"></span>
                        </div>
                        <button type="button" class="fvd-insc-eq-btn-guardar fvd-insc-btn fvd-insc-btn--inscribir" id="fvd-insc-eq-btn-guardar">
                            Guardar equipo
                        </button>
                    </div>
                    <div class="fvd-insc-eq-fila-2 fila-form-equipo-2">
                        <div class="fvd-insc-eq-campo-nombre campo-nombre-equipo">
                            <label class="fvd-insc-eq-lbl form-label" for="fvd-insc-nombre-equipo">Nombre del equipo *</label>
                            <input type="text" class="fvd-input" id="fvd-insc-nombre-equipo" maxlength="200" placeholder="Nombre del equipo *" autocomplete="organization" required>
                        </div>
                        <button type="button" class="fvd-insc-eq-btn-nueva fvd-insc-btn fvd-insc-btn--pendiente" id="fvd-insc-eq-btn-nueva">
                            Nueva equipo
                        </button>
                    </div>
                </div>

                <hr class="fvd-insc-eq-hr">

                <div id="fvd-insc-jugadores-container" class="fvd-insc-jugadores-container jugadores-container">
                    <?php for ($pj = 1; $pj <= $nEqSlots; $pj++): ?>
                    <div class="fvd-insc-eq-fila-jugador fila-jugador-compacta row g-1 align-items-center" data-posicion="<?= $pj ?>">
                        <div class="fvd-insc-eq-col-pos col-auto">
                            <?php if ($pj === 1): ?>
                            <span class="fvd-insc-eq-capitan" title="Capitán / primer integrante">★</span>
                            <?php else: ?>
                            <span class="fvd-insc-eq-num"><?= $pj ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="fvd-insc-eq-col-inputs col wrap-inputs-jugador">
                            <input type="text" class="fvd-input fvd-insc-eq-inp-id jugador-id-usuario" id="fvd-eq-jug-id-vis-<?= $pj ?>" placeholder="Nº FVD" readonly autocomplete="off">
                            <input type="hidden" id="fvd-eq-jug-aid-<?= $pj ?>" value="">
                            <input type="text" class="fvd-input fvd-insc-eq-inp-ced jugador-cedula" id="fvd-eq-jug-ced-<?= $pj ?>" placeholder="Cédula" readonly autocomplete="off">
                            <input type="text" class="fvd-input fvd-insc-eq-inp-nom jugador-nombre" id="fvd-eq-jug-nom-<?= $pj ?>" placeholder="Nombre" readonly autocomplete="off">
                        </div>
                        <div class="fvd-insc-eq-col-accion col-auto">
                            <button type="button" class="fvd-insc-eq-btn-quitar" id="fvd-eq-jug-clear-<?= $pj ?>" title="Quitar del equipo" disabled style="display:none">×</button>
                        </div>
                    </div>
                    <?php if ($pj < $nEqSlots): ?>
                    <div class="fvd-insc-eq-sep separador-jugador"></div>
                    <?php endif; ?>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($fvdUseInscSlots): ?>
        <p class="fvd-insc-hint is-hidden" id="fvd-insc-resumen-empty" style="display:none!important" aria-hidden="true"></p>
        <div id="fvd-insc-resumen-grid" class="is-hidden" style="display:none!important" hidden aria-hidden="true"></div>
        <?php else: ?>
        <?php
        $fvd_resumen_inscripcion = [];
        $fvd_resumen_titulo = null;
        require __DIR__ . '/../components/resumen_inscripcion.php';
        ?>
        <?php endif; ?>
    </div>
