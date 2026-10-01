<?php
/**
 * orden_de_trabajo/dashboard.php
 * Panel principal del módulo de Órdenes de Trabajo.
 * 
 * Directivas de diseño y arquitectura:
 * - Sin iconos ni emojis en la interfaz.
 * - Uso exclusivo de métodos de OrdenDeTrabajo: contarFiltrados, getFiltrados, obtenerPorId, crear, actualizarEstado, getLocalidades, getJurisdicciones.
 * - Botones de texto claro: "Nueva Orden", "Ver PDF", "Ver Detalle", "Cambiar Estado".
 * - Interfaz homogénea con layouts/header.php y layouts/footer.php.
 * - Operaciones AJAX sin recargar la página.
 */

$seccion = 'orden_de_trabajo';
$css_modulo = 'orden_de_trabajo';
$titulo_vista = 'Gestión de Órdenes de Trabajo';
$titulo_pagina = 'Órdenes de Trabajo - Sistema DeControl';

require_once __DIR__ . '/../clases/orden_de_trabajo.php';

$odtModel = new OrdenDeTrabajo();

// =========================================================================
// RESPUESTA AJAX PARA BÚSQUEDA, FILTROS Y PAGINACIÓN DINÁMICA
// =========================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');

    $porPagina = 10;
    $paginaActual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
    $busqueda = isset($_GET['busqueda']) ? trim((string)$_GET['busqueda']) : '';
    $estado = isset($_GET['estado']) ? trim((string)$_GET['estado']) : '';

    $filtros = [
        'busqueda' => $busqueda,
        'estado'   => $estado,
        'limite'   => $porPagina,
        'offset'   => ($paginaActual - 1) * $porPagina
    ];

    $totalRegistros = $odtModel->contarFiltrados($filtros);
    $totalPaginas = max(1, (int)ceil($totalRegistros / $porPagina));
    $ordenes = $odtModel->getFiltrados($filtros);

    echo json_encode([
        'success'         => true,
        'data'            => $ordenes,
        'total_registros' => $totalRegistros,
        'total_paginas'   => $totalPaginas,
        'pagina_actual'   => $paginaActual
    ]);
    exit;
}

// =========================================================================
// CARGA INICIAL (RENDERIZADO EN SERVIDOR)
// =========================================================================
$porPagina = 10;
$paginaActual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$busqueda = isset($_GET['busqueda']) ? trim((string)$_GET['busqueda']) : '';
$estado = isset($_GET['estado']) ? trim((string)$_GET['estado']) : '';

$filtros = [
    'busqueda' => $busqueda,
    'estado'   => $estado,
    'limite'   => $porPagina,
    'offset'   => ($paginaActual - 1) * $porPagina
];

$totalRegistros = $odtModel->contarFiltrados($filtros);
$totalPaginas = max(1, (int)ceil($totalRegistros / $porPagina));
$ordenes = $odtModel->getFiltrados($filtros);

$localidades = $odtModel->getLocalidades();
$jurisdicciones = $odtModel->getJurisdicciones();

include_once __DIR__ . '/../layouts/header.php';
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

    <button type="button" class="btn-pill-action" onclick="abrirModalCrear()">Nueva Orden</button>
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

                    $loc = !empty($ot['localidad_nombre']) ? htmlspecialchars($ot['localidad_nombre'], ENT_QUOTES, 'UTF-8') : '-';
                    $jur = !empty($ot['jurisdiccion_nombre']) ? htmlspecialchars($ot['jurisdiccion_nombre'], ENT_QUOTES, 'UTF-8') : '-';
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
                            <a href="<?= BASE_URL ?>/uploads/pa_pdfs/<?= $nombrePdf ?>" target="_blank" class="btn-pdf-view">
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
                            <button type="button" class="btn-action-text edit" onclick="abrirModalCambiarEstado(<?= $idOdt ?>, '<?= $numeroOt ?>', '<?= $estadoTexto ?>')">
                                Cambiar Estado
                            </button>
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
        <h2 class="modal-title">Nueva Orden de Trabajo</h2>
        <p class="modal-subtitle">Complete los campos de la orden y adjunte el expediente PDF en caso de corresponder</p>

        <form id="form-crear-odt" onsubmit="guardarNuevaOdt(event)" enctype="multipart/form-data">
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
const baseUrl = "<?= BASE_URL ?>";
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

        const loc = ot.localidad_nombre ? escaparHtml(ot.localidad_nombre) : '-';
        const jur = ot.jurisdiccion_nombre ? escaparHtml(ot.jurisdiccion_nombre) : '-';
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
                    <button type="button" class="btn-action-text edit" onclick="abrirModalCambiarEstado(${idOdt}, '${numeroOt}', '${estadoTexto}')">
                        Cambiar Estado
                    </button>
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

// Modal: Nueva Orden de Trabajo (FormData con PDF)
function abrirModalCrear() {
    document.getElementById('form-crear-odt').reset();
    abrirModal('modal-crear-odt');
}

function guardarNuevaOdt(e) {
    e.preventDefault();
    const btnSubmit = document.getElementById('btn-submit-crear');
    btnSubmit.disabled = true;
    btnSubmit.innerText = 'Guardando...';

    const form = document.getElementById('form-crear-odt');
    const formData = new FormData(form);

    fetch('crear_odt.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) throw new Error('Error al conectar con el servidor.');
        return response.json();
    })
    .then(res => {
        if (!res.success) throw new Error(res.error || 'No se pudo crear la Orden de Trabajo.');
        cerrarModal('modal-crear-odt');
        form.reset();
        mostrarAlerta(res.message, true);
        cargarOrdenes(1);
    })
    .catch(err => {
        mostrarAlerta(err.message, false);
    })
    .finally(() => {
        btnSubmit.disabled = false;
        btnSubmit.innerText = 'Guardar Orden';
    });
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

    fetch('cambiar_estado.php', {
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

    fetch(`ver_detalle.php?ajax=1&id=${idOdt}`, { credentials: 'same-origin' })
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
                    <span>${o.localidad_nombre ? escaparHtml(o.localidad_nombre) : 'No asignada'}</span>
                </div>
                <div class="detail-item">
                    <strong>Jurisdicción</strong>
                    <span>${o.jurisdiccion_nombre ? escaparHtml(o.jurisdiccion_nombre) : 'No asignada'}</span>
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
include_once __DIR__ . '/../layouts/footer.php';
?>
