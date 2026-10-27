<?php
// reporte/dashboard.php
session_start();

$seccion = 'reportes';

require_once __DIR__ . '/../clases/reportes.php';

$tab_reporte = $_GET['tab'] ?? 'compras';
$periodo = $_GET['periodo'] ?? 'hoy';
$buscar = trim($_GET['buscar'] ?? '');
$desde = trim($_GET['desde'] ?? '');
$hasta = trim($_GET['hasta'] ?? '');
$tipo_mov = trim($_GET['tipo_mov'] ?? '');

$kpis = obtenerKPISReporte();
$materiales_criticos = obtenerListaStockCritico();
$auditoria = obtenerAuditoriaMovimientos($periodo, $desde ?: null, $hasta ?: null, $buscar ?: null, $tipo_mov ?: null);
$consumo_rubros = obtenerConsumoPorRubro();

require_once __DIR__ . '/../layout/header.php';
?>

<!-- ENCABEZADO ORIGINAL SIN LOS BOTONES DE IMPRIMIR / CSV -->
<div class="content-header" style="margin-bottom: 1.5rem;">
    <div>
        <h1 style="font-size: 1.6rem; font-weight: 800; color: #0b1536; margin: 0;">Reportes y Control de Stock</h1>
        <p style="color: #64748b; font-size: 0.85rem; margin-top: 0.3rem;">
            Monitoreo preventivo de existencias para compras, balance por rubro y trazabilidad histórica.
        </p>
    </div>
</div>

<!-- TARJETAS SUPERIORES -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="card"
        style="padding: 1.1rem; border-radius: 8px; background: #fff; border: 1px solid #e2e8f0; border-left: 4px solid #ef4444; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <span style="font-size: 0.78rem; color: #64748b; font-weight: 600;">Insumos en Nivel Crítico</span>
        <div style="font-size: 1.8rem; font-weight: 800; color: #ef4444; margin-top: 0.2rem;"><?= $kpis['criticos'] ?>
        </div>
    </div>
    <div class="card"
        style="padding: 1.1rem; border-radius: 8px; background: #fff; border: 1px solid #e2e8f0; border-left: 4px solid #f59e0b; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <span style="font-size: 0.78rem; color: #64748b; font-weight: 600;">Herramientas en Préstamo</span>
        <div style="font-size: 1.8rem; font-weight: 800; color: #f59e0b; margin-top: 0.2rem;">
            <?= $kpis['herramientas_prestadas'] ?></div>
    </div>
    <div class="card"
        style="padding: 1.1rem; border-radius: 8px; background: #fff; border: 1px solid #e2e8f0; border-left: 4px solid #2563eb; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <span style="font-size: 0.78rem; color: #64748b; font-weight: 600;">Movimientos del Mes</span>
        <div style="font-size: 1.8rem; font-weight: 800; color: #2563eb; margin-top: 0.2rem;">
            <?= $kpis['movimientos_mes'] ?></div>
    </div>
    <div class="card"
        style="padding: 1.1rem; border-radius: 8px; background: #fff; border: 1px solid #e2e8f0; border-left: 4px solid #10b981; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <span style="font-size: 0.78rem; color: #64748b; font-weight: 600;">Órdenes de Trabajo Activas</span>
        <div style="font-size: 1.8rem; font-weight: 800; color: #10b981; margin-top: 0.2rem;"><?= $kpis['ot_activas'] ?>
        </div>
    </div>
</div>

<!-- SUBPESTAÑAS DE NAVEGACIÓN -->
<div style="display: flex; gap: 1.5rem; border-bottom: 2px solid #e2e8f0; margin-bottom: 1.5rem;">
    <a href="dashboard.php?tab=compras"
        style="text-decoration: none; padding: 0.65rem 0.5rem; font-size: 0.95rem; font-weight: 700; color: <?= $tab_reporte === 'compras' ? '#0b1536; border-bottom: 3px solid #0b1536;' : '#64748b;' ?> margin-bottom: -2px;">
        Alerta Stock Mínimo (Compras) (<?= count($materiales_criticos) ?>)
    </a>
    <a href="dashboard.php?tab=auditoria"
        style="text-decoration: none; padding: 0.65rem 0.5rem; font-size: 0.95rem; font-weight: 700; color: <?= $tab_reporte === 'auditoria' ? '#0b1536; border-bottom: 3px solid #0b1536;' : '#64748b;' ?> margin-bottom: -2px;">
        Historial de Auditoría
    </a>
    <a href="dashboard.php?tab=rubros"
        style="text-decoration: none; padding: 0.65rem 0.5rem; font-size: 0.95rem; font-weight: 700; color: <?= $tab_reporte === 'rubros' ? '#0b1536; border-bottom: 3px solid #0b1536;' : '#64748b;' ?> margin-bottom: -2px;">
        Consumo por Rubro
    </a>
