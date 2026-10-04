<?php
require_once __DIR__ . '/../config/database.php';

class Movimiento {
    private $db;

    public function __construct() {
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
    public function getAll($incluir_anulados = false) {
        $sql = "SELECT m.id_movimiento, m.fecha, m.hora, m.observaciones, m.activo,
                       tm.tipo as nombre_tipo_movimiento,
                       u.dni as dni_usuario,
                       o.apellido || ', ' || o.nombre as nombre_operario
                FROM movimiento m
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                INNER JOIN usuario u ON m.id_usuario = u.dni
                LEFT JOIN operario o ON m.id_operario = o.dni";
        
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
    public function getById($id_movimiento) {
        $sql = "SELECT m.*, tm.tipo as nombre_tipo_movimiento,
                       u.dni as dni_usuario,
                       o.apellido || ', ' || o.nombre as nombre_operario
                FROM movimiento m
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                INNER JOIN usuario u ON m.id_usuario = u.dni
                LEFT JOIN operario o ON m.id_operario = o.dni
                WHERE m.id_movimiento = :id_movimiento";

        return $this->db->fetchOne($sql, ['id_movimiento' => $id_movimiento]);
    }

    /**
     * Obtiene el DETALLE (los insumos) de un movimiento específico.
     * @param int $id_movimiento ID del movimiento
     * @return array Lista de insumos y cantidades de ese movimiento
     */
    public function getDetalles($id_movimiento) {
        $sql = "SELECT md.id, md.cantidad, md.id_estado_herramienta,
                       i.codigo, i.nombre as nombre_insumo,
                       um.unidad_medida,
                       eh.estado as nombre_estado_herramienta
                FROM movimiento_detalle md
                INNER JOIN insumos i ON md.id_insumo = i.codigo
                LEFT JOIN unidad_medida um ON i.id_unidad_medida = um.id_unidad_medida
                LEFT JOIN estado_herramienta eh ON md.id_estado_herramienta = eh.id_estado_herramienta
                WHERE md.id_movimiento = :id_movimiento
                ORDER BY i.nombre ASC";

        return $this->db->fetchAll($sql, ['id_movimiento' => $id_movimiento]);
    }

    /**
     * Obtiene movimientos de un operario específico.
     * @param string $dni_operario DNI del operario
     * @return array Lista de movimientos
     */
    public function getByOperario($dni_operario) {
        $sql = "SELECT m.id_movimiento, m.fecha, m.hora, tm.tipo as nombre_tipo_movimiento, m.observaciones
                FROM movimiento m
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                WHERE m.id_operario = :dni_operario AND m.activo = TRUE
                ORDER BY m.fecha DESC, m.hora DESC";
        
        return $this->db->fetchAll($sql, ['dni_operario' => $dni_operario]);
    }

    // =========================================================
    // 2. CREACIÓN (CREATE) - CON TRANSACCIÓN
    // =========================================================

    /**
     * Crea un movimiento completo (Cabecera + Detalles) en una sola transacción.
     * Esto garantiza que si falla el detalle, no se crea la cabecera huérfana.
     * 
     * @param array $cabecera - Datos del movimiento (id_tipo_mov, id_operario, id_usuario, fecha, hora, observaciones)
     * @param array $detalles - Array de arrays con los insumos (id_insumo, cantidad, id_estado_herramienta)
     * @return int - El ID del movimiento creado
     */
    public function create($cabecera, $detalles) {
        // Iniciamos una transacción de base de datos (ACID)
        $this->db->beginTransaction();

        try {
            // 1. Validar que haya detalles
            if (empty($detalles) || !is_array($detalles)) {
                throw new Exception("El movimiento debe tener al menos un insumo en el detalle.");
            }

            // 2. Preparar datos de la cabecera
            $cabecera['fecha'] = !empty($cabecera['fecha']) ? $cabecera['fecha'] : date('Y-m-d');
            $cabecera['hora'] = !empty($cabecera['hora']) ? $cabecera['hora'] : date('H:i:s');
            $cabecera['observaciones'] = !empty(trim($cabecera['observaciones'])) ? trim($cabecera['observaciones']) : null;
            $cabecera['id_operario'] = !empty($cabecera['id_operario']) ? $cabecera['id_operario'] : null;
            $cabecera['activo'] = true;

            // 3. Insertar la cabecera del movimiento
            $nuevoMovimiento = $this->db->insert('movimiento', $cabecera);
            $id_movimiento = $nuevoMovimiento['id_movimiento']; // Asumimos que tu clase Database devuelve el ID insertado

            // 4. Insertar cada línea del detalle
            foreach ($detalles as $detalle) {
                if (empty($detalle['id_insumo']) || $detalle['cantidad'] <= 0) {
                    throw new Exception("Cada detalle debe tener un insumo válido y una cantidad mayor a cero.");
                }

                $detalleData = [
                    'id_movimiento' => $id_movimiento,
                    'id_insumo' => $detalle['id_insumo'],
                    'cantidad' => (float)$detalle['cantidad'],
                    'id_estado_herramienta' => !empty($detalle['id_estado_herramienta']) ? (int)$detalle['id_estado_herramienta'] : null
                ];

                $this->db->insert('movimiento_detalle', $detalleData);
            }

            // 5. Si todo salió bien, confirmamos la transacción
            $this->db->commit();
            return $id_movimiento;

        } catch (Exception $e) {
            // Si algo falla, deshacemos TODO (ni la cabecera ni los detalles se guardan)
            $this->db->rollBack();
            throw new Exception("Error al crear el movimiento: " . $e->getMessage());
        }
    }

    // =========================================================
    // 3. ACTUALIZACIÓN (UPDATE) - RESTRINGIDA
    // =========================================================

    /**
     * Actualiza SOLO las observaciones o el operario de un movimiento.
     * IMPORTANTE: No se permite cambiar fecha, hora, tipo o detalles para preservar la auditoría.
     * 
     * @param int $id_movimiento ID del movimiento
     * @param array $data Datos a actualizar (solo 'observaciones' o 'id_operario')
     * @return bool
     */
    public function update($id_movimiento, $data) {
        try {
            // Filtramos estrictamente lo que se puede cambiar
            $allowedFields = [];
            if (isset($data['observaciones'])) {
                $allowedFields['observaciones'] = trim($data['observaciones']) ?: null;
            }
            if (isset($data['id_operario'])) {
                $allowedFields['id_operario'] = !empty($data['id_operario']) ? $data['id_operario'] : null;
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

    /**
     * Anula un movimiento (lo marca como inactivo).
     * ADVERTENCIA: Esto NO revierte el stock automáticamente. 
     * Debe haber un proceso o trigger en la base de datos que ajuste el stock, 
     * o el usuario debe crear un movimiento de "Devolución" o "Ajuste".
     * 
     * @param int $id_movimiento ID del movimiento
     * @return bool
     */
    public function anular($id_movimiento) {
        try {
            // Opcional: Verificar si este movimiento está vinculado a una ODT
            // Si lo está, la anulación podría ser más compleja.
            
            $data = ['activo' => false];
            return $this->db->update('movimiento', $data, 'id_movimiento = :id', ['id' => $id_movimiento]);
        } catch (PDOException $e) {
            throw new Exception("Error al anular el movimiento: " . $e->getMessage());
        }
    }

    // =========================================================
    // 5. UTILIDADES
    // =========================================================

    /**
     * Obtiene los tipos de movimiento para el <select> del formulario.
     */
    public function getTiposMovimiento() {
        return $this->db->fetchAll("SELECT id_tipo_mov, tipo FROM tipo_movimiento ORDER BY tipo");
    }

    /**
     * Obtiene los estados de herramienta para el <select> del detalle.
     */
    public function getEstadosHerramienta() {
        return $this->db->fetchAll("SELECT id_estado_herramienta, estado FROM estado_herramienta ORDER BY estado");
    }

    /**
     * Busca movimientos por observaciones, tipo de movimiento o nombre de operario.
     * @param string $termino Texto a buscar
     * @return array Resultados
     */
    public function buscar($termino) {
        $sql = "SELECT m.id_movimiento, m.fecha, m.hora, m.observaciones, m.activo,
                       tm.tipo as nombre_tipo_movimiento,
                       u.dni as dni_usuario,
                       o.apellido || ', ' || o.nombre as nombre_operario
                FROM movimiento m
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                INNER JOIN usuario u ON m.id_usuario = u.dni
                LEFT JOIN operario o ON m.id_operario = o.dni
                WHERE (m.observaciones ILIKE :termino
                   OR tm.tipo ILIKE :termino
                   OR o.apellido ILIKE :termino
                   OR o.nombre ILIKE :termino
                   OR CAST(m.id_movimiento AS TEXT) = :termino_exacto)
                AND m.activo = TRUE
                ORDER BY m.fecha DESC, m.hora DESC";

        return $this->db->fetchAll($sql, [
            'termino'       => '%' . $termino . '%',
            'termino_exacto' => $termino
        ]);
    }

    /**
     * Obtiene movimientos dentro de un rango de fechas.
     * @param string $fecha_desde Fecha inicio (formato Y-m-d)
     * @param string $fecha_hasta Fecha fin (formato Y-m-d)
     * @param bool $incluir_anulados Si es true, incluye los anulados
     * @return array Lista de movimientos en el rango
     */
    public function getByFecha($fecha_desde, $fecha_hasta, $incluir_anulados = false) {
        $sql = "SELECT m.id_movimiento, m.fecha, m.hora, m.observaciones, m.activo,
                       tm.tipo as nombre_tipo_movimiento,
                       u.dni as dni_usuario,
                       o.apellido || ', ' || o.nombre as nombre_operario
                FROM movimiento m
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                INNER JOIN usuario u ON m.id_usuario = u.dni
                LEFT JOIN operario o ON m.id_operario = o.dni
                WHERE m.fecha BETWEEN :fecha_desde AND :fecha_hasta";

        if (!$incluir_anulados) {
            $sql .= " AND m.activo = TRUE";
        }

        $sql .= " ORDER BY m.fecha DESC, m.hora DESC";

        return $this->db->fetchAll($sql, [
            'fecha_desde' => $fecha_desde,
            'fecha_hasta' => $fecha_hasta
        ]);
    }
}
?>