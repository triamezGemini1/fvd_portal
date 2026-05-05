<?php

declare(strict_types=1);

/**
 * Demo: layout 100vh + Fetch a /api/ (lógica separada del HTML).
 * Abrir desde la raíz del proyecto vía el servidor web (p. ej. /fvd_portal/app_liquid_demo.php).
 */
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$cssHref = ($base === '' ? '' : $base) . '/assets/css/fvd-app-shell.css';
$apiTabla = ($base === '' ? '' : $base) . '/api/tabla_ejemplo.php';
$apiPing = ($base === '' ? '' : $base) . '/api/ping.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FVD — Demo layout + API</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($cssHref, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body style="margin:0">
<div class="fvd-app-shell" id="fvdAppShell">
    <aside class="fvd-app-shell__sidebar" aria-label="Navegación">
        <div class="fvd-app-shell__brand">
            <span class="fvd-app-shell__mark" aria-hidden="true">F</span>
            <span>FVD Panel</span>
        </div>
        <nav class="fvd-app-shell__nav">
            <a href="#"><span aria-hidden="true">▣</span> <span>Resumen</span></a>
            <a href="#"><span aria-hidden="true">◎</span> <span>Torneos</span></a>
            <a href="#"><span aria-hidden="true">◇</span> <span>Atletas</span></a>
        </nav>
    </aside>
    <header class="fvd-app-shell__header">
        <button type="button" class="fvd-app-shell__toggle" id="fvdSidebarToggle" aria-expanded="true" aria-controls="fvdAppShell">Menú</button>
        <h1 class="fvd-app-shell__title">Contenido (scroll interno)</h1>
        <span id="fvdPingStatus" style="margin-left:auto;font-size:.75rem;color:#64748b"></span>
    </header>
    <main class="fvd-app-shell__main">
        <div class="fvd-app-shell__panel">
            <p style="margin:0 0 1rem;font-size:.875rem;color:#475569">
                Tabla cargada con <code>fetch()</code> desde <code>api/tabla_ejemplo.php</code> (JSON). La rejilla exterior permanece fija en <code>100vh</code>.
            </p>
            <div class="fvd-app-shell__table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Concepto</th>
                        <th>Monto</th>
                        <th>Estado</th>
                    </tr>
                    </thead>
                    <tbody id="fvdTablaBody">
                    <tr><td colspan="4">Cargando…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
<script>
(function () {
    var shell = document.getElementById('fvdAppShell');
    var btn = document.getElementById('fvdSidebarToggle');
    if (btn && shell) {
        btn.addEventListener('click', function () {
            var c = shell.classList.toggle('fvd-app-shell--collapsed');
            btn.setAttribute('aria-expanded', c ? 'false' : 'true');
        });
    }
    var pingEl = document.getElementById('fvdPingStatus');
    fetch(<?= json_encode($apiPing, JSON_UNESCAPED_UNICODE) ?>)
        .then(function (r) { return r.json(); })
        .then(function (j) {
            if (pingEl) pingEl.textContent = j.ok ? 'API: ' + (j.message || 'ok') : '';
        })
        .catch(function () {
            if (pingEl) pingEl.textContent = 'API no disponible';
        });

    var tbody = document.getElementById('fvdTablaBody');
    fetch(<?= json_encode($apiTabla, JSON_UNESCAPED_UNICODE) ?>)
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!tbody || !data.rows) return;
            tbody.innerHTML = '';
            data.rows.forEach(function (row) {
                var tr = document.createElement('tr');
                tr.innerHTML = '<td>' + row.id + '</td><td>' + escapeHtml(row.concepto) + '</td><td>' + Number(row.monto).toFixed(2) + '</td><td>' + escapeHtml(row.estado) + '</td>';
                tbody.appendChild(tr);
            });
        })
        .catch(function () {
            if (tbody) tbody.innerHTML = '<tr><td colspan="4">Error al cargar datos</td></tr>';
        });

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
})();
</script>
</body>
</html>
