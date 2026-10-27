/**
 * assets/js/rubro_detalle.js
 * Detalle de rubro: filtro en vivo de insumos por texto y tipo, contador y fila de "sin resultados".
 */

document.addEventListener('DOMContentLoaded', function () {
    const buscador = document.getElementById('buscador-insumo-rubro');
    const filtroTipo = document.getElementById('filtro-tipo-rubro');
    const totalContador = document.getElementById('total-articulos-visibles');
    const filas = document.querySelectorAll('.fila-insumo');
    const filaSinResultados = document.getElementById('fila-sin-resultados');

    function filtrarTabla() {
        const texto = buscador.value.toLowerCase().trim();
        const tipoSel = filtroTipo.value.toLowerCase().trim();
        let visibles = 0;

        filas.forEach(fila => {
            const codigo = fila.querySelector('td strong') ? fila.querySelector('td strong').innerText.toLowerCase() : '';
            const nombre = fila.querySelector('.col-nombre') ? fila.querySelector('.col-nombre').innerText.toLowerCase() : '';
            const tipoFila = fila.getAttribute('data-tipo') || '';

            const coincideTexto = codigo.includes(texto) || nombre.includes(texto);
            const coincideTipo = tipoSel === '' || tipoFila.includes(tipoSel);

            if (coincideTexto && coincideTipo) {
                fila.style.display = '';
                visibles++;
            } else {
                fila.style.display = 'none';
            }
        });

        if (totalContador) {
            totalContador.innerText = visibles;
        }

        // Mostrar o esconder el aviso de sin resultados
        if (filaSinResultados) {
            filaSinResultados.style.display = (visibles === 0) ? 'table-row' : 'none';
        }
    }

    if (buscador) buscador.addEventListener('input', filtrarTabla);
    if (filtroTipo) filtroTipo.addEventListener('change', filtrarTabla);
});
