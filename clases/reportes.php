<?php
// clases/reportes.php
// Definiciones ÚNICAS de las métricas de stock, herramientas, órdenes de trabajo y consumo.
// Las usan el Panel de Control (clases/panel.php) y el módulo de Reportes (reporte/dashboard.php),
// de modo que un mismo concepto ("stock crítico", "herramienta prestada", ...) da el mismo número en ambos.
//
// Convenciones:
// - Solo lectura. Solo cuentan movimientos activos (los anulados no existen para las métricas).
// - Material = insumo cuyo tipo NO es 'Herramienta'. Las herramientas se miden aparte (estado, no cantidad).
// - Consumo de un material = Entregas - Devoluciones (consumo neto).
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/movimiento.php';

class Reportes
{
    const TIPO_HERRAMIENTA = 'Herramienta';

    const HTA_DISPONIBLE = 'Disponible';
    const HTA_PRESTADA = 'Prestada';
    const HTA_REPARACION = 'En Reparación';
    const HTA_BAJA = 'Dada de baja';

    // Estados de orden de trabajo (ver Orden_de_trabajo::getEstadosPosibles). 'En Proceso' es el
    // nombre anterior que aún traen datos viejos de prueba.
    const OT_EN_CURSO = ['En Progreso', 'En Proceso'];
    const OT_CERRADAS = ['Finalizada', 'Anulada'];

    /** Rangos de tiempo de las métricas de consumo: clave => [etiqueta, sentencia SQL de fecha mínima o null]. */
    const RANGOS = [
        'mes' => ['Mes actual', "DATE_TRUNC('month', CURRENT_DATE)::date"],
        '30d' => ['Últimos 30 días', "(CURRENT_DATE - 30)"],
        '90d' => ['Últimos 90 días', "(CURRENT_DATE - 90)"],
        'anio' => ['Año actual', "DATE_TRUNC('year', CURRENT_DATE)::date"],
        'todo' => ['Todo el historial', null],
    ];

    /** Períodos del gráfico de órdenes por estado (según fecha de inicio): clave => [etiqueta, fecha mínima SQL o null]. */
    const RANGOS_ORDENES = [
        'semana' => ['Esta semana', "DATE_TRUNC('week', CURRENT_DATE)::date"],
        'mes' => ['Este mes', "DATE_TRUNC('month', CURRENT_DATE)::date"],
        'anio' => ['Este año', "DATE_TRUNC('year', CURRENT_DATE)::date"],
        'todo' => ['Todas', null],
    ];

    // ---------------------------------------------------------------------------------------------
    // Utilidades
    // ---------------------------------------------------------------------------------------------

    public static function rangoOrdenesValido(?string $rango): string
    {
        return isset(self::RANGOS_ORDENES[$rango ?? '']) ? $rango : 'todo';
    }

    public static function rangoValido(?string $rango): string
    {
        return isset(self::RANGOS[$rango ?? '']) ? $rango : '30d';
    }

    /** Condición SQL sobre m.fecha para un rango (cadena vacía si es todo el historial). */
    private static function condicionRango(string $rango): string
    {
        $desde = self::RANGOS[self::rangoValido($rango)][1];
        return $desde === null ? '' : " AND m.fecha >= {$desde}";
    }

    /** Lista de literales SQL ('a','b') a partir de constantes propias (nunca de datos del usuario). */
    private static function enLista(array $valores): string
    {
        return implode(',', array_map(fn($v) => "'" . str_replace("'", "''", $v) . "'", $valores));
    }

    private static function contar(string $sql, array $params = []): int
    {
        $fila = Database::fetch($sql, $params);
        return (int) ($fila['total'] ?? 0);
    }

    /**
     * Pagina una consulta en la base de datos (LIMIT/OFFSET) en vez de traer todas las filas.
     * $sql debe terminar en su ORDER BY (con un desempate estable) y sin LIMIT. Con $campoMax devuelve además
     * el máximo de esa columna sobre TODO el resultado (para escalar barras sin depender de la página).
     * Devuelve: filas, total, pagina (ya acotada al rango válido), paginas, desde, hasta y maximo.
     */
    public static function paginado(string $sql, array $params, int $pagina, int $porPagina, ?string $campoMax = null): array
    {
        $porPagina = max(1, $porPagina);
        $max = ($campoMax !== null && preg_match('/^\w+$/', $campoMax)) ? ", MAX({$campoMax}) AS maximo" : '';
        $res = Database::fetch("SELECT COUNT(*) AS total{$max} FROM ({$sql}) q", $params) ?? [];
        $total = (int) ($res['total'] ?? 0);
        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina = min(max(1, $pagina), $paginas);
        $desde = ($pagina - 1) * $porPagina;
        return [
            'filas' => $total > 0 ? Database::fetchAll($sql . ' LIMIT ' . $porPagina . ' OFFSET ' . $desde, $params) : [],
            'pagina' => $pagina,
            'paginas' => $paginas,
            'total' => $total,
            'desde' => $total > 0 ? $desde + 1 : 0,
            'hasta' => min($total, $desde + $porPagina),
            'maximo' => (float) ($res['maximo'] ?? 0),
        ];
    }

