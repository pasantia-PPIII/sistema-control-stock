/**
 * assets/js/rubro_detalle.js
 * Detalle de rubro: filtro en vivo de insumos por texto y tipo, contador, fila de "sin resultados"
 * y paginación de a 10 filas (la misma paginación de los demás listados, ver renderizarPaginacion en main.js).
 */

document.addEventListener('DOMContentLoaded', function () {
    const POR_PAGINA = 10;

    const buscador = document.getElementById('buscador-insumo-rubro');
    const filtroTipo = document.getElementById('filtro-tipo-rubro');
    const totalContador = document.getElementById('total-articulos-visibles');
    const filas = Array.from(document.querySelectorAll('.fila-insumo'));
    const filaSinResultados = document.getElementById('fila-sin-resultados');

    let filasFiltradas = filas.slice();
    let paginaActual = 1;

    // Muestra solo las filas de la página actual entre las que cumplen el filtro
    function mostrarPagina() {
        const totalPaginas = Math.max(1, Math.ceil(filasFiltradas.length / POR_PAGINA));
        paginaActual = Math.min(Math.max(paginaActual, 1), totalPaginas);
        const desde = (paginaActual - 1) * POR_PAGINA;
        const enPagina = new Set(filasFiltradas.slice(desde, desde + POR_PAGINA));

        filas.forEach(fila => {
            fila.style.display = enPagina.has(fila) ? '' : 'none';
        });

        if (totalContador) {
            totalContador.innerText = filasFiltradas.length;
        }

        // Mostrar o esconder el aviso de sin resultados
        if (filaSinResultados) {
            filaSinResultados.style.display = (filasFiltradas.length === 0) ? 'table-row' : 'none';
        }

        renderizarPaginacion(totalPaginas, paginaActual);
    }

    function filtrarTabla() {
        const texto = buscador.value.toLowerCase().trim();
        const tipoSel = filtroTipo.value.toLowerCase().trim();

        filasFiltradas = filas.filter(fila => {
            const codigo = fila.querySelector('td strong') ? fila.querySelector('td strong').innerText.toLowerCase() : '';
            const nombre = fila.querySelector('.col-nombre') ? fila.querySelector('.col-nombre').innerText.toLowerCase() : '';
            const tipoFila = fila.getAttribute('data-tipo') || '';

            const coincideTexto = codigo.includes(texto) || nombre.includes(texto);
            const coincideTipo = tipoSel === '' || tipoFila.includes(tipoSel);
            return coincideTexto && coincideTipo;
        });

        paginaActual = 1; // al cambiar el filtro se vuelve a la primera página
        mostrarPagina();
    }

    // Llamada por los botones de la paginación común (main.js)
    window.cambiarPagina = function (pagina) {
        paginaActual = pagina;
        mostrarPagina();
    };

    if (buscador) buscador.addEventListener('input', filtrarTabla);
    if (filtroTipo) filtroTipo.addEventListener('change', filtrarTabla);

    mostrarPagina();
});
