<?php
require_once __DIR__ . '/../config/database.php';

class Usuario {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    // =========================================================
    // 1. AUTENTICACIÓN
    // =========================================================

    /**
     * Inicia sesión verificando DNI y contraseña.
     * IMPORTANTE: Solo permite login si el usuario está ACTIVO.
     * 
     * @param string $dni - DNI del usuario
     * @param string $contrasena - Contraseña en texto plano
     * @return bool - true si el login es exitoso, false si falla
     */
    public function login($dni, $contrasena) {
        // CAMBIO 1: Agregamos condición de usuario activo
        $sql = "SELECT u.*, r.rol as nombre_rol 
                FROM usuario u
                INNER JOIN rol r ON u.id_rol = r.id_rol
                WHERE u.dni = :dni AND u.activo = TRUE";

        $usuario = $this->db->fetchOne($sql, ['dni' => $dni]);

        if ($usuario && password_verify($contrasena, $usuario['contrasena'])) {
            $_SESSION['dni_usuario'] = $usuario['dni'];
            $_SESSION['id_rol'] = $usuario['id_rol'];
            $_SESSION['nombre_rol'] = $usuario['nombre_rol'];

            if (!empty($usuario['dni_operario'])) {
                // CAMBIO 2: Verificamos que el operario asociado también esté activo
                $operario = $this->db->fetchOne(
                    "SELECT nombre, apellido FROM operario WHERE dni = :dni AND activo = TRUE",
                    ['dni' => $usuario['dni_operario']]
                );
                if ($operario) {
                    $_SESSION['usuario_nombre'] = $operario['nombre'] . ' ' . $operario['apellido'];
                } else {
                    $_SESSION['usuario_nombre'] = $usuario['dni'];
                }
            } else {
                $_SESSION['usuario_nombre'] = $usuario['dni'];
            }

            return true;
        }

        return false;
    }

    public function logout() {
        session_unset();
        session_destroy();
    }

    // =========================================================
    // 2. CONSULTAS (READ)
    // =========================================================

    /**
     * Obtiene todos los usuarios del sistema con su rol y operario asociado.
     * @param bool $incluir_inactivos - Si es true, muestra también los deshabilitados.
     * @return array - Lista de usuarios
     */
    public function getAll($incluir_inactivos = false) {
        $sql = "SELECT u.dni, u.dni_operario, u.id_rol, 
                       r.rol as nombre_rol,
                       o.nombre as operario_nombre, 
                       o.apellido as operario_apellido
                FROM usuario u
                INNER JOIN rol r ON u.id_rol = r.id_rol
                LEFT JOIN operario o ON u.dni_operario = o.dni";
        
        // CAMBIO 3: Filtramos solo usuarios activos por defecto
        if (!$incluir_inactivos) {
            $sql .= " WHERE u.activo = TRUE";
        }

        $sql .= " ORDER BY o.apellido, o.nombre";

        return $this->db->fetchAll($sql);
    }

    /**
     * Obtiene un usuario específico por su DNI.
     * 
     * @param string $dni - DNI del usuario a buscar
     * @return array|false - Datos del usuario o false si no existe
     */
    public function getByDni($dni) {
        $sql = "SELECT u.*, r.rol as nombre_rol,
                       o.nombre as operario_nombre, 
                       o.apellido as operario_apellido
                FROM usuario u
                INNER JOIN rol r ON u.id_rol = r.id_rol
                LEFT JOIN operario o ON u.dni_operario = o.dni
                WHERE u.dni = :dni AND u.activo = TRUE";

        return $this->db->fetchOne($sql, ['dni' => $dni]);
    }

    /**
     * Obtiene el catálogo de roles disponibles.
     * 
     * @return array - Lista de roles
     */
    public function getRoles() {
        return $this->db->fetchAll("SELECT id_rol, rol, descripcion FROM rol ORDER BY id_rol");
    }

    public function getRolById($id_rol) {
        return $this->db->fetchOne("SELECT * FROM rol WHERE id_rol = :id", ['id' => $id_rol]);
    }

    // =========================================================
    // 3. CREACIÓN Y ACTUALIZACIÓN (CREATE / UPDATE)
    // =========================================================

    /**
     * Crea un nuevo usuario en el sistema.
     * 
     * @param array $data - Datos del usuario (dni, dni_operario, id_rol)
     * @param string $contrasena - Contraseña en texto plano
     * @return array - El registro insertado
     */
    public function create($data, $contrasena) {
        try {
            $userData = [
                'dni' => $data['dni'],
                'dni_operario' => !empty($data['dni_operario']) ? $data['dni_operario'] : null,
                'contrasena' => password_hash($contrasena, PASSWORD_DEFAULT),
                'id_rol' => (int)$data['id_rol'],
                'activo' => true // CAMBIO 4: Se crea activo por defecto
            ];

            return $this->db->insert('usuario', $userData);
        } catch (PDOException $e) {
            throw new Exception("Error al crear el usuario: " . $e->getMessage());
        }
    }