    /** Suma ponderada del consumo neto (Entrega suma, Devolución resta) sobre movimiento_detalle `md`. */
    private static function sqlNeto(): string
    {
        return "SUM(CASE tm.tipo WHEN '" . Movimiento::TIPO_ENTREGA . "' THEN md.cantidad "
            . "WHEN '" . Movimiento::TIPO_DEVOLUCION . "' THEN -md.cantidad ELSE 0 END)";
    }

    private static function sqlEntregado(): string
    {
        return "SUM(CASE WHEN tm.tipo = '" . Movimiento::TIPO_ENTREGA . "' THEN md.cantidad ELSE 0 END)";
    }

    private static function sqlDevuelto(): string
    {
        return "SUM(CASE WHEN tm.tipo = '" . Movimiento::TIPO_DEVOLUCION . "' THEN md.cantidad ELSE 0 END)";
    }

    /** FROM/JOIN base de las métricas de consumo de materiales (movimientos activos de Entrega/Devolución). */
    private static function fromConsumo(string $joinsExtra = ''): string
    {
        return "FROM movimiento_detalle md
                INNER JOIN movimiento m ON md.id_movimiento = m.id_movimiento AND m.activo = TRUE
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                INNER JOIN insumos i ON md.id_insumo = i.id_insumo
                INNER JOIN tipo t ON i.id_tipo = t.id_tipo AND t.tipo <> '" . self::TIPO_HERRAMIENTA . "'
                {$joinsExtra}
                WHERE tm.tipo IN ('" . Movimiento::TIPO_ENTREGA . "', '" . Movimiento::TIPO_DEVOLUCION . "')";
    }

    // ---------------------------------------------------------------------------------------------
    // STOCK
    // ---------------------------------------------------------------------------------------------

    /**
     * Stock crítico: materiales activos con stock actual <= stock mínimo (sin registro de stock = 0).
     * Se sugiere comprar lo necesario para llegar al doble del mínimo (como mínimo, el mismo mínimo).
     * Los más críticos (mayor faltante) primero.
     */
    public static function stockCritico(?int $limite = null): array
    {
        $sql = self::sqlStockCritico();
        if ($limite !== null) {
            $sql .= ' LIMIT ' . (int) $limite;
        }
        return Database::fetchAll($sql);
    }

    /** Página $pagina (de $porPagina filas) de stockCritico(), paginada en la base con LIMIT/OFFSET. */
    public static function stockCriticoPaginado(int $pagina, int $porPagina): array
    {
        return self::paginado(self::sqlStockCritico(), [], $pagina, $porPagina);
    }

    private static function sqlStockCritico(): string
    {
        return "
            SELECT i.codigo, i.nombre,
                   COALESCE(r.nombre, 'Sin rubro') AS rubro,
                   COALESCE(u.ubicacion, 'Sin ubicación') AS ubicacion,
                   COALESCE(um.unidad_medida, 'Unid.') AS unidad,
                   COALESCE(um.unidad_medida, 'Unid.') AS unidad_medida,
                   COALESCE(sa.stock_actual, 0) AS stock_actual,
                   i.stock_minimo,
                   GREATEST((i.stock_minimo * 2) - COALESCE(sa.stock_actual, 0), i.stock_minimo) AS sugerido_comprar
            FROM insumos i
            INNER JOIN tipo t ON i.id_tipo = t.id_tipo
            LEFT JOIN stock_actual sa ON i.id_stock_actual = sa.id_stock_actual
            LEFT JOIN rubro r ON i.id_rubro = r.id_rubro
            LEFT JOIN ubicacion u ON i.id_ubicacion = u.id_ubicacion
            LEFT JOIN unidad_medida um ON i.id_unidad_medida = um.id_unidad_medida
            WHERE i.activo = TRUE
              AND t.tipo <> '" . self::TIPO_HERRAMIENTA . "'
              AND COALESCE(sa.stock_actual, 0) <= i.stock_minimo
            ORDER BY (i.stock_minimo - COALESCE(sa.stock_actual, 0)) DESC, i.nombre ASC, i.codigo ASC";
    }

