<?php
/**
 * reportes/dashboard.php
 * Módulo de Reportes, Alertas de Stock Crítico y Auditoría Histórica.
 * 
 * Directivas obligatorias:
 * - Uso exclusivo de Insumo::obtenerStockCritico() y Movimiento::obtenerAuditoriaPeriodica().
 * - Sin emojis ni iconos en ninguna parte de la interfaz.
 * - Etiquetas y botones de texto estándar: "Imprimir / Exportar", "Diario", "Semanal", "Mensual", "Anual", "Stock Crítico", "Auditoría".
 * - Integración con layouts/header.php y layouts/footer.php.
 */

$seccion = 'reportes';
$css_modulo = 'reportes';
$titulo_vista = 'Reportes y Auditoría';
$titulo_pagina = 'Reportes - Sistema DeControl';

require_once __DIR__ . '/../clases/insumo.php';
require_once __DIR__ . '/../clases/movimiento.php';

$insumoModel = new Insumo();
$movimientoModel = new Movimiento();

// Pestaña activa ('stock' o 'auditoria')
$tabActiva = isset($_GET['tab']) && $_GET['tab'] === 'auditoria' ? 'auditoria' : 'stock';

// Filtros para la pestaña de auditoría
$periodo = isset($_GET['periodo']) ? trim((string)$_GET['periodo']) : 'hoy';
$fechaDesde = !empty($_GET['fecha_desde']) ? trim((string)$_GET['fecha_desde']) : null;
$fechaHasta = !empty($_GET['fecha_hasta']) ? trim((string)$_GET['fecha_hasta']) : null;
$busqueda = isset($_GET['busqueda']) ? trim((string)$_GET['busqueda']) : null;

// =========================================================================
// RESPUESTA AJAX PARA LA TABLA DE AUDITORÍA DINÁMICA
// =========================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');

    $registrosAuditoria = $movimientoModel->obtenerAuditoriaPeriodica(
        $periodo,
        $fechaDesde,
        $fechaHasta,
        $busqueda
    );

    echo json_encode([
        'success' => true,
        'data'    => $registrosAuditoria,
        'total'   => count($registrosAuditoria)
    ]);
    exit;
}

// Carga de datos para el renderizado inicial
$itemsStockCritico = $insumoModel->obtenerStockCritico();
$registrosAuditoria = $movimientoModel->obtenerAuditoriaPeriodica(
    $periodo,
    $fechaDesde,
    $fechaHasta,
    $busqueda
);

include_once __DIR__ . '/../layouts/header.php';
?>

<!-- Encabezado de Impresión Institucional (Exclusivo para window.print) -->
<div class="print-only-header">
    <h2 style="margin: 0; font-size: 16pt;">Poder Judicial de Corrientes - DAM</h2>
    <h3 style="margin: 5px 0; font-size: 13pt;">Sistema de Control de Stock - Reporte Oficial</h3>
    <p style="margin: 0; font-size: 9pt; color: #555;">Fecha de Emisión: <?= date('d/m/Y H:i:s') ?></p>
    <hr style="border: 1px solid #000; margin: 15px 0;">
</div>

<!-- Barra Superior con Botón de Impresión / Exportación -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
    <div>
        <p style="color: #64748b; font-size: 0.9rem; margin: 0;">
            Monitoreo preventivo de existencias para compras y trazabilidad histórica de auditoría.
        </p>
    </div>
    <button type="button" class="btn-print-report" onclick="window.print()">
        Imprimir / Exportar
    </button>
</div>

<!-- Navegación de Sub-pestañas Principales -->
<div class="report-nav-tabs">
    <button type="button" id="tab-btn-stock" class="report-nav-tab <?= ($tabActiva === 'stock') ? 'active' : '' ?>" onclick="cambiarPestana('stock')">
        Alerta Stock Mínimo (Compras) (<?= count($itemsStockCritico) ?>)
    </button>
    <button type="button" id="tab-btn-auditoria" class="report-nav-tab <?= ($tabActiva === 'auditoria') ? 'active' : '' ?>" onclick="cambiarPestana('auditoria')">
        Historial de Auditoría
    </button>
</div>

