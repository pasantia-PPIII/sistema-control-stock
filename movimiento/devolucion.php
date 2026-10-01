<?php
/**
 * movimiento/devolucion.php
 * Formulario para registrar una Devolución de herramientas y materiales de un operario.
 * 
 * Directivas:
 * - Sin iconos ni emojis.
 * - Carga dinámica de herramientas en custodia al seleccionar operario.
 * - Selector para estado de reintegro (Disponible, En Reparación, Baja).
 * - Envío asíncrono vía fetch() a crear_devolucion.php.
 */

require_once __DIR__ . '/../clases/movimiento.php';

$movimientoModel = new Movimiento();

// Manejador AJAX para obtener herramientas en custodia de un operario específico
if (isset($_GET['action']) && $_GET['action'] === 'prestadas' && !empty($_GET['id_operario'])) {
    header('Content-Type: application/json; charset=utf-8');
    $idOp = (int)$_GET['id_operario'];
    $prestadas = $movimientoModel->getHerramientasPrestadasOperario($idOp);
    echo json_encode([
        'success' => true,
        'data'    => $prestadas
    ]);
    exit;
}

$seccion = 'movimiento';
$css_modulo = 'movimientos';
$titulo_vista = 'Registrar Devolución de Pañol';
$titulo_pagina = 'Registrar Devolución - Sistema DeControl';

$operarios = $movimientoModel->getOperariosParaMovimiento();
$insumos = $movimientoModel->getInsumosParaMovimiento();
$estadosHerramienta = $movimientoModel->getEstadosHerramienta();

$idOperarioPre = isset($_GET['id_operario']) ? (int)$_GET['id_operario'] : 0;

include_once __DIR__ . '/../layouts/header.php';
?>

<!-- Contenedor de Alertas Dinámicas -->
<div id="alerta-devolucion" style="display: none; padding: 0.85rem 1.25rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.9rem; font-weight: 600;"></div>

