/**
 * assets/js/auditoria.js
 * Modales de la pantalla de Auditoría (auditoria/dashboard.php):
 *  - Ítems de un movimiento (consulta auditoria/detalle_movimiento.php).
 *  - Detalle antes / después de un cambio en registros (los datos vienen en atributos data- de la fila).
 * Todo lo que viene de la base se inserta con textContent: un nombre con <script> o comillas no rompe la página.
 */

function auditoriaAbrirModal(id) {
    document.getElementById(id).style.display = 'flex';
}

function auditoriaCerrarModal(id) {
    document.getElementById(id).style.display = 'none';
}

function auditoriaCelda(fila, texto, clase) {
    const td = document.createElement('td');
    td.textContent = texto;
    if (clase) {
        td.className = clase;
    }
    fila.appendChild(td);
    return td;
}

// ---------------------------------------------------------------------------------------------
// Movimientos: desglose de ítems
// ---------------------------------------------------------------------------------------------
function abrirDetallesMovimiento(id) {
    const estado = document.getElementById('au-mov-estado');
    const tabla = document.getElementById('au-mov-tabla');
    const tbody = document.getElementById('au-mov-filas');

    document.getElementById('au-mov-id').textContent = id;
    estado.textContent = 'Cargando ítems...';
    estado.style.display = 'block';
    tabla.style.display = 'none';
    tbody.replaceChildren();
    auditoriaAbrirModal('au-modal-movimiento');

    fetch('detalle_movimiento.php?id=' + encodeURIComponent(id))
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.detalles && data.detalles.length > 0) {
                data.detalles.forEach(it => {
                    const fila = document.createElement('tr');
                    auditoriaCelda(fila, it.codigo).style.fontWeight = '700';
                    auditoriaCelda(fila, it.item_nombre);
                    auditoriaCelda(fila, it.cantidad + ' ' + it.unidad);
                    auditoriaCelda(fila, it.estado_herramienta);
                    tbody.appendChild(fila);
                });
                estado.style.display = 'none';
                tabla.style.display = 'block';
            } else if (data.success) {
                estado.textContent = 'No hay ítems registrados en este movimiento.';
            } else {
                estado.textContent = 'Error: ' + (data.error || 'No se pudieron recuperar los registros.');
            }
        })
        .catch(err => {
            estado.textContent = 'Fallo en la consulta: ' + err.message;
        });
}

// ---------------------------------------------------------------------------------------------
// Cambios en registros: antes / después
// ---------------------------------------------------------------------------------------------
function auditoriaValor(valor) {
    if (valor === null || valor === undefined || valor === '') {
        return '—';
    }
    if (valor === true) {
        return 'Sí';
    }
    if (valor === false) {
        return 'No';
    }
    if (typeof valor === 'object') {
        return JSON.stringify(valor);
    }
    return String(valor);
}

function abrirDetalleCambio(boton) {
    let antes = null;
    let despues = null;
    try {
        antes = JSON.parse(boton.dataset.antes || 'null');
        despues = JSON.parse(boton.dataset.despues || 'null');
    } catch (e) {
        // Datos ilegibles: se muestra el modal vacío
    }

    document.getElementById('au-cambio-titulo').textContent = boton.dataset.titulo || '';
    document.getElementById('au-cambio-meta').textContent = boton.dataset.meta || '';

    const tbody = document.getElementById('au-cambio-filas');
    tbody.replaceChildren();

    const campos = Array.from(new Set([
        ...Object.keys(antes || {}),
        ...Object.keys(despues || {})
    ]));

    if (campos.length === 0) {
        const fila = document.createElement('tr');
        const td = auditoriaCelda(fila, 'Este evento no guardó datos adicionales.', 'au-vacio');
        td.colSpan = 3;
        tbody.appendChild(fila);
    }

    campos.forEach(campo => {
        const fila = document.createElement('tr');
        auditoriaCelda(fila, campo).style.fontWeight = '700';
        auditoriaCelda(fila, antes && campo in antes ? auditoriaValor(antes[campo]) : '—', 'antes');
        auditoriaCelda(fila, despues && campo in despues ? auditoriaValor(despues[campo]) : '—', 'despues');
        tbody.appendChild(fila);
    });

    auditoriaAbrirModal('au-modal-cambio');
}

// Cerrar con Escape o haciendo clic fuera de la caja
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.au-modal').forEach(m => { m.style.display = 'none'; });
    }
});
document.querySelectorAll('.au-modal').forEach(modal => {
    modal.addEventListener('click', e => {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
});
