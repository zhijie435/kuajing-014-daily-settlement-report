<?php

namespace App\Models;

class Role extends Model
{
    protected $table = 'roles';

    protected $fillable = [
        'name', 'code', 'description', 'status'
    ];

    public function findByCode(string $code)
    {
        return $this->findBy('code', $code);
    }

    public function getPermissions(int $roleId): array
    {
        $sql = "
            SELECT p.* FROM permissions p
            INNER JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?
        ";
        return $this->db->query($sql, [$roleId]);
    }

    public function getUsers(int $roleId): array
    {
        $sql = "
            SELECT u.* FROM users u
            INNER JOIN user_roles ur ON u.id = ur.user_id
            WHERE ur.role_id = ?
        ";
        return $this->db->query($sql, [$roleId]);
    }

    public function assignPermission(int $roleId, int $permissionId): int
    {
        return $this->db->insert('role_permissions', [
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ]);
    }

    public function removePermission(int $roleId, int $permissionId): int
    {
        return $this->db->delete('role_permissions', 'role_id = ? AND permission_id = ?', [$roleId, $permissionId]);
    }

    public function assignUser(int $roleId, int $userId): int
    {
        return $this->db->insert('user_roles', [
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);
    }

    public function removeUser(int $roleId, int $userId): int
    {
        return $this->db->delete('user_roles', 'user_id = ? AND role_id = ?', [$userId, $roleId]);
    }
}
