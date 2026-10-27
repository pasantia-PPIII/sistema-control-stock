<?php
/**
 * orden_de_trabajo/dashboard.php
 * Panel principal del módulo de Órdenes de Trabajo.
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
    $idOdt = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $orden = $idOdt > 0 ? $odtModel->getById($idOdt, true) : false;

    if (!$orden) {
        echo json_encode(['success' => false, 'error' => "No se encontró la Orden de Trabajo #{$idOdt}."]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => $orden,
        'operarios' => $odtModel->getOperariosAsignados($idOdt),
        'movimientos' => $odtModel->getMovimientosVinculados($idOdt)
    ]);
    exit;
}

// =========================================================================
// ACCIÓN AJAX: RECURSOS PARA MODAL DE SALIDA (Operarios, Herramientas e Insumos)
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] === 'recursos_salida') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $pdo = Database::getConnection();

        // 1. Operarios activos (id_operario, nombre, apellido, dni) con activo = TRUE
        $stmtOp = $pdo->query("
            SELECT id_operario, nombre, apellido, dni 
            FROM operario 
            WHERE activo = TRUE 
            ORDER BY apellido ASC, nombre ASC
        ");
        $operarios = $stmtOp->fetchAll(PDO::FETCH_ASSOC);

        // 2. Herramientas disponibles asegurando compatibilidad con el esquema de PostgreSQL
        $herramientas = [];
        try {
            // Consulta normalizada: insumos + tipo + estado_herramienta + ubicacion
            $stmtHer = $pdo->query("
                SELECT i.id_insumo AS id, i.codigo, i.nombre, COALESCE(u.ubicacion, '-') AS ubicacion 
                FROM insumos i
                LEFT JOIN tipo t ON i.id_tipo = t.id_tipo
                LEFT JOIN ubicacion u ON i.id_ubicacion = u.id_ubicacion
                LEFT JOIN estado_herramienta eh ON i.id_estado_herramienta = eh.id_estado_herramienta
                WHERE (t.tipo ILIKE 'Herramienta' OR i.id_tipo = 2)
                  AND i.activo = TRUE
                  AND (eh.estado ILIKE 'Disponible' OR eh.estado IS NULL OR i.id_estado_herramienta = 1 OR i.id_estado_herramienta IS NULL)
                ORDER BY i.nombre ASC
            ");
            $herramientas = $stmtHer->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $eH1) {
            $herramientas = [];
        }

        if (empty($herramientas)) {
            try {
                // Intentar consultar sobre 'insumos' con columnas directas
                $stmtHer = $pdo->query("
                    SELECT COALESCE(id_insumo, id) AS id, codigo, nombre, COALESCE(ubicacion, '-') AS ubicacion 
                    FROM insumos 
                    WHERE (tipo = 'herramienta' OR tipo ILIKE 'herramienta')
                      AND (estado_herramienta = 'Disponible' OR estado_herramienta ILIKE 'Disponible' OR estado_herramienta IS NULL)
                    ORDER BY nombre ASC
                ");
                $herramientas = $stmtHer->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $eH2) {
                // Si falla, consultar sobre la tabla relacional 'herramienta' haciendo JOIN con 'estado_herramienta' y 'ubicacion'
                $stmtHer = $pdo->query("
                    SELECT h.id_herramienta AS id, 
                           COALESCE(i.codigo, 'HER-' || h.id_herramienta) AS codigo, 
                           COALESCE(i.nombre, 'Herramienta #' || h.id_herramienta) AS nombre, 
                           COALESCE(u.ubicacion, '-') AS ubicacion
                    FROM herramienta h
                    LEFT JOIN insumos i ON h.id_herramienta = i.id_insumo
                    LEFT JOIN ubicacion u ON i.id_ubicacion = u.id_ubicacion
                    LEFT JOIN estado_herramienta eh ON h.id_estado_herramienta = eh.id_estado_herramienta
                    WHERE (eh.estado ILIKE 'Disponible' OR eh.estado IS NULL OR h.id_estado_herramienta = 1 OR h.id_estado_herramienta IS NULL)
                      AND h.activo = TRUE
                    ORDER BY nombre ASC
                ");
                $herramientas = $stmtHer->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        // 3. Materiales consumibles con stock > 0
        $materiales = [];
        try {
            // Consulta normalizada: insumos + tipo + unidad_medida + stock_actual
            $stmtMat = $pdo->query("
                SELECT i.id_insumo AS id, i.codigo, i.nombre, 
                       COALESCE(um.unidad_medida, 'Unidad') AS unidad_medida, 
                       COALESCE(sa.stock_actual, 0) AS stock_actual
                FROM insumos i
                LEFT JOIN tipo t ON i.id_tipo = t.id_tipo
                LEFT JOIN unidad_medida um ON i.id_unidad_medida = um.id_unidad_medida
                LEFT JOIN stock_actual sa ON i.id_stock_actual = sa.id_stock_actual
                WHERE (t.tipo ILIKE 'Material' OR i.id_tipo = 1)
                  AND i.activo = TRUE
                  AND COALESCE(sa.stock_actual, 0) > 0
                ORDER BY i.nombre ASC
            ");
            $materiales = $stmtMat->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $eM1) {
            $materiales = [];
        }

        if (empty($materiales)) {
            // Esquema plano: insumos con columna directa stock_actual
            $stmtMat = $pdo->query("
                SELECT COALESCE(id_insumo, id) AS id, codigo, nombre, 
                       COALESCE(unidad_medida, 'Unidad') AS unidad_medida, 
                       stock_actual
                FROM insumos 
                WHERE (tipo = 'material' OR tipo ILIKE 'material' OR tipo IS NULL) 
                  AND stock_actual > 0
                ORDER BY nombre ASC
            ");
            $materiales = $stmtMat->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'success'      => true,
            'operarios'    => $operarios,
            'herramientas' => $herramientas,
            'materiales'   => $materiales
        ]);
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false,
            'error'   => 'Error en recursos_salida: ' . $e->getMessage()
        ]);
    }
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

        $idOdt = isset($_POST['id_odt']) ? (int) $_POST['id_odt'] : 0;
        $nuevoEstado = isset($_POST['estado']) ? trim((string) $_POST['estado']) : '';

        if ($idOdt <= 0) {
            throw new Exception('El identificador de la Orden de Trabajo es inválido.');
        }

        $orden = $odtModel->getById($idOdt);
        if (!$orden) {
            throw new Exception("La Orden de Trabajo #{$idOdt} no existe.");
        }

        $camposEditables = [
            'numero_ot',
            'pa',
            'trabajo_a_realizar',
            'id_localidad',
            'id_jurisdiccion',
            'fecha_inicio',
            'fecha_final',
            'hora_inicio',
            'hora_final',
            'observaciones',
            'archivo_pdf'
        ];
        $datos = array_intersect_key($orden, array_flip($camposEditables));
        $datos['estado'] = $nuevoEstado;

        if (!$odtModel->update($idOdt, $datos)) {
            throw new Exception('No se pudo actualizar el estado de la Orden de Trabajo.');
        }

        echo json_encode([
            'success' => true,
            'message' => "El estado se actualizó a '{$nuevoEstado}'.",
            'id_odt' => $idOdt,
            'nuevo_estado' => $nuevoEstado
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => safeErrorMessage($e)]);
    }
    exit;
}

// =========================================================================
// FILTROS Y PAGINACIÓN
// =========================================================================
$porPagina = 10;
$paginaActual = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
$busqueda = isset($_GET['busqueda']) ? trim((string) $_GET['busqueda']) : '';
$estado = isset($_GET['estado']) ? trim((string) $_GET['estado']) : '';

$mostrarInactivas = ($estado === 'Deshabilitadas');
$ordenesFiltradas = $odtModel->getFiltrados($busqueda, $estado, $mostrarInactivas);

$totalRegistros = count($ordenesFiltradas);
$totalPaginas = max(1, (int) ceil($totalRegistros / $porPagina));
$paginaActual = min($paginaActual, $totalPaginas);
$ordenes = array_slice($ordenesFiltradas, ($paginaActual - 1) * $porPagina, $porPagina);

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'data' => $ordenes,
        'total_registros' => $totalRegistros,
        'total_paginas' => $totalPaginas,
        'pagina_actual' => $paginaActual
    ]);
    exit;
}

$localidades = $odtModel->getLocalidades();
$jurisdicciones = $odtModel->getJurisdicciones();

include_once __DIR__ . '/../layout/header.php';
?>

<!-- Contenedor de Alertas Dinámicas -->
<div id="alerta-odt" class="alerta-mensaje"></div>

<!-- Barra de Filtros, Búsqueda y Botón Cargar Orden -->
<div class="action-bar-filters">
    <div class="search-input-wrapper">
        <input type="text" id="buscador-odt" class="search-input" placeholder="Buscar por N° OT, PA o trabajo..."
            value="<?= htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <select class="filter-select" id="filtro-estado">
        <option value="">Todos los Estados</option>
        <option value="Pendiente" <?= ($estado === 'Pendiente') ? 'selected' : '' ?>>Pendiente</option>
        <option value="En Proceso" <?= ($estado === 'En Proceso' || $estado === 'En Progreso') ? 'selected' : '' ?>>En
            Proceso</option>
        <option value="Pausada" <?= ($estado === 'Pausada') ? 'selected' : '' ?>>Pausada</option>
        <option value="Finalizada" <?= ($estado === 'Finalizada') ? 'selected' : '' ?>>Finalizada</option>
        <option value="Anulada" <?= ($estado === 'Anulada') ? 'selected' : '' ?>>Anulada</option>
        <option value="Deshabilitadas" <?= ($estado === 'Deshabilitadas') ? 'selected' : '' ?>>Solo Deshabilitadas</option>
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
                <th class="col-id">Identificación</th>
                <th class="col-trabajo">Trabajo a Realizar</th>
                <th class="col-ubicacion">Localidad / Jurisdicción</th>
                <th class="col-periodo">Período</th>
                <th class="col-estado">Estado</th>
                <th class="col-pdf">PDF</th>
                <th class="col-acciones">Acciones</th>
            </tr>
        </thead>
        <tbody id="tabla-odt-body">
            <?php if (empty($ordenes)): ?>
                <tr>
                    <td colspan="7" class="empty-state">No se encontraron órdenes de trabajo registradas con los criterios seleccionados.</td>
                </tr>
            <?php else: ?>
                <?php 
                $estadosPosibles = ['Pendiente', 'En Proceso', 'Pausada', 'Finalizada', 'Anulada'];
                foreach ($ordenes as $ot):
                    $idOdt = (int) $ot['id_odt'];
                    $numeroOt = htmlspecialchars($ot['numero_ot'], ENT_QUOTES, 'UTF-8');
                    $pa = !empty($ot['pa']) ? htmlspecialchars($ot['pa'], ENT_QUOTES, 'UTF-8') : '-';
                    $trabajo = !empty($ot['trabajo_a_realizar']) ? htmlspecialchars($ot['trabajo_a_realizar'], ENT_QUOTES, 'UTF-8') : 'Sin descripción';
                    $trabajoCorto = (mb_strlen($trabajo) > 60) ? mb_substr($trabajo, 0, 60) . '...' : $trabajo;

                    $loc = !empty($ot['nombre_localidad']) ? htmlspecialchars($ot['nombre_localidad'], ENT_QUOTES, 'UTF-8') : '-';
                    $jur = !empty($ot['nombre_jurisdiccion']) ? htmlspecialchars($ot['nombre_jurisdiccion'], ENT_QUOTES, 'UTF-8') : '-';
                    $ubicacion = ($loc !== '-' || $jur !== '-') ? "{$loc} / {$jur}" : '-';

                    $fechaIni = !empty($ot['fecha_inicio']) ? htmlspecialchars($ot['fecha_inicio'], ENT_QUOTES, 'UTF-8') : '-';
                    $fechaFin = !empty($ot['fecha_final']) ? htmlspecialchars($ot['fecha_final'], ENT_QUOTES, 'UTF-8') : '';

                    $estadoTexto = htmlspecialchars($ot['estado'] ?? 'Pendiente', ENT_QUOTES, 'UTF-8');
                    $badgeClass = 'badge-' . strtolower(str_replace(' ', '-', trim($ot['estado'] ?? 'pendiente')));

                    $tienePdf = !empty($ot['archivo_pdf']);
                    $nombrePdf = $tienePdf ? htmlspecialchars($ot['archivo_pdf'], ENT_QUOTES, 'UTF-8') : '';
                    $estaActiva = ($ot['activo'] === true || $ot['activo'] === 1 || $ot['activo'] === '1' || $ot['activo'] === 't');
                    ?>
                    <tr>
                        <td class="col-id">
                            <div class="cell-id-ot"><?= $numeroOt ?></div>
                            <div class="cell-id-pa"><?= ($pa !== '-' && $pa !== '') ? $pa : 'Sin PA' ?></div>
                        </td>
                        <td class="col-trabajo">
                            <div class="cell-multiline-2" title="<?= $trabajo ?>"><?= $trabajo ?></div>
                        </td>
                        <td class="col-ubicacion" title="<?= ($loc !== '-' || $jur !== '-') ? "{$loc} / {$jur}" : '-' ?>">
                            <div class="cell-localidad-ciudad"><?= $loc ?></div>
                            <div class="cell-localidad-edificio"><?= $jur ?></div>
                        </td>
                        <td class="col-periodo">
                            <div class="cell-periodo-ini"><?= $fechaIni ?></div>
                            <div class="cell-periodo-fin"><?= ($fechaFin !== '-' && $fechaFin !== '') ? 'al ' . $fechaFin : '' ?></div>
                        </td>
                        <td class="col-estado">
                            <select class="badge-select badge <?= $badgeClass ?>"
                                data-prev="<?= $estadoTexto ?>"
                                onchange="cambiarEstadoDirecto(this, <?= $idOdt ?>)"
                                <?= (canEdit() && $estaActiva) ? '' : 'disabled' ?>
                                title="Cambiar estado de la orden">
                                <?php foreach ($estadosPosibles as $est): ?>
                                    <option value="<?= $est ?>" <?= ($estadoTexto === $est || ($est === 'En Proceso' && estadoTexto === 'En Progreso')) ? 'selected' : '' ?>>
                                        <?= $est ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="col-pdf cell-center">
                            <?php if ($tienePdf): ?>
                                <a href="<?= rtrim(BASE_URL, "/") ?>/uploads/pa_pdfs/<?= $nombrePdf ?>" target="_blank"
                                    class="btn-pdf-view" title="Ver archivo PDF adjunto">
                                    Ver
                                </a>
                            <?php else: ?>
                                <span class="cell-pdf-none">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="col-acciones" onclick="event.stopPropagation()">
                            <div class="actions-cell">
                                <button type="button" class="btn-action-icon view" title="Ver Detalle" onclick="verDetalleModal(<?= $idOdt ?>)">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                                <?php if (canEdit()): ?>
                                    <?php if ($estaActiva): ?>
                                        <button type="button" class="btn-action-icon dispatch" title="Registrar Salida / Despachar Comisión"
                                            onclick="abrirModalSalida(<?= $idOdt ?>, '<?= $numeroOt ?>')">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M16 17l5-5-5-5"></path>
                                                <path d="M21 12H9"></path>
                                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                            </svg>
                                        </button>
                                        <button type="button" class="btn-action-icon edit" title="Editar Solicitud" onclick="abrirModalEditar(<?= $idOdt ?>)">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                        </button>
                                        <button type="button" class="btn-action-icon disable" title="Deshabilitar Orden"
                                            onclick="deshabilitarOdt(<?= $idOdt ?>, '<?= $numeroOt ?>')">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                                <line x1="14" y1="11" x2="14" y2="17"></line>
                                            </svg>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn-action-icon enable" title="Reintegrar Orden"
                                            onclick="reintegrarOdt(<?= $idOdt ?>, '<?= $numeroOt ?>')">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="1 4 1 10 7 10"></polyline>
                                                <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                                            </svg>
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
<div class="pagination-container" id="contenedor-paginacion">
    <?php if ($totalPaginas > 1): ?>
        <button type="button" class="pagination-btn text-label" onclick="cambiarPagina(<?= max(1, $paginaActual - 1) ?>)"
            <?= ($paginaActual <= 1) ? 'disabled' : '' ?>>
            Anterior
        </button>
        <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
            <button type="button" class="pagination-btn <?= ($p === $paginaActual) ? 'active' : '' ?>"
                onclick="cambiarPagina(<?= $p ?>)">
                <?= $p ?>
            </button>
        <?php endfor; ?>
        <button type="button" class="pagination-btn text-label"
            onclick="cambiarPagina(<?= min($totalPaginas, $paginaActual + 1) ?>)" <?= ($paginaActual >= $totalPaginas) ? 'disabled' : '' ?>>
            Siguiente
        </button>
    <?php endif; ?>
</div>

<!-- ========================================================================= -->
<!-- MODALES -->
<!-- ========================================================================= -->

<!-- Modal 1: Cargar / Editar Orden con PDF Oficial -->
<div id="modal-crear-odt" class="modal-overlay">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-crear-odt')"
            title="Cerrar">Cerrar</button>
        <h2 class="modal-title" id="crear-odt-titulo">Cargar Solicitud de Orden</h2>
        <p class="modal-subtitle">Ingrese los datos de cabecera y adjunte el expediente PDF oficial recibido</p>

        <form id="form-crear-odt" onsubmit="guardarNuevaOdt(event)" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" id="crear-id-odt" name="id_odt" value="">

            <div class="form-grid-2">
                <div>
                    <label class="modal-label" for="crear-numero-ot">Número de OT *</label>
                    <input type="text" id="crear-numero-ot" name="numero_ot" class="modal-input" required
                        placeholder="Ej: OT-2026-004">
                </div>
                <div>
                    <label class="modal-label" for="crear-pa">Número de PA / Expediente</label>
                    <input type="text" id="crear-pa" name="pa" class="modal-input" placeholder="Ej: PA-2026-004">
                </div>
            </div>

            <div class="form-grid-2">
                <div>
                    <label class="modal-label" for="crear-localidad">Localidad</label>
                    <select id="crear-localidad" name="id_localidad" class="modal-select">
                        <option value="">Seleccione una localidad...</option>
                        <?php foreach ($localidades as $loc): ?>
                            <option value="<?= (int) $loc['id_localidad'] ?>">
                                <?= htmlspecialchars($loc['localidad'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="modal-label" for="crear-jurisdiccion">Jurisdicción / Edificio</label>
                    <select id="crear-jurisdiccion" name="id_jurisdiccion" class="modal-select">
                        <option value="">Seleccione una jurisdicción...</option>
                        <?php foreach ($jurisdicciones as $jur): ?>
                            <option value="<?= (int) $jur['id_jurisdiccion'] ?>">
                                <?= htmlspecialchars($jur['jurisdiccion'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-grid-2">
                <div>
                    <label class="modal-label" for="crear-fecha-inicio">Fecha de Inicio Estimada</label>
                    <input type="date" id="crear-fecha-inicio" name="fecha_inicio" class="modal-input">
                </div>
                <div>
                    <label class="modal-label" for="crear-fecha-final">Fecha de Finalización Estimada</label>
                    <input type="date" id="crear-fecha-final" name="fecha_final" class="modal-input">
                </div>
            </div>

            <div class="form-grid-2">
                <div>
                    <label class="modal-label" for="crear-estado">Estado</label>
                    <select id="crear-estado" name="estado" class="modal-select">
                        <option value="Pendiente" selected>Pendiente</option>
                        <option value="En Proceso">En Proceso</option>
                        <option value="Pausada">Pausada</option>
                        <option value="Finalizada">Finalizada</option>
                    </select>
                </div>
                <div>
                    <label class="modal-label" for="crear-archivo-pdf">Expediente Oficial (PDF) *</label>
                    <input type="file" id="crear-archivo-pdf" name="archivo_pdf" class="modal-input"
                        accept=".pdf,application/pdf">
                </div>
            </div>

            <div class="form-group-mb">
                <label class="modal-label" for="crear-trabajo">Trabajo / Motivo a Realizar *</label>
                <textarea id="crear-trabajo" name="trabajo_a_realizar" class="modal-textarea" required
                    placeholder="Descripción o título del trabajo requerido en el expediente..."></textarea>
            </div>

            <div class="form-group-mb">
                <label class="modal-label" for="crear-observaciones">Observaciones adicionales</label>
                <textarea id="crear-observaciones" name="observaciones" class="modal-textarea"
                    placeholder="Notas internas..."></textarea>
            </div>

            <div class="modal-actions-row">
                <button type="button" class="btn-modal-cancel"
                    onclick="cerrarModal('modal-crear-odt')">Cancelar</button>
                <button type="submit" class="btn-modal-submit" id="btn-submit-crear">Guardar Solicitud</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Registrar Salida a Obra / Comisión (Diseño Hoja Membretada) -->
<div id="modal-salida-comision" class="modal-overlay">
    <div class="modal-card modal-card-comision">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-salida-comision')">Cerrar</button>

        <!-- Encabezado Membretado Institucional -->
        <div class="modal-header-institucional">
            <div>
                <span class="modal-subtitulo-sup">Dirección de Arquitectura y Mantenimiento</span>
                <h2 class="modal-titulo-inst">Poder Judicial de Corrientes</h2>
                <strong class="modal-subtitulo-inst">Información de Trabajo y Comisión</strong>
            </div>
            <div class="modal-header-right">
                <span class="modal-expediente-lbl">Expediente Asociado</span>
                <span id="papel-pa-display" class="modal-expediente-val">PA-XXXX-XXXX</span>
            </div>
        </div>

        <form id="form-salida-comision" onsubmit="guardarSalidaComision(event)">
            <?= csrfField() ?>
            <input type="hidden" id="salida-id-odt" name="id_odt">

            <!-- Fila 1: Localidad y Dependencia -->
            <div class="form-grid-2 form-group-mb-sm">
                <div>
                    <label class="modal-label">Localidad</label>
                    <input type="text" id="papel-localidad" class="modal-input input-readonly" readonly>
                </div>
                <div>
                    <label class="modal-label">Dependencia / Jurisdicción</label>
                    <input type="text" id="papel-dependencia" class="modal-input input-readonly" readonly>
                </div>
            </div>

            <!-- Fila 2: Fechas y Horarios de Inicio y Terminación -->
            <div class="form-grid-2 fechas-comision-box">
                <div class="grid-2-col">
                    <div>
                        <label class="modal-label">Fecha de Inicio *</label>
                        <input type="date" name="fecha_inicio" id="papel-fecha-inicio" class="modal-input" required>
                    </div>
                    <div>
                        <label class="modal-label">Hora Inicio</label>
                        <input type="time" name="hora_inicio" id="papel-hora-inicio" class="modal-input">
                    </div>
                </div>
                <div class="grid-2-col">
                    <div>
                        <label class="modal-label">Fecha Terminación</label>
                        <input type="date" name="fecha_final" id="papel-fecha-final" class="modal-input">
                    </div>
                    <div>
                        <label class="modal-label">Hora Terminación</label>
                        <input type="time" name="hora_final" id="papel-hora-final" class="modal-input">
                    </div>
                </div>
            </div>

            <!-- Bloque: Agentes en Comisión -->
            <div class="form-group-mb-lg">
                <label class="modal-label label-header-flex">
                    <span>Agentes en Comisión</span>
                    <span class="label-subinfo">Seleccione los agentes asignados a la salida</span>
                </label>
                <div id="contenedor-agentes-salida" class="agentes-checklist-box">
                    <span class="text-muted font-sm">Cargando agentes...</span>
                </div>
            </div>

            <!-- Trabajo a Realizar -->
            <div class="form-group-mb-sm">
                <label class="modal-label">Trabajo a Realizar</label>
                <textarea id="papel-trabajo" name="trabajo_a_realizar" class="modal-textarea textarea-trabajo"
                    placeholder="Detalle de tareas..." required></textarea>
            </div>

            <!-- Insumos y Herramientas -->
            <div class="recursos-box">
                <span class="recursos-box-title">
                    Materiales e Insumos / Herramientas de Salida
                </span>

                <!-- Herramientas en préstamo -->
                <div class="recurso-subseccion">
                    <div class="recurso-header-row">
                        <span class="recurso-header-title">Herramientas en Préstamo</span>
                        <button type="button" class="btn-action-text" onclick="agregarFilaHerramienta()">+ Agregar Herramienta</button>
                    </div>
                    <div id="lista-herramientas-salida"></div>
                </div>

                <!-- Materiales consumibles a descontar -->
                <div>
                    <div class="recurso-header-row">
                        <span class="recurso-header-title">Materiales a Descontar</span>
                        <button type="button" class="btn-action-text" onclick="agregarFilaMaterial()">+ Agregar Material</button>
                    </div>
                    <div id="lista-materiales-salida"></div>
                </div>
            </div>

            <div class="form-group-mb">
                <label class="modal-label">Observaciones</label>
                <textarea id="papel-observaciones" name="observaciones" class="modal-textarea textarea-observaciones"
                    placeholder="Notas de remito o estado..."></textarea>
            </div>

            <div class="modal-actions-row">
                <button type="button" class="btn-modal-cancel"
                    onclick="cerrarModal('modal-salida-comision')">Cancelar</button>
                <button type="submit" class="btn-modal-submit" id="btn-submit-salida">Confirmar Despacho y Salida</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 3: Cambiar Estado de Orden -->
<div id="modal-cambiar-estado" class="modal-overlay">
    <div class="modal-card modal-card-sm">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-cambiar-estado')"
            title="Cerrar">Cerrar</button>
        <h2 class="modal-title">Cambiar Estado</h2>
        <p class="modal-subtitle" id="estado-modal-subtitulo">Actualizar el estado operativo de la orden</p>

        <form id="form-cambiar-estado" onsubmit="guardarCambioEstado(event)">
            <?= csrfField() ?>
            <input type="hidden" id="estado-id-odt" name="id_odt">

            <div class="form-group-mb-lg">
                <label class="modal-label">Orden de Trabajo</label>
                <input type="text" id="estado-display-ot" class="modal-input input-readonly" readonly>
            </div>

            <div class="form-group-mb-lg">
                <label class="modal-label" for="estado-nuevo">Nuevo Estado *</label>
                <select id="estado-nuevo" name="estado" class="modal-select" required>
                    <option value="Pendiente">Pendiente</option>
                    <option value="En Proceso">En Proceso</option>
                    <option value="Pausada">Pausada</option>
                    <option value="Finalizada">Finalizada</option>
                    <option value="Anulada">Anulada</option>
                </select>
            </div>

            <div class="modal-actions-row">
                <button type="button" class="btn-modal-cancel"
                    onclick="cerrarModal('modal-cambiar-estado')">Cancelar</button>
                <button type="submit" class="btn-modal-submit" id="btn-submit-estado">Actualizar Estado</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 4: Ver Detalle Completo de la Orden -->
<div id="modal-detalle-odt" class="modal-overlay">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-detalle-odt')"
            title="Cerrar">Cerrar</button>
        <h2 class="modal-title" id="detalle-ot-titulo">Detalle de la Orden</h2>
        <p class="modal-subtitle" id="detalle-ot-subtitulo">Información completa y expediente</p>

        <div id="detalle-odt-contenido">
            <p class="modal-loading-text">Cargando información...</p>
        </div>

        <div class="modal-actions-row">
            <button type="button" class="btn-modal-cancel" onclick="cerrarModal('modal-detalle-odt')">Cerrar</button>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- JAVASCRIPT / AJAX -->
<!-- ========================================================================= -->
<script>
    const baseUrl = "<?= rtrim(BASE_URL, '/') ?>";
    const CSRF_TOKEN = "<?= csrfToken() ?>";
    const APP_PERMISOS = { editar: <?= canEdit() ? 'true' : 'false' ?> };
    let paginaActual = <?= (int) $paginaActual ?>;
    let debounceTimeout = null;

    let cacheRecursos = { operarios: [], herramientas: [], materiales: [] };

    // Control General de Modales
    function abrirModal(id) {
        const m = document.getElementById(id);
        if (m) {
            m.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    function cerrarModal(id) {
        const m = document.getElementById(id);
        if (m) {
            m.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('modal-overlay')) {
            cerrarModal(e.target.id);
        }
    });

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
        setTimeout(() => { alerta.style.display = 'none'; }, 4500);
    }

    function cargarOrdenes(pagina = 1) {
        paginaActual = pagina;
        const busqueda = encodeURIComponent(document.getElementById('buscador-odt').value.trim());
        const estado = encodeURIComponent(document.getElementById('filtro-estado').value);

        fetch(`dashboard.php?ajax=1&pagina=${pagina}&busqueda=${busqueda}&estado=${estado}`, { credentials: 'same-origin' })
            .then(res => res.json())
            .then(res => {
                if (!res.success) throw new Error(res.error || 'Error al obtener órdenes.');
                renderizarTabla(res.data);
                renderizarPaginacion(res.total_paginas, res.pagina_actual);
            })
            .catch(err => mostrarAlerta(err.message, false));
    }

    function cambiarPagina(p) { cargarOrdenes(p); }

    function renderizarTabla(ordenes) {
        const tbody = document.getElementById('tabla-odt-body');
        if (!tbody) return;

        if (!ordenes || ordenes.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="empty-state">No se encontraron órdenes registradas.</td></tr>';
            return;
        }

        const estadosPosibles = ['Pendiente', 'En Proceso', 'Pausada', 'Finalizada', 'Anulada'];

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
            const fechaFin = ot.fecha_final ? escaparHtml(ot.fecha_final) : '';

            const estadoTexto = escaparHtml(ot.estado || 'Pendiente');
            const badgeClass = 'badge-' + String(ot.estado || 'pendiente').toLowerCase().replace(/\s+/g, '-');

            let pdfHtml = '<span class="cell-pdf-none">-</span>';
            if (ot.archivo_pdf) {
                const pdfUrl = `${baseUrl}/uploads/pa_pdfs/${encodeURIComponent(ot.archivo_pdf)}`;
                pdfHtml = `<a href="${pdfUrl}" target="_blank" class="btn-pdf-view" title="Ver archivo PDF adjunto">Ver</a>`;
            }

            const optionsEstado = estadosPosibles.map(est => {
                const selected = (estadoTexto === est || (est === 'En Proceso' && estadoTexto === 'En Progreso')) ? 'selected' : '';
                return `<option value="${est}" ${selected}>${est}</option>`;
            }).join('');

            const estaActiva = (ot.activo !== false && ot.activo !== 0 && ot.activo !== '0' && ot.activo !== 'f');

            const selectEstadoHtml = `
                <select class="badge-select badge ${badgeClass}" 
                        data-prev="${estadoTexto}" 
                        onchange="cambiarEstadoDirecto(this, ${idOdt})" 
                        ${(APP_PERMISOS.editar && estaActiva) ? '' : 'disabled'}
                        title="Cambiar estado de la orden">
                    ${optionsEstado}
                </select>
            `;

            let botonesEdicion = '';
            if (APP_PERMISOS.editar) {
                if (estaActiva) {
                    botonesEdicion = `
                    <button type="button" class="btn-action-icon dispatch" title="Registrar Salida / Despachar Comisión" onclick="abrirModalSalida(${idOdt}, '${numeroOt}')">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 17l5-5-5-5"></path>
                            <path d="M21 12H9"></path>
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        </svg>
                    </button>
                    <button type="button" class="btn-action-icon edit" title="Editar Solicitud" onclick="abrirModalEditar(${idOdt})">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                    </button>
                    <button type="button" class="btn-action-icon disable" title="Deshabilitar Orden" onclick="deshabilitarOdt(${idOdt}, '${numeroOt}')">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            <line x1="10" y1="11" x2="10" y2="17"></line>
                            <line x1="14" y1="11" x2="14" y2="17"></line>
                        </svg>
                    </button>`;
                } else {
                    botonesEdicion = `
                    <button type="button" class="btn-action-icon enable" title="Reintegrar Orden" onclick="reintegrarOdt(${idOdt}, '${numeroOt}')">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="1 4 1 10 7 10"></polyline>
                            <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                        </svg>
                    </button>`;
                }
            }

            const accionesHtml = `
                <div class="actions-cell">
                    <button type="button" class="btn-action-icon view" title="Ver Detalle" onclick="verDetalleModal(${idOdt})">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                    ${botonesEdicion}
                </div>
            `;

            html += `
            <tr>
                <td class="col-id">
                    <div class="cell-id-ot">${numeroOt}</div>
                    <div class="cell-id-pa">${(pa !== '-' && pa !== '') ? pa : 'Sin PA'}</div>
                </td>
                <td class="col-trabajo">
                    <div class="cell-multiline-2" title="${trabajo}">${trabajo}</div>
                </td>
                <td class="col-ubicacion" title="${(loc !== '-' || jur !== '-') ? loc + ' / ' + jur : '-'}">
                    <div class="cell-localidad-ciudad">${loc}</div>
                    <div class="cell-localidad-edificio">${jur}</div>
                </td>
                <td class="col-periodo">
                    <div class="cell-periodo-ini">${fechaIni}</div>
                    <div class="cell-periodo-fin">${(fechaFin !== '-' && fechaFin !== '') ? 'al ' + fechaFin : ''}</div>
                </td>
                <td class="col-estado">${selectEstadoHtml}</td>
                <td class="col-pdf cell-center">${pdfHtml}</td>
                <td class="col-acciones" onclick="event.stopPropagation()">${accionesHtml}</td>
            </tr>`;
        });

        tbody.innerHTML = html;
    }

    function cambiarEstadoDirecto(selectElem, idOdt) {
        const estadoNuevo = selectElem.value;
        const estadoAnterior = selectElem.dataset.prev || 'Pendiente';

        if (estadoNuevo === estadoAnterior) return;

        function obtenerClaseBadge(est) {
            return 'badge-' + String(est || 'pendiente').toLowerCase().replace(/\s+/g, '-');
        }

        // Actualización optimista de color de badge
        selectElem.className = `badge-select badge ${obtenerClaseBadge(estadoNuevo)}`;

        const formData = new FormData();
        formData.append('id_odt', idOdt);
        formData.append('estado', estadoNuevo);
        formData.append('csrf_token', CSRF_TOKEN);

        fetch('dashboard.php?action=cambiar_estado', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(r => r.json())
            .then(res => {
                if (!res.success) throw new Error(res.error || 'Error al actualizar el estado.');
                selectElem.dataset.prev = estadoNuevo;
                mostrarAlerta(res.message || `Estado actualizado a "${estadoNuevo}".`, true);
            })
            .catch(err => {
                console.error(err);
                selectElem.value = estadoAnterior;
                selectElem.className = `badge-select badge ${obtenerClaseBadge(estadoAnterior)}`;
                mostrarAlerta(err.message, false);
            });
    }

    function renderizarPaginacion(totalPaginas, pagina) {
        const contenedor = document.getElementById('contenedor-paginacion');
        if (!contenedor) return;
        if (totalPaginas <= 1) { contenedor.innerHTML = ''; return; }

        let html = `<button type="button" class="pagination-btn text-label" onclick="cambiarPagina(${Math.max(1, pagina - 1)})" ${pagina <= 1 ? 'disabled' : ''}>Anterior</button>`;
        for (let p = 1; p <= totalPaginas; p++) {
            html += `<button type="button" class="pagination-btn ${p === pagina ? 'active' : ''}" onclick="cambiarPagina(${p})">${p}</button>`;
        }
        html += `<button type="button" class="pagination-btn text-label" onclick="cambiarPagina(${Math.min(totalPaginas, pagina + 1)})" ${pagina >= totalPaginas ? 'disabled' : ''}>Siguiente</button>`;
        contenedor.innerHTML = html;
    }

    // Filtros y Búsqueda
    document.getElementById('buscador-odt').addEventListener('input', function () {
        clearTimeout(debounceTimeout);
        debounceTimeout = setTimeout(() => cargarOrdenes(1), 350);
    });
    document.getElementById('filtro-estado').addEventListener('change', () => cargarOrdenes(1));

    // =========================================================================
    // MODAL: NUEVA / EDITAR ORDEN (PDF)
    // =========================================================================
    function abrirModalCrear() {
        document.getElementById('form-crear-odt').reset();
        document.getElementById('crear-id-odt').value = '';
        document.getElementById('crear-odt-titulo').innerText = 'Cargar Solicitud de Orden';
        document.getElementById('btn-submit-crear').innerText = 'Guardar Solicitud';
        document.getElementById('crear-archivo-pdf').required = true;
        abrirModal('modal-crear-odt');
    }

    function abrirModalEditar(idOdt) {
        fetch(`dashboard.php?action=detalle&id=${idOdt}`, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(res => {
                if (!res.success || !res.data) throw new Error(res.error || 'No se pudo cargar la orden.');
                const o = res.data;
                const form = document.getElementById('form-crear-odt');
                form.reset();

                document.getElementById('crear-id-odt').value = o.id_odt;
                document.getElementById('crear-odt-titulo').innerText = `Editar Solicitud ${o.numero_ot || ''}`;
                document.getElementById('btn-submit-crear').innerText = 'Guardar Cambios';
                document.getElementById('crear-archivo-pdf').required = false;

                document.getElementById('crear-numero-ot').value = o.numero_ot || '';
                document.getElementById('crear-pa').value = o.pa || '';
                document.getElementById('crear-localidad').value = o.id_localidad || '';
                document.getElementById('crear-jurisdiccion').value = o.id_jurisdiccion || '';
                document.getElementById('crear-fecha-inicio').value = o.fecha_inicio || '';
                document.getElementById('crear-fecha-final').value = o.fecha_final || '';
                document.getElementById('crear-estado').value = o.estado || 'Pendiente';
                document.getElementById('crear-trabajo').value = o.trabajo_a_realizar || '';
                document.getElementById('crear-observaciones').value = o.observaciones || '';

                abrirModal('modal-crear-odt');
            })
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
            .then(r => r.json())
            .then(res => {
                if (!res.success) throw new Error(res.error || 'No se pudo guardar la orden.');
                cerrarModal('modal-crear-odt');
                form.reset();
                mostrarAlerta(res.message, true);
                cargarOrdenes(esEdicion ? paginaActual : 1);
            })
            .catch(err => mostrarAlerta(err.message, false))
            .finally(() => {
                btnSubmit.disabled = false;
                btnSubmit.innerText = textoOriginal;
            });
    }

    // =========================================================================
    // MODAL: REGISTRAR SALIDA A OBRA (COMISIÓN)
    // =========================================================================
    function abrirModalSalida(idOdt, numeroOt) {
        document.getElementById('salida-id-odt').value = idOdt;
        document.getElementById('lista-herramientas-salida').innerHTML = '';
        document.getElementById('lista-materiales-salida').innerHTML = '';

        fetch(`dashboard.php?action=detalle&id=${idOdt}`, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(res => {
                if (!res.success || !res.data) throw new Error(res.error || 'No se pudo cargar la orden.');
                const o = res.data;

                document.getElementById('papel-pa-display').innerText = o.pa ? o.pa : numeroOt;
                document.getElementById('papel-localidad').value = o.nombre_localidad || 'CAPITAL';
                document.getElementById('papel-dependencia').value = o.nombre_jurisdiccion || '';
                document.getElementById('papel-fecha-inicio').value = o.fecha_inicio || new Date().toISOString().split('T')[0];
                document.getElementById('papel-fecha-final').value = o.fecha_final || '';
                document.getElementById('papel-hora-inicio').value = o.hora_inicio ? o.hora_inicio.substring(0, 5) : '';
                document.getElementById('papel-hora-final').value = o.hora_final ? o.hora_final.substring(0, 5) : '';
                document.getElementById('papel-trabajo').value = o.trabajo_a_realizar || '';
                document.getElementById('papel-observaciones').value = o.observaciones || '';

                return fetch('dashboard.php?action=recursos_salida', { credentials: 'same-origin' });
            })
            .then(r => r.json())
            .then(res => {
                if (!res.success) throw new Error(res.error || 'Error al obtener recursos.');
                cacheRecursos = res;

                const contAgentes = document.getElementById('contenedor-agentes-salida');
                if (!res.operarios.length) {
                    contAgentes.innerHTML = '<span class="text-muted font-sm grid-span-all">No hay agentes registrados activos.</span>';
                } else {
                    contAgentes.innerHTML = res.operarios.map((op, idx) => `
                    <label class="agente-check-label">
                        <input type="checkbox" name="operarios[]" value="${op.id_operario}">
                        <span><strong>${idx + 1}°</strong> ${escaparHtml(op.apellido)}, ${escaparHtml(op.nombre)}</span>
                    </label>
                `).join('');
                }

                abrirModal('modal-salida-comision');
            })
            .catch(err => {
                console.error(err);
                mostrarAlerta(err.message, false);
            });
    }

    function agregarFilaHerramienta() {
        const contenedor = document.getElementById('lista-herramientas-salida');
        if (!cacheRecursos.herramientas.length) {
            alert('No hay herramientas con estado "Disponible" en el pañol.');
            return;
        }
        const div = document.createElement('div');
        div.className = 'fila-recurso-dinamica';

        let options = cacheRecursos.herramientas.map(h => `<option value="${h.id}">${escaparHtml(h.codigo)} - ${escaparHtml(h.nombre)} (${escaparHtml(h.ubicacion || 'Sin ubic.')})</option>`).join('');

        div.innerHTML = `
            <select name="herramientas[]" class="modal-select input-select-flex1" required>
                <option value="">Seleccione una herramienta...</option>
                ${options}
            </select>
            <button type="button" onclick="this.parentElement.remove()" class="btn-action-text disable btn-remover-recurso">Quitar</button>
        `;
        contenedor.appendChild(div);
    }

    function agregarFilaMaterial() {
        const contenedor = document.getElementById('lista-materiales-salida');
        if (!cacheRecursos.materiales.length) {
            alert('No hay materiales con stock disponible actualmente.');
            return;
        }
        const div = document.createElement('div');
        div.className = 'fila-recurso-dinamica';

        let options = cacheRecursos.materiales.map(m => `<option value="${m.id}" data-max="${m.stock_actual}" data-unidad="${escaparHtml(m.unidad_medida)}">${escaparHtml(m.codigo)} - ${escaparHtml(m.nombre)} (Disp: ${m.stock_actual} ${escaparHtml(m.unidad_medida)})</option>`).join('');

        div.innerHTML = `
            <select name="material_id[]" class="modal-select input-select-flex2" required onchange="actualizarMaxMaterial(this)">
                <option value="">Seleccione material...</option>
                ${options}
            </select>
            <input type="number" name="material_cant[]" class="modal-input input-cant-salida" placeholder="Cant." step="0.01" min="0.01" required>
            <button type="button" onclick="this.parentElement.remove()" class="btn-action-text disable btn-remover-recurso">Quitar</button>
        `;
        contenedor.appendChild(div);
    }

    function actualizarMaxMaterial(selectElem) {
        const opt = selectElem.options[selectElem.selectedIndex];
        const inputCant = selectElem.parentElement.querySelector('input[type="number"]');
        if (opt && opt.dataset.max) {
            inputCant.max = opt.dataset.max;
            inputCant.placeholder = `Máx: ${opt.dataset.max}`;
        }
    }

    function guardarSalidaComision(e) {
        e.preventDefault();
        const btnSubmit = document.getElementById('btn-submit-salida');
        const txtOriginal = btnSubmit.innerText;
        btnSubmit.disabled = true;
        btnSubmit.innerText = 'Despachando...';

        const form = document.getElementById('form-salida-comision');
        const formData = new FormData(form);

        fetch('despachar_salida.php', { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(r => r.json())
            .then(res => {
                if (!res.success) throw new Error(res.error || 'No se pudo registrar la salida.');
                cerrarModal('modal-salida-comision');
                mostrarAlerta(res.message, true);
                cargarOrdenes(paginaActual);
            })
            .catch(err => mostrarAlerta(err.message, false))
            .finally(() => {
                btnSubmit.disabled = false;
                btnSubmit.innerText = txtOriginal;
            });
    }

    // Deshabilitar, Reintegrar y Cambiar Estado
    function deshabilitarOdt(idOdt, numeroOt) {
        if (!confirm(`¿Deshabilitar la Orden de Trabajo "${numeroOt}"? El registro se conservará en el sistema.`)) return;
        const formData = new FormData();
        formData.append('id_odt', idOdt);
        formData.append('accion', 'deshabilitar');
        formData.append('csrf_token', CSRF_TOKEN);

        fetch('deshabilitar_orden_de_trabajo.php', { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(r => r.json())
            .then(res => {
                if (!res.success) throw new Error(res.error || 'Error al deshabilitar la orden.');
                mostrarAlerta(res.message, true);
                cargarOrdenes(paginaActual);
            })
            .catch(err => mostrarAlerta(err.message, false));
    }

    function reintegrarOdt(idOdt, numeroOt) {
        if (!confirm(`¿Desea reintegrar al listado activo la Orden de Trabajo "${numeroOt}"?`)) return;
        const formData = new FormData();
        formData.append('id_odt', idOdt);
        formData.append('accion', 'habilitar');
        formData.append('csrf_token', CSRF_TOKEN);

        fetch('deshabilitar_orden_de_trabajo.php', { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(r => r.json())
            .then(res => {
                if (!res.success) throw new Error(res.error || 'Error al reintegrar la orden.');
                mostrarAlerta(res.message, true);
                cargarOrdenes(paginaActual);
            })
            .catch(err => mostrarAlerta(err.message, false));
    }

    function abrirModalCambiarEstado(idOdt, numeroOt, estadoActual) {
        document.getElementById('estado-id-odt').value = idOdt;
        document.getElementById('estado-display-ot').value = numeroOt;
        document.getElementById('estado-nuevo').value = estadoActual;
        abrirModal('modal-cambiar-estado');
    }

    function guardarCambioEstado(e) {
        e.preventDefault();
        const btnSubmit = document.getElementById('btn-submit-estado');
        btnSubmit.disabled = true;

        fetch('dashboard.php?action=cambiar_estado', {
            method: 'POST',
            body: new FormData(document.getElementById('form-cambiar-estado')),
            credentials: 'same-origin'
        })
            .then(r => r.json())
            .then(res => {
                if (!res.success) throw new Error(res.error || 'Error al cambiar estado.');
                cerrarModal('modal-cambiar-estado');
                mostrarAlerta(res.message, true);
                cargarOrdenes(paginaActual);
            })
            .catch(err => mostrarAlerta(err.message, false))
            .finally(() => { btnSubmit.disabled = false; });
    }

    function verDetalleModal(idOdt) {
        const cont = document.getElementById('detalle-odt-contenido');
        cont.innerHTML = '<p class="modal-loading-text">Cargando información...</p>';
        abrirModal('modal-detalle-odt');

        fetch(`dashboard.php?action=detalle&id=${idOdt}`, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(res => {
                if (!res.success || !res.data) throw new Error(res.error || 'No se pudo cargar la orden.');
                const o = res.data;
                document.getElementById('detalle-ot-titulo').innerText = `Orden: ${o.numero_ot || ''}`;
                document.getElementById('detalle-ot-subtitulo').innerText = `ID #${o.id_odt || ''} | Estado: ${o.estado || 'Pendiente'}`;

                let pdfBtn = '<span class="cell-pdf-none">Sin archivo PDF adjunto</span>';
                if (o.archivo_pdf) {
                    const pdfUrl = `${baseUrl}/uploads/pa_pdfs/${encodeURIComponent(o.archivo_pdf)}`;
                    pdfBtn = `<a href="${pdfUrl}" target="_blank" class="btn-pdf-view">Ver PDF Adjunto</a> (${escaparHtml(o.archivo_pdf)})`;
                }

                let operariosHtml = (res.operarios || []).length
                    ? '<ul class="detail-list">' + res.operarios.map(op => `<li>${escaparHtml(op.apellido + ', ' + op.nombre)} (DNI ${escaparHtml(op.dni)})</li>`).join('') + '</ul>'
                    : '<span class="text-muted">Sin operarios registrados en comisión.</span>';

                let movimientosHtml = (res.movimientos || []).length
                    ? '<ul class="detail-list">' + res.movimientos.map(m => `<li>Despacho / Movimiento #${parseInt(m.id_movimiento, 10)} - Fecha: ${escaparHtml(m.fecha)}</li>`).join('') + '</ul>'
                    : '<span class="text-muted">Sin movimientos vinculados.</span>';

                cont.innerHTML = `
            <div class="detail-grid">
                <div class="detail-item"><strong>N° PA / Expediente</strong><span>${o.pa ? escaparHtml(o.pa) : '-'}</span></div>
                <div class="detail-item"><strong>Localidad</strong><span>${o.nombre_localidad ? escaparHtml(o.nombre_localidad) : '-'}</span></div>
                <div class="detail-item"><strong>Jurisdicción</strong><span>${o.nombre_jurisdiccion ? escaparHtml(o.nombre_jurisdiccion) : '-'}</span></div>
                <div class="detail-item"><strong>Período</strong><span>${o.fecha_inicio || '-'} a ${o.fecha_final || '-'}</span></div>
            </div>
            <div class="detail-bloque">
                <label class="modal-label">Trabajo a Realizar</label>
                <div class="detail-box-desc">${escaparHtml(o.trabajo_a_realizar)}</div>
            </div>
            <div class="detail-bloque">
                <label class="modal-label">Expediente Oficial PDF</label>
                <div>${pdfBtn}</div>
            </div>
            <div class="detail-bloque">
                <label class="modal-label">Cuadrilla Asignada</label>
                <div>${operariosHtml}</div>
            </div>
            <div>
                <label class="modal-label">Movimientos Registrados</label>
                <div>${movimientosHtml}</div>
            </div>`;
            })
            .catch(err => { cont.innerHTML = `<p class="modal-error-text">Error: ${escaparHtml(err.message)}</p>`; });
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            cerrarModal('modal-crear-odt');
            cerrarModal('modal-salida-comision');
            cerrarModal('modal-cambiar-estado');
            cerrarModal('modal-detalle-odt');
        }
    });
</script>

<?php include_once __DIR__ . '/../layout/footer.php'; ?>