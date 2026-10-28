/**
 * assets/js/rubros.js
 * Dashboard de rubros: listado AJAX, modales y alta/edición/baja.
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
    const box = document.getElementById('alerta-rubros');
    if (!box) return;
    box.style.display = 'block';
    box.style.backgroundColor = esExito ? '#dcfce7' : '#fee2e2';
    box.style.color = esExito ? '#166534' : '#991b1b';
    box.style.border = esExito ? '1px solid #bbf7d0' : '1px solid #fecaca';
    box.innerText = mensaje;
    setTimeout(() => {
        box.style.display = 'none';
    }, 4500);
}

function escaparHtml(texto) {
    if (!texto) return '';
    const div = document.createElement('div');
    div.textContent = texto;
    return div.innerHTML;
}

function cargarRubros(pagina = 1) {
    paginaActual = pagina;
    const busqueda = document.getElementById('buscador-rubro').value.trim();
    const estado = document.getElementById('filtro-estado').value;

    const url = `dashboard.php?ajax=1&pagina=${pagina}&busqueda=${encodeURIComponent(busqueda)}&estado=${encodeURIComponent(estado)}`;

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
    cargarRubros(p);
}

function renderizarTabla(rubros) {
    const contenedor = document.getElementById('tabla-rubros-body');
    if (!contenedor) return;

    if (!rubros || rubros.length === 0) {
        contenedor.innerHTML = '<div class="empty-state">No se encontraron rubros registrados con los criterios seleccionados.</div>';
        return;
    }

    let html = '';
    rubros.forEach(r => {
        const nombreEscapado = escaparHtml(r.nombre);
        const descEscapada = r.descripcion ? escaparHtml(r.descripcion) : 'Sin descripción';
        const cantInsumos = parseInt(r.total_insumos || 0, 10);
        const badgeInsumos = cantInsumos > 0
            ? `<button type="button" class="badge badge-info badge-boton" onclick="verInsumosRubro(${r.id_rubro}, '${nombreEscapado}')">${cantInsumos} insumos</button>`
            : `<span class="badge badge-neutral">0 insumos</span>`;

        const esActivo = Boolean(r.activo == true || r.activo == 1 || r.activo === 't');
        const badgeEstado = esActivo
            ? `<span class="badge badge-success">Activo</span>`
            : `<span class="badge badge-danger">Inactivo</span>`;

        const jsonStr = JSON.stringify(r).replace(/"/g, '&quot;');
        const btnAccionEstado = !APP_PERMISOS.admin ? '' : (esActivo ? `
        <button type="button" class="btn-action-icon disable" title="Deshabilitar Rubro" 
                onclick="abrirModalDeshabilitar(${r.id_rubro}, '${nombreEscapado}', ${cantInsumos})">
            ${window.APP_ICONS.disable}
        </button>` : `
        <button type="button" class="btn-action-icon enable" title="Reactivar Rubro" 
                onclick="habilitarRubro(${r.id_rubro}, '${nombreEscapado}')">
            ${window.APP_ICONS.enable}
        </button>`);

        html += `
    <div class="rubro-card-row">
        <div><strong>#${escaparHtml(String(r.id_rubro))}</strong></div>
        <div class="rubro-name-cell">${nombreEscapado}</div>
        <div class="rubro-desc-cell" title="${descEscapada}">${descEscapada}</div>
        <div>${badgeInsumos}</div>
        <div>${badgeEstado}</div>
        
        <div class="actions-cell">
            <a href="detalle_rubro.php?id=${parseInt(r.id_rubro, 10)}" class="btn-action-icon view" title="Ver Detalle">${window.APP_ICONS.view}</a>
            ${APP_PERMISOS.admin ? `
            <button type="button" class="btn-action-icon edit" title="Editar Rubro" onclick="abrirModalEditar(${jsonStr})">
                ${window.APP_ICONS.edit}
            </button>
            `: ''}
            ${btnAccionEstado}
        </div>
    </div>`;
    });

    contenedor.innerHTML = html;
}

function renderizarPaginacion(totalPaginas, pagina) {
    const contenedor = document.getElementById('contenedor-paginacion');
    if (!contenedor) return;

    if (totalPaginas <= 1) {
        contenedor.innerHTML = '';
        return;
    }

    let html = '';
    html += `<button type="button" class="pagination-btn text-label" onclick="cambiarPagina(${Math.max(1, pagina - 1)})" ${pagina <= 1 ? 'disabled' : ''}>Anterior</button>`;

    for (let p = 1; p <= totalPaginas; p++) {
        html += `<button type="button" class="pagination-btn ${p === pagina ? 'active' : ''}" onclick="cambiarPagina(${p})">${p}</button>`;
    }

    html += `<button type="button" class="pagination-btn text-label" onclick="cambiarPagina(${Math.min(totalPaginas, pagina + 1)})" ${pagina >= totalPaginas ? 'disabled' : ''}>Siguiente</button>`;

    contenedor.innerHTML = html;
}

document.getElementById('buscador-rubro').addEventListener('input', function () {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        cargarRubros(1);
    }, 300);
});

document.getElementById('filtro-estado').addEventListener('change', function () {
    cargarRubros(1);
});

function abrirModalCrear() {
    document.getElementById('form-cargar-rubro').reset();
    abrirModal('modal-cargar-rubro');
}

function guardarNuevoRubro(e) {
    e.preventDefault();
    const btnSubmit = document.getElementById('btn-submit-crear');
    btnSubmit.disabled = true;
    btnSubmit.innerText = 'Guardando...';

    const form = document.getElementById('form-cargar-rubro');
    const formData = new FormData(form);

    fetch('crear_rubro.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
        .then(response => {
            if (!response.ok) throw new Error('Error al conectar con el servidor.');
            return response.json();
        })
        .then(res => {
            if (!res.success) throw new Error(res.error || 'No se pudo crear el rubro.');
            cerrarModal('modal-cargar-rubro');
            form.reset();
            mostrarAlerta(res.message, true);
            cargarRubros(1);
        })
        .catch(err => {
            mostrarAlerta(err.message, false);
        })
        .finally(() => {
            btnSubmit.disabled = false;
            btnSubmit.innerText = 'Guardar Rubro';
        });
}

function abrirModalEditar(rubro) {
    document.getElementById('edit-id-rubro').value = rubro.id_rubro;
    document.getElementById('edit-nombre').value = rubro.nombre || '';
    document.getElementById('edit-descripcion').value = rubro.descripcion || '';
    abrirModal('modal-editar-rubro');
}

function guardarEdicionRubro(e) {
    e.preventDefault();
    const btnSubmit = document.getElementById('btn-submit-editar');
    btnSubmit.disabled = true;
    btnSubmit.innerText = 'Guardando...';

    const form = document.getElementById('form-editar-rubro');
    const formData = new FormData(form);

    fetch('editar_rubro.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
        .then(response => {
            if (!response.ok) throw new Error('Error al conectar con el servidor.');
            return response.json();
        })
        .then(res => {
            if (!res.success) throw new Error(res.error || 'No se pudo actualizar el rubro.');
            cerrarModal('modal-editar-rubro');
            mostrarAlerta(res.message, true);
            cargarRubros(paginaActual);
        })
        .catch(err => {
            mostrarAlerta(err.message, false);
        })
        .finally(() => {
            btnSubmit.disabled = false;
            btnSubmit.innerText = 'Guardar Cambios';
        });
}

function abrirModalDeshabilitar(idRubro, nombreRubro, cantInsumos) {
    document.getElementById('deshabilitar-id-rubro').value = idRubro;
    const warningBox = document.getElementById('deshabilitar-warning-insumos');

    if (cantInsumos > 0) {
        warningBox.style.display = 'block';
        warningBox.innerText = `Atención: Este rubro tiene ${cantInsumos} insumo(s) vinculado(s). Al deshabilitarlo, los insumos seguirán existiendo en el sistema pero el rubro no estará disponible para nuevos registros.`;
    } else {
        warningBox.style.display = 'none';
    }

    document.getElementById('deshabilitar-mensaje').innerHTML = `¿Está seguro de que desea deshabilitar el rubro <strong>"${escaparHtml(nombreRubro)}"</strong>?`;
    abrirModal('modal-deshabilitar-rubro');
}

function confirmarDeshabilitarRubro(e) {
    e.preventDefault();
    const btnSubmit = document.getElementById('btn-submit-deshabilitar');
    btnSubmit.disabled = true;
    btnSubmit.innerText = 'Procesando...';

    const idRubro = document.getElementById('deshabilitar-id-rubro').value;
    const formData = new FormData();
    formData.append('id_rubro', idRubro);
    formData.append('accion', 'deshabilitar');

    fetch('deshabilitar_rubro.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
        .then(response => {
            if (!response.ok) throw new Error('Error al conectar con el servidor.');
            return response.json();
        })
        .then(res => {
            if (!res.success) throw new Error(res.error || 'No se pudo deshabilitar el rubro.');
            cerrarModal('modal-deshabilitar-rubro');
            mostrarAlerta(res.message, true);
            cargarRubros(paginaActual);
        })
        .catch(err => {
            mostrarAlerta(err.message, false);
        })
        .finally(() => {
            btnSubmit.disabled = false;
            btnSubmit.innerText = 'Deshabilitar';
        });
}

function habilitarRubro(idRubro, nombreRubro) {
    if (!confirm(`¿Desea reactivar y habilitar el rubro "${nombreRubro}"?`)) return;

    const formData = new FormData();
    formData.append('id_rubro', idRubro);
    formData.append('accion', 'habilitar');

    fetch('deshabilitar_rubro.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
        .then(response => {
            if (!response.ok) throw new Error('Error al conectar con el servidor.');
            return response.json();
        })
        .then(res => {
            if (!res.success) throw new Error(res.error || 'No se pudo reactivar el rubro.');
            mostrarAlerta(res.message, true);
            cargarRubros(paginaActual);
        })
        .catch(err => {
            mostrarAlerta(err.message, false);
        });
}

function verInsumosRubro(idRubro, nombreRubro) {
    document.getElementById('insumos-rubro-titulo').innerText = `Insumos: ${nombreRubro}`;
    const contenedor = document.getElementById('lista-insumos-rubro');
    contenedor.innerHTML = '<p class="lista-insumos-msg">Cargando insumos...</p>';
    abrirModal('modal-insumos-rubro');

    fetch(`dashboard.php?action=insumos&id_rubro=${idRubro}`, { credentials: 'same-origin' })
        .then(response => response.json())
        .then(res => {
            if (!res.success || !res.data || res.data.length === 0) {
                contenedor.innerHTML = '<p class="lista-insumos-msg">No hay insumos vinculados a este rubro.</p>';
                return;
            }

            let html = '<table class="tabla-insumos-modal">';
            html += '<thead><tr>';
            html += '<th>Código</th>';
            html += '<th>Nombre</th>';
            html += '<th>Tipo</th>';
            html += '<th>Unidad</th>';
            html += '<th class="col-stock">Stock</th>';
            html += '</tr></thead><tbody>';

            res.data.forEach(item => {
                const itemActivo = Boolean(item.activo == true || item.activo == 1 || item.activo === 't');
                html += `<tr class="${itemActivo ? '' : 'fila-inactiva'}">`;
                html += `<td class="col-codigo">${escaparHtml(String(item.codigo))}</td>`;
                html += `<td>${escaparHtml(item.nombre)}${itemActivo ? '' : ' <span class="badge badge-danger badge-mini">Deshabilitado</span>'}</td>`;
                html += `<td>${escaparHtml(item.tipo_nombre || '-')}</td>`;
                html += `<td>${escaparHtml(item.unidad_medida || '-')}</td>`;
                html += `<td class="col-stock">${escaparHtml(String(item.stock_actual ?? 0))}</td>`;
                html += `</tr>`;
            });

            html += '</tbody></table>';
            contenedor.innerHTML = html;
        })
        .catch(err => {
            contenedor.innerHTML = `<p class="lista-insumos-msg lista-insumos-msg--error">Error al cargar insumos: ${escaparHtml(err.message)}</p>`;
        });
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-backdrop.active').forEach(modal => {
            modal.classList.remove('active');
        });
    }
});
