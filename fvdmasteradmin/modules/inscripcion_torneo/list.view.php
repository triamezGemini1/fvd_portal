<?php
/** @var array{total:int,page:int,per_page:int,pages:int,rows:list<array<string,mixed>>} $result */
/** @var list<array<string,mixed>> $torneosF */
/** @var string $selfUrl */
/** @var int $filterT */
/** @var string $fvd_error */
/** @var string $fvd_ok */
/** @var string $fvd_url_inscripcion_sitio enlace a torneo_inscripción (en sitio), vacío si no hay torneo filtrado */
/** @var string $fvdTorneoActivoNombre */
/** @var string $fvdCupoLinea */
/** @var bool $fvd_ins_sin_torneo */
/** @var bool $fvd_inscripcion_torneo_tabla si existe el volcado auxiliar inscripcion_torneo */
$fvd_error = isset($fvd_error) ? (string) $fvd_error : '';
$fvd_ok = isset($fvd_ok) ? (string) $fvd_ok : '';
$fvd_url_inscripcion_sitio = isset($fvd_url_inscripcion_sitio) ? (string) $fvd_url_inscripcion_sitio : '';
$fvdTorneoActivoNombre = isset($fvdTorneoActivoNombre) ? (string) $fvdTorneoActivoNombre : '';
$fvdCupoLinea = isset($fvdCupoLinea) ? (string) $fvdCupoLinea : '';
$fvd_ins_sin_torneo = !empty($fvd_ins_sin_torneo);
$fvd_inscripcion_torneo_tabla = !empty($fvd_inscripcion_torneo_tabla);
if (!function_exists('url')) {
    require_once dirname(__DIR__, 3) . '/config/paths.php';
}
$fvdEsDelegadoIns = AuthService::isDelegadoAsociacion();
$fvdInsTorneoLabel = $fvdTorneoActivoNombre !== '' ? $fvdTorneoActivoNombre : ('Torneo #' . $filterT);
$fvd_inscripcion_api_url = isset($fvd_inscripcion_api_url) ? (string) $fvd_inscripcion_api_url : '';
$fvdInscAsocIdQuery = isset($fvdInscAsocIdQuery) ? (int) $fvdInscAsocIdQuery : 0;
$fvdInscTorneoClase = isset($fvdInscTorneoClase) ? (int) $fvdInscTorneoClase : 1;
$fvdInscSustituirModal = $filterT > 0
    && $fvd_inscripcion_api_url !== ''
    && $fvdInscTorneoClase === 1;
