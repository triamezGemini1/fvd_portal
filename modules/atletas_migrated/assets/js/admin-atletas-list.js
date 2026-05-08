/**
 * JS migrado: proxy temporal al script estable existente.
 * Se conserva comportamiento mientras se completa desacople.
 */
(function () {
    var s = document.createElement('script');
    s.src = (typeof window.url === 'function')
        ? window.url('assets/js/admin-atletas-list.js')
        : '/assets/js/admin-atletas-list.js';
    s.defer = true;
    document.head.appendChild(s);
})();
