/**
 * assets/js/operarios.js
 * Dashboard de operarios: listado AJAX, modales y alta/edición/baja lógica.
 * Datos de la página: <script type="application/json" id="datos-pagina"> en la vista.
 */

const DATOS_PAGINA = JSON.parse(document.getElementById('datos-pagina')?.textContent || '{}');

let paginaActual = DATOS_PAGINA.paginaActual || 1;
let debounceTimeout = null;

function abrirModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('active');
}

function cerrarModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
}

function mostrarAlerta(mensaje, esExito = true) {
    const box = document.getElementById('alerta-operarios');
    if (!box) return;
    box.style.display = 'block';
    box.style.backgroundColor = esExito ? '#dcfce7' : '#fee2e2';
    box.style.color = esExito ? '#166534' : '#991b1b';
    box.style.border = esExito ? '1px solid #bbf7d0' : '1px solid #fecaca';
    box.innerText = mensaje;
    setTimeout(() => {
        box.style.display = 'none';
    }, 4000);
}

function cargarOperarios(pagina = 1) {
    paginaActual = pagina;
    const busqueda = document.getElementById('buscador-operario').value.trim();
    const rubroId = document.getElementById('filtro-rubro').value;
    const estado = document.getElementById('filtro-estado').value;

    const url = `dashboard.php?ajax=1&pagina=${pagina}&busqueda=${encodeURIComponent(busqueda)}&rubro_id=${encodeURIComponent(rubroId)}&estado=${encodeURIComponent(estado)}`;

    fetch(url, { credentials: 'same-origin' })
        .then(response => {
            if (!response.ok) throw new Error('Error al conectar con el servidor.');
            return response.json();
        })
        .then(res => {
            if (!res.success) throw new Error(res.error || 'Error al obtener registros.');
            renderizarTabla(res.data);
            renderizarPaginacion(res.total_paginas, res.pagina_actual);
        })
        .catch(err => {
            mostrarAlerta(err.message, false);
        });
}

function cambiarPagina(p) {
    cargarOperarios(p);
}

function renderizarTabla(operarios) {
    const contenedor = document.getElementById('tabla-operarios-body');
    if (!contenedor) return;

    if (!operarios || operarios.length === 0) {
        contenedor.innerHTML = '<div class="empty-state">No se encontraron operarios registrados con los filtros seleccionados.</div>';
        return;
    }

    let html = '';
    operarios.forEach(op => {
        const nombreCompleto = escaparHtml(`${op.apellido}, ${op.nombre}`);
        const legajoVal = op.legajo || '';
        const legajoTxt = legajoVal ? escaparHtml(legajoVal) : '-';
        const rubroTxt = op.nombre_rubro ? escaparHtml(op.nombre_rubro) : 'General';
        const esActivo = Boolean(op.activo == true || op.activo == 1 || op.activo === 't');
        const idOp = parseInt(op.id_operario, 10);

        const jsonStr = JSON.stringify(op).replace(/"/g, '&quot;');
        // Datos en data-* (escapados) para que un apóstrofo en el nombre no rompa el onclick
        const datosEstado = `data-id="${idOp}" data-dni="${escaparHtml(op.dni)}" data-nombre="${nombreCompleto}"`;
        const btnAccionEstado = !APP_PERMISOS.admin ? '' : (esActivo ? `
        <button type="button" class="btn-action-text disable" title="Deshabilitar Operario" ${datosEstado}
                onclick="abrirModalEstado(this.dataset.id, this.dataset.dni, this.dataset.nombre, true)">
            Deshabilitar
        </button>` : `
        <button type="button" class="btn-action-text view" title="Habilitar Operario" ${datosEstado}
                onclick="abrirModalEstado(this.dataset.id, this.dataset.dni, this.dataset.nombre, false)">
            Habilitar
        </button>`);

        html += `
    <div class="operario-card-row" onclick="window.location.href='detalle_operario.php?id=${idOp}'">
        <div><strong>${legajoTxt}</strong></div>
        <div>${escaparHtml(op.dni)}</div>
        <div class="operario-nombre">${nombreCompleto}</div>
        <div>${rubroTxt}</div>
        
        <div class="actions-cell" onclick="event.stopPropagation()">
            <a href="detalle_operario.php?id=${idOp}" class="btn-action-text view" title="Ver Ficha">Ver Ficha</a>
            ${APP_PERMISOS.admin ? `
            <button type="button" class="btn-action-text edit" title="Editar Operario" onclick="abrirModalEditar(${jsonStr})">
                Editar
            </button>
            `: ''}
            ${btnAccionEstado}
        </div>
    </div>
    `;
    });

    contenedor.innerHTML = html;
}

function renderizarPaginacion(totalPaginas, actual) {
    const contenedor = document.getElementById('contenedor-paginacion');
    if (!contenedor) return;

    if (totalPaginas <= 1) {
        contenedor.innerHTML = '';
        return;
    }

    let html = '';
    html += `<button type="button" class="pagination-btn text-label" onclick="cambiarPagina(${Math.max(1, actual - 1)})" ${actual <= 1 ? 'disabled' : ''}>Anterior</button>`;

    for (let p = 1; p <= totalPaginas; p++) {
        html += `<button type="button" class="pagination-btn ${p === actual ? 'active' : ''}" onclick="cambiarPagina(${p})">${p}</button>`;
    }

    html += `<button type="button" class="pagination-btn text-label" onclick="cambiarPagina(${Math.min(totalPaginas, actual + 1)})" ${actual >= totalPaginas ? 'disabled' : ''}>Siguiente</button>`;

    contenedor.innerHTML = html;
}

function escaparHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

document.getElementById('buscador-operario').addEventListener('input', function () {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        cargarOperarios(1);
    }, 280);
});

