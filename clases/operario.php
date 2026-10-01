<?php
require_once __DIR__ . '/../config/database.php';

class Operario
{
    private $db;

    // Constructor: inicializa la conexión a la base de datos
    public function __construct()
    {
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
    public function getAll($incluir_inactivos = false)
    {
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
     * @param string $dni DNI del operario
     * @return array|false Datos del operario
     */
    public function getByDni($dni)
    {
        $sql = "SELECT o.*, r.nombre as nombre_rubro
                FROM operario o
                LEFT JOIN rubro r ON o.id_rubro = r.id_rubro
                WHERE o.dni = :dni AND o.activo = TRUE";

        return $this->db->fetchOne($sql, ['dni' => $dni]);
    }

    /**
     * Busca operarios por DNI, nombre, apellido o código interno.
     * @param string $termino Texto a buscar
     * @return array Resultados
     */
    public function buscar($termino)
    {
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
     * @param string $dni DNI del operario
     * @return bool
     */
    public function tieneUsuario($dni)
    {
        $sql = "SELECT COUNT(*) as total FROM usuario WHERE dni_operario = :dni";
        $result = $this->db->fetchOne($sql, ['dni' => $dni]);
        return $result['total'] > 0;
    }

    /**
     * Verifica si el operario tiene registros históricos (movimientos, herramientas, comisiones)
     * Aunque el soft delete no rompe la integridad, es bueno saberlo para auditoría
     * @param string $dni DNI del operario
     * @return bool
     */
    public function tieneRegistrosHistoricos($dni)
    {
        $sql = "SELECT 
                    (SELECT COUNT(*) FROM movimiento WHERE id_operario = :dni) +
                    (SELECT COUNT(*) FROM herramienta WHERE id_operario = :dni) +
                    (SELECT COUNT(*) FROM comision WHERE id_operario = :dni) as total";

        $result = $this->db->fetchOne($sql, ['dni' => $dni]);
        return $result['total'] > 0;
    }

    // =========================================================
    // 2. CREAR Y ACTUALIZAR (CREATE / UPDATE)
    // =========================================================

    /**
     * Crea un nuevo operario en la base de datos.
     * @param array $data Datos del operario (dni, apellido, nombre, codigo, id_rubro)
     * @return bool true si se creó correctamente
     */
    public function create($data)
    {
        try {
            // Validar campos obligatorios
            if (empty(trim($data['dni'])) || empty(trim($data['apellido'])) || empty(trim($data['nombre']))) {
                throw new Exception("DNI, Apellido y Nombre son obligatorios.");
            }

            // Asegurar que el operario se cree como activo
            $data['activo'] = true;

            // Mapear código a legajo si se recibe como alias
            if (isset($data['codigo']) && !isset($data['legajo'])) {
                $data['legajo'] = $data['codigo'];
            }
            unset($data['codigo']);

            if (empty(trim($data['legajo'] ?? ''))) {
                $data['legajo'] = null;
            }

            // Manejar id_rubro nulo si no se selecciona ninguno
            $data['id_rubro'] = !empty($data['id_rubro']) ? (int) $data['id_rubro'] : null;

            return $this->db->insert('operario', $data);
        } catch (PDOException $e) {
            // Capturar específicamente el error de clave única (DNI o Legajo duplicado)
            if ($e->getCode() == '23505') { // Código de violación de unicidad en PostgreSQL
                throw new Exception("El DNI o el Legajo ingresado ya existen en el sistema.");
            }
            throw new Exception("Error al crear el operario: " . $e->getMessage());
        }
    }

    /**
     * Actualiza los datos de un operario existente
     * @param string $dni_original DNI actual del operario (clave primaria)
     * @param array $data Nuevos datos
     * @return bool true si se actualizó correctamente
     */
    public function update($dni_original, $data)
    {
        try {
            if (empty(trim($data['apellido'])) || empty(trim($data['nombre']))) {
                throw new Exception("Apellido y Nombre son obligatorios.");
            }

            if (isset($data['codigo']) && !isset($data['legajo'])) {
                $data['legajo'] = $data['codigo'];
            }
            unset($data['codigo']);

            if (isset($data['legajo']) && empty(trim($data['legajo']))) {
                $data['legajo'] = null;
            }

            $data['id_rubro'] = !empty($data['id_rubro']) ? (int) $data['id_rubro'] : null;

            // Nota: No permitimos cambiar el DNI (clave primaria) desde aquí. 
            return $this->db->update('operario', $data, 'dni = :dni', ['dni' => $dni_original]);
        } catch (PDOException $e) {
            if ($e->getCode() == '23505') {
                throw new Exception("El Legajo ingresado ya existe en otro operario.");
            }
            throw new Exception("Error al actualizar el operario: " . $e->getMessage());
        }
    }

    // =========================================================
    // 3. DESHABILITACIÓN Y HABILITACIÓN (SOFT DELETE)
    // =========================================================

    /**
     * Deshabilita (da de baja) a un operario del sistema
     * No borra el registro, preservando la auditoría de movimientos y herramientas
     * @param string $dni DNI del operario a deshabilitar
     * @return bool true si se deshabilitó correctamente
     */
    public function deshabilitar($dni)
    {
        try {
            $data = ['activo' => false];
            return $this->db->update('operario', $data, 'dni = :dni', ['dni' => $dni]);
        } catch (PDOException $e) {
            throw new Exception("Error al deshabilitar el operario: " . $e->getMessage());
        }
    }

    /**
     * Habilita (reactiva) a un operario que fue deshabilitado.
     * @param string $dni DNI del operario a habilitar
     * @return bool true si se habilitó correctamente
     */
    public function habilitar($dni)
    {
        try {
            $data = ['activo' => true];
            return $this->db->update('operario', $data, 'dni = :dni', ['dni' => $dni]);
        } catch (PDOException $e) {
            throw new Exception("Error al habilitar el operario: " . $e->getMessage());
        }
    }

    // =========================================================
    // 4. VALIDACIONES Y UTILIDADES
    // =========================================================

    /**
     * Obtiene un operario por su ID o DNI
     * @param int|string $id ID del operario o DNI
     * @return array|false Datos del operario
     */
    public function getById($id)
    {
        $sql = "SELECT o.*, r.nombre as nombre_rubro
                FROM operario o
                LEFT JOIN rubro r ON o.id_rubro = r.id_rubro
                WHERE (o.id_operario = :id OR o.dni = :dni_str) AND o.activo = TRUE";
        return $this->db->fetchOne($sql, [
            'id' => is_numeric($id) ? (int)$id : 0,
            'dni_str' => (string)$id
        ]);
    }

    /**
     * Verifica si ya existe un operario con el mismo DNI.
     * @param string $dni DNI a verificar
     * @param string|null $dni_original DNI actual (para excluirlo en la edición, aunque el DNI no suele editarse)
     * @return bool true si ya existe
     */
    public function existeDni($dni, $dni_original = null)
    {
        $sql = "SELECT COUNT(*) as total FROM operario WHERE dni = :dni";
        $params = ['dni' => $dni];

        if ($dni_original !== null && $dni !== $dni_original) {
            $sql .= " AND dni != :dni_original";
            $params['dni_original'] = $dni_original;
        }

        $result = $this->db->fetchOne($sql, $params);
        return $result && $result['total'] > 0;
    }

    /**
     * Obtiene el total de operarios activos en el sistema.
     * @return int Cantidad de operarios
     */
    public function getTotal()
    {
        $sql = "SELECT COUNT(*) as total FROM operario WHERE activo = TRUE";
        $result = $this->db->fetchOne($sql);
        return $result ? (int) $result['total'] : 0;
    }

    /**
     * Obtiene todos los operarios (solo activos) para llenar un <select> en formularios.
     * @return array Lista simplificada de operarios
     */
    public function getParaSelect()
    {
        $sql = "SELECT dni, nombre, apellido, legajo 
                FROM operario 
                WHERE activo = TRUE 
                ORDER BY apellido ASC, nombre ASC";
        return $this->db->fetchAll($sql);
    }

    /**
     * Cuenta el total de operarios según filtros aplicados
     * @param string|array|null $busqueda Término de búsqueda o array asociativo de filtros
     * @param int|null $rubro_id ID de rubro
     * @param string|null $estado Estado ('activo', 'inactivo', o vacío)
     * @return int Total de registros
     */
    public function contarFiltrados($busqueda = null, $rubro_id = null, $estado = null): int
    {
        if (is_array($busqueda)) {
            $filtros = $busqueda;
            $busqueda = $filtros['busqueda'] ?? null;
            $rubro_id = $filtros['rubro_id'] ?? null;
            $estado = $filtros['estado'] ?? null;
        }

        $sql = "SELECT COUNT(*) as total FROM operario o WHERE 1=1";
        $params = [];

        if (!empty($busqueda)) {
            $sql .= " AND (o.dni ILIKE :b OR o.nombre ILIKE :b OR o.apellido ILIKE :b OR o.legajo ILIKE :b)";
            $params[':b'] = "%{$busqueda}%";
        }

        if (!empty($rubro_id)) {
            $sql .= " AND o.id_rubro = :rubro_id";
            $params[':rubro_id'] = (int)$rubro_id;
        }

        if ($estado === 'activo') {
            $sql .= " AND o.activo = TRUE";
        } elseif ($estado === 'inactivo') {
            $sql .= " AND o.activo = FALSE";
        } else {
            // Por defecto mostrar activos
            $sql .= " AND o.activo = TRUE";
        }

        $result = $this->db->fetchOne($sql, $params);
        return $result ? (int) $result['total'] : 0;
    }

    /**
     * Obtiene operarios con filtros y paginación LIMIT / OFFSET
     * @param string|array|null $busqueda Término de búsqueda o array de filtros
     * @param int|null $rubro_id ID de rubro
     * @param string|null $estado Estado ('activo', 'inactivo', o null)
     * @param int $limite Límite por página
     * @param int $offset Desplazamiento
     * @return array Lista de operarios
     */
    public function getFiltrados($busqueda = null, $rubro_id = null, $estado = null, int $limite = 15, int $offset = 0): array
    {
        if (is_array($busqueda)) {
            $filtros = $busqueda;
            $busqueda = $filtros['busqueda'] ?? null;
            $rubro_id = $filtros['rubro_id'] ?? null;
            $estado = $filtros['estado'] ?? null;
            $limite = isset($filtros['limite']) ? (int)$filtros['limite'] : $limite;
            $offset = isset($filtros['offset']) ? (int)$filtros['offset'] : $offset;
        }

        $sql = "SELECT o.*, r.nombre AS rubro_nombre,
                       (
                           SELECT COUNT(DISTINCT md.id_insumo)
                           FROM movimiento m
                           INNER JOIN movimiento_detalle md ON m.id_movimiento = md.id_movimiento
                           INNER JOIN insumos i ON md.id_insumo = i.id_insumo
                           INNER JOIN tipo t ON i.id_tipo = t.id_tipo
                           WHERE m.id_operario = o.id_operario
                             AND t.tipo ILIKE '%herramienta%'
                             AND i.id_estado_herramienta = 2
                             AND m.id_tipo_mov = 1
                             AND m.id_movimiento = (
                                 SELECT MAX(m2.id_movimiento)
                                 FROM movimiento m2
                                 INNER JOIN movimiento_detalle md2 ON m2.id_movimiento = md2.id_movimiento
                                 WHERE md2.id_insumo = i.id_insumo
                             )
                       ) AS herramientas_prestadas
                FROM operario o
                LEFT JOIN rubro r ON o.id_rubro = r.id_rubro
                WHERE 1=1";
        $params = [];

        if (!empty($busqueda)) {
            $sql .= " AND (o.dni ILIKE :b OR o.nombre ILIKE :b OR o.apellido ILIKE :b OR o.legajo ILIKE :b)";
            $params[':b'] = "%{$busqueda}%";
        }

        if (!empty($rubro_id)) {
            $sql .= " AND o.id_rubro = :rubro_id";
            $params[':rubro_id'] = (int)$rubro_id;
        }

        if ($estado === 'activo') {
            $sql .= " AND o.activo = TRUE";
        } elseif ($estado === 'inactivo') {
            $sql .= " AND o.activo = FALSE";
        } else {
            $sql .= " AND o.activo = TRUE";
        }

        $sql .= " ORDER BY o.apellido ASC, o.nombre ASC LIMIT :limite OFFSET :offset";

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
     * Alias compatible
     */
    public function obtenerFiltrados($busqueda = null, $rubro_id = null, $estado = null, int $limite = 15, int $offset = 0): array
    {
        return $this->getFiltrados($busqueda, $rubro_id, $estado, $limite, $offset);
    }

    /**
     * Obtiene las herramientas en custodia (prestadas) que tiene un operario en su poder
     * @param int|string $id_o_dni ID del operario o DNI
     * @return array Lista de herramientas prestadas
     */
    public function getHerramientasCustodia($id_o_dni): array
    {
        $operario = $this->getById($id_o_dni);
        if (!$operario) return [];
        $id_operario = (int)$operario['id_operario'];

        $sql = "
            SELECT i.id_insumo, i.codigo, i.nombre, i.serie_modelo, u.ubicacion, 
                   m.fecha, m.hora, odt.numero_ot, odt.pa, m.observaciones
            FROM insumos i
            INNER JOIN movimiento_detalle md ON i.id_insumo = md.id_insumo
            INNER JOIN movimiento m ON md.id_movimiento = m.id_movimiento
            LEFT JOIN ubicacion u ON i.id_ubicacion = u.id_ubicacion
            LEFT JOIN orden_de_trabajo odt ON m.id_odt = odt.id_odt
            INNER JOIN tipo t ON i.id_tipo = t.id_tipo
            WHERE m.id_operario = :id_operario
              AND t.tipo ILIKE '%herramienta%'
              AND i.id_estado_herramienta = 2
              AND m.id_tipo_mov = 1
              AND m.id_movimiento = (
                  SELECT MAX(m2.id_movimiento)
                  FROM movimiento m2
                  INNER JOIN movimiento_detalle md2 ON m2.id_movimiento = md2.id_movimiento
                  WHERE md2.id_insumo = i.id_insumo
              )
            ORDER BY m.fecha DESC, m.hora DESC";

        return Database::fetchAll($sql, [':id_operario' => $id_operario]);
    }

    /**
     * Obtiene el historial de materiales retirados por un operario
     * @param int|string $id_o_dni ID del operario o DNI
     * @return array Historial de materiales
     */
    public function getHistorialMateriales($id_o_dni): array
    {
        $operario = $this->getById($id_o_dni);
        if (!$operario) return [];
        $id_operario = (int)$operario['id_operario'];

        $sql = "
            SELECT m.fecha, m.hora, i.codigo, i.nombre AS material_nombre, 
                   um.unidad_medida, md.cantidad, odt.numero_ot, odt.pa, m.observaciones
            FROM movimiento_detalle md
            INNER JOIN movimiento m ON md.id_movimiento = m.id_movimiento
            INNER JOIN insumos i ON md.id_insumo = i.id_insumo
            LEFT JOIN unidad_medida um ON i.id_unidad_medida = um.id_unidad_medida
            LEFT JOIN orden_de_trabajo odt ON m.id_odt = odt.id_odt
            INNER JOIN tipo t ON i.id_tipo = t.id_tipo
            WHERE m.id_operario = :id_operario
              AND t.tipo ILIKE '%material%'
              AND m.id_tipo_mov = 1
            ORDER BY m.fecha DESC, m.hora DESC";

        return Database::fetchAll($sql, [':id_operario' => $id_operario]);
    }
}
?>