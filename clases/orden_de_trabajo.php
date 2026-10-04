<?php
require_once __DIR__ . '/../config/database.php';

class OrdenDeTrabajo {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    // 
    // 1. CONSULTAS (READ)
    // 

    /**
     * Obtiene todas las Órdenes de Trabajo con sus datos relacionados
     * @param bool $incluir_anuladas - Si es true muestra las anuladas
     * @return array Lista de orden de trabajo
     */
    public function getAll($incluir_anuladas = false) {
        $sql = "SELECT o.*, 
                       l.localidad as nombre_localidad,
                       j.jurisdiccion as nombre_jurisdiccion
                FROM orden_de_trabajo o
                LEFT JOIN localidad l ON o.id_localidad = l.id_localidad
                LEFT JOIN jurisdiccion j ON o.id_jurisdiccion = j.id_jurisdiccion";
        
        if (!$incluir_anuladas) {
            $sql .= " WHERE o.activo = TRUE";
        }

        $sql .= " ORDER BY o.fecha_inicio DESC, o.id_odt DESC";

        return $this->db->fetchAll($sql);
    }

    /**
     * Obtiene una Orden de Trabajo específica por su id
     * @param int $id_odt ID de la orden
     * @return array|false Datos de la Orden de trabajo
     */
    public function getById($id_odt) {
        $sql = "SELECT o.*, 
                       l.localidad as nombre_localidad,
                       j.jurisdiccion as nombre_jurisdiccion
                FROM orden_de_trabajo o
                LEFT JOIN localidad l ON o.id_localidad = l.id_localidad
                LEFT JOIN jurisdiccion j ON o.id_jurisdiccion = j.id_jurisdiccion
                WHERE o.id_odt = :id_odt AND o.activo = TRUE";

        return $this->db->fetchOne($sql, ['id_odt' => $id_odt]);
    }

    /**
     * Busca ODTs por número de PA, descripción del trabajo o id
     * @param string $termino Texto a buscar
     * @return array Resultados
     */
    public function buscar($termino) {
        $sql = "SELECT o.*, l.localidad as nombre_localidad
                FROM orden_de_trabajo o
                LEFT JOIN localidad l ON o.id_localidad = l.id_localidad
                WHERE (CAST(o.id_odt AS TEXT) ILIKE :termino 
                   OR o.pa ILIKE :termino 
                   OR o.trabajo_a_realizar ILIKE :termino)
                AND o.activo = TRUE
                ORDER BY o.fecha_inicio DESC";
        
        return $this->db->fetchAll($sql, ['termino' => '%' . $termino . '%']);
    }

    /**
     * Obtiene orden de trab filtradas por su estado
     * @param string $estado Estado a filtrar
     * @return array Lista de ODTs
     */
    public function getByEstado($estado) {
        $sql = "SELECT o.*, l.localidad as nombre_localidad
                FROM orden_de_trabajo o
                LEFT JOIN localidad l ON o.id_localidad = l.id_localidad
                WHERE o.estado = :estado AND o.activo = TRUE
                ORDER BY o.fecha_inicio DESC";
        
        return $this->db->fetchAll($sql, ['estado' => $estado]);
    }

    /**
     * Obtiene las orden de trab asignadas a un operario específico (a traves de la tabla comision)
     * @param string $dni_operario DNI del operario
     * @return array Lista de ODTs
     */
    public function getByOperario($dni_operario) {
        $sql = "SELECT o.*, l.localidad as nombre_localidad
                FROM orden_de_trabajo o
                INNER JOIN comision c ON o.id_odt = c.id_odt
                LEFT JOIN localidad l ON o.id_localidad = l.id_localidad
                WHERE c.id_operario = :dni AND o.activo = TRUE
                ORDER BY o.fecha_inicio DESC";
        
        return $this->db->fetchAll($sql, ['dni' => $dni_operario]);
    }

    /**
     * Obtiene los operarios asignados a una ODT específica (a traves de comision)
     * @param int $id_odt ID de la orden
     * @return array Lista de operarios
     */
    public function getOperariosAsignados($id_odt) {
        $sql = "SELECT o.dni, o.nombre, o.apellido, r.nombre as nombre_rubro
                FROM operario o
                INNER JOIN comision c ON o.dni = c.id_operario
                LEFT JOIN rubro r ON o.id_rubro = r.id_rubro
                WHERE c.id_odt = :id_odt
                ORDER BY o.apellido ASC";
        
        return $this->db->fetchAll($sql, ['id_odt' => $id_odt]);
    }