document.getElementById('filtro-rubro').addEventListener('change', function () {
    cargarOperarios(1);
});

document.getElementById('filtro-estado').addEventListener('change', function () {
    cargarOperarios(1);
});

function abrirModalCrear() {
    const form = document.getElementById('form-cargar-operario');
    if (form) form.reset();
    document.getElementById('msg-error-cargar').style.display = 'none';
    abrirModal('modal-cargar-operario');
}

function guardarNuevoOperario(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-submit-cargar');
    const msgError = document.getElementById('msg-error-cargar');
    msgError.style.display = 'none';
    btn.disabled = true;
    btn.innerText = 'Guardando...';

    const formData = new FormData(e.target);

    fetch('crear_operario.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerText = 'Guardar Operario';
            if (res.success) {
                cerrarModal('modal-cargar-operario');
                mostrarAlerta(res.message || 'Operario registrado exitosamente.', true);
                cargarOperarios(1);
            } else {
                msgError.innerText = res.error || 'Ocurrió un error al guardar el operario.';
                msgError.style.display = 'block';
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerText = 'Guardar Operario';
            msgError.innerText = 'Error de conexión: ' + err.message;
            msgError.style.display = 'block';
        });
}

function abrirModalEditar(op) {
    document.getElementById('edit-id-operario').value = op.id_operario || '';
    document.getElementById('edit-dni').value = op.dni || '';
    document.getElementById('edit-legajo').value = op.legajo || '';
    document.getElementById('edit-apellido').value = op.apellido || '';
    document.getElementById('edit-nombre').value = op.nombre || '';
    document.getElementById('edit-id-rubro').value = op.id_rubro || '';

    document.getElementById('msg-error-edit').style.display = 'none';
    abrirModal('modal-editar-operario');
}

function guardarEdicionOperario(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-submit-edit');
    const msgError = document.getElementById('msg-error-edit');
    msgError.style.display = 'none';
    btn.disabled = true;
    btn.innerText = 'Guardando...';

    const formData = new FormData(e.target);

    fetch('editar_operario.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerText = 'Guardar Cambios';
            if (res.success) {
                cerrarModal('modal-editar-operario');
                mostrarAlerta(res.message || 'Operario actualizado exitosamente.', true);
                cargarOperarios(paginaActual);
            } else {
                msgError.innerText = res.error || 'Ocurrió un error al actualizar.';
                msgError.style.display = 'block';
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerText = 'Guardar Cambios';
            msgError.innerText = 'Error de conexión: ' + err.message;
            msgError.style.display = 'block';
        });
}

function abrirModalEstado(idOperario, dni, nombre, deshabilitar) {
    const caja = document.getElementById('estado-caja');
    const nota = document.getElementById('estado-nota');
    const titulo = document.getElementById('estado-titulo');
    const btn = document.getElementById('btn-submit-estado');

    document.getElementById('estado-id-val').value = idOperario;
    document.getElementById('estado-accion-val').value = deshabilitar ? 'deshabilitar' : 'habilitar';
    document.getElementById('estado-info-nombre').innerText = nombre;
    document.getElementById('estado-info-dni').innerText = dni;

    if (deshabilitar) {
        titulo.innerText = 'Deshabilitar Operario';
        titulo.style.color = '#dc2626';
        document.getElementById('estado-subtitulo').innerText = '¿Está seguro de que desea dar de baja al siguiente operario?';
        caja.style.backgroundColor = '#fef2f2';
        caja.style.border = '1px solid #fecaca';
        caja.style.color = '#991b1b';
        nota.style.color = '#b91c1c';
        nota.innerText = 'El registro pasará al estado inactivo. Se conservará todo el historial de movimientos de insumos, auditorías y órdenes de trabajo asociadas.';
        btn.style.backgroundColor = '#dc2626';
        btn.innerText = 'Confirmar Deshabilitación';
    } else {
        titulo.innerText = 'Habilitar Operario';
        titulo.style.color = '#166534';
        document.getElementById('estado-subtitulo').innerText = '¿Desea volver a habilitar al siguiente operario?';
        caja.style.backgroundColor = '#f0fdf4';
        caja.style.border = '1px solid #bbf7d0';
        caja.style.color = '#166534';
        nota.style.color = '#15803d';
        nota.innerText = 'El operario volverá a estar disponible para movimientos y órdenes de trabajo. Su usuario del sistema (si tiene) no se modifica.';
        btn.style.backgroundColor = '#16a34a';
        btn.innerText = 'Confirmar Habilitación';
    }
    abrirModal('modal-estado-operario');
}

function ejecutarCambioEstadoOperario(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const deshabilitar = formData.get('accion') === 'deshabilitar';

    fetch('deshabilitar_operario.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
        .then(r => r.json())
        .then(res => {
            cerrarModal('modal-estado-operario');
            if (res.success) {
                mostrarAlerta(res.message || (deshabilitar ? 'Operario deshabilitado exitosamente.' : 'Operario habilitado exitosamente.'), true);
                cargarOperarios(paginaActual);
            } else {
                mostrarAlerta(res.error || 'Error al procesar la acción sobre el operario.', false);
            }
        })
        .catch(err => {
            cerrarModal('modal-estado-operario');
            mostrarAlerta('Error de conexión: ' + err.message, false);
        });
}
