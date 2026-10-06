<?php
/**
 * rubro/dashboard.php
 * Vista principal del módulo de Rubros.
 *
 * - Datos mediante la clase Rubro (getAll, getInsumosByRubro).
 * - Filtrado por texto/estado y paginación en PHP sobre el listado de la clase.
 * - Interacción mediante fetch() sin recarga de página.
 */

require_once __DIR__ . '/../config/config.php';
if (!isAuthenticated()) {
    if (isset($_GET['ajax']) || isset($_GET['action'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Sesión no iniciada. Inicie sesión nuevamente.']);
        exit;
    }
    redirect('auth/login.php');
}

$seccion = 'rubro';
$css_modulo = 'rubros';
$titulo_vista = 'Gestión de Rubros';
$titulo_pagina = 'Rubros - Sistema de Control de Stock';

require_once __DIR__ . '/../clases/rubro.php';

$rubroModel = new Rubro();

// =========================================================================
// ACCIÓN AJAX: OBTENER INSUMOS DE UN RUBRO ESPECÍFICO
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] === 'insumos' && !empty($_GET['id_rubro'])) {
    header('Content-Type: application/json; charset=utf-8');
    $idRubro = (int)$_GET['id_rubro'];
    $insumos = $rubroModel->getInsumosByRubro($idRubro);
    echo json_encode([
        'success' => true,
        'data'    => $insumos
    ]);
    exit;
}

// =========================================================================
// FILTROS Y PAGINACIÓN (servidor)
// =========================================================================
$porPagina = 10;
$paginaActual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$estado = isset($_GET['estado']) ? trim($_GET['estado']) : 'activo';

$rubrosFiltrados = array_values(array_filter($rubroModel->getAll(true), function ($r) use ($busqueda, $estado) {
    $activo = !empty($r['activo']) && $r['activo'] !== 'f';
    if ($estado === 'activo' && !$activo) {
        return false;
    }
    if ($estado === 'inactivo' && $activo) {
        return false;
    }
    if ($busqueda !== '' && stripos($r['nombre'] . ' ' . ($r['descripcion'] ?? ''), $busqueda) === false) {
        return false;
    }
    return true;
}));

$totalRegistros = count($rubrosFiltrados);
$totalPaginas = max(1, (int)ceil($totalRegistros / $porPagina));
$paginaActual = min($paginaActual, $totalPaginas);
$rubros = array_slice($rubrosFiltrados, ($paginaActual - 1) * $porPagina, $porPagina);

// =========================================================================
// RESPUESTA AJAX PARA BÚSQUEDA, FILTROS Y PAGINACIÓN DINÁMICA
// =========================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'         => true,
        'data'            => $rubros,
        'total_registros' => $totalRegistros,
        'total_paginas'   => $totalPaginas,
        'pagina_actual'   => $paginaActual
    ]);
    exit;
}

include_once __DIR__ . '/../layout/header.php';
?>

<!-- Contenedor de Alertas Dinámicas -->
<div id="alerta-rubros" style="display: none; padding: 0.85rem 1.25rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.9rem; font-weight: 600;"></div>

<!-- Barra de Filtros, Búsqueda y Botón Nuevo Rubro -->
<div class="action-bar-filters">
    <div class="search-input-wrapper">
        <input type="text" id="buscador-rubro" class="search-input" placeholder="Buscar por nombre o descripción..." value="<?= htmlspecialchars($busqueda) ?>">
    </div>

    <select class="filter-select" id="filtro-estado">
        <option value="activo" <?= ($estado === 'activo') ? 'selected' : '' ?>>Solo Activos</option>
        <option value="inactivo" <?= ($estado === 'inactivo') ? 'selected' : '' ?>>Solo Inactivos</option>
        <option value="todos" <?= ($estado === 'todos') ? 'selected' : '' ?>>Todos</option>
    </select>

    <?php if (isAdmin()): ?>
    <button type="button" class="btn-pill-action" onclick="abrirModalCrear()">Nuevo Rubro</button>
    <?php endif; ?>
</div>

