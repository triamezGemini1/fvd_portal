<?php
/** @var array{total:int,page:int,per_page:int,pages:int,rows:list} $result */
/** @var string $selfUrl */
/** @var string $cedula */
/** @var string $q */
/** @var string $fichaFiltro */
/** @var string $fvd_atletas_tab */
/** @var bool $fvd_puede_traspaso */
/** @var string $atletasSearchApiUrl */
/** @var string $atletasExportUrl */
/** @var string $atletasReportBaseUrl */
$atletasFormNuevoUrl = $selfUrl . '?action=form';
$fvd_tab_class = ($fvd_atletas_tab ?? 'list') === 'ficha' ? 'fvd-atletas-tab-ficha' : 'fvd-atletas-tab-list';
$carnetsUrlBase = $selfUrl . '?action=carnets';
?>

<h1 class="fvd-atletas-title">Atletas</h1>
<?php
$fvdRevFilt = !empty($fvd_revision_delegado_filtro);
$fvdEsSuper = \AuthService::isSuperAdmin();
?>
<?php if ($fvdRevFilt && $fvdEsSuper): ?>
<p class="fvd-mod-msg no-print" role="status" style="margin:0 0 0.75rem;padding:8px 12px;border-radius:8px;background:rgba(255,242,0,0.12);border:1px solid var(--fvd-amarillo);font-size:0.8125rem">
    Mostrando solo <strong>altas ingresadas por delegados</strong> pendientes de validación FVD (estatus pendiente). Quite el filtro para ver el listado completo.
