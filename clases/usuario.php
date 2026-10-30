<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auditoria.php';
require_once __DIR__ . '/validador.php';
require_once __DIR__ . '/foto_perfil.php';

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
            $_SESSION['foto_perfil'] = $usuario['foto_perfil'] ?? null;

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
                       u.foto_perfil,
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

        return array_map([$this, 'conFotoUrl'], $this->db->fetchAll($sql));
    }

    /**
     * Obtiene un usuario específico por su DNI.
     * 
     * @param string $dni - DNI del usuario a buscar
     * @return array|false - Datos del usuario o false si no existe
     */
    public function getByDni($dni, $incluir_inactivos = false) {
        $sql = "SELECT u.dni, u.nombre, u.apellido, u.id_rol, u.id_operario, u.activo, u.foto_perfil,
                       r.rol as nombre_rol,
                       o.dni as dni_operario,
                       o.nombre as operario_nombre,
                       o.apellido as operario_apellido
                FROM usuario u
                INNER JOIN rol r ON u.id_rol = r.id_rol
                LEFT JOIN operario o ON u.id_operario = o.id_operario
                WHERE u.dni = :dni" . ($incluir_inactivos ? "" : " AND u.activo = TRUE");

        $fila = $this->db->fetchOne($sql, ['dni' => $dni]);
        return $fila ? $this->conFotoUrl($fila) : $fila;
    }


    /**
     * Usuarios (activos y deshabilitados) para el autocompletado de los filtros de la Auditoría, paginados en la base.
     * Se incluyen los deshabilitados porque la auditoría conserva lo que hicieron antes de la baja.
     * Busca por DNI, nombre, apellido o "apellido, nombre". Con $termino vacío devuelve todos.
     * @return array Hasta $limite usuarios desde $offset (dni, nombre, apellido, activo)
     */
    public function buscarParaSelect(string $termino = '', int $limite = 10, int $offset = 0): array {
        $sql = "SELECT dni, nombre, apellido, activo FROM usuario";
        $params = [];
        if ($termino !== '') {
            $sql .= " WHERE (dni ILIKE :termino
                        OR nombre ILIKE :termino
                        OR apellido ILIKE :termino
                        OR (apellido || ', ' || nombre) ILIKE :termino)";
            $params['termino'] = '%' . addcslashes($termino, '%_\\') . '%';
        }
        $sql .= " ORDER BY apellido ASC, nombre ASC, dni ASC
                  LIMIT " . max(1, $limite) . " OFFSET " . max(0, $offset);
        return $this->db->fetchAll($sql, $params);
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

    /** Agrega foto_url a la fila: la foto del usuario o la imagen genérica si no cargó ninguna. */
    private function conFotoUrl(array $fila): array {
        $fila['foto_url'] = FotoPerfil::url($fila['foto_perfil'] ?? null);
        return $fila;
    }

    /**
     * Crea un nuevo usuario en el sistema.
     * 
     * @param array $data - Datos del usuario (dni, nombre, apellido, id_operario, id_rol)
     * @param string $contrasena - Contraseña en texto plano
     * @return array - El registro insertado
     */
    public function create($data, $contrasena) {
        try {
            $data['dni'] = Validador::dni($data['dni'] ?? '', 'DNI del usuario');
            Validador::password($contrasena);

            $existente = $this->getByDni($data['dni'], true);
            if ($existente) {
                throw new Exception($existente['activo']
                    ? "Ya existe un usuario con el DNI {$data['dni']}."
                    : "Ya existe un usuario con el DNI {$data['dni']}, pero está deshabilitado: habilítelo en lugar de crearlo de nuevo.");
            }

            $userData = [
                'dni' => $data['dni'],
                'nombre' => trim($data['nombre'] ?? ''),
                'apellido' => trim($data['apellido'] ?? ''),
                'id_operario' => !empty($data['id_operario']) ? (int)$data['id_operario'] : null,
                'contrasena' => password_hash($contrasena, PASSWORD_DEFAULT),
                'id_rol' => (int)$data['id_rol'],
                'foto_perfil' => !empty($data['foto_perfil']) ? $data['foto_perfil'] : null,
                'activo' => true // CAMBIO 4: Se crea activo por defecto
            ];

            // AUDITORÍA: la carga y su registro se guardan juntos (la clase Auditoria descarta la contraseña sola)
            return Auditoria::transaccion(function () use ($userData) {
                $usuario = $this->db->insert('usuario', $userData);
                Auditoria::registrarCambio(Auditoria::CREAR, 'usuario', $userData['dni'],
                    "Usuario {$userData['dni']} '{$userData['nombre']} {$userData['apellido']}' cargado", null, $usuario);
                return $usuario;
            });
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
            $camposPermitidos = ['nombre', 'apellido', 'id_operario', 'id_rol', 'password', 'foto_perfil'];
            $data = array_intersect_key($data, array_flip($camposPermitidos));

            if (!empty($data['password'])) {
                Validador::password($data['password']);
                $data['contrasena'] = password_hash($data['password'], PASSWORD_DEFAULT);
                unset($data['password']);
            }

            // 'activo' se maneja exclusivamente con deshabilitar() y habilitar()
            // DNI (PK) tampoco se permite modificar

            // AUDITORÍA: el cambio y su registro se guardan juntos. Importa sobre todo el cambio de rol (id_rol).
            return Auditoria::transaccion(function () use ($dni, $data) {
                $antes = $this->db->fetchOne(
                    "SELECT nombre, apellido, id_operario, id_rol, foto_perfil, contrasena FROM usuario WHERE dni = :dni FOR UPDATE",
                    ['dni' => $dni]
                );
                $ok = $this->db->update('usuario', $data, 'dni = :dni', ['dni' => $dni]);
                [$a, $d] = Auditoria::diferencias($antes ?? [], $data);
                if ($d) {
                    Auditoria::registrarCambio(Auditoria::EDITAR, 'usuario', $dni,
                        "Usuario {$dni} editado: " . implode(', ', array_keys($d)), $a, $d);
                }
                if (isset($data['contrasena'])) {
                    Auditoria::registrarCambio(Auditoria::CAMBIAR_CONTRASENA, 'usuario', $dni,
                        "Contraseña del usuario {$dni} modificada");
                }
                return $ok;
            });
        } catch (PDOException $e) {
            throw errorAmigable('Error al actualizar el usuario', $e);
        }
    }

    public function changePassword($dni, $nueva_contrasena) {
        try {
            Validador::password($nueva_contrasena);
            $data = ['contrasena' => password_hash($nueva_contrasena, PASSWORD_DEFAULT)];
            return Auditoria::transaccion(function () use ($dni, $data) {
                $ok = $this->db->update('usuario', $data, 'dni = :dni', ['dni' => $dni]);
                Auditoria::registrarCambio(Auditoria::CAMBIAR_CONTRASENA, 'usuario', $dni,
                    "Contraseña del usuario {$dni} modificada");
                return $ok;
            });
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
            return Auditoria::transaccion(function () use ($dni) {
                $ok = $this->db->update('usuario', ['activo' => false], 'dni = :dni', ['dni' => $dni]);
                Auditoria::registrarCambio(Auditoria::DESHABILITAR, 'usuario', $dni,
                    "Usuario {$dni} deshabilitado", ['activo' => true], ['activo' => false]);
                return $ok;
            });
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
            return Auditoria::transaccion(function () use ($dni) {
                $ok = $this->db->update('usuario', ['activo' => true], 'dni = :dni', ['dni' => $dni]);
                Auditoria::registrarCambio(Auditoria::HABILITAR, 'usuario', $dni,
                    "Usuario {$dni} habilitado", ['activo' => false], ['activo' => true]);
                return $ok;
            });
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
            return Auditoria::transaccion(function () use ($id_operario) {
                // AUDITORÍA: una entrada por cada usuario que estaba activo y se da de baja
                $afectados = $this->db->fetchAll(
                    "SELECT dni FROM usuario WHERE id_operario = :id_operario AND activo = TRUE FOR UPDATE",
                    ['id_operario' => $id_operario]
                );
                $ok = $this->db->update('usuario', ['activo' => false], 'id_operario = :id_operario', ['id_operario' => $id_operario]);
                foreach ($afectados as $u) {
                    Auditoria::registrarCambio(Auditoria::DESHABILITAR, 'usuario', $u['dni'],
                        "Usuario {$u['dni']} deshabilitado por baja del operario #{$id_operario}", ['activo' => true], ['activo' => false]);
                }
                return $ok;
            });
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
            return Auditoria::transaccion(function () use ($id_operario) {
                $afectados = $this->db->fetchAll(
                    "SELECT dni FROM usuario WHERE id_operario = :id_operario AND activo = FALSE FOR UPDATE",
                    ['id_operario' => $id_operario]
                );
                $ok = $this->db->update('usuario', ['activo' => true], 'id_operario = :id_operario', ['id_operario' => $id_operario]);
                foreach ($afectados as $u) {
                    Auditoria::registrarCambio(Auditoria::HABILITAR, 'usuario', $u['dni'],
                        "Usuario {$u['dni']} habilitado por alta del operario #{$id_operario}", ['activo' => false], ['activo' => true]);
                }
                return $ok;
            });
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
                WHERE (" . sqlBusquedaSinTildes('u.dni') . "
                   OR " . sqlBusquedaSinTildes('o.nombre') . "
                   OR " . sqlBusquedaSinTildes('o.apellido') . ")
                AND u.activo = TRUE
                ORDER BY o.apellido, o.nombre";

        return $this->db->fetchAll($sql, ['termino' => '%' . $termino . '%']);
    }
}
?>