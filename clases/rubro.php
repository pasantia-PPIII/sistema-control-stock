<?php
require_once __DIR__ . '/../config/database.php';

class Rubro {
    private $db;

    // Constructor: crea una instancia de Database para usarla en toda la clase
    public function __construct() {
        $this->db = new Database();
    }

    // =========================================================
    // 1. CONSULTAS (READ)
    // =========================================================

    /**
     * Obtiene todos los rubros del sistema ordenados alfabéticamente.
     * 
     * @param bool $incluir_inactivos - Si es true, incluye también los rubros deshabilitados.
     * @return array - Lista de rubros
     */
    public function getAll($incluir_inactivos = false) {
        $sql = "SELECT r.*, 
                       COUNT(i.codigo) as total_insumos
                FROM rubro r
                LEFT JOIN insumos i ON r.id_rubro = i.id_rubro";
        
        // Por defecto, solo mostramos los rubros activos
        if (!$incluir_inactivos) {
            $sql .= " WHERE r.activo = 1";
        }

        $sql .= " GROUP BY r.id_rubro
                ORDER BY r.nombre ASC";

        return $this->db->fetchAll($sql);
    }

    /**
     * Obtiene un rubro específico por su ID.
     * 
     * @param int $id_rubro - ID del rubro a buscar
     * @return array|false - Datos del rubro o false si no existe
     */
    public function getById($id_rubro) {
        $sql = "SELECT r.*, 
                       COUNT(i.codigo) as total_insumos
                FROM rubro r
                LEFT JOIN insumos i ON r.id_rubro = i.id_rubro
                WHERE r.id_rubro = :id
                GROUP BY r.id_rubro";

        return $this->db->fetchOne($sql, ['id' => $id_rubro]);
    }

    /**
     * Obtiene los insumos asociados a un rubro específico.
     * 
     * @param int $id_rubro - ID del rubro
     * @return array - Lista de insumos del rubro
     */
    public function getInsumosByRubro($id_rubro) {
        $sql = "SELECT i.*, 
                       um.unidad_medida, 
                       t.tipo as tipo_nombre
                FROM insumos i
                INNER JOIN unidad_medida um ON i.id_unidad_medida = um.id_unidad_medida
                INNER JOIN tipo t ON i.id_tipo = t.id_tipo
                WHERE i.id_rubro = :id_rubro
                ORDER BY i.nombre ASC";

        return $this->db->fetchAll($sql, ['id_rubro' => $id_rubro]);
    }

    /**
     * Verifica si un rubro tiene insumos asociados.
     * Aunque al deshabilitar (soft delete) no se rompe la integridad referencial,
     * es útil para advertir al usuario que el rubro está en uso antes de deshabilitarlo.
     * 
     * @param int $id_rubro - ID del rubro
     * @return bool - true si tiene insumos, false si está vacío
     */
    public function tieneInsumos($id_rubro) {
        $sql = "SELECT COUNT(*) as total FROM insumos WHERE id_rubro = :id_rubro";
        $result = $this->db->fetchOne($sql, ['id_rubro' => $id_rubro]);
        return $result['total'] > 0;
    }

    // =========================================================
    // 2. CREACIÓN Y ACTUALIZACIÓN (CREATE / UPDATE)
    // =========================================================

    /**
     * Crea un nuevo rubro en el sistema.
     * 
     * @param array $data - Datos del rubro (nombre, descripcion)
     * @return array - El registro insertado (con su ID generado)
     */
    public function create($data) {
        try {
            if (empty(trim($data['nombre']))) {
                throw new Exception("El nombre del rubro es obligatorio.");
            }

            $rubroData = [
                'nombre' => trim($data['nombre']),
                'descripcion' => !empty($data['descripcion']) ? trim($data['descripcion']) : null,
                'activo' => 1 // Se crea activo por defecto
            ];

            return $this->db->insert('rubro', $rubroData);
        } catch (PDOException $e) {
            throw new Exception("Error al crear el rubro: " . $e->getMessage());
        }
    }

