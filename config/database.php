<?php
// config/database.php

/**
 * Convierte una excepción capturada en una Exception apta para mostrar al usuario.
 *
 * - Error de base de datos (PDOException): el detalle técnico (SQL, tablas, columnas) se guarda
 *   solo en el log y el usuario recibe un mensaje genérico. En modo debug se agrega el detalle.
 * - Cualquier otro error (validaciones propias como "El nombre ya existe"): se conserva su mensaje.
 *
 * Uso:  catch (Exception $e) { throw errorAmigable('Error al crear el rubro', $e); }
 */
function errorAmigable(string $contexto, Throwable $e): Exception
{
    if ($e instanceof PDOException) {
        error_log('[DB] ' . $contexto . ': ' . $e->getMessage());
        $detalle = (defined('DEBUG_MODE') && DEBUG_MODE) ? ' (' . $e->getMessage() . ')' : '';
        return new Exception($contexto . '. Intente nuevamente.' . $detalle);
    }
    return new Exception($contexto . ': ' . $e->getMessage());
}

class Database
{
    private static ?PDO $conexion = null;

    public static function getConnection(): PDO
    {
        if (self::$conexion === null) {
            if (file_exists(__DIR__ . '/env.php')) {
                require_once __DIR__ . '/env.php';
            }

            $host = defined('DB_HOST') ? DB_HOST : 'localhost';
            $port = defined('DB_PORT') ? DB_PORT : '5432';
            $dbname = defined('DB_NAME') ? DB_NAME : 'db_control_stock';
            $user = defined('DB_USER') ? DB_USER : 'postgres';
            $password = defined('DB_PASS') ? DB_PASS : '';

            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};";

            try {
                self::$conexion = new PDO($dsn, $user, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                error_log("Conexión a PostgreSQL fallida: " . $e->getMessage());
                http_response_code(500);
                die((defined("DEBUG_MODE") && DEBUG_MODE)
                    ? "Error crítico de conexión a PostgreSQL: " . $e->getMessage()
                    : "No se pudo conectar con la base de datos. Intente más tarde.");
            }
        }
        return self::$conexion;
    }

    // Método auxiliar para vincular parámetros preservando tipos booleanos y nulos para PostgreSQL
    private static function bindValues(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $k => $v) {
            $paramName = is_int($k) ? $k + 1 : (str_starts_with($k, ':') ? $k : ':' . $k);
            if (is_bool($v)) {
                $stmt->bindValue($paramName, $v, PDO::PARAM_BOOL);
            } elseif (is_null($v)) {
                $stmt->bindValue($paramName, null, PDO::PARAM_NULL);
            } elseif (is_int($v)) {
                $stmt->bindValue($paramName, $v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($paramName, (string)$v, PDO::PARAM_STR);
            }
        }
    }

    // Método estático de ayuda que están llamando las clases
    public static function fetchAll(string $sql, array $params = []): array
    {
        $pdo = self::getConnection();
        if (empty($params)) {
            $stmt = $pdo->query($sql);
        } else {
            $stmt = $pdo->prepare($sql);
            self::bindValues($stmt, $params);
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Método estático para obtener una sola fila
    public static function fetch(string $sql, array $params = []): ?array
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare($sql);
        self::bindValues($stmt, $params);
        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado ?: null;
    }

    // Método estático para ejecutar INSERT, UPDATE, DELETE
    public static function execute(string $sql, array $params = []): bool
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare($sql);
        self::bindValues($stmt, $params);
        return $stmt->execute();
    }

    // Métodos de instancia compatibles con Operario, Rubro, Movimiento, etc.
    public function fetchOne(string $sql, array $params = []): ?array
    {
        return self::fetch($sql, $params);
    }

    public function beginTransaction(): bool
    {
        return self::getConnection()->beginTransaction();
    }

    public function commit(): bool
    {
        return self::getConnection()->commit();
    }

    public function rollBack(): bool
    {
        return self::getConnection()->rollBack();
    }

    public function inTransaction(): bool
    {
        return self::getConnection()->inTransaction();
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return self::getConnection()->lastInsertId($name);
    }

    public function insert(string $table, array $data): array|bool
    {
        $fields = array_keys($data);
        $placeholders = array_map(fn($f) => ':' . $f, $fields);
        $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ") RETURNING *";
        $pdo = self::getConnection();
        $stmt = $pdo->prepare($sql);
        self::bindValues($stmt, $data);
        $stmt->execute();
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: true;
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): bool
    {
        $setParts = [];
        $bindings = [];
        foreach ($data as $field => $val) {
            $setParts[] = "{$field} = :set_{$field}";
            $bindings['set_' . $field] = $val;
        }
        $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE {$where}";
        foreach ($whereParams as $k => $v) {
            $bindings[ltrim($k, ':')] = $v;
        }
        return self::execute($sql, $bindings);
    }
}

// Variable global $pdo para compatibilidad con código procedural
$pdo = Database::getConnection();