    public static function totalStockCritico(): int
    {
        return self::contar("
            SELECT COUNT(*) AS total
            FROM insumos i
            INNER JOIN tipo t ON i.id_tipo = t.id_tipo
            LEFT JOIN stock_actual sa ON i.id_stock_actual = sa.id_stock_actual
            WHERE i.activo = TRUE
              AND t.tipo <> '" . self::TIPO_HERRAMIENTA . "'
              AND COALESCE(sa.stock_actual, 0) <= i.stock_minimo");
    }

    /**
     * Perecederos con stock que ya vencieron o vencen dentro de $dias días.
     * Cada fila trae dias_restantes (negativo = ya vencido) y estado ('Vencido' | 'Por vencer').
     */
    public static function vencimientos(int $dias = 30, ?int $limite = null): array
    {
        $sql = self::sqlVencimientos($dias);
        if ($limite !== null) {
            $sql .= ' LIMIT ' . (int) $limite;
        }
        return Database::fetchAll($sql);
    }

    /** Página $pagina (de $porPagina filas) de vencimientos(), paginada en la base con LIMIT/OFFSET. */
    public static function vencimientosPaginado(int $pagina, int $porPagina, int $dias = 30): array
    {
        return self::paginado(self::sqlVencimientos($dias), [], $pagina, $porPagina);
    }

    private static function sqlVencimientos(int $dias): string
    {
        return "
            SELECT i.codigo, i.nombre, i.fecha_vencimiento,
                   COALESCE(sa.stock_actual, 0) AS stock_actual,
                   COALESCE(um.unidad_medida, 'Unid.') AS unidad,
                   (i.fecha_vencimiento - CURRENT_DATE) AS dias_restantes,
                   CASE WHEN i.fecha_vencimiento < CURRENT_DATE THEN 'Vencido' ELSE 'Por vencer' END AS estado
            FROM insumos i
            LEFT JOIN stock_actual sa ON i.id_stock_actual = sa.id_stock_actual
            LEFT JOIN unidad_medida um ON i.id_unidad_medida = um.id_unidad_medida
            WHERE i.activo = TRUE AND i.es_perecedero = TRUE
              AND i.fecha_vencimiento IS NOT NULL
              AND i.fecha_vencimiento <= CURRENT_DATE + " . (int) $dias . "
              AND COALESCE(sa.stock_actual, 0) > 0
            ORDER BY i.fecha_vencimiento ASC, i.nombre ASC, i.codigo ASC";
    }

    /** Cantidad de perecederos con stock: ['vencidos' => n, 'por_vencer' => n]. */
    public static function totalVencimientos(int $dias = 30): array
    {
        $f = Database::fetch("
            SELECT COUNT(*) FILTER (WHERE i.fecha_vencimiento < CURRENT_DATE) AS vencidos,
                   COUNT(*) FILTER (WHERE i.fecha_vencimiento >= CURRENT_DATE) AS por_vencer
            FROM insumos i
            LEFT JOIN stock_actual sa ON i.id_stock_actual = sa.id_stock_actual
            WHERE i.activo = TRUE AND i.es_perecedero = TRUE
              AND i.fecha_vencimiento IS NOT NULL
              AND i.fecha_vencimiento <= CURRENT_DATE + " . (int) $dias . "
              AND COALESCE(sa.stock_actual, 0) > 0");
        return ['vencidos' => (int) ($f['vencidos'] ?? 0), 'por_vencer' => (int) ($f['por_vencer'] ?? 0)];
    }

    /**
     * Días de cobertura: cuántos días alcanza el stock actual al ritmo de consumo neto de los últimos
     * $ventana días. Solo materiales con consumo neto positivo en la ventana. Los de menor cobertura primero.
     */
    public static function cobertura(int $ventana = 30, int $limite = 10): array
    {
        $ventana = max(1, $ventana);
        return Database::fetchAll("
            SELECT i.codigo, i.nombre,
                   COALESCE(um.unidad_medida, 'Unid.') AS unidad,
                   COALESCE(sa.stock_actual, 0) AS stock_actual,
                   c.neto AS consumo_ventana,
                   ROUND(c.neto / {$ventana}, 2) AS consumo_diario,
                   ROUND(COALESCE(sa.stock_actual, 0) / (c.neto / {$ventana}), 1) AS dias_cobertura
            FROM (
                SELECT md.id_insumo, " . self::sqlNeto() . " AS neto
                " . self::fromConsumo() . "
                  AND m.fecha >= CURRENT_DATE - {$ventana}
                GROUP BY md.id_insumo
                HAVING " . self::sqlNeto() . " > 0
            ) c
            INNER JOIN insumos i ON i.id_insumo = c.id_insumo AND i.activo = TRUE
            LEFT JOIN stock_actual sa ON i.id_stock_actual = sa.id_stock_actual
            LEFT JOIN unidad_medida um ON i.id_unidad_medida = um.id_unidad_medida
            ORDER BY dias_cobertura ASC, i.nombre ASC
            LIMIT " . (int) $limite);
    }

    /**
     * Stock inmóvil: materiales activos con stock que no tuvieron ninguna Entrega en los últimos $dias días
     * (y que fueron dados de alta hace más de $dias días). Los más antiguos primero.
     */
    public static function stockInmovil(int $dias = 90, ?int $limite = null): array
    {
        $sql = self::sqlStockInmovil($dias);
        if ($limite !== null) {
            $sql .= ' LIMIT ' . (int) $limite;
        }
        return Database::fetchAll($sql);
    }

    /** Página $pagina (de $porPagina filas) de stockInmovil(), paginada en la base con LIMIT/OFFSET. */
    public static function stockInmovilPaginado(int $pagina, int $porPagina, int $dias = 90): array
    {
        return self::paginado(self::sqlStockInmovil($dias), [], $pagina, $porPagina);
    }

    private static function sqlStockInmovil(int $dias): string
    {
        $dias = max(1, $dias);
        return "
            SELECT i.codigo, i.nombre,
                   COALESCE(um.unidad_medida, 'Unid.') AS unidad,
                   sa.stock_actual,
                   le.ultima_entrega,
                   (CURRENT_DATE - COALESCE(le.ultima_entrega, i.fecha_registro)) AS dias_sin_salida
            FROM insumos i
            INNER JOIN tipo t ON i.id_tipo = t.id_tipo AND t.tipo <> '" . self::TIPO_HERRAMIENTA . "'
            INNER JOIN stock_actual sa ON i.id_stock_actual = sa.id_stock_actual AND sa.stock_actual > 0
            LEFT JOIN unidad_medida um ON i.id_unidad_medida = um.id_unidad_medida
            LEFT JOIN (
                SELECT md.id_insumo, MAX(m.fecha) AS ultima_entrega
                FROM movimiento_detalle md
                INNER JOIN movimiento m ON md.id_movimiento = m.id_movimiento AND m.activo = TRUE
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov AND tm.tipo = '" . Movimiento::TIPO_ENTREGA . "'
                GROUP BY md.id_insumo
            ) le ON le.id_insumo = i.id_insumo
            WHERE i.activo = TRUE
              AND COALESCE(le.ultima_entrega, i.fecha_registro) < CURRENT_DATE - {$dias}
            ORDER BY dias_sin_salida DESC, i.nombre ASC, i.codigo ASC";
    }

    /**
     * Calidad de datos del catálogo: insumos activos con configuración incompleta que vuelve ciega
     * a otras métricas (sin stock mínimo no hay alerta de stock crítico, etc.).
     */
    public static function higieneDatos(): array
    {
        $etiquetas = [
            'sin_stock_minimo' => 'Materiales sin stock mínimo (nunca generan alerta)',
            'sin_ubicacion' => 'Insumos sin ubicación',
            'sin_unidad' => 'Insumos sin unidad de medida',
            'sin_registro_stock' => 'Insumos sin registro de stock',
            'perecedero_sin_fecha' => 'Perecederos sin fecha de vencimiento',
        ];
        $afectados = array_fill_keys(array_keys($etiquetas), []);
        $filas = Database::fetchAll("
            SELECT i.id_insumo, i.codigo, i.nombre, t.tipo,
                   (t.tipo <> '" . self::TIPO_HERRAMIENTA . "' AND i.stock_minimo <= 0) AS sin_stock_minimo,
                   (i.id_ubicacion IS NULL) AS sin_ubicacion,
                   (i.id_unidad_medida IS NULL) AS sin_unidad,
                   (i.id_stock_actual IS NULL) AS sin_registro_stock,
                   (i.es_perecedero = TRUE AND i.fecha_vencimiento IS NULL) AS perecedero_sin_fecha
            FROM insumos i
            INNER JOIN tipo t ON i.id_tipo = t.id_tipo
            WHERE i.activo = TRUE
            ORDER BY i.codigo");
        foreach ($filas as $f) {
            foreach ($etiquetas as $clave => $_) {
                if ($f[$clave] === true || $f[$clave] === 't' || $f[$clave] === 1 || $f[$clave] === '1') {
                    $afectados[$clave][] = [
                        'id_insumo' => (int) $f['id_insumo'],
                        'codigo' => $f['codigo'],
                        'nombre' => $f['nombre'],
                        'tipo' => $f['tipo'],
                    ];
                }
            }
        }
        // clave => [etiqueta, cantidad, insumos afectados]
        $out = [];
        foreach ($etiquetas as $clave => $etiqueta) {
            $out[$clave] = [$etiqueta, count($afectados[$clave]), $afectados[$clave]];
        }
        return $out;
    }

    // ---------------------------------------------------------------------------------------------
    // HERRAMIENTAS (el estado vive en insumos.id_estado_herramienta; lo actualizan los movimientos)
    // ---------------------------------------------------------------------------------------------

    /** Herramientas activas por estado: ['Disponible' => n, 'Prestada' => n, ...] (todos los estados, aun en 0). */
    public static function herramientasPorEstado(): array
    {
        $out = [];
        foreach (Database::fetchAll("SELECT estado FROM estado_herramienta ORDER BY id_estado_herramienta") as $e) {
            $out[$e['estado']] = 0;
        }
        $filas = Database::fetchAll("
            SELECT e.estado, COUNT(*) AS total
            FROM insumos i
            INNER JOIN tipo t ON i.id_tipo = t.id_tipo AND t.tipo = '" . self::TIPO_HERRAMIENTA . "'
            INNER JOIN estado_herramienta e ON i.id_estado_herramienta = e.id_estado_herramienta
            WHERE i.activo = TRUE
            GROUP BY e.estado");
        foreach ($filas as $f) {
            $out[$f['estado']] = (int) $f['total'];
        }
        return $out;
    }

    public static function totalHerramientasEnEstado(string $estado): int
    {
        return self::contar("
            SELECT COUNT(*) AS total
            FROM insumos i
            INNER JOIN tipo t ON i.id_tipo = t.id_tipo AND t.tipo = '" . self::TIPO_HERRAMIENTA . "'
            INNER JOIN estado_herramienta e ON i.id_estado_herramienta = e.id_estado_herramienta
            WHERE i.activo = TRUE AND e.estado = :estado", [':estado' => $estado]);
    }

    public static function totalHerramientasPrestadas(): int
    {
        return self::totalHerramientasEnEstado(self::HTA_PRESTADA);
    }

    public static function totalHerramientasEnReparacion(): int
    {
        return self::totalHerramientasEnEstado(self::HTA_REPARACION);
    }

    /**
     * Herramientas prestadas con quién las tiene y desde cuándo (última Entrega activa de cada una).
     * Las de mayor antigüedad primero. Trae id_movimiento_entrega para cruzar con las órdenes de trabajo.
     */
    public static function herramientasPrestadas(): array
    {
        return Database::fetchAll(self::sqlHerramientasPrestadas());
    }

    /** Página $pagina (de $porPagina filas) de herramientasPrestadas(), paginada en la base con LIMIT/OFFSET. */
    public static function herramientasPrestadasPaginado(int $pagina, int $porPagina): array
    {
        return self::paginado(self::sqlHerramientasPrestadas(), [], $pagina, $porPagina);
    }

    private static function sqlHerramientasPrestadas(): string
    {
        return "
            SELECT i.codigo, i.nombre,
                   ult.id_movimiento AS id_movimiento_entrega,
                   ult.fecha AS fecha_entrega,
                   COALESCE(ult.operario, 'Sin operario') AS operario,
                   ult.id_operario,
                   (CURRENT_DATE - ult.fecha) AS dias_prestada
            FROM insumos i
            INNER JOIN tipo t ON i.id_tipo = t.id_tipo AND t.tipo = '" . self::TIPO_HERRAMIENTA . "'
            INNER JOIN estado_herramienta e ON i.id_estado_herramienta = e.id_estado_herramienta
                   AND e.estado = '" . self::HTA_PRESTADA . "'
            LEFT JOIN LATERAL (
                SELECT m.id_movimiento, m.fecha, m.id_operario, o.apellido || ', ' || o.nombre AS operario
                FROM movimiento_detalle md
                INNER JOIN movimiento m ON md.id_movimiento = m.id_movimiento AND m.activo = TRUE
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                        AND tm.tipo = '" . Movimiento::TIPO_ENTREGA . "'
                LEFT JOIN operario o ON m.id_operario = o.id_operario
                WHERE md.id_insumo = i.id_insumo
                ORDER BY m.fecha DESC, m.hora DESC, m.id_movimiento DESC
                LIMIT 1
            ) ult ON TRUE
            WHERE i.activo = TRUE
            ORDER BY dias_prestada DESC NULLS LAST, i.nombre ASC, i.codigo ASC";
    }

    /** Herramientas prestadas agrupadas por operario, con la antigüedad del préstamo más viejo. */
    public static function herramientasPrestadasPorOperario(): array
    {
        $porOperario = [];
        foreach (self::herramientasPrestadas() as $h) {
            $clave = $h['operario'];
            if (!isset($porOperario[$clave])) {
                $porOperario[$clave] = ['operario' => $clave, 'cantidad' => 0, 'max_dias' => null, 'herramientas' => []];
            }
            $porOperario[$clave]['cantidad']++;
            $porOperario[$clave]['herramientas'][] = $h['nombre'];
            if ($h['dias_prestada'] !== null) {
                $d = (int) $h['dias_prestada'];
                $porOperario[$clave]['max_dias'] = max($porOperario[$clave]['max_dias'] ?? 0, $d);
            }
        }
        $lista = array_values($porOperario);
        usort($lista, fn($a, $b) => [$b['cantidad'], $b['max_dias'] ?? -1] <=> [$a['cantidad'], $a['max_dias'] ?? -1]);
        return $lista;
    }

    // ---------------------------------------------------------------------------------------------
    // ÓRDENES DE TRABAJO
    // ---------------------------------------------------------------------------------------------

    /**
     * Órdenes activas por estado: ['Pendiente' => n, ...], de mayor a menor cantidad.
     * Con un período distinto de 'todo' cuenta las órdenes cuya fecha de inicio cae en él.
     */
    public static function ordenesPorEstado(string $rango = 'todo'): array
    {
        $desde = self::RANGOS_ORDENES[self::rangoOrdenesValido($rango)][1];
        $filtro = $desde === null ? '' : " AND fecha_inicio >= {$desde}";
        $out = [];
        $filas = Database::fetchAll("
            SELECT COALESCE(estado, 'Pendiente') AS estado, COUNT(*) AS total
            FROM orden_de_trabajo WHERE activo = TRUE{$filtro} GROUP BY 1 ORDER BY total DESC, 1");
        foreach ($filas as $f) {
            $out[$f['estado']] = (int) $f['total'];
        }
        return $out;
    }

    public static function totalOrdenesEnCurso(): int
    {
        return self::contar("
            SELECT COUNT(*) AS total FROM orden_de_trabajo
            WHERE activo = TRUE AND estado IN (" . self::enLista(self::OT_EN_CURSO) . ")");
    }

    /** Condición SQL de orden vencida: tiene fecha final pasada y todavía no se cerró. */
    private static function condicionOrdenVencida(string $alias = 'o'): string
    {
        return "{$alias}.activo = TRUE AND {$alias}.fecha_final IS NOT NULL AND {$alias}.fecha_final < CURRENT_DATE
                AND COALESCE({$alias}.estado, 'Pendiente') NOT IN (" . self::enLista(self::OT_CERRADAS) . ")";
    }

    public static function totalOrdenesVencidas(): int
    {
        return self::contar("SELECT COUNT(*) AS total FROM orden_de_trabajo o WHERE " . self::condicionOrdenVencida());
    }

    /** Órdenes vencidas (fecha final pasada y sin cerrar), las de mayor atraso primero. */
    public static function ordenesVencidas(?int $limite = null): array
    {
        $sql = self::sqlOrdenesVencidas();
        if ($limite !== null) {
            $sql .= ' LIMIT ' . (int) $limite;
        }
        return Database::fetchAll($sql);
    }

    /** Página $pagina (de $porPagina filas) de ordenesVencidas(), paginada en la base con LIMIT/OFFSET. */
    public static function ordenesVencidasPaginado(int $pagina, int $porPagina): array
    {
        return self::paginado(self::sqlOrdenesVencidas(), [], $pagina, $porPagina);
    }

    private static function sqlOrdenesVencidas(): string
    {
        return "
            SELECT o.id_odt, o.numero_ot, COALESCE(o.estado, 'Pendiente') AS estado, o.fecha_final,
                   (CURRENT_DATE - o.fecha_final) AS dias_atraso,
                   COALESCE(l.localidad, 'Sin localidad') AS localidad,
                   COALESCE(j.jurisdiccion, 'Sin jurisdicción') AS jurisdiccion
            FROM orden_de_trabajo o
            LEFT JOIN localidad l ON o.id_localidad = l.id_localidad
            LEFT JOIN jurisdiccion j ON o.id_jurisdiccion = j.id_jurisdiccion
            WHERE " . self::condicionOrdenVencida() . "
            ORDER BY o.fecha_final ASC, o.id_odt ASC";
    }

    /**
     * Herramientas que siguen prestadas aunque la orden de trabajo que las retiró ya está Finalizada o Anulada.
     * Se cruza el movimiento de egreso de la orden con la última Entrega vigente de la herramienta
     * (si ya se devolvió o se volvió a prestar por otra orden, no aparece).
     */
    public static function herramientasSinDevolverEnOrdenesCerradas(): array
    {
        $prestadas = [];
        foreach (self::herramientasPrestadas() as $h) {
            if ($h['id_movimiento_entrega'] !== null) {
                $prestadas[(int) $h['id_movimiento_entrega']][] = $h;
            }
        }
        if (empty($prestadas)) {
            return [];
        }

        $ordenes = Database::fetchAll("
            SELECT o.id_odt, o.numero_ot, o.estado, o.fecha_final, o.id_mov_egreso
            FROM orden_de_trabajo o
            WHERE o.activo = TRUE AND o.id_mov_egreso IS NOT NULL
              AND o.estado IN (" . self::enLista(self::OT_CERRADAS) . ")
            ORDER BY o.fecha_final DESC NULLS LAST, o.id_odt DESC");

        $out = [];
        foreach ($ordenes as $o) {
            foreach ($prestadas[(int) $o['id_mov_egreso']] ?? [] as $h) {
                $out[] = [
                    'numero_ot' => $o['numero_ot'],
                    'estado' => $o['estado'],
                    'fecha_final' => $o['fecha_final'],
                    'codigo' => $h['codigo'],
                    'herramienta' => $h['nombre'],
                    'operario' => $h['operario'],
                    'dias_prestada' => $h['dias_prestada'],
                ];
            }
        }
        return $out;
    }

    // ---------------------------------------------------------------------------------------------
    // MOVIMIENTOS Y CONSUMO
    // ---------------------------------------------------------------------------------------------

    public static function totalMovimientosMes(): int
    {
        return self::contar("
            SELECT COUNT(*) AS total FROM movimiento
            WHERE activo = TRUE AND DATE_TRUNC('month', fecha) = DATE_TRUNC('month', CURRENT_DATE)");
    }

    public static function totalMovimientosHoy(): int
    {
        return self::contar("SELECT COUNT(*) AS total FROM movimiento WHERE activo = TRUE AND fecha = CURRENT_DATE");
    }

    /**
     * Cantidad de movimientos por mes y tipo en los últimos $meses meses (incluye meses sin actividad).
     * Cada fila: mes (YYYY-MM), etiqueta, ingresos, entregas, devoluciones.
     */
    public static function evolucionMensual(int $meses = 6): array
    {
        $meses = max(1, $meses);
        $filas = Database::fetchAll("
            SELECT TO_CHAR(s.mes, 'YYYY-MM') AS mes,
                   COUNT(m.id_movimiento) FILTER (WHERE tm.tipo = '" . Movimiento::TIPO_INGRESO . "') AS ingresos,
                   COUNT(m.id_movimiento) FILTER (WHERE tm.tipo = '" . Movimiento::TIPO_ENTREGA . "') AS entregas,
                   COUNT(m.id_movimiento) FILTER (WHERE tm.tipo = '" . Movimiento::TIPO_DEVOLUCION . "') AS devoluciones
            FROM GENERATE_SERIES(
                     DATE_TRUNC('month', CURRENT_DATE) - ((" . ($meses - 1) . ") * INTERVAL '1 month'),
                     DATE_TRUNC('month', CURRENT_DATE),
                     INTERVAL '1 month') AS s(mes)
            LEFT JOIN movimiento m ON m.activo = TRUE AND DATE_TRUNC('month', m.fecha) = s.mes
            LEFT JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
            GROUP BY s.mes
            ORDER BY s.mes");
        foreach ($filas as &$f) {
            $f['ingresos'] = (int) $f['ingresos'];
            $f['entregas'] = (int) $f['entregas'];
            $f['devoluciones'] = (int) $f['devoluciones'];
        }
        return $filas;
    }

    /**
     * Movimientos activos por tipo en el rango: cantidad de movimientos y de ítems (líneas de detalle).
     * Incluye todos los tipos del catálogo, aun los que no tuvieron actividad.
     */
    public static function movimientosPorTipo(string $rango = '30d'): array
    {
        $filas = Database::fetchAll("
            SELECT tm.tipo,
                   COUNT(DISTINCT m.id_movimiento) AS movimientos,
                   COUNT(md.id_detalle) AS items
            FROM tipo_movimiento tm
            LEFT JOIN movimiento m ON m.id_tipo_mov = tm.id_tipo_mov AND m.activo = TRUE" . self::condicionRango($rango) . "
            LEFT JOIN movimiento_detalle md ON md.id_movimiento = m.id_movimiento
            GROUP BY tm.id_tipo_mov, tm.tipo
            ORDER BY tm.id_tipo_mov");
        foreach ($filas as &$f) {
            $f['movimientos'] = (int) $f['movimientos'];
            $f['items'] = (int) $f['items'];
        }
        return $filas;
    }

    /** Cantidad de movimientos por día en los últimos $dias días (incluye los días sin actividad). */
    public static function movimientosPorDia(int $dias = 30): array
    {
        $dias = max(1, $dias);
        $filas = Database::fetchAll("
            SELECT s.dia::date AS dia, COUNT(m.id_movimiento) AS movimientos
            FROM GENERATE_SERIES((CURRENT_DATE - " . ($dias - 1) . ")::timestamp, CURRENT_DATE::timestamp, INTERVAL '1 day') AS s(dia)
            LEFT JOIN movimiento m ON m.activo = TRUE AND m.fecha = s.dia::date
            GROUP BY s.dia
            ORDER BY s.dia");
        foreach ($filas as &$f) {
            $f['movimientos'] = (int) $f['movimientos'];
        }
        return $filas;
    }

    /** Últimos movimientos activos, del más reciente al más antiguo. */
    public static function ultimosMovimientos(int $limite = 10): array
    {
        return Database::fetchAll(self::sqlUltimosMovimientos() . ' LIMIT ' . (int) $limite);
    }

    /** Página $pagina (de $porPagina filas) de ultimosMovimientos(), paginada en la base con LIMIT/OFFSET. */
    public static function ultimosMovimientosPaginado(int $pagina, int $porPagina): array
    {
        return self::paginado(self::sqlUltimosMovimientos(), [], $pagina, $porPagina);
    }

    private static function sqlUltimosMovimientos(): string
    {
        return "
            SELECT m.id_movimiento, m.fecha, m.hora, tm.tipo,
                   COALESCE(o.apellido || ', ' || o.nombre, 'Sin operario') AS operario,
                   ot.numero_ot,
                   (SELECT COUNT(*) FROM movimiento_detalle md WHERE md.id_movimiento = m.id_movimiento) AS items
            FROM movimiento m
            INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
            LEFT JOIN operario o ON m.id_operario = o.id_operario
            LEFT JOIN orden_de_trabajo ot ON m.id_odt = ot.id_odt
            WHERE m.activo = TRUE
            ORDER BY m.fecha DESC, m.hora DESC, m.id_movimiento DESC";
    }

    /** Materiales más consumidos (consumo neto) en el rango. */
    public static function topInsumosConsumidos(string $rango = '30d', int $limite = 10): array
    {
        return Database::fetchAll(self::sqlTopInsumosConsumidos($rango) . ' LIMIT ' . (int) $limite);
    }

    /** Página $pagina (de $porPagina filas) de topInsumosConsumidos(), paginada en la base con LIMIT/OFFSET. */
    public static function topInsumosConsumidosPaginado(int $pagina, int $porPagina, string $rango = '30d'): array
    {
        return self::paginado(self::sqlTopInsumosConsumidos($rango), [], $pagina, $porPagina, 'neto');
    }

    private static function sqlTopInsumosConsumidos(string $rango): string
    {
        return "
            SELECT i.codigo, i.nombre, COALESCE(um.unidad_medida, 'Unid.') AS unidad,
                   " . self::sqlEntregado() . " AS entregado,
                   " . self::sqlDevuelto() . " AS devuelto,
                   " . self::sqlNeto() . " AS neto
            " . self::fromConsumo('LEFT JOIN unidad_medida um ON i.id_unidad_medida = um.id_unidad_medida') . self::condicionRango($rango) . "
            GROUP BY i.id_insumo, i.codigo, i.nombre, um.unidad_medida
            HAVING " . self::sqlNeto() . " > 0
            ORDER BY neto DESC, i.nombre ASC, i.codigo ASC";
    }

    /** Consumo de materiales por rubro en el rango (entregado, devuelto y neto). */
    public static function consumoPorRubro(string $rango = '30d'): array
    {
        return Database::fetchAll("
            SELECT COALESCE(r.nombre, 'Sin rubro') AS rubro,
                   COUNT(DISTINCT m.id_movimiento) AS movimientos,
                   " . self::sqlEntregado() . " AS entregado,
                   " . self::sqlDevuelto() . " AS devuelto,
                   " . self::sqlNeto() . " AS neto
            " . self::fromConsumo('LEFT JOIN rubro r ON i.id_rubro = r.id_rubro') . self::condicionRango($rango) . "
            GROUP BY r.nombre
            HAVING " . self::sqlEntregado() . " > 0
            ORDER BY neto DESC, rubro ASC");
    }

    /** Consumo neto de materiales por operario destinatario en el rango. */
    public static function consumoPorOperario(string $rango = '30d', int $limite = 10): array
    {
        return Database::fetchAll(self::sqlConsumoPorOperario($rango) . ' LIMIT ' . (int) $limite);
    }

    /** Página $pagina (de $porPagina filas) de consumoPorOperario(), paginada en la base con LIMIT/OFFSET. */
    public static function consumoPorOperarioPaginado(int $pagina, int $porPagina, string $rango = '30d'): array
    {
        return self::paginado(self::sqlConsumoPorOperario($rango), [], $pagina, $porPagina, 'neto');
    }

    private static function sqlConsumoPorOperario(string $rango): string
    {
        return "
            SELECT COALESCE(o.apellido || ', ' || o.nombre, 'Sin operario') AS operario,
                   COUNT(DISTINCT m.id_movimiento) AS movimientos,
                   " . self::sqlNeto() . " AS neto
            " . self::fromConsumo('LEFT JOIN operario o ON m.id_operario = o.id_operario') . self::condicionRango($rango) . "
            GROUP BY o.id_operario, o.apellido, o.nombre
            HAVING " . self::sqlEntregado() . " > 0
            ORDER BY neto DESC, operario ASC, o.id_operario ASC";
    }

    /** Consumo neto de materiales por orden de trabajo en el rango (solo movimientos asociados a una orden). */
    public static function consumoPorOrden(string $rango = '30d', int $limite = 10): array
    {
        return Database::fetchAll(self::sqlConsumoPorOrden($rango) . ' LIMIT ' . (int) $limite);
    }

    /** Página $pagina (de $porPagina filas) de consumoPorOrden(), paginada en la base con LIMIT/OFFSET. */
    public static function consumoPorOrdenPaginado(int $pagina, int $porPagina, string $rango = '30d'): array
    {
        return self::paginado(self::sqlConsumoPorOrden($rango), [], $pagina, $porPagina, 'neto');
    }

    private static function sqlConsumoPorOrden(string $rango): string
    {
        $f = self::fromConsumo('INNER JOIN orden_de_trabajo ot ON m.id_odt = ot.id_odt');
        return "
            SELECT ot.numero_ot, COALESCE(ot.estado, 'Pendiente') AS estado,
                   COUNT(DISTINCT m.id_movimiento) AS movimientos,
                   " . self::sqlNeto() . " AS neto
            {$f}" . self::condicionRango($rango) . "
            GROUP BY ot.id_odt, ot.numero_ot, ot.estado
            HAVING " . self::sqlEntregado() . " > 0
            ORDER BY neto DESC, ot.numero_ot ASC, ot.id_odt ASC";
    }

    /** Consumo neto de materiales por jurisdicción de la orden de trabajo en el rango. */
    public static function consumoPorJurisdiccion(string $rango = '30d'): array
    {
        $f = self::fromConsumo('LEFT JOIN orden_de_trabajo ot ON m.id_odt = ot.id_odt LEFT JOIN jurisdiccion j ON ot.id_jurisdiccion = j.id_jurisdiccion');
        return Database::fetchAll("
            SELECT CASE WHEN m.id_odt IS NULL THEN 'Sin orden de trabajo'
                        ELSE COALESCE(j.jurisdiccion, 'Sin jurisdicción') END AS jurisdiccion,
                   COUNT(DISTINCT m.id_movimiento) AS movimientos,
                   " . self::sqlNeto() . " AS neto
            {$f}" . self::condicionRango($rango) . "
            GROUP BY 1
            HAVING " . self::sqlEntregado() . " > 0
            ORDER BY neto DESC, 1 ASC");
    }

    /**
     * Insumos con más ajustes de inventario en el rango. Un ajuste fija el stock contado, así que la cantidad
     * de ajustes sobre un mismo insumo indica diferencias recurrentes entre el stock registrado y el real.
     * (La diferencia exacta requiere el libro de stock de la auditoría completa, aún no implementada.)
     */
    public static function ajustesPorInsumo(string $rango = '30d', int $limite = 10): array
    {
        return Database::fetchAll(self::sqlAjustesPorInsumo($rango) . ' LIMIT ' . (int) $limite);
    }

    /** Página $pagina (de $porPagina filas) de ajustesPorInsumo(), paginada en la base con LIMIT/OFFSET. */
    public static function ajustesPorInsumoPaginado(int $pagina, int $porPagina, string $rango = '30d'): array
    {
        return self::paginado(self::sqlAjustesPorInsumo($rango), [], $pagina, $porPagina);
    }

    private static function sqlAjustesPorInsumo(string $rango): string
    {
        return "
            SELECT i.codigo, i.nombre,
                   COUNT(DISTINCT m.id_movimiento) AS ajustes,
                   MAX(m.fecha) AS ultimo_ajuste
            FROM movimiento_detalle md
            INNER JOIN movimiento m ON md.id_movimiento = m.id_movimiento AND m.activo = TRUE
            INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov AND tm.tipo = '" . Movimiento::TIPO_AJUSTE . "'
            INNER JOIN insumos i ON md.id_insumo = i.id_insumo
            WHERE TRUE" . self::condicionRango($rango) . "
            GROUP BY i.id_insumo, i.codigo, i.nombre
            ORDER BY ajustes DESC, ultimo_ajuste DESC, i.codigo ASC";
    }
}
