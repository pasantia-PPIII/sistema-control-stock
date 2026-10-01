<?php
/**
 * movimiento/entrega.php
 * Formulario para registrar una nueva Entrega / Egreso a un operario.
 * 
 * Directivas:
 * - Sin iconos ni emojis.
 * - Selectores dinámicos para operarios activos y herramientas/materiales con stock.
 * - Envío asíncrono vía fetch() a crear_entrega.php.
 */

$seccion = 'movimiento';
$css_modulo = 'movimientos';
$titulo_vista = 'Registrar Entrega de Pañol';
$titulo_pagina = 'Registrar Entrega - Sistema DeControl';

require_once __DIR__ . '/../clases/movimiento.php';

$movimientoModel = new Movimiento();
$operarios = $movimientoModel->getOperariosParaMovimiento();
$insumos = $movimientoModel->getInsumosParaMovimiento();

$idOperarioPre = isset($_GET['id_operario']) ? (int)$_GET['id_operario'] : 0;

include_once __DIR__ . '/../layouts/header.php';
?>

<!-- Contenedor de Alertas Dinámicas -->
<div id="alerta-entrega" style="display: none; padding: 0.85rem 1.25rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.9rem; font-weight: 600;"></div>

<div class="form-card">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem;">
        <div>
            <h2 class="form-card-title">Nueva Entrega de Insumos y Herramientas</h2>
            <p class="form-card-subtitle">Registrar egreso de materiales consumibles o asignación de herramientas en custodia para personal técnico</p>
        </div>
        <a href="dashboard.php" class="btn-cancel-form">Volver al Historial</a>
    </div>

    <form id="form-entrega" onsubmit="enviarEntrega(event)">
        <!-- Cabecera del Movimiento -->
        <div class="form-grid-3">
            <div class="form-group">
                <label for="id_operario">Operario Responsable *</label>
                <select id="id_operario" name="id_operario" class="form-control" required>
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
                <label for="fecha">Fecha de Entrega *</label>
                <input type="date" id="fecha" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="form-group">
                <label for="hora">Hora *</label>
                <input type="time" id="hora" name="hora" class="form-control" value="<?= date('H:i') ?>" required>
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 2rem;">
            <label for="observaciones">Observaciones o Motivo</label>
            <textarea id="observaciones" name="observaciones" class="form-control" style="min-height: 70px; resize: vertical;" placeholder="Detalles de la entrega, orden de trabajo, destino o estado al entregar..."></textarea>
        </div>

        <!-- Sección de Selección y Agregado de Ítems -->
        <div class="items-section-header">
            <h3 class="items-section-title">Ítems a Entregar</h3>
        </div>

        <div class="form-grid-3" style="background-color: #f8fafc; padding: 1.25rem; border-radius: 8px; margin-bottom: 1.25rem; align-items: flex-end;">
            <div class="form-group" style="flex: 2;">
                <label for="selector-insumo">Seleccionar Insumo o Herramienta</label>
                <select id="selector-insumo" class="form-control">
                    <option value="">-- Buscar por código o nombre --</option>
                    <?php foreach ($insumos as $ins): 
                        $stock = (float)$ins['stock_actual'];
                        $tipo = ($ins['id_tipo'] == 2) ? 'Herramienta' : 'Material';
                        $estadoHerramienta = ($ins['id_tipo'] == 2 && !empty($ins['estado_herramienta_nombre'])) ? " [{$ins['estado_herramienta_nombre']}]" : "";
                    ?>
                        <option value="<?= htmlspecialchars($ins['id_insumo']) ?>" 
                                data-codigo="<?= htmlspecialchars($ins['codigo']) ?>"
                                data-nombre="<?= htmlspecialchars($ins['nombre']) ?>"
                                data-tipo="<?= htmlspecialchars($tipo) ?>"
                                data-unidad="<?= htmlspecialchars($ins['unidad_medida'] ?? 'unidades') ?>"
                                data-stock="<?= $stock ?>"
                                <?= ($stock <= 0) ? 'disabled' : '' ?>>
                            <?= htmlspecialchars("{$ins['codigo']} - {$ins['nombre']} ({$tipo}) - Stock: {$stock} {$ins['unidad_medida']}{$estadoHerramienta}") ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="flex: 1;">
                <label for="input-cantidad">Cantidad a Entregar</label>
                <input type="number" id="input-cantidad" class="form-control" min="1" step="any" value="1" placeholder="Ej: 1">
            </div>

            <div class="form-group" style="flex: 0 0 auto;">
                <button type="button" class="btn-add-row" onclick="agregarItem()">Agregar a la Lista</button>
            </div>
        </div>

        <!-- Tabla de Ítems Agregados -->
        <table class="items-table" id="tabla-items">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Insumo / Herramienta</th>
                    <th>Tipo</th>
                    <th>Stock en Pañol</th>
                    <th style="width: 140px;">Cantidad</th>
                    <th style="width: 90px; text-align: center;">Acción</th>
                </tr>
            </thead>
            <tbody id="tbody-items">
                <tr id="fila-vacia">
                    <td colspan="6" style="text-align: center; color: #64748b; padding: 2rem;">No se han agregado ítems a la entrega todavía. Seleccione un insumo arriba para comenzar.</td>
                </tr>
            </tbody>
        </table>

        <!-- Botones de Acción -->
        <div class="form-actions-bottom">
            <a href="dashboard.php" class="btn-cancel-form">Cancelar</a>
            <button type="submit" class="btn-submit-form" id="btn-submit-entrega">Registrar Entrega</button>
        </div>
    </form>