<!-- ========================================================================= -->
<!-- PESTAÑA 1: ALERTA STOCK MÍNIMO (COMPRAS) -->
<!-- ========================================================================= -->
<div id="seccion-stock-critico" style="display: <?= ($tabActiva === 'stock') ? 'block' : 'none' ?>;">

    <!-- Métricas Clave de Compras -->
    <div class="kpi-cards-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
        <div class="kpi-card">
            <div class="kpi-body">
                <div class="kpi-label">Insumos en Estado Crítico</div>
                <div class="kpi-value" style="color: #dc2626;"><?= count($itemsStockCritico) ?></div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-body">
                <div class="kpi-label">Criterio de Alerta</div>
                <div class="kpi-value" style="font-size: 1rem; color: #475569; font-weight: 600;">Stock Actual &le; Stock Mínimo</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-body">
                <div class="kpi-label">Acción Operativa Sugerida</div>
                <div class="kpi-value" style="font-size: 1rem; color: #166534; font-weight: 600;">Emisión de Pedido de Compra</div>
            </div>
        </div>
    </div>

    <!-- Tabla de Insumos Críticos -->
    <div class="report-table-responsive">
        <table class="report-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre del Insumo</th>
                    <th>Rubro</th>
                    <th>Ubicación</th>
                    <th>Unidad</th>
                    <th style="text-align: right;">Stock Actual</th>
                    <th style="text-align: right;">Stock Mínimo</th>
                    <th style="text-align: right;">Sugerido a Comprar</th>
                    <th style="text-align: center;">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($itemsStockCritico)): ?>
                    <tr>
                        <td colspan="9" class="empty-state">
                            No hay insumos en nivel crítico. Todas las existencias se encuentran por encima del stock de seguridad.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($itemsStockCritico as $item): 
                        $codigo = htmlspecialchars($item['codigo'], ENT_QUOTES, 'UTF-8');
                        $nombre = htmlspecialchars($item['nombre'], ENT_QUOTES, 'UTF-8');
                        $rubro = !empty($item['rubro_nombre']) ? htmlspecialchars($item['rubro_nombre'], ENT_QUOTES, 'UTF-8') : '-';
                        $ubicacion = !empty($item['ubicacion_nombre']) ? htmlspecialchars($item['ubicacion_nombre'], ENT_QUOTES, 'UTF-8') : '-';
                        $unidad = !empty($item['unidad_nombre']) ? htmlspecialchars($item['unidad_nombre'], ENT_QUOTES, 'UTF-8') : '-';
                        $stockActual = (float)$item['stock_actual'];
                        $stockMinimo = (float)$item['stock_minimo'];
                        $sugerido = (float)$item['sugerido_comprar'];
                    ?>
                    <tr>
                        <td><strong><?= $codigo ?></strong></td>
                        <td><?= $nombre ?></td>
                        <td><?= $rubro ?></td>
                        <td><?= $ubicacion ?></td>
                        <td><?= $unidad ?></td>
                        <td style="text-align: right;">
                            <span class="badge-critico"><?= $stockActual ?></span>
                        </td>
                        <td style="text-align: right; font-weight: 700; color: #475569;">
                            <?= $stockMinimo ?>
                        </td>
                        <td style="text-align: right;">
                            <span class="badge-sugerido">+<?= $sugerido ?></span>
                        </td>
                        <td style="text-align: center;">
                            <a href="<?= BASE_URL ?>/movimiento/dashboard.php" class="btn-action-comprar" title="Registrar ingreso de stock">
                                Registrar Ingreso
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========================================================================= -->
<!-- PESTAÑA 2: HISTORIAL DE AUDITORÍA -->
<!-- ========================================================================= -->
<div id="seccion-auditoria" style="display: <?= ($tabActiva === 'auditoria') ? 'block' : 'none' ?>;">

    <!-- Barra de Filtros de Auditoría -->
    <form id="form-filtros-auditoria" onsubmit="aplicarFiltrosAuditoria(event)" class="audit-filter-bar">
        <!-- Selector Rápido de Período -->
        <div class="period-buttons-group">
            <button type="button" class="btn-periodo <?= ($periodo === 'hoy' && empty($fechaDesde)) ? 'active' : '' ?>" onclick="seleccionarPeriodo('hoy')">
                Diario
            </button>
            <button type="button" class="btn-periodo <?= ($periodo === 'semana' && empty($fechaDesde)) ? 'active' : '' ?>" onclick="seleccionarPeriodo('semana')">
                Semanal
            </button>
            <button type="button" class="btn-periodo <?= ($periodo === 'mes' && empty($fechaDesde)) ? 'active' : '' ?>" onclick="seleccionarPeriodo('mes')">
                Mensual
            </button>
            <button type="button" class="btn-periodo <?= ($periodo === 'anio' && empty($fechaDesde)) ? 'active' : '' ?>" onclick="seleccionarPeriodo('anio')">
                Anual
            </button>
        </div>

        <input type="hidden" id="filtro-periodo" name="periodo" value="<?= htmlspecialchars($periodo, ENT_QUOTES, 'UTF-8') ?>">

        <!-- Rango de Fechas Manual -->
        <div class="date-filter-group">
            <label style="font-size: 0.8rem; font-weight: 600; color: #64748b;">Desde:</label>
            <input type="date" id="filtro-fecha-desde" name="fecha_desde" class="date-filter-input" value="<?= htmlspecialchars($fechaDesde ?? '', ENT_QUOTES, 'UTF-8') ?>">
            
            <label style="font-size: 0.8rem; font-weight: 600; color: #64748b;">Hasta:</label>
            <input type="date" id="filtro-fecha-hasta" name="fecha_hasta" class="date-filter-input" value="<?= htmlspecialchars($fechaHasta ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <!-- Buscador por Texto -->
        <input type="text" id="filtro-busqueda" name="busqueda" class="audit-search-input" placeholder="Buscar por usuario, operario u observación..." value="<?= htmlspecialchars($busqueda ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <!-- Botones de Acción -->
        <button type="submit" class="btn-filter-action">Filtrar</button>
        <button type="button" class="btn-filter-clear" onclick="limpiarFiltrosAuditoria()">Limpiar</button>
    </form>

    <!-- Tabla de Registros de Auditoría -->
    <div class="report-table-responsive">
        <table class="report-table">
            <thead>
                <tr>
                    <th>Fecha y Hora</th>
                    <th>Operación</th>
                    <th>Operario Responsable</th>
                    <th>Usuario del Sistema</th>
                    <th>Observaciones</th>
                </tr>
            </thead>
            <tbody id="tabla-auditoria-body">
                <?php if (empty($registrosAuditoria)): ?>
                    <tr>
                        <td colspan="5" class="empty-state">
                            No se encontraron registros de auditoría para el período y criterios seleccionados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($registrosAuditoria as $aud): 
                        $fechaHora = htmlspecialchars($aud['fecha'] . ' ' . substr($aud['hora'], 0, 5), ENT_QUOTES, 'UTF-8');
                        $tipoMov = htmlspecialchars($aud['tipo_movimiento'], ENT_QUOTES, 'UTF-8');

                        $tieneOp = !empty($aud['operario_nombre']) || !empty($aud['operario_apellido']);
                        $operario = $tieneOp 
                            ? htmlspecialchars(trim(($aud['operario_apellido'] ?? '') . ', ' . ($aud['operario_nombre'] ?? '')), ENT_QUOTES, 'UTF-8')
                            : 'No aplica';

                        $usuarioInfo = htmlspecialchars($aud['usuario'], ENT_QUOTES, 'UTF-8');
                        if (!empty($aud['usuario_nombre'])) {
                            $usuarioInfo .= ' (' . htmlspecialchars($aud['usuario_nombre'], ENT_QUOTES, 'UTF-8') . ')';
                        }

                        $observaciones = !empty($aud['observaciones']) ? htmlspecialchars($aud['observaciones'], ENT_QUOTES, 'UTF-8') : 'Sin observaciones';
                    ?>
                    <tr>
                        <td style="white-space: nowrap; font-weight: 600;"><?= $fechaHora ?></td>
                        <td><strong><?= $tipoMov ?></strong></td>
                        <td><?= $operario ?></td>
                        <td><?= $usuarioInfo ?></td>
                        <td><?= $observaciones ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========================================================================= -->
