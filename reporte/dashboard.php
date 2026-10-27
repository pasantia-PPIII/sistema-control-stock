<?php
// reporte/dashboard.php
// Reportes y métricas del sistema de control de stock.
// Las definiciones de cada métrica están en clases/reportes.php (las mismas que usa el Panel de Control).
// config.php inicia la sesión con la cookie endurecida; la autenticación se valida antes de consultar la BD.
require_once __DIR__ . '/../config/config.php';

if (!isAuthenticated()) {
    redirect('auth/login.php');
}

$seccion = 'reportes';
$css_modulo = 'reportes';
$titulo_vista = 'Reportes';
$titulo_pagina = 'Reportes - Sistema de Control de Stock';

require_once __DIR__ . '/../clases/reportes.php';
require_once __DIR__ . '/../clases/auditoria.php';

// ---------------------------------------------------------------------------------------------
// Pestañas (los nombres anteriores 'compras' y 'rubros' siguen funcionando)
// ---------------------------------------------------------------------------------------------
$pestanias = [
    'stock' => 'Stock',
    'movimientos' => 'Movimientos',
    'herramientas' => 'Herramientas',
    'ordenes' => 'Órdenes de trabajo',
    'consumo' => 'Consumo',
    'auditoria' => 'Historial',
];
$alias = ['compras' => 'stock', 'rubros' => 'consumo'];
$tab_reporte = $_GET['tab'] ?? 'stock';
$tab_reporte = $alias[$tab_reporte] ?? $tab_reporte;
if (!isset($pestanias[$tab_reporte])) {
    $tab_reporte = 'stock';
}

// ---------------------------------------------------------------------------------------------
// Utilidades de presentación
// ---------------------------------------------------------------------------------------------
$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

/** Número al estilo local (1.234,5), sin ceros decimales sobrantes. */
function rp_num($valor, int $decimales = 2): string
{
    $txt = number_format((float) $valor, $decimales, ',', '.');
    if ($decimales > 0) {
        $txt = rtrim(rtrim($txt, '0'), ',');
    }
    return $txt;
}

function rp_fecha($fecha): string
{
    return $fecha ? date('d/m/Y', strtotime((string) $fecha)) : '-';
}

function rp_dias(int $dias): string
{
    return $dias === 1 ? '1 día' : $dias . ' días';
}

// ---------------------------------------------------------------------------------------------
// Paginación: cada listado tiene su propio parámetro p_<clave>, así que paginar una sección no
// mueve las demás. Se conservan el resto de los parámetros de la URL (pestaña, período, etc.).
// ---------------------------------------------------------------------------------------------
const RP_POR_PAGINA = 10;

/** URL de esta página con los parámetros actuales y los $cambios indicados (null o '' los quita). */
function rp_url(array $cambios, string $ancla = ''): string
{
    $params = array_filter(array_merge($_GET, $cambios), fn($v) => $v !== null && $v !== '');
    return 'dashboard.php?' . http_build_query($params) . ($ancla !== '' ? '#' . $ancla : '');
}

/** Recorta $filas a la página pedida en $_GET['p_<clave>'] (por defecto la 1). */
function rp_paginar(array $filas, string $clave, int $porPagina = RP_POR_PAGINA): array
{
    $total = count($filas);
    $paginas = max(1, (int) ceil($total / $porPagina));
    $pagina = min(max(1, (int) ($_GET['p_' . $clave] ?? 1)), $paginas);
    $desde = ($pagina - 1) * $porPagina;
    return [
        'clave' => $clave,
        'filas' => array_slice($filas, $desde, $porPagina),
        'pagina' => $pagina,
        'paginas' => $paginas,
        'total' => $total,
        'desde' => $total > 0 ? $desde + 1 : 0,
        'hasta' => min($total, $desde + $porPagina),
    ];
}