</div>

<?php if ($tab_reporte === 'compras'): ?>
    <!-- TABLA DE ALERTA DE COMPRAS -->
    <div class="card"
        style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div class="table-responsive">
            <table class="data-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0; text-align: left;">
                        <th style="padding: 0.75rem 0.5rem; font-size: 0.85rem; color: #0b1536;">Código</th>
                        <th style="padding: 0.75rem 0.5rem; font-size: 0.85rem; color: #0b1536;">Nombre del Insumo</th>
                        <th style="padding: 0.75rem 0.5rem; font-size: 0.85rem; color: #0b1536;">Rubro</th>
                        <th style="padding: 0.75rem 0.5rem; font-size: 0.85rem; color: #0b1536;">Ubicación</th>
                        <th style="padding: 0.75rem 0.5rem; font-size: 0.85rem; color: #0b1536;">Stock Actual</th>
                        <th style="padding: 0.75rem 0.5rem; font-size: 0.85rem; color: #0b1536;">Stock Mínimo</th>
                        <th style="padding: 0.75rem 0.5rem; font-size: 0.85rem; color: #0b1536;">Sugerido a Comprar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($materiales_criticos)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2.5rem; color: #64748b; font-size: 0.9rem;">
                                No hay insumos en nivel crítico. Existencias en estado óptimo.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($materiales_criticos as $item): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 0.75rem 0.5rem;"><strong><?= htmlspecialchars($item['codigo']) ?></strong></td>
                                <td style="padding: 0.75rem 0.5rem;"><?= htmlspecialchars($item['nombre']) ?></td>
                                <td style="padding: 0.75rem 0.5rem;"><?= htmlspecialchars($item['rubro'] ?? 'General') ?></td>
                                <td style="padding: 0.75rem 0.5rem;"><?= htmlspecialchars($item['ubicacion'] ?? 'Depósito') ?></td>
                                <td style="padding: 0.75rem 0.5rem; color: #ef4444; font-weight: 800;"><?= $item['stock_actual'] ?>
                                    <?= htmlspecialchars($item['unidad']) ?></td>
                                <td style="padding: 0.75rem 0.5rem;"><?= $item['stock_minimo'] ?>
                                    <?= htmlspecialchars($item['unidad']) ?></td>
                                <td style="padding: 0.75rem 0.5rem;">
                                    <span
                                        style="background: #dcfce7; color: #166534; font-weight: 700; padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.8rem;">
                                        + <?= $item['sugerido_comprar'] ?>             <?= htmlspecialchars($item['unidad']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($tab_reporte === 'auditoria'): ?>
    <!-- TABLA DE AUDITORÍA -->
    <div class="card"
        style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="display: flex; gap: 0.5rem; margin-bottom: 1.25rem; align-items: center; flex-wrap: wrap;">
            <span style="font-size: 0.85rem; font-weight: 700; color: #475569; margin-right: 0.5rem;">Período:</span>
            <a href="dashboard.php?tab=auditoria&periodo=hoy"
                style="text-decoration: none; padding: 0.35rem 0.75rem; font-size: 0.8rem; border-radius: 4px; font-weight: 600; background: <?= $periodo === 'hoy' ? '#0b1536; color:#fff;' : '#f1f5f9; color:#475569;' ?>">Diario
                (Hoy)</a>
            <a href="dashboard.php?tab=auditoria&periodo=semana"
                style="text-decoration: none; padding: 0.35rem 0.75rem; font-size: 0.8rem; border-radius: 4px; font-weight: 600; background: <?= $periodo === 'semana' ? '#0b1536; color:#fff;' : '#f1f5f9; color:#475569;' ?>">Semanal</a>
            <a href="dashboard.php?tab=auditoria&periodo=mes"
                style="text-decoration: none; padding: 0.35rem 0.75rem; font-size: 0.8rem; border-radius: 4px; font-weight: 600; background: <?= $periodo === 'mes' ? '#0b1536; color:#fff;' : '#f1f5f9; color:#475569;' ?>">Mensual</a>
            <a href="dashboard.php?tab=auditoria&periodo=anio"
                style="text-decoration: none; padding: 0.35rem 0.75rem; font-size: 0.8rem; border-radius: 4px; font-weight: 600; background: <?= $periodo === 'anio' ? '#0b1536; color:#fff;' : '#f1f5f9; color:#475569;' ?>">Anual</a>
        </div>

        <form method="GET" action="dashboard.php"
            style="display: flex; gap: 0.75rem; margin-bottom: 1.25rem; align-items: center; flex-wrap: wrap;">
            <input type="hidden" name="tab" value="auditoria">
            <input type="text" name="buscar" value="<?= htmlspecialchars($buscar) ?>"
                placeholder="Buscar por responsable, operario o nota..." class="form-input"
                style="flex: 2; min-width: 200px; padding: 0.45rem 0.75rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.85rem;">

            <select name="tipo_mov" class="form-input"
                style="padding: 0.45rem 0.75rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.85rem;">
                <option value="">Todos los Tipos</option>
                <option value="Entrega" <?= $tipo_mov === 'Entrega' ? 'selected' : '' ?>>Entrega</option>
                <option value="Devolución" <?= $tipo_mov === 'Devolución' ? 'selected' : '' ?>>Devolución</option>
                <option value="Ingreso" <?= $tipo_mov === 'Ingreso' ? 'selected' : '' ?>>Ingreso</option>
                <option value="Ajuste" <?= $tipo_mov === 'Ajuste' ? 'selected' : '' ?>>Ajuste</option>
            </select>

            <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" class="form-input"
                style="padding: 0.45rem 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.85rem;">
            <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="form-input"
                style="padding: 0.45rem 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.85rem;">

            <button type="submit" class="btn btn-primary"
                style="padding: 0.45rem 0.9rem; font-size: 0.85rem; font-weight: 600;">Filtrar</button>
            <a href="dashboard.php?tab=auditoria" class="btn btn-secondary"
                style="padding: 0.45rem 0.8rem; font-size: 0.85rem; text-decoration: none;">Limpiar</a>
        </form>

        <div class="table-responsive">
            <table class="data-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0; text-align: left;">
                        <th style="padding: 0.75rem 0.5rem; font-size: 0.85rem; color: #0b1536;">Fecha y Hora</th>
                        <th style="padding: 0.75rem 0.5rem; font-size: 0.85rem; color: #0b1536;">Operación</th>
                        <th style="padding: 0.75rem 0.5rem; font-size: 0.85rem; color: #0b1536;">Operario Destinatario</th>
                        <th style="padding: 0.75rem 0.5rem; font-size: 0.85rem; color: #0b1536;">Usuario Responsable</th>
                        <th style="padding: 0.75rem 0.5rem; font-size: 0.85rem; color: #0b1536;">Observaciones</th>
                        <th style="padding: 0.75rem 0.5rem; font-size: 0.85rem; color: #0b1536; text-align: right;">Acción
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($auditoria)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2.5rem; color: #64748b; font-size: 0.9rem;">
                                No se encontraron registros de auditoría para los filtros aplicados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($auditoria as $a): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 0.75rem 0.5rem;"><?= date('d/m/Y', strtotime($a['fecha'])) ?>
                                    <?= htmlspecialchars($a['hora']) ?></td>
                                <td style="padding: 0.75rem 0.5rem;">
                                    <span
                                        style="background: #e0f2fe; color: #0369a1; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 9999px; font-size: 0.8rem;">
                                        <?= htmlspecialchars($a['tipo_movimiento']) ?>
                                    </span>
                                </td>
                                <td style="padding: 0.75rem 0.5rem;"><strong><?= !empty($a['id_operario']) ? '<a href="../operario/detalle_operario.php?id=' . (int)$a['id_operario'] . '">' . htmlspecialchars($a['operario']) . '</a>' : htmlspecialchars($a['operario']) ?></strong></td>
                                <td style="padding: 0.75rem 0.5rem;"><?= htmlspecialchars($a['usuario_responsable']) ?></td>
                                <td style="padding: 0.75rem 0.5rem; color: #64748b;">
                                    <?= htmlspecialchars($a['observaciones'] ?: '-') ?></td>
                                <td style="padding: 0.75rem 0.5rem; text-align: right;">
                                    <button type="button" class="btn btn-secondary"
                                        style="padding: 0.35rem 0.7rem; font-size: 0.78rem; font-weight: 700; cursor: pointer;"
                                        onclick="abrirDetallesMovimiento(<?= $a['id_movimiento'] ?>)">
                                        Ver Ítems
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>
    <!-- TABLA DE CONSUMO POR RUBRO -->
    <div class="card"
        style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <h3 style="font-size: 1.1rem; font-weight: 800; color: #0b1536; margin-bottom: 1rem;">Distribución y Despacho
            Histórico por Rubro</h3>
        <div class="table-responsive">
            <table class="data-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0; text-align: left;">
                        <th style="padding: 0.75rem 0.5rem; font-size: 0.85rem; color: #0b1536;">Rubro / Especialidad</th>
                        <th style="padding: 0.75rem 0.5rem; font-size: 0.85rem; color: #0b1536;">Transacciones Registradas
                        </th>
                        <th style="padding: 0.75rem 0.5rem; font-size: 0.85rem; color: #0b1536;">Cantidad de Unidades
                            Despachadas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($consumo_rubros as $cr): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 0.75rem 0.5rem;"><strong><?= htmlspecialchars($cr['rubro']) ?></strong></td>
                            <td style="padding: 0.75rem 0.5rem;"><?= $cr['total_movimientos'] ?> despachos</td>
                            <td style="padding: 0.75rem 0.5rem;">
                                <span
                                    style="background: #f1f5f9; color: #0f172a; font-weight: 800; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.85rem;">
                                    <?= $cr['total_despachado'] ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- MODAL VER DETALLE DE AUDITORÍA -->
<div id="modal-auditoria"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.45); z-index: 9999; justify-content: center; align-items: center;">
    <div
        style="background: #fff; width: 100%; max-width: 600px; border-radius: 8px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
        <div
            style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.6rem; margin-bottom: 1rem;">
            <h3 style="margin: 0; font-size: 1.15rem; color: #0b1536; font-weight: 800;">
                Desglose de Ítems (Movimiento #<span id="txt-modal-id"></span>)
            </h3>
            <button type="button" onclick="cerrarModalAuditoria()"
                style="background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #64748b;">&times;</button>
        </div>

        <div id="modal-estado-carga" style="text-align: center; padding: 1.5rem; color: #64748b;">Cargando...</div>

        <div id="modal-tabla-items" style="display: none;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0; text-align: left;">
                        <th style="padding: 0.5rem;">Código</th>
                        <th style="padding: 0.5rem;">Artículo / Insumo</th>
                        <th style="padding: 0.5rem;">Cantidad</th>
                        <th style="padding: 0.5rem;">Estado Hta.</th>
                    </tr>
                </thead>
                <tbody id="modal-filas"></tbody>
            </table>
        </div>

        <div style="text-align: right; margin-top: 1.25rem;">
            <button type="button" class="btn btn-secondary" onclick="cerrarModalAuditoria()"
                style="padding: 0.45rem 1rem; font-size: 0.85rem; font-weight: 600; cursor: pointer;">
                Cerrar
            </button>
        </div>
    </div>
</div>

<script>
    function abrirDetallesMovimiento(id) {
        document.getElementById('txt-modal-id').innerText = id;
        document.getElementById('modal-estado-carga').innerText = 'Cargando ítems...';
        document.getElementById('modal-estado-carga').style.display = 'block';
        document.getElementById('modal-tabla-items').style.display = 'none';
        document.getElementById('modal-auditoria').style.display = 'flex';

        fetch('detalle_auditoria.php?id=' + id)
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.detalles && data.detalles.length > 0) {
                    document.getElementById('modal-estado-carga').style.display = 'none';
                    let tbody = document.getElementById('modal-filas');
                    tbody.innerHTML = '';
                    data.detalles.forEach(it => {
                        tbody.innerHTML += `
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 0.5rem;"><strong>${it.codigo}</strong></td>
                            <td style="padding: 0.5rem;">${it.item_nombre}</td>
                            <td style="padding: 0.5rem;">${it.cantidad} ${it.unidad}</td>
                            <td style="padding: 0.5rem;">${it.estado_herramienta}</td>
                        </tr>
                    `;
                    });
                    document.getElementById('modal-tabla-items').style.display = 'block';
                } else if (data.success) {
                    document.getElementById('modal-estado-carga').innerText = 'No hay ítems registrados en este movimiento.';
                } else {
                    document.getElementById('modal-estado-carga').innerText = 'Error: ' + (data.error || 'No se pudieron recuperar los registros.');
                }
            })
            .catch(err => {
                document.getElementById('modal-estado-carga').innerText = 'Fallo en la consulta: ' + err.message;
            });
    }

    function cerrarModalAuditoria() {
        document.getElementById('modal-auditoria').style.display = 'none';
    }
</script>

<?php
require_once __DIR__ . '/../layout/footer.php';
?>