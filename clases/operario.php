<?php
require_once __DIR__ . '/../config/database.php';

class Operario {
    private $db;

    // Constructor: inicializa la conexión a la base de datos
    public function __construct() {
        $this->db = new Database();
    }

    // =========================================================
    // 1. CONSULTAS (READ)
    // =========================================================

    /**
     * Obtiene todos los operarios con su rubro asociado
     * @param bool $incluir_inactivos  si es true muestra también los deshabilitado
     * @return array Lista de operarios
     */
    public function getAll($incluir_inactivos = false) {
        $sql = "SELECT o.*, r.nombre as nombre_rubro
                FROM operario o
                LEFT JOIN rubro r ON o.id_rubro = r.id_rubro";
        
        if (!$incluir_inactivos) {
            $sql .= " WHERE o.activo = TRUE";
        }

        $sql .= " ORDER BY o.apellido ASC, o.nombre ASC";

        return $this->db->fetchAll($sql);
    }

    /**
     * Obtiene un operario específico por su DNI
     * @param int $id_operario ID del operario
     * @return array|false Datos del operario
     */
    public function getByDni($dni) {
        $sql = "SELECT o.*, r.nombre as nombre_rubro
                FROM operario o
                LEFT JOIN rubro r ON o.id_rubro = r.id_rubro
                WHERE o.dni = :dni AND o.activo = TRUE";

        return $this->db->fetchOne($sql, ['dni' => $dni]);
    }

    /**
     * Obtiene un operario por su ID, incluyendo inactivos (ficha de detalle).
     * @param int $id_operario ID del operario
     * @return array|null Datos del operario
     */
    public function getById($id_operario) {
        $sql = "SELECT o.*, r.nombre as nombre_rubro
                FROM operario o
                LEFT JOIN rubro r ON o.id_rubro = r.id_rubro
                WHERE o.id_operario = :id";

        return $this->db->fetchOne($sql, ['id' => $id_operario]);
    }

    /**
     * Herramientas en poder del operario: entregado menos devuelto, por herramienta
     * (insumo de tipo Herramienta), según los movimientos Entrega / Devolución.
     * @param int $id_operario ID del operario
     * @return array Herramientas con saldo pendiente de devolución
     */
    public function getHerramientasCustodia($id_operario) {
        $saldo = "SUM(CASE WHEN tm.tipo = 'Entrega' THEN md.cantidad
                           WHEN tm.tipo = 'Devolución' THEN -md.cantidad ELSE 0 END)";
        $sql = "SELECT i.codigo, i.nombre, i.serie_modelo,
                       u.ubicacion,
                       {$saldo} as en_poder,
                       MAX(CASE WHEN tm.tipo = 'Entrega' THEN m.fecha END) as fecha
                FROM movimiento m
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                INNER JOIN movimiento_detalle md ON m.id_movimiento = md.id_movimiento
                INNER JOIN insumos i ON md.id_insumo = i.id_insumo
                INNER JOIN tipo t ON i.id_tipo = t.id_tipo
                LEFT JOIN ubicacion u ON i.id_ubicacion = u.id_ubicacion
                WHERE m.id_operario = :id AND m.activo = TRUE AND t.tipo = 'Herramienta'
                GROUP BY i.id_insumo, i.codigo, i.nombre, i.serie_modelo, u.ubicacion
                HAVING {$saldo} > 0
                ORDER BY i.nombre ASC";