<!-- Grilla de Rubros -->
<div class="rubros-table-container">
    <div class="table-header-row">
        <div>ID</div>
        <div>Nombre</div>
        <div>Descripción</div>
        <div>Insumos Vinculados</div>
        <div>Estado</div>
        <div>Acciones</div>
    </div>

    <div id="tabla-rubros-body">
        <?php if (empty($rubros)): ?>
            <div class="empty-state">No se encontraron rubros registrados con los criterios seleccionados.</div>
        <?php else: ?>
            <?php foreach ($rubros as $r): 
                $nombreEscapado = htmlspecialchars($r['nombre']);
                $descEscapada = !empty($r['descripcion']) ? htmlspecialchars($r['descripcion']) : 'Sin descripción';
                $cantInsumos = (int)($r['total_insumos'] ?? 0);
                $badgeInsumos = $cantInsumos > 0 
                    ? "<button type='button' class='badge badge-info' style='cursor:pointer; border:none;' onclick='verInsumosRubro({$r['id_rubro']}, \"{$nombreEscapado}\")'>{$cantInsumos} insumos</button>" 
                    : "<span class='badge badge-neutral'>0 insumos</span>";
                
                $esActivo = (bool)$r['activo'];
                $badgeEstado = $esActivo 
                    ? "<span class='badge badge-success'>Activo</span>" 
                    : "<span class='badge badge-danger'>Inactivo</span>";
            ?>
            <div class="rubro-card-row">
                <div><strong>#<?= htmlspecialchars($r['id_rubro']) ?></strong></div>
                <div class="rubro-name-cell"><?= $nombreEscapado ?></div>
                <div class="rubro-desc-cell" title="<?= $descEscapada ?>"><?= $descEscapada ?></div>
                <div><?= $badgeInsumos ?></div>
                <div><?= $badgeEstado ?></div>
                
                <div class="actions-cell">
                    <a href="detalle_rubro.php?id=<?= (int)$r['id_rubro'] ?>" class="btn-action-text view" title="Ver Detalle" style="text-decoration: none;">Ver</a>
                    <?php if (isAdmin()): ?>
                    <button type="button" class="btn-action-text edit" title="Editar Rubro"
                            onclick='abrirModalEditar(<?= json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                        Editar
                    </button>

                    <?php if ($esActivo): ?>
                        <button type="button" class="btn-action-text disable" title="Deshabilitar Rubro" 
                                onclick="abrirModalDeshabilitar(<?= $r['id_rubro'] ?>, '<?= htmlspecialchars($r['nombre'], ENT_QUOTES) ?>', <?= $cantInsumos ?>)">
                            Deshabilitar
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn-action-text view" title="Reactivar Rubro" 
                                onclick="habilitarRubro(<?= $r['id_rubro'] ?>, '<?= htmlspecialchars($r['nombre'], ENT_QUOTES) ?>')">
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

<!-- Modal 1: Nuevo Rubro -->
<div id="modal-cargar-rubro" class="modal-backdrop">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-cargar-rubro')">Cerrar</button>
        <div class="modal-header-text">
            <h2>Nuevo Rubro</h2>
            <p>Registrar una nueva categoría o rubro de insumos</p>
        </div>

        <form id="form-cargar-rubro" onsubmit="guardarNuevoRubro(event)">
            <div class="modal-form-group">
                <label for="crear-nombre">Nombre del Rubro *</label>
                <input type="text" id="crear-nombre" name="nombre" class="modal-input" required placeholder="Ej: Pinturería, Metalúrgica...">
            </div>

            <div class="modal-form-group">
                <label for="crear-descripcion">Descripción</label>
                <textarea id="crear-descripcion" name="descripcion" class="modal-textarea" placeholder="Descripción detallada del rubro..."></textarea>
            </div>

            <div class="modal-actions-row">
                <button type="button" class="btn-modal-cancel" onclick="cerrarModal('modal-cargar-rubro')">Cancelar</button>
                <button type="submit" class="btn-modal-submit" id="btn-submit-crear">Guardar Rubro</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Editar Rubro -->
