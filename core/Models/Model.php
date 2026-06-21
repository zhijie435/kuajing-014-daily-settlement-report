<?php

namespace App\Models;

use App\Services\Database;

abstract class Model
{
    protected $table = '';
    protected $fillable = [];
    protected $primaryKey = 'id';
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }

    public function create(array $data): int
    {
        $filtered = $this->filterFillable($data);
        return $this->db->insert($this->table, $filtered);
    }

    public function update($id, array $data): int
    {
        $filtered = $this->filterFillable($data);
        return $this->db->update($this->table, $filtered, "{$this->primaryKey} = ?", [$id]);
    }

    public function delete($id): int
    {
        return $this->db->delete($this->table, "{$this->primaryKey} = ?", [$id]);
    }

    public function find($id)
    {
        return $this->db->selectOne($this->table, "{$this->primaryKey} = ?", [$id]);
    }

    public function findBy(string $column, $value)
    {
        return $this->db->selectOne($this->table, "{$column} = ?", [$value]);
    }

    public function all(string $orderBy = ''): array
    {
        return $this->db->selectAll($this->table, '', [], '*', $orderBy);
    }

    public function where(string $where, array $params = [], string $orderBy = '', int $limit = 0): array
    {
        return $this->db->selectAll($this->table, $where, $params, '*', $orderBy, $limit);
    }

    public function count(string $where = '', array $params = []): int
    {
        return $this->db->count($this->table, $where, $params);
    }
}