    // =========================================================
    // 2. CREAR Y ACTUALIZAR (CREATE / UPDATE)
    // =========================================================

    /**
     * Crea una nueva Orden de Trabajo.
     * @param array $data Datos de la ODT
     * @return array El registro insertado con su ID generado
     */
    public function create($data) {
        try {
            // Validaciones básicas
            if (empty(trim($data['trabajo_a_realizar']))) {
                throw new Exception("La descripción del trabajo a realizar es obligatoria.");
            }

            // Limpieza de campos opcionales: si vienen vacíos, los convertimos a NULL
            $data['pa'] = !empty(trim($data['pa'])) ? trim($data['pa']) : null;
            $data['fecha_inicio'] = !empty($data['fecha_inicio']) ? $data['fecha_inicio'] : null;
            $data['fecha_final'] = !empty($data['fecha_final']) ? $data['fecha_final'] : null;
            $data['hora_inicio'] = !empty($data['hora_inicio']) ? $data['hora_inicio'] : null;
            $data['hora_final'] = !empty($data['hora_final']) ? $data['hora_final'] : null;
            $data['observaciones'] = !empty(trim($data['observaciones'])) ? trim($data['observaciones']) : null;
            
            // Estado por defecto si no se envía
            $data['estado'] = !empty(trim($data['estado'])) ? trim($data['estado']) : 'Pendiente';

            // Forzamos que se cree activa
            $data['activo'] = true;

            // Manejo de claves foráneas nulas
            $data['id_localidad'] = !empty($data['id_localidad']) ? (int)$data['id_localidad'] : null;
            $data['id_jurisdiccion'] = !empty($data['id_jurisdiccion']) ? (int)$data['id_jurisdiccion'] : null;

            // Los movimientos se asignan DESPUÉS de crear la ODT, no al crearla
            $data['id_mov_egreso'] = null;
            $data['id_mov_devolucion'] = null;

            return $this->db->insert('orden_de_trabajo', $data);
        } catch (PDOException $e) {
            throw new Exception("Error al crear la Orden de Trabajo: " . $e->getMessage());
        }
    }

    /**
     * Actualiza una Orden de Trabajo existente.
     * @param int $id_odt ID de la orden a actualizar
     * @param array $data Nuevos datos
     * @return bool true si se actualizó correctamente
     */
    public function update($id_odt, $data) {
        try {
            if (empty(trim($data['trabajo_a_realizar']))) {
                throw new Exception("La descripción del trabajo a realizar es obligatoria.");
            }

            // Misma limpieza de campos opcionales
            $data['pa'] = !empty(trim($data['pa'])) ? trim($data['pa']) : null;
            $data['fecha_inicio'] = !empty($data['fecha_inicio']) ? $data['fecha_inicio'] : null;
            $data['fecha_final'] = !empty($data['fecha_final']) ? $data['fecha_final'] : null;
            $data['hora_inicio'] = !empty($data['hora_inicio']) ? $data['hora_inicio'] : null;
            $data['hora_final'] = !empty($data['hora_final']) ? $data['hora_final'] : null;
            $data['observaciones'] = !empty(trim($data['observaciones'])) ? trim($data['observaciones']) : null;

            $data['id_localidad'] = !empty($data['id_localidad']) ? (int)$data['id_localidad'] : null;
            $data['id_jurisdiccion'] = !empty($data['id_jurisdiccion']) ? (int)$data['id_jurisdiccion'] : null;

            // Eliminamos 'activo' del array de update
            unset($data['activo']);

            return $this->db->update('orden_de_trabajo', $data, 'id_odt = :id_odt', ['id_odt' => $id_odt]);
        } catch (PDOException $e) {
            throw new Exception("Error al actualizar la Orden de Trabajo: " . $e->getMessage());
        }
    }

    // =========================================================
    // 3. GESTIÓN DE COMISIONES (ASIGNAR/QUITAR OPERARIOS)
    // =========================================================

