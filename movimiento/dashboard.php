<?php
/**
 * movimiento/dashboard.php
 * Vista principal del módulo de Movimientos (historial de pañol y stock).
 *
 * - Datos mediante la clase Movimiento (getAll, getTiposMovimiento, getDetalles).
 * - Filtrado por texto/tipo/estado y paginación en PHP sobre el listado de la clase.
 * - Detalle de cada movimiento por fetch() a detalle_movimiento.php.
 * - Botón "Registrar Movimiento" (crear_movimiento.php): el tipo se elige en el formulario.
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
// Día exacto (AAAA-MM-DD); 'hoy' es un atajo (lo usa el Panel). Un valor inválido se ignora
$fecha = isset($_GET['fecha']) ? trim($_GET['fecha']) : '';
if ($fecha === 'hoy') {
    $fecha = date('Y-m-d');
}
$fechaObj = DateTime::createFromFormat('!Y-m-d', $fecha);
if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
    $fecha = '';
}

// getAll() devuelve el nombre del tipo pero no su id: se completa con el catálogo
$tipoIdPorNombre = array_column($tiposMovimiento, 'id_tipo_mov', 'tipo');
$todosMovimientos = array_map(function ($m) use ($tipoIdPorNombre) {
    $m['id_tipo_mov'] = $tipoIdPorNombre[$m['nombre_tipo_movimiento']] ?? null;
    return $m;
}, $movimientoModel->getAll(true));

$movimientosFiltrados = array_values(array_filter($todosMovimientos, function ($m) use ($busqueda, $idTipoMov, $estado, $fecha) {
    $activo = !empty($m['activo']) && $m['activo'] !== 'f';
    if ($fecha !== '' && substr((string)$m['fecha'], 0, 10) !== $fecha) {
        return false;
    }
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
    if ($busqueda !== '' && mb_strpos(textoNormalizado($texto), textoNormalizado($busqueda)) === false) {
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
<div id="alerta-movimientos" class="alerta-inline"></div>

<!-- Barra de Filtros, Búsqueda y Botones de Acción -->
<div class="action-bar-filters action-bar--agrupada">
  <div class="barra-filtros">
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

    <input type="date" class="filter-select" id="filtro-fecha" value="<?= htmlspecialchars($fecha) ?>" title="Filtrar por día (vacío = todas las fechas)">
  </div>

  <?php if (canEdit()): ?>
  <div class="barra-acciones">
    <?php /* El tipo (entrega, devolución o ingreso) se elige dentro del formulario */ ?>
    <a href="crear_movimiento.php" class="btn-pill-action">Registrar Movimiento</a>
  </div>
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

                $operarioTxt = enlaceOperario($m['id_operario'] ?? null, $m['nombre_operario'] ?? null, 'No asignado');
                $usuarioTxt = !empty($m['dni_usuario']) ? htmlspecialchars($m['dni_usuario']) : '-';
                $cantItems = (int)($m['total_items'] ?? 0);
            ?>
            <div class="movimiento-card-row fila-clic">
                <div><strong>#<?= htmlspecialchars($m['id_movimiento']) ?></strong></div>
                <div><?= htmlspecialchars($m['fecha']) ?></div>
                <div><?= htmlspecialchars(substr($m['hora'], 0, 5)) ?></div>
                <div><span class="badge <?= $tipoClass ?>"><?= htmlspecialchars($m['nombre_tipo_movimiento']) ?></span></div>
                <div><?= $operarioTxt ?></div>
                <div><?= $usuarioTxt ?></div>
                <div><span class="badge badge-neutral"><?= $cantItems ?> ítem(s)</span></div>
                
                <div class="actions-cell">
                    <button type="button" class="btn-action-icon view" title="Ver Detalle del Movimiento" onclick="abrirModalDetalle(<?= $m['id_movimiento'] ?>)">
                        <?= iconoAccion('view') ?>
                    </button>
                    <?php if (canEdit() && !empty($m['activo']) && $m['activo'] !== 'f'): ?>
                    <button type="button" class="btn-action-icon disable" title="Anular Movimiento" onclick="anularMovimiento(<?= (int)$m['id_movimiento'] ?>)">
                        <?= iconoAccion('disable') ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Paginación -->
<div class="pagination-container" id="contenedor-paginacion" data-total="<?= (int)$totalPaginas ?>" data-actual="<?= (int)$paginaActual ?>"></div>