<!-- LÓGICA JAVASCRIPT / AJAX -->
<!-- ========================================================================= -->
<script>
function escaparHtml(texto) {
    if (texto === null || texto === undefined) return '';
    return String(texto)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function cambiarPestana(nombreTab) {
    const tabStockBtn = document.getElementById('tab-btn-stock');
    const tabAuditoriaBtn = document.getElementById('tab-btn-auditoria');
    const seccionStock = document.getElementById('seccion-stock-critico');
    const seccionAuditoria = document.getElementById('seccion-auditoria');

    if (nombreTab === 'auditoria') {
        tabStockBtn.classList.remove('active');
        tabAuditoriaBtn.classList.add('active');
        seccionStock.style.display = 'none';
        seccionAuditoria.style.display = 'block';
    } else {
        tabAuditoriaBtn.classList.remove('active');
        tabStockBtn.classList.add('active');
        seccionAuditoria.style.display = 'none';
        seccionStock.style.display = 'block';
    }

    // Actualiza la URL sin recargar la página para permitir compartir/recargar en la misma pestaña
    const url = new URL(window.location);
    url.searchParams.set('tab', nombreTab);
    window.history.replaceState({}, '', url);
}

function seleccionarPeriodo(periodo) {
    document.getElementById('filtro-periodo').value = periodo;
    document.getElementById('filtro-fecha-desde').value = '';
    document.getElementById('filtro-fecha-hasta').value = '';

    document.querySelectorAll('.btn-periodo').forEach(btn => {
        btn.classList.remove('active');
    });

    const botones = document.querySelectorAll('.btn-periodo');
    if (periodo === 'hoy') botones[0].classList.add('active');
    else if (periodo === 'semana') botones[1].classList.add('active');
    else if (periodo === 'mes') botones[2].classList.add('active');
    else if (periodo === 'anio') botones[3].classList.add('active');

    cargarAuditoriaAjax();
}

function aplicarFiltrosAuditoria(e) {
    if (e) e.preventDefault();
    const fDesde = document.getElementById('filtro-fecha-desde').value;
    const fHasta = document.getElementById('filtro-fecha-hasta').value;

    // Si se especifican fechas manuales, deseleccionamos el período rápido
    if (fDesde || fHasta) {
        document.querySelectorAll('.btn-periodo').forEach(btn => btn.classList.remove('active'));
        document.getElementById('filtro-periodo').value = '';
    }

    cargarAuditoriaAjax();
}

function limpiarFiltrosAuditoria() {
    document.getElementById('filtro-fecha-desde').value = '';
    document.getElementById('filtro-fecha-hasta').value = '';
    document.getElementById('filtro-busqueda').value = '';
    seleccionarPeriodo('hoy');
}

function cargarAuditoriaAjax() {
    const periodo = encodeURIComponent(document.getElementById('filtro-periodo').value);
    const fDesde = encodeURIComponent(document.getElementById('filtro-fecha-desde').value);
    const fHasta = encodeURIComponent(document.getElementById('filtro-fecha-hasta').value);
    const busqueda = encodeURIComponent(document.getElementById('filtro-busqueda').value.trim());

    const tbody = document.getElementById('tabla-auditoria-body');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:2rem; color:#64748b;">Cargando registros de auditoría...</td></tr>';

    const url = `dashboard.php?ajax=1&periodo=${periodo}&fecha_desde=${fDesde}&fecha_hasta=${fHasta}&busqueda=${busqueda}`;

    fetch(url, { credentials: 'same-origin' })
        .then(response => {
            if (!response.ok) throw new Error('Error al conectar con el servidor.');
            return response.json();
        })
        .then(res => {
            if (!res.success) throw new Error(res.error || 'No se pudieron obtener los datos de auditoría.');
            renderizarTablaAuditoria(res.data);
        })
        .catch(err => {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:2rem; color:#dc2626;">Error: ${escaparHtml(err.message)}</td></tr>`;
        });
}

function renderizarTablaAuditoria(registros) {
    const tbody = document.getElementById('tabla-auditoria-body');
    if (!tbody) return;

    if (!registros || registros.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="empty-state">No se encontraron registros de auditoría para el período y criterios seleccionados.</td></tr>';
        return;
    }

    let html = '';
    registros.forEach(r => {
        const horaCorta = r.hora ? r.hora.substring(0, 5) : '';
        const fechaHora = escaparHtml(`${r.fecha} ${horaCorta}`);
        const tipoMov = escaparHtml(r.tipo_movimiento || '-');

        const tieneOp = Boolean(r.operario_nombre || r.operario_apellido);
        const operario = tieneOp
            ? escaparHtml(`${r.operario_apellido || ''}, ${r.operario_nombre || ''}`.trim())
            : 'No aplica';

        let usuarioInfo = escaparHtml(r.usuario || '-');
        if (r.usuario_nombre) {
            usuarioInfo += ` (${escaparHtml(r.usuario_nombre)})`;
        }

        const observaciones = r.observaciones ? escaparHtml(r.observaciones) : 'Sin observaciones';

        html += `
        <tr>
            <td style="white-space: nowrap; font-weight: 600;">${fechaHora}</td>
            <td><strong>${tipoMov}</strong></td>
            <td>${operario}</td>
            <td>${usuarioInfo}</td>
            <td>${observaciones}</td>
        </tr>`;
    });

    tbody.innerHTML = html;
}
</script>

<?php
include_once __DIR__ . '/../layouts/footer.php';
?>
