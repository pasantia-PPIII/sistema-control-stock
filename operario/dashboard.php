<?php
/**
 * operario/dashboard.php
 * Vista principal del módulo de Operarios.
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
$js_modulo = 'operarios';
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
$paginaActual = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$rubroId = !empty($_GET['rubro_id']) ? (int) $_GET['rubro_id'] : null;
$estado = isset($_GET['estado']) ? trim($_GET['estado']) : 'activo';

$operariosFiltrados = array_values(array_filter($operarioModel->getAll(true), function ($op) use ($busqueda, $rubroId, $estado) {
    $activo = !empty($op['activo']) && $op['activo'] !== 'f';
    if ($estado === 'activo' && !$activo) {
        return false;
    }
    if ($estado === 'inactivo' && $activo) {
        return false;
    }
    if ($rubroId !== null && (int) $op['id_rubro'] !== $rubroId) {
        return false;
    }
    $texto = $op['dni'] . ' ' . $op['apellido'] . ' ' . $op['nombre'] . ' ' . ($op['legajo'] ?? '');
    if ($busqueda !== '' && mb_strpos(textoNormalizado($texto), textoNormalizado($busqueda)) === false) {
        return false;
    }
    return true;
}));

$totalRegistros = count($operariosFiltrados);
$totalPaginas = max(1, (int) ceil($totalRegistros / $porPagina));
$paginaActual = min($paginaActual, $totalPaginas);
$operarios = array_slice($operariosFiltrados, ($paginaActual - 1) * $porPagina, $porPagina);

// =========================================================================
// RESPUESTA AJAX PARA BÚSQUEDA, FILTROS Y PAGINACIÓN DINÁMICA
// =========================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'data' => $operarios,
        'total_registros' => $totalRegistros,
        'total_paginas' => $totalPaginas,
        'pagina_actual' => $paginaActual
    ]);
    exit;
}

$rubros = $rubroModel->getAll();

include_once __DIR__ . '/../layout/header.php';
?>

<!-- Contenedor de Alertas Dinámicas -->
<div id="alerta-operarios" class="alerta-pagina">
</div>

<!-- Barra de Filtros, Búsqueda y Botón Nuevo Operario -->
<div class="action-bar-filters">
    <div class="search-input-wrapper">
        <input type="text" id="buscador-operario" class="search-input"
            placeholder="Buscar por DNI, Legajo, Apellido o Nombre..." value="<?= htmlspecialchars($busqueda) ?>">
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
                $esActivo = !empty($op['activo']) && $op['activo'] !== 'f';
                $idOp = (int) $op['id_operario'];
                ?>
                <div class="operario-card-row fila-clic">
                    <div><strong><?= $legajoTxt ?></strong></div>
                    <div><?= htmlspecialchars($op['dni']) ?></div>
                    <div class="operario-nombre"><?= $nombreCompleto ?></div>
                    <div><?= $rubroTxt ?></div>

                    <div class="actions-cell" onclick="event.stopPropagation()">
                        <a href="detalle_operario.php?id=<?= $idOp ?>" class="btn-action-icon view" title="Ver Ficha"><?= iconoAccion('view') ?></a>
                        <?php if (isAdmin()): ?>
                            <button type="button" class="btn-action-icon edit" title="Editar Operario"
                                onclick='abrirModalEditar(<?= json_encode($op, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                <?= iconoAccion('edit') ?>
                            </button>

                            <?php
                            $datosEstado = 'data-id="' . $idOp . '" data-dni="' . htmlspecialchars($op['dni'], ENT_QUOTES) . '" data-nombre="' . htmlspecialchars($op['apellido'] . ', ' . $op['nombre'], ENT_QUOTES) . '"';
                            ?>
                            <?php if ($esActivo): ?>
                                <button type="button" class="btn-action-icon disable" title="Deshabilitar Operario" <?= $datosEstado ?>
                                    onclick="abrirModalEstado(this.dataset.id, this.dataset.dni, this.dataset.nombre, true)">
                                    <?= iconoAccion('disable') ?>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn-action-icon enable" title="Habilitar Operario" <?= $datosEstado ?>
                                    onclick="abrirModalEstado(this.dataset.id, this.dataset.dni, this.dataset.nombre, false)">
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
<div id="modal-cargar-operario" class="modal-backdrop">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-cargar-operario')" title="Cerrar" aria-label="Cerrar"><?= iconoAccion('close', 18) ?></button>
        <div class="modal-header-text">
            <h2>Nuevo Operario</h2>
            <p>Registrar un nuevo integrante del personal técnico o pañol</p>
        </div>

        <form id="form-cargar-operario" onsubmit="guardarNuevoOperario(event)">
            <div class="modal-grid-2">
                <div class="modal-form-group">
                    <label>DNI *</label>
                    <input type="text" name="dni" id="cargar-dni" class="modal-input"
                        placeholder="Número de DNI sin puntos" required
                        oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                </div>
                <div class="modal-form-group">
                    <label>Legajo Interno</label>
                    <input type="text" name="legajo" id="cargar-legajo" class="modal-input" placeholder="Ej. OP-104">
                </div>
            </div>

            <div class="modal-grid-2">
                <div class="modal-form-group">
                    <label>Apellido *</label>
                    <input type="text" name="apellido" id="cargar-apellido" class="modal-input" placeholder="Apellido"
                        required>
                </div>
                <div class="modal-form-group">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" id="cargar-nombre" class="modal-input"
                        placeholder="Nombre completo" required>
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

            <div id="msg-error-cargar" class="msg-error-modal">
            </div>

            <button type="submit" class="btn-modal-submit" id="btn-submit-cargar">Guardar Operario</button>
        </form>
    </div>
</div>

<div id="modal-editar-operario" class="modal-backdrop">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-editar-operario')" title="Cerrar" aria-label="Cerrar"><?= iconoAccion('close', 18) ?></button>
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

            <div id="msg-error-edit" class="msg-error-modal">
            </div>

            <button type="submit" class="btn-modal-submit" id="btn-submit-edit">Guardar Cambios</button>
        </form>
    </div>
</div>

<!-- Modal: Deshabilitar / Habilitar operario -->
<div id="modal-estado-operario" class="modal-backdrop">
    <div class="modal-card modal-card--estado">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-estado-operario')" title="Cerrar" aria-label="Cerrar"><?= iconoAccion('close', 18) ?></button>
        <div class="modal-header-text">
            <h2 id="estado-titulo">Deshabilitar Operario</h2>
            <p id="estado-subtitulo"></p>
        </div>

        <div id="estado-caja" class="estado-caja">
            <p><strong>Operario:</strong> <span id="estado-info-nombre"></span></p>
            <p><strong>DNI:</strong> <span id="estado-info-dni"></span></p>
            <p id="estado-nota" class="estado-nota"></p>
        </div>

        <form id="form-estado-operario" onsubmit="ejecutarCambioEstadoOperario(event)">
            <input type="hidden" id="estado-id-val" name="id_operario">
            <input type="hidden" id="estado-accion-val" name="accion" value="deshabilitar">
            <div class="modal-acciones-centro">
                <button type="button" class="btn-action-text view btn-modal-estado"
                    onclick="cerrarModal('modal-estado-operario')">
                    Cancelar
                </button>
                <button type="submit" id="btn-submit-estado" class="btn-action-text disable btn-modal-estado btn-modal-estado--confirmar">
                    Confirmar Deshabilitación
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Datos para assets/js/operarios.js -->
<script type="application/json" id="datos-pagina"><?= json_encode(['paginaActual' => (int) $paginaActual]) ?></script>

<?php
include_once __DIR__ . '/../layout/footer.php';
?>