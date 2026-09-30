<?php
class Database {
    private $host;
    private $port;
    private $dbname;
    private $username;
    private $password;
    private $pdo;

    public function __construct() {
        // Cargar constantes desde config.php
        $this->host = DB_HOST;
        $this->port = DB_PORT;
        $this->dbname = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;

        try {
            // Cadena de conexión para PostgreSQL
            $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->dbname}";

            $this->pdo = new PDO(
                $dsn,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => false
                ]
            );
        } catch (PDOException $e) {
            // En producción, no mostrar el error detallado
            error_log("Error de conexión: " . $e->getMessage());
            die("Error de conexión a la base de datos");
        }
    }

    /**
     * Obtener la conexión PDO
     */
    public function getConnection() {
        return $this->pdo;
    }

    /**
     * Ejecutar una consulta y devolver UN solo registro
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /**
     * Ejecutar una consulta y devolver VARIOS registros
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Insertar un registro y devolver el registro completo
     */
    public function insert($table, $data) {
        $columns = implode(", ", array_keys($data));
        $placeholders = ":" . implode(", :", array_keys($data));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders}) RETURNING *";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);

        return $stmt->fetch();
    }

    /**
     * Actualizar registros existentes
     */
    public function update($table, $data, $where, $params) {
        $set = implode(", ", array_map(fn($key) => "{$key} = :{$key}", array_keys($data)));
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(array_merge($data, $params));
    }

    /**
     * Eliminar un registro
     */
    public function delete($table, $where, $params) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Ejecutar consultas genéricas
     */
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // =========================================================
    // MÉTODOS DE TRANSACCIÓN (AGREGADOS)
    // =========================================================

    /**
     * Iniciar una transacción
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * Confirmar una transacción
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * Cancelar una transacción (rollback)
     */
    public function rollBack() {
        return $this->pdo->rollBack();
    }

    /**
     * Verificar si hay una transacción activa
     */
    public function inTransaction() {
        return $this->pdo->inTransaction();
    }
}
?>