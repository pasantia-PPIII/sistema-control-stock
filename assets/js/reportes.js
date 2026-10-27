/**
 * assets/js/reportes.js
 * Modal de detalle de movimientos de la pestaña Historial (consulta reporte/detalle_auditoria.php).
 */

function abrirDetallesMovimiento(id) {
    document.getElementById('txt-modal-id').innerText = id;
    document.getElementById('modal-estado-carga').innerText = 'Cargando ítems...';
    document.getElementById('modal-estado-carga').style.display = 'block';
    document.getElementById('modal-tabla-items').style.display = 'none';
    document.getElementById('modal-auditoria').style.display = 'flex';

    fetch('detalle_auditoria.php?id=' + id)
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.detalles && data.detalles.length > 0) {
                document.getElementById('modal-estado-carga').style.display = 'none';
                let tbody = document.getElementById('modal-filas');
                tbody.innerHTML = '';
                data.detalles.forEach(it => {
                    tbody.innerHTML += `
                    <tr>
                        <td><strong>${it.codigo}</strong></td>
                        <td>${it.item_nombre}</td>
                        <td>${it.cantidad} ${it.unidad}</td>
                        <td>${it.estado_herramienta}</td>
                    </tr>
                `;
                });
                document.getElementById('modal-tabla-items').style.display = 'block';
            } else if (data.success) {
                document.getElementById('modal-estado-carga').innerText = 'No hay ítems registrados en este movimiento.';
            } else {
                document.getElementById('modal-estado-carga').innerText = 'Error: ' + (data.error || 'No se pudieron recuperar los registros.');
            }
        })
        .catch(err => {
            document.getElementById('modal-estado-carga').innerText = 'Fallo en la consulta: ' + err.message;
        });
}

function cerrarModalAuditoria() {
    document.getElementById('modal-auditoria').style.display = 'none';
}
