<?php

namespace App\Models;

class OperationLog extends Model
{
    protected $table = 'operation_logs';

    protected $fillable = [
        'user_id', 'username', 'module', 'action',
        'resource_type', 'resource_id', 'old_value', 'new_value',
        'request_params', 'response_code', 'ip_address', 'user_agent',
        'remark', 'status'
    ];

    public function log(
        string $module,
        string $action,
        $resourceType = null,
        $resourceId = null,
        $oldValue = null,
        $newValue = null,
        $requestParams = null,
        $responseCode = null,
        $userId = null,
        $username = null,
        string $remark = '',
        string $ip = '',
        string $userAgent = '',
        int $status = 1
    ): int {
        return $this->create([
            'user_id' => $userId,
            'username' => $username,
            'module' => $module,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId === null ? null : (is_scalar($resourceId) ? (string)$resourceId : json_encode($resourceId, JSON_UNESCAPED_UNICODE)),
            'old_value' => $oldValue === null ? null : (is_scalar($oldValue) ? (string)$oldValue : json_encode($oldValue, JSON_UNESCAPED_UNICODE)),
            'new_value' => $newValue === null ? null : (is_scalar($newValue) ? (string)$newValue : json_encode($newValue, JSON_UNESCAPED_UNICODE)),
            'request_params' => $requestParams === null ? null : json_encode($requestParams, JSON_UNESCAPED_UNICODE),
            'response_code' => $responseCode,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'remark' => $remark,
            'status' => $status,
        ]);
    }

    public function findByResource(string $resourceType, $resourceId): array
    {
        $resourceIdStr = is_scalar($resourceId) ? (string)$resourceId : json_encode($resourceId, JSON_UNESCAPED_UNICODE);
        return $this->where(
            'resource_type = ? AND resource_id = ?',
            [$resourceType, $resourceIdStr],
            'id DESC'
        );
    }

    public function findByUser(int $userId, int $limit = 100): array
    {
        return $this->where(
            'user_id = ?',
            [$userId],
            'id DESC',
            $limit
        );
    }

    public function findByModule(string $module, int $limit = 100): array
    {
        return $this->where(
            'module = ?',
            [$module],
            'id DESC',
            $limit
        );
    }

    public function findByModuleAndAction(string $module, string $action, int $limit = 100): array
    {
        return $this->where(
            'module = ? AND action = ?',
            [$module, $action],
            'id DESC',
            $limit
        );
    }

    public function search(
        string $module = '',
        string $action = '',
        int $userId = 0,
        string $startDate = '',
        string $endDate = '',
        int $page = 1,
        int $pageSize = 20
    ): array {
        $where = [];
        $params = [];

        if ($module) {
            $where[] = 'module = ?';
            $params[] = $module;
        }
        if ($action) {
            $where[] = 'action = ?';
            $params[] = $action;
        }
        if ($userId > 0) {
            $where[] = 'user_id = ?';
            $params[] = $userId;
        }
        if ($startDate) {
            $where[] = 'DATE(created_at) >= ?';
            $params[] = $startDate;
        }
        if ($endDate) {
            $where[] = 'DATE(created_at) <= ?';
            $params[] = $endDate;
        }

        $whereSql = !empty($where) ? implode(' AND ', $where) : '';
        $offset = ($page - 1) * $pageSize;

        $total = $this->count($whereSql, $params);
        $list = $this->where($whereSql, $params, 'id DESC', $pageSize, $offset);

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ];
    }
}
