<?php
require_once __DIR__ . '/../config/database.php';

class Movimiento
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    // =========================================================
    // 1. CONSULTAS (READ)
    // =========================================================

    /**
     * Obtiene todos los movimientos con sus relaciones legibles.
     * @param bool $incluir_anulados - Si es true, muestra los anulados.
     * @return array Lista de movimientos
     */
    public function getAll($incluir_anulados = false)
    {
        $sql = "SELECT m.id_movimiento, m.fecha, m.hora, m.observaciones, m.activo,
                       m.id_tipo_mov,
                       tm.tipo as nombre_tipo_movimiento,
                       u.id_usuario,
                       u.dni as dni_usuario,
                       u.nombre || ' ' || u.apellido as nombre_usuario,
                       o.id_operario,
                       o.dni as dni_operario,
                       o.apellido || ', ' || o.nombre as nombre_operario,
                       (SELECT COUNT(*) FROM movimiento_detalle md WHERE md.id_movimiento = m.id_movimiento) as total_items
                FROM movimiento m
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                INNER JOIN usuario u ON m.id_usuario = u.id_usuario
                LEFT JOIN operario o ON m.id_operario = o.id_operario";

        if (!$incluir_anulados) {
            $sql .= " WHERE m.activo = TRUE";
        }

        $sql .= " ORDER BY m.fecha DESC, m.hora DESC, m.id_movimiento DESC";

        return $this->db->fetchAll($sql);
    }

    /**
     * Obtiene un movimiento específico por su ID.
     * @param int $id_movimiento ID del movimiento
     * @return array|false Datos del movimiento
     */
    public function getById($id_movimiento)
    {
        $sql = "SELECT m.*, 
                       tm.tipo as nombre_tipo_movimiento,
                       u.dni as dni_usuario,
                       u.nombre || ' ' || u.apellido as nombre_usuario,
                       o.dni as dni_operario,
                       o.apellido || ', ' || o.nombre as nombre_operario,
                       o.legajo as legajo_operario
                FROM movimiento m
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                INNER JOIN usuario u ON m.id_usuario = u.id_usuario
                LEFT JOIN operario o ON m.id_operario = o.id_operario
                WHERE m.id_movimiento = :id_movimiento";

        return $this->db->fetchOne($sql, ['id_movimiento' => $id_movimiento]);
    }

    /**
     * Obtiene el DETALLE (los insumos) de un movimiento específico.
     * @param int $id_movimiento ID del movimiento
     * @return array Lista de insumos y cantidades de ese movimiento
     */
    public function getDetalles($id_movimiento)
    {
        $sql = "SELECT md.id_detalle, md.cantidad, md.id_estado_herramienta,
                       i.id_insumo, i.codigo, i.nombre as nombre_insumo,
                       i.id_tipo,
                       t.tipo as tipo_insumo,
                       um.unidad_medida,
                       eh.estado as nombre_estado_herramienta
                FROM movimiento_detalle md
                INNER JOIN insumos i ON md.id_insumo = i.id_insumo
                LEFT JOIN tipo t ON i.id_tipo = t.id_tipo
                LEFT JOIN unidad_medida um ON i.id_unidad_medida = um.id_unidad_medida
                LEFT JOIN estado_herramienta eh ON md.id_estado_herramienta = eh.id_estado_herramienta
                WHERE md.id_movimiento = :id_movimiento
                ORDER BY i.nombre ASC";

        return $this->db->fetchAll($sql, ['id_movimiento' => $id_movimiento]);
    }

    /**
     * Obtiene movimientos de un operario específico.
     * @param string|int $operario_id_o_dni DNI o ID del operario
     * @return array Lista de movimientos
     */
    public function getByOperario($operario_id_o_dni)
    {
        $sql = "SELECT m.id_movimiento, m.fecha, m.hora, tm.tipo as nombre_tipo_movimiento, m.observaciones
                FROM movimiento m
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                LEFT JOIN operario o ON m.id_operario = o.id_operario
                WHERE (o.dni = :op OR o.id_operario::text = :op) AND m.activo = TRUE
                ORDER BY m.fecha DESC, m.hora DESC";

        return $this->db->fetchAll($sql, ['op' => (string)$operario_id_o_dni]);
    }

    /**
     * Obtiene herramientas actualmente en poder (prestadas) de un operario.
     * @param int $id_operario ID del operario
     * @return array Lista de herramientas prestadas
     */
    public function getHerramientasPrestadasOperario($id_operario)
    {
        $sql = "SELECT DISTINCT i.id_insumo, i.codigo, i.nombre, i.serie_modelo,
                       i.stock_actual,
                       md.cantidad, m.fecha, m.hora, m.id_movimiento
                FROM movimiento m
                INNER JOIN movimiento_detalle md ON m.id_movimiento = md.id_movimiento
                INNER JOIN insumos i ON md.id_insumo = i.id_insumo
                WHERE m.id_operario = :id_operario
                  AND i.id_tipo = 2
                  AND i.id_estado_herramienta = 2
                  AND m.id_tipo_mov = 1
                  AND m.id_movimiento = (
                      SELECT MAX(m2.id_movimiento)
                      FROM movimiento m2
                      INNER JOIN movimiento_detalle md2 ON m2.id_movimiento = md2.id_movimiento
                      WHERE md2.id_insumo = i.id_insumo
                  )
                ORDER BY i.nombre ASC";

        return $this->db->fetchAll($sql, ['id_operario' => (int)$id_operario]);
    }

    /**
     * Cuenta el total de movimientos según filtros aplicados.
     */
    public function contarFiltrados($filtros = [])
    {
        $sql = "SELECT COUNT(DISTINCT m.id_movimiento) as total
                FROM movimiento m
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                INNER JOIN usuario u ON m.id_usuario = u.id_usuario
                LEFT JOIN operario o ON m.id_operario = o.id_operario
                WHERE 1=1";
        $params = [];

        if (!empty($filtros['busqueda'])) {
            $sql .= " AND (
                o.nombre ILIKE :busqueda OR 
                o.apellido ILIKE :busqueda OR 
                o.dni ILIKE :busqueda OR 
                u.nombre ILIKE :busqueda OR 
                u.apellido ILIKE :busqueda OR 
                m.observaciones ILIKE :busqueda OR 
                m.id_movimiento::text ILIKE :busqueda
            )";
            $params['busqueda'] = '%' . $filtros['busqueda'] . '%';
        }

        if (!empty($filtros['id_tipo_mov'])) {
            $sql .= " AND m.id_tipo_mov = :id_tipo_mov";
            $params['id_tipo_mov'] = (int)$filtros['id_tipo_mov'];
        }

        if (isset($filtros['estado']) && $filtros['estado'] === 'anulado') {
            $sql .= " AND m.activo = FALSE";
        } elseif (isset($filtros['estado']) && $filtros['estado'] === 'todos') {
            // Todos los estados
        } else {
            $sql .= " AND m.activo = TRUE";
        }

        $res = $this->db->fetchOne($sql, $params);
        return (int)($res['total'] ?? 0);
    }

    /**
     * Obtiene el listado paginado y filtrado de movimientos.
     */
    public function getFiltrados($filtros = [])
    {
        $sql = "SELECT m.id_movimiento, m.fecha, m.hora, m.observaciones, m.activo,
                       m.id_tipo_mov,
                       tm.tipo as nombre_tipo_movimiento,
                       u.id_usuario,
                       u.dni as dni_usuario,
                       u.nombre || ' ' || u.apellido as nombre_usuario,
                       o.id_operario,
                       o.dni as dni_operario,
                       o.apellido || ', ' || o.nombre as nombre_operario,
                       (SELECT COUNT(*) FROM movimiento_detalle md WHERE md.id_movimiento = m.id_movimiento) as total_items
                FROM movimiento m
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                INNER JOIN usuario u ON m.id_usuario = u.id_usuario
                LEFT JOIN operario o ON m.id_operario = o.id_operario
                WHERE 1=1";
        $params = [];

        if (!empty($filtros['busqueda'])) {
            $sql .= " AND (
                o.nombre ILIKE :busqueda OR 
                o.apellido ILIKE :busqueda OR 
                o.dni ILIKE :busqueda OR 
                u.nombre ILIKE :busqueda OR 
                u.apellido ILIKE :busqueda OR 
                m.observaciones ILIKE :busqueda OR 
                m.id_movimiento::text ILIKE :busqueda
            )";
            $params['busqueda'] = '%' . $filtros['busqueda'] . '%';
        }

        if (!empty($filtros['id_tipo_mov'])) {
            $sql .= " AND m.id_tipo_mov = :id_tipo_mov";
            $params['id_tipo_mov'] = (int)$filtros['id_tipo_mov'];
        }

        if (isset($filtros['estado']) && $filtros['estado'] === 'anulado') {
            $sql .= " AND m.activo = FALSE";
        } elseif (isset($filtros['estado']) && $filtros['estado'] === 'todos') {
            // Todos
        } else {
            $sql .= " AND m.activo = TRUE";
        }

        $sql .= " ORDER BY m.fecha DESC, m.hora DESC, m.id_movimiento DESC";

        if (!empty($filtros['limite'])) {
            $sql .= " LIMIT :limite";
            $params['limite'] = (int)$filtros['limite'];
        }

        if (isset($filtros['offset']) && $filtros['offset'] > 0) {
            $sql .= " OFFSET :offset";
            $params['offset'] = (int)$filtros['offset'];
        }

        return $this->db->fetchAll($sql, $params);
    }

    // =========================================================
    // 2. CREACIÓN (CREATE) - CON TRANSACCIÓN ATÓMICA
    // =========================================================

    /**
     * Crea un movimiento completo (Cabecera + Detalles) y actualiza stock/estados en una sola transacción.
     * 
     * @param array $cabecera - Datos del movimiento (id_tipo_mov, id_operario, id_usuario, fecha, hora, observaciones, id_odt)
     * @param array $detalles - Array de arrays (id_insumo, cantidad, id_estado_herramienta)
     * @return int - El ID del movimiento creado
     */
    public function create($cabecera, $detalles)
    {
        $this->db->beginTransaction();

        try {
            if (empty($detalles) || !is_array($detalles)) {
                throw new Exception("El movimiento debe contener al menos un insumo o herramienta.");
            }

            if (empty($cabecera['id_tipo_mov'])) {
                throw new Exception("Debe especificar el tipo de movimiento.");
            }

            if (empty($cabecera['id_usuario'])) {
                throw new Exception("Debe especificar el usuario que registra el movimiento.");
            }

            // Para entregas y devoluciones, se exige el operario
            $idTipoMov = (int)$cabecera['id_tipo_mov'];
            if (($idTipoMov === 1 || $idTipoMov === 2) && empty($cabecera['id_operario'])) {
                throw new Exception("Debe seleccionar un operario responsable.");
            }

            $datosCabecera = [
                'id_tipo_mov'   => $idTipoMov,
                'id_usuario'    => (int)$cabecera['id_usuario'],
                'id_operario'   => !empty($cabecera['id_operario']) ? (int)$cabecera['id_operario'] : null,
                'id_odt'        => !empty($cabecera['id_odt']) ? (int)$cabecera['id_odt'] : null,
                'fecha'         => !empty($cabecera['fecha']) ? $cabecera['fecha'] : date('Y-m-d'),
                'hora'          => !empty($cabecera['hora']) ? $cabecera['hora'] : date('H:i:s'),
                'observaciones' => !empty(trim($cabecera['observaciones'] ?? '')) ? trim($cabecera['observaciones']) : null,
                'activo'        => true
            ];

            $nuevoMov = $this->db->insert('movimiento', $datosCabecera);
            $id_movimiento = is_array($nuevoMov) ? (int)$nuevoMov['id_movimiento'] : (int)$this->db->lastInsertId('movimiento_id_movimiento_seq');

            if ($id_movimiento <= 0) {
                throw new Exception("No se pudo obtener el identificador del nuevo movimiento.");
            }

            $esEntrega = ($idTipoMov === 1);
            $esDevolucion = ($idTipoMov === 2);

            foreach ($detalles as $detalle) {
                $idInsumo = (int)($detalle['id_insumo'] ?? 0);
                $cantidad = (float)($detalle['cantidad'] ?? 0);

                if ($idInsumo <= 0 || $cantidad <= 0) {
                    throw new Exception("Cada ítem debe tener un insumo válido y una cantidad mayor a cero.");
                }

                // Consultar insumo bloqueando fila para consistencia de concurrencia
                $insumo = $this->db->fetchOne("SELECT * FROM insumos WHERE id_insumo = :id FOR UPDATE", ['id' => $idInsumo]);
                if (!$insumo) {
                    throw new Exception("No se encontró el insumo con ID {$idInsumo}.");
                }

                $esHerramienta = ((int)$insumo['id_tipo'] === 2);
                $idEstadoHerramienta = !empty($detalle['id_estado_herramienta']) ? (int)$detalle['id_estado_herramienta'] : null;

                if ($esEntrega) {
                    if ((float)$insumo['stock_actual'] < $cantidad) {
                        throw new Exception("Stock insuficiente para '{$insumo['nombre']}'. Disponible en pañol: {$insumo['stock_actual']}, Requerido: {$cantidad}.");
                    }
                    $nuevoStock = (float)$insumo['stock_actual'] - $cantidad;

                    if ($esHerramienta) {
                        $idEstadoHerramienta = 2; // Prestada
                        $this->db->update('insumos', [
                            'stock_actual'          => $nuevoStock,
                            'id_estado_herramienta' => 2
                        ], 'id_insumo = :id', ['id' => $idInsumo]);
                    } else {
                        $this->db->update('insumos', [
                            'stock_actual' => $nuevoStock
                        ], 'id_insumo = :id', ['id' => $idInsumo]);
                    }
                } elseif ($esDevolucion) {
                    $nuevoStock = (float)$insumo['stock_actual'] + $cantidad;
                    if ($esHerramienta) {
                        $estadoFinal = $idEstadoHerramienta ?: 1; // Por defecto 1 = Disponible
                        $this->db->update('insumos', [
                            'stock_actual'          => $nuevoStock,
                            'id_estado_herramienta' => $estadoFinal
                        ], 'id_insumo = :id', ['id' => $idInsumo]);
                        $idEstadoHerramienta = $estadoFinal;
                    } else {
                        $this->db->update('insumos', [
                            'stock_actual' => $nuevoStock
                        ], 'id_insumo = :id', ['id' => $idInsumo]);
                    }
                }

                $detalleData = [
                    'id_movimiento'          => $id_movimiento,
                    'id_insumo'              => $idInsumo,
                    'cantidad'               => $cantidad,
                    'id_estado_herramienta'  => $idEstadoHerramienta
                ];

                $this->db->insert('movimiento_detalle', $detalleData);
            }

            $this->db->commit();
            return $id_movimiento;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Error al registrar el movimiento: " . $e->getMessage());
        }
    }

    // =========================================================
    // 3. ACTUALIZACIÓN (UPDATE) - RESTRINGIDA
    // =========================================================

    public function update($id_movimiento, $data)
    {
        try {
            $allowedFields = [];
            if (isset($data['observaciones'])) {
                $allowedFields['observaciones'] = trim($data['observaciones']) ?: null;
            }
            if (isset($data['id_operario'])) {
                $allowedFields['id_operario'] = !empty($data['id_operario']) ? (int)$data['id_operario'] : null;
            }

            if (empty($allowedFields)) {
                throw new Exception("No se proporcionaron datos válidos para actualizar.");
            }

            return $this->db->update('movimiento', $allowedFields, 'id_movimiento = :id', ['id' => $id_movimiento]);
        } catch (PDOException $e) {
            throw new Exception("Error al actualizar el movimiento: " . $e->getMessage());
        }
    }

    // =========================================================
    // 4. ANULACIÓN (SOFT DELETE)
    // =========================================================

    public function anular($id_movimiento)
    {
        try {
            $data = ['activo' => false];
            return $this->db->update('movimiento', $data, 'id_movimiento = :id', ['id' => $id_movimiento]);
        } catch (PDOException $e) {
            throw new Exception("Error al anular el movimiento: " . $e->getMessage());
        }
    }

    // =========================================================
    // 5. UTILIDADES Y CATÁLOGOS
    // =========================================================

    public function getTiposMovimiento()
    {
        return $this->db->fetchAll("SELECT id_tipo_mov, tipo FROM tipo_movimiento ORDER BY id_tipo_mov ASC");
    }

    public function getEstadosHerramienta()
    {
        return $this->db->fetchAll("SELECT id_estado_herramienta, estado FROM estado_herramienta ORDER BY id_estado_herramienta ASC");
    }

    public function getOperariosParaMovimiento()
    {
        $sql = "SELECT o.id_operario, o.dni, o.legajo, o.nombre, o.apellido, r.nombre as nombre_rubro
                FROM operario o
                LEFT JOIN rubro r ON o.id_rubro = r.id_rubro
                WHERE o.activo = TRUE
                ORDER BY o.apellido ASC, o.nombre ASC";
        return $this->db->fetchAll($sql);
    }

    public function getInsumosParaMovimiento($id_tipo = null)
    {
        $sql = "SELECT i.id_insumo, i.codigo, i.nombre, i.stock_actual, i.stock_minimo,
                       i.id_tipo, t.tipo as tipo_nombre,
                       um.unidad_medida,
                       eh.estado as estado_herramienta_nombre,
                       i.id_estado_herramienta,
                       r.nombre as rubro_nombre
                FROM insumos i
                INNER JOIN tipo t ON i.id_tipo = t.id_tipo
                LEFT JOIN unidad_medida um ON i.id_unidad_medida = um.id_unidad_medida
                LEFT JOIN estado_herramienta eh ON i.id_estado_herramienta = eh.id_estado_herramienta
                LEFT JOIN rubro r ON i.id_rubro = r.id_rubro
                WHERE i.activo = TRUE";
        $params = [];

        if ($id_tipo !== null) {
            $sql .= " AND i.id_tipo = :id_tipo";
            $params['id_tipo'] = (int)$id_tipo;
        }

        $sql .= " ORDER BY i.nombre ASC";
        return $this->db->fetchAll($sql, $params);
    }
    /**
     * Obtiene registros de auditoría filtrados por período temporal o rango de fechas
     */
    public function obtenerAuditoriaPeriodica(?string $periodo = 'hoy', ?string $fecha_desde = null, ?string $fecha_hasta = null, ?string $busqueda = null, ?string $accion = null): array {
        $sql = "SELECT m.id_movimiento, m.fecha, m.hora, tm.tipo AS tipo_movimiento,
                       o.nombre AS operario_nombre, o.apellido AS operario_apellido,
                       u.usuario, u.nombre AS usuario_nombre,
                       m.observaciones
                FROM movimiento m
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                LEFT JOIN operario o ON m.id_operario = o.id_operario
                INNER JOIN usuario u ON m.id_usuario = u.id_usuario
                WHERE m.activo = TRUE";
        $params = [];

        // Filtro por botones de período rápido
        if (!empty($periodo) && empty($fecha_desde) && empty($fecha_hasta)) {
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

        // Rango de fechas manual
        if (!empty($fecha_desde)) {
            $sql .= " AND m.fecha >= :fdesde";
            $params[':fdesde'] = $fecha_desde;
        }
        if (!empty($fecha_hasta)) {
            $sql .= " AND m.fecha <= :fhasta";
            $params[':fhasta'] = $fecha_hasta;
        }

        // Búsqueda por texto
        if (!empty($busqueda)) {
            $sql .= " AND (u.usuario ILIKE :b OR o.nombre ILIKE :b OR o.apellido ILIKE :b OR m.observaciones ILIKE :b)";
            $params[':b'] = "%{$busqueda}%";
        }

        $sql .= " ORDER BY m.fecha DESC, m.hora DESC";
        return Database::fetchAll($sql, $params);
    }
}
?>