<div class="form-card">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem;">
        <div>
            <h2 class="form-card-title">Registrar Devolución de Herramientas y Materiales</h2>
            <p class="form-card-subtitle">Reintegro de herramientas en custodia al pañol y devolución de materiales sobrantes</p>
        </div>
        <a href="dashboard.php" class="btn-cancel-form">Volver al Historial</a>
    </div>

    <form id="form-devolucion" onsubmit="enviarDevolucion(event)">
        <!-- Cabecera del Movimiento -->
        <div class="form-grid-3">
            <div class="form-group">
                <label for="id_operario">Operario que Devuelve *</label>
                <select id="id_operario" name="id_operario" class="form-control" onchange="cargarPrestadasOperario(this.value)" required>
                    <option value="">-- Seleccionar Operario --</option>
                    <?php foreach ($operarios as $op): 
                        $rubroTxt = !empty($op['nombre_rubro']) ? " ({$op['nombre_rubro']})" : "";
                        $selected = ($idOperarioPre === (int)$op['id_operario']) ? 'selected' : '';
                    ?>
                        <option value="<?= htmlspecialchars($op['id_operario']) ?>" <?= $selected ?>>
                            <?= htmlspecialchars($op['apellido'] . ', ' . $op['nombre'] . ' - DNI: ' . $op['dni'] . $rubroTxt) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="fecha">Fecha de Devolución *</label>
                <input type="date" id="fecha" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="form-group">
                <label for="hora">Hora *</label>
                <input type="time" id="hora" name="hora" class="form-control" value="<?= date('H:i') ?>" required>
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 2rem;">
            <label for="observaciones">Observaciones o Estado de Devolución</label>
            <textarea id="observaciones" name="observaciones" class="form-control" style="min-height: 70px; resize: vertical;" placeholder="Comentarios sobre el estado general de reintegro o motivo de devolución..."></textarea>
        </div>

        <!-- Panel de Herramientas en Custodia del Operario Seleccionado -->
        <div id="contenedor-herramientas-prestadas" style="display: none; margin-bottom: 2rem;">
            <div class="items-section-header">
                <h3 class="items-section-title" id="titulo-prestadas">Herramientas en Custodia de Este Operario</h3>
            </div>
            <div id="lista-prestadas" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem;">
                <!-- Dinámico -->
            </div>
        </div>

        <!-- Sección de Selección y Agregado Manual de Ítems a Devolver -->
        <div class="items-section-header">
            <h3 class="items-section-title">Ítems a Devolver</h3>
        </div>

        <div class="form-grid-3" style="background-color: #f8fafc; padding: 1.25rem; border-radius: 8px; margin-bottom: 1.25rem; align-items: flex-end;">
            <div class="form-group" style="flex: 2;">
                <label for="selector-insumo">Seleccionar Insumo o Herramienta</label>
                <select id="selector-insumo" class="form-control">
                    <option value="">-- Buscar por código o nombre --</option>
                    <?php foreach ($insumos as $ins): 
                        $tipo = ($ins['id_tipo'] == 2) ? 'Herramienta' : 'Material';
                    ?>
                        <option value="<?= htmlspecialchars($ins['id_insumo']) ?>" 
                                data-codigo="<?= htmlspecialchars($ins['codigo']) ?>"
                                data-nombre="<?= htmlspecialchars($ins['nombre']) ?>"
                                data-tipo="<?= htmlspecialchars($tipo) ?>"
                                data-unidad="<?= htmlspecialchars($ins['unidad_medida'] ?? 'unidades') ?>"
                                data-es-herramienta="<?= ($ins['id_tipo'] == 2) ? '1' : '0' ?>">
                            <?= htmlspecialchars("{$ins['codigo']} - {$ins['nombre']} ({$tipo})") ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="flex: 1;">
                <label for="input-cantidad">Cantidad</label>
                <input type="number" id="input-cantidad" class="form-control" min="1" step="any" value="1">
            </div>

            <div class="form-group" style="flex: 1.2;">
                <label for="input-estado">Estado de Ingreso</label>
                <select id="input-estado" class="form-control">
                    <option value="1">Disponible (Buen Estado)</option>
                    <option value="3">En Reparación</option>
                    <option value="4">Baja (Dañada / Inservible)</option>
                </select>
            </div>

            <div class="form-group" style="flex: 0 0 auto;">
                <button type="button" class="btn-add-row" onclick="agregarItemManual()">Agregar a Lista</button>
            </div>
        </div>

        <!-- Tabla de Ítems a Devolver -->
        <table class="items-table" id="tabla-items-devolucion">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Insumo / Herramienta</th>
                    <th>Tipo</th>
                    <th style="width: 130px;">Cantidad</th>
                    <th style="width: 180px;">Estado al Ingresar</th>
                    <th style="width: 90px; text-align: center;">Acción</th>
                </tr>
            </thead>
            <tbody id="tbody-items-devolucion">
                <tr id="fila-vacia-devolucion">
                    <td colspan="6" style="text-align: center; color: #64748b; padding: 2rem;">No se han agregado ítems a devolver todavía. Seleccione un operario o agregue ítems desde el catálogo.</td>
                </tr>
            </tbody>
        </table>

        <!-- Botones de Acción -->
        <div class="form-actions-bottom">
            <a href="dashboard.php" class="btn-cancel-form">Cancelar</a>
            <button type="submit" class="btn-submit-form" id="btn-submit-devolucion">Registrar Devolución</button>
        </div>
    </form>
</div>

<script>
let itemsDevolucion = [];

