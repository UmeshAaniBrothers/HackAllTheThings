<?php

/**
 * Database - PDO Singleton Wrapper
 *
 * Provides a single persistent PDO connection with
 * prepared statement helpers for safe database operations.
 */
class Database
{
    /** @var Database|null */
    private static $instance = null;
    /** @var PDO */
    private $pdo;
    /** @var array */
    private $config;

    private function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }

    private function connect()
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $this->config['host'],
            $this->config['name'],
            $this->config['charset']
        );

        $this->pdo = new PDO($dsn, $this->config['user'], $this->config['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    /**
     * Reconnect if the connection has been lost.
     */
    public function reconnect()
    {
        try {
            $this->pdo->query('SELECT 1');
        } catch (PDOException $e) {
            $this->connect();
        }
    }

    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            if (empty($config)) {
                throw new RuntimeException('Database config required for first initialization');
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Execute a query with parameters and return the statement.
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            // Auto-reconnect on "MySQL server has gone away" or "Lost connection"
            if (strpos($e->getMessage(), '2006') !== false || strpos($e->getMessage(), '2013') !== false) {
                $this->connect();
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt;
            }
            throw $e;
        }
    }

    /**
     * Fetch all rows from a query.
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Fetch a single row from a query.
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row ?: null;
    }

    /**
     * Fetch a single column value.
     */
    public function fetchColumn(string $sql, array $params = [])
    {
        return $this->query($sql, $params)->fetchColumn();
    }

    /**
     * Insert a row and return the last insert ID.
     */
    public function insert(string $table, array $data): string
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));

        return $this->pdo->lastInsertId();
    }

    /**
     * Update rows matching conditions.
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        $values = [];
        foreach ($data as $column => $value) {
            $sets[] = "{$column} = ?";
            $values[] = $value;
        }

        $sql = "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE {$where}";
        $stmt = $this->query($sql, array_merge($values, $whereParams));

        return $stmt->rowCount();
    }

    /**
     * Begin a transaction.
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit a transaction.
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Roll back a transaction.
     */
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }
}