    /**
     * Actualiza los datos de un rubro existente.
     * 
     * @param int $id_rubro - ID del rubro a actualizar
     * @param array $data - Datos a actualizar (nombre, descripcion)
     * @return bool - true si se actualizó correctamente
     */
    public function update($id_rubro, $data) {
        try {
            if (empty(trim($data['nombre']))) {
                throw new Exception("El nombre del rubro es obligatorio.");
            }

            $rubroData = [
                'nombre' => trim($data['nombre']),
                'descripcion' => !empty($data['descripcion']) ? trim($data['descripcion']) : null
            ];

            return $this->db->update('rubro', $rubroData, 'id_rubro = :id', ['id' => $id_rubro]);
        } catch (PDOException $e) {
            throw new Exception("Error al actualizar el rubro: " . $e->getMessage());
        }
    }

    // =========================================================
    // 3. DESHABILITACIÓN Y HABILITACIÓN (SOFT DELETE)
    // =========================================================

    /**
     * Deshabilita (desactiva) un rubro del sistema.
     * IMPORTANTE: No se elimina el registro de la base de datos, 
     * solo se cambia su estado a inactivo para mantener la integridad histórica.
     * 
     * @param int $id_rubro - ID del rubro a deshabilitar
     * @return bool - true si se deshabilitó correctamente
     */
    public function deshabilitar($id_rubro) {
        try {
            $rubroData = ['activo' => 0];
            return $this->db->update('rubro', $rubroData, 'id_rubro = :id', ['id' => $id_rubro]);
        } catch (PDOException $e) {
            throw new Exception("Error al deshabilitar el rubro: " . $e->getMessage());
        }
    }

    /**
     * Habilita (reactiva) un rubro que estaba deshabilitado.
     * 
     * @param int $id_rubro - ID del rubro a habilitar
     * @return bool - true si se habilitó correctamente
     */
    public function habilitar($id_rubro) {
        try {
            $rubroData = ['activo' => 1];
            return $this->db->update('rubro', $rubroData, 'id_rubro = :id', ['id' => $id_rubro]);
        } catch (PDOException $e) {
            throw new Exception("Error al habilitar el rubro: " . $e->getMessage());
        }
    }

    // =========================================================
    // 4. VALIDACIONES Y UTILIDADES
    // =========================================================

    /**
     * Verifica si ya existe un rubro con el mismo nombre.
     * Se usa para evitar duplicados al crear o editar.
     * 
     * @param string $nombre - Nombre del rubro a verificar
     * @param int|null $id_rubro - ID del rubro actual (para excluirlo en la edición)
     * @return bool - true si ya existe, false si no
     */
    public function existeNombre($nombre, $id_rubro = null) {
        $sql = "SELECT COUNT(*) as total FROM rubro WHERE LOWER(nombre) = LOWER(:nombre)";
        $params = ['nombre' => $nombre];

        if ($id_rubro !== null) {
            $sql .= " AND id_rubro != :id";
            $params['id'] = $id_rubro;
        }

        $result = $this->db->fetchOne($sql, $params);
        return $result['total'] > 0;
    }

    /**
     * Obtiene un rubro por su nombre (búsqueda exacta, sin distinguir mayusculas/minusculas)
     * 
     * @param string $nombre - Nombre del rubro
     * @return array|false - Datos del rubro o false si no existe
     */
    public function getByNombre($nombre) {
        $sql = "SELECT * FROM rubro WHERE LOWER(nombre) = LOWER(:nombre)";
        return $this->db->fetchOne($sql, ['nombre' => $nombre]);
    }

    /**
     * Obtiene el total de rubros registrados en el sistema.
     * 
     * @return int - Cantidad de rubros
     */
    public function getTotal() {
        $sql = "SELECT COUNT(*) as total FROM rubro";
        $result = $this->db->fetchOne($sql);
        return (int)$result['total'];
    }

    /**
     * Obtiene el total de insumos agrupados por rubro.
     * Útil para mostrar estadísticas o gráficos.
     * 
     * @return array - Lista de rubros con su cantidad de insumos
     */
    public function getEstadisticas() {
        $sql = "SELECT r.nombre, 
                       COUNT(i.codigo) as total_insumos
                FROM rubro r
                LEFT JOIN insumos i ON r.id_rubro = i.id_rubro
                WHERE r.activo = 1
                GROUP BY r.id_rubro, r.nombre
                ORDER BY total_insumos DESC";

        return $this->db->fetchAll($sql);
    }
}
?>