<?php
/**
 * movimiento/crear_movimiento.php
 * Registro de movimientos de pañol: Entrega (egreso a un operario) y Devolución.
 *
 * - GET  ?tipo=entrega|devolucion : muestra el formulario correspondiente.
 * - POST (fetch, JSON)            : registra el movimiento con Movimiento::create().
 *
 * Datos mediante las clases Movimiento, Operario e Insumo.
 */

require_once __DIR__ . '/../config/config.php';

$esPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

if (!isAuthenticated()) {
    if ($esPost) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Sesión no iniciada. Inicie sesión nuevamente.']);
        exit;
    }
    redirect('auth/login.php');
}
requireRole(['admin', 'panolero']);

require_once __DIR__ . '/../clases/movimiento.php';
require_once __DIR__ . '/../clases/operario.php';
require_once __DIR__ . '/../clases/insumo.php';

// Tipos de movimiento (ids del catálogo tipo_movimiento): 1 = Entrega, 2 = Devolución
$tipoForm = (($_POST['tipo'] ?? $_GET['tipo'] ?? 'entrega') === 'devolucion') ? 'devolucion' : 'entrega';
$esDevolucion = ($tipoForm === 'devolucion');
$idTipoMov = $esDevolucion ? 2 : 1;

$movimientoModel = new Movimiento();
$operarioModel = new Operario();
$insumoModel = new Insumo();

// =========================================================================
// PROCESAMIENTO DEL REGISTRO (POST, respuesta JSON)
// =========================================================================
if ($esPost) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $idOperario = !empty($_POST['id_operario']) ? (int)$_POST['id_operario'] : 0;
        $fecha = !empty($_POST['fecha']) ? trim($_POST['fecha']) : date('Y-m-d');
        $hora = !empty($_POST['hora']) ? trim($_POST['hora']) : date('H:i:s');
        $observaciones = isset($_POST['observaciones']) ? trim((string)$_POST['observaciones']) : '';

        if ($idOperario <= 0) {
            throw new Exception($esDevolucion
                ? 'Debe seleccionar el operario que realiza la devolución.'
                : 'Debe seleccionar un operario responsable para la entrega.');
        }

        $operario = $operarioModel->getById($idOperario);
        if (!$operario || !$operario['activo']) {
            throw new Exception('El operario seleccionado no existe o no se encuentra activo.');
        }

        $items = [];
        if (isset($_POST['items'])) {
            $decoded = is_string($_POST['items']) ? json_decode($_POST['items'], true) : $_POST['items'];
            $items = is_array($decoded) ? $decoded : [];
        }
        if (empty($items)) {
            throw new Exception('Debe agregar al menos un ítem al movimiento.');
        }

        $detalles = [];
        foreach ($items as $item) {
            $codigo = isset($item['id_insumo']) ? trim((string)$item['id_insumo']) : '';
            $cantidad = isset($item['cantidad']) ? (float)$item['cantidad'] : 0;

            if ($codigo === '') {
                throw new Exception('Se detectó un ítem con identificación de insumo inválida.');
            }
            if ($cantidad <= 0) {
                throw new Exception('La cantidad de cada ítem debe ser mayor a cero.');
            }

            $insumo = $insumoModel->getByCodigo($codigo);
            if (!$insumo) {
                throw new Exception("El insumo '{$codigo}' no existe o está deshabilitado.");
            }
            if (!$esDevolucion && $cantidad > (float)$insumo['stock_actual']) {
                throw new Exception("Stock insuficiente para '{$insumo['nombre']}'. Disponible: {$insumo['stock_actual']}.");
            }

            // El estado de herramienta solo aplica a herramientas (tipo 2)
            $idEstado = null;
            if ((int)$insumo['id_tipo'] === 2) {
                // Entrega: 2 = Prestada. Devolución: el estado con el que vuelve (por defecto 1 = Disponible)
                $idEstado = $esDevolucion
                    ? (!empty($item['id_estado_herramienta']) ? (int)$item['id_estado_herramienta'] : 1)
                    : 2;
            }

            $detalles[] = [
                'id_insumo'             => (int)$insumo['id_insumo'],
                'cantidad'              => $cantidad,
                'id_estado_herramienta' => $idEstado
            ];
        }

        $cabecera = [
            'id_tipo_mov'   => $idTipoMov,
            'dni_usuario'   => $_SESSION['dni_usuario'],
            'id_operario'   => (int)$operario['id_operario'],
            'fecha'         => $fecha,
            'hora'          => $hora,
            'observaciones' => $observaciones
        ];

        $idMovimiento = $movimientoModel->create($cabecera, $detalles);

        echo json_encode([
            'success'       => true,
            'message'       => ($esDevolucion ? 'Devolución' : 'Entrega') . " #{$idMovimiento} registrada exitosamente para {$operario['nombre']} {$operario['apellido']}.",
            'id_movimiento' => $idMovimiento
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => safeErrorMessage($e)]);
    }
    exit;
}

