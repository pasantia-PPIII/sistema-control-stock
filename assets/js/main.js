/**
 * assets/js/main.js
 * Funcionalidades globales del sistema de control de stock:
 * Control de modales y comportamiento común de la interfaz.
 */

function abrirModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.style.display = 'flex';
    }
}

function cerrarModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.style.display = 'none';
    }
}

// Cierre de modal al hacer clic en el fondo oscuro
window.addEventListener('click', function(event) {
    if (event.target && event.target.classList.contains('modal-overlay')) {
        event.target.style.display = 'none';
    }
});

// Protección CSRF: todas las peticiones fetch() que modifican datos envían el token de la sesión
// (publicado por layout/header.php en <meta name="csrf-token">).
(function () {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (!meta || typeof window.fetch !== 'function') return;

    const fetchOriginal = window.fetch.bind(window);
    window.fetch = function (input, init) {
        init = init || {};
        const metodo = String(init.method || (input && input.method) || 'GET').toUpperCase();
        if (metodo !== 'GET' && metodo !== 'HEAD') {
            const headers = new Headers(init.headers || (input && input.headers) || {});
            if (!headers.has('X-CSRF-Token')) {
                headers.set('X-CSRF-Token', meta.content);
            }
            init.headers = headers;
        }
        return fetchOriginal(input, init);
    };
})();
