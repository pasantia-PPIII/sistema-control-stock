<?php
/**
 * ajax/buscar_autocompletado.php
 * Endpoint AJAX (GET, JSON) del autocompletado de operarios, insumos y órdenes de trabajo
 * (nueva entrega/devolución, usuarios y comisión de las órdenes de trabajo).
 *
 * Parámetros:
 *   recurso : 'operario' | 'insumo' | 'odt'
 *   q       : texto a buscar (vacío = todos)
 *   pagina  : número de página, de a 10 resultados (por defecto 1)
 *   excluir : ids separados por coma que no deben aparecer (solo para 'operario': los ya elegidos)
 *   tipo    : 'entrega' | 'devolucion' (solo para 'insumo': al entregar no se pueden elegir
 *             materiales sin stock ni herramientas que no estén Disponibles)
 *
 * Respuesta: { success, items: [...], hay_mas }
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Sesión no iniciada. Inicie sesión nuevamente.']);
    exit;
}
requireRole(['admin', 'panolero']);

require_once __DIR__ . '/../clases/operario.php';
require_once __DIR__ . '/../clases/insumo.php';
require_once __DIR__ . '/../clases/movimiento.php';
require_once __DIR__ . '/../clases/orden_de_trabajo.php';

const AUTOCOMPLETADO_POR_PAGINA = 10;

try {
    $recurso = $_GET['recurso'] ?? '';
    $termino = trim((string)($_GET['q'] ?? ''));
    $pagina = max(1, (int)($_GET['pagina'] ?? 1));
    $esDevolucion = (($_GET['tipo'] ?? '') === 'devolucion');

    $offset = ($pagina - 1) * AUTOCOMPLETADO_POR_PAGINA;
    // Se pide uno de más para saber si hay otra página sin hacer un COUNT
    $limite = AUTOCOMPLETADO_POR_PAGINA + 1;

    $items = [];

    if ($recurso === 'operario') {
        $filas = (new Operario())->buscarParaSelect($termino, $limite, $offset, explode(',', (string)($_GET['excluir'] ?? '')));
        foreach ($filas as $op) {
            $items[] = [
                'id'    => (int)$op['id_operario'],
                'texto' => $op['apellido'] . ', ' . $op['nombre'] . ' - DNI: ' . $op['dni'],
            ];
        }
    } elseif ($recurso === 'insumo') {
        $filas = (new Insumo())->buscarParaSelect($termino, $limite, $offset);
        $estados = array_column((new Movimiento())->getEstadosHerramienta(), 'estado', 'id_estado_herramienta');

        foreach ($filas as $ins) {
            $esHerramienta = ((int)$ins['id_tipo'] === 2);
            $tipo = $esHerramienta ? 'Herramienta' : 'Material';
            $unidad = $ins['unidad_medida'] ?? 'unidades';
            $stock = (float)($ins['stock_actual'] ?? 0);

            if ($esHerramienta) {
                // Una herramienta es una unidad: stock 1 = en el pañol, 0 = no está. Se entrega solo si está Disponible
                $idEstado = (int)($ins['id_estado_herramienta'] ?? 0) ?: 1;
                $noSeleccionable = !$esDevolucion && ($idEstado !== 1 || $stock < 1);
                $detalle = 'Estado: ' . ($estados[$idEstado] ?? 'Desconocido') . " - Stock: {$stock}";
            } else {
                $noSeleccionable = !$esDevolucion && $stock <= 0;
                $detalle = "Stock: {$stock} {$unidad}";
            }

            $items[] = [
                'codigo'          => $ins['codigo'],
                'nombre'          => $ins['nombre'],
                'tipo'            => $tipo,
                'unidad'          => $unidad,
                'stock'           => $stock,
                'deshabilitado'   => $noSeleccionable,
                'texto'           => "{$ins['codigo']} - {$ins['nombre']} ({$tipo}) - {$detalle}",
            ];
        }
    } elseif ($recurso === 'odt') {
        // Una entrega no se puede vincular a una orden Finalizada o Anulada (RF-27); una devolución sí
        $filas = (new OrdenDeTrabajo())->buscarParaSelect($termino, $limite, $offset, !$esDevolucion);
        foreach ($filas as $ot) {
            $items[] = [
                'id'    => (int)$ot['id_odt'],
                'texto' => OrdenDeTrabajo::textoParaSelect($ot),
            ];
        }
    } else {
        throw new Exception('Recurso de búsqueda no válido.');
    }

    $hayMas = count($items) > AUTOCOMPLETADO_POR_PAGINA;
    echo json_encode([
        'success' => true,
        'items'   => array_slice($items, 0, AUTOCOMPLETADO_POR_PAGINA),
        'hay_mas' => $hayMas,
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => safeErrorMessage($e)]);
}
exit;
