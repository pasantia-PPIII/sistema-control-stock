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
$js_modulo = 'insumos';
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
<div id="alerta-dashboard" class="alerta-pagina"></div>

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
            <div class="insumo-card-row<?= $ins['activo'] ? '' : ' insumo-card-row--inactivo' ?>" onclick='abrirModalDetalles(<?= json_encode($ins, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
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
                    <p id="nota-stock-cargar" class="nota-stock">
                        Una herramienta es una unidad: se carga con 1 en el pañol. Su stock lo controlan las entregas y devoluciones.
                    </p>
                </div>

                <div class="modal-grid-2">
                    <div class="modal-form-group modal-form-group--centro">
                        <label class="check-etiqueta">
                            <input type="checkbox" name="es_perecedero" id="cargar-es-perecedero" value="1" onchange="alternarPerecedero(this.checked, 'cargar')">
                            <span>¿Es un artículo perecedero?</span>
                        </label>
                    </div>
                    <div class="modal-form-group oculto" id="grupo-vencimiento-cargar">
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

            <div id="msg-error-cargar" class="msg-error-modal"></div>

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
                <p id="nota-stock-edit" class="nota-stock">
                    El stock de una herramienta (1 en el pañol, 0 fuera) lo controlan las entregas y devoluciones.
                </p>
                <div class="modal-form-group">
                    <label>Stock Mínimo</label>
                    <input type="number" id="edit-stock-minimo" name="stock_minimo" class="modal-input" min="0">
                </div>
                <div class="modal-form-group modal-form-group--centro">
                    <label class="check-etiqueta">
                        <input type="checkbox" id="edit-es-perecedero" name="es_perecedero" value="1" onchange="alternarPerecedero(this.checked, 'edit')">
                        <span>¿Es perecedero?</span>
                    </label>
                </div>
            </div>

            <div class="modal-grid-2 oculto" id="grupo-vencimiento-edit">
                <div class="modal-form-group">
                    <label>Fecha de Vencimiento</label>
                    <input type="date" id="edit-fecha-vencimiento" name="fecha_vencimiento" class="modal-input">
                </div>
            </div>

            <div id="msg-error-edit" class="msg-error-modal"></div>

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
    <div class="modal-card modal-card--estado">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-estado-insumo')">Cerrar</button>
        <div class="modal-header-text">
            <h2 id="estado-titulo">Deshabilitar Insumo</h2>
            <p id="estado-subtitulo"></p>
        </div>

        <div id="estado-caja" class="estado-caja">
            <p><strong>Insumo:</strong> <span id="estado-info"></span></p>
            <p id="estado-nota" class="estado-nota"></p>
        </div>

        <form id="form-estado-insumo" onsubmit="ejecutarCambioEstadoInsumo(event)">
            <input type="hidden" id="estado-codigo-val" name="codigo">
            <input type="hidden" id="estado-accion-val" name="accion" value="deshabilitar">
            <div class="modal-acciones-centro">
                <button type="button" class="btn-action-text view btn-modal-estado" onclick="cerrarModal('modal-estado-insumo')">
                    Cancelar
                </button>
                <button type="submit" id="btn-submit-estado" class="btn-action-text disable btn-modal-estado btn-modal-estado--confirmar">
                    Confirmar Deshabilitación
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Enlace directo desde Reportes (?editar=ID): abre el modal de edición de ese insumo.
$insumoAEditar = (canEdit() && isset($_GET['editar'])) ? $insumoModel->getById((int) $_GET['editar']) : null;
?>
<!-- Datos para assets/js/insumos.js -->
<script type="application/json" id="datos-pagina"><?= json_encode([
    'paginaActual'  => $paginaActual,
    'insumoAEditar' => $insumoAEditar ?: null,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<?php
include_once __DIR__ . '/../layout/footer.php';
?>
