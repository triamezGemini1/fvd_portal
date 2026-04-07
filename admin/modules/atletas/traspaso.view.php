<?php
declare(strict_types=1);
/** @var string $selfUrl */
/** @var string $atletasListUrl */
/** @var string $carnetFotoApiUrl */
/** @var int $atletaIdTraspaso */
/** @var string $currentAsocNombre */
/** @var list<array<string, mixed>> $asociacionesDestino */
/** @var array<string, mixed> $cardPreview */
/** @var array<string, string> $asocMap */
/** @var string $traspasoError */

if (!function_exists('url')) {
    require_once FVD_PROJECT_ROOT . '/config/paths.php';
}
$carnetUnSolo = true;
$card = $cardPreview;
$hayDestinos = $asociacionesDestino !== [];
$traspasoFotoPreviewUrl = !empty($card['foto_url']) ? (string) $card['foto_url'] : '';
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traspaso de asociación FVD</title>
    <style>
        :root { --fvd-azul: #2e3092; --fvd-ama: #fff200; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, Segoe UI, sans-serif; background: #0f172a; color: #f8fafc; min-height: 100vh; }
        .fvd-carnet-shell { margin: 0 auto; padding: 18px 21px 36px; max-width: 510px; }
        .fvd-carnet-toolbar.fvd-carnet-no-print {
            display: flex; flex-wrap: wrap; gap: 12px; align-items: center;
            margin-bottom: 18px;
        }
        .fvd-carnet-toolbar a, .fvd-carnet-toolbar button {
            cursor: pointer; border: 1px solid #475569; background: #334155; color: #f8fafc;
            padding: 11px 18px; border-radius: 9px; font-size: 18px; font-weight: 600;
            text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
        }
        .fvd-carnet-toolbar a:hover, .fvd-carnet-toolbar button:hover { background: #475569; }
        .fvd-carnet-toolbar .fvd-carnet-btn--primary {
            background: #2e3092; border-color: var(--fvd-ama); color: var(--fvd-ama);
        }
        .fvd-carnet-toolbar .fvd-carnet-btn--primary:hover { background: #3a3eb5; }
        .fvd-carnet-toolbar .fvd-carnet-btn--ghost { background: transparent; border-color: #64748b; }
        .fvd-carnet-indicador {
            font-size: 17px; color: #94a3b8; flex: 1 1 100%;
            margin: 0 0 6px 0; line-height: 1.35;
        }
        .fvd-traspaso-error {
            flex: 1 1 100%; margin: 0 0 8px 0; padding: 10px 12px; border-radius: 8px;
            background: rgba(220, 38, 38, 0.2); border: 1px solid #b91c1c; color: #fecaca; font-size: 16px;
        }
        .fvd-traspaso-form .fvd-traspaso-select-wrap {
            margin-bottom: 18px; padding: 15px; border-radius: 12px; border: 1px solid #334155; background: #1e293b;
        }
        .fvd-traspaso-form .fvd-traspaso-select-wrap label {
            font-size: 17px; font-weight: 600; display: block; margin-bottom: 9px; color: #cbd5e1;
        }
        .fvd-traspaso-form .fvd-traspaso-select-wrap select {
            width: 100%; max-width: 100%; font-size: 18px; padding: 10px 12px; border-radius: 8px;
            border: 1px solid #475569; background: #0f172a; color: #f8fafc;
        }
        .fvd-traspaso-current { font-size: 16px; color: #94a3b8; margin: 0 0 12px 0; line-height: 1.4; }
        .fvd-carnet-foto-box.fvd-carnet-no-print {
            margin-top: 18px; padding: 15px; border-radius: 12px; border: 1px solid #334155; background: #1e293b;
        }
        .fvd-carnet-foto-box label { font-size: 17px; font-weight: 600; display: block; margin-bottom: 9px; color: #cbd5e1; }
        .fvd-carnet-foto-box input[type=file] { font-size: 18px; max-width: 100%; color: #e2e8f0; }
        .fvd-carnet-foto-box button { margin-top: 12px; }
        .fvd-carnet-page { display: grid; gap: 15px; grid-template-columns: 1fr; }
        .fvd-carnet-card {
            background: linear-gradient(135deg, #3a3eb5 0%, #2e3092 100%);
            border: 1px solid rgba(255,255,255,.2); border-radius: 15px; overflow: hidden;
        }
        .fvd-carnet-card__inner {
            display: flex; gap: 12px; align-items: stretch; padding: 12px;
        }
        .fvd-carnet-card__photo {
            flex-shrink: 0; width: 108px; height: 108px; border-radius: 12px; overflow: hidden;
            background: #0f172a; border: 2px solid var(--fvd-ama);
        }
        .fvd-carnet-shell .fvd-carnet-card__photo { width: 132px; height: 132px; }
        .fvd-carnet-card__photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .fvd-carnet-card__ph {
            display: flex; align-items: center; justify-content: center; width: 100%; height: 100%;
            font-size: 18px; font-weight: 800; color: var(--fvd-ama);
        }
        .fvd-carnet-card__data { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 5px; font-size: 17px; line-height: 1.2; }
        .fvd-carnet-card__name { font-size: 18px; color: var(--fvd-ama); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .fvd-carnet-card__line { color: #e2e8f0; }
        .fvd-carnet-card__asoc { color: #cbd5e1; font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .fvd-carnet-markers { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 3px; }
        .fvd-carnet-marker { font-size: 14px; padding: 3px 8px; border-radius: 6px; background: rgba(0,0,0,.25); font-weight: 700; }
        .fvd-carnet-marker--pend { color: #fde68a; }
        .fvd-carnet-marker--ok { color: #86efac; }
        .fvd-carnet-marker--tr { color: #93c5fd; }
        .fvd-carnet-preview-caption { font-size: 16px; color: #94a3b8; margin: 0 0 10px 0; font-weight: 600; }
        #fvd-traspaso-msg { font-size: 18px; color: #94a3b8; flex: 1 1 100%; }
        .fvd-traspaso-empty { padding: 1rem; background: #1e293b; border-radius: 10px; color: #94a3b8; font-size: 17px; }
        @media print {
            body { background: #fff; color: #000; }
            .fvd-carnet-no-print { display: none !important; }
            .fvd-carnet-shell { max-width: none !important; padding: 0; }
            .fvd-carnet-page { display: block; }
            .fvd-carnet-card {
                width: 85mm; height: 55mm; margin: 0 auto 5mm;
                page-break-inside: avoid; break-inside: avoid;
                border: 1px solid #333; background: #fff; border-radius: 4px;
            }
            .fvd-carnet-card__inner { height: 100%; padding: 3mm; box-sizing: border-box; }
            .fvd-carnet-card__name { color: #1e3a8a; }
            .fvd-carnet-card__line { color: #111; }
            .fvd-carnet-card__asoc { color: #333; }
            .fvd-carnet-card__photo { border-color: #1e3a8a; width: 22mm; height: 22mm; }
            .fvd-carnet-card__photo img { width: 22mm; height: 22mm; }
            .fvd-carnet-card__data { font-size: 9pt; }
            .fvd-carnet-card__name { font-size: 10pt; }
        }
    </style>
</head>
<body>
<div class="fvd-carnet-shell">
    <div class="fvd-carnet-toolbar fvd-carnet-no-print">
        <p class="fvd-carnet-indicador">
            <strong>Traspaso de asociación</strong>: al confirmar se actualiza el código <code style="font-size:15px">atletas.asociacion</code>
            a la asociación elegida, se marca <code style="font-size:15px">atletas.traspaso = 1</code> y queda registro en <code style="font-size:15px">log_traspasos</code>.
        </p>
        <?php if ($traspasoError !== ''): ?>
            <p class="fvd-traspaso-error" role="alert"><?= htmlspecialchars($traspasoError, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <a class="fvd-carnet-btn--ghost" href="<?= htmlspecialchars($atletasListUrl, ENT_QUOTES, 'UTF-8') ?>">← Volver al listado</a>
        <button type="button" class="fvd-carnet-btn--ghost" onclick="window.print()">Solicitar (vista impresión)</button>
        <span id="fvd-traspaso-msg" role="status"></span>
    </div>

    <p class="fvd-traspaso-current fvd-carnet-no-print">Asociación actual: <strong><?= htmlspecialchars($currentAsocNombre, ENT_QUOTES, 'UTF-8') ?></strong></p>

    <?php if ($hayDestinos && $atletaIdTraspaso > 0): ?>
    <div class="fvd-carnet-foto-box fvd-carnet-no-print">
        <label for="fvd-carnet-foto-input">Cambiar foto del carnet</label>
        <form id="fvd-carnet-foto-form" enctype="multipart/form-data">
            <input type="hidden" name="atleta_id" value="<?= (int) $atletaIdTraspaso ?>">
            <input type="file" id="fvd-carnet-foto-input" name="foto" accept="image/jpeg,image/png,image/webp,image/gif">
            <div id="fvd_preview_traspaso_foto" style="margin-top:12px;min-height:88px;padding:10px;border:1px dashed #64748b;border-radius:10px;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,.5);">
                <?php if ($traspasoFotoPreviewUrl !== ''): ?>
                    <img src="<?= htmlspecialchars($traspasoFotoPreviewUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" width="132" height="132" style="max-width:132px;max-height:132px;width:auto;height:auto;object-fit:cover;border-radius:8px;display:block">
                <?php else: ?>
                    <span style="color:#94a3b8;font-size:15px;">Vista previa (foto del carnet)</span>
                <?php endif; ?>
            </div>
            <div>
                <button type="submit" class="fvd-carnet-btn--ghost">Subir nueva foto</button>
            </div>
        </form>
    </div>
    <script src="<?= htmlspecialchars(url('assets/js/file-preview.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script>
    (function () {
        if (typeof window.filePreview === 'undefined') return;
        window.filePreview.init('fvd-carnet-foto-input', 'fvd_preview_traspaso_foto', 'image', { previewSize: 132 });
    })();
    </script>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-traspaso-form">
        <input type="hidden" name="_action" value="traspaso_confirm">
        <input type="hidden" name="atleta_id" value="<?= (int) $atletaIdTraspaso ?>">

        <div class="fvd-traspaso-select-wrap fvd-carnet-no-print">
            <label for="fvd-traspaso-dest">Nueva asociación (vista previa del carnet abajo)</label>
            <?php if ($hayDestinos): ?>
                <select id="fvd-traspaso-dest" name="asociacion_destino_id" required>
                    <?php foreach ($asociacionesDestino as $a): ?>
                        <?php $oid = (int) ($a['id'] ?? 0); ?>
                        <option value="<?= $oid ?>"><?= htmlspecialchars((string) ($a['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <p class="fvd-traspaso-empty">No hay otra asociación disponible para traspaso (o el atleta no tiene asociación distinta a elegir).</p>
            <?php endif; ?>
        </div>

        <p class="fvd-carnet-preview-caption fvd-carnet-no-print">Vista previa del carnet con la nueva asociación</p>
        <div id="fvd-traspaso-preview" class="fvd-carnet-page">
            <?php require FVD_PROJECT_ROOT . '/templates/components/carnet_layout.php'; ?>
        </div>

        <?php if ($hayDestinos): ?>
        <div class="fvd-carnet-toolbar fvd-carnet-no-print" style="margin-top:18px">
            <button type="submit" class="fvd-carnet-btn--primary">Confirmar solicitud de traspaso</button>
        </div>
        <?php endif; ?>
    </form>
</div>
<?php if ($hayDestinos): ?>
<script>
(function () {
    var map = <?= json_encode($asocMap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var sel = document.getElementById('fvd-traspaso-dest');
    var root = document.getElementById('fvd-traspaso-preview');
    var asocEl = root ? root.querySelector('.fvd-carnet-card__asoc') : null;
    var msg = document.getElementById('fvd-traspaso-msg');

    function syncAsoc() {
        if (!sel || !asocEl) {
            return;
        }
        var id = sel.value;
        var name = map[id];
        if (name) {
            asocEl.textContent = name;
        }
    }
    if (sel) {
        sel.addEventListener('change', syncAsoc);
        syncAsoc();
    }

    var fotoForm = document.getElementById('fvd-carnet-foto-form');
    var fotoApi = <?= json_encode($carnetFotoApiUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    if (fotoForm && fotoApi) {
        fotoForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(fotoForm);
            var finp = document.getElementById('fvd-carnet-foto-input');
            if (!finp || !finp.files || !finp.files.length) {
                if (msg) { msg.textContent = 'Elija un archivo de imagen.'; }
                return;
            }
            if (msg) { msg.textContent = 'Subiendo foto…'; }
            fetch(fotoApi, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d && d.ok && d.foto_url) {
                        var img = document.getElementById('fvd-carnet-live-photo');
                        if (img) {
                            img.src = d.foto_url + (d.foto_url.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();
                        } else {
                            var ph = document.getElementById('fvd-carnet-live-photo-ph');
                            if (ph && ph.parentNode) {
                                var n = document.createElement('img');
                                n.id = 'fvd-carnet-live-photo';
                                n.src = d.foto_url;
                                n.alt = '';
                                n.width = 132;
                                n.height = 132;
                                n.style.width = '100%';
                                n.style.height = '100%';
                                n.style.objectFit = 'cover';
                                ph.parentNode.replaceChild(n, ph);
                            }
                        }
                        if (msg) { msg.textContent = 'Foto actualizada.'; }
                        fotoForm.reset();
                    } else {
                        if (msg) { msg.textContent = (d && d.error) ? d.error : 'Error al subir.'; }
                    }
                })
                .catch(function () {
                    if (msg) { msg.textContent = 'Error de red.'; }
                });
        });
    }
})();
</script>
<?php endif; ?>
</body>
</html>
