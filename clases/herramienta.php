<?php
require_once __DIR__ . '/../config/database.php';

class Herramienta {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    // =========================================================
    // 1. CONSULTAS (READ)
    // =========================================================

    /**
     * Obtiene todas las herramientas con el operario al que están asignadas
     * @param bool $incluir_inactivas - Si es true, muestra las dadas de baja
     * @return array Lista de herramientas
     */
    public function getAll($incluir_inactivas = false) {
        $sql = "SELECT h.*, 
                       o.apellido || ', ' || o.nombre as nombre_operario
                FROM herramienta h
                LEFT JOIN operario o ON h.id_operario = o.id_operario";
        
        if (!$incluir_inactivas) {
            $sql .= " WHERE h.activo = TRUE";
        }

        $sql .= " ORDER BY h.fecha_entrega DESC, h.id_herramienta DESC";

        return $this->db->fetchAll($sql);
    }

    /**
     * Obtiene una herramienta específica por su id
     * @param int $id_herramienta ID de la herramienta
     * @return array|false Datos de la herramienta
     */
    public function getById($id_herramienta) {
        $sql = "SELECT h.*, 
                       o.apellido || ', ' || o.nombre as nombre_operario,
                       o.dni as dni_operario
                FROM herramienta h
                LEFT JOIN operario o ON h.id_operario = o.id_operario
                WHERE h.id_herramienta = :id AND h.activo = TRUE";

        return $this->db->fetchOne($sql, ['id' => $id_herramienta]);
    }

    /**
     * Obtiene las herramientas actualmente asignadas a un operario
     * Útil para ver qué tiene un operario en su poder
     * @param int $id_operario ID del operario
     * @return array Lista de herramientas
     */
    public function getByOperario($id_operario) {
        $sql = "SELECT h.*, eh.estado as nombre_estado
                FROM herramienta h
                LEFT JOIN estado_herramienta eh ON h.id_estado_herramienta = eh.id_estado_herramienta
                WHERE h.id_operario = :id_operario 
                AND h.fecha_devolucion IS NULL 
                AND h.activo = TRUE
                ORDER BY h.fecha_entrega DESC";
        
        return $this->db->fetchAll($sql, ['id_operario' => (int)$id_operario]);
    }

    /**
     * Obtiene herramientas sin devolver (en poder de operarios)
     * Útil para reportes de auditoría y control
     * @return array Lista de herramientas pendientes de devolución
     */
    public function getSinDevolver() {
        $sql = "SELECT h.*, 
                       o.apellido || ', ' || o.nombre as nombre_operario
                FROM herramienta h
                INNER JOIN operario o ON h.id_operario = o.id_operario
                WHERE h.fecha_devolucion IS NULL 
                AND h.activo = TRUE
                ORDER BY h.fecha_entrega ASC";
        
        return $this->db->fetchAll($sql);
    }

    // =========================================================
    // 2. CREAR Y ACTUALIZAR (CREATE / UPDATE)
    // =========================================================

    /**
     * Registra la entrega de una herramienta a un operario
     * @param array $data Datos (id_operario, estado, observaciones)
     * @return array El registro insertado
     */
    public function registrarEntrega($data) {
        try {
            if (empty($data['id_operario'])) {
                throw new Exception("Debe asignar la herramienta a un operario.");
            }

            $herramientaData = [
                'id_operario' => $data['id_operario'],
                'fecha_entrega' => !empty($data['fecha_entrega']) ? $data['fecha_entrega'] : date('Y-m-d'),
                'fecha_devolucion' => null, // Aún no se ha devuelto
                'estado' => !empty(trim($data['estado'])) ? trim($data['estado']) : 'Bueno',
                'observaciones' => !empty(trim($data['observaciones'])) ? trim($data['observaciones']) : null,
                'activo' => true
            ];

            return $this->db->insert('herramienta', $herramientaData);
        } catch (PDOException $e) {
            throw errorAmigable('Error al registrar la entrega', $e);
        }
    }

