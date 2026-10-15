<?php
// reporte/dashboard.php
session_start();

// 1. Inclusión de la clase de reportes
require_once __DIR__ . '/../clases/reportes.php';

// 2. Parámetros de filtros y pestañas
$tab_reporte = $_GET['tab'] ?? 'compras';
$periodo = $_GET['periodo'] ?? 'hoy';
$buscar = trim($_GET['buscar'] ?? '');
$desde = trim($_GET['desde'] ?? '');
$hasta = trim($_GET['hasta'] ?? '');

// 3. Consultas a la base de datos
$kpis = obtenerKPISReporte();
$materiales_criticos = obtenerListaStockCritico();
$auditoria = obtenerAuditoriaMovimientos($periodo, $desde ?: null, $hasta ?: null, $buscar ?: null);

// 4. Inclusión del header institucional (en singular)
$seccion = 'reportes';
require_once __DIR__ . '/../layout/header.php';
?>

<div class="content-header" style="margin-bottom: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <h1 style="font-size: 1.6rem; font-weight: 800; color: #0b1536; margin: 0;">Reportes y Auditoría</h1>
            <p style="color: #64748b; font-size: 0.85rem; margin-top: 0.3rem;">
                Monitoreo preventivo de existencias para compras y trazabilidad histórica de auditoría.
            </p>
        </div>
        <div>
            <button type="button" onclick="window.print()" class="btn btn-secondary"
                style="padding: 0.55rem 1.2rem; font-size: 0.85rem; font-weight: 700; cursor: pointer;">
                Imprimir / Exportar
            </button>
        </div>
    </div>
</div>

<!-- TARJETAS RESUMEN / CRITERIOS -->
<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="card"
        style="padding: 1.1rem; border-radius: 8px; background: #fff; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <span style="font-size: 0.78rem; color: #64748b; font-weight: 600;">Insumos en Estado Crítico</span>
        <div style="font-size: 1.8rem; font-weight: 800; color: #ef4444; margin-top: 0.2rem;"><?= $kpis['criticos'] ?>
        </div>
    </div>
    <div class="card"
        style="padding: 1.1rem; border-radius: 8px; background: #fff; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <span style="font-size: 0.78rem; color: #64748b; font-weight: 600;">Criterio de Alerta</span>
        <div style="font-size: 1.15rem; font-weight: 700; color: #0b1536; margin-top: 0.4rem;">Stock Actual &le; Stock
            Mínimo</div>
    </div>
    <div class="card"
        style="padding: 1.1rem; border-radius: 8px; background: #fff; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <span style="font-size: 0.78rem; color: #64748b; font-weight: 600;">Acción Operativa Sugerida</span>
        <div style="font-size: 1.15rem; font-weight: 700; color: #166534; margin-top: 0.4rem;">Emisión de Pedido de
            Compra</div>
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
</div>

<?php if ($tab_reporte === 'compras'): ?>
    <!-- PESTAÑA 1: TABLA DE ALERTA DE COMPRAS -->
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
                        <th style="padding: 0.75rem 0.5rem; font-size: 0.85rem; color: #0b1536;">Unidad</th>
                        <th style="padding: 0.75rem 0.5rem; font-size: 0.85rem; color: #0b1536;">Stock Actual</th>
                        <th style="padding: 0.75rem 0.5rem; font-size: 0.85rem; color: #0b1536;">Stock Mínimo</th>
                        <th style="padding: 0.75rem 0.5rem; font-size: 0.85rem; color: #0b1536;">Sugerido a Comprar</th>
                        <th style="padding: 0.75rem 0.5rem; font-size: 0.85rem; color: #0b1536; text-align: right;">Acción
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($materiales_criticos)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 2.5rem; color: #64748b; font-size: 0.9rem;">
                                No hay insumos en nivel crítico. Todas las existencias se encuentran por encima del stock de
                                seguridad.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($materiales_criticos as $item): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 0.75rem 0.5rem;"><strong><?= htmlspecialchars($item['codigo']) ?></strong></td>
                                <td style="padding: 0.75rem 0.5rem;"><?= htmlspecialchars($item['nombre']) ?></td>
                                <td style="padding: 0.75rem 0.5rem;"><?= htmlspecialchars($item['rubro'] ?? 'General') ?></td>
                                <td style="padding: 0.75rem 0.5rem;"><?= htmlspecialchars($item['ubicacion'] ?? 'Depósito') ?></td>
                                <td style="padding: 0.75rem 0.5rem;"><?= htmlspecialchars($item['unidad']) ?></td>
                                <td style="padding: 0.75rem 0.5rem; color: #ef4444; font-weight: 800;"><?= $item['stock_actual'] ?>
                                </td>
                                <td style="padding: 0.75rem 0.5rem;"><?= $item['stock_minimo'] ?></td>
                                <td style="padding: 0.75rem 0.5rem;">
                                    <span
                                        style="background: #dcfce7; color: #166534; font-weight: 700; padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.8rem;">
                                        + <?= $item['sugerido_comprar'] ?>
                                    </span>
                                </td>
                                <td style="padding: 0.75rem 0.5rem; text-align: right;">
                                    <a href="../insumo/crear_insumo.php" class="btn btn-primary"
                                        style="padding: 0.35rem 0.75rem; font-size: 0.8rem; text-decoration: none;">Comprar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>
    <!-- PESTAÑA 2: TABLA DE HISTORIAL DE AUDITORÍA -->
    <div class="card"
        style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <!-- Botones de Período Rápido -->
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

        <!-- Filtros por Fechas y Texto -->
        <form method="GET" action="dashboard.php"
            style="display: flex; gap: 0.75rem; margin-bottom: 1.25rem; align-items: center;">
            <input type="hidden" name="tab" value="auditoria">
            <input type="text" name="buscar" value="<?= htmlspecialchars($buscar) ?>"
                placeholder="Buscar por responsable, operario o nota..." class="form-input"
                style="flex: 1; padding: 0.45rem 0.75rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.85rem;">
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
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($auditoria)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2.5rem; color: #64748b; font-size: 0.9rem;">
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
                                <td style="padding: 0.75rem 0.5rem;"><strong><?= htmlspecialchars($a['operario']) ?></strong></td>
                                <td style="padding: 0.75rem 0.5rem;"><?= htmlspecialchars($a['usuario_responsable']) ?></td>
                                <td style="padding: 0.75rem 0.5rem; color: #64748b;">
                                    <?= htmlspecialchars($a['observaciones'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php
// 5. Inclusión del footer institucional (en singular)
require_once __DIR__ . '/../layout/footer.php';
?>