    /**
     * Actualiza los datos de un usuario existente.
     * 
     * @param string $dni - DNI del usuario a actualizar
     * @param array $data - Datos a actualizar
     * @return bool - true si se actualizó correctamente
     */
    public function update($dni, $data) {
        try {
            // Whitelist: solo permite actualizar estos campos
            $camposPermitidos = ['dni_operario', 'id_rol', 'password'];
            $data = array_intersect_key($data, array_flip($camposPermitidos));

            if (!empty($data['password'])) {
                $data['contrasena'] = password_hash($data['password'], PASSWORD_DEFAULT);
                unset($data['password']);
            }

            // 'activo' se maneja exclusivamente con deshabilitar() y habilitar()
            // DNI (PK) tampoco se permite modificar

            return $this->db->update('usuario', $data, 'dni = :dni', ['dni' => $dni]);
        } catch (PDOException $e) {
            throw new Exception("Error al actualizar el usuario: " . $e->getMessage());
        }
    }

    public function changePassword($dni, $nueva_contrasena) {
        try {
            $data = ['contrasena' => password_hash($nueva_contrasena, PASSWORD_DEFAULT)];
            return $this->db->update('usuario', $data, 'dni = :dni', ['dni' => $dni]);
        } catch (PDOException $e) {
            throw new Exception("Error al cambiar la contraseña: " . $e->getMessage());
        }
    }

    // =========================================================
    // 4. DESHABILITACIÓN Y HABILITACIÓN (SOFT DELETE)
    // =========================================================

    /**
     * Deshabilita un usuario del sistema.
     * @param string $dni - DNI del usuario a deshabilitar
     * @return bool - true si se deshabilitó correctamente
     */
    public function deshabilitar($dni) {
        try {
            $data = ['activo' => false];
            return $this->db->update('usuario', $data, 'dni = :dni', ['dni' => $dni]);
        } catch (PDOException $e) {
            throw new Exception("Error al deshabilitar el usuario: " . $e->getMessage());
        }
    }

    /**
     * Habilita un usuario que estaba deshabilitado.
     * @param string $dni - DNI del usuario a habilitar
     * @return bool - true si se habilitó correctamente
     */
    public function habilitar($dni) {
        try {
            $data = ['activo' => true];
            return $this->db->update('usuario', $data, 'dni = :dni', ['dni' => $dni]);
        } catch (PDOException $e) {
            throw new Exception("Error al habilitar el usuario: " . $e->getMessage());
        }
    }

    /**
     * Deshabilita el usuario asociado a un operario específico.
     * Útil cuando se da de baja un operario y queremos bloquear su acceso.
     * 
     * @param string $dni_operario - DNI del operario
     * @return bool - true si se deshabilitó, false si no tenía usuario asociado
     */
    public function deshabilitarPorOperario($dni_operario) {
        try {
            $data = ['activo' => false];
            return $this->db->update('usuario', $data, 'dni_operario = :dni', ['dni' => $dni_operario]);
        } catch (PDOException $e) {
            throw new Exception("Error al deshabilitar usuario por operario: " . $e->getMessage());
        }
    }

    /**
     * Habilita el usuario asociado a un operario específico.
     * 
     * @param string $dni_operario - DNI del operario
     * @return bool - true si se habilitó
     */
    public function habilitarPorOperario($dni_operario) {
        try {
            $data = ['activo' => true];
            return $this->db->update('usuario', $data, 'dni_operario = :dni', ['dni' => $dni_operario]);
        } catch (PDOException $e) {
            throw new Exception("Error al habilitar usuario por operario: " . $e->getMessage());
        }
    }

    // =========================================================
    // 5. VERIFICACIÓN DE PERMISOS (ROLES)
    // =========================================================

    public function esAdmin() {
        return isset($_SESSION['nombre_rol']) && $_SESSION['nombre_rol'] === 'administrador';
    }

    public function esPanolero() {
        return isset($_SESSION['nombre_rol']) && 
               in_array($_SESSION['nombre_rol'], ['administrador', 'panolero']);
    }

    public function puedeEditar() {
        return $this->esPanolero();
    }

    public function puedeGestionarUsuarios() {
        return $this->esAdmin();
    }

    public function esVisualizador() {
        return isset($_SESSION['nombre_rol']) && $_SESSION['nombre_rol'] === 'visualizador';
    }

    public function puedeVerReportes() {
        return isset($_SESSION['dni_usuario']);
    }

    /**
     * Busca usuarios por DNI o nombre/apellido del operario asociado.
     * @param string $termino Texto a buscar
     * @return array Resultados
     */
    public function buscar($termino) {
        $sql = "SELECT u.dni, u.dni_operario, u.id_rol, 
                       r.rol as nombre_rol,
                       o.nombre as operario_nombre, 
                       o.apellido as operario_apellido
                FROM usuario u
                INNER JOIN rol r ON u.id_rol = r.id_rol
                LEFT JOIN operario o ON u.dni_operario = o.dni
                WHERE (u.dni ILIKE :termino
                   OR o.nombre ILIKE :termino
                   OR o.apellido ILIKE :termino)
                AND u.activo = TRUE
                ORDER BY o.apellido, o.nombre";

        return $this->db->fetchAll($sql, ['termino' => '%' . $termino . '%']);
    }
}
?>