<!-- ========================================================================= -->
<!-- MODAL DE DETALLE DE MOVIMIENTO (FETCH ASÍNCRONO) -->
<!-- ========================================================================= -->
<div id="modal-detalle-movimiento" class="modal-backdrop">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-detalle-movimiento')" title="Cerrar" aria-label="Cerrar"><?= iconoAccion('close', 18) ?></button>
        
        <div class="modal-header-text">
            <h2 id="modal-mov-titulo">Detalle del Movimiento</h2>
            <p id="modal-mov-subtitulo">Información de cabecera e ítems involucrados</p>
        </div>

        <div id="modal-mov-contenido">
            <!-- Cargado dinámicamente vía fetch() -->
            <p class="estado-vacio">Cargando información...</p>
        </div>

        <div class="modal-actions-row">
            <a id="modal-btn-ver-ficha" href="#" target="_blank" class="btn-action-text view btn-ver-ficha">Abrir Ficha Completa</a>
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
    // Con un modal abierto, el error se muestra dentro de él (la alerta de página queda detrás)
    if (!esExito && mostrarErrorEnModal(mensaje)) return;
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
    const fecha = document.getElementById('filtro-fecha').value;

    const url = `dashboard.php?ajax=1&pagina=${pagina}&busqueda=${encodeURIComponent(busqueda)}&id_tipo_mov=${encodeURIComponent(idTipoMov)}&estado=${encodeURIComponent(estado)}&fecha=${encodeURIComponent(fecha)}`;

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

        const operarioTxt = m.nombre_operario
            ? (m.id_operario ? `<a href="../operario/detalle_operario.php?id=${encodeURIComponent(m.id_operario)}" class="enlace-nombre">${escaparHtml(m.nombre_operario)}</a>` : escaparHtml(m.nombre_operario))
            : 'No asignado';
        const usuarioTxt = m.dni_usuario ? escaparHtml(m.dni_usuario) : '-';
        const cantItems = parseInt(m.total_items || 0, 10);
        const horaTxt = m.hora ? escaparHtml(m.hora.substring(0, 5)) : '';

        html += `
        <div class="movimiento-card-row fila-clic">
            <div><strong>#${escaparHtml(String(m.id_movimiento))}</strong></div>
            <div>${escaparHtml(m.fecha)}</div>
            <div>${horaTxt}</div>
            <div><span class="badge ${tipoClass}">${escaparHtml(m.nombre_tipo_movimiento)}</span></div>
            <div>${operarioTxt}</div>
            <div>${usuarioTxt}</div>
            <div><span class="badge badge-neutral">${cantItems} ítem(s)</span></div>
            
            <div class="actions-cell">
                <button type="button" class="btn-action-icon view" title="Ver Detalle del Movimiento" onclick="abrirModalDetalle(${m.id_movimiento})">
                    ${window.APP_ICONS.view}
                </button>
                ${(APP_PERMISOS.editar && (m.activo === true || m.activo === 't' || m.activo == 1)) ? `
                <button type="button" class="btn-action-icon disable" title="Anular Movimiento" onclick="anularMovimiento(${m.id_movimiento})">
                    ${window.APP_ICONS.disable}
                </button>` : ''}
            </div>
        </div>`;
    });

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

document.getElementById('filtro-fecha').addEventListener('change', function() {
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
    contenedor.innerHTML = '<p class="estado-vacio">Cargando información del movimiento...</p>';

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
                <div class="mov-resumen">
                    <div><strong>Operario:</strong> ${m.nombre_operario ? (m.id_operario ? `<a href="../operario/detalle_operario.php?id=${encodeURIComponent(m.id_operario)}" class="enlace-nombre">${escaparHtml(m.nombre_operario)}</a>` : escaparHtml(m.nombre_operario)) : 'No asignado'}</div>
                    <div><strong>Registrado Por:</strong> ${escaparHtml(m.dni_usuario || 'Sistema')}</div>
                    <div><strong>Fecha y Hora:</strong> ${escaparHtml(m.fecha)} ${escaparHtml(m.hora.substring(0, 5))}</div>
                    <div><strong>Estado Registro:</strong> ${m.activo ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-danger">Anulado</span>'}</div>
                </div>`;

            if (m.numero_ot) {
                html += `
                    <div class="mov-campo">
                        <strong>Orden de Trabajo:</strong> ${escaparHtml(m.numero_ot)}
                    </div>`;
            }

            if (m.nombre_motivo) {
                html += `
                    <div class="mov-campo">
                        <strong>Motivo de la devolución:</strong> ${escaparHtml(m.nombre_motivo)}
                    </div>`;
            }

            if (m.observaciones) {
                html += `
                    <div class="mov-campo mov-campo--ultimo">
                        <strong>Observaciones:</strong> ${escaparHtml(m.observaciones)}
                    </div>`;
            }

            html += `
                <div class="mov-items-titulo">Ítems Involucrados (${detalles.length})</div>
                <div class="mov-items-scroll">
                    <table class="mov-items-tabla">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Insumo / Herramienta</th>
                                <th>Tipo</th>
                                <th class="mov-num">Cantidad</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>`;

            if (detalles.length === 0) {
                html += `<tr><td colspan="5" class="estado-vacio estado-vacio--compacto">Sin ítems registrados.</td></tr>`;
            } else {
                detalles.forEach(d => {
                    const cant = parseFloat(d.cantidad || 0);
                    const estadoH = d.nombre_estado_herramienta ? `<span class="badge badge-neutral">${escaparHtml(d.nombre_estado_herramienta)}</span>` : '-';
                    html += `
                        <tr>
                            <td class="mov-codigo">${escaparHtml(d.codigo)}</td>
                            <td>${escaparHtml(d.nombre_insumo)}</td>
                            <td>${escaparHtml(d.tipo_insumo || 'Material')}</td>
                            <td class="mov-num">${cant} ${escaparHtml(d.unidad_medida || '')}</td>
                            <td>${estadoH}</td>
                        </tr>`;
                });
            }

            html += `</tbody></table></div>`;
            contenedor.innerHTML = html;
        })
        .catch(err => {
            contenedor.innerHTML = `<p class="estado-vacio estado-vacio--error">Error al cargar detalle: ${escaparHtml(err.message)}</p>`;
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