</div>

<script>
// Array en memoria con los ítems agregados
let itemsEntrega = [];

function mostrarAlerta(mensaje, esExito = true) {
    const box = document.getElementById('alerta-entrega');
    if (!box) return;
    box.style.display = 'block';
    box.style.backgroundColor = esExito ? '#dcfce7' : '#fee2e2';
    box.style.color = esExito ? '#166534' : '#991b1b';
    box.style.border = esExito ? '1px solid #bbf7d0' : '1px solid #fecaca';
    box.innerText = mensaje;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function agregarItem() {
    const select = document.getElementById('selector-insumo');
    const inputCant = document.getElementById('input-cantidad');

    const idInsumo = parseInt(select.value, 10);
    const cantidad = parseFloat(inputCant.value);

    if (!idInsumo || isNaN(idInsumo)) {
        alert('Por favor, seleccione un insumo o herramienta de la lista.');
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
    const unidad = opt.getAttribute('data-unidad');
    const stock = parseFloat(opt.getAttribute('data-stock'));

    // Verificar si ya existe en la lista
    const indexExistente = itemsEntrega.findIndex(it => it.id_insumo === idInsumo);
    const cantidadTotal = (indexExistente >= 0 ? itemsEntrega[indexExistente].cantidad : 0) + cantidad;

    if (cantidadTotal > stock) {
        alert(`Stock insuficiente para "${nombre}". Stock disponible: ${stock}, total solicitado: ${cantidadTotal}.`);
        return;
    }

    if (indexExistente >= 0) {
        itemsEntrega[indexExistente].cantidad = cantidadTotal;
    } else {
        itemsEntrega.push({
            id_insumo: idInsumo,
            codigo: codigo,
            nombre: nombre,
            tipo: tipo,
            unidad: unidad,
            stock: stock,
            cantidad: cantidad
        });
    }

    renderizarTablaItems();
    select.value = '';
    inputCant.value = '1';
}

function quitarItem(idInsumo) {
    itemsEntrega = itemsEntrega.filter(it => it.id_insumo !== idInsumo);
    renderizarTablaItems();
}

function actualizarCantidadItem(idInsumo, valor) {
    const cant = parseFloat(valor);
    const item = itemsEntrega.find(it => it.id_insumo === idInsumo);
    if (!item) return;

    if (isNaN(cant) || cant <= 0) {
        alert('La cantidad debe ser mayor a cero.');
        renderizarTablaItems();
        return;
    }

    if (cant > item.stock) {
        alert(`Stock insuficiente para "${item.nombre}". Stock disponible: ${item.stock}.`);
        renderizarTablaItems();
        return;
    }

    item.cantidad = cant;
}

function renderizarTablaItems() {
    const tbody = document.getElementById('tbody-items');
    if (!tbody) return;

    if (itemsEntrega.length === 0) {
        tbody.innerHTML = `
            <tr id="fila-vacia">
                <td colspan="6" style="text-align: center; color: #64748b; padding: 2rem;">No se han agregado ítems a la entrega todavía. Seleccione un insumo arriba para comenzar.</td>
            </tr>`;
        return;
    }

    let html = '';
    itemsEntrega.forEach(it => {
        html += `
            <tr>
                <td style="font-weight: 700;">${escaparHtml(it.codigo)}</td>
                <td>${escaparHtml(it.nombre)}</td>
                <td><span class="badge ${it.tipo === 'Herramienta' ? 'badge-info' : 'badge-neutral'}">${escaparHtml(it.tipo)}</span></td>
                <td>${it.stock} ${escaparHtml(it.unidad)}</td>
                <td>
                    <input type="number" class="form-control" style="padding: 0.35rem 0.5rem;" min="1" max="${it.stock}" step="any" value="${it.cantidad}" onchange="actualizarCantidadItem(${it.id_insumo}, this.value)">
                </td>
                <td style="text-align: center;">
                    <button type="button" class="btn-remove-row" onclick="quitarItem(${it.id_insumo})">Quitar</button>
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

function enviarEntrega(e) {
    e.preventDefault();

    const idOperario = document.getElementById('id_operario').value;
    if (!idOperario) {
        alert('Debe seleccionar un operario responsable.');
        return;
    }

    if (itemsEntrega.length === 0) {
        alert('Debe agregar al menos un insumo o herramienta a la lista de entrega.');
        return;
    }

    const btnSubmit = document.getElementById('btn-submit-entrega');
    btnSubmit.disabled = true;
    btnSubmit.innerText = 'Procesando Entrega...';

    const formData = new FormData(document.getElementById('form-entrega'));
    formData.append('items', JSON.stringify(itemsEntrega));

    fetch('crear_entrega.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) throw new Error('Error al conectar con el servidor.');
        return response.json();
    })
    .then(res => {
        if (!res.success) throw new Error(res.error || 'No se pudo registrar la entrega.');
        mostrarAlerta(res.message, true);
        setTimeout(() => {
            window.location.href = `ver_detalle.php?id=${res.id_movimiento}`;
        }, 1200);
    })
    .catch(err => {
        mostrarAlerta(err.message, false);
        btnSubmit.disabled = false;
        btnSubmit.innerText = 'Registrar Entrega';
    });
}
</script>

<?php
include_once __DIR__ . '/../layouts/footer.php';
?>
