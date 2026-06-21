<?php

namespace App\Models;

class Permission extends Model
{
    protected $table = 'permissions';

    protected $fillable = [
        'name', 'code', 'module', 'description'
    ];

    public function findByCode(string $code)
    {
        return $this->findBy('code', $code);
    }

    public function getByModule(string $module): array
    {
        return $this->where('module = ?', [$module], 'code ASC');
    }

    public function getRoles(int $permissionId): array
    {
        $sql = "
            SELECT r.* FROM roles r
            INNER JOIN role_permissions rp ON r.id = rp.role_id
            WHERE rp.permission_id = ?
        ";
        return $this->db->query($sql, [$permissionId]);
    }

    public function getAllGroupedByModule(): array
    {
        $permissions = $this->all('module, code ASC');
        $grouped = [];
        foreach ($permissions as $perm) {
            $grouped[$perm['module']][] = $perm;
        }
        return $grouped;
    }
}
