<?php
// clases/reportes.php
require_once __DIR__ . '/../config/database.php';

/**
 * 1. KPIs del encabezado
 */
function obtenerKPISReporte(): array
{
    $pdo = Database::getConnection();

    // Insumos en estado crítico comparando con la tabla stock_actual
    $stmtCriticos = $pdo->query("
        SELECT COUNT(*) AS total 
        FROM insumos i
        LEFT JOIN stock_actual sa ON i.id_stock_actual = sa.id_stock_actual
        WHERE i.activo = TRUE 
          AND COALESCE(sa.stock_actual, 0) <= i.stock_minimo
    ");
    $criticos = (int) ($stmtCriticos->fetchColumn() ?: 0);

    // Herramientas prestadas (id_estado_herramienta = 2)
    $stmtHerramientas = $pdo->query("
        SELECT COUNT(*) AS total 
        FROM herramienta 
        WHERE activo = TRUE AND id_estado_herramienta = 2
    ");
    $herramientasPrestadas = (int) ($stmtHerramientas->fetchColumn() ?: 0);

    // Movimientos del mes en curso
    $stmtMovimientos = $pdo->query("
        SELECT COUNT(*) AS total 
        FROM movimiento 
        WHERE activo = TRUE 
          AND DATE_TRUNC('month', fecha) = DATE_TRUNC('month', CURRENT_DATE)
    ");
    $movimientosMes = (int) ($stmtMovimientos->fetchColumn() ?: 0);

    // Órdenes de trabajo en proceso
    $stmtOT = $pdo->query("
        SELECT COUNT(*) AS total 
        FROM orden_de_trabajo 
        WHERE activo = TRUE AND estado ILIKE '%proceso%'
    ");
    $otActivas = (int) ($stmtOT->fetchColumn() ?: 0);

    return [
        'criticos' => $criticos,
        'herramientas_prestadas' => $herramientasPrestadas,
        'movimientos_mes' => $movimientosMes,
        'ot_activas' => $otActivas
    ];
}

/**
 * 2. Insumos Críticos para la Pestaña "Alerta Stock Mínimo (Compras)"
 */
function obtenerListaStockCritico(): array
{
    $sql = "
        SELECT 
            i.id_insumo,
            i.codigo,
            i.nombre,
            r.nombre AS rubro,
            COALESCE(u.ubicacion, 'Depósito') AS ubicacion,
            COALESCE(um.unidad_medida, 'Unid.') AS unidad,
            COALESCE(sa.stock_actual, 0) AS stock_actual,
            i.stock_minimo,
            GREATEST((i.stock_minimo * 2) - COALESCE(sa.stock_actual, 0), i.stock_minimo) AS sugerido_comprar
        FROM insumos i
        LEFT JOIN stock_actual sa ON i.id_stock_actual = sa.id_stock_actual
        LEFT JOIN rubro r ON i.id_rubro = r.id_rubro
        LEFT JOIN ubicacion u ON i.id_ubicacion = u.id_ubicacion
        LEFT JOIN unidad_medida um ON i.id_unidad_medida = um.id_unidad_medida
        WHERE i.activo = TRUE 
          AND COALESCE(sa.stock_actual, 0) <= i.stock_minimo
        ORDER BY (i.stock_minimo - COALESCE(sa.stock_actual, 0)) DESC
    ";
    return Database::fetchAll($sql);
}

/**
 * 3. Historial de Auditoría de Movimientos
 */
function obtenerAuditoriaMovimientos(?string $periodo = 'hoy', ?string $desde = null, ?string $hasta = null, ?string $buscar = null): array
{
    $sql = "
        SELECT 
            m.id_movimiento,
            m.fecha, 
            m.hora, 
            tm.tipo AS tipo_movimiento,
            COALESCE(o.nombre || ' ' || o.apellido, 'Sin operario') AS operario,
            COALESCE(u.nombre || ' ' || u.apellido, u.dni) AS usuario_responsable,
            m.observaciones
        FROM movimiento m
        INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
        LEFT JOIN operario o ON m.id_operario = o.id_operario
        LEFT JOIN usuario u ON m.dni_usuario = u.dni
        WHERE m.activo = TRUE
    ";
    $params = [];

    if (!empty($periodo) && empty($desde) && empty($hasta)) {
        if ($periodo === 'hoy') {
            $sql .= " AND m.fecha = CURRENT_DATE";
        } elseif ($periodo === 'semana') {
            $sql .= " AND m.fecha >= (CURRENT_DATE - INTERVAL '7 days')";
        } elseif ($periodo === 'mes') {
            $sql .= " AND DATE_TRUNC('month', m.fecha) = DATE_TRUNC('month', CURRENT_DATE)";
        } elseif ($periodo === 'anio') {
            $sql .= " AND DATE_TRUNC('year', m.fecha) = DATE_TRUNC('year', CURRENT_DATE)";
        }
    }

    if (!empty($desde)) {
        $sql .= " AND m.fecha >= :fdesde";
        $params[':fdesde'] = $desde;
    }
    if (!empty($hasta)) {
        $sql .= " AND m.fecha <= :fhasta";
        $params[':fhasta'] = $hasta;
    }

    if (!empty($buscar)) {
        $sql .= " AND (u.nombre ILIKE :b OR u.apellido ILIKE :b OR o.nombre ILIKE :b OR o.apellido ILIKE :b OR m.observaciones ILIKE :b)";
        $params[':b'] = "%{$buscar}%";
    }

    $sql .= " ORDER BY m.fecha DESC, m.hora DESC LIMIT 50";
    return Database::fetchAll($sql, $params);
}