function mostrarAlerta(mensaje, esExito = true) {
    const box = document.getElementById('alerta-devolucion');
    if (!box) return;
    box.style.display = 'block';
    box.style.backgroundColor = esExito ? '#dcfce7' : '#fee2e2';
    box.style.color = esExito ? '#166534' : '#991b1b';
    box.style.border = esExito ? '1px solid #bbf7d0' : '1px solid #fecaca';
    box.innerText = mensaje;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cargarPrestadasOperario(idOperario) {
    const contenedor = document.getElementById('contenedor-herramientas-prestadas');
    const lista = document.getElementById('lista-prestadas');

    if (!idOperario) {
        contenedor.style.display = 'none';
        lista.innerHTML = '';
        return;
    }

    lista.innerHTML = '<p style="color: #64748b;">Consultando herramientas en custodia...</p>';
    contenedor.style.display = 'block';

    fetch(`devolucion.php?action=prestadas&id_operario=${idOperario}`, { credentials: 'same-origin' })
        .then(res => res.json())
        .then(res => {
            if (!res.success || !res.data || res.data.length === 0) {
                lista.innerHTML = '<p style="color: #64748b; font-size: 0.88rem;">Este operario no registra herramientas en custodia actualmente.</p>';
                return;
            }

            let html = '<div style="display: flex; flex-direction: column; gap: 0.6rem;">';
            res.data.forEach(h => {
                const nombreEscapado = escaparHtml(h.nombre);
                const codigoEscapado = escaparHtml(h.codigo);
                const cant = parseFloat(h.cantidad || 1);
                
                html += `
                    <div style="display: flex; justify-content: space-between; align-items: center; background-color: #ffffff; padding: 0.65rem 1rem; border-radius: 6px; border: 1px solid #e2e8f0;">
                        <div>
                            <strong>${codigoEscapado} - ${nombreEscapado}</strong>
                            <span style="color: #64748b; font-size: 0.8rem; margin-left: 0.5rem;">(${cant} unidad en custodia desde ${h.fecha})</span>
                        </div>
                        <button type="button" class="btn-action-text view" onclick="agregarPrestada(${h.id_insumo}, '${codigoEscapado}', '${nombreEscapado}', ${cant})">
                            Devolver Esta Herramienta
                        </button>
                    </div>`;
            });
            html += '</div>';
            lista.innerHTML = html;
        })
        .catch(err => {
            lista.innerHTML = `<p style="color: #dc2626;">Error al consultar herramientas en custodia: ${escaparHtml(err.message)}</p>`;
        });
}

function agregarPrestada(idInsumo, codigo, nombre, cantidad) {
    const yaExiste = itemsDevolucion.some(it => it.id_insumo === idInsumo);
    if (yaExiste) {
        alert('Esta herramienta ya fue agregada a la lista de devolución.');
        return;
    }

    itemsDevolucion.push({
        id_insumo: idInsumo,
        codigo: codigo,
        nombre: nombre,
        tipo: 'Herramienta',
        cantidad: cantidad,
        id_estado_herramienta: 1 // Por defecto Disponible
    });

    renderizarTablaDevolucion();
}

function agregarItemManual() {
    const select = document.getElementById('selector-insumo');
    const inputCant = document.getElementById('input-cantidad');
    const inputEstado = document.getElementById('input-estado');

    const idInsumo = parseInt(select.value, 10);
    const cantidad = parseFloat(inputCant.value);
    const estadoHerramienta = parseInt(inputEstado.value, 10);

    if (!idInsumo || isNaN(idInsumo)) {
        alert('Seleccione un insumo o herramienta de la lista.');
        return;
    }

    if (isNaN(cantidad) || cantidad <= 0) {
        alert('Ingrese una cantidad válida mayor a cero.');
        return;
    }

    const opt = select.options[select.selectedIndex];
    const codigo = opt.getAttribute('data-codigo');
    const nombre = opt.getAttribute('data-nombre');
    const tipo = opt.getAttribute('data-tipo');

    const indexExistente = itemsDevolucion.findIndex(it => it.id_insumo === idInsumo);
    if (indexExistente >= 0) {
        itemsDevolucion[indexExistente].cantidad += cantidad;
        itemsDevolucion[indexExistente].id_estado_herramienta = estadoHerramienta;
    } else {
        itemsDevolucion.push({
            id_insumo: idInsumo,
            codigo: codigo,
            nombre: nombre,
            tipo: tipo,
            cantidad: cantidad,
            id_estado_herramienta: estadoHerramienta
        });
    }

    renderizarTablaDevolucion();
    select.value = '';
    inputCant.value = '1';
}

function quitarItemDevolucion(idInsumo) {
    itemsDevolucion = itemsDevolucion.filter(it => it.id_insumo !== idInsumo);
    renderizarTablaDevolucion();
}

function actualizarCantidadDevolucion(idInsumo, valor) {
    const cant = parseFloat(valor);
    const item = itemsDevolucion.find(it => it.id_insumo === idInsumo);
    if (!item) return;

    if (isNaN(cant) || cant <= 0) {
        alert('La cantidad debe ser mayor a cero.');
        renderizarTablaDevolucion();
        return;
    }

    item.cantidad = cant;
}

function actualizarEstadoDevolucion(idInsumo, estado) {
    const item = itemsDevolucion.find(it => it.id_insumo === idInsumo);
    if (item) {
        item.id_estado_herramienta = parseInt(estado, 10);
    }
}

function renderizarTablaDevolucion() {
    const tbody = document.getElementById('tbody-items-devolucion');
    if (!tbody) return;

    if (itemsDevolucion.length === 0) {
        tbody.innerHTML = `
            <tr id="fila-vacia-devolucion">
                <td colspan="6" style="text-align: center; color: #64748b; padding: 2rem;">No se han agregado ítems a devolver todavía. Seleccione un operario o agregue ítems desde el catálogo.</td>
            </tr>`;
        return;
    }

    let html = '';
    itemsDevolucion.forEach(it => {
        const esHerramienta = (it.tipo === 'Herramienta');
        const selectorEstado = esHerramienta ? `
            <select class="form-control" style="padding: 0.35rem 0.5rem;" onchange="actualizarEstadoDevolucion(${it.id_insumo}, this.value)">
                <option value="1" ${it.id_estado_herramienta === 1 ? 'selected' : ''}>Disponible</option>
                <option value="3" ${it.id_estado_herramienta === 3 ? 'selected' : ''}>En Reparación</option>
                <option value="4" ${it.id_estado_herramienta === 4 ? 'selected' : ''}>Baja</option>
            </select>` : `<span class="badge badge-neutral">No aplica</span>`;

        html += `
            <tr>
                <td style="font-weight: 700;">${escaparHtml(it.codigo)}</td>
                <td>${escaparHtml(it.nombre)}</td>
                <td><span class="badge ${esHerramienta ? 'badge-info' : 'badge-neutral'}">${escaparHtml(it.tipo)}</span></td>
                <td>
                    <input type="number" class="form-control" style="padding: 0.35rem 0.5rem;" min="1" step="any" value="${it.cantidad}" onchange="actualizarCantidadDevolucion(${it.id_insumo}, this.value)">
                </td>
                <td>${selectorEstado}</td>
                <td style="text-align: center;">
                    <button type="button" class="btn-remove-row" onclick="quitarItemDevolucion(${it.id_insumo})">Quitar</button>
                </td>
            </tr>`;
    });

    tbody.innerHTML = html;
}

function escaparHtml(texto) {
    if (!texto) return '';
    const div = document.createElement('div');
    div.textContent = texto;
    return div.innerHTML;
}

function enviarDevolucion(e) {
    e.preventDefault();

    const idOperario = document.getElementById('id_operario').value;
    if (!idOperario) {
        alert('Debe seleccionar el operario que realiza la devolución.');
        return;
    }

    if (itemsDevolucion.length === 0) {
        alert('Debe agregar al menos un ítem a la lista de devolución.');
        return;
    }

    const btnSubmit = document.getElementById('btn-submit-devolucion');
    btnSubmit.disabled = true;
    btnSubmit.innerText = 'Procesando Devolución...';

    const formData = new FormData(document.getElementById('form-devolucion'));
    formData.append('items', JSON.stringify(itemsDevolucion));

    fetch('crear_devolucion.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) throw new Error('Error al conectar con el servidor.');
        return response.json();
    })
    .then(res => {
        if (!res.success) throw new Error(res.error || 'No se pudo registrar la devolución.');
        mostrarAlerta(res.message, true);
        setTimeout(() => {
            window.location.href = `ver_detalle.php?id=${res.id_movimiento}`;
        }, 1200);
    })
    .catch(err => {
        mostrarAlerta(err.message, false);
        btnSubmit.disabled = false;
        btnSubmit.innerText = 'Registrar Devolución';
    });
}

// Carga inicial si viene un operario preseleccionado
document.addEventListener('DOMContentLoaded', () => {
    const selectOp = document.getElementById('id_operario');
    if (selectOp && selectOp.value) {
        cargarPrestadasOperario(selectOp.value);
    }
});
</script>

<?php
include_once __DIR__ . '/../layouts/footer.php';
?>