<div id="modal-editar-rubro" class="modal-backdrop">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-editar-rubro')">Cerrar</button>
        <div class="modal-header-text">
            <h2>Editar Rubro</h2>
            <p>Actualizar datos del rubro seleccionado</p>
        </div>

        <form id="form-editar-rubro" onsubmit="guardarEdicionRubro(event)">
            <input type="hidden" id="edit-id-rubro" name="id_rubro">

            <div class="modal-form-group">
                <label for="edit-nombre">Nombre del Rubro *</label>
                <input type="text" id="edit-nombre" name="nombre" class="modal-input" required>
            </div>

            <div class="modal-form-group">
                <label for="edit-descripcion">Descripción</label>
                <textarea id="edit-descripcion" name="descripcion" class="modal-textarea"></textarea>
            </div>

            <div class="modal-actions-row">
                <button type="button" class="btn-modal-cancel" onclick="cerrarModal('modal-editar-rubro')">Cancelar</button>
                <button type="submit" class="btn-modal-submit" id="btn-submit-editar">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 3: Confirmación Deshabilitar Rubro -->
<div id="modal-deshabilitar-rubro" class="modal-backdrop">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-deshabilitar-rubro')">Cerrar</button>
        <div class="modal-header-text">
            <h2>Deshabilitar Rubro</h2>
            <p>Confirmar la baja del rubro seleccionado</p>
        </div>

        <div id="deshabilitar-warning-insumos" class="modal-warning-box" style="display: none;"></div>

        <p id="deshabilitar-mensaje" style="font-size: 0.95rem; color: #334155; margin-bottom: 1.5rem; text-align: center;"></p>

        <form id="form-deshabilitar-rubro" onsubmit="confirmarDeshabilitarRubro(event)">
            <input type="hidden" id="deshabilitar-id-rubro" name="id_rubro">

            <div class="modal-actions-row">
                <button type="button" class="btn-modal-cancel" onclick="cerrarModal('modal-deshabilitar-rubro')">Cancelar</button>
                <button type="submit" class="btn-modal-danger" id="btn-submit-deshabilitar">Deshabilitar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 4: Insumos Asociados al Rubro -->
<div id="modal-insumos-rubro" class="modal-backdrop">
    <div class="modal-card" style="max-width: 650px;">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-insumos-rubro')">Cerrar</button>
        <div class="modal-header-text">
            <h2 id="insumos-rubro-titulo">Insumos Asociados</h2>
            <p id="insumos-rubro-subtitulo">Listado de insumos vinculados a este rubro</p>
        </div>

        <div id="lista-insumos-rubro" style="max-height: 320px; overflow-y: auto; margin-bottom: 1.25rem;">
            <!-- Contenido dinámico cargado vía fetch -->
        </div>

        <div class="modal-actions-row">
            <button type="button" class="btn-modal-cancel" onclick="cerrarModal('modal-insumos-rubro')">Cerrar</button>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- JAVASCRIPT: CONTROL DINÁMICO FETCH Y MODALES -->
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

// Carga Dinámica vía AJAX (Fetch)
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
            ? `<button type="button" class="badge badge-info" style="cursor:pointer; border:none;" onclick="verInsumosRubro(${r.id_rubro}, '${nombreEscapado}')">${cantInsumos} insumos</button>` 
            : `<span class="badge badge-neutral">0 insumos</span>`;
        
        const esActivo = Boolean(r.activo == true || r.activo == 1 || r.activo === 't');
        const badgeEstado = esActivo 
            ? `<span class="badge badge-success">Activo</span>` 
            : `<span class="badge badge-danger">Inactivo</span>`;
        
        const jsonStr = JSON.stringify(r).replace(/"/g, '&quot;');
        const btnAccionEstado = !APP_PERMISOS.admin ? '' : esActivo ? `
            <button type="button" class="btn-action-text disable" title="Deshabilitar Rubro" 
                    onclick="abrirModalDeshabilitar(${r.id_rubro}, '${nombreEscapado}', ${cantInsumos})">
                Deshabilitar
            </button>` : `
            <button type="button" class="btn-action-text view" title="Reactivar Rubro" 
                    onclick="habilitarRubro(${r.id_rubro}, '${nombreEscapado}')">
                Habilitar
            </button>`;

        html += `
        <div class="rubro-card-row">
            <div><strong>#${escaparHtml(String(r.id_rubro))}</strong></div>
            <div class="rubro-name-cell">${nombreEscapado}</div>
            <div class="rubro-desc-cell" title="${descEscapada}">${descEscapada}</div>
            <div>${badgeInsumos}</div>
            <div>${badgeEstado}</div>
            
            <div class="actions-cell">
                <a href="detalle_rubro.php?id=${parseInt(r.id_rubro, 10)}" class="btn-action-text view" title="Ver Detalle" style="text-decoration: none;">Ver</a>
                ${APP_PERMISOS.admin ? `
                <button type="button" class="btn-action-text edit" title="Editar Rubro" onclick="abrirModalEditar(${jsonStr})">
                    Editar
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

// Búsqueda y Filtros con Debounce
document.getElementById('buscador-rubro').addEventListener('input', function() {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        cargarRubros(1);
    }, 350);
});