/** Navegación entre páginas de un listado. No muestra nada si todo entra en una sola página. */
function rp_paginador(array $p): void
{
    if ($p['paginas'] <= 1) {
        return;
    }
    $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $ancla = 'sec-' . $p['clave'];
    $enlace = fn(int $n) => $e(rp_url(['p_' . $p['clave'] => $n > 1 ? $n : null], $ancla));

    $visibles = array_unique(array_merge([1, $p['paginas']], range(max(1, $p['pagina'] - 1), min($p['paginas'], $p['pagina'] + 1))));
    sort($visibles);

    echo '<nav class="rp-paginacion" aria-label="Paginación del listado">';
    echo '<span class="rp-paginacion__info">Mostrando ' . $p['desde'] . '-' . $p['hasta'] . ' de ' . $p['total'] . '</span>';
    echo '<span class="rp-paginacion__botones">';
    echo $p['pagina'] > 1
        ? '<a class="rp-pag" href="' . $enlace($p['pagina'] - 1) . '">Anterior</a>'
        : '<span class="rp-pag rp-pag--off">Anterior</span>';
    $previa = 0;
    foreach ($visibles as $n) {
        if ($n - $previa > 1) {
            echo '<span class="rp-pag rp-pag--puntos" aria-hidden="true">…</span>';
        }
        echo $n === $p['pagina']
            ? '<span class="rp-pag rp-pag--actual" aria-current="page">' . $n . '</span>'
            : '<a class="rp-pag" href="' . $enlace($n) . '" aria-label="Página ' . $n . '">' . $n . '</a>';
        $previa = $n;
    }
    echo $p['pagina'] < $p['paginas']
        ? '<a class="rp-pag" href="' . $enlace($p['pagina'] + 1) . '">Siguiente</a>'
        : '<span class="rp-pag rp-pag--off">Siguiente</span>';
    echo '</span></nav>';
}

/** Porcentaje (0-100) de $valor respecto de $maximo, para el ancho de las barras. */
function rp_pct($valor, $maximo): int
{
    return $maximo > 0 ? (int) max(0, min(100, round(((float) $valor / (float) $maximo) * 100))) : 0;
}

// ---------------------------------------------------------------------------------------------
// Datos: los KPIs se calculan siempre; el detalle, solo de la pestaña activa
// ---------------------------------------------------------------------------------------------
$vencimientosTotales = Reportes::totalVencimientos(30);
$ordenesPorEstado = Reportes::ordenesPorEstado();
$kpis = [
    'criticos' => Reportes::totalStockCritico(),
    'vencidos' => $vencimientosTotales['vencidos'],
    'por_vencer' => $vencimientosTotales['por_vencer'],
    'htas_prestadas' => Reportes::totalHerramientasPrestadas(),
    'htas_reparacion' => Reportes::totalHerramientasEnReparacion(),
    'ot_en_curso' => Reportes::totalOrdenesEnCurso(),
    'ot_activas' => array_sum($ordenesPorEstado),
    'ot_vencidas' => Reportes::totalOrdenesVencidas(),
    'mov_mes' => Reportes::totalMovimientosMes(),
    'mov_hoy' => Reportes::totalMovimientosHoy(),
];

$rango = Reportes::rangoValido($_GET['rango'] ?? null);

switch ($tab_reporte) {
    case 'stock':
        $stockCritico = Reportes::stockCritico();
        $stockInmovil = Reportes::stockInmovil(90);
        $vencimientos = Reportes::vencimientos(30);
        $higiene = Reportes::higieneDatos();
        break;
    case 'movimientos':
        $movPorTipo = Reportes::movimientosPorTipo($rango);
        $actividadDiaria = Reportes::movimientosPorDia(30);
        $evolucion = Reportes::evolucionMensual(6);
        $ultimosMov = Reportes::ultimosMovimientos(50);
        break;
    case 'herramientas':
        $htasPorEstado = Reportes::herramientasPorEstado();
        $htasPrestadas = Reportes::herramientasPrestadas();
        $htasPorOperario = Reportes::herramientasPrestadasPorOperario();
        break;
    case 'ordenes':
        $otVencidas = Reportes::ordenesVencidas();
        $htasSinDevolver = Reportes::herramientasSinDevolverEnOrdenesCerradas();
        $rangoOt = Reportes::rangoOrdenesValido($_GET['rango_ot'] ?? null);
        $ordenesPeriodo = Reportes::ordenesPorEstado($rangoOt);
        break;
    case 'consumo':
        $topInsumos = Reportes::topInsumosConsumidos($rango, 200);
        $porRubro = Reportes::consumoPorRubro($rango);
        $porOperario = Reportes::consumoPorOperario($rango, 200);
        $porOrden = Reportes::consumoPorOrden($rango, 200);
        $porJurisdiccion = Reportes::consumoPorJurisdiccion($rango);
        $ajustes = Reportes::ajustesPorInsumo($rango, 200);
        break;
    case 'auditoria':
        $periodo = $_GET['periodo'] ?? 'hoy';
        $buscar = trim($_GET['buscar'] ?? '');
        $desde = trim($_GET['desde'] ?? '');
        $hasta = trim($_GET['hasta'] ?? '');
        $tipo_mov = trim($_GET['tipo_mov'] ?? '');
        $auditoria = obtenerAuditoriaMovimientos($periodo, $desde ?: null, $hasta ?: null, $buscar ?: null, $tipo_mov ?: null);
        $pgAud = rp_paginar($auditoria, 'aud');
        break;
}

