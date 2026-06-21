<?php

namespace App\Services;

class Database
{
    private static $instance = null;
    private $pdo;

    public function __construct()
    {
        $dbPath = $GLOBALS['test_db_path'] ?? __DIR__ . '/../../data/app.sqlite';

        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->pdo = new \PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo(): \PDO
    {
        return $this->pdo;
    }

    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $params = []): int
    {
        $sets = [];
        $values = [];
        foreach ($data as $column => $value) {
            $sets[] = "{$column} = ?";
            $values[] = $value;
        }
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $sets),
            $where
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($values, $params));
        return $stmt->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = sprintf('DELETE FROM %s WHERE %s', $table, $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function selectOne(string $table, string $where, array $params = [], string $columns = '*')
    {
        $sql = sprintf('SELECT %s FROM %s WHERE %s LIMIT 1', $columns, $table, $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    public function selectAll(string $table, string $where = '', array $params = [], string $columns = '*', string $orderBy = '', int $limit = 0, int $offset = 0): array
    {
        $sql = sprintf('SELECT %s FROM %s', $columns, $table);
        if ($where) {
            $sql .= ' WHERE ' . $where;
        }
        if ($orderBy) {
            $sql .= ' ORDER BY ' . $orderBy;
        }
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
            if ($offset > 0) {
                $sql .= ' OFFSET ' . $offset;
            }
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function count(string $table, string $where = '', array $params = []): int
    {
        $sql = sprintf('SELECT COUNT(*) FROM %s', $table);
        if ($where) {
            $sql .= ' WHERE ' . $where;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }
}
