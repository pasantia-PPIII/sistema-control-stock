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

// ---------------------------------------------------------------------------------------------
// Pestañas (los nombres anteriores 'compras' y 'rubros' siguen funcionando)
// ---------------------------------------------------------------------------------------------
$pestanias = [
    'stock' => 'Stock',
    'movimientos' => 'Movimientos',
    'herramientas' => 'Herramientas',
    'ordenes' => 'Órdenes de trabajo',
    'consumo' => 'Consumo',
];
$alias = ['compras' => 'stock', 'rubros' => 'consumo'];
$tab_reporte = $_GET['tab'] ?? 'stock';
$tab_reporte = $alias[$tab_reporte] ?? $tab_reporte;
// El historial de auditoría ya no está en Reportes: tiene su propia sección (solo Administrador)
if ($tab_reporte === 'auditoria' && canViewAudit()) {
    redirect('auditoria/dashboard.php?tab=movimientos');
}
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

<!-- EXPORTAR (RF-41): el PDF lleva la pestaña activa con el período elegido -->
<div class="rp-exportar">
    <a class="rp-exportar__btn" href="<?= $h('exportar_pdf.php?' . http_build_query(array_filter([
        'tab' => $tab_reporte,
        'rango' => $_GET['rango'] ?? null,
        'rango_ot' => $_GET['rango_ot'] ?? null,
    ], fn($v) => $v !== null && $v !== ''))) ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
        Exportar PDF
    </a>
</div>

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
<?php endif; ?>

<?php
require_once __DIR__ . '/../layout/footer.php';
?>

