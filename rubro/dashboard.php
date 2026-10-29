<?php
/**
 * rubro/dashboard.php
 * Vista principal del módulo de Rubros.
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
$js_modulo = 'rubros';
$titulo_vista = 'Gestión de Rubros';
$titulo_pagina = 'Rubros - Sistema de Control de Stock';

require_once __DIR__ . '/../clases/rubro.php';

$rubroModel = new Rubro();

// FILTROS Y PAGINACIÓN
$porPagina = 10;
$paginaActual = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
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
$totalPaginas = max(1, (int) ceil($totalRegistros / $porPagina));
$paginaActual = min($paginaActual, $totalPaginas);
$rubros = array_slice($rubrosFiltrados, ($paginaActual - 1) * $porPagina, $porPagina);

// RESPUESTA AJAX
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'data' => $rubros,
        'total_registros' => $totalRegistros,
        'total_paginas' => $totalPaginas,
        'pagina_actual' => $paginaActual
    ]);
    exit;
}

include_once __DIR__ . '/../layout/header.php';
?>

<!-- Contenedor de Alertas Dinámicas -->
<div id="alerta-rubros" class="alerta-pagina">
</div>

<!-- Barra de Filtros, Búsqueda y Botón Nuevo Rubro -->
<div class="action-bar-filters">
    <div class="search-input-wrapper">
        <input type="text" id="buscador-rubro" class="search-input" placeholder="Buscar por nombre o descripción..."
            value="<?= htmlspecialchars($busqueda) ?>">
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
                $cantInsumos = (int) ($r['total_insumos'] ?? 0);
                $badgeInsumos = $cantInsumos > 0
                    ? "<span class='badge badge-info'>{$cantInsumos} insumos</span>"
                    : "<span class='badge badge-neutral'>0 insumos</span>";

                $esActivo = !empty($r['activo']) && $r['activo'] !== 'f';
                $badgeEstado = $esActivo
                    ? "<span class='badge badge-success'>Activo</span>"
                    : "<span class='badge badge-danger'>Inactivo</span>";
                ?>
                <div class="rubro-card-row fila-clic">
                    <div><strong>#<?= htmlspecialchars($r['id_rubro']) ?></strong></div>
                    <div class="rubro-name-cell"><?= $nombreEscapado ?></div>
                    <div class="rubro-desc-cell" title="<?= $descEscapada ?>"><?= $descEscapada ?></div>
                    <div><?= $badgeInsumos ?></div>
                    <div><?= $badgeEstado ?></div>

                    <div class="actions-cell">
                        <a href="detalle_rubro.php?id=<?= (int) $r['id_rubro'] ?>" class="btn-action-icon view" title="Ver Detalle"><?= iconoAccion('view') ?></a>
                        <?php if (isAdmin()): ?>
                            <button type="button" class="btn-action-icon edit" title="Editar Rubro"
                                onclick='abrirModalEditar(<?= json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                <?= iconoAccion('edit') ?>
                            </button>

                            <?php if ($esActivo): ?>
                                <button type="button" class="btn-action-icon disable" title="Deshabilitar Rubro"
                                    onclick="abrirModalDeshabilitar(<?= $r['id_rubro'] ?>, '<?= htmlspecialchars($r['nombre'], ENT_QUOTES) ?>', <?= $cantInsumos ?>)">
                                    <?= iconoAccion('disable') ?>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn-action-icon enable" title="Reactivar Rubro"
                                    onclick="habilitarRubro(<?= $r['id_rubro'] ?>, '<?= htmlspecialchars($r['nombre'], ENT_QUOTES) ?>')">
                                    <?= iconoAccion('enable') ?>
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
<div class="pagination-container" id="contenedor-paginacion" data-total="<?= (int)$totalPaginas ?>" data-actual="<?= (int)$paginaActual ?>"></div>

<!-- Modales -->
<div id="modal-cargar-rubro" class="modal-backdrop">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-cargar-rubro')" title="Cerrar" aria-label="Cerrar"><?= iconoAccion('close', 18) ?></button>
        <div class="modal-header-text">
            <h2>Nuevo Rubro</h2>
            <p>Registrar una nueva categoría o rubro de insumos</p>
        </div>

        <form id="form-cargar-rubro" onsubmit="guardarNuevoRubro(event)">
            <div class="modal-form-group">
                <label for="crear-nombre">Nombre del Rubro *</label>
                <input type="text" id="crear-nombre" name="nombre" class="modal-input" required
                    placeholder="Ej: Pinturería, Metalúrgica...">
            </div>

            <div class="modal-form-group">
                <label for="crear-descripcion">Descripción</label>
                <textarea id="crear-descripcion" name="descripcion" class="modal-textarea"
                    placeholder="Descripción detallada del rubro..."></textarea>
            </div>

            <div class="modal-actions-row">
                <button type="button" class="btn-modal-cancel"
                    onclick="cerrarModal('modal-cargar-rubro')">Cancelar</button>
                <button type="submit" class="btn-modal-submit" id="btn-submit-crear">Guardar Rubro</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-editar-rubro" class="modal-backdrop">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-editar-rubro')" title="Cerrar" aria-label="Cerrar"><?= iconoAccion('close', 18) ?></button>
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
                <button type="button" class="btn-modal-cancel"
                    onclick="cerrarModal('modal-editar-rubro')">Cancelar</button>
                <button type="submit" class="btn-modal-submit" id="btn-submit-editar">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-deshabilitar-rubro" class="modal-backdrop">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-deshabilitar-rubro')" title="Cerrar" aria-label="Cerrar"><?= iconoAccion('close', 18) ?></button>
        <div class="modal-header-text">
            <h2>Deshabilitar Rubro</h2>
            <p>Confirmar la baja del rubro seleccionado</p>
        </div>

        <div id="deshabilitar-warning-insumos" class="modal-warning-box oculto"></div>

        <p id="deshabilitar-mensaje" class="deshabilitar-mensaje"></p>

        <form id="form-deshabilitar-rubro" onsubmit="confirmarDeshabilitarRubro(event)">
            <input type="hidden" id="deshabilitar-id-rubro" name="id_rubro">

            <div class="modal-actions-row">
                <button type="button" class="btn-modal-cancel"
                    onclick="cerrarModal('modal-deshabilitar-rubro')">Cancelar</button>
                <button type="submit" class="btn-modal-danger" id="btn-submit-deshabilitar">Deshabilitar</button>
            </div>
        </form>
    </div>
</div>

<!-- Datos para assets/js/rubros.js -->
<script type="application/json" id="datos-pagina"><?= json_encode(['paginaActual' => (int) $paginaActual]) ?></script>

<?php
include_once __DIR__ . '/../layout/footer.php';
?>