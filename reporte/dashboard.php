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
$js_modulo = 'reportes';
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
if (!canViewAudit()) {
    unset($pestanias['auditoria']);
}
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

/**
 * Página pedida en $_GET['p_<clave>'] (por defecto la 1). Los listados grandes la pasan a Reportes::*Paginado(),
 * que pagina en la base con LIMIT/OFFSET; rp_paginar() solo queda para listas chicas que ya están en memoria.
 */
function rp_pagina(string $clave): int
{
    return max(1, (int) ($_GET['p_' . $clave] ?? 1));
}

/** Agrega la clave del listado al resultado de un *Paginado(), con la forma que espera rp_paginador(). */
function rp_clave(string $clave, array $pagina): array
{
    return ['clave' => $clave] + $pagina;
}

/** Recorta $filas (ya en memoria) a la página pedida en $_GET['p_<clave>']. Para listas chicas. */
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

    // Ventana de 10 botones numéricos, igual que la paginación de los demás listados del sistema
    $ventana = 10;
    $inicio = max(1, min($p['pagina'] - 4, $p['paginas'] - $ventana + 1));
    $fin = min($p['paginas'], $inicio + $ventana - 1);

    echo '<nav class="rp-paginacion" aria-label="Paginación del listado">';
    echo '<span class="rp-paginacion__info">Mostrando ' . $p['desde'] . '-' . $p['hasta'] . ' de ' . $p['total'] . '</span>';
    echo '<span class="rp-paginacion__botones">';
    echo $p['pagina'] > 1
        ? '<a class="rp-pag rp-pag--texto" href="' . $enlace($p['pagina'] - 1) . '">Anterior</a>'
        : '<span class="rp-pag rp-pag--texto rp-pag--off">Anterior</span>';
    for ($n = $inicio; $n <= $fin; $n++) {
        echo $n === $p['pagina']
            ? '<span class="rp-pag rp-pag--actual" aria-current="page">' . $n . '</span>'
            : '<a class="rp-pag" href="' . $enlace($n) . '" aria-label="Página ' . $n . '">' . $n . '</a>';
    }
    echo $p['pagina'] < $p['paginas']
        ? '<a class="rp-pag rp-pag--texto" href="' . $enlace($p['pagina'] + 1) . '">Siguiente</a>'
        : '<span class="rp-pag rp-pag--texto rp-pag--off">Siguiente</span>';
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
        $pgCrit = rp_clave('crit', Reportes::stockCriticoPaginado(rp_pagina('crit'), RP_POR_PAGINA));
        $pgVenc = rp_clave('venc', Reportes::vencimientosPaginado(rp_pagina('venc'), RP_POR_PAGINA, 30));
        $pgInm = rp_clave('inm', Reportes::stockInmovilPaginado(rp_pagina('inm'), RP_POR_PAGINA, 90));
        $higiene = Reportes::higieneDatos();
        break;
    case 'movimientos':
        $movPorTipo = Reportes::movimientosPorTipo($rango);
        $actividadDiaria = Reportes::movimientosPorDia(30);
        $evolucion = Reportes::evolucionMensual(6);
        $pgUlt = rp_clave('um', Reportes::ultimosMovimientosPaginado(rp_pagina('um'), RP_POR_PAGINA));
        break;
    case 'herramientas':
        $htasPorEstado = Reportes::herramientasPorEstado();
        $pgPrest = rp_clave('prest', Reportes::herramientasPrestadasPaginado(rp_pagina('prest'), RP_POR_PAGINA));
        $htasPorOperario = Reportes::herramientasPrestadasPorOperario();
        break;
    case 'ordenes':
        $pgVencidas = rp_clave('ovenc', Reportes::ordenesVencidasPaginado(rp_pagina('ovenc'), RP_POR_PAGINA));
        $htasSinDevolver = Reportes::herramientasSinDevolverEnOrdenesCerradas();
        $rangoOt = Reportes::rangoOrdenesValido($_GET['rango_ot'] ?? null);
        $ordenesPeriodo = Reportes::ordenesPorEstado($rangoOt);
        break;
    case 'consumo':
        $pgTop = rp_clave('top', Reportes::topInsumosConsumidosPaginado(rp_pagina('top'), RP_POR_PAGINA, $rango));
        $porRubro = Reportes::consumoPorRubro($rango);
        $pgOpe = rp_clave('ope', Reportes::consumoPorOperarioPaginado(rp_pagina('ope'), RP_POR_PAGINA, $rango));
        $pgOrd = rp_clave('ord', Reportes::consumoPorOrdenPaginado(rp_pagina('ord'), RP_POR_PAGINA, $rango));
        $porJurisdiccion = Reportes::consumoPorJurisdiccion($rango);
        $pgAju = rp_clave('aju', Reportes::ajustesPorInsumoPaginado(rp_pagina('aju'), RP_POR_PAGINA, $rango));
        break;
    case 'auditoria':
        $periodo = $_GET['periodo'] ?? 'hoy';
        if (!in_array($periodo, ['hoy', 'semana', 'mes', 'anio'], true)) {
            $periodo = 'hoy';
        }
        $buscar = trim($_GET['buscar'] ?? '');
        $desde = trim($_GET['desde'] ?? '');
        $hasta = trim($_GET['hasta'] ?? '');

        // Las fechas del formulario deben ser YYYY-MM-DD válidas; si no, se ignoran (evita el error SQL).
        $fechaValida = function (string $fecha): bool {
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $m)) {
                return false;
            }
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
        };
        if ($desde !== '' && !$fechaValida($desde)) {
            $desde = '';
            setAlert('La fecha "desde" no es válida; se ignoró ese filtro.', 'warning');
        }
        if ($hasta !== '' && !$fechaValida($hasta)) {
            $hasta = '';
            setAlert('La fecha "hasta" no es válida; se ignoró ese filtro.', 'warning');
        }

        $tipo_mov = trim($_GET['tipo_mov'] ?? '');
        $pgAud = rp_clave('aud', obtenerAuditoriaMovimientosPaginado(rp_pagina('aud'), RP_POR_PAGINA, $periodo, $desde ?: null, $hasta ?: null, $buscar ?: null, $tipo_mov ?: null));
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
    <div class="card rp-aud" id="sec-aud">
        <?php
        // Botón de período activo (solo si no hay rango de fechas manual)
        $periodoActivo = fn(string $p) => ($periodo === $p && !$desde && !$hasta) ? ' rp-aud__periodo--activo' : '';
        ?>
        <div class="rp-aud__periodos">
            <span class="rp-aud__periodos-titulo">Período:</span>
            <a class="rp-aud__periodo<?= $periodoActivo('hoy') ?>"
                href="<?= $h(rp_url(['periodo' => 'hoy', 'desde' => null, 'hasta' => null, 'p_aud' => null], 'sec-aud')) ?>">Diario
                (Hoy)</a>
            <a class="rp-aud__periodo<?= $periodoActivo('semana') ?>"
                href="<?= $h(rp_url(['periodo' => 'semana', 'desde' => null, 'hasta' => null, 'p_aud' => null], 'sec-aud')) ?>">Semanal</a>
            <a class="rp-aud__periodo<?= $periodoActivo('mes') ?>"
                href="<?= $h(rp_url(['periodo' => 'mes', 'desde' => null, 'hasta' => null, 'p_aud' => null], 'sec-aud')) ?>">Mensual</a>
            <a class="rp-aud__periodo<?= $periodoActivo('anio') ?>"
                href="<?= $h(rp_url(['periodo' => 'anio', 'desde' => null, 'hasta' => null, 'p_aud' => null], 'sec-aud')) ?>">Anual</a>
        </div>

        <form method="GET" action="dashboard.php" class="rp-aud__filtros">
            <input type="hidden" name="tab" value="auditoria">
            <input type="hidden" name="periodo" value="<?= $h($periodo) ?>">
            <input type="text" name="buscar" value="<?= htmlspecialchars($buscar) ?>"
                placeholder="Buscar por responsable, operario o nota..." class="form-input rp-aud__input rp-aud__input--buscar">

            <select name="tipo_mov" class="form-input rp-aud__input">
                <option value="">Todos los Tipos</option>
                <option value="Entrega" <?= $tipo_mov === 'Entrega' ? 'selected' : '' ?>>Entrega</option>
                <option value="Devolución" <?= $tipo_mov === 'Devolución' ? 'selected' : '' ?>>Devolución</option>
                <option value="Ingreso" <?= $tipo_mov === 'Ingreso' ? 'selected' : '' ?>>Ingreso</option>
                <option value="Ajuste de Inventario" <?= $tipo_mov === 'Ajuste de Inventario' ? 'selected' : '' ?>>Ajuste</option>
            </select>

            <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" class="form-input rp-aud__input rp-aud__input--fecha">
            <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="form-input rp-aud__input rp-aud__input--fecha">

            <button type="submit" class="btn btn-primary rp-aud__btn-filtrar">Filtrar</button>
            <a href="dashboard.php?tab=auditoria" class="btn btn-secondary rp-aud__btn-limpiar">Limpiar</a>
        </form>

        <div class="table-responsive">
            <table class="data-table rp-aud__tabla">
                <thead>
                    <tr>
                        <th>Fecha y Hora</th>
                        <th>Operación</th>
                        <th>Operario Destinatario</th>
                        <th>Usuario Responsable</th>
                        <th>Observaciones</th>
                        <th class="rp-aud__col-der">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pgAud['filas'])): ?>
                        <tr>
                            <td colspan="6" class="rp-aud__vacio">
                                No se encontraron registros de auditoría para los filtros aplicados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pgAud['filas'] as $a): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($a['fecha'])) ?>
                                    <?= htmlspecialchars($a['hora']) ?></td>
                                <td>
                                    <span class="rp-aud__tipo">
                                        <?= htmlspecialchars($a['tipo_movimiento']) ?>
                                    </span>
                                </td>
                                <td><strong><?= enlaceOperario($a['id_operario'] ?? null, $a['operario'] ?? null) ?></strong></td>
                                <td><?= htmlspecialchars($a['usuario_responsable']) ?></td>
                                <td class="rp-aud__obs">
                                    <?= htmlspecialchars($a['observaciones'] ?: '-') ?></td>
                                <td class="rp-aud__col-der">
                                    <button type="button" class="btn btn-secondary rp-aud__btn-items"
                                        onclick="abrirDetallesMovimiento(<?= (int) $a['id_movimiento'] ?>)">
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
<div id="modal-auditoria" class="rp-modal">
    <div class="rp-modal__caja">
        <div class="rp-modal__cabecera">
            <h3 class="rp-modal__titulo">
                Desglose de Ítems (Movimiento #<span id="txt-modal-id"></span>)
            </h3>
            <button type="button" class="modal-close-btn rp-modal__cerrar" onclick="cerrarModalAuditoria()" title="Cerrar" aria-label="Cerrar"><?= iconoAccion('close', 18) ?></button>
        </div>

        <div id="modal-estado-carga" class="rp-modal__estado">Cargando...</div>

        <div id="modal-tabla-items" class="oculto">
            <table class="rp-modal__tabla">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Artículo / Insumo</th>
                        <th>Cantidad</th>
                        <th>Estado Hta.</th>
                    </tr>
                </thead>
                <tbody id="modal-filas"></tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
require_once __DIR__ . '/../layout/footer.php';
?>