    /**
     * Registra la devolución de una herramienta.
     * IMPORTANTE: No se elimina el registro, solo se actualiza la fecha de devolución
     * @param int $id_herramienta ID de la herramienta
     * @param string $estado_final Estado en que se devuelve (Bueno, Regular, Malo)
     * @param string|null $observaciones_dev Observaciones al devolver
     * @return bool
     */
    public function registrarDevolucion($id_herramienta, $estado_final, $observaciones_dev = null) {
        try {
            $data = [
                'fecha_devolucion' => date('Y-m-d'),
                'estado' => $estado_final
            ];
            
            if ($observaciones_dev !== null) {
                $data['observaciones'] = $observaciones_dev;
            }

            return $this->db->update('herramienta', $data, 'id_herramienta = :id', ['id' => $id_herramienta]);
        } catch (PDOException $e) {
            throw errorAmigable('Error al registrar la devolución', $e);
        }
    }

    /**
     * Actualiza observaciones o estado de una herramienta (sin marcar devolución)
     * @param int $id_herramienta ID de la herramienta
     * @param array $data Datos a actualizar
     * @return bool
     */
    public function update($id_herramienta, $data) {
        try {
            // Solo permitimos actualizar campos no críticos
            $allowedFields = [];
            if (isset($data['estado'])) {
                $allowedFields['estado'] = trim($data['estado']);
            }
            if (isset($data['observaciones'])) {
                $allowedFields['observaciones'] = trim($data['observaciones']) ?: null;
            }

            if (empty($allowedFields)) {
                throw new Exception("No se proporcionaron datos válidos para actualizar.");
            }

            return $this->db->update('herramienta', $allowedFields, 'id_herramienta = :id', ['id' => $id_herramienta]);
        } catch (PDOException $e) {
            throw errorAmigable('Error al actualizar la herramienta', $e);
        }
    }

    // =========================================================
    // 3. DESHABILITACIÓN Y HABILITACIÓN (SOFT DELETE)
    // =========================================================

    /**
     * Da de baja una herramienta del inventario (ej: se perdió o se rompió definitivamente)
     * @param int $id_herramienta ID de la herramienta
     * @return bool
     */
    public function deshabilitar($id_herramienta) {
        try {
            $data = ['activo' => false];
            return $this->db->update('herramienta', $data, 'id_herramienta = :id', ['id' => $id_herramienta]);
        } catch (PDOException $e) {
            throw errorAmigable('Error al dar de baja la herramienta', $e);
        }
    }

    public function habilitar($id_herramienta) {
        try {
            $data = ['activo' => true];
            return $this->db->update('herramienta', $data, 'id_herramienta = :id', ['id' => $id_herramienta]);
        } catch (PDOException $e) {
            throw errorAmigable('Error al reactivar la herramienta', $e);
        }
    }

    // =========================================================
    // 4. VALIDACIONES Y UTILIDADES
    // =========================================================

    /**
     * Verifica si la herramienta está actualmente en poder de un operario
     * @param int $id_herramienta ID de la herramienta
     * @return bool
     */
    public function estaAsignada($id_herramienta) {
        $sql = "SELECT COUNT(*) as total FROM herramienta 
                WHERE id_herramienta = :id 
                AND fecha_devolucion IS NULL 
                AND activo = TRUE";
        $result = $this->db->fetchOne($sql, ['id' => $id_herramienta]);
        return $result['total'] > 0;
    }

    /**
     * Obtiene los estados posibles para una herramienta
     * @return array
     */
    public function getEstadosPosibles() {
        return $this->db->fetchAll("SELECT id_estado_herramienta, estado FROM estado_herramienta ORDER BY estado");
    }

    /**
     * Obtiene el total de herramientas en poder de operarios (sin devolver)
     * @return int
     */
    public function getTotalPrestadas() {
        $sql = "SELECT COUNT(*) as total FROM herramienta 
                WHERE fecha_devolucion IS NULL AND activo = TRUE";
        $result = $this->db->fetchOne($sql);
        return (int)$result['total'];
    }

    /**
     * Busca herramientas por nombre del operario, estado u observaciones.
     * @param string $termino Texto a buscar
     * @return array Resultados
     */
    public function buscar($termino) {
        $sql = "SELECT h.*, 
                       o.apellido || ', ' || o.nombre as nombre_operario
                FROM herramienta h
                LEFT JOIN operario o ON h.id_operario = o.id_operario
                WHERE (o.apellido ILIKE :termino
                   OR o.nombre ILIKE :termino
                   OR h.estado ILIKE :termino
                   OR h.observaciones ILIKE :termino)
                AND h.activo = TRUE
                ORDER BY h.fecha_entrega DESC";

        return $this->db->fetchAll($sql, ['termino' => '%' . $termino . '%']);
    }
}
?>