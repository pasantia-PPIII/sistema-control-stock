<?php
/**
 * orden_de_trabajo/dashboard.php
 * Panel principal del módulo de Órdenes de Trabajo.
 *
 * - Datos mediante la clase OrdenDeTrabajo (getAll, getById, update, getLocalidades, getJurisdicciones).
 * - Filtrado por texto/estado y paginación en PHP sobre el listado de la clase.
 * - Acciones AJAX integradas en este archivo:
 *     GET  ?action=detalle&id=N       -> datos completos de una orden (modal "Ver Detalle")
 *     POST ?action=cambiar_estado     -> actualiza el estado de una orden (modal "Cambiar Estado")
 * - La creación de órdenes se envía a crear_orden_de_trabajo.php.
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

$seccion = 'orden_de_trabajo';
$css_modulo = 'orden_de_trabajo';
$titulo_vista = 'Gestión de Órdenes de Trabajo';
$titulo_pagina = 'Órdenes de Trabajo - Sistema de Control de Stock';

require_once __DIR__ . '/../clases/orden_de_trabajo.php';

$odtModel = new OrdenDeTrabajo();

// =========================================================================
// ACCIÓN AJAX: DETALLE DE UNA ORDEN
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] === 'detalle') {
    header('Content-Type: application/json; charset=utf-8');
    $idOdt = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $orden = $idOdt > 0 ? $odtModel->getById($idOdt) : false;

    if (!$orden) {
        echo json_encode(['success' => false, 'error' => "No se encontró la Orden de Trabajo #{$idOdt}."]);
        exit;
    }

    echo json_encode([
        'success'     => true,
        'data'        => $orden,
        'operarios'   => $odtModel->getOperariosAsignados($idOdt),
        'movimientos' => $odtModel->getMovimientosVinculados($idOdt)
    ]);
    exit;
}

// =========================================================================
// ACCIÓN AJAX: MOVIMIENTOS DISPONIBLES PARA VINCULAR (entrega y devolución, ambos opcionales)
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] === 'movimientos_disponibles') {
    requireRole(['admin', 'panolero']);
    header('Content-Type: application/json; charset=utf-8');
    $idOdt = isset($_GET['id_odt']) ? (int)$_GET['id_odt'] : 0;
    echo json_encode([
        'success'     => true,
        'entregas'    => $odtModel->getMovimientosDisponibles('Entrega', $idOdt),
        'devoluciones' => $odtModel->getMovimientosDisponibles('Devolución', $idOdt)
    ]);
    exit;
}

// =========================================================================
// ACCIÓN AJAX: CAMBIAR ESTADO DE UNA ORDEN
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] === 'cambiar_estado') {
    requireRole(['admin', 'panolero']);
    header('Content-Type: application/json; charset=utf-8');

    try {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('Método no permitido. Se requiere petición POST.');
        }

        $idOdt = isset($_POST['id_odt']) ? (int)$_POST['id_odt'] : 0;
        $nuevoEstado = isset($_POST['estado']) ? trim((string)$_POST['estado']) : '';

        if ($idOdt <= 0) {
            throw new Exception('El identificador de la Orden de Trabajo es inválido.');
        }
        if (!array_key_exists($nuevoEstado, $odtModel->getEstadosPosibles())) {
            throw new Exception('Debe especificar un estado válido.');
        }

        $orden = $odtModel->getById($idOdt);
        if (!$orden) {
            throw new Exception("La Orden de Trabajo #{$idOdt} no existe.");
        }

        // update() recibe todos los campos editables: se parte de los datos actuales y se cambia solo el estado
        $camposEditables = [
            'numero_ot', 'pa', 'trabajo_a_realizar', 'id_localidad', 'id_jurisdiccion',
            'fecha_inicio', 'fecha_final', 'hora_inicio', 'hora_final', 'observaciones', 'archivo_pdf'
        ];
        $datos = array_intersect_key($orden, array_flip($camposEditables));
        $datos['estado'] = $nuevoEstado;

        if (!$odtModel->update($idOdt, $datos)) {
            throw new Exception('No se pudo actualizar el estado de la Orden de Trabajo en la base de datos.');
        }

        echo json_encode([
            'success'      => true,
            'message'      => "El estado de la Orden de Trabajo #{$idOdt} se actualizó a '{$nuevoEstado}'.",
            'id_odt'       => $idOdt,
            'nuevo_estado' => $nuevoEstado
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => safeErrorMessage($e)]);
    }
    exit;
}

// =========================================================================
// FILTROS Y PAGINACIÓN (servidor)
// =========================================================================
$porPagina = 10;
$paginaActual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$busqueda = isset($_GET['busqueda']) ? trim((string)$_GET['busqueda']) : '';
$estado = isset($_GET['estado']) ? trim((string)$_GET['estado']) : '';

$ordenesFiltradas = array_values(array_filter($odtModel->getAll(), function ($ot) use ($busqueda, $estado) {
    if ($estado !== '' && ($ot['estado'] ?? 'Pendiente') !== $estado) {
        return false;
    }
    $texto = $ot['id_odt'] . ' ' . $ot['numero_ot'] . ' ' . ($ot['pa'] ?? '') . ' ' . ($ot['trabajo_a_realizar'] ?? '');
    if ($busqueda !== '' && stripos($texto, $busqueda) === false) {
        return false;
    }
    return true;
}));

$totalRegistros = count($ordenesFiltradas);
$totalPaginas = max(1, (int)ceil($totalRegistros / $porPagina));
$paginaActual = min($paginaActual, $totalPaginas);
$ordenes = array_slice($ordenesFiltradas, ($paginaActual - 1) * $porPagina, $porPagina);

// =========================================================================
// RESPUESTA AJAX PARA BÚSQUEDA, FILTROS Y PAGINACIÓN DINÁMICA
// =========================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'         => true,
        'data'            => $ordenes,
        'total_registros' => $totalRegistros,
        'total_paginas'   => $totalPaginas,
        'pagina_actual'   => $paginaActual
    ]);
    exit;
}

require_once __DIR__ . '/../clases/operario.php';
$operariosOdt = (new Operario())->getParaSelect();
$localidades = $odtModel->getLocalidades();
$jurisdicciones = $odtModel->getJurisdicciones();

include_once __DIR__ . '/../layout/header.php';
?>

<!-- Contenedor de Alertas Dinámicas -->
<div id="alerta-odt" class="alerta-mensaje"></div>

<!-- Barra de Filtros, Búsqueda y Botón Nueva Orden -->
<div class="action-bar-filters">
    <div class="search-input-wrapper">
        <input type="text" id="buscador-odt" class="search-input" placeholder="Buscar por N° OT, PA o trabajo..." value="<?= htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <select class="filter-select" id="filtro-estado">
        <option value="">Todos los Estados</option>
        <option value="Pendiente" <?= ($estado === 'Pendiente') ? 'selected' : '' ?>>Pendiente</option>
        <option value="En Progreso" <?= ($estado === 'En Progreso') ? 'selected' : '' ?>>En Progreso</option>
        <option value="Pausada" <?= ($estado === 'Pausada') ? 'selected' : '' ?>>Pausada</option>
        <option value="Finalizada" <?= ($estado === 'Finalizada') ? 'selected' : '' ?>>Finalizada</option>
        <option value="Anulada" <?= ($estado === 'Anulada') ? 'selected' : '' ?>>Anulada</option>
    </select>

    <?php if (canEdit()): ?>
    <button type="button" class="btn-pill-action" onclick="abrirModalCrear()">Nueva Orden</button>
    <?php endif; ?>
</div>

<!-- Grilla de Órdenes de Trabajo -->
<div class="table-responsive">
    <table class="data-table">
        <thead>
            <tr>
                <th>N° OT</th>
                <th>N° PA</th>
                <th>Trabajo a Realizar</th>
                <th>Localidad / Jurisdicción</th>
                <th>Período</th>
                <th>Estado</th>
                <th>Documento PDF</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tabla-odt-body">
            <?php if (empty($ordenes)): ?>
                <tr>
                    <td colspan="8" class="empty-state">No se encontraron órdenes de trabajo registradas con los criterios seleccionados.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($ordenes as $ot): 
                    $idOdt = (int)$ot['id_odt'];
                    $numeroOt = htmlspecialchars($ot['numero_ot'], ENT_QUOTES, 'UTF-8');
                    $pa = !empty($ot['pa']) ? htmlspecialchars($ot['pa'], ENT_QUOTES, 'UTF-8') : '-';
                    $trabajo = !empty($ot['trabajo_a_realizar']) ? htmlspecialchars($ot['trabajo_a_realizar'], ENT_QUOTES, 'UTF-8') : 'Sin descripción';
                    $trabajoCorto = (mb_strlen($trabajo) > 60) ? mb_substr($trabajo, 0, 60) . '...' : $trabajo;

                    $loc = !empty($ot['nombre_localidad']) ? htmlspecialchars($ot['nombre_localidad'], ENT_QUOTES, 'UTF-8') : '-';
                    $jur = !empty($ot['nombre_jurisdiccion']) ? htmlspecialchars($ot['nombre_jurisdiccion'], ENT_QUOTES, 'UTF-8') : '-';
                    $ubicacion = ($loc !== '-' || $jur !== '-') ? "{$loc} / {$jur}" : '-';

                    $fechaIni = !empty($ot['fecha_inicio']) ? htmlspecialchars($ot['fecha_inicio'], ENT_QUOTES, 'UTF-8') : '-';
                    $fechaFin = !empty($ot['fecha_final']) ? htmlspecialchars($ot['fecha_final'], ENT_QUOTES, 'UTF-8') : '-';
                    $periodo = ($fechaIni !== '-' || $fechaFin !== '-') ? "{$fechaIni} a {$fechaFin}" : '-';

                    $estadoTexto = htmlspecialchars($ot['estado'] ?? 'Pendiente', ENT_QUOTES, 'UTF-8');
                    $estadoLower = strtolower(str_replace(' ', '-', trim($ot['estado'] ?? 'pendiente')));
                    $badgeClass = 'badge-' . $estadoLower;

                    $tienePdf = !empty($ot['archivo_pdf']);
                    $nombrePdf = $tienePdf ? htmlspecialchars($ot['archivo_pdf'], ENT_QUOTES, 'UTF-8') : '';
                ?>
                <tr>
                    <td><strong><?= $numeroOt ?></strong></td>
                    <td><?= $pa ?></td>
                    <td title="<?= $trabajo ?>"><?= $trabajoCorto ?></td>
                    <td><?= $ubicacion ?></td>
                    <td><?= $periodo ?></td>
                    <td><span class="badge <?= $badgeClass ?>"><?= $estadoTexto ?></span></td>
                    <td>
                        <?php if ($tienePdf): ?>
                            <a href="<?= rtrim(BASE_URL, "/") ?>/uploads/pa_pdfs/<?= $nombrePdf ?>" target="_blank" class="btn-pdf-view">
                                Ver PDF
                            </a>
                        <?php else: ?>
                            <span class="btn-pdf-none">Sin PDF</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="actions-cell">
                            <button type="button" class="btn-action-text view" onclick="verDetalleModal(<?= $idOdt ?>)">
                                Ver Detalle
                            </button>
                            <?php if (canEdit()): ?>
                            <button type="button" class="btn-action-text edit" onclick="abrirModalEditar(<?= $idOdt ?>)">
                                Editar
                            </button>
                            <button type="button" class="btn-action-text disable" onclick="deshabilitarOdt(<?= $idOdt ?>, '<?= $numeroOt ?>')">
                                Deshabilitar
                            </button>
                            <button type="button" class="btn-action-text edit" onclick="abrirModalCambiarEstado(<?= $idOdt ?>, '<?= $numeroOt ?>', '<?= $estadoTexto ?>')">
                                Cambiar Estado
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Contenedor de Paginación Dinámica -->
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

<!-- Modal 1: Nueva Orden de Trabajo -->
<div id="modal-crear-odt" class="modal-overlay">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-crear-odt')" title="Cerrar">Cerrar</button>
        <h2 class="modal-title" id="crear-odt-titulo">Nueva Orden de Trabajo</h2>
        <p class="modal-subtitle">Complete los campos de la orden y adjunte el expediente PDF en caso de corresponder</p>

        <form id="form-crear-odt" onsubmit="guardarNuevaOdt(event)" enctype="multipart/form-data">
            <input type="hidden" id="crear-id-odt" name="id_odt" value="">
            <div class="form-grid-2">
                <div>
                    <label class="modal-label" for="crear-numero-ot">Número de OT *</label>
                    <input type="text" id="crear-numero-ot" name="numero_ot" class="modal-input" required placeholder="Ej: OT-2026-001">
                </div>
                <div>
                    <label class="modal-label" for="crear-pa">Número de PA (Pedido de Abastecimiento)</label>
                    <input type="text" id="crear-pa" name="pa" class="modal-input" placeholder="Ej: PA-2026-104">
                </div>
            </div>

            <div class="form-grid-2">
                <div>
                    <label class="modal-label" for="crear-localidad">Localidad</label>
                    <select id="crear-localidad" name="id_localidad" class="modal-select">
                        <option value="">Seleccione una localidad...</option>
                        <?php foreach ($localidades as $loc): ?>
                            <option value="<?= (int)$loc['id_localidad'] ?>">
                                <?= htmlspecialchars($loc['localidad'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="modal-label" for="crear-jurisdiccion">Jurisdicción</label>
                    <select id="crear-jurisdiccion" name="id_jurisdiccion" class="modal-select">
                        <option value="">Seleccione una jurisdicción...</option>
                        <?php foreach ($jurisdicciones as $jur): ?>
                            <option value="<?= (int)$jur['id_jurisdiccion'] ?>">
                                <?= htmlspecialchars($jur['jurisdiccion'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-grid-2">
                <div>
                    <label class="modal-label" for="crear-fecha-inicio">Fecha de Inicio</label>
                    <input type="date" id="crear-fecha-inicio" name="fecha_inicio" class="modal-input">
                </div>
                <div>
                    <label class="modal-label" for="crear-fecha-final">Fecha de Finalización</label>
                    <input type="date" id="crear-fecha-final" name="fecha_final" class="modal-input">
                </div>
            </div>

            <div class="form-grid-2">
                <div>
                    <label class="modal-label" for="crear-hora-inicio">Hora de Inicio</label>
                    <input type="time" id="crear-hora-inicio" name="hora_inicio" class="modal-input">
                </div>
                <div>
                    <label class="modal-label" for="crear-hora-final">Hora de Finalización</label>
                    <input type="time" id="crear-hora-final" name="hora_final" class="modal-input">
                </div>
            </div>

            <div class="form-grid-2">
                <div>
                    <label class="modal-label" for="crear-estado">Estado Inicial</label>
                    <select id="crear-estado" name="estado" class="modal-select">
                        <option value="Pendiente" selected>Pendiente</option>
                        <option value="En Progreso">En Progreso</option>
                        <option value="Pausada">Pausada</option>
                        <option value="Finalizada">Finalizada</option>
                    </select>
                </div>
                <div>
                    <label class="modal-label" for="crear-archivo-pdf">Expediente Oficial (PDF)</label>
                    <input type="file" id="crear-archivo-pdf" name="archivo_pdf" class="modal-input" accept=".pdf,application/pdf">
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label class="modal-label" for="crear-operarios">Operarios asignados (comisión)</label>
                <select id="crear-operarios" name="operarios[]" class="modal-select" multiple size="4">
                    <?php foreach ($operariosOdt as $op): ?>
                        <option value="<?= (int)$op['id_operario'] ?>">
                            <?= htmlspecialchars($op['apellido'] . ', ' . $op['nombre'] . ' (DNI ' . $op['dni'] . ')', ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="color:#64748b;">Mantenga presionada la tecla Ctrl para seleccionar varios operarios.</small>
            </div>

            <div class="form-grid-2">
                <div>
                    <label class="modal-label" for="crear-mov-egreso">Movimiento de entrega (opcional)</label>
                    <select id="crear-mov-egreso" name="id_mov_egreso" class="modal-select">
                        <option value="">Sin movimiento de entrega</option>
                    </select>
                </div>
                <div>
                    <label class="modal-label" for="crear-mov-devolucion">Movimiento de devolución (opcional)</label>
                    <select id="crear-mov-devolucion" name="id_mov_devolucion" class="modal-select">
                        <option value="">Sin movimiento de devolución</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label class="modal-label" for="crear-trabajo">Trabajo a Realizar / Descripción</label>
                <textarea id="crear-trabajo" name="trabajo_a_realizar" class="modal-textarea" placeholder="Describa las tareas a ejecutar..."></textarea>
            </div>

            <div style="margin-bottom: 1rem;">
                <label class="modal-label" for="crear-observaciones">Observaciones</label>
                <textarea id="crear-observaciones" name="observaciones" class="modal-textarea" placeholder="Notas o requerimientos adicionales..."></textarea>
            </div>

            <div class="modal-actions-row">
                <button type="button" class="btn-modal-cancel" onclick="cerrarModal('modal-crear-odt')">Cancelar</button>
                <button type="submit" class="btn-modal-submit" id="btn-submit-crear">Guardar Orden</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Cambiar Estado de Orden -->
<div id="modal-cambiar-estado" class="modal-overlay">
    <div class="modal-card" style="max-width: 480px;">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-cambiar-estado')" title="Cerrar">Cerrar</button>
        <h2 class="modal-title">Cambiar Estado</h2>
        <p class="modal-subtitle" id="estado-modal-subtitulo">Actualizar el estado operativo de la orden</p>

        <form id="form-cambiar-estado" onsubmit="guardarCambioEstado(event)">
            <input type="hidden" id="estado-id-odt" name="id_odt">

            <div style="margin-bottom: 1.25rem;">
                <label class="modal-label">Orden de Trabajo Seleccionada</label>
                <input type="text" id="estado-display-ot" class="modal-input" readonly style="background-color: #f1f5f9; font-weight:700;">
            </div>

            <div style="margin-bottom: 1.25rem;">
                <label class="modal-label" for="estado-nuevo">Nuevo Estado *</label>
                <select id="estado-nuevo" name="estado" class="modal-select" required>
                    <option value="Pendiente">Pendiente</option>
                    <option value="En Progreso">En Progreso</option>
                    <option value="Pausada">Pausada</option>
                    <option value="Finalizada">Finalizada</option>
                    <option value="Anulada">Anulada</option>
                </select>
            </div>

            <div class="modal-actions-row">
                <button type="button" class="btn-modal-cancel" onclick="cerrarModal('modal-cambiar-estado')">Cancelar</button>
                <button type="submit" class="btn-modal-submit" id="btn-submit-estado">Actualizar Estado</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 3: Ver Detalle Completo de la Orden -->
<div id="modal-detalle-odt" class="modal-overlay">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-detalle-odt')" title="Cerrar">Cerrar</button>
        <h2 class="modal-title" id="detalle-ot-titulo">Detalle de la Orden</h2>
        <p class="modal-subtitle" id="detalle-ot-subtitulo">Información completa y expediente</p>

        <div id="detalle-odt-contenido">
            <p style="text-align:center; color:#64748b; padding:2rem;">Cargando información...</p>
        </div>

        <div class="modal-actions-row">
            <button type="button" class="btn-modal-cancel" onclick="cerrarModal('modal-detalle-odt')">Cerrar</button>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- LÓGICA JAVASCRIPT / AJAX (FETCH API) -->
<!-- ========================================================================= -->
<script>
const baseUrl = "<?= rtrim(BASE_URL, '/') ?>";
let paginaActual = <?= (int)$paginaActual ?>;
let debounceTimeout = null;

function escaparHtml(texto) {
    if (texto === null || texto === undefined) return '';
    return String(texto)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function mostrarAlerta(mensaje, esExito = true) {
    const alerta = document.getElementById('alerta-odt');
    if (!alerta) return;
    alerta.className = 'alerta-mensaje ' + (esExito ? 'alerta-exito' : 'alerta-error');
    alerta.innerText = mensaje;
    alerta.style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
    setTimeout(() => {
        alerta.style.display = 'none';
    }, 4500);
}

function cargarOrdenes(pagina = 1) {
    paginaActual = pagina;
    const busqueda = encodeURIComponent(document.getElementById('buscador-odt').value.trim());
    const estado = encodeURIComponent(document.getElementById('filtro-estado').value);

    const url = `dashboard.php?ajax=1&pagina=${pagina}&busqueda=${busqueda}&estado=${estado}`;

    fetch(url, { credentials: 'same-origin' })
        .then(response => {
            if (!response.ok) throw new Error('Error al conectar con el servidor.');
            return response.json();
        })
        .then(res => {
            if (!res.success) throw new Error(res.error || 'No se pudieron obtener las órdenes de trabajo.');
            renderizarTabla(res.data);
            renderizarPaginacion(res.total_paginas, res.pagina_actual);
        })
        .catch(err => {
            mostrarAlerta(err.message, false);
        });
}

function cambiarPagina(p) {
    cargarOrdenes(p);
}

function renderizarTabla(ordenes) {
    const tbody = document.getElementById('tabla-odt-body');
    if (!tbody) return;

    if (!ordenes || ordenes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="empty-state">No se encontraron órdenes de trabajo registradas con los criterios seleccionados.</td></tr>';
        return;
    }

    let html = '';
    ordenes.forEach(ot => {
        const idOdt = parseInt(ot.id_odt, 10);
        const numeroOt = escaparHtml(ot.numero_ot);
        const pa = ot.pa ? escaparHtml(ot.pa) : '-';
        const trabajo = ot.trabajo_a_realizar ? escaparHtml(ot.trabajo_a_realizar) : 'Sin descripción';
        const trabajoCorto = (trabajo.length > 60) ? trabajo.substring(0, 60) + '...' : trabajo;

        const loc = ot.nombre_localidad ? escaparHtml(ot.nombre_localidad) : '-';
        const jur = ot.nombre_jurisdiccion ? escaparHtml(ot.nombre_jurisdiccion) : '-';
        const ubicacion = (loc !== '-' || jur !== '-') ? `${loc} / ${jur}` : '-';

        const fechaIni = ot.fecha_inicio ? escaparHtml(ot.fecha_inicio) : '-';
        const fechaFin = ot.fecha_final ? escaparHtml(ot.fecha_final) : '-';
        const periodo = (fechaIni !== '-' || fechaFin !== '-') ? `${fechaIni} a ${fechaFin}` : '-';

        const estadoTexto = escaparHtml(ot.estado || 'Pendiente');
        const estadoLower = String(ot.estado || 'pendiente').toLowerCase().replace(/\s+/g, '-');
        const badgeClass = 'badge-' + estadoLower;

        let pdfHtml = '<span class="btn-pdf-none">Sin PDF</span>';
        if (ot.archivo_pdf) {
            const pdfUrl = `${baseUrl}/uploads/pa_pdfs/${encodeURIComponent(ot.archivo_pdf)}`;
            pdfHtml = `<a href="${pdfUrl}" target="_blank" class="btn-pdf-view">Ver PDF</a>`;
        }

        html += `
        <tr>
            <td><strong>${numeroOt}</strong></td>
            <td>${pa}</td>
            <td title="${trabajo}">${trabajoCorto}</td>
            <td>${ubicacion}</td>
            <td>${periodo}</td>
            <td><span class="badge ${badgeClass}">${estadoTexto}</span></td>
            <td>${pdfHtml}</td>
            <td>
                <div class="actions-cell">
                    <button type="button" class="btn-action-text view" onclick="verDetalleModal(${idOdt})">
                        Ver Detalle
                    </button>
                    ${APP_PERMISOS.editar ? `
                    <button type="button" class="btn-action-text edit" onclick="abrirModalEditar(${idOdt})">
                        Editar
                    </button>
                    <button type="button" class="btn-action-text disable" onclick="deshabilitarOdt(${idOdt}, '${numeroOt}')">
                        Deshabilitar
                    </button>
                    <button type="button" class="btn-action-text edit" onclick="abrirModalCambiarEstado(${idOdt}, '${numeroOt}', '${estadoTexto}')">
                        Cambiar Estado
                    </button>
                    `: ''}
                </div>
            </td>
        </tr>`;
    });

    tbody.innerHTML = html;
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

// Filtros y Búsqueda con Debounce
document.getElementById('buscador-odt').addEventListener('input', function() {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        cargarOrdenes(1);
    }, 350);
});

document.getElementById('filtro-estado').addEventListener('change', function() {
    cargarOrdenes(1);
});

// Modal: Nueva / Editar Orden de Trabajo (FormData con PDF, operarios y movimientos opcionales)
function llenarSelectMovimientos(idSelect, movimientos, seleccionado, textoVacio) {
    const select = document.getElementById(idSelect);
    let html = `<option value="">${escaparHtml(textoVacio)}</option>`;
    (movimientos || []).forEach(m => {
        const operario = m.nombre_operario ? ` - ${escaparHtml(m.nombre_operario)}` : '';
        const hora = m.hora ? String(m.hora).substring(0, 5) : '';
        html += `<option value="${parseInt(m.id_movimiento, 10)}">#${parseInt(m.id_movimiento, 10)} - ${escaparHtml(m.fecha)} ${hora}${operario}</option>`;
    });
    select.innerHTML = html;
    select.value = seleccionado ? String(seleccionado) : '';
}

function cargarMovimientosDisponibles(idOdt, idEgreso, idDevolucion) {
    return fetch(`dashboard.php?action=movimientos_disponibles&id_odt=${idOdt}`, { credentials: 'same-origin' })
        .then(response => response.json())
        .then(res => {
            if (!res.success) throw new Error(res.error || 'No se pudieron cargar los movimientos.');
            llenarSelectMovimientos('crear-mov-egreso', res.entregas, idEgreso, 'Sin movimiento de entrega');
            llenarSelectMovimientos('crear-mov-devolucion', res.devoluciones, idDevolucion, 'Sin movimiento de devolución');
        });
}

function abrirModalCrear() {
    document.getElementById('form-crear-odt').reset();
    document.getElementById('crear-id-odt').value = '';
    document.getElementById('crear-odt-titulo').innerText = 'Nueva Orden de Trabajo';
    document.getElementById('btn-submit-crear').innerText = 'Guardar Orden';
    cargarMovimientosDisponibles(0, null, null).catch(err => mostrarAlerta(err.message, false));
    abrirModal('modal-crear-odt');
}

function abrirModalEditar(idOdt) {
    fetch(`dashboard.php?action=detalle&id=${idOdt}`, { credentials: 'same-origin' })
        .then(response => response.json())
        .then(res => {
            if (!res.success || !res.data) throw new Error(res.error || 'No se pudo cargar la orden.');
            const o = res.data;
            const form = document.getElementById('form-crear-odt');
            form.reset();

            document.getElementById('crear-id-odt').value = o.id_odt;
            document.getElementById('crear-odt-titulo').innerText = `Editar Orden de Trabajo ${o.numero_ot || ''}`;
            document.getElementById('btn-submit-crear').innerText = 'Guardar Cambios';

            document.getElementById('crear-numero-ot').value = o.numero_ot || '';
            document.getElementById('crear-pa').value = o.pa || '';
            document.getElementById('crear-localidad').value = o.id_localidad || '';
            document.getElementById('crear-jurisdiccion').value = o.id_jurisdiccion || '';
            document.getElementById('crear-fecha-inicio').value = o.fecha_inicio || '';
            document.getElementById('crear-fecha-final').value = o.fecha_final || '';
            document.getElementById('crear-hora-inicio').value = o.hora_inicio ? String(o.hora_inicio).substring(0, 5) : '';
            document.getElementById('crear-hora-final').value = o.hora_final ? String(o.hora_final).substring(0, 5) : '';
            document.getElementById('crear-estado').value = o.estado || 'Pendiente';
            document.getElementById('crear-trabajo').value = o.trabajo_a_realizar || '';
            document.getElementById('crear-observaciones').value = o.observaciones || '';

            const asignados = (res.operarios || []).map(op => String(op.id_operario));
            Array.from(document.getElementById('crear-operarios').options).forEach(opt => {
                opt.selected = asignados.includes(opt.value);
            });

            return cargarMovimientosDisponibles(idOdt, o.id_mov_egreso, o.id_mov_devolucion);
        })
        .then(() => abrirModal('modal-crear-odt'))
        .catch(err => mostrarAlerta(err.message, false));
}

function guardarNuevaOdt(e) {
    e.preventDefault();
    const btnSubmit = document.getElementById('btn-submit-crear');
    const textoOriginal = btnSubmit.innerText;
    btnSubmit.disabled = true;
    btnSubmit.innerText = 'Guardando...';

    const form = document.getElementById('form-crear-odt');
    const formData = new FormData(form);
    const esEdicion = formData.get('id_odt') !== '';

    fetch(esEdicion ? 'editar_orden_de_trabajo.php' : 'crear_orden_de_trabajo.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(res => {
        if (!res.success) throw new Error(res.error || 'No se pudo guardar la Orden de Trabajo.');
        cerrarModal('modal-crear-odt');
        form.reset();
        mostrarAlerta(res.message, true);
        cargarOrdenes(esEdicion ? paginaActual : 1);
    })
    .catch(err => {
        mostrarAlerta(err.message, false);
    })
    .finally(() => {
        btnSubmit.disabled = false;
        btnSubmit.innerText = textoOriginal;
    });
}

// Deshabilitar (anular) una Orden de Trabajo: no se borra nada
function deshabilitarOdt(idOdt, numeroOt) {
    if (!confirm(`¿Deshabilitar la Orden de Trabajo "${numeroOt}"? El registro se conserva en el sistema.`)) {
        return;
    }
    const formData = new FormData();
    formData.append('id_odt', idOdt);

    fetch('deshabilitar_orden_de_trabajo.php', { method: 'POST', body: formData, credentials: 'same-origin' })
        .then(response => response.json())
        .then(res => {
            if (!res.success) throw new Error(res.error || 'No se pudo deshabilitar la orden.');
            mostrarAlerta(res.message, true);
            cargarOrdenes(paginaActual);
        })
        .catch(err => mostrarAlerta(err.message, false));
}

// Modal: Cambiar Estado
function abrirModalCambiarEstado(idOdt, numeroOt, estadoActual) {
    document.getElementById('estado-id-odt').value = idOdt;
    document.getElementById('estado-display-ot').value = numeroOt;
    document.getElementById('estado-nuevo').value = estadoActual;
    document.getElementById('estado-modal-subtitulo').innerText = `Actualizar estado de la orden "${numeroOt}"`;
    abrirModal('modal-cambiar-estado');
}

function guardarCambioEstado(e) {
    e.preventDefault();
    const btnSubmit = document.getElementById('btn-submit-estado');
    btnSubmit.disabled = true;
    btnSubmit.innerText = 'Actualizando...';

    const form = document.getElementById('form-cambiar-estado');
    const formData = new FormData(form);

    fetch('dashboard.php?action=cambiar_estado', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) throw new Error('Error al conectar con el servidor.');
        return response.json();
    })
    .then(res => {
        if (!res.success) throw new Error(res.error || 'No se pudo actualizar el estado.');
        cerrarModal('modal-cambiar-estado');
        mostrarAlerta(res.message, true);
        cargarOrdenes(paginaActual);
    })
    .catch(err => {
        mostrarAlerta(err.message, false);
    })
    .finally(() => {
        btnSubmit.disabled = false;
        btnSubmit.innerText = 'Actualizar Estado';
    });
}

// Modal: Ver Detalle Completo
function verDetalleModal(idOdt) {
    const contenedor = document.getElementById('detalle-odt-contenido');
    contenedor.innerHTML = '<p style="text-align:center; color:#64748b; padding:2rem;">Cargando información...</p>';
    abrirModal('modal-detalle-odt');

    fetch(`dashboard.php?action=detalle&id=${idOdt}`, { credentials: 'same-origin' })
        .then(response => {
            if (!response.ok) throw new Error('Error al conectar con el servidor.');
            return response.json();
        })
        .then(res => {
            if (!res.success || !res.data) throw new Error(res.error || 'No se pudo cargar la orden.');
            const o = res.data;

            document.getElementById('detalle-ot-titulo').innerText = `Orden de Trabajo: ${o.numero_ot || ''}`;
            document.getElementById('detalle-ot-subtitulo').innerText = `ID Sistema: #${o.id_odt || ''} | Estado: ${o.estado || 'Pendiente'}`;

            let pdfBtn = '<span class="btn-pdf-none">Sin archivo PDF adjunto</span>';
            if (o.archivo_pdf) {
                const pdfUrl = `${baseUrl}/uploads/pa_pdfs/${encodeURIComponent(o.archivo_pdf)}`;
                pdfBtn = `<a href="${pdfUrl}" target="_blank" class="btn-pdf-view">Ver PDF Adjunto</a> (${escaparHtml(o.archivo_pdf)})`;
            }

            let html = `
            <div class="detail-grid">
                <div class="detail-item">
                    <strong>Número de PA</strong>
                    <span>${o.pa ? escaparHtml(o.pa) : 'No especificado'}</span>
                </div>
                <div class="detail-item">
                    <strong>Localidad</strong>
                    <span>${o.nombre_localidad ? escaparHtml(o.nombre_localidad) : 'No asignada'}</span>
                </div>
                <div class="detail-item">
                    <strong>Jurisdicción</strong>
                    <span>${o.nombre_jurisdiccion ? escaparHtml(o.nombre_jurisdiccion) : 'No asignada'}</span>
                </div>
                <div class="detail-item">
                    <strong>Fecha Inicio</strong>
                    <span>${o.fecha_inicio ? escaparHtml(o.fecha_inicio) : '-'}</span>
                </div>
                <div class="detail-item">
                    <strong>Fecha Final</strong>
                    <span>${o.fecha_final ? escaparHtml(o.fecha_final) : '-'}</span>
                </div>
                <div class="detail-item">
                    <strong>Horario</strong>
                    <span>${o.hora_inicio ? escaparHtml(o.hora_inicio.substring(0, 5)) : '--:--'} a ${o.hora_final ? escaparHtml(o.hora_final.substring(0, 5)) : '--:--'}</span>
                </div>
            </div>

            <div style="margin-bottom: 1.25rem;">
                <label class="modal-label">Trabajo a Realizar / Descripción</label>
                <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.85rem; color: #334155; line-height: 1.5; font-size: 0.88rem;">
                    ${o.trabajo_a_realizar ? escaparHtml(o.trabajo_a_realizar).replace(/\n/g, '<br>') : 'Sin descripción detallada.'}
                </div>
            </div>

            <div style="margin-bottom: 1.25rem;">
                <label class="modal-label">Observaciones</label>
                <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.85rem; color: #334155; line-height: 1.5; font-size: 0.88rem;">
                    ${o.observaciones ? escaparHtml(o.observaciones).replace(/\n/g, '<br>') : 'Sin observaciones registradas.'}
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label class="modal-label">Expediente Oficial PDF</label>
                <div>${pdfBtn}</div>
            </div>`;

            const operarios = res.operarios || [];
            const movimientos = res.movimientos || [];
            const listaOperarios = operarios.length
                ? '<ul style="margin: 0.3rem 0 0 1.2rem;">' + operarios.map(op => `<li>${escaparHtml(op.apellido + ', ' + op.nombre)} (DNI ${escaparHtml(op.dni)})</li>`).join('') + '</ul>'
                : '<span style="color:#64748b;">Sin operarios asignados.</span>';
            const listaMovimientos = movimientos.length
                ? '<ul style="margin: 0.3rem 0 0 1.2rem;">' + movimientos.map(m => `<li>${m.rol_en_odt === 'egreso' ? 'Entrega' : 'Devolución'}: <a href="../movimiento/detalle_movimiento.php?id=${parseInt(m.id_movimiento, 10)}">Movimiento #${parseInt(m.id_movimiento, 10)}</a> - ${escaparHtml(m.fecha)}</li>`).join('') + '</ul>'
                : '<span style="color:#64748b;">Sin movimientos vinculados.</span>';

            html += `
            <div style="margin-bottom: 1rem;">
                <label class="modal-label">Operarios asignados (${operarios.length})</label>
                <div>${listaOperarios}</div>
            </div>
            <div style="margin-bottom: 1rem;">
                <label class="modal-label">Movimientos vinculados</label>
                <div>${listaMovimientos}</div>
            </div>`;

            contenedor.innerHTML = html;
        })
        .catch(err => {
            contenedor.innerHTML = `<p style="text-align:center; color:#dc2626; padding:2rem;">Error: ${escaparHtml(err.message)}</p>`;
        });
}

// Cierre de modales con tecla ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        cerrarModal('modal-crear-odt');
        cerrarModal('modal-cambiar-estado');
        cerrarModal('modal-detalle-odt');
    }
});
</script>

<?php
include_once __DIR__ . '/../layout/footer.php';
?>
