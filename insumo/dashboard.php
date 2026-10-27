<?php
/**
 * insumo/dashboard.php
 * Vista principal del módulo de Insumos:
 * Grilla de insumos, filtros dinámicos, paginación y modales CRUD.
 *
 * - Datos mediante la clase Insumo (getAll, getTipos, getRubros, getUnidadesMedida, getUbicaciones).
 * - Filtrado y paginación en PHP sobre el listado de la clase.
 * - Interacción mediante fetch() sin recarga de página.
 */

require_once __DIR__ . '/../config/config.php';
if (!isAuthenticated()) {
    if (isset($_GET['ajax'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Sesión no iniciada. Inicie sesión nuevamente.']);
        exit;
    }
    redirect('auth/login.php');
}

$seccion = 'insumo';
$css_modulo = 'insumos';
$titulo_vista = 'Gestión de Insumos';
$titulo_pagina = 'Gestión de Insumos - Sistema de Control de Stock';

require_once __DIR__ . '/../clases/insumo.php';

$insumoModel = new Insumo();

// =========================================================================
// FILTROS Y PAGINACIÓN (servidor)
// =========================================================================
$porPagina = 10;
$paginaActual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$filtroTipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';
$filtroRubro = isset($_GET['rubro']) ? trim($_GET['rubro']) : '';
$estado = isset($_GET['estado']) ? trim($_GET['estado']) : 'activo';

$insumosFiltrados = array_values(array_filter($insumoModel->getAll(true), function ($ins) use ($busqueda, $filtroTipo, $filtroRubro, $estado) {
    if ($estado === 'activo' && !$ins['activo']) {
        return false;
    }
    if ($estado === 'inactivo' && $ins['activo']) {
        return false;
    }
    if ($busqueda !== '' && stripos($ins['codigo'] . ' ' . $ins['nombre'], $busqueda) === false) {
        return false;
    }
    if ($filtroTipo !== '' && (string)$ins['id_tipo'] !== $filtroTipo) {
        return false;
    }
    if ($filtroRubro !== '' && (string)$ins['id_rubro'] !== $filtroRubro) {
        return false;
    }
    return true;
}));

$totalRegistros = count($insumosFiltrados);
$totalPaginas = max(1, (int)ceil($totalRegistros / $porPagina));
$paginaActual = min($paginaActual, $totalPaginas);
$insumos = array_slice($insumosFiltrados, ($paginaActual - 1) * $porPagina, $porPagina);

// =========================================================================
// ENDPOINT AJAX INTERNO: si la petición viene por AJAX, devuelve JSON
// =========================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'         => true,
        'data'            => $insumos,
        'total_registros' => $totalRegistros,
        'total_paginas'   => $totalPaginas,
        'pagina_actual'   => $paginaActual
    ]);
    exit;
}

// =========================================================================
// CATÁLOGOS PARA FILTROS Y FORMULARIOS
// =========================================================================
$tipos = $insumoModel->getTipos();
$rubros = $insumoModel->getRubros();
$unidadesMedida = $insumoModel->getUnidadesMedida();
$ubicaciones = $insumoModel->getUbicaciones();

include_once __DIR__ . '/../layout/header.php';
?>

<!-- Contenedor de Alertas de Notificación Dinámicas -->
<div id="alerta-dashboard" style="display: none; padding: 0.8rem 1.2rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.9rem; font-weight: 600;"></div>

