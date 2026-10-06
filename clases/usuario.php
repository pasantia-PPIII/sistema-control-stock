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

            // El nombre a mostrar sale del propio usuario (nombre y apellido son obligatorios en la tabla)
            $nombreCompleto = trim(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido'] ?? ''));
            $_SESSION['usuario_nombre'] = $nombreCompleto !== '' ? $nombreCompleto : $usuario['dni'];
            $_SESSION['id_operario'] = !empty($usuario['id_operario']) ? (int)$usuario['id_operario'] : null;

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
        $sql = "SELECT u.dni, u.nombre, u.apellido, u.activo, u.id_operario, o.dni as dni_operario, u.id_rol, 
                       r.rol as nombre_rol,
                       o.nombre as operario_nombre, 
                       o.apellido as operario_apellido
                FROM usuario u
                INNER JOIN rol r ON u.id_rol = r.id_rol
                LEFT JOIN operario o ON u.id_operario = o.id_operario";
        
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
    public function getByDni($dni, $incluir_inactivos = false) {
        $sql = "SELECT u.dni, u.nombre, u.apellido, u.id_rol, u.id_operario, u.activo,
                       r.rol as nombre_rol,
                       o.dni as dni_operario,
                       o.nombre as operario_nombre,
                       o.apellido as operario_apellido
                FROM usuario u
                INNER JOIN rol r ON u.id_rol = r.id_rol
                LEFT JOIN operario o ON u.id_operario = o.id_operario
                WHERE u.dni = :dni" . ($incluir_inactivos ? "" : " AND u.activo = TRUE");

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
     * @param array $data - Datos del usuario (dni, nombre, apellido, id_operario, id_rol)
     * @param string $contrasena - Contraseña en texto plano
     * @return array - El registro insertado
     */
    public function create($data, $contrasena) {
        try {
            $userData = [
                'dni' => $data['dni'],
                'nombre' => trim($data['nombre'] ?? ''),
                'apellido' => trim($data['apellido'] ?? ''),
                'id_operario' => !empty($data['id_operario']) ? (int)$data['id_operario'] : null,
                'contrasena' => password_hash($contrasena, PASSWORD_DEFAULT),
                'id_rol' => (int)$data['id_rol'],
                'activo' => true // CAMBIO 4: Se crea activo por defecto
            ];

            return $this->db->insert('usuario', $userData);
        } catch (PDOException $e) {
            throw errorAmigable('Error al crear el usuario', $e);
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
            $camposPermitidos = ['nombre', 'apellido', 'id_operario', 'id_rol', 'password'];
            $data = array_intersect_key($data, array_flip($camposPermitidos));

            if (!empty($data['password'])) {
                $data['contrasena'] = password_hash($data['password'], PASSWORD_DEFAULT);
                unset($data['password']);
            }

            // 'activo' se maneja exclusivamente con deshabilitar() y habilitar()
            // DNI (PK) tampoco se permite modificar

            return $this->db->update('usuario', $data, 'dni = :dni', ['dni' => $dni]);
        } catch (PDOException $e) {
            throw errorAmigable('Error al actualizar el usuario', $e);
        }
    }

    public function changePassword($dni, $nueva_contrasena) {
        try {
            $data = ['contrasena' => password_hash($nueva_contrasena, PASSWORD_DEFAULT)];
            return $this->db->update('usuario', $data, 'dni = :dni', ['dni' => $dni]);
        } catch (PDOException $e) {
            throw errorAmigable('Error al cambiar la contraseña', $e);
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
            throw errorAmigable('Error al deshabilitar el usuario', $e);
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
            throw errorAmigable('Error al habilitar el usuario', $e);
        }
    }

    /**
     * Deshabilita el usuario asociado a un operario específico.
     * Útil cuando se da de baja un operario y queremos bloquear su acceso.
     * 
     * @param int $id_operario - ID del operario
     * @return bool - true si se deshabilitó, false si no tenía usuario asociado
     */
    public function deshabilitarPorOperario($id_operario) {
        try {
            $data = ['activo' => false];
            return $this->db->update('usuario', $data, 'id_operario = :id_operario', ['id_operario' => $id_operario]);
        } catch (PDOException $e) {
            throw errorAmigable('Error al deshabilitar usuario por operario', $e);
        }
    }

    /**
     * Habilita el usuario asociado a un operario específico.
     * 
     * @param int $id_operario - ID del operario
     * @return bool - true si se habilitó
     */
    public function habilitarPorOperario($id_operario) {
        try {
            $data = ['activo' => true];
            return $this->db->update('usuario', $data, 'id_operario = :id_operario', ['id_operario' => $id_operario]);
        } catch (PDOException $e) {
            throw errorAmigable('Error al habilitar usuario por operario', $e);
        }
    }

    // =========================================================
    // 5. VERIFICACIÓN DE PERMISOS (ROLES)
    // =========================================================

    public function esAdmin() {
        return isAdmin();
    }

    public function esPanolero() {
        return isPanolero();
    }

    public function puedeEditar() {
        return $this->esPanolero();
    }

    public function puedeGestionarUsuarios() {
        return $this->esAdmin();
    }

    public function esVisualizador() {
        return isVista();
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
        $sql = "SELECT u.dni, u.nombre, u.apellido, u.activo, u.id_operario, o.dni as dni_operario, u.id_rol, 
                       r.rol as nombre_rol,
                       o.nombre as operario_nombre, 
                       o.apellido as operario_apellido
                FROM usuario u
                INNER JOIN rol r ON u.id_rol = r.id_rol
                LEFT JOIN operario o ON u.id_operario = o.id_operario
                WHERE (u.dni ILIKE :termino
                   OR o.nombre ILIKE :termino
                   OR o.apellido ILIKE :termino)
                AND u.activo = TRUE
                ORDER BY o.apellido, o.nombre";

        return $this->db->fetchAll($sql, ['termino' => '%' . $termino . '%']);
    }
}
?>