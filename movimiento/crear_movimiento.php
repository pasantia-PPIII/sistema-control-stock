<?php
/**
 * movimiento/crear_movimiento.php
 * Registro de movimientos de pañol: Entrega (egreso a un operario), Devolución e Ingreso de stock.
 *
 * - GET  ?tipo=entrega|devolucion|ingreso : muestra el formulario correspondiente (sin ?tipo = entrega);
 *   el tipo se cambia desde el selector del propio formulario.
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
require_once __DIR__ . '/../clases/orden_de_trabajo.php';

// Tipos de movimiento (ids del catálogo tipo_movimiento): 1 = Entrega, 2 = Devolución, 3 = Ingreso (reposición de stock, RF-21)
$tipoForm = $_POST['tipo'] ?? $_GET['tipo'] ?? 'entrega';
$tipoForm = in_array($tipoForm, ['devolucion', 'ingreso'], true) ? $tipoForm : 'entrega';
$esDevolucion = ($tipoForm === 'devolucion');
$esIngreso = ($tipoForm === 'ingreso');
$idTipoMov = $esDevolucion ? 2 : ($esIngreso ? 3 : 1);

$movimientoModel = new Movimiento();
$operarioModel = new Operario();
$insumoModel = new Insumo();
$odtModel = new OrdenDeTrabajo();

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
        // Orden de Trabajo opcional (RF-27): vacío = uso general. Movimiento::create() valida que exista y esté habilitada
        $idOdt = (!$esIngreso && !empty($_POST['id_odt'])) ? (int)$_POST['id_odt'] : null;

        // RF-34: toda devolución lleva un motivo del catálogo. Movimiento::create() lo valida
        $idMotivo = ($esDevolucion && !empty($_POST['id_motivo'])) ? (int)$_POST['id_motivo'] : null;

        // El ingreso de stock (compra o reposición) no tiene operario: lo registra el usuario del sistema
        $operario = null;
        if (!$esIngreso) {
            if ($idOperario <= 0) {
                throw new Exception($esDevolucion
                    ? 'Debe seleccionar el operario que realiza la devolución.'
                    : 'Debe seleccionar un operario responsable para la entrega.');
            }

            $operario = $operarioModel->getById($idOperario);
            if (!$operario || !$operario['activo']) {
                throw new Exception('El operario seleccionado no existe o no se encuentra activo.');
            }
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
            $esHerramienta = ((int)$insumo['id_tipo'] === 2);

            // Al entregar un material, el stock debe alcanzar. Las herramientas se validan por estado en
            // Movimiento::create() (informa a quién está asignada); un ingreso solo admite materiales.
            if ($esIngreso && $esHerramienta) {
                throw new Exception("El ingreso de stock aplica solo a materiales: '{$insumo['nombre']}' es una herramienta.");
            }
            if (!$esDevolucion && !$esIngreso && !$esHerramienta && $cantidad > (float)$insumo['stock_actual']) {
                throw new Exception("Stock insuficiente para '{$insumo['nombre']}'. Disponible: {$insumo['stock_actual']}.");
            }

            // El estado de herramienta solo aplica a herramientas (tipo 2)
            $idEstado = null;
            if ($esHerramienta) {
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
            'id_operario'   => $operario ? (int)$operario['id_operario'] : null,
            'fecha'         => $fecha,
            'hora'          => $hora,
            'observaciones' => $observaciones,
            'id_odt'        => $idOdt,
            'id_motivo'     => $idMotivo
        ];

        $idMovimiento = $movimientoModel->create($cabecera, $detalles);

        $quien = $operario ? " para {$operario['nombre']} {$operario['apellido']}" : '';
        echo json_encode([
            'success'       => true,
            'message'       => ($esIngreso ? 'Ingreso' : ($esDevolucion ? 'Devolución' : 'Entrega')) . " #{$idMovimiento} registrad" . ($esIngreso ? 'o' : 'a') . " exitosamente{$quien}.",
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
$titulo_vista = $esIngreso ? 'Registrar Ingreso de Stock' : ($esDevolucion ? 'Registrar Devolución de Pañol' : 'Registrar Entrega de Pañol');
$titulo_pagina = ($esIngreso ? 'Registrar Ingreso' : ($esDevolucion ? 'Registrar Devolución' : 'Registrar Entrega')) . ' - Sistema de Control de Stock';

// El operario y el insumo se buscan por AJAX (ajax/buscar_autocompletado.php) al escribir, de a 10 resultados
$js_modulo = 'autocompletado';

$estadosHerramienta = $movimientoModel->getEstadosHerramienta();

// Operario preseleccionado (?id_operario=): se muestra su texto y se envía su id en el campo oculto
$idOperarioPre = !empty($_GET['id_operario']) ? (int)$_GET['id_operario'] : 0;
$operarioPre = $idOperarioPre ? $operarioModel->getById($idOperarioPre) : null;
if ($operarioPre && empty($operarioPre['activo'])) {
    $operarioPre = null;
}
$motivosDevolucion = $esDevolucion ? $movimientoModel->getMotivosDevolucion() : [];

// Orden de Trabajo preseleccionada (?id_odt=, RF-27). Una entrega no se puede asociar a una orden Finalizada o Anulada
$idOdtPre = !empty($_GET['id_odt']) ? (int)$_GET['id_odt'] : 0;
$odtPre = $idOdtPre ? $odtModel->getById($idOdtPre) : null;
if ($odtPre && !$esDevolucion && in_array($odtPre['estado'] ?? '', ['Finalizada', 'Anulada'], true)) {
    $odtPre = null;
}

include_once __DIR__ . '/../layout/header.php';
?>

<!-- Contenedor de Alertas Dinámicas -->
<div id="alerta-movimiento" class="alerta-inline"></div>

<div class="form-card">
    <div class="form-card-header">
        <div>
            <?php if ($esIngreso): ?>
                <h2 class="form-card-title">Nuevo Ingreso de Stock</h2>
                <p class="form-card-subtitle">Registrar el ingreso de materiales al pañol por compra o reposición; el stock se actualiza al confirmar</p>
            <?php elseif ($esDevolucion): ?>
                <h2 class="form-card-title">Nueva Devolución de Insumos y Herramientas</h2>
                <p class="form-card-subtitle">Registrar el reintegro al pañol de materiales y herramientas en custodia del personal técnico</p>
            <?php else: ?>
                <h2 class="form-card-title">Nueva Entrega de Insumos y Herramientas</h2>
                <p class="form-card-subtitle">Registrar egreso de materiales consumibles o asignación de herramientas en custodia para personal técnico</p>
            <?php endif; ?>
        </div>
        <a href="dashboard.php" class="btn-cancel-form">Volver al Historial</a>
    </div>

    <!-- Tipo de movimiento: cambiarlo recarga el formulario con los campos que corresponden (se conserva el operario o la OT preseleccionados) -->
    <div class="tipo-selector" role="group" aria-label="Tipo de movimiento">
        <span class="tipo-selector-etiqueta">Tipo de movimiento:</span>
        <?php
        $opcionesTipo = ['entrega' => 'Entrega', 'devolucion' => 'Devolución', 'ingreso' => 'Ingreso de stock'];
        $parametrosPre = array_filter(['id_operario' => $idOperarioPre ?: null, 'id_odt' => $idOdtPre ?: null]);
        foreach ($opcionesTipo as $clave => $etiqueta):
            $esActual = ($clave === $tipoForm);
            $enlace = 'crear_movimiento.php?' . http_build_query(['tipo' => $clave] + $parametrosPre);
        ?>
            <a href="<?= htmlspecialchars($enlace) ?>" class="tipo-opcion<?= $esActual ? ' activo' : '' ?>"<?= $esActual ? ' aria-current="true"' : '' ?>><?= $etiqueta ?></a>
        <?php endforeach; ?>
    </div>

    <form id="form-movimiento" onsubmit="enviarMovimiento(event)">
        <input type="hidden" name="tipo" value="<?= $tipoForm ?>">

        <!-- Cabecera del Movimiento -->
        <div class="form-grid-3">
            <?php if (!$esIngreso): ?>
            <div class="form-group autocomplete-wrapper">
                <label for="buscar-operario">Operario <?= $esDevolucion ? 'que Devuelve' : 'Responsable' ?> *</label>
                <input type="text" id="buscar-operario" class="form-control" placeholder="Escriba DNI, apellido o nombre..." autocomplete="off"
                       value="<?= $operarioPre ? htmlspecialchars($operarioPre['apellido'] . ', ' . $operarioPre['nombre'] . ' - DNI: ' . $operarioPre['dni']) : '' ?>">
                <input type="hidden" id="id_operario" name="id_operario" value="<?= $operarioPre ? (int)$operarioPre['id_operario'] : '' ?>">
                <div id="lista-operarios" class="autocomplete-list"></div>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="fecha">Fecha de <?= $esIngreso ? 'Ingreso' : ($esDevolucion ? 'Devolución' : 'Entrega') ?> *</label>
                <input type="date" id="fecha" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="form-group">
                <label for="hora">Hora *</label>
                <input type="time" id="hora" name="hora" class="form-control" value="<?= date('H:i') ?>" required>
            </div>
        </div>

        <?php if ($esDevolucion): ?>
        <div class="form-group mb-20">
            <label for="id_motivo">Motivo de la Devolución *</label>
            <select id="id_motivo" name="id_motivo" class="form-control" required>
                <option value="">-- Seleccionar Motivo --</option>
                <?php foreach ($motivosDevolucion as $mot): ?>
                    <option value="<?= (int)$mot['id_motivo'] ?>"><?= htmlspecialchars($mot['motivo']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <?php if (!$esIngreso): ?>
        <div class="form-group autocomplete-wrapper mb-20">
            <label for="buscar-odt">Orden de Trabajo (opcional)</label>
            <input type="text" id="buscar-odt" class="form-control" placeholder="Escriba el N° de OT, PA o trabajo... (vacío = sin OT / uso general)" autocomplete="off"
                   value="<?= $odtPre ? htmlspecialchars(OrdenDeTrabajo::textoParaSelect($odtPre)) : '' ?>">
            <input type="hidden" id="id_odt" name="id_odt" value="<?= $odtPre ? (int)$odtPre['id_odt'] : '' ?>">
            <div id="lista-odt" class="autocomplete-list"></div>
        </div>
        <?php endif; ?>

        <div class="form-group mb-32">
            <label for="observaciones"><?= $esIngreso ? 'Observaciones (compra o reposición)' : ($esDevolucion ? 'Observaciones' : 'Observaciones o Motivo') ?></label>
            <textarea id="observaciones" name="observaciones" class="form-control form-control--area" placeholder="<?= $esIngreso ? 'Origen del material, motivo de la reposición...' : ($esDevolucion ? 'Detalles de la devolución, estado de los elementos, orden de trabajo...' : 'Detalles de la entrega, orden de trabajo, destino o estado al entregar...') ?>"></textarea>
        </div>

        <!-- Sección de Selección y Agregado de Ítems -->
        <div class="items-section-header">
            <h3 class="items-section-title">Ítems a <?= $esIngreso ? 'Ingresar' : ($esDevolucion ? 'Devolver' : 'Entregar') ?></h3>
        </div>

        <div class="form-grid-3 form-grid-3--items">
            <div class="form-group autocomplete-wrapper form-group--flex-2">
                <label for="buscar-insumo"><?= $esIngreso ? 'Buscar Material' : 'Buscar Insumo o Herramienta' ?></label>
                <input type="text" id="buscar-insumo" class="form-control" placeholder="Escriba código o nombre..." autocomplete="off">
                <div id="lista-insumos" class="autocomplete-list"></div>
            </div>

            <div class="form-group form-group--flex-1">
                <label for="input-cantidad">Cantidad a <?= $esIngreso ? 'Ingresar' : ($esDevolucion ? 'Devolver' : 'Entregar') ?></label>
                <input type="number" id="input-cantidad" class="form-control" min="0.01" step="any" value="1" placeholder="Ej: 1">
            </div>

            <div class="form-group form-group--flex-auto">
                <button type="button" class="btn-add-row" onclick="agregarItem()">Agregar a la Lista</button>
            </div>
        </div>

        <!-- Tabla de Ítems Agregados -->
        <div class="items-table-wrap">
            <table class="items-table" id="tabla-items">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th><?= $esIngreso ? 'Material' : 'Insumo / Herramienta' ?></th>
                        <th>Tipo</th>
                        <th><?= $esDevolucion ? 'En poder del operario' : 'Stock en Pañol' ?></th>
                        <th class="col-cantidad">Cantidad</th>
                        <?php if ($esDevolucion): ?>
                            <th class="col-estado">Estado al Devolver</th>
                        <?php endif; ?>
                        <th class="col-accion">Acción</th>
                    </tr>
                </thead>
                <tbody id="tbody-items">
                    <tr id="fila-vacia">
                        <td colspan="<?= $esDevolucion ? 7 : 6 ?>" class="estado-vacio">No se han agregado ítems todavía. Seleccione un insumo arriba para comenzar.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Botones de Acción -->
        <div class="form-actions-bottom">
            <a href="dashboard.php" class="btn-cancel-form">Cancelar</a>
            <button type="submit" class="btn-submit-form" id="btn-submit-movimiento">Registrar <?= $esIngreso ? 'Ingreso' : ($esDevolucion ? 'Devolución' : 'Entrega') ?></button>
        </div>
    </form>
</div>

<script>
const ES_DEVOLUCION = <?= $esDevolucion ? 'true' : 'false' ?>;
const ES_INGRESO = <?= $esIngreso ? 'true' : 'false' ?>;
const TIPO_FORM = <?= json_encode($tipoForm) ?>;
const ESTADOS_HERRAMIENTA = <?= json_encode($estadosHerramienta, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const COLUMNAS_TABLA = ES_DEVOLUCION ? 7 : 6;

// Ítems agregados en memoria (id_insumo = código del insumo)
let itemsMovimiento = [];

// Cambiar el tipo recarga el formulario: si ya hay ítems cargados, se pide confirmar para no perderlos sin querer
document.querySelectorAll('.tipo-opcion').forEach(enlace => {
    enlace.addEventListener('click', function (e) {
        if (this.classList.contains('activo')) {
            e.preventDefault();
            return;
        }
        if (itemsMovimiento.length > 0 && !confirm('Cambiar el tipo de movimiento descarta los ítems agregados. ¿Continuar?')) {
            e.preventDefault();
        }
    });
});

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

// Insumo elegido en el autocompletado (null = ninguno o texto editado a mano)
let insumoSeleccionado = null;

function agregarItem() {
    const inputCant = document.getElementById('input-cantidad');
    const cantidad = parseFloat(inputCant.value);

    if (!insumoSeleccionado) {
        alert('Por favor, seleccione un insumo o herramienta de la lista.');
        return;
    }

    if (isNaN(cantidad) || cantidad <= 0) {
        alert('Ingrese una cantidad válida mayor a cero.');
        return;
    }

    const { codigo, nombre, tipo, unidad, stock, en_poder } = insumoSeleccionado;

    const indexExistente = itemsMovimiento.findIndex(it => it.id_insumo === codigo);
    const cantidadTotal = (indexExistente >= 0 ? itemsMovimiento[indexExistente].cantidad : 0) + cantidad;

    if (ES_INGRESO && tipo === 'Herramienta') {
        alert('El ingreso de stock aplica solo a materiales.');
        return;
    }
    // Devolución: no se puede devolver más de lo que el operario tiene en su poder
    if (ES_DEVOLUCION && en_poder !== null && en_poder !== undefined && cantidadTotal > en_poder) {
        alert(`El operario tiene en su poder ${en_poder} de "${nombre}": no puede devolver ${cantidadTotal}.`);
        return;
    }

    // Herramientas: siempre de a una unidad. Al entregar, no superar el stock (herramienta: 1 si está en el pañol, 0 si no).
    if (tipo === 'Herramienta' && cantidadTotal > 1) {
        alert(`La herramienta "${nombre}" se registra por unidad (cantidad 1).`);
        return;
    }
    if (!ES_DEVOLUCION && !ES_INGRESO && cantidadTotal > stock) {
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
            en_poder: en_poder,
            cantidad: cantidad,
            id_estado_herramienta: 1
        });
    }

    renderizarTablaItems();
    insumoSeleccionado = null;
    document.getElementById('buscar-insumo').value = '';
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

    if (item.tipo === 'Herramienta' && cant > 1) {
        alert(`La herramienta "${item.nombre}" se registra por unidad (cantidad 1).`);
        renderizarTablaItems();
        return;
    }
    if (ES_DEVOLUCION && item.en_poder !== null && item.en_poder !== undefined && cant > item.en_poder) {
        alert(`El operario tiene en su poder ${item.en_poder} de "${item.nombre}".`);
        renderizarTablaItems();
        return;
    }
    if (!ES_DEVOLUCION && !ES_INGRESO && cant > item.stock) {
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
                <td colspan="${COLUMNAS_TABLA}" class="estado-vacio">No se han agregado ítems todavía. Seleccione un insumo arriba para comenzar.</td>
            </tr>`;
        return;
    }

    let html = '';
    itemsMovimiento.forEach(it => {
        const codigoAttr = escaparHtml(it.id_insumo).replace(/'/g, '&#39;');
        const esHerramienta = (it.tipo === 'Herramienta');
        // Herramienta: máximo 1 unidad siempre. Material: máximo el stock, solo al entregar
        const enPoderConocido = ES_DEVOLUCION && it.en_poder !== null && it.en_poder !== undefined;
        const maxAttr = esHerramienta ? 'max="1"' : (enPoderConocido ? `max="${it.en_poder}"` : ((ES_DEVOLUCION || ES_INGRESO) ? '' : `max="${it.stock}"`));
        const stockTxt = `${enPoderConocido ? it.en_poder : it.stock} ${escaparHtml(it.unidad)}`;

        let celdaEstado = '';
        if (ES_DEVOLUCION) {
            if (it.tipo === 'Herramienta') {
                const opciones = ESTADOS_HERRAMIENTA.map(e =>
                    `<option value="${e.id_estado_herramienta}" ${parseInt(e.id_estado_herramienta, 10) === it.id_estado_herramienta ? 'selected' : ''}>${escaparHtml(e.estado)}</option>`
                ).join('');
                celdaEstado = `<td><select class="form-control form-control--compacto" onchange="actualizarEstadoItem('${codigoAttr}', this.value)">${opciones}</select></td>`;
            } else {
                celdaEstado = '<td>-</td>';
            }
        }

        html += `
            <tr>
                <td class="celda-fuerte">${escaparHtml(it.id_insumo)}</td>
                <td>${escaparHtml(it.nombre)}</td>
                <td><span class="badge ${it.tipo === 'Herramienta' ? 'badge-info' : 'badge-neutral'}">${escaparHtml(it.tipo)}</span></td>
                <td>${stockTxt}</td>
                <td>
                    <input type="number" class="form-control form-control--compacto" min="0.01" ${maxAttr} step="any" value="${it.cantidad}" onchange="actualizarCantidadItem('${codigoAttr}', this.value)">
                </td>
                ${celdaEstado}
                <td class="celda-centro">
                    <button type="button" class="btn-remove-row" onclick="quitarItem('${codigoAttr}')">Quitar</button>
                </td>
            </tr>`;
    });

    tbody.innerHTML = html;
}

// Autocompletado de operario e insumo (assets/js/autocompletado.js, se carga al final de la página)
document.addEventListener('DOMContentLoaded', function () {
    if (!ES_INGRESO) {
        crearAutocompletado({
            input: document.getElementById('buscar-operario'),
            lista: document.getElementById('lista-operarios'),
            url: '../ajax/buscar_autocompletado.php',
            parametros: { recurso: 'operario' },
            alSeleccionar: function (op) {
                document.getElementById('id_operario').value = op.id;
                cambioDeOperario();
            },
            alLimpiar: function () {
                document.getElementById('id_operario').value = '';
                cambioDeOperario();
            }
        });

        crearAutocompletado({
            input: document.getElementById('buscar-odt'),
            lista: document.getElementById('lista-odt'),
            url: '../ajax/buscar_autocompletado.php',
            parametros: { recurso: 'odt', tipo: TIPO_FORM },
            alSeleccionar: function (ot) { document.getElementById('id_odt').value = ot.id; },
            alLimpiar: function () { document.getElementById('id_odt').value = ''; }
        });
    }

    crearAutocompletado({
        input: document.getElementById('buscar-insumo'),
        lista: document.getElementById('lista-insumos'),
        url: '../ajax/buscar_autocompletado.php',
        // Al devolver, se ofrece solo lo que el operario elegido tiene en su poder
        parametros: function () {
            const p = { recurso: 'insumo', tipo: TIPO_FORM };
            const op = document.getElementById('id_operario');
            if (ES_DEVOLUCION && op && op.value) p.operario = op.value;
            return p;
        },
        alSeleccionar: function (ins) { insumoSeleccionado = ins; },
        alLimpiar: function () { insumoSeleccionado = null; }
    });
});

// Si cambia el operario de una devolución, la lista agregada ya no corresponde a lo que tiene en su poder
function cambioDeOperario() {
    if (!ES_DEVOLUCION || itemsMovimiento.length === 0) return;
    itemsMovimiento = [];
    renderizarTablaItems();
    insumoSeleccionado = null;
    document.getElementById('buscar-insumo').value = '';
}

function enviarMovimiento(e) {
    e.preventDefault();

    if (!ES_INGRESO && !document.getElementById('id_operario').value) {
        alert('Debe seleccionar un operario.');
        return;
    }

    // Texto escrito sin elegir una opción: se avisa en lugar de registrar el movimiento sin OT sin querer
    if (!ES_INGRESO && document.getElementById('buscar-odt').value.trim() !== '' && !document.getElementById('id_odt').value) {
        alert('Elija una Orden de Trabajo de la lista o deje el campo vacío para registrar el movimiento sin OT.');
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