<!-- Barra de Búsqueda, Filtros y Botón Cargar Insumo -->
<div class="action-bar-filters">
    <div class="search-input-wrapper">
        <input type="text" id="buscador-insumo" class="search-input" placeholder="Buscar por nombre o código..." value="<?= htmlspecialchars($busqueda) ?>">
    </div>

    <select class="filter-select" id="filtro-tipo">
        <option value="">Todos los Tipos</option>
        <?php foreach ($tipos as $t): ?>
            <option value="<?= htmlspecialchars($t['id_tipo']) ?>" <?= ($filtroTipo == $t['id_tipo']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($t['tipo']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select class="filter-select" id="filtro-rubro">
        <option value="">Todos los Rubros</option>
        <?php foreach ($rubros as $r): ?>
            <option value="<?= htmlspecialchars($r['id_rubro']) ?>" <?= ($filtroRubro == $r['id_rubro']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($r['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select class="filter-select" id="filtro-estado">
        <option value="activo" <?= ($estado === 'activo') ? 'selected' : '' ?>>Solo Activos</option>
        <option value="inactivo" <?= ($estado === 'inactivo') ? 'selected' : '' ?>>Solo Deshabilitados</option>
        <option value="todos" <?= ($estado === 'todos') ? 'selected' : '' ?>>Todos</option>
    </select>

    <?php if (canEdit()): ?>
    <button type="button" class="btn-pill-action" onclick="abrirModalCrear()">Cargar Insumo</button>
    <?php endif; ?>
</div>

<!-- Grilla de Insumos -->
<div class="insumos-table-container">
    <div class="table-header-row">
        <div>Código</div>
        <div>Nombre</div>
        <div>Rubro</div>
        <div>U. Medida</div>
        <div>Stock</div>
        <div>Tipo</div>
        <div>Estado / Venc.</div>
        <div>Acciones</div>
    </div>

    <div id="tabla-insumos-body">
        <?php if (empty($insumos)): ?>
            <div class="empty-state">No se encontraron insumos con los criterios seleccionados.</div>
        <?php else: ?>
            <?php foreach ($insumos as $ins): 
                $esHerramienta = (!empty($ins['nombre_tipo']) && stripos($ins['nombre_tipo'], 'herramienta') !== false);
                $tipoTxt = $ins['nombre_tipo'] ?? 'Material';
                $umTxt = $esHerramienta ? '-' : ($ins['unidad_medida'] ?? 'Unidad');
                $stkTxt = $ins['stock_actual'] ?? 0;
                $estadoVenc = !$ins['activo'] ? 'Deshabilitado' : ($esHerramienta ? ($ins['nombre_estado_herramienta'] ?? 'Disponible') : (!empty($ins['fecha_vencimiento']) ? htmlspecialchars($ins['fecha_vencimiento']) : 'No perecedero'));
            ?>
            <div class="insumo-card-row" onclick='abrirModalDetalles(<?= json_encode($ins, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                <div><strong><?= htmlspecialchars($ins['codigo']) ?></strong></div>
                <div><?= htmlspecialchars($ins['nombre']) ?></div>
                <div><?= htmlspecialchars($ins['nombre_rubro'] ?? '-') ?></div>
                <div><?= htmlspecialchars($umTxt) ?></div>
                <div><?= htmlspecialchars($stkTxt) ?></div>
                <div><?= htmlspecialchars($tipoTxt) ?></div>
                <div><?= htmlspecialchars($estadoVenc) ?></div>
                
                <div class="actions-cell" onclick="event.stopPropagation()">
                    <button type="button" class="btn-action-text view" title="Ver Detalles" 
                            onclick='abrirModalDetalles(<?= json_encode($ins, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                        Ver
                    </button>
                    
                    <?php if (canEdit()): ?>
                    <?php if ($ins['activo']): ?>
                    <button type="button" class="btn-action-text edit" title="Editar Insumo"
                            onclick='abrirModalEditar(<?= json_encode($ins, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                        Editar
                    </button>

                    <button type="button" class="btn-action-text disable" title="Deshabilitar Insumo"
                            data-codigo="<?= htmlspecialchars($ins['codigo'], ENT_QUOTES) ?>" data-nombre="<?= htmlspecialchars($ins['nombre'], ENT_QUOTES) ?>"
                            onclick="abrirModalEstado(this.dataset.codigo, this.dataset.nombre, true)">
                        Deshabilitar
                    </button>
                    <?php else: ?>
                    <button type="button" class="btn-action-text view" title="Habilitar Insumo"
                            data-codigo="<?= htmlspecialchars($ins['codigo'], ENT_QUOTES) ?>" data-nombre="<?= htmlspecialchars($ins['nombre'], ENT_QUOTES) ?>"
                            onclick="abrirModalEstado(this.dataset.codigo, this.dataset.nombre, false)">
                        Habilitar
                    </button>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Contenedor de Paginación -->
<div class="pagination-container" id="contenedor-paginacion">
    <?php if ($totalPaginas > 1): ?>
        <button type="button" class="pagination-btn text-label" onclick="cambiarPagina(<?= max(1, $paginaActual - 1) ?>)" <?= ($paginaActual <= 1) ? 'disabled' : '' ?>>
            Anterior
        </button>
        <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
            <button type="button" class="pagination-btn <?= ($p === $paginaActual) ? 'active' : '' ?>" onclick="cambiarPagina(<?= $p ?>)">
                <?= $p ?>
            </button>
        <?php endfor; ?>
        <button type="button" class="pagination-btn text-label" onclick="cambiarPagina(<?= min($totalPaginas, $paginaActual + 1) ?>)" <?= ($paginaActual >= $totalPaginas) ? 'disabled' : '' ?>>
            Siguiente
        </button>
    <?php endif; ?>
</div>

<!-- ========================================================================= -->
<!-- MODALES DEL SISTEMA -->
<!-- ========================================================================= -->

<!-- Modal 1: Cargar Insumo -->
<div id="modal-cargar-insumo" class="modal-backdrop">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-cargar-insumo')">Cerrar</button>
        <div class="modal-header-text">
            <h2>Cargar Insumo</h2>
            <p>Completar los campos obligatorios para registrar un nuevo artículo</p>
        </div>

        <form id="form-cargar-insumo" onsubmit="guardarNuevoInsumo(event)">
            <div class="modal-grid-2">
                <div class="modal-form-group">
                    <label>Tipo de Insumo *</label>
                    <select name="id_tipo" id="cargar-id-tipo" class="modal-select" required onchange="alternarCamposInsumo(this.value, 'cargar')">
                        <option value="">Seleccione un tipo...</option>
                        <?php foreach ($tipos as $t): ?>
                            <option value="<?= htmlspecialchars($t['id_tipo']) ?>" data-nombre="<?= strtolower(htmlspecialchars($t['tipo'])) ?>">
                                <?= htmlspecialchars($t['tipo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-form-group">
                    <label>Código *</label>
                    <input type="text" name="codigo" id="cargar-codigo" class="modal-input" placeholder="Ej. INS-001" required>
                </div>
            </div>

            <div class="modal-grid-2">
                <div class="modal-form-group">
                    <label>Nombre / Descripción *</label>
                    <input type="text" name="nombre" id="cargar-nombre" class="modal-input" placeholder="Descripción clara del insumo" required>
                </div>
                <div class="modal-form-group">
                    <label>Rubro *</label>
                    <select name="id_rubro" id="cargar-id-rubro" class="modal-select" required>
                        <option value="">Seleccione un rubro...</option>
                        <?php foreach ($rubros as $r): ?>
                            <option value="<?= htmlspecialchars($r['id_rubro']) ?>">
                                <?= htmlspecialchars($r['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Bloque para Material / Consumible -->
            <div id="bloque-material-cargar">
                <div class="modal-grid-2">
                    <div class="modal-form-group">
                        <label>Unidad de Medida</label>
                        <select name="id_unidad_medida" id="cargar-id-um" class="modal-select">
                            <option value="">Seleccione U.M....</option>
                            <?php foreach ($unidadesMedida as $um): ?>
                                <option value="<?= htmlspecialchars($um['id_unidad_medida']) ?>">
                                    <?= htmlspecialchars($um['unidad_medida']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-form-group">
                        <label>Stock Mínimo de Alerta</label>
                        <input type="number" name="stock_minimo" id="cargar-stock-minimo" class="modal-input" value="5" min="0">
                    </div>
                </div>

                <div class="modal-grid-2">
                    <div class="modal-form-group" id="grupo-stock-cargar">
                        <label>Stock Inicial *</label>
                        <input type="number" name="stock_inicial" id="cargar-stock-inicial" class="modal-input" placeholder="Cantidad existente (0 si no hay)" min="0" step="any" required>
                    </div>
                    <p id="nota-stock-cargar" style="display: none; font-size: 0.85rem; color: #475569; align-self: center;">
                        Una herramienta es una unidad: se carga con 1 en el pañol. Su stock lo controlan las entregas y devoluciones.
                    </p>
                </div>

                <div class="modal-grid-2">
                    <div class="modal-form-group" style="justify-content: center;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; margin-top: 0.5rem;">
                            <input type="checkbox" name="es_perecedero" id="cargar-es-perecedero" value="1" onchange="alternarPerecedero(this.checked, 'cargar')">
                            <span>¿Es un artículo perecedero?</span>
                        </label>
                    </div>
                    <div class="modal-form-group" id="grupo-vencimiento-cargar" style="display: none;">
                        <label>Fecha de Vencimiento</label>
                        <input type="date" name="fecha_vencimiento" id="cargar-fecha-vencimiento" class="modal-input">
                    </div>
                </div>
            </div>

            <!-- Bloque para Ubicación -->
            <div class="modal-grid-2">
                <div class="modal-form-group">
                    <label>Ubicación en Depósito / Pañol</label>
                    <select name="id_ubicacion" id="cargar-id-ubicacion" class="modal-select">
                        <option value="">Seleccione ubicación...</option>
                        <?php foreach ($ubicaciones as $ub): ?>
                            <option value="<?= htmlspecialchars($ub['id_ubicacion']) ?>">
                                <?= htmlspecialchars($ub['ubicacion']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="msg-error-cargar" style="display: none; color: #dc2626; font-size: 0.85rem; margin-top: 0.5rem; text-align: center;"></div>

            <button type="submit" class="btn-modal-submit" id="btn-submit-cargar">Guardar Insumo</button>
        </form>
    </div>
</div>

<!-- Modal 2: Editar Insumo -->
<div id="modal-editar-insumo" class="modal-backdrop">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-editar-insumo')">Cerrar</button>
        <div class="modal-header-text">
            <h2>Editar Insumo</h2>
            <p>Modificar los datos del artículo seleccionado</p>
        </div>

        <form id="form-editar-insumo" onsubmit="guardarEdicionInsumo(event)">
            <div class="modal-grid-2">
                <div class="modal-form-group">
                    <label>Código (Solo lectura)</label>
                    <input type="text" id="edit-codigo" name="codigo" class="modal-input" readonly>
                </div>
                <div class="modal-form-group">
                    <label>Nombre / Descripción *</label>
                    <input type="text" id="edit-nombre" name="nombre" class="modal-input" required>
                </div>
            </div>

            <div class="modal-grid-2">
                <div class="modal-form-group">
                    <label>Rubro *</label>
                    <select id="edit-id-rubro" name="id_rubro" class="modal-select" required>
                        <option value="">Seleccione rubro...</option>
                        <?php foreach ($rubros as $r): ?>
                            <option value="<?= htmlspecialchars($r['id_rubro']) ?>">
                                <?= htmlspecialchars($r['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-form-group">
                    <label>Tipo de Insumo *</label>
                    <select id="edit-id-tipo" name="id_tipo" class="modal-select" required>
                        <option value="">Seleccione tipo...</option>
                        <?php foreach ($tipos as $t): ?>
                            <option value="<?= htmlspecialchars($t['id_tipo']) ?>">
                                <?= htmlspecialchars($t['tipo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="modal-grid-2">
                <div class="modal-form-group">
                    <label>Unidad de Medida</label>
                    <select id="edit-id-um" name="id_unidad_medida" class="modal-select">
                        <option value="">Seleccione U.M....</option>
                        <?php foreach ($unidadesMedida as $um): ?>
                            <option value="<?= htmlspecialchars($um['id_unidad_medida']) ?>">
                                <?= htmlspecialchars($um['unidad_medida']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-form-group">
                    <label>Ubicación</label>
                    <select id="edit-id-ubicacion" name="id_ubicacion" class="modal-select">
                        <option value="">Seleccione ubicación...</option>
                        <?php foreach ($ubicaciones as $ub): ?>
                            <option value="<?= htmlspecialchars($ub['id_ubicacion']) ?>">
                                <?= htmlspecialchars($ub['ubicacion']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="modal-grid-2">
                <div class="modal-form-group" id="grupo-stock-edit">
                    <label>Stock Actual</label>
                    <input type="number" id="edit-stock-actual" name="stock_actual" class="modal-input" min="0" step="any">
                </div>
                <p id="nota-stock-edit" style="display: none; font-size: 0.85rem; color: #475569; align-self: center;">
                    El stock de una herramienta (1 en el pañol, 0 fuera) lo controlan las entregas y devoluciones.
                </p>
                <div class="modal-form-group">
                    <label>Stock Mínimo</label>
                    <input type="number" id="edit-stock-minimo" name="stock_minimo" class="modal-input" min="0">
                </div>
                <div class="modal-form-group" style="justify-content: center;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; margin-top: 0.5rem;">
                        <input type="checkbox" id="edit-es-perecedero" name="es_perecedero" value="1" onchange="alternarPerecedero(this.checked, 'edit')">
                        <span>¿Es perecedero?</span>
                    </label>
                </div>
            </div>

            <div class="modal-grid-2" id="grupo-vencimiento-edit" style="display: none;">
                <div class="modal-form-group">
                    <label>Fecha de Vencimiento</label>
                    <input type="date" id="edit-fecha-vencimiento" name="fecha_vencimiento" class="modal-input">
                </div>
            </div>

            <div id="msg-error-edit" style="display: none; color: #dc2626; font-size: 0.85rem; margin-top: 0.5rem; text-align: center;"></div>

            <button type="submit" class="btn-modal-submit" id="btn-submit-edit">Guardar Cambios</button>
        </form>
    </div>
</div>

<!-- Modal 3: Detalles Insumo -->
<div id="modal-detalles-insumo" class="modal-backdrop">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-detalles-insumo')">Cerrar</button>
        <div class="modal-header-text">
            <h2>Detalles del Insumo</h2>
            <p>Información completa registrada en el sistema</p>
        </div>

        <div class="details-list">
            <div class="detail-item"><strong>Código:</strong> <span id="det-codigo"></span></div>
            <div class="detail-item"><strong>Nombre / Descripción:</strong> <span id="det-nombre"></span></div>
            <div class="detail-item"><strong>Tipo de Insumo:</strong> <span id="det-tipo"></span></div>
            <div class="detail-item"><strong>Rubro:</strong> <span id="det-rubro"></span></div>
            <div class="detail-item"><strong>Unidad de Medida:</strong> <span id="det-um"></span></div>
            <div class="detail-item"><strong>Stock Actual:</strong> <span id="det-stock"></span></div>
            <div class="detail-item"><strong>Stock Mínimo:</strong> <span id="det-stock-min"></span></div>
            <div class="detail-item"><strong>Ubicación:</strong> <span id="det-ubicacion"></span></div>
            <div class="detail-item"><strong>¿Es Perecedero?:</strong> <span id="det-perecedero"></span></div>
            <div class="detail-item"><strong>Fecha de Vencimiento:</strong> <span id="det-vencimiento"></span></div>
            <div class="detail-item"><strong>Estado del Registro:</strong> <span id="det-activo"></span></div>
        </div>

        <button type="button" class="btn-modal-back" onclick="cerrarModal('modal-detalles-insumo')">Volver</button>
    </div>
</div>

<!-- Modal 4: Confirmar Deshabilitación / Habilitación (los textos y colores los pone abrirModalEstado) -->
<div id="modal-estado-insumo" class="modal-backdrop">
    <div class="modal-card" style="max-width: 460px;">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-estado-insumo')">Cerrar</button>
        <div class="modal-header-text">
            <h2 id="estado-titulo">Deshabilitar Insumo</h2>
            <p id="estado-subtitulo"></p>
        </div>

        <div id="estado-caja" style="border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; font-size: 0.9rem;">
            <p><strong>Insumo:</strong> <span id="estado-info"></span></p>
            <p id="estado-nota" style="margin-top: 0.4rem; font-size: 0.8rem;"></p>
        </div>

        <form id="form-estado-insumo" onsubmit="ejecutarCambioEstadoInsumo(event)">
            <input type="hidden" id="estado-codigo-val" name="codigo">
            <input type="hidden" id="estado-accion-val" name="accion" value="deshabilitar">
            <div style="display: flex; gap: 1rem; justify-content: center;">
                <button type="button" class="btn-action-text view" style="padding: 0.6rem 1.4rem; font-size: 0.9rem;" onclick="cerrarModal('modal-estado-insumo')">
                    Cancelar
                </button>
                <button type="submit" id="btn-submit-estado" class="btn-action-text disable" style="padding: 0.6rem 1.4rem; font-size: 0.9rem; color: #ffffff;">
                    Confirmar Deshabilitación
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- JAVASCRIPT: Interacción Single Page mediante fetch() -->
<!-- ========================================================================= -->
<script>
let paginaActual = <?= $paginaActual ?>;
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
        const estadoVenc = !insActivo ? 'Deshabilitado' : (esHerramienta ? (ins.nombre_estado_herramienta || 'Disponible') : (ins.fecha_vencimiento ? ins.fecha_vencimiento : 'No perecedero'));
        const jsonStr = JSON.stringify(ins).replace(/"/g, '&quot;');
        // Código y nombre viajan en data-* (escapados) para que un apóstrofo no rompa el onclick
        const datosEstado = `data-codigo="${escaparHtml(ins.codigo)}" data-nombre="${escaparHtml(ins.nombre)}"`;
        const botonesEdicion = insActivo ? `
                <button type="button" class="btn-action-text edit" title="Editar Insumo" onclick="abrirModalEditar(${jsonStr})">
                    Editar
                </button>
                <button type="button" class="btn-action-text disable" title="Deshabilitar Insumo" ${datosEstado} onclick="abrirModalEstado(this.dataset.codigo, this.dataset.nombre, true)">
                    Deshabilitar
                </button>` : `
                <button type="button" class="btn-action-text view" title="Habilitar Insumo" ${datosEstado} onclick="abrirModalEstado(this.dataset.codigo, this.dataset.nombre, false)">
                    Habilitar
                </button>`;

        html += `
        <div class="insumo-card-row" onclick="abrirModalDetalles(${jsonStr})">
            <div><strong>${escaparHtml(ins.codigo)}</strong></div>
            <div>${escaparHtml(ins.nombre)}</div>
            <div>${escaparHtml(ins.nombre_rubro || '-')}</div>
            <div>${escaparHtml(umTxt)}</div>
            <div>${escaparHtml(stkTxt)}</div>
            <div>${escaparHtml(tipoTxt)}</div>
            <div>${escaparHtml(estadoVenc)}</div>
            
            <div class="actions-cell" onclick="event.stopPropagation()">
                <button type="button" class="btn-action-text view" title="Ver Detalles" onclick="abrirModalDetalles(${jsonStr})">
                    Ver
                </button>
                ${APP_PERMISOS.editar ? botonesEdicion : ''}
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
    document.getElementById('det-activo').innerText = activo ? 'Activo' : 'Deshabilitado';

    abrirModal('modal-detalles-insumo');
}

// =========================================================================
// MODAL: DESHABILITAR / HABILITAR INSUMO (FETCH POST)
// =========================================================================
function abrirModalEstado(codigo, nombre, deshabilitar) {
    const caja = document.getElementById('estado-caja');
    const nota = document.getElementById('estado-nota');
    const titulo = document.getElementById('estado-titulo');
    const btn = document.getElementById('btn-submit-estado');

    document.getElementById('estado-codigo-val').value = codigo;
    document.getElementById('estado-accion-val').value = deshabilitar ? 'deshabilitar' : 'habilitar';
    document.getElementById('estado-info').innerText = codigo + ' - ' + nombre;

    if (deshabilitar) {
        titulo.innerText = 'Deshabilitar Insumo';
        titulo.style.color = 'var(--btn-delete)';
        document.getElementById('estado-subtitulo').innerText = '¿Está seguro de que desea dar de baja este insumo del catálogo activo?';
        caja.style.backgroundColor = '#fef2f2';
        caja.style.border = '1px solid #fecaca';
        caja.style.color = '#991b1b';
        nota.style.color = '#b91c1c';
        nota.innerText = 'El registro permanecerá en el sistema para fines de auditoría pero no estará disponible para nuevos movimientos.';
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

<?php
// Enlace directo desde Reportes (?editar=ID): abre el modal de edición de ese insumo.
$insumoAEditar = null;
if (canEdit() && isset($_GET['editar'])) {
    foreach ($insumoModel->getAll(true) as $ins) {
        if ((int)$ins['id_insumo'] === (int)$_GET['editar'] && $ins['activo']) {
            $insumoAEditar = $ins;
            break;
        }
    }
}
if ($insumoAEditar): ?>
document.addEventListener('DOMContentLoaded', function () {
    abrirModalEditar(<?= json_encode($insumoAEditar, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
});
<?php endif; ?>
</script>

<?php
include_once __DIR__ . '/../layout/footer.php';
?>