    /**
     * Asigna un operario a una Orden de Trabajo (crea una comisión).
     * @param int $id_odt ID de la orden
     * @param string $dni_operario DNI del operario
     * @return bool true si se asignó correctamente
     */
    public function asignarOperario($id_odt, $dni_operario) {
        try {
            // Verificar que el operario no esté ya asignado
            if ($this->operarioYaAsignado($id_odt, $dni_operario)) {
                throw new Exception("Este operario ya está asignado a la orden de trabajo.");
            }

            $comisionData = [
                'id_odt' => $id_odt,
                'id_operario' => $dni_operario
            ];

            return $this->db->insert('comision', $comisionData);
        } catch (PDOException $e) {
            throw new Exception("Error al asignar el operario: " . $e->getMessage());
        }
    }

    /**
     * Quita un operario de una Orden de Trabajo (elimina la comisión).
     * @param int $id_odt ID de la orden
     * @param string $dni_operario DNI del operario
     * @return bool true si se quitó correctamente
     */
    public function quitarOperario($id_odt, $dni_operario) {
        try {
            return $this->db->delete('comision', 'id_odt = :id_odt AND id_operario = :dni', [
                'id_odt' => $id_odt,
                'dni' => $dni_operario
            ]);
        } catch (PDOException $e) {
            throw new Exception("Error al quitar el operario: " . $e->getMessage());
        }
    }

    /**
     * Verifica si un operario ya está asignado a una orden d trab
     * @param int $id_odt ID de la orden
     * @param string $dni_operario DNI del operario
     * @return bool
     */
    private function operarioYaAsignado($id_odt, $dni_operario) {
        $sql = "SELECT COUNT(*) as total FROM comision 
                WHERE id_odt = :id_odt AND id_operario = :dni";
        $result = $this->db->fetchOne($sql, [
            'id_odt' => $id_odt,
            'dni' => $dni_operario
        ]);
        return $result['total'] > 0;
    }

    /**
     * Asigna múltiples operarios a una order de trab de una sola vez
     * @param int $id_odt ID de la orden
     * @param array $dnis_operarios Array de DNIs
     * @return int Cantidad de operarios asignados
     */
    public function asignarMultiplesOperarios($id_odt, $dnis_operarios) {
        $this->db->beginTransaction();
        try {
            $asignados = 0;
            foreach ($dnis_operarios as $dni) {
                if (!$this->operarioYaAsignado($id_odt, $dni)) {
                    $this->asignarOperario($id_odt, $dni);
                    $asignados++;
                }
            }
            $this->db->commit();
            return $asignados;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Error al asignar operarios: " . $e->getMessage());
        }
    }

    // =========================================================
    // 4. VINCULACIÓN CON MOVIMIENTOS
    // =========================================================

    /**
     * Vincula un movimiento de egreso (salida de materiales) a la orden de trab
     * @param int $id_odt ID de la orden
     * @param int $id_mov_egreso ID del movimiento de egreso
     * @return bool
     */
    public function vincularMovimientoEgreso($id_odt, $id_mov_egreso) {
        try {
            $data = ['id_mov_egreso' => $id_mov_egreso];
            return $this->db->update('orden_de_trabajo', $data, 'id_odt = :id_odt', ['id_odt' => $id_odt]);
        } catch (PDOException $e) {
            throw new Exception("Error al vincular el movimiento de egreso: " . $e->getMessage());
        }
    }

    /**
     * Vincula un movimiento de devolución a la orden de trab
     * @param int $id_odt ID de la orden
     * @param int $id_mov_devolucion ID del movimiento de devolución
     * @return bool
     */
    public function vincularMovimientoDevolucion($id_odt, $id_mov_devolucion) {
        try {
            $data = ['id_mov_devolucion' => $id_mov_devolucion];
            return $this->db->update('orden_de_trabajo', $data, 'id_odt = :id_odt', ['id_odt' => $id_odt]);
        } catch (PDOException $e) {
            throw new Exception("Error al vincular el movimiento de devolución: " . $e->getMessage());
        }
    }

    // =========================================================
    // 5. DESHABILITACIÓN Y HABILITACIÓN (SOFT DELETE)
    // =========================================================

    /**
     * Anula una Orden de Trabajo.
     * @param int $id_odt ID de la orden
     * @return bool
     */
    public function anular($id_odt) {
        try {
            $data = ['activo' => false];
            return $this->db->update('orden_de_trabajo', $data, 'id_odt = :id_odt', ['id_odt' => $id_odt]);
        } catch (PDOException $e) {
            throw new Exception("Error al anular la Orden de Trabajo: " . $e->getMessage());
        }
    }

