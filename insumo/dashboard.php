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

$insumosFiltrados = array_values(array_filter($insumoModel->getAll(), function ($ins) use ($busqueda, $filtroTipo, $filtroRubro) {
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
                $stkTxt = $esHerramienta ? '-' : ($ins['stock_actual'] ?? 0);
                $estadoVenc = $esHerramienta ? 'Disponible' : (!empty($ins['fecha_vencimiento']) ? htmlspecialchars($ins['fecha_vencimiento']) : 'No perecedero');
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
                    <button type="button" class="btn-action-text edit" title="Editar Insumo" 
                            onclick='abrirModalEditar(<?= json_encode($ins, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                        Editar
                    </button>

                    <button type="button" class="btn-action-text disable" title="Deshabilitar Insumo" 
                            onclick="abrirModalDeshabilitar('<?= htmlspecialchars($ins['codigo'], ENT_QUOTES) ?>', '<?= htmlspecialchars($ins['nombre'], ENT_QUOTES) ?>')">
                        Deshabilitar
                    </button>
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

<!-- Modal 4: Confirmar Deshabilitación -->
<div id="modal-deshabilitar-insumo" class="modal-backdrop">
    <div class="modal-card" style="max-width: 460px;">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-deshabilitar-insumo')">Cerrar</button>
        <div class="modal-header-text">
            <h2 style="color: var(--btn-delete);">Deshabilitar Insumo</h2>
            <p>¿Está seguro de que desea dar de baja este insumo del catálogo activo?</p>
        </div>

        <div style="background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; font-size: 0.9rem; color: #991b1b;">
            <p><strong>Insumo:</strong> <span id="deshab-info"></span></p>
            <p style="margin-top: 0.4rem; font-size: 0.8rem; color: #b91c1c;">El registro permanecerá en el sistema para fines de auditoría pero no estará disponible para nuevos movimientos.</p>
        </div>

        <form id="form-deshabilitar-insumo" onsubmit="ejecutarDeshabilitarInsumo(event)">
            <input type="hidden" id="deshab-codigo-val" name="codigo">
            <div style="display: flex; gap: 1rem; justify-content: center;">
                <button type="button" class="btn-action-text view" style="padding: 0.6rem 1.4rem; font-size: 0.9rem;" onclick="cerrarModal('modal-deshabilitar-insumo')">
                    Cancelar
                </button>
                <button type="submit" class="btn-action-text disable" style="padding: 0.6rem 1.4rem; font-size: 0.9rem; background-color: var(--btn-delete); color: #ffffff;">
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

function alternarCamposInsumo(idTipo, prefijo) {
    // Si es herramienta, se pueden ajustar campos si es necesario
    const select = document.getElementById(prefijo + '-id-tipo');
    if (!select) return;
    const selectedOption = select.options[select.selectedIndex];
    const tipoNombre = selectedOption ? (selectedOption.dataset.nombre || '') : '';
    const esHerramienta = tipoNombre.includes('herramienta');
    
    const bloqueMat = document.getElementById('bloque-material-' + prefijo);
    if (bloqueMat) {
        // En ambos casos mostramos campos relevantes
    }
}

// =========================================================================
// CARGA DINÁMICA DE INSUMOS MEDIANTE FETCH() (SIN RECARGAR LA PÁGINA)
// =========================================================================
function cargarInsumos(pagina = 1) {
    paginaActual = pagina;
    const busqueda = document.getElementById('buscador-insumo').value.trim();
    const tipo = document.getElementById('filtro-tipo').value;
    const rubro = document.getElementById('filtro-rubro').value;

    const url = `dashboard.php?ajax=1&pagina=${pagina}&busqueda=${encodeURIComponent(busqueda)}&tipo=${encodeURIComponent(tipo)}&rubro=${encodeURIComponent(rubro)}`;

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
        const stkTxt = esHerramienta ? '-' : (ins.stock_actual !== null ? ins.stock_actual : 0);
        const estadoVenc = esHerramienta ? 'Disponible' : (ins.fecha_vencimiento ? ins.fecha_vencimiento : 'No perecedero');
        const jsonStr = JSON.stringify(ins).replace(/"/g, '&quot;');

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
                ${APP_PERMISOS.editar ? `
                <button type="button" class="btn-action-text edit" title="Editar Insumo" onclick="abrirModalEditar(${jsonStr})">
                    Editar
                </button>
                <button type="button" class="btn-action-text disable" title="Deshabilitar Insumo" onclick="abrirModalDeshabilitar('${escaparHtml(ins.codigo)}', '${escaparHtml(ins.nombre)}')">
                    Deshabilitar
                </button>
                `: ''}
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

// =========================================================================
// MODAL: CARGAR INSUMO (FETCH POST)
// =========================================================================
function abrirModalCrear() {
    const form = document.getElementById('form-cargar-insumo');
    if (form) form.reset();
    document.getElementById('msg-error-cargar').style.display = 'none';
    alternarPerecedero(false, 'cargar');
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
    document.getElementById('det-stock').innerText = esHerramienta ? 'Control por Pañol' : (ins.stock_actual !== null ? ins.stock_actual : 0);
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
// MODAL: DESHABILITAR INSUMO (FETCH POST)
// =========================================================================
function abrirModalDeshabilitar(codigo, nombre) {
    document.getElementById('deshab-codigo-val').value = codigo;
    document.getElementById('deshab-info').innerText = codigo + ' - ' + nombre;
    abrirModal('modal-deshabilitar-insumo');
}

function ejecutarDeshabilitarInsumo(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    fetch('deshabilitar_insumo.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(res => {
        cerrarModal('modal-deshabilitar-insumo');
        if (res.success) {
            mostrarAlerta(res.message || 'Insumo deshabilitado exitosamente.', true);
            cargarInsumos(paginaActual);
        } else {
            mostrarAlerta(res.error || 'Error al deshabilitar el insumo.', false);
        }
    })
    .catch(err => {
        cerrarModal('modal-deshabilitar-insumo');
        mostrarAlerta('Error de conexión: ' + err.message, false);
    });
}
</script>

<?php
include_once __DIR__ . '/../layout/footer.php';
?>
