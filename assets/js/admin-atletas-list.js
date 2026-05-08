(function () {
    var root = document.getElementById('fvd-atletas-root');
    if (!root) {
        return;
    }

    var fvdDelegadoLine = root.getAttribute('data-fvd-es-delegado') === '1';
    var fvdAtletasSpaParent = root.getAttribute('data-fvd-spa-fragment') === '1';
    var apiUrl = root.getAttribute('data-atletas-search-api') || '';
    var exportUrl = root.getAttribute('data-atletas-export-url') || '';
    var fvdDelegadoSolUnaUrl = root.getAttribute('data-fvd-delegado-sol-una-api') || '';

    var filtFormEarly = document.getElementById('fvd-atletas-filter-form');
    var fvdListaSinBusquedaEnVivo = filtFormEarly && filtFormEarly.classList.contains('fvd-atletas-filter-form--fvd-wrap');
    var cedulaEl = document.getElementById('fvd-atleta-cedula');
    var qEl = document.getElementById('fvd-atleta-q');

    function cedulaVal() {
        return cedulaEl && cedulaEl.value ? String(cedulaEl.value).trim() : '';
    }

    function qVal() {
        return qEl && qEl.value ? String(qEl.value).trim() : '';
    }

    function alcanceFromAsocSelect() {
        var alcEl = document.getElementById('fvd-atletas-alcance');
        if (alcEl) {
            return alcEl.value;
        }
        var asEl = document.getElementById('fvd-atletas-asoc-id');
        if (asEl && String(asEl.value || '0') !== '0') {
            return 'asociacion';
        }
        return 'todos';
    }

    var table = document.getElementById('fvd-tabla-atletas');
    var tbody = table ? table.querySelector('tbody') : null;
    var pager = document.getElementById('fvd-atletas-pager');
    var hint = document.getElementById('fvd-atletas-live-hint');
    var fetchPage = function () {};

    function applyAtletasTableView(mode) {
        if (!table || fvdDelegadoLine) {
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

    if (!fvdDelegadoLine) {
        document.querySelectorAll('[data-fvd-atletas-view]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                applyAtletasTableView(btn.getAttribute('data-fvd-atletas-view'));
            });
        });
    }

    function buildExportUrl(format) {
        if (!exportUrl) {
            return '';
        }
        var sep = exportUrl.indexOf('?') >= 0 ? '&' : '?';
        var tipoEl = document.getElementById('fvd-atletas-tipo');
        var asEl = document.getElementById('fvd-atletas-asoc-id');
        var alc = alcanceFromAsocSelect();
        var hidAlc = document.querySelector('#fvd-atletas-filter-form input[name="alcance"][type="hidden"]');
        var hidAid = document.querySelector('#fvd-atletas-filter-form input[name="asociacion_id"][type="hidden"]');
        if (hidAlc && hidAlc.value) {
            alc = String(hidAlc.value);
        }
        var tipo = tipoEl ? tipoEl.value : 'ultimos';
        var aid = asEl ? String(asEl.value || '0') : '0';
        if ((!asEl || aid === '0') && hidAid && hidAid.value) {
            aid = String(hidAid.value || '0');
        }
        var marEl = document.getElementById('fvd-atletas-marcador-field');
        var mar = marEl && marEl.value ? String(marEl.value).trim() : '';
        var marQs = mar !== '' ? '&marcador=' + encodeURIComponent(mar) : '';
        return exportUrl + sep + 'format=' + encodeURIComponent(format)
            + '&cedula=' + encodeURIComponent(cedulaVal())
            + '&q=' + encodeURIComponent(qVal())
            + '&alcance=' + encodeURIComponent(alc)
            + '&tipo=' + encodeURIComponent(tipo)
            + '&asociacion_id=' + encodeURIComponent(aid)
            + marQs;
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
            p.set('cedula', cedulaVal());
            p.set('q', qVal());
            var alcEl = document.getElementById('fvd-atletas-alcance');
            var tipoEl = document.getElementById('fvd-atletas-tipo');
            var asEl = document.getElementById('fvd-atletas-asoc-id');
            var hidAlc2 = document.querySelector('#fvd-atletas-filter-form input[name="alcance"][type="hidden"]');
            var hidAid2 = document.querySelector('#fvd-atletas-filter-form input[name="asociacion_id"][type="hidden"]');
            var alcVal = alcEl ? alcEl.value : alcanceFromAsocSelect();
            if (hidAlc2 && hidAlc2.value) {
                alcVal = String(hidAlc2.value);
            }
            p.set('alcance', alcVal);
            if (tipoEl) {
                p.set('tipo', tipoEl.value);
            }
            if (asEl) {
                p.set('asociacion_id', asEl.value || '0');
            } else if (hidAid2 && hidAid2.value) {
                p.set('asociacion_id', String(hidAid2.value || '0'));
            }
            var marEl2 = document.getElementById('fvd-atletas-marcador-field');
            if (marEl2 && marEl2.value) {
                p.set('marcador', String(marEl2.value).trim());
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
                    if (!fvdDelegadoLine) {
                        applyAtletasTableView(table.getAttribute('data-atletas-view') || 'tech');
                    }
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

        if (!fvdListaSinBusquedaEnVivo) {
            if (cedulaEl) {
                cedulaEl.addEventListener('input', scheduleFetch);
            }
            if (qEl) {
                qEl.addEventListener('input', scheduleFetch);
            }
        }

        var alcEl = document.getElementById('fvd-atletas-alcance');
        var tipoEl = document.getElementById('fvd-atletas-tipo');
        var asEl = document.getElementById('fvd-atletas-asoc-id');
        var filtForm = document.getElementById('fvd-atletas-filter-form');
        var asocWrap = document.getElementById('fvd-atletas-asoc-wrap');

        function submitFilterForm() {
            if (!filtForm) {
                return;
            }
            if (typeof filtForm.requestSubmit === 'function') {
                filtForm.requestSubmit();
            } else {
                filtForm.submit();
            }
        }

        function syncAsocUi() {
            if (!alcEl || !asocWrap || !asEl) {
                return;
            }
            var on = alcEl.value === 'asociacion';
            asocWrap.style.display = on ? 'block' : 'none';
            asEl.required = on;
            if (!on) {
                asEl.setCustomValidity('');
            }
        }

        if (alcEl && asocWrap && asEl) {
            syncAsocUi();
            alcEl.addEventListener('change', syncAsocUi);
        }
        if (tipoEl) {
            tipoEl.addEventListener('change', submitFilterForm);
        }
        if (asEl) {
            asEl.addEventListener('change', submitFilterForm);
        }

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
            if (isNaN(np) || np < 1) {
                return;
            }
            if (fvdListaSinBusquedaEnVivo && filtFormEarly) {
                if (fvdAtletasSpaParent) {
                    fetchPage(np);
                    return;
                }
                try {
                    var fd = new FormData(filtFormEarly);
                    fd.set('page', String(np));
                    var u = new URL(window.location.href);
                    u.search = '?' + new URLSearchParams(fd).toString();
                    window.location.assign(u.toString());
                } catch (eNav) {
                    fetchPage(np);
                }
                return;
            }
            fetchPage(np);
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

    try {
        if (fvdDelegadoLine) {
            localStorage.removeItem('fvdDelegadoSolQueueV1');
        }
    } catch (eLs) { /* vacío */ }

    if (fvdDelegadoLine && fvdDelegadoSolUnaUrl && root && typeof fetch === 'function') {
        root.addEventListener('click', function (ev) {
            var t = ev.target;
            if (!t || !t.closest) {
                return;
            }
            var b = t.closest('.fvd-delegado-sol-direct');
            if (!b || !root.contains(b)) {
                return;
            }
            ev.preventDefault();
            if (b.getAttribute('data-fvd-sol-loading') === '1') {
                return;
            }
            var tipo = b.getAttribute('data-fvd-sol-tipo') || '';
            var aid = parseInt(b.getAttribute('data-fvd-atleta-id') || '0', 10);
            if (!tipo || aid <= 0) {
                return;
            }
            var body = { tipo: tipo, atleta_id: aid };
            if (tipo === 'traspaso') {
                var wrap = b.closest('.fvd-delegado-traspaso-queue-wrap');
                var sel = wrap ? wrap.querySelector('.fvd-delegado-traspaso-dest') : null;
                if (!sel) {
                    return;
                }
                var destId = parseInt(sel.value || '0', 10);
                if (destId <= 0) {
                    return;
                }
                body.asociacion_destino_id = destId;
            }
            b.setAttribute('data-fvd-sol-loading', '1');
            fetch(fvdDelegadoSolUnaUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(body)
            }).then(function (r) {
                return r.json().then(function (j) {
                    return { r: r, j: j };
                });
            }).then(function (pack) {
                b.removeAttribute('data-fvd-sol-loading');
                var data = pack.j;
                if (!data || !data.ok) {
                    if (hint) {
                        hint.textContent = (data && data.error) ? data.error : 'No se pudo registrar la solicitud.';
                    }
                    return;
                }
                if (hint) {
                    hint.textContent = '';
                }
                var tr = b.closest('tr');
                if (tr && tr.parentNode) {
                    tr.parentNode.removeChild(tr);
                }
            }).catch(function () {
                b.removeAttribute('data-fvd-sol-loading');
                if (hint) {
                    hint.textContent = 'Error de red.';
                }
            });
        });
    }
})();