        return $this->db->fetchAll($sql, ['id' => $id_operario]);
    }

    /**
     * Historial de materiales (insumos que no son herramientas) entregados al operario.
     * @param int $id_operario ID del operario
     * @return array Retiros con fecha, insumo, cantidad y orden de trabajo
     */
    public function getHistorialMateriales($id_operario) {
        $sql = "SELECT m.id_movimiento, m.fecha, m.hora, m.observaciones,
                       i.codigo, i.nombre as material_nombre, md.cantidad,
                       um.unidad_medida,
                       odt.numero_ot, odt.pa
                FROM movimiento m
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                INNER JOIN movimiento_detalle md ON m.id_movimiento = md.id_movimiento
                INNER JOIN insumos i ON md.id_insumo = i.id_insumo
                INNER JOIN tipo t ON i.id_tipo = t.id_tipo
                LEFT JOIN unidad_medida um ON i.id_unidad_medida = um.id_unidad_medida
                LEFT JOIN orden_de_trabajo odt ON m.id_odt = odt.id_odt
                WHERE m.id_operario = :id AND m.activo = TRUE
                  AND tm.tipo = 'Entrega' AND t.tipo <> 'Herramienta'
                ORDER BY m.fecha DESC, m.hora DESC, m.id_movimiento DESC";

        return $this->db->fetchAll($sql, ['id' => $id_operario]);
    }

    /**
     * Busca operarios por DNI, nombre, apellido o código interno.
     * @param string $termino Texto a buscar
     * @return array Resultados
     */
    public function buscar($termino) {
        $sql = "SELECT o.*, r.nombre as nombre_rubro
                FROM operario o
                LEFT JOIN rubro r ON o.id_rubro = r.id_rubro
                WHERE (o.dni ILIKE :termino 
                   OR o.nombre ILIKE :termino 
                   OR o.apellido ILIKE :termino 
                   OR o.legajo ILIKE :termino)
                AND o.activo = TRUE
                ORDER BY o.apellido ASC";
        
        return $this->db->fetchAll($sql, ['termino' => '%' . $termino . '%']);
    }

    /**
     * Verifica si el operario tiene un usuario del sistema asociado.
     * Útil para advertir al administrador antes de deshabilitar.
     * @param int $id_operario ID del operario
     * @return bool
     */
    public function tieneUsuario($id_operario) {
        $sql = "SELECT COUNT(*) as total FROM usuario WHERE id_operario = :id";
        $result = $this->db->fetchOne($sql, ['id' => (int)$id_operario]);
        return $result['total'] > 0;
    }

    /**
     * Verifica si el operario tiene registros históricos (movimientos, herramientas, comisiones)
     * Aunque el soft delete no rompe la integridad, es bueno saberlo para auditoría
     * @param int $id_operario ID del operario
     * @return bool
     */
    public function tieneRegistrosHistoricos($id_operario) {
        $sql = "SELECT 
                    (SELECT COUNT(*) FROM movimiento WHERE id_operario = :id1) +
                    (SELECT COUNT(*) FROM herramienta WHERE id_operario = :id2) +
                    (SELECT COUNT(*) FROM comision WHERE id_operario = :id3) as total";
        
        $result = $this->db->fetchOne($sql, ['id1' => (int)$id_operario, 'id2' => (int)$id_operario, 'id3' => (int)$id_operario]);
        return $result['total'] > 0;
    }

    // =========================================================
    // 2. CREAR Y ACTUALIZAR (CREATE / UPDATE)
    // =========================================================

    /**
     * Crea un nuevo operario en la base de datos.
     * @param array $data Datos del operario (dni, apellido, nombre, legajo, id_rubro)
     * @return bool true si se creó correctamente
     */
    public function create($data) {
        try {
            // Whitelist de campos permitidos para el INSERT
            // Evita que campos extra del formulario rompan el query
            $camposPermitidos = ['dni', 'apellido', 'nombre', 'legajo', 'id_rubro', 'activo'];
            $data = array_intersect_key($data, array_flip($camposPermitidos));

            // Validar campos obligatorios
            if (empty(trim($data['dni'] ?? '')) || empty(trim($data['apellido'] ?? '')) || empty(trim($data['nombre'] ?? ''))) {
                throw new Exception("DNI, Apellido y Nombre son obligatorios.");
            }

            // Asegurar que el operario se cree como activo
            $data['activo'] = true;

            // Limpiar el legajo si viene vacío para evitar problemas con UNIQUE
            if (empty(trim($data['legajo'] ?? ''))) {
                $data['legajo'] = null;
            }

            // Manejar id_rubro nulo si no se selecciona ninguno
            $data['id_rubro'] = !empty($data['id_rubro']) ? (int)$data['id_rubro'] : null;

            return $this->db->insert('operario', $data);
        } catch (PDOException $e) {
            // Capturar específicamente el error de clave única (DNI o Legajo duplicado)
            if ($e->getCode() == '23505') { // Código de violación de unicidad en PostgreSQL
                throw new Exception("El DNI o el Legajo ingresado ya existen en el sistema.");
            }
            throw errorAmigable('Error al crear el operario', $e);
        }
    }

    /**
     * Actualiza los datos de un operario existente
     * @param int $id_operario ID del operario
     * @param array $data Nuevos datos
     * @return bool true si se actualizó correctamente
     */
    public function update($id_operario, $data) {
        try {
            // Whitelist: solo permite actualizar estos campos
            $camposPermitidos = ['apellido', 'nombre', 'legajo', 'id_rubro'];
            $data = array_intersect_key($data, array_flip($camposPermitidos));

            if (empty(trim($data['apellido'] ?? '')) || empty(trim($data['nombre'] ?? ''))) {
                throw new Exception("Apellido y Nombre son obligatorios.");
            }

            if (empty(trim($data['legajo'] ?? ''))) {
                $data['legajo'] = null;
            }

            $data['id_rubro'] = !empty($data['id_rubro']) ? (int)$data['id_rubro'] : null;

            // Nota: No se permite cambiar el DNI desde aquí.
            return $this->db->update('operario', $data, 'id_operario = :id', ['id' => (int)$id_operario]);
        } catch (PDOException $e) {
            if ($e->getCode() == '23505') {
                throw new Exception("El Legajo ingresado ya existe en otro operario.");
            }
            throw errorAmigable('Error al actualizar el operario', $e);
        }
    }

    // =========================================================
    // 3. DESHABILITACIÓN Y HABILITACIÓN (SOFT DELETE)
    // =========================================================

    /**
     * Deshabilita (da de baja) a un operario del sistema
     * No borra el registro, preservando la auditoría de movimientos y herramientas
     * @param int $id_operario ID del operario a deshabilitar
     * @return bool true si se deshabilitó correctamente
     */
    public function deshabilitar($id_operario) {
        try {
            $data = ['activo' => false];
            return $this->db->update('operario', $data, 'id_operario = :id', ['id' => (int)$id_operario]);
        } catch (PDOException $e) {
            throw errorAmigable('Error al deshabilitar el operario', $e);
        }
    }

    /**
     * Habilita (reactiva) a un operario que fue deshabilitado.
     * @param int $id_operario ID del operario a habilitar
     * @return bool true si se habilitó correctamente
     */
    public function habilitar($id_operario) {
        try {
            $data = ['activo' => true];
            return $this->db->update('operario', $data, 'id_operario = :id', ['id' => (int)$id_operario]);
        } catch (PDOException $e) {
            throw errorAmigable('Error al habilitar el operario', $e);
        }
    }

    // =========================================================
    // 4. VALIDACIONES Y UTILIDADES
    // =========================================================

    /**
     * Verifica si ya existe un operario con el mismo DNI.
     * @param string $dni DNI a verificar
     * @param string|null $dni_original DNI actual (para excluirlo en la edición, aunque el DNI no suele editarse)
     * @return bool true si ya existe
     */
    public function existeDni($dni, $dni_original = null) {
        $sql = "SELECT COUNT(*) as total FROM operario WHERE dni = :dni";
        $params = ['dni' => $dni];

        if ($dni_original !== null && $dni !== $dni_original) {
            // Esta condición es rara, pero por si acaso se permitiera cambiar el DNI
            $sql .= " AND dni != :dni_original";
            $params['dni_original'] = $dni_original;
        }

        $result = $this->db->fetchOne($sql, $params);
        return $result['total'] > 0;
    }

    /**
     * Obtiene el total de operarios activos en el sistema.
     * @return int Cantidad de operarios
     */
    public function getTotal() {
        $sql = "SELECT COUNT(*) as total FROM operario WHERE activo = TRUE";
        $result = $this->db->fetchOne($sql);
        return (int)$result['total'];
    }

    /**
     * Obtiene todos los operarios (solo activos) para llenar un <select> en formularios.
     * @return array Lista simplificada de operarios
     */
    public function getParaSelect() {
        $sql = "SELECT id_operario, dni, nombre, apellido, legajo 
                FROM operario 
                WHERE activo = TRUE 
                ORDER BY apellido ASC, nombre ASC";
        return $this->db->fetchAll($sql);
    }

    /**
     * Obtiene todos los operarios activos de un rubro específico.
     * Ütil para filtrar en formularios de asignación.
     * @param int $id_rubro ID del rubro
     * @return array Lista de operarios del rubro
     */
    public function getByRubro($id_rubro) {
        $sql = "SELECT o.*, r.nombre as nombre_rubro
                FROM operario o
                LEFT JOIN rubro r ON o.id_rubro = r.id_rubro
                WHERE o.id_rubro = :id_rubro AND o.activo = TRUE
                ORDER BY o.apellido ASC, o.nombre ASC";
        return $this->db->fetchAll($sql, ['id_rubro' => $id_rubro]);
    }
}
?>