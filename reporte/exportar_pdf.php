<?php
/**
 * reporte/exportar_pdf.php
 * Exporta a PDF la pestaña de Reportes indicada (RF-41).
 *
 * Parámetros GET:
 *   tab      : stock | movimientos | herramientas | ordenes | consumo (por defecto stock)
 *   rango    : período de Movimientos y Consumo (mes | 30d | 90d | anio | todo)
 *   rango_ot : período de "Órdenes por estado" (semana | mes | anio | todo)
 *
 * Exporta el listado completo (no solo la página que se ve en pantalla). Lo puede usar cualquier usuario con sesión,
 * incluido el rol de solo lectura: los reportes no modifican datos.
 */
require_once __DIR__ . '/../config/config.php';

if (!isAuthenticated()) {
    redirect('auth/login.php');
}

require_once __DIR__ . '/../clases/reportes.php';
require_once __DIR__ . '/../clases/pdf_reporte.php';

/** Cantidad al estilo local (1.234,5), sin ceros decimales sobrantes. */
function pdf_num($valor, int $decimales = 2): string
{
    $txt = number_format((float) $valor, $decimales, ',', '.');
    return $decimales > 0 ? rtrim(rtrim($txt, '0'), ',') : $txt;
}

function pdf_fecha($fecha): string
{
    return $fecha ? date('d/m/Y', strtotime((string) $fecha)) : '-';
}

function pdf_dias($dias): string
{
    $dias = (int) $dias;
    return $dias === 1 ? '1 día' : $dias . ' días';
}

// Los listados paginados de Reportes se piden con una página enorme para obtener todas las filas
const PDF_TODAS = 100000;

$pestanias = [
    'stock' => 'Stock',
    'movimientos' => 'Movimientos',
    'herramientas' => 'Herramientas',
    'ordenes' => 'Órdenes de trabajo',
    'consumo' => 'Consumo',
];
$tab = $_GET['tab'] ?? 'stock';
if (!isset($pestanias[$tab])) {
    $tab = 'stock';
}
$rango = Reportes::rangoValido($_GET['rango'] ?? null);
$rotuloRango = Reportes::RANGOS[$rango][0];

$subtitulo = 'Generado el ' . date('d/m/Y H:i') . ' por ' . ($_SESSION['usuario_nombre'] ?? 'Usuario');
$pdf = new PdfReporte('Reporte de ' . mb_strtolower($pestanias[$tab], 'UTF-8'), $subtitulo);

