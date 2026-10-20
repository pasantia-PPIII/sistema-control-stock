<?php
// clases/reportes.php
require_once __DIR__ . '/../config/database.php';

function obtenerKPISReporte(): array
{
    $pdo = Database::getConnection();

    $stmtCriticos = $pdo->query("
        SELECT COUNT(*) AS total 
        FROM insumos i
        LEFT JOIN tipo t ON i.id_tipo = t.id_tipo
        LEFT JOIN stock_actual sa ON i.id_stock_actual = sa.id_stock_actual
        WHERE i.activo = TRUE
          AND COALESCE(t.tipo, '') <> 'Herramienta'
          AND COALESCE(sa.stock_actual, 0) <= i.stock_minimo
    ");
    $criticos = (int) ($stmtCriticos->fetchColumn() ?: 0);

    $stmtHerramientas = $pdo->query("
        SELECT COUNT(*) AS total 
        FROM herramienta 
        WHERE activo = TRUE AND id_estado_herramienta = 2
    ");
    $herramientasPrestadas = (int) ($stmtHerramientas->fetchColumn() ?: 0);

    $stmtMovimientos = $pdo->query("
        SELECT COUNT(*) AS total 
        FROM movimiento 
        WHERE activo = TRUE 
          AND DATE_TRUNC('month', fecha) = DATE_TRUNC('month', CURRENT_DATE)
    ");
    $movimientosMes = (int) ($stmtMovimientos->fetchColumn() ?: 0);

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

function obtenerListaStockCritico(): array
{
    $sql = "
        SELECT 
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
        LEFT JOIN tipo t ON i.id_tipo = t.id_tipo
        WHERE i.activo = TRUE
          AND COALESCE(t.tipo, '') <> 'Herramienta'
          AND COALESCE(sa.stock_actual, 0) <= i.stock_minimo
        ORDER BY (i.stock_minimo - COALESCE(sa.stock_actual, 0)) DESC
    ";
    return Database::fetchAll($sql);
}

function obtenerAuditoriaMovimientos(?string $periodo = 'hoy', ?string $desde = null, ?string $hasta = null, ?string $buscar = null, ?string $tipo_mov = null): array
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

    if (!empty($tipo_mov)) {
        $sql .= " AND tm.tipo = :tipo_mov";
        $params[':tipo_mov'] = $tipo_mov;
    }

    if (!empty($buscar)) {
        $sql .= " AND (u.nombre ILIKE :b OR u.apellido ILIKE :b OR o.nombre ILIKE :b OR o.apellido ILIKE :b OR m.observaciones ILIKE :b)";
        $params[':b'] = "%{$buscar}%";
    }

    $sql .= " ORDER BY m.fecha DESC, m.hora DESC LIMIT 50";
    return Database::fetchAll($sql, $params);
}

/**
 * Consulta de líneas detalladas de un movimiento para el Modal
 */
/**
 * Consulta de líneas detalladas de un movimiento para el Modal
 */
function obtenerDetalleAuditoriaPorId(int $id_movimiento): array
{
    $pdo = Database::getConnection();

    $sql = "
        SELECT 
            COALESCE(i.codigo, '-') AS codigo,
            COALESCE(i.nombre, 'Artículo no identificado') AS item_nombre,
            COALESCE(md.cantidad, 1) AS cantidad,
            COALESCE(um.unidad_medida, 'Unid.') AS unidad,
            COALESCE(eh.estado, 'N/A') AS estado_herramienta
        FROM movimiento_detalle md
        LEFT JOIN insumos i ON md.id_insumo = i.id_insumo
        LEFT JOIN unidad_medida um ON i.id_unidad_medida = um.id_unidad_medida
        LEFT JOIN estado_herramienta eh ON md.id_estado_herramienta = eh.id_estado_herramienta
        WHERE md.id_movimiento = :id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id_movimiento]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Consulta para la distribución de consumo por rubro
 */
function obtenerConsumoPorRubro(): array
{
    $sql = "
        SELECT 
            COALESCE(r.nombre, 'Sin Rubro') AS rubro,
            COUNT(DISTINCT md.id_movimiento) AS total_movimientos,
            COALESCE(SUM(md.cantidad), 0) AS total_despachado
        FROM movimiento_detalle md
        INNER JOIN insumos i ON md.id_insumo = i.id_insumo
        LEFT JOIN rubro r ON i.id_rubro = r.id_rubro
        INNER JOIN movimiento m ON md.id_movimiento = m.id_movimiento
        WHERE m.activo = TRUE
        GROUP BY r.nombre
        ORDER BY total_despachado DESC
    ";
    return Database::fetchAll($sql);
}