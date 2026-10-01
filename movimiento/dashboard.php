<?php
/**
 * movimiento/dashboard.php
 * Vista principal del módulo de Movimientos (Historial de Pañol y Stock).
 * 
 * Directivas obligatorias:
 * - Sin iconos ni emojis en ninguna parte de la interfaz.
 * - Tabla de historial con paginación, filtros por texto y tipo de movimiento.
 * - Botón para abrir modal de detalle del movimiento vía fetch().
 * - Enlaces directos a Registrar Entrega y Registrar Devolución.
 */

$seccion = 'movimiento';
$css_modulo = 'movimientos';
$titulo_vista = 'Movimientos de Stock y Pañol';
$titulo_pagina = 'Movimientos - Sistema DeControl';

require_once __DIR__ . '/../clases/movimiento.php';

$movimientoModel = new Movimiento();

// =========================================================================
// RESPUESTA AJAX PARA BÚSQUEDA, FILTROS Y PAGINACIÓN DINÁMICA
// =========================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');

    $porPagina = 10;
    $paginaActual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
    $busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
    $idTipoMov = !empty($_GET['id_tipo_mov']) ? (int)$_GET['id_tipo_mov'] : null;
    $estado = isset($_GET['estado']) ? trim($_GET['estado']) : 'activo';

    $filtros = [
        'busqueda'    => $busqueda,
        'id_tipo_mov' => $idTipoMov,
        'estado'      => $estado,
        'limite'      => $porPagina,
        'offset'      => ($paginaActual - 1) * $porPagina
    ];

    $totalRegistros = $movimientoModel->contarFiltrados($filtros);
    $totalPaginas = max(1, (int)ceil($totalRegistros / $porPagina));
    $movimientos = $movimientoModel->getFiltrados($filtros);

    echo json_encode([
        'success'         => true,
        'data'            => $movimientos,
        'total_registros' => $totalRegistros,
        'total_paginas'   => $totalPaginas,
        'pagina_actual'   => $paginaActual
    ]);
    exit;
}

// =========================================================================
// CARGA INICIAL
// =========================================================================
$tiposMovimiento = $movimientoModel->getTiposMovimiento();

$porPagina = 10;
$paginaActual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$idTipoMov = !empty($_GET['id_tipo_mov']) ? (int)$_GET['id_tipo_mov'] : null;
$estado = isset($_GET['estado']) ? trim($_GET['estado']) : 'activo';

$filtros = [
    'busqueda'    => $busqueda,
    'id_tipo_mov' => $idTipoMov,
    'estado'      => $estado,
    'limite'      => $porPagina,
    'offset'      => ($paginaActual - 1) * $porPagina
];

$totalRegistros = $movimientoModel->contarFiltrados($filtros);
$totalPaginas = max(1, (int)ceil($totalRegistros / $porPagina));
$movimientos = $movimientoModel->getFiltrados($filtros);

include_once __DIR__ . '/../layouts/header.php';
?>

<!-- Contenedor de Alertas Dinámicas -->
<div id="alerta-movimientos" style="display: none; padding: 0.85rem 1.25rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.9rem; font-weight: 600;"></div>

