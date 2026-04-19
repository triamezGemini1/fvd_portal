<?php
declare(strict_types=1);
?>
        </main>
    </div>
    </div>
</div>
<script>
(function () {
    var shell = document.getElementById('fvd-shell');
    var wideBtn = document.getElementById('fvd-sidebar-wide-toggle');
    /* No quitar sidebar-rail en vistas sin menú lateral: el layout depende de la clase inicial. */
    if (shell && !shell.classList.contains('fvd-shell--no-sidebar') && localStorage.getItem('fvdSidebarWide') === '1') {
        shell.classList.remove('fvd-shell--sidebar-rail');
    }
    if (shell && wideBtn) {
        wideBtn.addEventListener('click', function () {
            shell.classList.toggle('fvd-shell--sidebar-rail');
            var wide = !shell.classList.contains('fvd-shell--sidebar-rail');
            localStorage.setItem('fvdSidebarWide', wide ? '1' : '0');
        });
    }
})();
(function () {
    var shell = document.getElementById('fvd-shell');
    var btn = document.getElementById('fvd-mnav-toggle');
    var backdrop = document.getElementById('fvd-sidebar-backdrop');
    if (!shell || !btn) { return; }
    function setOpen(open) {
        if (open) {
            shell.classList.add('fvd-shell--nav-open');
            btn.setAttribute('aria-expanded', 'true');
        } else {
            shell.classList.remove('fvd-shell--nav-open');
            btn.setAttribute('aria-expanded', 'false');
        }
    }
    btn.addEventListener('click', function () {
        setOpen(!shell.classList.contains('fvd-shell--nav-open'));
    });
    if (backdrop) {
        backdrop.addEventListener('click', function () { setOpen(false); });
    }
    shell.querySelectorAll('.fvd-sidebar-nav a[href]').forEach(function (a) {
        a.addEventListener('click', function () {
            if (window.matchMedia('(max-width: 900px)').matches) { setOpen(false); }
        });
    });
})();
</script>
</body>
</html>