require_once __DIR__ . '/../layout/header.php';
?>

<!-- INDICADORES -->
<div class="rp-kpis">
    <a class="rp-kpi <?= $kpis['criticos'] > 0 ? 'rp-kpi--alerta' : 'rp-kpi--ok' ?>" href="dashboard.php?tab=stock">
        <span class="rp-kpi__titulo">Stock crítico</span>
        <span class="rp-kpi__valor"><?= $kpis['criticos'] ?></span>
        <span class="rp-kpi__detalle">materiales en o bajo el mínimo</span>
    </a>
    <a class="rp-kpi <?= $kpis['vencidos'] > 0 ? 'rp-kpi--alerta' : ($kpis['por_vencer'] > 0 ? 'rp-kpi--aviso' : 'rp-kpi--ok') ?>"
        href="dashboard.php?tab=stock#sec-venc">
        <span class="rp-kpi__titulo">Vencidos con stock</span>
        <span class="rp-kpi__valor"><?= $kpis['vencidos'] ?></span>
        <span class="rp-kpi__detalle"><?= $kpis['por_vencer'] ?> por vencer en 30 días</span>
    </a>
    <a class="rp-kpi <?= $kpis['htas_prestadas'] > 0 ? 'rp-kpi--aviso' : 'rp-kpi--ok' ?>" href="dashboard.php?tab=herramientas">
        <span class="rp-kpi__titulo">Herramientas prestadas</span>
        <span class="rp-kpi__valor"><?= $kpis['htas_prestadas'] ?></span>
        <span class="rp-kpi__detalle"><?= $kpis['htas_reparacion'] ?> en reparación</span>
    </a>
    <a class="rp-kpi rp-kpi--info" href="dashboard.php?tab=ordenes">
        <span class="rp-kpi__titulo">Órdenes en curso</span>
        <span class="rp-kpi__valor"><?= $kpis['ot_en_curso'] ?></span>
        <span class="rp-kpi__detalle">de <?= $kpis['ot_activas'] ?> órdenes activas</span>
    </a>
    <a class="rp-kpi rp-kpi--info" href="dashboard.php?tab=movimientos">
        <span class="rp-kpi__titulo">Movimientos del mes</span>
        <span class="rp-kpi__valor"><?= $kpis['mov_mes'] ?></span>
        <span class="rp-kpi__detalle"><?= $kpis['mov_hoy'] ?> hoy</span>
    </a>
</div>

<!-- PESTAÑAS -->
<nav class="rp-tabs" aria-label="Secciones de reportes">
    <?php foreach ($pestanias as $clave => $titulo): ?>
        <a href="dashboard.php?tab=<?= $h($clave) ?>" class="rp-tab <?= $tab_reporte === $clave ? 'rp-tab--activa' : '' ?>"
            <?= $tab_reporte === $clave ? 'aria-current="page"' : '' ?>><?= $h($titulo) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($tab_reporte === 'stock'): ?>
    <?php include __DIR__ . '/tabs/stock.php'; ?>
<?php elseif ($tab_reporte === 'movimientos'): ?>
    <?php include __DIR__ . '/tabs/movimientos.php'; ?>
<?php elseif ($tab_reporte === 'herramientas'): ?>
    <?php include __DIR__ . '/tabs/herramientas.php'; ?>
