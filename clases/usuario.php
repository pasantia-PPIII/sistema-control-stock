<?php
// clases/usuario.php

require_once __DIR__ . '/../config/database.php';

class Usuario
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Cuenta total de usuarios filtrados para calcular la paginación
     */
    public function contarFiltrados($busqueda = null, $rol_id = null): int
    {
        if (is_array($busqueda)) {
            $filtros = $busqueda;
            $busqueda = $filtros['busqueda'] ?? null;
            $rol_id = $filtros['id_rol'] ?? ($filtros['rol_id'] ?? null);
        }

        // Corregido: JOIN con id_operario en lugar de dni_operario
        $sql = "SELECT COUNT(*) AS total 
                FROM usuario u 
                INNER JOIN rol r ON u.id_rol = r.id_rol 
                LEFT JOIN operario o ON u.id_operario = o.id_operario 
                WHERE 1=1 AND u.activo = TRUE";
        $params = [];

        if (!empty($busqueda)) {
            $sql .= " AND (u.usuario ILIKE :b OR u.nombre ILIKE :b OR u.apellido ILIKE :b OR u.dni ILIKE :b)";
            $params[':b'] = "%{$busqueda}%";
        }
        if (!empty($rol_id)) {
            $sql .= " AND u.id_rol = :rol";
            $params[':rol'] = (int) $rol_id;
        }

        $res = Database::fetch($sql, $params);
        return (int) ($res['total'] ?? 0);
    }

    /**
     * Listado paginado de usuarios para el dashboard
     */
    public function getFiltrados($busqueda = null, $rol_id = null, int $limite = 15, int $offset = 0): array
    {
        if (is_array($busqueda)) {
            $filtros = $busqueda;
            $busqueda = $filtros['busqueda'] ?? null;
            $rol_id = $filtros['id_rol'] ?? ($filtros['rol_id'] ?? null);
            $limite = isset($filtros['limite']) ? (int) $filtros['limite'] : $limite;
            $offset = isset($filtros['offset']) ? (int) $filtros['offset'] : $offset;
        }

        // Corregido: u.id_operario = o.id_operario
        // Agregamos r.rol AS nombre_rol para que coincida con la línea 135 del dashboard
        $sql = "SELECT u.id_usuario, u.usuario, u.nombre, u.apellido, u.dni, u.activo,
                       r.id_rol, r.rol AS rol_nombre, r.rol AS nombre_rol,
                       o.legajo AS operario_legajo, o.dni AS dni_operario,
                       CONCAT(o.apellido, ' ', o.nombre) AS operario_nombre
                FROM usuario u
                INNER JOIN rol r ON u.id_rol = r.id_rol
                LEFT JOIN operario o ON u.id_operario = o.id_operario
                WHERE 1=1 AND u.activo = TRUE";
        $params = [];

        if (!empty($busqueda)) {
            $sql .= " AND (u.usuario ILIKE :b OR u.nombre ILIKE :b OR u.apellido ILIKE :b OR u.dni ILIKE :b)";
            $params[':b'] = "%{$busqueda}%";
        }
        if (!empty($rol_id)) {
            $sql .= " AND u.id_rol = :rol";
            $params[':rol'] = (int) $rol_id;
        }

        $sql .= " ORDER BY u.id_usuario ASC LIMIT :limite OFFSET :offset";

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

    public function obtenerPorId(int $id): ?array
    {
        $sql = "SELECT u.*, r.rol AS rol_nombre 
                FROM usuario u 
                INNER JOIN rol r ON u.id_rol = r.id_rol 
                WHERE u.id_usuario = :id";
        return Database::fetch($sql, [':id' => $id]);
    }

    public function crear(array $d): bool
    {
        $hash = password_hash($d['password'], PASSWORD_DEFAULT);
        $sql = "INSERT INTO usuario (usuario, contrasena, nombre, apellido, dni, id_rol, id_operario, activo) 
                VALUES (:usr, :pass, :nom, :ape, :dni, :rol, :op, TRUE)";
        return Database::execute($sql, [
            ':usr' => $d['usuario'],
            ':pass' => $hash,
            ':nom' => $d['nombre'],
            ':ape' => $d['apellido'],
            ':dni' => !empty($d['dni']) ? $d['dni'] : null,
            ':rol' => (int) $d['id_rol'],
            ':op' => !empty($d['id_operario']) ? (int) $d['id_operario'] : null
        ]);
    }

    public function editar(int $id, array $d): bool
    {
        $params = [
            ':usr' => $d['usuario'],
            ':nom' => $d['nombre'],
            ':ape' => $d['apellido'],
            ':dni' => !empty($d['dni']) ? $d['dni'] : null,
            ':rol' => (int) $d['id_rol'],
            ':op' => !empty($d['id_operario']) ? (int) $d['id_operario'] : null,
            ':id' => $id
        ];

        $extraSql = "";
        if (!empty($d['password'])) {
            $extraSql = ", contrasena = :pass";
            $params[':pass'] = password_hash($d['password'], PASSWORD_DEFAULT);
        }

        $sql = "UPDATE usuario SET 
                    usuario = :usr, nombre = :nom, apellido = :ape, 
                    dni = :dni, id_rol = :rol, id_operario = :op {$extraSql}
                WHERE id_usuario = :id";

        return Database::execute($sql, $params);
    }

    public function alternarEstado(int $id): bool
    {
        return Database::execute("UPDATE usuario SET activo = NOT activo WHERE id_usuario = :id", [':id' => $id]);
    }

    public function getRoles(): array
    {
        return Database::fetchAll("SELECT id_rol, rol FROM rol ORDER BY id_rol ASC");
    }

    public function getOperariosDisponibles(): array
    {
        return Database::fetchAll("SELECT id_operario, legajo, nombre, apellido FROM operario WHERE activo = TRUE ORDER BY apellido, nombre");
    }
}