    /**
     * Reactiva una Orden de Trabajo anulada.
     * @param int $id_odt ID de la orden
     * @return bool
     */
    public function reactivar($id_odt) {
        try {
            $data = ['activo' => true];
            return $this->db->update('orden_de_trabajo', $data, 'id_odt = :id_odt', ['id_odt' => $id_odt]);
        } catch (PDOException $e) {
            throw new Exception("Error al reactivar la Orden de Trabajo: " . $e->getMessage());
        }
    }

    // =========================================================
    // 6. VALIDACIONES Y UTILIDADES
    // =========================================================

    /**
     * Verifica si la ODT tiene movimientos asociado
     * @param int $id_odt ID de la orden
     * @return bool
     */
    public function tieneMovimientosAsociados($id_odt) {
        $sql = "SELECT 
                    (CASE WHEN id_mov_egreso IS NOT NULL THEN 1 ELSE 0 END) +
                    (CASE WHEN id_mov_devolucion IS NOT NULL THEN 1 ELSE 0 END) as total
                FROM orden_de_trabajo 
                WHERE id_odt = :id_odt";
        
        $result = $this->db->fetchOne($sql, ['id_odt' => $id_odt]);
        return $result['total'] > 0;
    }

    /**
     * Verifica si la oden de trab tiene operarios asignado
     * @param int $id_odt ID de la orden
     * @return bool
     */
    public function tieneOperariosAsignados($id_odt) {
        $sql = "SELECT COUNT(*) as total FROM comision WHERE id_odt = :id_odt";
        $result = $this->db->fetchOne($sql, ['id_odt' => $id_odt]);
        return $result['total'] > 0;
    }

    /**
     * Obtiene el catálogo de localidade
     */
    public function getLocalidades() {
        return $this->db->fetchAll("SELECT id_localidad, localidad FROM localidad ORDER BY localidad");
    }

    /**
     * Obtiene el catálogo de jurisdiccion
     */
    public function getJurisdicciones() {
        return $this->db->fetchAll("SELECT id_jurisdiccion, jurisdiccion FROM jurisdiccion ORDER BY jurisdiccion");
    }

    /**
     * Obtiene los estados posibles para una orden de Trab
     */
    public function getEstadosPosibles() {
        return [
            'Pendiente' => 'Pendiente',
            'En Progreso' => 'En Progreso',
            'Pausada' => 'Pausada',
            'Finalizada' => 'Finalizada',
            'Anulada' => 'Anulada'
        ];
    }

    /**
     * Obtiene el total de orden de trab activa
     * @return int
     */
    public function getTotal() {
        $sql = "SELECT COUNT(*) as total FROM orden_de_trabajo WHERE activo = TRUE";
        $result = $this->db->fetchOne($sql);
        return (int)$result['total'];
    }

    /**
     * Obtiene el total de orden de trab por estado
     * @return array
     */
    public function getTotalPorEstado() {
        $sql = "SELECT estado, COUNT(*) as total 
                FROM orden_de_trabajo 
                WHERE activo = TRUE 
                GROUP BY estado 
                ORDER BY total DESC";
        return $this->db->fetchAll($sql);
    }

    /**
     * Obtiene los movimientos vinculados a una ODT (egreso y devolución).
     * Devuelve los datos completos de cada movimiento para mostrar en el frontend.
     * @param int $id_odt ID de la orden de trabajo
     * @return array Movimientos vinculados con tipo y fecha
     */
    public function getMovimientosVinculados($id_odt) {
        $sql = "SELECT 
                    'egreso' as rol_en_odt,
                    m.id_movimiento, m.fecha, m.hora, m.observaciones,
                    tm.tipo as nombre_tipo_movimiento
                FROM orden_de_trabajo o
                INNER JOIN movimiento m ON o.id_mov_egreso = m.id_movimiento
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                WHERE o.id_odt = :id_odt AND o.id_mov_egreso IS NOT NULL
                UNION ALL
                SELECT 
                    'devolucion' as rol_en_odt,
                    m.id_movimiento, m.fecha, m.hora, m.observaciones,
                    tm.tipo as nombre_tipo_movimiento
                FROM orden_de_trabajo o
                INNER JOIN movimiento m ON o.id_mov_devolucion = m.id_movimiento
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                WHERE o.id_odt = :id_odt2 AND o.id_mov_devolucion IS NOT NULL
                ORDER BY fecha ASC";

        return $this->db->fetchAll($sql, ['id_odt' => $id_odt, 'id_odt2' => $id_odt]);
    }
}
?>