<?php
// clases/insumo.php

require_once __DIR__ . '/../config/database.php';

class Insumo
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Obtiene el listado completo de insumos con nombres de catálogos relacionados
     */
    public function obtenerTodos(?string $busqueda = null, ?int $tipo_id = null, ?int $rubro_id = null): array
    {
        $sql = "SELECT i.*, 
                       r.nombre AS rubro_nombre, 
                       t.tipo AS tipo_nombre, 
                       u.ubicacion AS ubicacion_nombre, 
                       um.unidad_medida AS unidad_nombre,
                       eh.estado AS estado_herramienta_nombre
                FROM insumos i
                INNER JOIN tipo t ON i.id_tipo = t.id_tipo
                INNER JOIN rubro r ON i.id_rubro = r.id_rubro
                LEFT JOIN ubicacion u ON i.id_ubicacion = u.id_ubicacion
                LEFT JOIN unidad_medida um ON i.id_unidad_medida = um.id_unidad_medida
                LEFT JOIN estado_herramienta eh ON i.id_estado_herramienta = eh.id_estado_herramienta
                WHERE i.activo = TRUE";
        $params = [];

        if (!empty($busqueda)) {
            $sql .= " AND (i.codigo ILIKE :b OR i.nombre ILIKE :b)";
            $params[':b'] = "%{$busqueda}%";
        }
        if (!empty($tipo_id)) {
            $sql .= " AND i.id_tipo = :tipo_id";
            $params[':tipo_id'] = $tipo_id;
        }
        if (!empty($rubro_id)) {
            $sql .= " AND i.id_rubro = :rubro_id";
            $params[':rubro_id'] = $rubro_id;
        }

        $sql .= " ORDER BY i.id_insumo DESC";
        return Database::fetchAll($sql, $params);
    }

    /**
     * Obtiene un insumo puntual por su clave primaria o código
     */
    public function obtenerPorId(int $id): ?array
    {
        $sql = "SELECT i.*, 
                       r.nombre AS rubro_nombre, 
                       t.tipo AS tipo_nombre, 
                       u.ubicacion AS ubicacion_nombre, 
                       um.unidad_medida AS unidad_nombre,
                       eh.estado AS estado_herramienta_nombre
                FROM insumos i
                INNER JOIN tipo t ON i.id_tipo = t.id_tipo
                INNER JOIN rubro r ON i.id_rubro = r.id_rubro
                LEFT JOIN ubicacion u ON i.id_ubicacion = u.id_ubicacion
                LEFT JOIN unidad_medida um ON i.id_unidad_medida = um.id_unidad_medida
                LEFT JOIN estado_herramienta eh ON i.id_estado_herramienta = eh.id_estado_herramienta
                WHERE i.id_insumo = :id";
        return Database::fetch($sql, [':id' => $id]);
    }

    /**
     * Da de alta un nuevo insumo (material o herramienta)
     */
    public function crear(array $datos): bool
    {
        $sql = "INSERT INTO insumos (
                    codigo, nombre, id_tipo, id_rubro, id_ubicacion, 
                    id_unidad_medida, stock_actual, stock_minimo, 
                    es_perecedero, fecha_vencimiento, serie_modelo, 
                    id_estado_herramienta, activo
                ) VALUES (
                    :codigo, :nombre, :id_tipo, :id_rubro, :id_ubicacion, 
                    :id_unidad_medida, :stock_actual, :stock_minimo, 
                    :es_perecedero, :fecha_vencimiento, :serie_modelo, 
                    :id_estado_herramienta, TRUE
                )";

        return Database::execute($sql, [
            ':codigo' => $datos['codigo'],
            ':nombre' => $datos['nombre'],
            ':id_tipo' => (int) $datos['id_tipo'],
            ':id_rubro' => (int) $datos['id_rubro'],
            ':id_ubicacion' => !empty($datos['id_ubicacion']) ? (int) $datos['id_ubicacion'] : null,
            ':id_unidad_medida' => !empty($datos['id_unidad_medida']) ? (int) $datos['id_unidad_medida'] : null,
            ':stock_actual' => (float) ($datos['stock_actual'] ?? 0),
            ':stock_minimo' => (float) ($datos['stock_minimo'] ?? 0),
            ':es_perecedero' => !empty($datos['es_perecedero']) ? 'true' : 'false',
            ':fecha_vencimiento' => !empty($datos['fecha_vencimiento']) ? $datos['fecha_vencimiento'] : null,
            ':serie_modelo' => !empty($datos['serie_modelo']) ? $datos['serie_modelo'] : null,
            ':id_estado_herramienta' => !empty($datos['id_estado_herramienta']) ? (int) $datos['id_estado_herramienta'] : null
        ]);
    }

    /**
     * Modifica los datos de un insumo existente
     */
    public function editar(int $id, array $datos): bool
    {
        $sql = "UPDATE insumos SET 
                    nombre = :nombre, 
                    id_rubro = :id_rubro, 
                    id_ubicacion = :id_ubicacion, 
                    id_unidad_medida = :id_unidad_medida, 
                    stock_actual = :stock_actual, 
                    stock_minimo = :stock_minimo, 
                    es_perecedero = :es_perecedero, 
                    fecha_vencimiento = :fecha_vencimiento, 
                    serie_modelo = :serie_modelo, 
                    id_estado_herramienta = :id_estado_herramienta
                WHERE id_insumo = :id";

        return Database::execute($sql, [
            ':nombre' => $datos['nombre'],
            ':id_rubro' => (int) $datos['id_rubro'],
            ':id_ubicacion' => !empty($datos['id_ubicacion']) ? (int) $datos['id_ubicacion'] : null,
            ':id_unidad_medida' => !empty($datos['id_unidad_medida']) ? (int) $datos['id_unidad_medida'] : null,
            ':stock_actual' => (float) ($datos['stock_actual'] ?? 0),
            ':stock_minimo' => (float) ($datos['stock_minimo'] ?? 0),
            ':es_perecedero' => !empty($datos['es_perecedero']) ? 'true' : 'false',
            ':fecha_vencimiento' => !empty($datos['fecha_vencimiento']) ? $datos['fecha_vencimiento'] : null,
            ':serie_modelo' => !empty($datos['serie_modelo']) ? $datos['serie_modelo'] : null,
            ':id_estado_herramienta' => !empty($datos['id_estado_herramienta']) ? (int) $datos['id_estado_herramienta'] : null,
            ':id' => $id
        ]);
    }

    /**
     * Alterna el estado activo/inactivo (baja lógica)
     */
    public function alternarEstado(int $id): bool
    {
        $sql = "UPDATE insumos SET activo = NOT activo WHERE id_insumo = :id";
        return Database::execute($sql, [':id' => $id]);
    }

    // =========================================================
    // MÉTODOS DE CATÁLOGOS AUXILIARES (Requeridos por dashboard.php)
    // =========================================================

    public function getUbicaciones(): array
    {
        return Database::fetchAll("SELECT id_ubicacion, ubicacion FROM ubicacion ORDER BY ubicacion ASC");
    }

    public function getRubros(): array
    {
        return Database::fetchAll("SELECT id_rubro, nombre FROM rubro WHERE activo = TRUE ORDER BY nombre ASC");
    }

    public function getTipos(): array
    {
        return Database::fetchAll("SELECT id_tipo, tipo FROM tipo ORDER BY tipo ASC");
    }

    public function getUnidadesMedida(): array
    {
        return Database::fetchAll("SELECT id_unidad_medida, unidad_medida FROM unidad_medida ORDER BY unidad_medida ASC");
    }

    public function getEstadosHerramienta(): array
    {
        return Database::fetchAll("SELECT id_estado_herramienta, estado FROM estado_herramienta ORDER BY estado ASC");
    }
    /**
     * Cuenta la cantidad total de insumos según los filtros aplicados
     * Soporta recibir un array asociativo como primer argumento o parámetros sueltos
     */
    public function contarFiltrados($busqueda = null, $tipo = null, $rubro = null): int
    {
        // Si el controlador le pasa un array con todos los filtros juntos
        if (is_array($busqueda)) {
            $filtros = $busqueda;
            $busqueda = $filtros['busqueda'] ?? null;
            $tipo = $filtros['tipo'] ?? null;
            $rubro = $filtros['rubro'] ?? null;
        }

        $sql = "SELECT COUNT(*) FROM insumos i WHERE i.activo = TRUE";
        $params = [];

        if (!empty($busqueda)) {
            $sql .= " AND (i.codigo ILIKE :b OR i.nombre ILIKE :b)";
            $params[':b'] = "%{$busqueda}%";
        }
        if (!empty($tipo)) {
            if (is_numeric($tipo)) {
                $sql .= " AND i.id_tipo = :tipo";
                $params[':tipo'] = (int) $tipo;
            } else {
                $sql .= " AND i.id_tipo = (SELECT id_tipo FROM tipo WHERE tipo = :tipo LIMIT 1)";
                $params[':tipo'] = $tipo;
            }
        }
        if (!empty($rubro)) {
            $sql .= " AND i.id_rubro = :rubro";
            $params[':rubro'] = (int) $rubro;
        }

        $res = Database::fetch($sql, $params);
        return (int) ($res['count'] ?? 0);
    }

    /**
     * Obtiene el listado paginado con LIMIT y OFFSET
     * Soporta recibir un array asociativo de filtros o parámetros sueltos
     */
    public function obtenerFiltrados($busqueda = null, $tipo = null, $rubro = null, int $limite = 15, int $offset = 0): array
    {
        // Si el primer parámetro es un array asociativo de filtros
        if (is_array($busqueda)) {
            $filtros = $busqueda;
            $busqueda = $filtros['busqueda'] ?? null;
            $tipo = $filtros['tipo'] ?? null;
            $rubro = $filtros['rubro'] ?? null;
            $limite = isset($filtros['limite']) ? (int) $filtros['limite'] : $limite;
            $offset = isset($filtros['offset']) ? (int) $filtros['offset'] : $offset;
        }

        $sql = "SELECT i.*, 
                       r.nombre AS rubro_nombre, 
                       t.tipo AS tipo_nombre, 
                       u.ubicacion AS ubicacion_nombre, 
                       um.unidad_medida AS unidad_nombre,
                       eh.estado AS estado_herramienta_nombre
                FROM insumos i
                LEFT JOIN tipo t ON i.id_tipo = t.id_tipo
                LEFT JOIN rubro r ON i.id_rubro = r.id_rubro
                LEFT JOIN ubicacion u ON i.id_ubicacion = u.id_ubicacion
                LEFT JOIN unidad_medida um ON i.id_unidad_medida = um.id_unidad_medida
                LEFT JOIN estado_herramienta eh ON i.id_estado_herramienta = eh.id_estado_herramienta
                WHERE i.activo = TRUE";
        $params = [];

        if (!empty($busqueda)) {
            $sql .= " AND (i.codigo ILIKE :b OR i.nombre ILIKE :b)";
            $params[':b'] = "%{$busqueda}%";
        }
        if (!empty($tipo)) {
            if (is_numeric($tipo)) {
                $sql .= " AND i.id_tipo = :tipo";
                $params[':tipo'] = (int) $tipo;
            } else {
                $sql .= " AND i.id_tipo = (SELECT id_tipo FROM tipo WHERE tipo = :tipo LIMIT 1)";
                $params[':tipo'] = $tipo;
            }
        }
        if (!empty($rubro)) {
            $sql .= " AND i.id_rubro = :rubro";
            $params[':rubro'] = (int) $rubro;
        }

        $sql .= " ORDER BY i.id_insumo DESC LIMIT :limite OFFSET :offset";

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * Alias compatible con dashboard.php línea 81
     */
    public function getFiltrados($busqueda = null, $tipo = null, $rubro = null, int $limite = 15, int $offset = 0): array
    {
        return $this->obtenerFiltrados($busqueda, $tipo, $rubro, $limite, $offset);
    }
    /**
     * Obtiene los insumos que están por debajo o igual al stock mínimo de seguridad
     */
    public function obtenerStockCritico(): array
    {
        $sql = "SELECT i.*, 
                       r.nombre AS rubro_nombre, 
                       u.ubicacion AS ubicacion_nombre, 
                       um.unidad_medida AS unidad_nombre,
                       GREATEST((i.stock_minimo * 2) - i.stock_actual, i.stock_minimo) AS sugerido_comprar
                FROM insumos i
                INNER JOIN tipo t ON i.id_tipo = t.id_tipo
                LEFT JOIN rubro r ON i.id_rubro = r.id_rubro
                LEFT JOIN ubicacion u ON i.id_ubicacion = u.id_ubicacion
                LEFT JOIN unidad_medida um ON i.id_unidad_medida = um.id_unidad_medida
                WHERE t.tipo = 'material' 
                  AND i.activo = TRUE 
                  AND i.stock_actual <= i.stock_minimo
                ORDER BY (i.stock_minimo - i.stock_actual) DESC";
        return Database::fetchAll($sql);
    }
}