// =========================================================================
// FORMULARIO (GET)
// =========================================================================
$seccion = 'movimiento';
$css_modulo = 'movimientos';
$titulo_vista = $esDevolucion ? 'Registrar Devolución de Pañol' : 'Registrar Entrega de Pañol';
$titulo_pagina = ($esDevolucion ? 'Registrar Devolución' : 'Registrar Entrega') . ' - Sistema de Control de Stock';

$operarios = $operarioModel->getParaSelect();
$insumos = $insumoModel->getAll();
$estadosHerramienta = $movimientoModel->getEstadosHerramienta();

$idOperarioPre = !empty($_GET['id_operario']) ? (int)$_GET['id_operario'] : 0;

include_once __DIR__ . '/../layout/header.php';
?>

<!-- Contenedor de Alertas Dinámicas -->
<div id="alerta-movimiento" style="display: none; padding: 0.85rem 1.25rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.9rem; font-weight: 600;"></div>

<div class="form-card">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem;">
        <div>
            <?php if ($esDevolucion): ?>
                <h2 class="form-card-title">Nueva Devolución de Insumos y Herramientas</h2>
                <p class="form-card-subtitle">Registrar el reintegro al pañol de materiales y herramientas en custodia del personal técnico</p>
            <?php else: ?>
                <h2 class="form-card-title">Nueva Entrega de Insumos y Herramientas</h2>
                <p class="form-card-subtitle">Registrar egreso de materiales consumibles o asignación de herramientas en custodia para personal técnico</p>
            <?php endif; ?>
        </div>
        <a href="dashboard.php" class="btn-cancel-form">Volver al Historial</a>
    </div>

    <form id="form-movimiento" onsubmit="enviarMovimiento(event)">
        <input type="hidden" name="tipo" value="<?= $tipoForm ?>">

        <!-- Cabecera del Movimiento -->
        <div class="form-grid-3">
            <div class="form-group">
                <label for="id_operario">Operario <?= $esDevolucion ? 'que Devuelve' : 'Responsable' ?> *</label>
                <select id="id_operario" name="id_operario" class="form-control" required>
                    <option value="">-- Seleccionar Operario --</option>
                    <?php foreach ($operarios as $op): ?>
                        <option value="<?= (int)$op['id_operario'] ?>" <?= ($idOperarioPre === (int)$op['id_operario']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($op['apellido'] . ', ' . $op['nombre'] . ' - DNI: ' . $op['dni']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="fecha">Fecha de <?= $esDevolucion ? 'Devolución' : 'Entrega' ?> *</label>
                <input type="date" id="fecha" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="form-group">
                <label for="hora">Hora *</label>
                <input type="time" id="hora" name="hora" class="form-control" value="<?= date('H:i') ?>" required>
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 2rem;">
            <label for="observaciones">Observaciones o Motivo</label>
            <textarea id="observaciones" name="observaciones" class="form-control" style="min-height: 70px; resize: vertical;" placeholder="<?= $esDevolucion ? 'Detalles de la devolución, estado de los elementos, orden de trabajo...' : 'Detalles de la entrega, orden de trabajo, destino o estado al entregar...' ?>"></textarea>
        </div>

        <!-- Sección de Selección y Agregado de Ítems -->
        <div class="items-section-header">
            <h3 class="items-section-title">Ítems a <?= $esDevolucion ? 'Devolver' : 'Entregar' ?></h3>
        </div>

        <div class="form-grid-3" style="background-color: #f8fafc; padding: 1.25rem; border-radius: 8px; margin-bottom: 1.25rem; align-items: flex-end;">
            <div class="form-group" style="flex: 2;">
                <label for="selector-insumo">Seleccionar Insumo o Herramienta</label>
                <select id="selector-insumo" class="form-control">
                    <option value="">-- Buscar por código o nombre --</option>
                    <?php foreach ($insumos as $ins):
                        $stock = (float)($ins['stock_actual'] ?? 0);
                        $esHerramienta = ((int)$ins['id_tipo'] === 2);
                        $tipo = $esHerramienta ? 'Herramienta' : 'Material';
                        $unidad = $ins['unidad_medida'] ?? 'unidades';
                    ?>
                        <option value="<?= htmlspecialchars($ins['codigo']) ?>"
                                data-nombre="<?= htmlspecialchars($ins['nombre']) ?>"
                                data-tipo="<?= $tipo ?>"
                                data-unidad="<?= htmlspecialchars($unidad) ?>"
                                data-stock="<?= $stock ?>"
                                <?= (!$esDevolucion && $stock <= 0) ? 'disabled' : '' ?>>
                            <?= htmlspecialchars("{$ins['codigo']} - {$ins['nombre']} ({$tipo}) - Stock: {$stock} {$unidad}") ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="flex: 1;">
                <label for="input-cantidad">Cantidad a <?= $esDevolucion ? 'Devolver' : 'Entregar' ?></label>
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
                    <?php if ($esDevolucion): ?>
                        <th style="width: 170px;">Estado al Devolver</th>
                    <?php endif; ?>
                    <th style="width: 90px; text-align: center;">Acción</th>
                </tr>
            </thead>
            <tbody id="tbody-items">
                <tr id="fila-vacia">
                    <td colspan="<?= $esDevolucion ? 7 : 6 ?>" style="text-align: center; color: #64748b; padding: 2rem;">No se han agregado ítems todavía. Seleccione un insumo arriba para comenzar.</td>
                </tr>
            </tbody>
        </table>

        <!-- Botones de Acción -->
        <div class="form-actions-bottom">
            <a href="dashboard.php" class="btn-cancel-form">Cancelar</a>
            <button type="submit" class="btn-submit-form" id="btn-submit-movimiento">Registrar <?= $esDevolucion ? 'Devolución' : 'Entrega' ?></button>
        </div>
    </form>
</div>

<script>
const ES_DEVOLUCION = <?= $esDevolucion ? 'true' : 'false' ?>;
const ESTADOS_HERRAMIENTA = <?= json_encode($estadosHerramienta, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const COLUMNAS_TABLA = ES_DEVOLUCION ? 7 : 6;

// Ítems agregados en memoria (id_insumo = código del insumo)
let itemsMovimiento = [];

function mostrarAlerta(mensaje, esExito = true) {
    const box = document.getElementById('alerta-movimiento');
    if (!box) return;
    box.style.display = 'block';
    box.style.backgroundColor = esExito ? '#dcfce7' : '#fee2e2';
    box.style.color = esExito ? '#166534' : '#991b1b';
    box.style.border = esExito ? '1px solid #bbf7d0' : '1px solid #fecaca';
    box.innerText = mensaje;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function escaparHtml(texto) {
    if (texto === null || texto === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(texto);
    return div.innerHTML;
}

function agregarItem() {
    const select = document.getElementById('selector-insumo');
    const inputCant = document.getElementById('input-cantidad');

    const codigo = select.value;
    const cantidad = parseFloat(inputCant.value);

    if (!codigo) {
        alert('Por favor, seleccione un insumo o herramienta de la lista.');
        return;
    }

    if (isNaN(cantidad) || cantidad <= 0) {
        alert('Ingrese una cantidad válida mayor a cero.');
        return;
    }

    const opt = select.options[select.selectedIndex];
    const nombre = opt.getAttribute('data-nombre');
    const tipo = opt.getAttribute('data-tipo');
    const unidad = opt.getAttribute('data-unidad');
    const stock = parseFloat(opt.getAttribute('data-stock'));

    const indexExistente = itemsMovimiento.findIndex(it => it.id_insumo === codigo);
    const cantidadTotal = (indexExistente >= 0 ? itemsMovimiento[indexExistente].cantidad : 0) + cantidad;

    if (!ES_DEVOLUCION && cantidadTotal > stock) {
        alert(`Stock insuficiente para "${nombre}". Stock disponible: ${stock}, total solicitado: ${cantidadTotal}.`);
        return;
    }

    if (indexExistente >= 0) {
        itemsMovimiento[indexExistente].cantidad = cantidadTotal;
    } else {
        itemsMovimiento.push({
            id_insumo: codigo,
            nombre: nombre,
            tipo: tipo,
            unidad: unidad,
            stock: stock,
            cantidad: cantidad,
            id_estado_herramienta: 1
        });
    }

    renderizarTablaItems();
    select.value = '';
    inputCant.value = '1';
}

function quitarItem(codigo) {
    itemsMovimiento = itemsMovimiento.filter(it => it.id_insumo !== codigo);
    renderizarTablaItems();
}

function actualizarCantidadItem(codigo, valor) {
    const cant = parseFloat(valor);
    const item = itemsMovimiento.find(it => it.id_insumo === codigo);
    if (!item) return;

    if (isNaN(cant) || cant <= 0) {
        alert('La cantidad debe ser mayor a cero.');
        renderizarTablaItems();
        return;
    }

    if (!ES_DEVOLUCION && cant > item.stock) {
        alert(`Stock insuficiente para "${item.nombre}". Stock disponible: ${item.stock}.`);
        renderizarTablaItems();
        return;
    }

    item.cantidad = cant;
}

function actualizarEstadoItem(codigo, valor) {
    const item = itemsMovimiento.find(it => it.id_insumo === codigo);
    if (item) item.id_estado_herramienta = parseInt(valor, 10);
}

function renderizarTablaItems() {
    const tbody = document.getElementById('tbody-items');
    if (!tbody) return;

    if (itemsMovimiento.length === 0) {
        tbody.innerHTML = `
            <tr id="fila-vacia">
                <td colspan="${COLUMNAS_TABLA}" style="text-align: center; color: #64748b; padding: 2rem;">No se han agregado ítems todavía. Seleccione un insumo arriba para comenzar.</td>
            </tr>`;
        return;
    }

    let html = '';
    itemsMovimiento.forEach(it => {
        const codigoAttr = escaparHtml(it.id_insumo).replace(/'/g, '&#39;');
        const maxAttr = ES_DEVOLUCION ? '' : `max="${it.stock}"`;

        let celdaEstado = '';
        if (ES_DEVOLUCION) {
            if (it.tipo === 'Herramienta') {
                const opciones = ESTADOS_HERRAMIENTA.map(e =>
                    `<option value="${e.id_estado_herramienta}" ${parseInt(e.id_estado_herramienta, 10) === it.id_estado_herramienta ? 'selected' : ''}>${escaparHtml(e.estado)}</option>`
                ).join('');
                celdaEstado = `<td><select class="form-control" style="padding: 0.35rem 0.5rem;" onchange="actualizarEstadoItem('${codigoAttr}', this.value)">${opciones}</select></td>`;
            } else {
                celdaEstado = '<td>-</td>';
            }
        }

        html += `
            <tr>
                <td style="font-weight: 700;">${escaparHtml(it.id_insumo)}</td>
                <td>${escaparHtml(it.nombre)}</td>
                <td><span class="badge ${it.tipo === 'Herramienta' ? 'badge-info' : 'badge-neutral'}">${escaparHtml(it.tipo)}</span></td>
                <td>${it.stock} ${escaparHtml(it.unidad)}</td>
                <td>
                    <input type="number" class="form-control" style="padding: 0.35rem 0.5rem;" min="1" ${maxAttr} step="any" value="${it.cantidad}" onchange="actualizarCantidadItem('${codigoAttr}', this.value)">
                </td>
                ${celdaEstado}
                <td style="text-align: center;">
                    <button type="button" class="btn-remove-row" onclick="quitarItem('${codigoAttr}')">Quitar</button>
                </td>
            </tr>`;
    });

    tbody.innerHTML = html;
}

function enviarMovimiento(e) {
    e.preventDefault();

    if (!document.getElementById('id_operario').value) {
        alert('Debe seleccionar un operario.');
        return;
    }

    if (itemsMovimiento.length === 0) {
        alert('Debe agregar al menos un insumo o herramienta a la lista.');
        return;
    }

    const btnSubmit = document.getElementById('btn-submit-movimiento');
    const textoOriginal = btnSubmit.innerText;
    btnSubmit.disabled = true;
    btnSubmit.innerText = 'Procesando...';

    const formData = new FormData(document.getElementById('form-movimiento'));
    formData.append('items', JSON.stringify(itemsMovimiento));

    fetch('crear_movimiento.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) throw new Error('Error al conectar con el servidor.');
        return response.json();
    })
    .then(res => {
        if (!res.success) throw new Error(res.error || 'No se pudo registrar el movimiento.');
        mostrarAlerta(res.message, true);
        setTimeout(() => {
            window.location.href = `detalle_movimiento.php?id=${res.id_movimiento}`;
        }, 1200);
    })
    .catch(err => {
        mostrarAlerta(err.message, false);
        btnSubmit.disabled = false;
        btnSubmit.innerText = textoOriginal;
    });
}
</script>

<?php
include_once __DIR__ . '/../layout/footer.php';
?>
