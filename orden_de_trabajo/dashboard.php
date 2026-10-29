<?php
/**
 * orden_de_trabajo/dashboard.php
 * Panel principal del módulo de Órdenes de Trabajo.
 *
 * - Datos mediante la clase OrdenDeTrabajo (getAll, getById, update, getLocalidades, getJurisdicciones).
 * - Filtrado por texto/estado y paginación en PHP sobre el listado de la clase.
 * - Acciones AJAX integradas en este archivo:
 *     GET  ?action=detalle&id=N       -> datos completos de una orden (modal "Ver Detalle")
 *     POST ?action=cambiar_estado     -> actualiza el estado de una orden (selector de estado en la fila)
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
$js_modulo = 'autocompletado'; // comisión de operarios: autocompletado con etiquetas (se busca por AJAX)
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
// 'estado' es el estado del trabajo (Pendiente, En Progreso...); 'registro' indica si la orden está habilitada o deshabilitada
$registro = isset($_GET['registro']) ? trim((string)$_GET['registro']) : 'activo';

$res = $odtModel->getPaginado($busqueda, $estado, $registro, $paginaActual, $porPagina);
$ordenes = $res['filas'];
$totalRegistros = $res['total'];
$totalPaginas = $res['paginas'];
$paginaActual = $res['pagina'];

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

$estadosPosibles = $odtModel->getEstadosPosibles();
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

    <select class="filter-select" id="filtro-registro">
        <option value="activo" <?= ($registro === 'activo') ? 'selected' : '' ?>>Solo Habilitadas</option>
        <option value="inactivo" <?= ($registro === 'inactivo') ? 'selected' : '' ?>>Solo Deshabilitadas</option>
        <option value="todos" <?= ($registro === 'todos') ? 'selected' : '' ?>>Todas</option>
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
                <tr class="fila-clic">
                    <td><strong><?= $numeroOt ?></strong></td>
                    <td><?= $pa ?></td>
                    <td title="<?= $trabajo ?>"><?= $trabajoCorto ?></td>
                    <td><?= $ubicacion ?></td>
                    <td><?= $periodo ?></td>
                    <td>
                        <?php if (canEdit() && $ot['activo']): ?>
                        <select class="badge badge-select <?= $badgeClass ?>" data-prev="<?= $estadoTexto ?>"
                                onchange="cambiarEstadoDirecto(this, <?= $idOdt ?>)" title="Cambiar estado de la orden">
                            <?php foreach (array_keys($estadosPosibles) as $est): ?>
                            <option value="<?= htmlspecialchars($est, ENT_QUOTES, 'UTF-8') ?>" <?= ($ot['estado'] ?? 'Pendiente') === $est ? 'selected' : '' ?>><?= htmlspecialchars($est, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <span class="badge <?= $badgeClass ?>"><?= $estadoTexto ?></span>
                        <?php endif; ?>
                        <?php if (!$ot['activo']): ?><span class="badge badge-anulada">Deshabilitada</span><?php endif; ?>
                    </td>
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
                            <?php if ($ot['activo']): ?>
                            <a href="detalle_orden_de_trabajo.php?id=<?= $idOdt ?>" class="btn-action-icon view" title="Ver Detalle">
                                <?= iconoAccion('view') ?>
                            </a>
                            <?php endif; ?>
                            <?php if (canEdit()): ?>
                            <?php if ($ot['activo']): ?>
                            <?php if (!in_array($ot['estado'] ?? '', ['Finalizada', 'Anulada'], true)): ?>
                            <a href="../movimiento/crear_movimiento.php?tipo=entrega&amp;id_odt=<?= $idOdt ?>" class="btn-action-icon dispatch" title="Registrar Entrega para esta orden"><?= iconoAccion('dispatch') ?></a>
                            <?php endif; ?>
                            <a href="../movimiento/crear_movimiento.php?tipo=devolucion&amp;id_odt=<?= $idOdt ?>" class="btn-action-icon return" title="Registrar Devolución de esta orden"><?= iconoAccion('return') ?></a>
                            <button type="button" class="btn-action-icon edit" title="Editar Orden" onclick="abrirModalEditar(<?= $idOdt ?>)">
                                <?= iconoAccion('edit') ?>
                            </button>
                            <button type="button" class="btn-action-icon disable" title="Deshabilitar Orden" data-id="<?= $idOdt ?>" data-numero="<?= $numeroOt ?>"
                                    onclick="cambiarRegistroOdt(this.dataset.id, this.dataset.numero, true)">
                                <?= iconoAccion('disable') ?>
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn-action-icon enable" title="Habilitar Orden" data-id="<?= $idOdt ?>" data-numero="<?= $numeroOt ?>"
                                    onclick="cambiarRegistroOdt(this.dataset.id, this.dataset.numero, false)">
                                <?= iconoAccion('enable') ?>
                            </button>
                            <?php endif; ?>
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
<div class="pagination-container" id="contenedor-paginacion" data-total="<?= (int)$totalPaginas ?>" data-actual="<?= (int)$paginaActual ?>"></div>

<!-- ========================================================================= -->
<!-- MODALES -->
<!-- ========================================================================= -->

<!-- Modal 1: Nueva Orden de Trabajo -->
<div id="modal-crear-odt" class="modal-overlay">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-crear-odt')" title="Cerrar" aria-label="Cerrar"><?= iconoAccion('close', 18) ?></button>
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

            <div class="mb-16">
                <label class="modal-label" for="crear-buscar-operarios">Operarios asignados (comisión)</label>
                <div class="autocomplete-wrapper">
                    <input type="text" id="crear-buscar-operarios" class="modal-input" placeholder="Escriba DNI, apellido o nombre para agregar..." autocomplete="off">
                    <div id="crear-lista-operarios" class="autocomplete-list"></div>
                </div>
                <div id="crear-operarios-elegidos" class="chips-seleccion"></div>
                <small class="texto-tenue">Busque y elija a cada operario; puede agregar varios y quitarlos con la x.</small>
            </div>

            <div class="mb-16">
                <label class="modal-label" for="crear-trabajo">Trabajo a Realizar / Descripción</label>
                <textarea id="crear-trabajo" name="trabajo_a_realizar" class="modal-textarea" placeholder="Describa las tareas a ejecutar..."></textarea>
            </div>

            <div class="mb-16">
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
    const registro = encodeURIComponent(document.getElementById('filtro-registro').value);

    const url = `dashboard.php?ajax=1&pagina=${pagina}&busqueda=${busqueda}&estado=${estado}&registro=${registro}`;

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
        const otActiva = (ot.activo === true || ot.activo === 't' || ot.activo === 1 || ot.activo === '1');
        const badgeDeshabilitada = otActiva ? '' : '<span class="badge badge-anulada">Deshabilitada</span>';

        // Con permiso de edicion y orden habilitada, el estado se cambia desde la propia fila
        const celdaEstado = (APP_PERMISOS.editar && otActiva)
            ? `<select class="badge badge-select ${badgeClass}" data-prev="${estadoTexto}" onchange="cambiarEstadoDirecto(this, ${idOdt})" title="Cambiar estado de la orden">${opcionesEstado(ot.estado || 'Pendiente')}</select>`
            : `<span class="badge ${badgeClass}">${estadoTexto}</span>`;

        // El número viaja en data-* (escapado) para que un apóstrofo no rompa el onclick
        let botones = '';
        if (otActiva) {
            botones = `
                    <a href="detalle_orden_de_trabajo.php?id=${idOdt}" class="btn-action-icon view" title="Ver Detalle">
                        ${window.APP_ICONS.view}
                    </a>
                    ${APP_PERMISOS.editar ? `
                    ${(ot.estado === 'Finalizada' || ot.estado === 'Anulada') ? '' : `<a href="../movimiento/crear_movimiento.php?tipo=entrega&id_odt=${idOdt}" class="btn-action-icon dispatch" title="Registrar Entrega para esta orden">${window.APP_ICONS.dispatch}</a>`}
                    <a href="../movimiento/crear_movimiento.php?tipo=devolucion&id_odt=${idOdt}" class="btn-action-icon return" title="Registrar Devolución de esta orden">${window.APP_ICONS.return}</a>
                    <button type="button" class="btn-action-icon edit" title="Editar Orden" onclick="abrirModalEditar(${idOdt})">
                        ${window.APP_ICONS.edit}
                    </button>
                    <button type="button" class="btn-action-icon disable" title="Deshabilitar Orden" data-id="${idOdt}" data-numero="${numeroOt}" onclick="cambiarRegistroOdt(this.dataset.id, this.dataset.numero, true)">
                        ${window.APP_ICONS.disable}
                    </button>
                    `: ''}`;
        } else if (APP_PERMISOS.editar) {
            botones = `
                    <button type="button" class="btn-action-icon enable" title="Habilitar Orden" data-id="${idOdt}" data-numero="${numeroOt}" onclick="cambiarRegistroOdt(this.dataset.id, this.dataset.numero, false)">
                        ${window.APP_ICONS.enable}
                    </button>`;
        }

        let pdfHtml = '<span class="btn-pdf-none">Sin PDF</span>';
        if (ot.archivo_pdf) {
            const pdfUrl = `${baseUrl}/uploads/pa_pdfs/${encodeURIComponent(ot.archivo_pdf)}`;
            pdfHtml = `<a href="${pdfUrl}" target="_blank" class="btn-pdf-view">Ver PDF</a>`;
        }

        html += `
        <tr class="fila-clic">
            <td><strong>${numeroOt}</strong></td>
            <td>${pa}</td>
            <td title="${trabajo}">${trabajoCorto}</td>
            <td>${ubicacion}</td>
            <td>${periodo}</td>
            <td>${celdaEstado} ${badgeDeshabilitada}</td>
            <td>${pdfHtml}</td>
            <td>
                <div class="actions-cell">${botones}
                </div>
            </td>
        </tr>`;
    });

    tbody.innerHTML = html;
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

document.getElementById('filtro-registro').addEventListener('change', function() {
    cargarOrdenes(1);
});

// Comisión de operarios: autocompletado con etiquetas (assets/js/autocompletado.js, se carga al final de la página)
let selectorOperarios = null;
document.addEventListener('DOMContentLoaded', function () {
    selectorOperarios = crearAutocompletadoMultiple({
        input: document.getElementById('crear-buscar-operarios'),
        lista: document.getElementById('crear-lista-operarios'),
        etiquetas: document.getElementById('crear-operarios-elegidos'),
        nombreCampo: 'operarios[]',
        url: '../ajax/buscar_autocompletado.php',
        parametros: { recurso: 'operario' }
    });
});

// Modal: Nueva / Editar Orden de Trabajo (FormData con PDF y operarios)
function abrirModalCrear() {
    document.getElementById('form-crear-odt').reset();
    selectorOperarios.limpiar();
    document.getElementById('crear-id-odt').value = '';
    document.getElementById('crear-odt-titulo').innerText = 'Nueva Orden de Trabajo';
    document.getElementById('btn-submit-crear').innerText = 'Guardar Orden';
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
            selectorOperarios.limpiar();

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

            (res.operarios || []).forEach(op => {
                selectorOperarios.agregar(op.id_operario, `${op.apellido}, ${op.nombre} - DNI: ${op.dni}`);
            });
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
        selectorOperarios.limpiar();
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

// Deshabilitar (anular) o volver a habilitar una Orden de Trabajo: no se borra nada
function cambiarRegistroOdt(idOdt, numeroOt, deshabilitar) {
    const pregunta = deshabilitar
        ? `¿Deshabilitar la Orden de Trabajo "${numeroOt}"? El registro se conserva en el sistema.`
        : `¿Volver a habilitar la Orden de Trabajo "${numeroOt}"?`;
    if (!confirm(pregunta)) {
        return;
    }
    const formData = new FormData();
    formData.append('id_odt', idOdt);
    formData.append('accion', deshabilitar ? 'deshabilitar' : 'habilitar');

    fetch('deshabilitar_orden_de_trabajo.php', { method: 'POST', body: formData, credentials: 'same-origin' })
        .then(response => response.json())
        .then(res => {
            if (!res.success) throw new Error(res.error || 'No se pudo procesar la acción sobre la orden.');
            mostrarAlerta(res.message, true);
            cargarOrdenes(paginaActual);
        })
        .catch(err => mostrarAlerta(err.message, false));
}

// Opciones del selector de estado (los estados salen de OrdenDeTrabajo::getEstadosPosibles)
const ESTADOS_ODT = <?= json_encode(array_keys($estadosPosibles), JSON_HEX_TAG | JSON_HEX_AMP) ?>;

function opcionesEstado(actual) {
    const lista = ESTADOS_ODT.includes(actual) ? ESTADOS_ODT : [actual].concat(ESTADOS_ODT);
    return lista.map(est => `<option value="${escaparHtml(est)}" ${est === actual ? 'selected' : ''}>${escaparHtml(est)}</option>`).join('');
}

// Cambio de estado desde el selector de la fila (mismo endpoint de siempre: dashboard.php?action=cambiar_estado)
function cambiarEstadoDirecto(select, idOdt) {
    const anterior = select.dataset.prev;
    const nuevo = select.value;
    if (nuevo === anterior) return;

    const formData = new FormData();
    formData.append('id_odt', idOdt);
    formData.append('estado', nuevo);
    select.disabled = true;

    fetch('dashboard.php?action=cambiar_estado', { method: 'POST', body: formData, credentials: 'same-origin' })
        .then(response => {
            if (!response.ok) throw new Error('Error al conectar con el servidor.');
            return response.json();
        })
        .then(res => {
            if (!res.success) throw new Error(res.error || 'No se pudo actualizar el estado.');
            mostrarAlerta(res.message, true);
            cargarOrdenes(paginaActual);
        })
        .catch(err => {
            select.value = anterior;
            select.disabled = false;
            mostrarAlerta(err.message, false);
        });
}

// Cierre de modales con tecla ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        cerrarModal('modal-crear-odt');
    }
});
</script>

<?php
include_once __DIR__ . '/../layout/footer.php';
?>
