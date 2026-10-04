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
     * @param string $dni DNI del operario
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
                   OR o.codigo ILIKE :termino)
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
    public function tieneUsuario($dni) {
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
    public function tieneRegistrosHistoricos($dni) {
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
    public function create($data) {
        try {
            // Whitelist de campos permitidos para el INSERT
            // Evita que campos extra del formulario rompan el query
            $camposPermitidos = ['dni', 'apellido', 'nombre', 'codigo', 'id_rubro', 'activo'];
            $data = array_intersect_key($data, array_flip($camposPermitidos));

            // Validar campos obligatorios
            if (empty(trim($data['dni'] ?? '')) || empty(trim($data['apellido'] ?? '')) || empty(trim($data['nombre'] ?? ''))) {
                throw new Exception("DNI, Apellido y Nombre son obligatorios.");
            }

            // Asegurar que el operario se cree como activo
            $data['activo'] = true;

            // Limpiar el código si viene vacío para evitar problemas con UNIQUE
            if (empty(trim($data['codigo'] ?? ''))) {
                $data['codigo'] = null;
            }

            // Manejar id_rubro nulo si no se selecciona ninguno
            $data['id_rubro'] = !empty($data['id_rubro']) ? (int)$data['id_rubro'] : null;

            return $this->db->insert('operario', $data);
        } catch (PDOException $e) {
            // Capturar específicamente el error de clave única (DNI o Código duplicado)
            if ($e->getCode() == '23505') { // Código de violación de unicidad en PostgreSQL
                throw new Exception("El DNI o el Código ingresado ya existen en el sistema.");
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
    public function update($dni_original, $data) {
        try {
            // Whitelist: solo permite actualizar estos campos
            $camposPermitidos = ['apellido', 'nombre', 'codigo', 'id_rubro'];
            $data = array_intersect_key($data, array_flip($camposPermitidos));

            if (empty(trim($data['apellido'] ?? '')) || empty(trim($data['nombre'] ?? ''))) {
                throw new Exception("Apellido y Nombre son obligatorios.");
            }

            if (empty(trim($data['codigo'] ?? ''))) {
                $data['codigo'] = null;
            }

            $data['id_rubro'] = !empty($data['id_rubro']) ? (int)$data['id_rubro'] : null;

            // Nota: No permitimos cambiar el DNI (clave primaria) desde aquí.
            return $this->db->update('operario', $data, 'dni = :dni', ['dni' => $dni_original]);
        } catch (PDOException $e) {
            if ($e->getCode() == '23505') {
                throw new Exception("El Código ingresado ya existe en otro operario.");
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
    public function deshabilitar($dni) {
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
    public function habilitar($dni) {
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
        $sql = "SELECT dni, nombre, apellido, codigo 
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