?>
<link rel="stylesheet" href="<?= htmlspecialchars(url('assets/css/fvd-insc-forms-panel.css'), ENT_QUOTES, 'UTF-8') ?>">
<?php if (function_exists('fvd_delegado_inner_heading_visible') && fvd_delegado_inner_heading_visible()): ?>
<h1>Administrador de inscripciones</h1>
<?php endif; ?>
<?php if ($fvd_error !== ''): ?>
    <p class="fvd-mod-msg"><?= htmlspecialchars($fvd_error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($fvd_ok !== ''): ?>
    <p class="fvd-mod-msg" style="color:#86efac"><?= htmlspecialchars($fvd_ok, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<?php if ($fvd_ins_sin_torneo && $fvdEsDelegadoIns): ?>
    <p class="fvd-muted" style="font-size:.8125rem;margin-bottom:10px;max-width:42rem">
        Sin torneo en la URL: use el panel del delegado o elija un torneo del campeonato en el bloque siguiente.
    </p>
<?php endif; ?>

<?php include __DIR__ . '/partial_insc_ctx_estadisticas.php'; ?>

<?php if ($filterT > 0 && $result['rows'] === []): ?>
    <p class="fvd-muted">No hay inscripciones activas en este torneo para su club.</p>
<?php elseif ($filterT > 0): ?>
    <div class="fvd-insc-rep-wrap" role="region" aria-label="Informe de inscritos">
        <table class="fvd-insc-rep-line">
            <thead>
            <tr>
                <th class="fvd-insc-rep-foto" scope="col">Foto</th>
                <th scope="col" class="fvd-insc-rep-num">Nº FVD</th>
                <th scope="col">Cédula</th>
                <th scope="col">Nombre</th>
                <th scope="col" class="fvd-insc-rep-num">Sx</th>
                <th scope="col" class="fvd-insc-rep-num">Ins</th>
                <th scope="col" class="fvd-insc-rep-num">Afi</th>
                <th scope="col" class="fvd-insc-rep-num">Anu</th>
                <th scope="col" class="fvd-insc-rep-num">Car</th>
                <th scope="col" class="fvd-insc-rep-num">Tra</th>
                <th scope="col" class="fvd-insc-rep-actions">Retirar · Sustituir</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($result['rows'] as $r): ?>
                <?php
                $iid = (int) ($r['id'] ?? 0);
                $fotoFn = trim((string) ($r['foto'] ?? ($r['atleta_foto'] ?? '')));
                $fotoSrc = $fotoFn !== '' ? url('crud_atletas/uploads/' . ltrim($fotoFn, '/')) : '';
                $nomFull = (string) ($r['nombre'] ?? '');
                $sxRaw = is_numeric($r['sexo'] ?? null) ? (string) (int) ($r['sexo'] ?? 0) : trim((string) ($r['sexo'] ?? ''));
                $yn1 = static fn (int $v): string => $v === 1 ? '1' : '0';
                ?>
                <tr>
                    <td class="fvd-insc-rep-foto">
                        <?php if ($fotoSrc !== ''): ?>
                            <img src="<?= htmlspecialchars($fotoSrc, ENT_QUOTES, 'UTF-8') ?>" alt="" width="36" height="36" loading="lazy">
                        <?php else: ?>
                            <span class="fvd-muted" style="font-size:.65rem;display:inline-block;width:36px;text-align:center">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="fvd-insc-rep-num"><?= (int) ($r['numfvd'] ?? 0) ?></td>
                    <td><?= htmlspecialchars((string) ($r['cedula'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="fvd-insc-rep-nombre" title="<?= htmlspecialchars($nomFull, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($nomFull, ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="fvd-insc-rep-num"><?= htmlspecialchars($sxRaw, ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="fvd-insc-rep-num"><?= (int) ($r['inscripcion'] ?? 0) ?></td>
                    <td class="fvd-insc-rep-num"><?= $yn1((int) ($r['afiliacion'] ?? 0)) ?></td>
                    <td class="fvd-insc-rep-num"><?= $yn1((int) ($r['anualidad'] ?? 0)) ?></td>
                    <td class="fvd-insc-rep-num"><?= $yn1((int) ($r['carnet'] ?? 0)) ?></td>
                    <td class="fvd-insc-rep-num"><?= $yn1((int) ($r['traspaso'] ?? 0)) ?></td>
                    <td class="fvd-insc-rep-actions">
                        <span class="fvd-insc-rep-actions-inner">
                            <form method="post" action="<?= htmlspecialchars($selfUrl . '?torneo_id=' . $filterT, ENT_QUOTES, 'UTF-8') ?>"
                                  onsubmit="return confirm('¿Retirar a este atleta del torneo? Se desmarcará la inscripción en su ficha y se actualizará la deuda.');">
                                <input type="hidden" name="_action" value="retirar_inscripcion_atleta">
                                <input type="hidden" name="atleta_id" value="<?= $iid ?>">
                                <input type="hidden" name="torneo_id" value="<?= (int) $filterT ?>">
                                <button type="submit" class="fvd-insc-rep-btn-ret">Retirar</button>
                            </form>
                            <?php if ($fvdInscSustituirModal): ?>
                                <button type="button" class="fvd-insc-rep-link-sust"
                                        data-fvd-sust-sale-id="<?= $iid ?>"
                                        data-fvd-sust-sale-nom="<?= htmlspecialchars($nomFull, ENT_QUOTES, 'UTF-8') ?>">
                                    Sustituir
                                </button>
                            <?php elseif ($fvd_url_inscripcion_sitio !== ''): ?>
                                <a class="fvd-insc-rep-link-sust" href="<?= htmlspecialchars($fvd_url_inscripcion_sitio, ENT_QUOTES, 'UTF-8') ?>">Sustituir (sitio)</a>
                            <?php else: ?>
                                <span class="fvd-muted" title="Defina enlace a sitio">—</span>
                            <?php endif; ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    if ($result['pages'] > 1):
        $qPrev = array_merge($_GET, ['torneo_id' => $filterT, 'p' => $result['page'] - 1]);
        $qNext = array_merge($_GET, ['torneo_id' => $filterT, 'p' => $result['page'] + 1]);
        unset($qPrev['fvd_err'], $qPrev['fvd_ok'], $qNext['fvd_err'], $qNext['fvd_ok']);
        ?>
        <nav class="fvd-actions" style="margin-top:1.25rem;">
            <?php if ($result['page'] > 1): ?>
                <a href="<?= htmlspecialchars($selfUrl . '?' . http_build_query($qPrev), ENT_QUOTES, 'UTF-8') ?>">← Anterior</a>
            <?php endif; ?>
            <span class="fvd-muted">Página <?= (int) $result['page'] ?> / <?= (int) $result['pages'] ?></span>
            <?php if ($result['page'] < $result['pages']): ?>
                <a href="<?= htmlspecialchars($selfUrl . '?' . http_build_query($qNext), ENT_QUOTES, 'UTF-8') ?>">Siguiente →</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php if ($filterT > 0): ?>
<p class="fvd-actions" style="margin-top:1.5rem;">
    <a href="<?= htmlspecialchars(fvd_module_url('inscripciones/index.php?torneo_id=' . $filterT), ENT_QUOTES, 'UTF-8') ?>">Reportes PDF y finanzas (módulo inscripciones)</a>
</p>
<?php endif; ?>

<?php if ($fvdInscSustituirModal): ?>
<div id="fvd-insc-sust-overlay" class="fvd-insc-sust-overlay" aria-hidden="true">
    <div class="fvd-insc-sust-dialog" role="dialog" aria-modal="true" aria-labelledby="fvd-insc-sust-title">
        <h3 id="fvd-insc-sust-title">Sustituir por cédula</h3>
        <p class="fvd-muted" style="font-size:.78rem;margin:0 0 10px" id="fvd-insc-sust-sale-line"></p>
        <div class="fvd-insc-sust-row">
            <div style="flex:0 0 3.5rem">
                <label for="fvd-insc-sust-nac">Nac.</label>
                <input type="text" id="fvd-insc-sust-nac" class="fvd-input" maxlength="1" value="V" autocomplete="off">
            </div>
            <div style="flex:1;min-width:8rem">
                <label for="fvd-insc-sust-ced">Cédula (reemplazo)</label>
                <input type="text" id="fvd-insc-sust-ced" class="fvd-input" inputmode="numeric" maxlength="15" placeholder="Números" autocomplete="off">
            </div>
        </div>
        <button type="button" class="fvd-mtf-btn fvd-mtf-btn--secondary" id="fvd-insc-sust-buscar" style="font-size:.78rem">Buscar en el club</button>
        <p id="fvd-insc-sust-preview" class="fvd-muted" style="font-size:.8rem;margin:8px 0 0;min-height:1.25em"></p>
        <p id="fvd-insc-sust-msg" class="fvd-insc-sust-msg" role="status"></p>
        <form method="post" action="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" id="fvd-insc-sust-form" class="fvd-insc-sust-form-offscreen" aria-hidden="true">
            <input type="hidden" name="_action" value="sustituir_inscripcion_atleta">
            <input type="hidden" name="torneo_id" value="<?= (int) $filterT ?>">
            <input type="hidden" name="atleta_salida_id" id="fvd-insc-sust-sale" value="">
            <input type="hidden" name="atleta_entrada_id" id="fvd-insc-sust-entra" value="">
        </form>
        <div class="fvd-insc-sust-actions">
            <button type="button" class="fvd-mtf-btn fvd-mtf-btn--secondary" id="fvd-insc-sust-cancel">Cancelar</button>
            <button type="submit" class="fvd-mtf-btn fvd-mtf-btn--secondary" id="fvd-insc-sust-confirm" form="fvd-insc-sust-form" disabled>Confirmar sustitución</button>
        </div>
    </div>
</div>
<script>
(function () {
    var overlay = document.getElementById('fvd-insc-sust-overlay');
    if (!overlay) return;
    var api = <?= json_encode($fvd_inscripcion_api_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var torneoId = <?= (int) $filterT ?>;
    var asocQ = <?= (int) $fvdInscAsocIdQuery ?>;
    var saleInput = document.getElementById('fvd-insc-sust-sale');
    var entraInput = document.getElementById('fvd-insc-sust-entra');
    var saleLine = document.getElementById('fvd-insc-sust-sale-line');
    var nacEl = document.getElementById('fvd-insc-sust-nac');
    var cedEl = document.getElementById('fvd-insc-sust-ced');
    var msgEl = document.getElementById('fvd-insc-sust-msg');
    var prevEl = document.getElementById('fvd-insc-sust-preview');
    var btnBuscar = document.getElementById('fvd-insc-sust-buscar');
    var btnOk = document.getElementById('fvd-insc-sust-confirm');
    var btnCancel = document.getElementById('fvd-insc-sust-cancel');
    var form = document.getElementById('fvd-insc-sust-form');

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function setMsg(text, err) {
        msgEl.textContent = text || '';
        msgEl.className = 'fvd-insc-sust-msg' + (err ? ' fvd-insc-sust-msg--err' : (text ? ' fvd-insc-sust-msg--ok' : ''));
    }

    function closeModal() {
        overlay.classList.remove('fvd-insc-sust-overlay--open');
        overlay.setAttribute('aria-hidden', 'true');
        cedEl.value = '';
        prevEl.textContent = '';
        entraInput.value = '';
        btnOk.disabled = true;
        setMsg('');
    }

    function openModal(saleId, saleNom) {
        saleInput.value = String(saleId);
        saleLine.innerHTML = 'Sale: <strong>' + esc(saleNom) + '</strong> (id ' + saleId + ')';
        entraInput.value = '';
        prevEl.textContent = '';
        cedEl.value = '';
        btnOk.disabled = true;
        setMsg('');
        overlay.classList.add('fvd-insc-sust-overlay--open');
        overlay.setAttribute('aria-hidden', 'false');
        cedEl.focus();
    }

    document.querySelectorAll('[data-fvd-sust-sale-id]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openModal(btn.getAttribute('data-fvd-sust-sale-id'), btn.getAttribute('data-fvd-sust-sale-nom') || '');
        });
    });

    btnCancel.addEventListener('click', closeModal);
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeModal();
    });

    btnBuscar.addEventListener('click', function () {
        setMsg('');
        entraInput.value = '';
        btnOk.disabled = true;
        prevEl.textContent = '';
        var nac = (nacEl.value || 'V').trim().charAt(0) || 'V';
        var ced = (cedEl.value || '').replace(/\D/g, '');
        if (ced.length < 4) {
            setMsg('Indique al menos 4 dígitos de cédula.', true);
            return;
        }
        var u = api + '?action=buscar_cedula_sitio&torneo_id=' + encodeURIComponent(String(torneoId))
            + '&modo_bandera=1&nacionalidad=' + encodeURIComponent(nac) + '&cedula=' + encodeURIComponent(ced);
        if (asocQ > 0) u += '&asociacion_id=' + encodeURIComponent(String(asocQ));
        fetch(u, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
            if (!j || !j.ok) {
                setMsg((j && j.error) ? j.error : 'Error en la búsqueda.', true);
                return;
            }
            if (j.resultado === 'atleta' && j.atleta) {
                var id = parseInt(j.atleta.id, 10);
                if (id === parseInt(saleInput.value, 10)) {
                    setMsg('El reemplazo no puede ser el mismo atleta.', true);
                    return;
                }
                entraInput.value = String(id);
                prevEl.textContent = 'Reemplazo: ' + (j.atleta.nombre || '') + ' — CI ' + (j.atleta.cedula || '') + ' — Nº FVD ' + (j.atleta.numfvd || 0);
                btnOk.disabled = false;
                setMsg('Atleta localizado. Confirme para retirar al saliente e inscribir al entrante.', false);
                return;
            }
            if (j.resultado === 'ya_inscrito') {
                setMsg(j.mensaje || 'Ya inscrito en este torneo.', true);
                return;
            }
            if (j.resultado === 'no_encontrado') {
                setMsg(j.mensaje || 'No encontrado.', true);
                return;
            }
            setMsg(j.mensaje || 'No se pudo usar esta cédula.', true);
        }).catch(function () {
            setMsg('Fallo de red al consultar la cédula.', true);
        });
    });

    form.addEventListener('submit', function (e) {
        var ent = (entraInput.value || '').trim();
        if (!ent || ent === '0') {
            e.preventDefault();
            return false;
        }
        if (!confirm('¿Confirmar sustitución? Se retirará al atleta actual y quedará inscrito el atleta buscado.')) {
            e.preventDefault();
            return false;
        }
        return true;
    });
})();
</script>
<?php endif; ?>