<?php elseif ($tab_reporte === 'ordenes'): ?>
    <?php include __DIR__ . '/tabs/ordenes.php'; ?>
<?php elseif ($tab_reporte === 'consumo'): ?>
    <?php include __DIR__ . '/tabs/consumo.php'; ?>
<?php else: ?>
    <!-- TABLA DE AUDITORÍA -->
    <div class="card" id="sec-aud"
        style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="display: flex; gap: 0.5rem; margin-bottom: 1.25rem; align-items: center; flex-wrap: wrap;">
            <span style="font-size: 0.85rem; font-weight: 700; color: #475569; margin-right: 0.5rem;">Período:</span>
            <a href="<?= $h(rp_url(['periodo' => 'hoy', 'desde' => null, 'hasta' => null, 'p_aud' => null], 'sec-aud')) ?>"
                style="text-decoration: none; padding: 0.35rem 0.75rem; font-size: 0.8rem; border-radius: 4px; font-weight: 600; background: <?= ($periodo === 'hoy' && !$desde && !$hasta) ? '#0b1536; color:#fff;' : '#f1f5f9; color:#475569;' ?>">Diario
                (Hoy)</a>
            <a href="<?= $h(rp_url(['periodo' => 'semana', 'desde' => null, 'hasta' => null, 'p_aud' => null], 'sec-aud')) ?>"
                style="text-decoration: none; padding: 0.35rem 0.75rem; font-size: 0.8rem; border-radius: 4px; font-weight: 600; background: <?= ($periodo === 'semana' && !$desde && !$hasta) ? '#0b1536; color:#fff;' : '#f1f5f9; color:#475569;' ?>">Semanal</a>
            <a href="<?= $h(rp_url(['periodo' => 'mes', 'desde' => null, 'hasta' => null, 'p_aud' => null], 'sec-aud')) ?>"
                style="text-decoration: none; padding: 0.35rem 0.75rem; font-size: 0.8rem; border-radius: 4px; font-weight: 600; background: <?= ($periodo === 'mes' && !$desde && !$hasta) ? '#0b1536; color:#fff;' : '#f1f5f9; color:#475569;' ?>">Mensual</a>
            <a href="<?= $h(rp_url(['periodo' => 'anio', 'desde' => null, 'hasta' => null, 'p_aud' => null], 'sec-aud')) ?>"
                style="text-decoration: none; padding: 0.35rem 0.75rem; font-size: 0.8rem; border-radius: 4px; font-weight: 600; background: <?= ($periodo === 'anio' && !$desde && !$hasta) ? '#0b1536; color:#fff;' : '#f1f5f9; color:#475569;' ?>">Anual</a>
        </div>

        <form method="GET" action="dashboard.php"
            style="display: flex; gap: 0.75rem; margin-bottom: 1.25rem; align-items: center; flex-wrap: wrap;">
            <input type="hidden" name="tab" value="auditoria">
            <input type="hidden" name="periodo" value="<?= $h($periodo) ?>">
            <input type="text" name="buscar" value="<?= htmlspecialchars($buscar) ?>"
                placeholder="Buscar por responsable, operario o nota..." class="form-input"
                style="flex: 2; min-width: 200px; padding: 0.45rem 0.75rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.85rem;">

            <select name="tipo_mov" class="form-input"
                style="padding: 0.45rem 0.75rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.85rem;">
                <option value="">Todos los Tipos</option>
                <option value="Entrega" <?= $tipo_mov === 'Entrega' ? 'selected' : '' ?>>Entrega</option>
                <option value="Devolución" <?= $tipo_mov === 'Devolución' ? 'selected' : '' ?>>Devolución</option>
                <option value="Ingreso" <?= $tipo_mov === 'Ingreso' ? 'selected' : '' ?>>Ingreso</option>
                <option value="Ajuste de Inventario" <?= $tipo_mov === 'Ajuste de Inventario' ? 'selected' : '' ?>>Ajuste</option>
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
                        <?php foreach ($pgAud['filas'] as $a): ?>
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
        <?php rp_paginador($pgAud); ?>
    </div>
<?php endif; ?>

<?php if ($tab_reporte === 'auditoria'): ?>
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
<?php endif; ?>

<?php
require_once __DIR__ . '/../layout/footer.php';
?>