document.getElementById('filtro-estado').addEventListener('change', function() {
    cargarRubros(1);
});

// Modales: Crear Rubro
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

// Modales: Editar Rubro
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

// Modales: Deshabilitar Rubro
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

// Reactivación / Habilitación de Rubro
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

// Ver Insumos Asociados al Rubro
function verInsumosRubro(idRubro, nombreRubro) {
    document.getElementById('insumos-rubro-titulo').innerText = `Insumos: ${nombreRubro}`;
    const contenedor = document.getElementById('lista-insumos-rubro');
    contenedor.innerHTML = '<p style="text-align:center; padding:1.5rem; color:#64748b;">Cargando insumos...</p>';
    abrirModal('modal-insumos-rubro');

    fetch(`dashboard.php?action=insumos&id_rubro=${idRubro}`, { credentials: 'same-origin' })
        .then(response => response.json())
        .then(res => {
            if (!res.success || !res.data || res.data.length === 0) {
                contenedor.innerHTML = '<p style="text-align:center; padding:1.5rem; color:#64748b;">No hay insumos activos vinculados a este rubro.</p>';
                return;
            }

            let html = '<table style="width:100%; border-collapse:collapse; font-size:0.85rem; text-align:left;">';
            html += '<thead><tr style="border-bottom:2px solid #e2e8f0; color:#0b1536;">';
            html += '<th style="padding:0.6rem;">Código</th>';
            html += '<th style="padding:0.6rem;">Nombre</th>';
            html += '<th style="padding:0.6rem;">Tipo</th>';
            html += '<th style="padding:0.6rem;">Unidad</th>';
            html += '<th style="padding:0.6rem; text-align:right;">Stock</th>';
            html += '</tr></thead><tbody>';

            res.data.forEach(item => {
                html += `<tr style="border-bottom:1px solid #f1f5f9;">`;
                html += `<td style="padding:0.6rem; font-weight:600;">${escaparHtml(String(item.codigo))}</td>`;
                html += `<td style="padding:0.6rem;">${escaparHtml(item.nombre)}</td>`;
                html += `<td style="padding:0.6rem;">${escaparHtml(item.tipo_nombre || '-')}</td>`;
                html += `<td style="padding:0.6rem;">${escaparHtml(item.unidad_medida || '-')}</td>`;
                html += `<td style="padding:0.6rem; text-align:right; font-weight:700;">${escaparHtml(String(item.stock_actual ?? 0))}</td>`;
                html += `</tr>`;
            });

            html += '</tbody></table>';
            contenedor.innerHTML = html;
        })
        .catch(err => {
            contenedor.innerHTML = `<p style="text-align:center; padding:1.5rem; color:#dc2626;">Error al cargar insumos: ${escaparHtml(err.message)}</p>`;
        });
}

// Cerrar modales con tecla ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-backdrop.active').forEach(modal => {
            modal.classList.remove('active');
        });
    }
});
</script>

<?php
include_once __DIR__ . '/../layout/footer.php';
?>
