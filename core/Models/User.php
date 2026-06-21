<?php

namespace App\Models;

class User extends Model
{
    protected $table = 'users';

    protected $fillable = [
        'username', 'password_hash', 'real_name', 'email', 'phone', 'status',
        'last_login_at', 'last_login_ip'
    ];

    public function findByUsername(string $username)
    {
        return $this->findBy('username', $username);
    }

    public function getRoles(int $userId): array
    {
        $sql = "
            SELECT r.* FROM roles r
            INNER JOIN user_roles ur ON r.id = ur.role_id
            WHERE ur.user_id = ? AND r.status = 1
        ";
        return $this->db->query($sql, [$userId]);
    }

    public function getPermissions(int $userId): array
    {
        $sql = "
            SELECT DISTINCT p.code, p.name, p.module, p.description
            FROM permissions p
            INNER JOIN role_permissions rp ON p.id = rp.permission_id
            INNER JOIN user_roles ur ON rp.role_id = ur.role_id
            INNER JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ? AND r.status = 1
        ";
        return $this->db->query($sql, [$userId]);
    }

    public function hasPermission(int $userId, string $permissionCode): bool
    {
        $sql = "
            SELECT COUNT(*) as count
            FROM permissions p
            INNER JOIN role_permissions rp ON p.id = rp.permission_id
            INNER JOIN user_roles ur ON rp.role_id = ur.role_id
            INNER JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ? AND p.code = ? AND r.status = 1
            LIMIT 1
        ";
        $result = $this->db->query($sql, [$userId, $permissionCode]);
        return !empty($result) && intval($result[0]['count']) > 0;
    }

    public function createToken(int $userId, string $token, string $expiresAt, string $ip = '', string $userAgent = ''): int
    {
        return $this->db->insert('user_tokens', [
            'user_id' => $userId,
            'token' => $token,
            'expires_at' => $expiresAt,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }

    public function findByToken(string $token)
    {
        $sql = "
            SELECT u.*, t.expires_at, t.ip_address, t.user_agent
            FROM users u
            INNER JOIN user_tokens t ON u.id = t.user_id
            WHERE t.token = ? AND t.expires_at > NOW() AND u.status = 1
            LIMIT 1
        ";
        $stmt = $this->db->getPdo()->prepare($sql);
        $stmt->execute([$token]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function deleteToken(string $token): int
    {
        return $this->db->delete('user_tokens', 'token = ?', [$token]);
    }

    public function deleteExpiredTokens(): int
    {
        return $this->db->delete('user_tokens', 'expires_at <= NOW()');
    }
}