<!-- Barra de Filtros, Búsqueda y Botones de Acción -->
<div class="action-bar-filters">
    <div class="search-input-wrapper">
        <input type="text" id="buscador-movimiento" class="search-input" placeholder="Buscar por operario, usuario, ID o motivo..." value="<?= htmlspecialchars($busqueda) ?>">
    </div>

    <select class="filter-select" id="filtro-tipo-mov">
        <option value="">Todos los Tipos</option>
        <?php foreach ($tiposMovimiento as $tm): ?>
            <option value="<?= htmlspecialchars($tm['id_tipo_mov']) ?>" <?= ($idTipoMov === (int)$tm['id_tipo_mov']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($tm['tipo']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select class="filter-select" id="filtro-estado">
        <option value="activo" <?= ($estado === 'activo') ? 'selected' : '' ?>>Solo Activos</option>
        <option value="anulado" <?= ($estado === 'anulado') ? 'selected' : '' ?>>Solo Anulados</option>
        <option value="todos" <?= ($estado === 'todos') ? 'selected' : '' ?>>Todos</option>
    </select>

    <a href="entrega.php" class="btn-pill-action">Registrar Entrega</a>
    <a href="devolucion.php" class="btn-pill-secondary">Registrar Devolución</a>
</div>

<!-- Grilla / Historial de Movimientos -->
<div class="movimientos-table-container">
    <div class="table-header-row">
        <div>ID</div>
        <div>Fecha</div>
        <div>Hora</div>
        <div>Tipo</div>
        <div>Operario</div>
        <div>Registrado Por</div>
        <div>Ítems</div>
        <div>Acciones</div>
    </div>

    <div id="tabla-movimientos-body">
        <?php if (empty($movimientos)): ?>
            <div class="empty-state">No se encontraron movimientos registrados con los filtros seleccionados.</div>
        <?php else: ?>
            <?php foreach ($movimientos as $m): 
                $tipoClass = 'badge-neutral';
                if ($m['id_tipo_mov'] == 1) $tipoClass = 'badge-entrega';
                elseif ($m['id_tipo_mov'] == 2) $tipoClass = 'badge-devolucion';
                elseif ($m['id_tipo_mov'] == 3) $tipoClass = 'badge-ingreso';
                elseif ($m['id_tipo_mov'] == 4) $tipoClass = 'badge-ajuste';

                $operarioTxt = !empty($m['nombre_operario']) ? htmlspecialchars($m['nombre_operario']) : 'No asignado';
                $usuarioTxt = !empty($m['nombre_usuario']) ? htmlspecialchars($m['nombre_usuario']) : "Usuario #{$m['id_usuario']}";
                $cantItems = (int)($m['total_items'] ?? 0);
            ?>
            <div class="movimiento-card-row">
                <div><strong>#<?= htmlspecialchars($m['id_movimiento']) ?></strong></div>
                <div><?= htmlspecialchars($m['fecha']) ?></div>
                <div><?= htmlspecialchars(substr($m['hora'], 0, 5)) ?></div>
                <div><span class="badge <?= $tipoClass ?>"><?= htmlspecialchars($m['nombre_tipo_movimiento']) ?></span></div>
                <div><?= $operarioTxt ?></div>
                <div><?= $usuarioTxt ?></div>
                <div><span class="badge badge-neutral"><?= $cantItems ?> ítem(s)</span></div>
                
                <div class="actions-cell">
                    <button type="button" class="btn-action-text view" title="Ver Detalle del Movimiento" onclick="abrirModalDetalle(<?= $m['id_movimiento'] ?>)">
                        Ver Detalle
                    </button>
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
<!-- MODAL DE DETALLE DE MOVIMIENTO (FETCH ASÍNCRONO) -->
<!-- ========================================================================= -->
<div id="modal-detalle-movimiento" class="modal-backdrop">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-detalle-movimiento')">Cerrar</button>
        
        <div class="modal-header-text">
            <h2 id="modal-mov-titulo">Detalle del Movimiento</h2>
            <p id="modal-mov-subtitulo">Información de cabecera e ítems involucrados</p>
        </div>

        <div id="modal-mov-contenido">
            <!-- Cargado dinámicamente vía fetch() -->
            <p style="text-align: center; color: #64748b; padding: 2rem;">Cargando información...</p>
        </div>

        <div class="modal-actions-row">
            <a id="modal-btn-ver-ficha" href="#" target="_blank" class="btn-action-text view" style="display: none; align-self: center;">Abrir Ficha Completa</a>
            <button type="button" class="btn-modal-cancel" onclick="cerrarModal('modal-detalle-movimiento')">Cerrar</button>
        </div>
    </div>
</div>

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
    const box = document.getElementById('alerta-movimientos');
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

// Carga Dinámica vía Fetch
function cargarMovimientos(pagina = 1) {
    paginaActual = pagina;
    const busqueda = document.getElementById('buscador-movimiento').value.trim();
    const idTipoMov = document.getElementById('filtro-tipo-mov').value;
    const estado = document.getElementById('filtro-estado').value;

    const url = `dashboard.php?ajax=1&pagina=${pagina}&busqueda=${encodeURIComponent(busqueda)}&id_tipo_mov=${encodeURIComponent(idTipoMov)}&estado=${encodeURIComponent(estado)}`;

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
    cargarMovimientos(p);
}

function renderizarTabla(movimientos) {
    const contenedor = document.getElementById('tabla-movimientos-body');
    if (!contenedor) return;

    if (!movimientos || movimientos.length === 0) {
        contenedor.innerHTML = '<div class="empty-state">No se encontraron movimientos registrados con los filtros seleccionados.</div>';
        return;
    }

    let html = '';
    movimientos.forEach(m => {
        let tipoClass = 'badge-neutral';
        if (m.id_tipo_mov == 1) tipoClass = 'badge-entrega';
        else if (m.id_tipo_mov == 2) tipoClass = 'badge-devolucion';
        else if (m.id_tipo_mov == 3) tipoClass = 'badge-ingreso';
        else if (m.id_tipo_mov == 4) tipoClass = 'badge-ajuste';

        const operarioTxt = m.nombre_operario ? escaparHtml(m.nombre_operario) : 'No asignado';
        const usuarioTxt = m.nombre_usuario ? escaparHtml(m.nombre_usuario) : `Usuario #${m.id_usuario}`;
        const cantItems = parseInt(m.total_items || 0, 10);
        const horaTxt = m.hora ? escaparHtml(m.hora.substring(0, 5)) : '';

        html += `
        <div class="movimiento-card-row">
            <div><strong>#${escaparHtml(String(m.id_movimiento))}</strong></div>
            <div>${escaparHtml(m.fecha)}</div>
            <div>${horaTxt}</div>
            <div><span class="badge ${tipoClass}">${escaparHtml(m.nombre_tipo_movimiento)}</span></div>
            <div>${operarioTxt}</div>
            <div>${usuarioTxt}</div>
            <div><span class="badge badge-neutral">${cantItems} ítem(s)</span></div>
            
            <div class="actions-cell">
                <button type="button" class="btn-action-text view" title="Ver Detalle del Movimiento" onclick="abrirModalDetalle(${m.id_movimiento})">
                    Ver Detalle
                </button>
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

// Búsqueda en Vivo y Filtros
document.getElementById('buscador-movimiento').addEventListener('input', function() {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        cargarMovimientos(1);
    }, 350);
});

document.getElementById('filtro-tipo-mov').addEventListener('change', function() {
    cargarMovimientos(1);
});

document.getElementById('filtro-estado').addEventListener('change', function() {
    cargarMovimientos(1);
});

// Modal de Detalle
function abrirModalDetalle(idMovimiento) {
    document.getElementById('modal-mov-titulo').innerText = `Movimiento #${idMovimiento}`;
    const contenedor = document.getElementById('modal-mov-contenido');
    contenedor.innerHTML = '<p style="text-align: center; color: #64748b; padding: 2rem;">Cargando información del movimiento...</p>';

    const btnVerFicha = document.getElementById('modal-btn-ver-ficha');
    btnVerFicha.href = `ver_detalle.php?id=${idMovimiento}`;
    btnVerFicha.style.display = 'inline-block';

    abrirModal('modal-detalle-movimiento');

    fetch(`ver_detalle.php?id=${idMovimiento}&ajax=1`, { credentials: 'same-origin' })
        .then(response => {
            if (!response.ok) throw new Error('Error al conectar con el servidor.');
            return response.json();
        })
        .then(res => {
            if (!res.success || !res.movimiento) throw new Error(res.error || 'No se pudo obtener el detalle.');

            const m = res.movimiento;
            const detalles = res.detalles || [];

            document.getElementById('modal-mov-subtitulo').innerText = `${m.nombre_tipo_movimiento} - ${m.fecha} a las ${m.hora.substring(0, 5)}`;

            let html = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; background-color: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.85rem;">
                    <div><strong>Operario:</strong> ${escaparHtml(m.nombre_operario || 'No asignado')}</div>
                    <div><strong>Registrado Por:</strong> ${escaparHtml(m.nombre_usuario || 'Sistema')}</div>
                    <div><strong>Fecha y Hora:</strong> ${escaparHtml(m.fecha)} ${escaparHtml(m.hora.substring(0, 5))}</div>
                    <div><strong>Estado Registro:</strong> ${m.activo ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-danger">Anulado</span>'}</div>
                </div>`;

            if (m.observaciones) {
                html += `
                    <div style="margin-bottom: 1.25rem; font-size: 0.85rem; color: #475569;">
                        <strong>Observaciones:</strong> ${escaparHtml(m.observaciones)}
                    </div>`;
            }

            html += `
                <div style="font-weight: 700; color: #0b1536; font-size: 0.9rem; margin-bottom: 0.5rem;">Ítems Involucrados (${detalles.length})</div>
                <div style="max-height: 250px; overflow-y: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                        <thead>
                            <tr style="border-bottom: 2px solid #e2e8f0; color: #0b1536; background-color: #f1f5f9;">
                                <th style="padding: 0.5rem; text-align: left;">Código</th>
                                <th style="padding: 0.5rem; text-align: left;">Insumo / Herramienta</th>
                                <th style="padding: 0.5rem; text-align: left;">Tipo</th>
                                <th style="padding: 0.5rem; text-align: right;">Cantidad</th>
                                <th style="padding: 0.5rem; text-align: left;">Estado</th>
                            </tr>
                        </thead>
                        <tbody>`;

            if (detalles.length === 0) {
                html += `<tr><td colspan="5" style="text-align: center; color: #64748b; padding: 1.5rem;">Sin ítems registrados.</td></tr>`;
            } else {
                detalles.forEach(d => {
                    const cant = parseFloat(d.cantidad || 0);
                    const estadoH = d.nombre_estado_herramienta ? `<span class="badge badge-neutral">${escaparHtml(d.nombre_estado_herramienta)}</span>` : '-';
                    html += `
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 0.5rem; font-weight: 600;">${escaparHtml(d.codigo)}</td>
                            <td style="padding: 0.5rem;">${escaparHtml(d.nombre_insumo)}</td>
                            <td style="padding: 0.5rem;">${escaparHtml(d.tipo_insumo || 'Material')}</td>
                            <td style="padding: 0.5rem; text-align: right; font-weight: 700;">${cant} ${escaparHtml(d.unidad_medida || '')}</td>
                            <td style="padding: 0.5rem;">${estadoH}</td>
                        </tr>`;
                });
            }

            html += `</tbody></table></div>`;
            contenedor.innerHTML = html;
        })
        .catch(err => {
            contenedor.innerHTML = `<p style="color: #dc2626; text-align: center; padding: 2rem;">Error al cargar detalle: ${escaparHtml(err.message)}</p>`;
        });
}

// Cerrar con Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-backdrop.active').forEach(modal => {
            modal.classList.remove('active');
        });
    }
});
</script>

<?php
include_once __DIR__ . '/../layouts/footer.php';
?>
