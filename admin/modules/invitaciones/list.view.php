<?php
declare(strict_types=1);
/** @var array{total:int,page:int,per_page:int,pages:int,rows:list} $result */
/** @var string $selfUrl */
/** @var string $invitacionesSearchApiUrl */
/** @var string $estadoFiltro */
/** @var string $fvd_error */

$fvd_error = $fvd_error ?? '';
$estadoFiltro = $estadoFiltro ?? '';
$okMsg = isset($_GET['ok']) && $_GET['ok'] === '1';
$rowTpl = FVD_PROJECT_ROOT . '/templates/components/invitacion_row.php';
$formTpl = FVD_PROJECT_ROOT . '/templates/invitaciones/form.php';
?>

<h1 class="fvd-inv-h1">Invitaciones</h1>
<p class="fvd-inv-intro no-print">Enlaces con token seguro (sin id en la URL). Listado compacto para pantalla 13″.</p>

<?php if ($okMsg): ?>
    <p class="fvd-mod-msg fvd-inv-ok no-print" role="status">Invitación creada. Use «Copiar enlace» para compartirla.</p>
<?php endif; ?>

<style>
    .fvd-inv-compact { margin-bottom: 0.75rem; padding: 0.65rem 0.75rem; border-radius: 8px; border: 1px solid var(--fvd-border, rgba(255,255,255,.14)); background: rgba(0,0,0,.12); }
    .fvd-inv-form__title { font-size: var(--fvd-font-h3, 0.95rem); margin: 0 0 0.5rem; }
    .fvd-inv-form__grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px 10px; align-items: end; }
    .fvd-inv-form__span2 { grid-column: span 2; }
    .fvd-inv-form__actions { grid-column: 1 / -1; }
    .fvd-inv-label { font-size: 0.65rem; color: var(--fvd-muted); display: block; margin-bottom: 2px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
    .fvd-inv-input { width: 100%; max-width: none; padding: 5px 8px; font-size: 0.8125rem; box-sizing: border-box; }
    .fvd-inv-btn { padding: 6px 12px; font-size: 0.8125rem; }
    @media (max-width: 1100px) {
        .fvd-inv-form__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .fvd-inv-form__span2 { grid-column: span 2; }
    }
    @media (max-width: 640px) {
        .fvd-inv-form__grid { grid-template-columns: 1fr; }
        .fvd-inv-form__span2 { grid-column: span 1; }
    }
    .fvd-inv-h1 { font-size: var(--fvd-font-h1, 1.15rem); margin-bottom: 0.25rem; }
    .fvd-inv-intro { font-size: 0.75rem; color: var(--fvd-muted); margin: 0 0 0.5rem; }
    .fvd-inv-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-end; margin-bottom: 0.5rem; }
    .fvd-inv-toolbar label { font-size: 0.65rem; color: var(--fvd-muted); display: block; margin-bottom: 2px; font-weight: 600; }
    .fvd-inv-table-wrap { overflow-x: auto; }
    .fvd-inv-table { font-size: 0.75rem; }
    .fvd-inv-table th, .fvd-inv-table td { padding: 5px 6px; vertical-align: middle; }
    .fvd-inv-nowrap { white-space: nowrap; }
    .fvd-inv-clip { max-width: 7rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .fvd-inv-linkcell { white-space: nowrap; }
    .fvd-inv-copy {
        display: inline-flex; align-items: center; justify-content: center;
        padding: 3px 6px; margin-right: 4px; border-radius: 5px;
        border: 1px solid var(--fvd-border, rgba(255,255,255,.2)); background: rgba(0,0,0,.2);
        color: var(--fvd-amarillo, #fff200); cursor: pointer; vertical-align: middle;
    }
    .fvd-inv-copy:hover { background: rgba(255,242,0,.12); }
    .fvd-inv-url-hint { font-size: 0.65rem; color: var(--fvd-muted); }
    .fvd-inv-badge { display: inline-block; font-size: 0.62rem; font-weight: 700; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.03em; }
    .fvd-inv-badge--activa { background: rgba(34, 197, 94, 0.25); color: #86efac; }
    .fvd-inv-badge--usada { background: rgba(148, 163, 184, 0.25); color: #cbd5e1; }
    .fvd-inv-badge--expirada { background: rgba(239, 68, 68, 0.22); color: #fecaca; }
    .fvd-inv-ok { color: #86efac; }
    #fvd-inv-hint { font-size: 0.75rem; color: var(--fvd-muted); min-height: 1rem; margin-top: 4px; }
</style>

<?php require $formTpl; ?>

<div class="fvd-inv-toolbar no-print">
    <div>
        <label for="fvd-inv-filtro-estado">Estado</label>
        <select id="fvd-inv-filtro-estado" class="fvd-input" style="padding:5px 8px;font-size:0.8125rem;min-width:9rem">
            <option value=""<?= $estadoFiltro === '' ? ' selected' : '' ?>>Todos</option>
            <option value="pendiente"<?= $estadoFiltro === 'pendiente' ? ' selected' : '' ?>>Pendiente (activa)</option>
            <option value="aceptada"<?= $estadoFiltro === 'aceptada' ? ' selected' : '' ?>>Usada</option>
            <option value="expirada"<?= $estadoFiltro === 'expirada' ? ' selected' : '' ?>>Expirada</option>
        </select>
    </div>
</div>

<div class="fvd-mod-table-wrap fvd-inv-table-wrap">
    <table id="fvd-inv-tabla" class="fvd-mod-table fvd-mod-table--nowrap fvd-inv-table">
        <thead>
        <tr>
            <th>Tipo</th>
            <th>Documento</th>
            <th>Estado</th>
            <th>Vence</th>
            <th>Correo</th>
            <th>Nota</th>
            <th>Enlace</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($result['rows'] as $r): ?>
            <?php require $rowTpl; ?>
        <?php endforeach; ?>
        <?php if ($result['rows'] === []): ?>
            <tr><td colspan="7" style="padding:10px">Sin invitaciones. Cree una arriba.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<p id="fvd-inv-hint" class="no-print" aria-live="polite"></p>

<?php
$p = (int) $result['page'];
$pages = (int) $result['pages'];
$estArg = $estadoFiltro !== '' ? '&estado=' . rawurlencode($estadoFiltro) : '';
?>
<nav id="fvd-inv-pager" class="fvd-mod-pager no-print">
    <span><?= (int) $result['total'] ?> reg. · pág. <?= $p ?>/<?= $pages ?></span>
    <?php if ($p > 1): ?><a href="<?= htmlspecialchars($selfUrl . '?page=' . ($p - 1) . $estArg, ENT_QUOTES, 'UTF-8') ?>">Anterior</a><?php endif; ?>
    <?php if ($p < $pages): ?><a href="<?= htmlspecialchars($selfUrl . '?page=' . ($p + 1) . $estArg, ENT_QUOTES, 'UTF-8') ?>">Siguiente</a><?php endif; ?>
</nav>

<script>
(function () {
    var api = <?= json_encode($invitacionesSearchApiUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var sel = document.getElementById('fvd-inv-filtro-estado');
    var table = document.getElementById('fvd-inv-tabla');
    var tbody = table ? table.querySelector('tbody') : null;
    var pager = document.getElementById('fvd-inv-pager');
    var hint = document.getElementById('fvd-inv-hint');
    if (!api || !tbody || !pager) { return; }

    function fetchPage(page) {
        if (hint) { hint.textContent = 'Cargando…'; }
        var u = api + (api.indexOf('?') >= 0 ? '&' : '?') + 'page=' + encodeURIComponent(String(page))
            + '&estado=' + encodeURIComponent(sel ? sel.value : '');
        fetch(u, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (hint) { hint.textContent = ''; }
                if (!d || !d.ok) { return; }
                tbody.innerHTML = d.tbody_html;
                pager.innerHTML = d.pager_html;
            })
            .catch(function () {
                if (hint) { hint.textContent = 'Error de red.'; }
            });
    }

    if (sel) {
        sel.addEventListener('change', function () { fetchPage(1); });
    }

    pager.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) { return; }
        var a = t.closest('a[data-fvd-page]');
        if (!a) { return; }
        e.preventDefault();
        var np = parseInt(a.getAttribute('data-fvd-page'), 10);
        if (!isNaN(np) && np >= 1) { fetchPage(np); }
    });

    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('.fvd-inv-copy') : null;
        if (!btn || !tbody || !tbody.contains(btn)) { return; }
        var cell = btn.closest('td');
        var inp = cell ? cell.querySelector('.fvd-inv-url') : null;
        var text = inp ? inp.value : '';
        if (!text) { return; }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                if (hint) { hint.textContent = 'Enlace copiado.'; }
            }).catch(function () {
                if (hint) { hint.textContent = 'No se pudo copiar.'; }
            });
        } else {
            inp.type = 'text';
            inp.select();
            try {
                document.execCommand('copy');
                if (hint) { hint.textContent = 'Enlace copiado.'; }
            } catch (err) {
                if (hint) { hint.textContent = 'Copie manualmente desde el código fuente.'; }
            }
            inp.type = 'hidden';
        }
    });
})();
</script>
