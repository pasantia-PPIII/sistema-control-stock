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


/**
 * Paginación común de los listados (.pagination-container). Muestra a lo sumo `ventana` botones
 * numéricos alrededor de la página actual. La carga inicial la hace este mismo archivo con los
 * data-total / data-actual de cada contenedor. Al elegir una página:
 *  - si el contenedor tiene data-param (fichas y detalles que se arman en el servidor), se recarga la
 *    página con ?<param>=<n> (y se vuelve a la sección indicada en data-ancla, si la tiene);
 *  - si no, se llama a cambiarPagina(n), definida por la vista (listados que se actualizan por AJAX).
 * Una página puede tener varios contenedores; cada uno usa su propio parámetro.
 */
function renderizarPaginacion(totalPaginas, actual, ventana, contenedor) {
    contenedor = contenedor || document.getElementById('contenedor-paginacion');
    if (!contenedor) return;

    totalPaginas = parseInt(totalPaginas, 10) || 1;
    actual = Math.min(Math.max(parseInt(actual, 10) || 1, 1), totalPaginas);
    ventana = ventana || 10;

    if (totalPaginas <= 1) {
        contenedor.innerHTML = '';
        return;
    }

    const inicio = Math.max(1, Math.min(actual - Math.floor(ventana / 2) + 1, totalPaginas - ventana + 1));
    const fin = Math.min(totalPaginas, inicio + ventana - 1);

    let html = `<button type="button" class="pagination-btn text-label" data-pagina="${actual - 1}" ${actual <= 1 ? 'disabled' : ''}>Anterior</button>`;
    for (let p = inicio; p <= fin; p++) {
        html += `<button type="button" class="pagination-btn ${p === actual ? 'active' : ''}" data-pagina="${p}">${p}</button>`;
    }
    html += `<button type="button" class="pagination-btn text-label" data-pagina="${actual + 1}" ${actual >= totalPaginas ? 'disabled' : ''}>Siguiente</button>`;

    contenedor.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.pagination-container[data-total]').forEach(function (contenedor) {
        renderizarPaginacion(contenedor.dataset.total, contenedor.dataset.actual, 10, contenedor);
    });
});

document.addEventListener('click', function (e) {
    const boton = e.target.closest('.pagination-container [data-pagina]');
    if (!boton || boton.disabled) return;
    const contenedor = boton.closest('.pagination-container');
    const pagina = parseInt(boton.dataset.pagina, 10);

    if (contenedor.dataset.param) {
        const url = new URL(window.location.href);
        url.searchParams.set(contenedor.dataset.param, pagina);
        if (contenedor.dataset.ancla) url.hash = contenedor.dataset.ancla;
        window.location.href = url.toString();
    } else if (typeof cambiarPagina === 'function') {
        cambiarPagina(pagina);
    }
});


/**
 * Filas de listado clickeables (.fila-clic): un clic en cualquier parte de la fila abre su detalle,
 * disparando el botón o enlace "ver" (.btn-action-icon.view) de esa misma fila. Así el clic hace siempre
 * lo mismo que el ícono del ojo (modal o página, según el módulo) y sirve también para las filas que
 * se vuelven a dibujar al buscar o paginar. Los botones, enlaces y campos de la fila no abren el detalle.
 */
document.addEventListener('click', function (e) {
    const fila = e.target.closest('.fila-clic');
    if (!fila) return;
    if (e.target.closest('a, button, input, select, textarea, label, .actions-cell')) return;
    if (window.getSelection && window.getSelection().toString() !== '') return; // se está seleccionando texto
    const ver = fila.querySelector('.btn-action-icon.view');
    if (ver) ver.click();
});

/**
 * Navegación responsive: control de apertura y cierre de sidebar en móviles y tablets.
 */
function toggleSidebar(forzar) {
    const sidebar = document.getElementById('app-sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    const body = document.body;

    const estaAbierto = body.classList.contains('sidebar-open');
    const debeAbrir = (typeof forzar === 'boolean') ? forzar : !estaAbierto;

    if (debeAbrir) {
        body.classList.add('sidebar-open');
        if (sidebar) sidebar.classList.add('is-open');
        if (backdrop) backdrop.classList.add('is-active');
    } else {
        body.classList.remove('sidebar-open');
        if (sidebar) sidebar.classList.remove('is-open');
        if (backdrop) backdrop.classList.remove('is-active');
    }
}

// Cerrar sidebar al presionar tecla ESC
window.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
        toggleSidebar(false);
    }
});

// Cerrar sidebar al hacer clic en un enlace de navegación en móvil
document.addEventListener('DOMContentLoaded', function () {
    const enlacesMenu = document.querySelectorAll('.sidebar-menu .menu-item');
    enlacesMenu.forEach(function (enlace) {
        enlace.addEventListener('click', function () {
            if (window.innerWidth <= 992) {
                toggleSidebar(false);
            }
        });
    });
});

