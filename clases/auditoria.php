<?php
// clases/auditoria.php
// Historial de auditoría de movimientos (pestaña "Historial").
// Las métricas y KPIs (stock, herramientas, órdenes, consumo) viven en clases/reportes.php.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/reportes.php';

/**
 * Arma la consulta del historial con sus filtros (sin LIMIT). Devuelve [sql, params].
 */
function sqlAuditoriaMovimientos(?string $periodo, ?string $desde, ?string $hasta, ?string $buscar, ?string $tipo_mov): array
{
    $sql = "
        SELECT 
            m.id_movimiento,
            m.fecha, 
            m.hora, 
            tm.tipo AS tipo_movimiento,
            m.id_operario,
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
        $sql .= " AND (u.nombre ILIKE :b OR u.apellido ILIKE :b OR o.nombre ILIKE :b OR o.apellido ILIKE :b
            OR (o.nombre || ' ' || o.apellido) ILIKE :b OR (u.nombre || ' ' || u.apellido) ILIKE :b OR m.observaciones ILIKE :b)";
        $params[':b'] = "%{$buscar}%";
    }

    $sql .= " ORDER BY m.fecha DESC, m.hora DESC, m.id_movimiento DESC";
    return [$sql, $params];
}

/**
 * Historial de auditoría paginado en la base (LIMIT/OFFSET): devuelve la página pedida y el total de registros.
 * @see Reportes::paginado() para la estructura devuelta.
 */
function obtenerAuditoriaMovimientosPaginado(int $pagina, int $porPagina, ?string $periodo = 'hoy', ?string $desde = null, ?string $hasta = null, ?string $buscar = null, ?string $tipo_mov = null): array
{
    [$sql, $params] = sqlAuditoriaMovimientos($periodo, $desde, $hasta, $buscar, $tipo_mov);
    return Reportes::paginado($sql, $params, $pagina, $porPagina);
}

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