try {
    switch ($tab) {
        case 'stock':
            $filas = Reportes::stockCriticoPaginado(1, PDF_TODAS)['filas'];
            $pdf->seccion('Stock crítico', 'Materiales con stock actual igual o menor al mínimo, los más faltantes primero. Es el insumo para gestionar compras.');
            $pdf->tabla(
                ['Código', 'Material', 'Rubro', 'Ubicación', 'Stock', 'Mínimo'],
                array_map(fn($f) => [$f['codigo'], $f['nombre'], $f['rubro'], $f['ubicacion'],
                    pdf_num($f['stock_actual']) . ' ' . $f['unidad'], pdf_num($f['stock_minimo']) . ' ' . $f['unidad']], $filas),
                [1, 3.2, 1.6, 2.4, 1.3, 1.3], [4 => 'R', 5 => 'R'],
                'Ningún material está en nivel crítico.'
            );

            $filas = Reportes::vencimientosPaginado(1, PDF_TODAS, 30)['filas'];
            $pdf->seccion('Vencimientos', 'Perecederos con stock que ya vencieron o vencen en los próximos 30 días.');
            $pdf->tabla(
                ['Código', 'Material', 'Vencimiento', 'Situación', 'Stock'],
                array_map(function ($f) {
                    $d = (int) $f['dias_restantes'];
                    $situacion = $d < 0 ? 'Vencido hace ' . pdf_dias(-$d) : ($d === 0 ? 'Vence hoy' : 'Vence en ' . pdf_dias($d));
                    return [$f['codigo'], $f['nombre'], pdf_fecha($f['fecha_vencimiento']), $situacion, pdf_num($f['stock_actual']) . ' ' . $f['unidad']];
                }, $filas),
                [1, 3.5, 1.4, 2, 1.4], [4 => 'R'],
                'No hay materiales vencidos ni próximos a vencer.'
            );

            $filas = Reportes::stockInmovilPaginado(1, PDF_TODAS, 90)['filas'];
            $pdf->seccion('Stock inmóvil', 'Materiales con stock que no salieron en los últimos 90 días. Los más antiguos primero.');
            $pdf->tabla(
                ['Código', 'Material', 'Stock', 'Última entrega', 'Sin salida hace'],
                array_map(fn($f) => [$f['codigo'], $f['nombre'], pdf_num($f['stock_actual']) . ' ' . $f['unidad'],
                    $f['ultima_entrega'] ? pdf_fecha($f['ultima_entrega']) : 'Nunca', pdf_dias($f['dias_sin_salida'])], $filas),
                [1, 3.5, 1.4, 1.6, 1.6], [2 => 'R', 4 => 'R'],
                'No hay materiales con stock inmóvil.'
            );
            break;

        case 'movimientos':
            $pdf->seccion('Movimientos por tipo', 'Movimientos registrados (sin los anulados) y cantidad de ítems movidos. Período: ' . $rotuloRango . '.');
            $pdf->tabla(
                ['Tipo', 'Movimientos', 'Ítems'],
                array_map(fn($f) => [$f['tipo'], $f['movimientos'], $f['items']], Reportes::movimientosPorTipo($rango)),
                [3, 1, 1], [1 => 'R', 2 => 'R']
            );

            $pdf->seccion('Evolución mensual', 'Cantidad de movimientos por mes en los últimos 6 meses.');
            $pdf->tabla(
                ['Mes', 'Ingresos', 'Entregas', 'Devoluciones'],
                array_map(fn($f) => [$f['mes'], $f['ingresos'], $f['entregas'], $f['devoluciones']], Reportes::evolucionMensual(6)),
                [2, 1, 1, 1], [1 => 'R', 2 => 'R', 3 => 'R']
            );

            $limite = 1000;
            $pagina = Reportes::ultimosMovimientosPaginado(1, $limite);
            $pdf->seccion('Últimos movimientos', 'Movimientos activos (sin anulados), del más nuevo al más antiguo.'
                . ($pagina['total'] > $limite ? " Se muestran los últimos {$limite} de {$pagina['total']}." : ''));
            $pdf->tabla(
                ['N°', 'Fecha y hora', 'Tipo', 'Operario', 'Orden', 'Ítems'],
                array_map(fn($f) => ['#' . $f['id_movimiento'], pdf_fecha($f['fecha']) . ' ' . substr((string) $f['hora'], 0, 5),
                    $f['tipo'], $f['operario'], $f['numero_ot'] ?: '-', $f['items']], $pagina['filas']),
                [0.8, 1.6, 1.5, 3, 1.5, 0.8], [5 => 'R']
            );
            break;

        case 'herramientas':
            $pdf->seccion('Estado de las herramientas', 'Herramientas activas según su estado actual.');
            $estados = Reportes::herramientasPorEstado();
            $pdf->tabla(
                ['Estado', 'Cantidad'],
                array_merge(array_map(fn($e, $n) => [$e, $n], array_keys($estados), array_values($estados)),
                    [['Total', array_sum($estados)]]),
                [3, 1], [1 => 'R']
            );

            $filas = Reportes::herramientasPrestadasPaginado(1, PDF_TODAS)['filas'];
            $pdf->seccion('Herramientas prestadas', 'Quién tiene cada herramienta y desde cuándo. Las de mayor antigüedad primero.');
            $pdf->tabla(
                ['Código', 'Herramienta', 'Operario', 'Entregada', 'Antigüedad'],
                array_map(fn($f) => [$f['codigo'], $f['nombre'], $f['operario'], pdf_fecha($f['fecha_entrega']),
                    $f['dias_prestada'] === null ? 'Sin dato' : pdf_dias($f['dias_prestada'])], $filas),
                [1, 3, 3, 1.4, 1.4], [4 => 'R'],
                'No hay herramientas prestadas.'
            );

            $pdf->seccion('Herramientas por operario', 'Cuántas herramientas tiene prestadas cada operario y el préstamo más antiguo.');
            $pdf->tabla(
                ['Operario', 'Herramientas', 'Préstamo más antiguo', 'Detalle'],
                array_map(fn($f) => [$f['operario'], $f['cantidad'], $f['max_dias'] === null ? '-' : pdf_dias($f['max_dias']),
                    implode(', ', $f['herramientas'])], Reportes::herramientasPrestadasPorOperario()),
                [2.4, 1, 1.4, 5], [1 => 'R', 2 => 'R'],
                'Ningún operario tiene herramientas prestadas.'
            );
            break;

        case 'ordenes':
            $rangoOt = Reportes::rangoOrdenesValido($_GET['rango_ot'] ?? null);
            $pdf->seccion('Órdenes por estado', 'Órdenes de trabajo activas agrupadas por estado. Período (por fecha de inicio): ' . Reportes::RANGOS_ORDENES[$rangoOt][0] . '.');
            $porEstado = Reportes::ordenesPorEstado($rangoOt);
            $pdf->tabla(
                ['Estado', 'Órdenes'],
                array_merge(array_map(fn($e, $n) => [$e, $n], array_keys($porEstado), array_values($porEstado)),
                    [['Total', array_sum($porEstado)]]),
                [3, 1], [1 => 'R']
            );

            $filas = Reportes::ordenesVencidasPaginado(1, PDF_TODAS)['filas'];
            $pdf->seccion('Órdenes vencidas', 'Su fecha final ya pasó y todavía no están Finalizadas ni Anuladas. Las de mayor atraso primero.');
            $pdf->tabla(
                ['Orden', 'Estado', 'Localidad', 'Jurisdicción', 'Fecha final', 'Atraso'],
                array_map(fn($f) => [$f['numero_ot'], $f['estado'], $f['localidad'], $f['jurisdiccion'],
                    pdf_fecha($f['fecha_final']), pdf_dias($f['dias_atraso'])], $filas),
                [1.4, 1.2, 1.6, 3.6, 1.2, 1], [5 => 'R'],
                'No hay órdenes vencidas.'
            );

            $pdf->seccion('Herramientas sin devolver de órdenes cerradas', 'La orden ya está Finalizada o Anulada pero la herramienta que salió para ella sigue prestada.');
            $pdf->tabla(
                ['Orden', 'Estado', 'Herramienta', 'Operario', 'Prestada hace'],
                array_map(fn($f) => [$f['numero_ot'], $f['estado'], $f['codigo'] . ' - ' . $f['herramienta'], $f['operario'],
                    $f['dias_prestada'] === null ? 'Sin dato' : pdf_dias($f['dias_prestada'])], Reportes::herramientasSinDevolverEnOrdenesCerradas()),
                [1.4, 1.2, 3.6, 2.6, 1.2], [4 => 'R'],
                'No hay herramientas sin devolver en órdenes cerradas.'
            );
            break;

        case 'consumo':
            $nota = ' Período: ' . $rotuloRango . '.';
            $pdf->seccion('Materiales más consumidos', 'Ordenados por consumo neto (entregado menos devuelto).' . $nota);
            $pdf->tabla(
                ['Código', 'Material', 'Entregado', 'Devuelto', 'Consumo neto'],
                array_map(fn($f) => [$f['codigo'], $f['nombre'], pdf_num($f['entregado']) . ' ' . $f['unidad'],
                    pdf_num($f['devuelto']) . ' ' . $f['unidad'], pdf_num($f['neto']) . ' ' . $f['unidad']],
                    Reportes::topInsumosConsumidosPaginado(1, PDF_TODAS, $rango)['filas']),
                [1, 3.6, 1.4, 1.4, 1.5], [2 => 'R', 3 => 'R', 4 => 'R'],
                'No hay consumo en el período.'
            );

            $pdf->seccion('Consumo por rubro', 'Unidades netas entregadas por rubro del material.' . $nota);
            $pdf->tabla(
                ['Rubro', 'Movimientos', 'Entregado', 'Devuelto', 'Consumo neto'],
                array_map(fn($f) => [$f['rubro'], $f['movimientos'], pdf_num($f['entregado']), pdf_num($f['devuelto']), pdf_num($f['neto'])],
                    Reportes::consumoPorRubro($rango)),
                [3, 1, 1.2, 1.2, 1.3], [1 => 'R', 2 => 'R', 3 => 'R', 4 => 'R'],
                'No hay consumo en el período.'
            );

            $pdf->seccion('Consumo por jurisdicción', 'Según la jurisdicción de la orden de trabajo a la que se entregó el material.' . $nota);
            $pdf->tabla(
                ['Jurisdicción', 'Movimientos', 'Consumo neto'],
                array_map(fn($f) => [$f['jurisdiccion'], $f['movimientos'], pdf_num($f['neto'])], Reportes::consumoPorJurisdiccion($rango)),
                [5, 1, 1.3], [1 => 'R', 2 => 'R'],
                'No hay consumo en el período.'
            );

            $pdf->seccion('Consumo por operario', 'Operarios que recibieron material, de mayor a menor (neto).' . $nota);
            $pdf->tabla(
                ['Operario', 'Movimientos', 'Consumo neto'],
                array_map(fn($f) => [$f['operario'], $f['movimientos'], pdf_num($f['neto'])],
                    Reportes::consumoPorOperarioPaginado(1, PDF_TODAS, $rango)['filas']),
                [5, 1, 1.3], [1 => 'R', 2 => 'R'],
                'No hay consumo en el período.'
            );

            $pdf->seccion('Consumo por orden de trabajo', 'Órdenes que más material consumieron. Solo movimientos asociados a una orden.' . $nota);
            $pdf->tabla(
                ['Orden', 'Estado', 'Movimientos', 'Consumo neto'],
                array_map(fn($f) => [$f['numero_ot'], $f['estado'], $f['movimientos'], pdf_num($f['neto'])],
                    Reportes::consumoPorOrdenPaginado(1, PDF_TODAS, $rango)['filas']),
                [2, 2, 1, 1.3], [2 => 'R', 3 => 'R'],
                'No hay consumo en el período.'
            );

            $pdf->seccion('Ajustes de inventario', 'Materiales con más ajustes en el período.' . $nota);
            $pdf->tabla(
                ['Código', 'Material', 'Ajustes', 'Último ajuste'],
                array_map(fn($f) => [$f['codigo'], $f['nombre'], $f['ajustes'], pdf_fecha($f['ultimo_ajuste'])],
                    Reportes::ajustesPorInsumoPaginado(1, PDF_TODAS, $rango)['filas']),
                [1, 4, 1, 1.4], [2 => 'R'],
                'No hay ajustes en el período.'
            );
            break;
    }

    $pdf->descargar('reporte-' . $tab . '-' . date('Ymd') . '.pdf');
} catch (Throwable $e) {
    error_log('Exportar PDF: ' . $e->getMessage());
    setAlert('No se pudo generar el PDF del reporte. Intente nuevamente.', 'danger');
    redirect('reporte/dashboard.php?tab=' . urlencode($tab));
}
