<?php
/**
 * operario/dashboard.php
 * Vista principal del módulo de Operarios.
 *
 * - Datos mediante las clases Operario (getAll) y Rubro (getAll).
 * - Filtrado por texto/rubro/estado y paginación en PHP sobre el listado de la clase.
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

$seccion = 'operario';
$css_modulo = 'operarios';
$titulo_vista = 'Personal Técnico y Operarios';
$titulo_pagina = 'Operarios - Sistema de Control de Stock';

require_once __DIR__ . '/../clases/operario.php';
require_once __DIR__ . '/../clases/rubro.php';

$operarioModel = new Operario();
$rubroModel = new Rubro();

// =========================================================================
// FILTROS Y PAGINACIÓN (servidor)
// =========================================================================
$porPagina = 10;
$paginaActual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$rubroId = !empty($_GET['rubro_id']) ? (int)$_GET['rubro_id'] : null;
$estado = isset($_GET['estado']) ? trim($_GET['estado']) : 'activo';

$operariosFiltrados = array_values(array_filter($operarioModel->getAll(true), function ($op) use ($busqueda, $rubroId, $estado) {
    $activo = !empty($op['activo']) && $op['activo'] !== 'f';
    if ($estado === 'activo' && !$activo) {
        return false;
    }
    if ($estado === 'inactivo' && $activo) {
        return false;
    }
    if ($rubroId !== null && (int)$op['id_rubro'] !== $rubroId) {
        return false;
    }
    $texto = $op['dni'] . ' ' . $op['apellido'] . ' ' . $op['nombre'] . ' ' . ($op['legajo'] ?? '');
    if ($busqueda !== '' && stripos($texto, $busqueda) === false) {
        return false;
    }
    return true;
}));

$totalRegistros = count($operariosFiltrados);
$totalPaginas = max(1, (int)ceil($totalRegistros / $porPagina));
$paginaActual = min($paginaActual, $totalPaginas);
$operarios = array_slice($operariosFiltrados, ($paginaActual - 1) * $porPagina, $porPagina);

// =========================================================================
// RESPUESTA AJAX PARA BÚSQUEDA, FILTROS Y PAGINACIÓN DINÁMICA
// =========================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'         => true,
        'data'            => $operarios,
        'total_registros' => $totalRegistros,
        'total_paginas'   => $totalPaginas,
        'pagina_actual'   => $paginaActual
    ]);
    exit;
}

$rubros = $rubroModel->getAll();

include_once __DIR__ . '/../layout/header.php';
?>

<!-- Contenedor de Alertas Dinámicas -->
<div id="alerta-operarios" style="display: none; padding: 0.8rem 1.2rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.9rem; font-weight: 600;"></div>

<!-- Barra de Filtros, Búsqueda y Botón Nuevo Operario -->
<div class="action-bar-filters">
    <div class="search-input-wrapper">
        <input type="text" id="buscador-operario" class="search-input" placeholder="Buscar por DNI, Legajo, Apellido o Nombre..." value="<?= htmlspecialchars($busqueda) ?>">
    </div>

    <select class="filter-select" id="filtro-rubro">
        <option value="">Todos los Rubros</option>
        <?php foreach ($rubros as $r): ?>
            <option value="<?= htmlspecialchars($r['id_rubro']) ?>" <?= ($rubroId == $r['id_rubro']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($r['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select class="filter-select" id="filtro-estado">
        <option value="activo" <?= ($estado === 'activo') ? 'selected' : '' ?>>Solo Activos</option>
        <option value="inactivo" <?= ($estado === 'inactivo') ? 'selected' : '' ?>>Solo Inactivos</option>
        <option value="todos" <?= ($estado === 'todos') ? 'selected' : '' ?>>Todos</option>
    </select>

    <?php if (isAdmin()): ?>
    <button type="button" class="btn-pill-action" onclick="abrirModalCrear()">Nuevo Operario</button>
    <?php endif; ?>
</div>

<!-- Grilla de Operarios -->
<div class="operarios-table-container">
    <div class="table-header-row">
        <div>Legajo</div>
        <div>DNI</div>
        <div>Apellido y Nombre</div>
        <div>Rubro</div>
        <div>Acciones</div>
    </div>

    <div id="tabla-operarios-body">
        <?php if (empty($operarios)): ?>
            <div class="empty-state">No se encontraron operarios registrados con los filtros seleccionados.</div>
        <?php else: ?>
            <?php foreach ($operarios as $op): 
                $nombreCompleto = htmlspecialchars($op['apellido'] . ', ' . $op['nombre']);
                $legajoVal = $op['legajo'] ?? '';
                $legajoTxt = !empty($legajoVal) ? htmlspecialchars($legajoVal) : '-';
                $rubroTxt = !empty($op['nombre_rubro']) ? htmlspecialchars($op['nombre_rubro']) : 'General';
            ?>
            <div class="operario-card-row">
                <div><strong><?= $legajoTxt ?></strong></div>
                <div><?= htmlspecialchars($op['dni']) ?></div>
                <div><?= $nombreCompleto ?></div>
                <div><?= $rubroTxt ?></div>
                
                <div class="actions-cell">
                    <a href="detalle_operario.php?id=<?= (int)$op['id_operario'] ?>" class="btn-action-text view" title="Ver Ficha" style="text-decoration: none;">Ver Ficha</a>
                    <?php if (isAdmin()): ?>
                    <button type="button" class="btn-action-text edit" title="Editar Operario" 
                            onclick='abrirModalEditar(<?= json_encode($op, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                        Editar
                    </button>

                    <?php if ($op['activo']): ?>
                        <button type="button" class="btn-action-text disable" title="Deshabilitar Operario" 
                                onclick="abrirModalDeshabilitar(<?= (int)$op['id_operario'] ?>, '<?= htmlspecialchars($op['dni'], ENT_QUOTES) ?>', '<?= htmlspecialchars($nombreCompleto, ENT_QUOTES) ?>')">
                            Deshabilitar
                        </button>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Paginación -->
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
<!-- MODALES -->
<!-- ========================================================================= -->

<!-- Modal 1: Nuevo Operario -->
<div id="modal-cargar-operario" class="modal-backdrop">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-cargar-operario')">Cerrar</button>
        <div class="modal-header-text">
            <h2>Nuevo Operario</h2>
            <p>Registrar un nuevo integrante del personal técnico o pañol</p>
        </div>

        <form id="form-cargar-operario" onsubmit="guardarNuevoOperario(event)">
            <div class="modal-grid-2">
                <div class="modal-form-group">
                    <label>DNI *</label>
                    <input type="text" name="dni" id="cargar-dni" class="modal-input" placeholder="Número de DNI sin puntos" required>
                </div>
                <div class="modal-form-group">
                    <label>Legajo Interno</label>
                    <input type="text" name="legajo" id="cargar-legajo" class="modal-input" placeholder="Ej. OP-104">
                </div>
            </div>

            <div class="modal-grid-2">
                <div class="modal-form-group">
                    <label>Apellido *</label>
                    <input type="text" name="apellido" id="cargar-apellido" class="modal-input" placeholder="Apellido" required>
                </div>
                <div class="modal-form-group">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" id="cargar-nombre" class="modal-input" placeholder="Nombre completo" required>
                </div>
            </div>

            <div class="modal-grid-2">
                <div class="modal-form-group">
                    <label>Rubro / Especialidad</label>
                    <select name="id_rubro" id="cargar-id-rubro" class="modal-select">
                        <option value="">Seleccione rubro...</option>
                        <?php foreach ($rubros as $r): ?>
                            <option value="<?= htmlspecialchars($r['id_rubro']) ?>">
                                <?= htmlspecialchars($r['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="msg-error-cargar" style="display: none; color: #dc2626; font-size: 0.85rem; margin-top: 0.5rem; text-align: center;"></div>

            <button type="submit" class="btn-modal-submit" id="btn-submit-cargar">Guardar Operario</button>
        </form>
    </div>
</div>

<!-- Modal 2: Editar Operario -->
<div id="modal-editar-operario" class="modal-backdrop">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-editar-operario')">Cerrar</button>
        <div class="modal-header-text">
            <h2>Editar Operario</h2>
            <p>Actualizar datos del personal técnico</p>
        </div>

        <form id="form-editar-operario" onsubmit="guardarEdicionOperario(event)">
            <input type="hidden" name="id_operario" id="edit-id-operario">

            <div class="modal-grid-2">
                <div class="modal-form-group">
                    <label>DNI (Identificador)</label>
                    <input type="text" name="dni" id="edit-dni" class="modal-input" readonly>
                </div>
                <div class="modal-form-group">
                    <label>Legajo Interno</label>
                    <input type="text" name="legajo" id="edit-legajo" class="modal-input" placeholder="Ej. OP-104">
                </div>
            </div>

            <div class="modal-grid-2">
                <div class="modal-form-group">
                    <label>Apellido *</label>
                    <input type="text" name="apellido" id="edit-apellido" class="modal-input" required>
                </div>
                <div class="modal-form-group">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" id="edit-nombre" class="modal-input" required>
                </div>
            </div>

            <div class="modal-grid-2">
                <div class="modal-form-group">
                    <label>Rubro / Especialidad</label>
                    <select name="id_rubro" id="edit-id-rubro" class="modal-select">
                        <option value="">Seleccione rubro...</option>
                        <?php foreach ($rubros as $r): ?>
                            <option value="<?= htmlspecialchars($r['id_rubro']) ?>">
                                <?= htmlspecialchars($r['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="msg-error-edit" style="display: none; color: #dc2626; font-size: 0.85rem; margin-top: 0.5rem; text-align: center;"></div>

            <button type="submit" class="btn-modal-submit" id="btn-submit-edit">Guardar Cambios</button>
        </form>
    </div>
</div>

<!-- Modal 3: Confirmar Deshabilitación -->
<div id="modal-deshabilitar-operario" class="modal-backdrop">
    <div class="modal-card" style="max-width: 480px;">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-deshabilitar-operario')">Cerrar</button>
        <div class="modal-header-text">
            <h2 style="color: var(--btn-delete);">Deshabilitar Operario</h2>
            <p>¿Está seguro de que desea dar de baja al siguiente operario?</p>
        </div>

        <div style="background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 1.1rem; margin-bottom: 1.5rem; font-size: 0.9rem; color: #991b1b;">
            <p><strong>Operario:</strong> <span id="deshab-info-nombre"></span></p>
            <p><strong>DNI:</strong> <span id="deshab-info-dni"></span></p>
            <p style="margin-top: 0.5rem; font-size: 0.82rem; color: #b91c1c;">
                El registro pasará al estado inactivo. Se conservará todo el historial de movimientos de insumos, auditorías y órdenes de trabajo asociadas.
            </p>
        </div>

        <form id="form-deshabilitar-operario" onsubmit="ejecutarDeshabilitarOperario(event)">
            <input type="hidden" id="deshab-id-val" name="id_operario">
            <div style="display: flex; gap: 1rem; justify-content: center;">
                <button type="button" class="btn-action-text view" style="padding: 0.6rem 1.4rem; font-size: 0.9rem;" onclick="cerrarModal('modal-deshabilitar-operario')">
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

// =========================================================================
// CARGA DINÁMICA DE OPERARIOS (FETCH SIN RECARGA)
// =========================================================================
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
        
        const jsonStr = JSON.stringify(op).replace(/"/g, '&quot;');
        const btnDeshabilitar = (APP_PERMISOS.admin && op.activo) ? `
            <button type="button" class="btn-action-text disable" title="Deshabilitar Operario" 
                    onclick="abrirModalDeshabilitar(${parseInt(op.id_operario, 10)}, '${escaparHtml(op.dni)}', '${nombreCompleto}')">
                Deshabilitar
            </button>` : '';

        html += `
        <div class="operario-card-row">
            <div><strong>${legajoTxt}</strong></div>
            <div>${escaparHtml(op.dni)}</div>
            <div>${nombreCompleto}</div>
            <div>${rubroTxt}</div>
            
            <div class="actions-cell">
                ${APP_PERMISOS.admin ? `
                <button type="button" class="btn-action-text edit" title="Editar Operario" onclick="abrirModalEditar(${jsonStr})">
                    Editar
                </button>
                `: ''}
                ${btnDeshabilitar}
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

// Eventos de Filtro y Búsqueda
document.getElementById('buscador-operario').addEventListener('input', function() {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        cargarOperarios(1);
    }, 280);
});

document.getElementById('filtro-rubro').addEventListener('change', function() {
    cargarOperarios(1);
});

document.getElementById('filtro-estado').addEventListener('change', function() {
    cargarOperarios(1);
});

// =========================================================================
// MODAL: CREAR OPERARIO (FETCH POST)
// =========================================================================
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

// =========================================================================
// MODAL: EDITAR OPERARIO (FETCH POST)
// =========================================================================
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

// =========================================================================
// MODAL: DESHABILITAR OPERARIO (FETCH POST)
// =========================================================================
function abrirModalDeshabilitar(idOperario, dni, nombre) {
    document.getElementById('deshab-id-val').value = idOperario;
    document.getElementById('deshab-info-nombre').innerText = nombre;
    document.getElementById('deshab-info-dni').innerText = dni;
    abrirModal('modal-deshabilitar-operario');
}

function ejecutarDeshabilitarOperario(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    fetch('deshabilitar_operario.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(res => {
        cerrarModal('modal-deshabilitar-operario');
        if (res.success) {
            mostrarAlerta(res.message || 'Operario deshabilitado exitosamente.', true);
            cargarOperarios(paginaActual);
        } else {
            mostrarAlerta(res.error || 'Error al deshabilitar el operario.', false);
        }
    })
    .catch(err => {
        cerrarModal('modal-deshabilitar-operario');
        mostrarAlerta('Error de conexión: ' + err.message, false);
    });
}
</script>

<?php
include_once __DIR__ . '/../layout/footer.php';
?>
