<?php
/**
 * movimiento/dashboard.php
 * Vista principal del módulo de Movimientos (historial de pañol y stock).
 *
 * - Datos mediante la clase Movimiento (getAll, getTiposMovimiento, getDetalles).
 * - Filtrado por texto/tipo/estado y paginación en PHP sobre el listado de la clase.
 * - Detalle de cada movimiento por fetch() a detalle_movimiento.php.
 * - Enlaces a Registrar Entrega / Devolución (crear_movimiento.php).
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

$seccion = 'movimiento';
$css_modulo = 'movimientos';
$titulo_vista = 'Movimientos de Stock y Pañol';
$titulo_pagina = 'Movimientos - Sistema de Control de Stock';

require_once __DIR__ . '/../clases/movimiento.php';

$movimientoModel = new Movimiento();
$tiposMovimiento = $movimientoModel->getTiposMovimiento();

// =========================================================================
// FILTROS Y PAGINACIÓN (servidor)
// =========================================================================
$porPagina = 10;
$paginaActual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$idTipoMov = !empty($_GET['id_tipo_mov']) ? (int)$_GET['id_tipo_mov'] : null;
$estado = isset($_GET['estado']) ? trim($_GET['estado']) : 'activo';

// getAll() devuelve el nombre del tipo pero no su id: se completa con el catálogo
$tipoIdPorNombre = array_column($tiposMovimiento, 'id_tipo_mov', 'tipo');
$todosMovimientos = array_map(function ($m) use ($tipoIdPorNombre) {
    $m['id_tipo_mov'] = $tipoIdPorNombre[$m['nombre_tipo_movimiento']] ?? null;
    return $m;
}, $movimientoModel->getAll(true));

$movimientosFiltrados = array_values(array_filter($todosMovimientos, function ($m) use ($busqueda, $idTipoMov, $estado) {
    $activo = !empty($m['activo']) && $m['activo'] !== 'f';
    if ($estado === 'activo' && !$activo) {
        return false;
    }
    if ($estado === 'anulado' && $activo) {
        return false;
    }
    if ($idTipoMov !== null && (int)$m['id_tipo_mov'] !== $idTipoMov) {
        return false;
    }
    $texto = $m['id_movimiento'] . ' ' . ($m['nombre_operario'] ?? '') . ' ' . $m['dni_usuario'] . ' '
           . ($m['observaciones'] ?? '') . ' ' . $m['nombre_tipo_movimiento'];
    if ($busqueda !== '' && stripos($texto, $busqueda) === false) {
        return false;
    }
    return true;
}));

$totalRegistros = count($movimientosFiltrados);
$totalPaginas = max(1, (int)ceil($totalRegistros / $porPagina));
$paginaActual = min($paginaActual, $totalPaginas);

// Cantidad de ítems solo para los movimientos de la página actual
$movimientos = array_map(function ($m) use ($movimientoModel) {
    $m['total_items'] = count($movimientoModel->getDetalles($m['id_movimiento']));
    return $m;
}, array_slice($movimientosFiltrados, ($paginaActual - 1) * $porPagina, $porPagina));

// =========================================================================
// RESPUESTA AJAX PARA BÚSQUEDA, FILTROS Y PAGINACIÓN DINÁMICA
// =========================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'         => true,
        'data'            => $movimientos,
        'total_registros' => $totalRegistros,
        'total_paginas'   => $totalPaginas,
        'pagina_actual'   => $paginaActual
    ]);
    exit;
}

include_once __DIR__ . '/../layout/header.php';
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

    <?php if (canEdit()): ?>
    <a href="crear_movimiento.php?tipo=entrega" class="btn-pill-action">Registrar Entrega</a>
    <a href="crear_movimiento.php?tipo=devolucion" class="btn-pill-secondary">Registrar Devolución</a>
    <?php endif; ?>
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
                $usuarioTxt = !empty($m['dni_usuario']) ? htmlspecialchars($m['dni_usuario']) : '-';
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
                    <?php if (canEdit() && !empty($m['activo']) && $m['activo'] !== 'f'): ?>
                    <button type="button" class="btn-action-text disable" title="Anular Movimiento" onclick="anularMovimiento(<?= (int)$m['id_movimiento'] ?>)">
                        Anular
                    </button>
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
        const usuarioTxt = m.dni_usuario ? escaparHtml(m.dni_usuario) : '-';
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
                ${(APP_PERMISOS.editar && (m.activo === true || m.activo === 't' || m.activo == 1)) ? `
                <button type="button" class="btn-action-text disable" title="Anular Movimiento" onclick="anularMovimiento(${m.id_movimiento})">
                    Anular
                </button>` : ''}
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

// Anular un movimiento (Administrador / Pañolero): revierte el stock y el estado de las herramientas
function anularMovimiento(idMovimiento) {
    if (!confirm(`¿Anular el movimiento #${idMovimiento}? Se revertirá su efecto sobre el stock y las herramientas.`)) {
        return;
    }
    const formData = new FormData();
    formData.append('id_movimiento', idMovimiento);

    fetch('deshabilitar_movimiento.php', { method: 'POST', body: formData, credentials: 'same-origin' })
        .then(response => response.json())
        .then(res => {
            if (!res.success) throw new Error(res.error || 'No se pudo anular el movimiento.');
            mostrarAlerta(res.message, true);
            cargarMovimientos(typeof paginaActual === 'number' ? paginaActual : 1);
        })
        .catch(err => mostrarAlerta(err.message, false));
}

// Modal de Detalle
function abrirModalDetalle(idMovimiento) {
    document.getElementById('modal-mov-titulo').innerText = `Movimiento #${idMovimiento}`;
    const contenedor = document.getElementById('modal-mov-contenido');
    contenedor.innerHTML = '<p style="text-align: center; color: #64748b; padding: 2rem;">Cargando información del movimiento...</p>';

    const btnVerFicha = document.getElementById('modal-btn-ver-ficha');
    btnVerFicha.href = `detalle_movimiento.php?id=${idMovimiento}`;
    btnVerFicha.style.display = 'inline-block';

    abrirModal('modal-detalle-movimiento');

    fetch(`detalle_movimiento.php?id=${idMovimiento}&ajax=1`, { credentials: 'same-origin' })
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
                    <div><strong>Registrado Por:</strong> ${escaparHtml(m.dni_usuario || 'Sistema')}</div>
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
include_once __DIR__ . '/../layout/footer.php';
?>
