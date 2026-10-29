/**
 * assets/js/insumos.js
 * Catálogo de insumos: listado AJAX, modales y alta/edición/estado.
 * Datos de la página: <script type="application/json" id="datos-pagina"> en la vista.
 */

const DATOS_PAGINA = JSON.parse(document.getElementById('datos-pagina')?.textContent || '{}');

let paginaActual = DATOS_PAGINA.paginaActual || 1;
let debounceTimeout = null;

// Control de apertura y cierre de modales
function abrirModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('active');
}

function cerrarModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
}

function mostrarAlerta(mensaje, esExito = true) {
    const box = document.getElementById('alerta-dashboard');
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

function alternarPerecedero(activo, prefijo) {
    const contenedor = document.getElementById('grupo-vencimiento-' + prefijo);
    if (contenedor) {
        contenedor.style.display = activo ? 'flex' : 'none';
        if (!activo) {
            const input = document.getElementById(prefijo + '-fecha-vencimiento');
            if (input) input.value = '';
        }
    }
}

// Muestra el campo de stock (materiales) o la nota (herramientas, cuyo stock es 1 en el pañol / 0 fuera)
function mostrarCampoStock(prefijo, esHerramienta) {
    const grupo = document.getElementById('grupo-stock-' + prefijo);
    const nota = document.getElementById('nota-stock-' + prefijo);
    if (grupo) grupo.style.display = esHerramienta ? 'none' : '';
    if (nota) nota.style.display = esHerramienta ? 'block' : 'none';
    // En el alta el stock inicial es obligatorio, salvo en herramientas (nacen con 1 unidad)
    const inputInicial = document.getElementById(prefijo + '-stock-inicial');
    if (inputInicial) inputInicial.required = !esHerramienta;
}

function alternarCamposInsumo(idTipo, prefijo) {
    const select = document.getElementById(prefijo + '-id-tipo');
    if (!select) return;
    const selectedOption = select.options[select.selectedIndex];
    const tipoNombre = selectedOption ? (selectedOption.dataset.nombre || '') : '';
    mostrarCampoStock(prefijo, tipoNombre.includes('herramienta'));
}

// =========================================================================
// CARGA DINÁMICA DE INSUMOS MEDIANTE FETCH() (SIN RECARGAR LA PÁGINA)
// =========================================================================
function cargarInsumos(pagina = 1) {
    paginaActual = pagina;
    const busqueda = document.getElementById('buscador-insumo').value.trim();
    const tipo = document.getElementById('filtro-tipo').value;
    const rubro = document.getElementById('filtro-rubro').value;
    const estado = document.getElementById('filtro-estado').value;

    const url = `dashboard.php?ajax=1&pagina=${pagina}&busqueda=${encodeURIComponent(busqueda)}&tipo=${encodeURIComponent(tipo)}&rubro=${encodeURIComponent(rubro)}&estado=${encodeURIComponent(estado)}`;

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
    cargarInsumos(p);
}

function renderizarTabla(insumos) {
    const contenedor = document.getElementById('tabla-insumos-body');
    if (!contenedor) return;

    if (!insumos || insumos.length === 0) {
        contenedor.innerHTML = '<div class="empty-state">No se encontraron insumos con los criterios seleccionados.</div>';
        return;
    }

    let html = '';
    insumos.forEach(ins => {
        const esHerramienta = (ins.nombre_tipo && ins.nombre_tipo.toLowerCase().includes('herramienta'));
        const tipoTxt = ins.nombre_tipo || 'Material';
        const umTxt = esHerramienta ? '-' : (ins.unidad_medida || 'Unidad');
        const stkTxt = (ins.stock_actual !== null && ins.stock_actual !== undefined) ? ins.stock_actual : 0;
        const insActivo = (ins.activo === true || ins.activo === 't' || ins.activo === 1 || ins.activo === '1');
        let estadoVenc = !insActivo ? ((esHerramienta && ins.motivo_baja) ? `Dada de baja (${ins.motivo_baja})` : 'Deshabilitado') : (esHerramienta ? (ins.nombre_estado_herramienta || 'Disponible') : (ins.fecha_vencimiento ? ins.fecha_vencimiento : 'No perecedero'));
        if (insActivo && esHerramienta && ins.asignado_a) {
            const [anio, mes, dia] = String(ins.asignado_desde).substring(0, 10).split('-');
            estadoVenc += ` - ${ins.asignado_a} (desde ${dia}/${mes}/${anio})`;
        }
        const jsonStr = JSON.stringify(ins).replace(/"/g, '&quot;');
        // Código y nombre viajan en data-* (escapados) para que un apóstrofo no rompa el onclick
        const datosEstado = `data-codigo="${escaparHtml(ins.codigo)}" data-nombre="${escaparHtml(ins.nombre)}" data-herramienta="${esHerramienta ? '1' : '0'}"`;
        const botonesEdicion = insActivo ? `
                <button type="button" class="btn-action-icon edit" title="Editar Insumo" onclick="abrirModalEditar(${jsonStr})">
                    ${window.APP_ICONS.edit}
                </button>
                <button type="button" class="btn-action-icon disable" title="Deshabilitar Insumo" ${datosEstado} onclick="abrirModalEstado(this.dataset.codigo, this.dataset.nombre, true, this.dataset.herramienta === '1')">
                    ${window.APP_ICONS.disable}
                </button>` : `
                <button type="button" class="btn-action-icon enable" title="Habilitar Insumo" ${datosEstado} onclick="abrirModalEstado(this.dataset.codigo, this.dataset.nombre, false)">
                    ${window.APP_ICONS.enable}
                </button>`;

        html += `
        <div class="insumo-card-row fila-clic${insActivo ? '' : ' insumo-card-row--inactivo'}">
            <div><strong>${escaparHtml(ins.codigo)}</strong></div>
            <div>${escaparHtml(ins.nombre)}</div>
            <div>${escaparHtml(ins.nombre_rubro || '-')}</div>
            <div>${escaparHtml(umTxt)}</div>
            <div>${escaparHtml(stkTxt)}</div>
            <div>${escaparHtml(tipoTxt)}</div>
            <div>${escaparHtml(estadoVenc)}</div>
            
            <div class="actions-cell" onclick="event.stopPropagation()">
                <button type="button" class="btn-action-icon view" title="Ver Detalles" onclick="abrirModalDetalles(${jsonStr})">
                    ${window.APP_ICONS.view}
                </button>
                ${APP_PERMISOS.editar ? botonesEdicion : ''}
            </div>
        </div>
        `;
    });

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

// =========================================================================
// EVENTOS DE BÚSQUEDA Y FILTRADO
// =========================================================================
document.getElementById('buscador-insumo').addEventListener('input', function() {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        cargarInsumos(1);
    }, 280);
});

document.getElementById('filtro-tipo').addEventListener('change', function() {
    cargarInsumos(1);
});

document.getElementById('filtro-rubro').addEventListener('change', function() {
    cargarInsumos(1);
});

document.getElementById('filtro-estado').addEventListener('change', function() {
    cargarInsumos(1);
});

// =========================================================================
// MODAL: CARGAR INSUMO (FETCH POST)
// =========================================================================
function abrirModalCrear() {
    const form = document.getElementById('form-cargar-insumo');
    if (form) form.reset();
    document.getElementById('msg-error-cargar').style.display = 'none';
    alternarPerecedero(false, 'cargar');
    mostrarCampoStock('cargar', false);
    abrirModal('modal-cargar-insumo');
}

function guardarNuevoInsumo(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-submit-cargar');
    const msgError = document.getElementById('msg-error-cargar');
    msgError.style.display = 'none';
    btn.disabled = true;
    btn.innerText = 'Guardando...';

    const formData = new FormData(e.target);

    fetch('crear_insumo.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.innerText = 'Guardar Insumo';
        if (res.success) {
            cerrarModal('modal-cargar-insumo');
            mostrarAlerta(res.message || 'Insumo creado exitosamente.', true);
            cargarInsumos(1);
        } else {
            msgError.innerText = res.error || 'Ocurrió un error al guardar el insumo.';
            msgError.style.display = 'block';
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerText = 'Guardar Insumo';
        msgError.innerText = 'Error de conexión: ' + err.message;
        msgError.style.display = 'block';
    });
}

// =========================================================================
// MODAL: EDITAR INSUMO (FETCH POST)
// =========================================================================
function abrirModalEditar(ins) {
    document.getElementById('edit-codigo').value = ins.codigo || '';
    document.getElementById('edit-nombre').value = ins.nombre || '';
    document.getElementById('edit-descripcion').value = ins.descripcion || '';
    document.getElementById('edit-id-rubro').value = ins.id_rubro || '';
    document.getElementById('edit-id-tipo').value = ins.id_tipo || '';
    document.getElementById('edit-id-um').value = ins.id_unidad_medida || '';
    document.getElementById('edit-id-ubicacion').value = ins.id_ubicacion || '';
    document.getElementById('edit-stock-minimo').value = ins.stock_minimo !== null ? ins.stock_minimo : 0;

    // Materiales: el stock se edita acá. Herramientas: lo manejan las entregas y devoluciones
    const esHerramientaEdit = (ins.nombre_tipo && ins.nombre_tipo.toLowerCase().includes('herramienta'));
    document.getElementById('edit-stock-actual').value = (ins.stock_actual !== null && ins.stock_actual !== undefined) ? ins.stock_actual : 0;
    mostrarCampoStock('edit', esHerramientaEdit);

    const esPerecedero = (ins.es_perecedero === true || ins.es_perecedero === 't' || ins.es_perecedero === 1 || ins.es_perecedero === '1');
    const chkPerecedero = document.getElementById('edit-es-perecedero');
    chkPerecedero.checked = esPerecedero;
    alternarPerecedero(esPerecedero, 'edit');
    document.getElementById('edit-fecha-vencimiento').value = ins.fecha_vencimiento || '';

    document.getElementById('msg-error-edit').style.display = 'none';
    abrirModal('modal-editar-insumo');
}

function guardarEdicionInsumo(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-submit-edit');
    const msgError = document.getElementById('msg-error-edit');
    msgError.style.display = 'none';
    btn.disabled = true;
    btn.innerText = 'Guardando...';

    const formData = new FormData(e.target);

    fetch('editar_insumo.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.innerText = 'Guardar Cambios';
        if (res.success) {
            cerrarModal('modal-editar-insumo');
            mostrarAlerta(res.message || 'Insumo actualizado exitosamente.', true);
            cargarInsumos(paginaActual);
        } else {
            msgError.innerText = res.error || 'Ocurrió un error al actualizar el insumo.';
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

// =========================================================================
// MODAL: VER DETALLES
// =========================================================================
function abrirModalDetalles(ins) {
    document.getElementById('det-codigo').innerText = ins.codigo || '-';
    document.getElementById('det-nombre').innerText = ins.nombre || '-';
    document.getElementById('det-descripcion').innerText = ins.descripcion || '-';
    document.getElementById('det-tipo').innerText = ins.nombre_tipo || 'Material';
    document.getElementById('det-rubro').innerText = ins.nombre_rubro || 'Sin rubro';
    
    const esHerramienta = (ins.nombre_tipo && ins.nombre_tipo.toLowerCase().includes('herramienta'));
    document.getElementById('det-um').innerText = esHerramienta ? 'N/A (Herramienta)' : (ins.unidad_medida || 'Unidad');
    const stockDet = (ins.stock_actual !== null && ins.stock_actual !== undefined) ? ins.stock_actual : 0;
    document.getElementById('det-stock').innerText = esHerramienta ? `${stockDet} (1 = en el pañol, 0 = fuera del pañol)` : stockDet;
    document.getElementById('det-stock-min').innerText = ins.stock_minimo !== null ? ins.stock_minimo : '0';
    document.getElementById('det-ubicacion').innerText = ins.nombre_ubicacion || 'No especificada';
    
    const esPerecedero = (ins.es_perecedero === true || ins.es_perecedero === 't' || ins.es_perecedero === 1 || ins.es_perecedero === '1');
    document.getElementById('det-perecedero').innerText = esPerecedero ? 'Sí' : 'No';
    document.getElementById('det-vencimiento').innerText = ins.fecha_vencimiento ? ins.fecha_vencimiento : 'No aplica';
    
    const activo = (ins.activo === true || ins.activo === 't' || ins.activo === 1 || ins.activo === '1');
    document.getElementById('det-activo').innerText = activo ? 'Activo' : (ins.motivo_baja ? `Dada de baja (${ins.motivo_baja})` : 'Deshabilitado');
    // Ficha completa: kardex, asignaciones de la herramienta y datos de la baja
    document.getElementById('det-ficha-link').href = 'detalles_insumo.php?codigo=' + encodeURIComponent(ins.codigo);

    abrirModal('modal-detalles-insumo');
}

// =========================================================================
// MODAL: DESHABILITAR / HABILITAR INSUMO (FETCH POST)
// =========================================================================
function abrirModalEstado(codigo, nombre, deshabilitar, esHerramienta) {
    const caja = document.getElementById('estado-caja');
    const nota = document.getElementById('estado-nota');
    const titulo = document.getElementById('estado-titulo');
    const btn = document.getElementById('btn-submit-estado');

    document.getElementById('estado-codigo-val').value = codigo;
    document.getElementById('estado-accion-val').value = deshabilitar ? 'deshabilitar' : 'habilitar';
    document.getElementById('estado-info').innerText = codigo + ' - ' + nombre;

    // Dar de baja una herramienta pide el motivo (RF-18)
    const pideMotivo = !!(deshabilitar && esHerramienta);
    document.getElementById('estado-baja-bloque').style.display = pideMotivo ? 'block' : 'none';
    document.getElementById('estado-motivo-baja').required = pideMotivo;
    document.getElementById('estado-motivo-baja').value = '';
    document.getElementById('estado-observacion-baja').value = '';

    if (deshabilitar) {
        titulo.innerText = esHerramienta ? 'Dar de baja la herramienta' : 'Deshabilitar Insumo';
        titulo.style.color = 'var(--btn-delete)';
        document.getElementById('estado-subtitulo').innerText = '¿Está seguro de que desea dar de baja este insumo del catálogo activo?';
        caja.style.backgroundColor = '#fef2f2';
        caja.style.border = '1px solid #fecaca';
        caja.style.color = '#991b1b';
        nota.style.color = '#b91c1c';
        nota.innerText = esHerramienta
            ? 'Se registra el motivo y la fecha de la baja. Si la herramienta está prestada, primero debe devolverse. El historial se conserva.'
            : 'El registro permanecerá en el sistema para fines de auditoría pero no estará disponible para nuevos movimientos.';
        btn.style.backgroundColor = 'var(--btn-delete)';
        btn.innerText = 'Confirmar Deshabilitación';
    } else {
        titulo.innerText = 'Habilitar Insumo';
        titulo.style.color = '#166534';
        document.getElementById('estado-subtitulo').innerText = '¿Desea volver a habilitar este insumo en el catálogo activo?';
        caja.style.backgroundColor = '#f0fdf4';
        caja.style.border = '1px solid #bbf7d0';
        caja.style.color = '#166534';
        nota.style.color = '#15803d';
        nota.innerText = 'El insumo volverá a estar disponible para nuevos movimientos, con el stock que tenía al deshabilitarse.';
        btn.style.backgroundColor = '#16a34a';
        btn.innerText = 'Confirmar Habilitación';
    }
    abrirModal('modal-estado-insumo');
}

function ejecutarCambioEstadoInsumo(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const deshabilitar = formData.get('accion') === 'deshabilitar';

    fetch('deshabilitar_insumo.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(res => {
        cerrarModal('modal-estado-insumo');
        if (res.success) {
            mostrarAlerta(res.message || (deshabilitar ? 'Insumo deshabilitado exitosamente.' : 'Insumo habilitado exitosamente.'), true);
            cargarInsumos(paginaActual);
        } else {
            mostrarAlerta(res.error || 'Error al procesar la acción sobre el insumo.', false);
        }
    })
    .catch(err => {
        cerrarModal('modal-estado-insumo');
        mostrarAlerta('Error de conexión: ' + err.message, false);
    });
}

// Enlace directo desde Reportes (?editar=ID): abre el modal de edición de ese insumo.
document.addEventListener('DOMContentLoaded', function () {
    if (DATOS_PAGINA.insumoAEditar) {
        abrirModalEditar(DATOS_PAGINA.insumoAEditar);
    }
});
