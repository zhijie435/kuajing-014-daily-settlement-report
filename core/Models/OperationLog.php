<?php

namespace App\Models;

class OperationLog extends Model
{
    protected $table = 'operation_logs';

    protected $fillable = [
        'module', 'resource_type', 'resource_id', 'action',
        'old_value', 'new_value', 'operator', 'remark', 'ip_address'
    ];

    public function log(string $module, string $resourceType, $resourceId, string $action, $oldValue = null, $newValue = null, string $operator = 'system', string $remark = '', string $ip = ''): int
    {
        return $this->create([
            'module' => $module,
            'resource_type' => $resourceType,
            'resource_id' => is_scalar($resourceId) ? (string)$resourceId : json_encode($resourceId, JSON_UNESCAPED_UNICODE),
            'action' => $action,
            'old_value' => $oldValue === null ? null : (is_scalar($oldValue) ? (string)$oldValue : json_encode($oldValue, JSON_UNESCAPED_UNICODE)),
            'new_value' => $newValue === null ? null : (is_scalar($newValue) ? (string)$newValue : json_encode($newValue, JSON_UNESCAPED_UNICODE)),
            'operator' => $operator,
            'remark' => $remark,
            'ip_address' => $ip,
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
}