</p>
<?php endif; ?>
<p class="fvd-atletas-intro no-print hide-on-13 fvd-only-list-tab" style="font-size:0.8125rem;color:var(--fvd-muted);margin:0 0 0.75rem">Busque por <strong>cédula</strong> (coincidencia por inicio) o refine por nombre. La tabla se actualiza al escribir (espera breve). Pantalla optimizada para 13".</p>
<?php if (!empty($fvd_error ?? '')): ?><p class="fvd-mod-msg"><?= htmlspecialchars((string) $fvd_error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

<div id="fvd-atletas-root" class="<?= htmlspecialchars($fvd_tab_class, ENT_QUOTES, 'UTF-8') ?>">

<div class="fvd-atletas-main-tabs no-print" role="tablist" aria-label="Sección atletas">
    <button type="button" class="fvd-atletas-main-tab<?= ($fvd_atletas_tab ?? 'list') === 'list' ? ' fvd-atletas-main-tab--on' : '' ?>" data-fvd-atletas-main-tab="list" role="tab" id="fvd-tab-list">Listado</button>
    <button type="button" class="fvd-atletas-main-tab<?= ($fvd_atletas_tab ?? 'list') === 'ficha' ? ' fvd-atletas-main-tab--on' : '' ?>" data-fvd-atletas-main-tab="ficha" role="tab" id="fvd-tab-ficha">Gestión de Fichas</button>
</div>

<div class="report-container">
<div class="fvd-mod-toolbar no-print" style="flex-wrap:wrap;align-items:flex-end;gap:10px">
    <form method="get" action="" class="no-print" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
        <input type="hidden" name="action" value="list">
        <input type="hidden" name="tab" id="fvd-form-tab" value="<?= htmlspecialchars($fvd_atletas_tab ?? 'list', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="ficha" id="fvd-form-ficha" value="<?= htmlspecialchars($fichaFiltro ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($fvdRevFilt): ?><input type="hidden" name="revision_delegado" id="fvd-form-revision-delegado" value="1"><?php endif; ?>
        <div>
            <label style="font-size:.8125rem;color:var(--fvd-muted);display:block;font-weight:600">Cédula</label>
            <input id="fvd-atleta-cedula" class="fvd-input" type="search" name="cedula" value="<?= htmlspecialchars($cedula, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej. 30399011" style="max-width:11rem" autocomplete="off">
        </div>
        <div>
            <label style="font-size:.8125rem;color:var(--fvd-muted);display:block">Nombre</label>
            <input id="fvd-atleta-q" class="fvd-input" type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Contiene…" style="max-width:12rem">
        </div>
        <button type="submit" class="fvd-input" style="width:auto;padding:6px 12px">Buscar</button>
        <a href="<?= htmlspecialchars($selfUrl . '?action=list', ENT_QUOTES, 'UTF-8') ?>" class="fvd-input" style="width:auto;padding:6px 12px;display:inline-flex;align-items:center;text-decoration:none;box-sizing:border-box">Limpiar</a>
        <?php if ($fvdEsSuper): ?>
            <?php if (!$fvdRevFilt): ?>
                <a href="<?= htmlspecialchars($selfUrl . '?action=list&revision_delegado=1', ENT_QUOTES, 'UTF-8') ?>" class="fvd-input" style="width:auto;padding:6px 12px;display:inline-flex;align-items:center;text-decoration:none;box-sizing:border-box;font-weight:600;border-color:var(--fvd-amarillo);color:var(--fvd-amarillo)">Altas delegado (pend. FVD)</a>
            <?php else: ?>
                <a href="<?= htmlspecialchars($selfUrl . '?action=list', ENT_QUOTES, 'UTF-8') ?>" class="fvd-input" style="width:auto;padding:6px 12px;display:inline-flex;align-items:center;text-decoration:none;box-sizing:border-box">Quitar filtro delegado</a>
            <?php endif; ?>
        <?php endif; ?>
    </form>
    <div class="fvd-atletas-ficha-toolbar fvd-only-ficha-tab no-print" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
        <div>
            <label for="fvd-ficha-filtro" style="font-size:.75rem;color:var(--fvd-muted);display:block;font-weight:600">Filtro ficha / carnet</label>
            <select id="fvd-ficha-filtro" class="fvd-input" style="min-width:12rem">
                <option value=""<?= ($fichaFiltro ?? '') === '' ? ' selected' : '' ?>>Todos</option>
                <option value="sin_carnet"<?= ($fichaFiltro ?? '') === 'sin_carnet' ? ' selected' : '' ?>>Pendiente solicitud carnet (carnet=0)</option>
                <option value="carnet_solicitado"<?= in_array(($fichaFiltro ?? ''), ['carnet_solicitado', 'carnet_emitido'], true) ? ' selected' : '' ?>>Carnet solicitado (carnet=1)</option>
                <option value="ficha_vencida"<?= ($fichaFiltro ?? '') === 'ficha_vencida' ? ' selected' : '' ?>>Ficha vencida (+365 días sin actualizar)</option>
            </select>
        </div>
        <button type="button" class="fvd-input" id="fvd-carnets-lote" style="width:auto;padding:6px 12px" title="Abre vista de impresión con los seleccionados">Imprimir carnets (selección)</button>
        <div class="fvd-atletas-report-links no-print" style="flex-basis:100%;display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:4px">
            <?php
            if (!function_exists('admin_module_url')) {
                require_once FVD_PROJECT_ROOT . '/config/paths.php';
            }
            $repBase = isset($atletasReportBaseUrl) && is_string($atletasReportBaseUrl) && $atletasReportBaseUrl !== ''
                ? $atletasReportBaseUrl
                : admin_module_url('atletas/');
            $uRepPen = $repBase . 'reporte_carnets.php?tipo=pendientes';
            $uRepEmi = $repBase . 'reporte_carnets.php?tipo=solicitados';
            $uRepTr = $repBase . 'reporte_traspasos.php';
            ?>
            <span style="font-size:.7rem;color:var(--fvd-muted);font-weight:600">Informes:</span>
            <a class="fvd-input" style="width:auto;padding:4px 10px;font-size:.75rem;text-decoration:none;display:inline-flex;align-items:center;box-sizing:border-box" href="<?= htmlspecialchars($uRepPen, ENT_QUOTES, 'UTF-8') ?>">Elaboración carnets</a>
            <a class="fvd-input" style="width:auto;padding:4px 10px;font-size:.75rem;text-decoration:none;display:inline-flex;align-items:center;box-sizing:border-box" href="<?= htmlspecialchars($uRepEmi, ENT_QUOTES, 'UTF-8') ?>">Carnets solicitados</a>
            <a class="fvd-input" style="width:auto;padding:4px 10px;font-size:.75rem;text-decoration:none;display:inline-flex;align-items:center;box-sizing:border-box" href="<?= htmlspecialchars($uRepTr, ENT_QUOTES, 'UTF-8') ?>">Traspasos</a>
        </div>
    </div>
    <div class="fvd-atletas-export no-print" role="group" aria-label="Exportar listado">
        <span class="fvd-atletas-export__label" style="font-size:.75rem;color:var(--fvd-muted);display:block;margin-bottom:4px;font-weight:600">Exportar</span>
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
            <button type="button" class="fvd-input" id="fvd-export-csv" style="width:auto;padding:6px 12px">Excel (CSV)</button>
            <button type="button" class="fvd-input" id="fvd-export-pdf" style="width:auto;padding:6px 12px">PDF</button>
        </div>
    </div>
    <a href="<?= htmlspecialchars($atletasFormNuevoUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-btn-primary no-print" style="text-decoration:none;box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center">Nuevo atleta</a>
</div>

<div class="fvd-atletas-view-seg no-print fvd-only-list-tab" role="tablist" aria-label="Vista de columnas">
    <button type="button" class="fvd-atletas-seg__btn fvd-atletas-seg__btn--on" data-fvd-atletas-view="basic" role="tab">Vista básica (contacto)</button>
    <button type="button" class="fvd-atletas-seg__btn" data-fvd-atletas-view="tech" role="tab">Vista técnica (FVD)</button>
</div>

<p id="fvd-atletas-live-hint" class="fvd-atletas-live-hint no-print" aria-live="polite"></p>

<div class="fvd-mod-table-wrap">
    <table id="fvd-tabla-atletas" class="fvd-mod-table fvd-mod-table--nowrap tabla-atletas fvd-atletas-view--basic" data-atletas-view="basic">
        <thead>
        <tr>
            <th class="fvd-col-check fvd-only-ficha-tab"><span class="fvd-sr-only">Selección</span></th>
            <th class="fvd-col-id">ID</th>
            <th class="fvd-col-foto">Foto</th>
            <th class="fvd-col-ced">Cédula</th>
            <th class="fvd-col-nom">Nombre</th>
            <th class="fvd-col-contact fvd-col-cel">Celular</th>
            <th class="fvd-col-contact fvd-col-email">Email</th>
            <th class="fvd-col-tech fvd-col-sexo">Sexo</th>
            <th class="fvd-col-tech fvd-col-numfvd">Nº FVD</th>
            <th class="fvd-col-asoc">Asociación</th>
            <th class="fvd-col-tech fvd-col-categ">Categ.</th>
            <th class="fvd-col-tech fvd-col-estatus">Estatus</th>
            <th class="fvd-col-actions"></th>
        </tr>
        </thead>
        <tbody>
        <?php
        $atletaRowTpl = FVD_PROJECT_ROOT . '/templates/components/atleta_table_row.php';
        foreach ($result['rows'] as $r):
            require $atletaRowTpl;
        endforeach;
        ?>
        <?php if ($result['rows'] === []): ?>
            <tr><td colspan="13" style="padding:12px">Sin registros con los filtros actuales.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$cedArg = $cedula !== '' ? '&cedula=' . rawurlencode($cedula) : '';
$qArg = $q !== '' ? '&q=' . rawurlencode($q) : '';
$fichaArg = ($fichaFiltro ?? '') !== '' ? '&ficha=' . rawurlencode((string) $fichaFiltro) : '';
$tabArg = ($fvd_atletas_tab ?? 'list') === 'ficha' ? '&tab=ficha' : '';
$revArg = !empty($fvd_revision_delegado_filtro) ? '&revision_delegado=1' : '';
$filterArg = $cedArg . $qArg . $fichaArg . $tabArg . $revArg;
$p = (int) $result['page'];
$pages = (int) $result['pages'];
?>
<nav id="fvd-atletas-pager" class="fvd-mod-pager no-print">
    <span><?= (int) $result['total'] ?> reg. · pág. <?= $p ?>/<?= $pages ?></span>
    <?php if ($p > 1): ?><a href="<?= htmlspecialchars($selfUrl . '?page=' . ($p - 1) . $filterArg, ENT_QUOTES, 'UTF-8') ?>">Anterior</a><?php endif; ?>
    <?php if ($p < $pages): ?><a href="<?= htmlspecialchars($selfUrl . '?page=' . ($p + 1) . $filterArg, ENT_QUOTES, 'UTF-8') ?>">Siguiente</a><?php endif; ?>
</nav>
</div>

</div>

<script>
(function () {
    var apiUrl = <?= json_encode($atletasSearchApiUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var exportUrl = <?= json_encode($atletasExportUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var carnetsBase = <?= json_encode($carnetsUrlBase, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var rootEl = document.getElementById('fvd-atletas-root');
    var formTab = document.getElementById('fvd-form-tab');
    var formFicha = document.getElementById('fvd-form-ficha');
    var cedulaEl = document.getElementById('fvd-atleta-cedula');
    var qEl = document.getElementById('fvd-atleta-q');
    var fichaFiltroEl = document.getElementById('fvd-ficha-filtro');
    var table = document.getElementById('fvd-tabla-atletas');
    var tbody = table ? table.querySelector('tbody') : null;
    var pager = document.getElementById('fvd-atletas-pager');
    var hint = document.getElementById('fvd-atletas-live-hint');
    var btnLote = document.getElementById('fvd-carnets-lote');
    var revisionDelegado = <?= !empty($fvd_revision_delegado_filtro) ? 'true' : 'false' ?>;
    var fetchPage = function () {};

    function applyAtletasTableView(mode) {
        if (!table) {
            return;
        }
        table.classList.remove('fvd-atletas-view--basic', 'fvd-atletas-view--tech');
        table.classList.add(mode === 'tech' ? 'fvd-atletas-view--tech' : 'fvd-atletas-view--basic');
        table.setAttribute('data-atletas-view', mode);
        document.querySelectorAll('[data-fvd-atletas-view]').forEach(function (b) {
            var on = b.getAttribute('data-fvd-atletas-view') === mode;
            b.classList.toggle('fvd-atletas-seg__btn--on', on);
        });
    }

    document.querySelectorAll('[data-fvd-atletas-view]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            applyAtletasTableView(btn.getAttribute('data-fvd-atletas-view'));
        });
    });

    function setMainTab(mode) {
        if (!rootEl) {
            return;
        }
        var isFicha = mode === 'ficha';
        rootEl.classList.toggle('fvd-atletas-tab-ficha', isFicha);
        rootEl.classList.toggle('fvd-atletas-tab-list', !isFicha);
        document.querySelectorAll('[data-fvd-atletas-main-tab]').forEach(function (b) {
            var on = b.getAttribute('data-fvd-atletas-main-tab') === mode;
            b.classList.toggle('fvd-atletas-main-tab--on', on);
        });
        if (formTab) {
            formTab.value = mode;
        }
        if (!isFicha && fichaFiltroEl) {
            fichaFiltroEl.value = '';
            if (formFicha) {
                formFicha.value = '';
            }
        }
        if (apiUrl && tbody && pager && table && cedulaEl && qEl) {
            fetchPage(1);
        }
    }


    document.querySelectorAll('[data-fvd-atletas-main-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setMainTab(btn.getAttribute('data-fvd-atletas-main-tab') || 'list');
        });
    });

    if (btnLote && tbody) {
        btnLote.addEventListener('click', function () {
            var boxes = tbody.querySelectorAll('input.fvd-atleta-check:checked');
            var ids = [];
            boxes.forEach(function (c) {
                var v = parseInt(c.value, 10);
                if (v) {
                    ids.push(v);
                }
            });
            if (ids.length === 0) {
                window.alert('Seleccione al menos un atleta con las casillas de la primera columna.');
                return;
            }
            window.open(carnetsBase + '&ids=' + ids.join(','), '_blank', 'noopener');
        });
    }

    if (fichaFiltroEl && formFicha) {
        fichaFiltroEl.addEventListener('change', function () {
            formFicha.value = fichaFiltroEl.value;
            if (apiUrl && tbody && pager) {
                fetchPage(1);
            }
        });
    }

    if (!cedulaEl || !qEl) {
        return;
    }

    function buildExportUrl(format) {
        if (!exportUrl) {
            return '';
        }
        var sep = exportUrl.indexOf('?') >= 0 ? '&' : '?';
        var ficha = (fichaFiltroEl && rootEl && rootEl.classList.contains('fvd-atletas-tab-ficha')) ? fichaFiltroEl.value : '';
        return exportUrl + sep + 'format=' + encodeURIComponent(format)
            + '&cedula=' + encodeURIComponent(cedulaEl.value.trim())
            + '&q=' + encodeURIComponent(qEl.value.trim())
            + (ficha ? '&ficha=' + encodeURIComponent(ficha) : '')
            + (revisionDelegado ? '&revision_delegado=1' : '');
    }

    if (apiUrl && tbody && pager && table) {
        var debounceMs = 300;
        var timer = null;

        function setLoading(on) {
            if (on) {
                table.classList.add('is-loading');
                if (hint) {
                    hint.textContent = 'Cargando…';
                }
            } else {
                table.classList.remove('is-loading');
                if (hint) {
                    hint.textContent = '';
                }
            }
        }

        function buildQuery(page) {
            var p = new URLSearchParams();
            p.set('page', String(page));
            p.set('cedula', cedulaEl.value.trim());
            p.set('q', qEl.value.trim());
            var tab = rootEl && rootEl.classList.contains('fvd-atletas-tab-ficha') ? 'ficha' : 'list';
            p.set('tab', tab);
            if (tab === 'ficha' && fichaFiltroEl) {
                p.set('ficha', fichaFiltroEl.value);
            }
            if (revisionDelegado) {
                p.set('revision_delegado', '1');
            }
            return p.toString();
        }

        fetchPage = function (page) {
            setLoading(true);
            var url = apiUrl + (apiUrl.indexOf('?') >= 0 ? '&' : '?') + buildQuery(page);
            fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    setLoading(false);
                    if (!data || !data.ok) {
                        if (hint) {
                            hint.textContent = 'No se pudieron cargar los datos.';
                        }
                        return;
                    }
                    tbody.innerHTML = data.tbody_html;
                    pager.innerHTML = data.pager_html;
                    applyAtletasTableView(table.getAttribute('data-atletas-view') || 'basic');
                })
                .catch(function () {
                    setLoading(false);
                    if (hint) {
                        hint.textContent = 'Error de red. Intente de nuevo.';
                    }
                });
        };

        function scheduleFetch() {
            if (timer) {
                clearTimeout(timer);
            }
            timer = setTimeout(function () {
                timer = null;
                fetchPage(1);
            }, debounceMs);
        }

        cedulaEl.addEventListener('input', scheduleFetch);
        qEl.addEventListener('input', scheduleFetch);

        pager.addEventListener('click', function (e) {
            var t = e.target;
            if (!t || !t.closest) {
                return;
            }
            var a = t.closest('a[data-fvd-page]');
            if (!a) {
                return;
            }
            e.preventDefault();
            var np = parseInt(a.getAttribute('data-fvd-page'), 10);
            if (!isNaN(np) && np >= 1) {
                fetchPage(np);
            }
        });
    }

    var btnCsv = document.getElementById('fvd-export-csv');
    var btnPdf = document.getElementById('fvd-export-pdf');
    if (btnCsv) {
        btnCsv.addEventListener('click', function () {
            var u = buildExportUrl('csv');
            if (u) {
                window.location.href = u;
            }
        });
    }
    if (btnPdf) {
        btnPdf.addEventListener('click', function () {
            var u = buildExportUrl('pdf');
            if (u) {
                window.location.href = u;
            }
        });
    }
})();
</script>
