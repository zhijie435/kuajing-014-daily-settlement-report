<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config.php';

function json_success($data = null, $msg = 'success') {
    global $auditLogData;
    if (isset($auditLogData) && $auditLogData['response_code'] === null) {
        $auditLogData['response_code'] = 0;
    }
    echo json_encode([
        'code' => 0,
        'msg'  => $msg,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error($msg = 'error', $code = 1, $data = null) {
    global $auditLogData;
    if (isset($auditLogData) && $auditLogData['response_code'] === null) {
        $auditLogData['response_code'] = $code;
        $auditLogData['status'] = 0;
        $auditLogData['remark'] = $msg;
    }
    echo json_encode([
        'code' => $code,
        'msg'  => $msg,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function get_param($key, $default = null) {
    $value = isset($_GET[$key]) ? $_GET[$key] : (isset($_POST[$key]) ? $_POST[$key] : $default);
    return $value === null ? $default : trim($value);
}

function get_json_input() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    return $data ? $data : [];
}

function get_client_ip() {
    $ip = '';
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED'];
    } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
        $ip = $_SERVER['HTTP_FORWARDED'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip ? explode(',', $ip)[0] : 'unknown';
}

function get_user_agent() {
    return isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : '';
}

function get_authorization_token() {
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER['Authorization']);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
    }
    if (!empty($headers)) {
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
    }
    $token = get_param('token', '');
    return $token ?: null;
}

function get_current_auth_user() {
    static $currentUser = null;
    if ($currentUser !== null) {
        return $currentUser;
    }
    $token = get_authorization_token();
    if (!$token) {
        return null;
    }
    $pdo = getDbConnection();
    $sql = "
        SELECT u.*, t.expires_at, t.ip_address AS token_ip, t.user_agent AS token_ua
        FROM users u
        INNER JOIN user_tokens t ON u.id = t.user_id
        WHERE t.token = ? AND t.expires_at > NOW() AND u.status = 1
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user) {
        $currentUser = $user;
    }
    return $currentUser;
}

function get_user_permissions($userId) {
    static $permissionsCache = [];
    if (isset($permissionsCache[$userId])) {
        return $permissionsCache[$userId];
    }
    $pdo = getDbConnection();
    $sql = "
        SELECT DISTINCT p.code, p.name, p.module
        FROM permissions p
        INNER JOIN role_permissions rp ON p.id = rp.permission_id
        INNER JOIN user_roles ur ON rp.role_id = ur.role_id
        INNER JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = ? AND r.status = 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $perms = $stmt->fetchAll();
    $permissionsCache[$userId] = $perms;
    return $perms;
}

function get_user_permission_codes($userId) {
    $perms = get_user_permissions($userId);
    return array_column($perms, 'code');
}

function has_permission($permissionCode) {
    $user = get_current_auth_user();
    if (!$user) {
        return false;
    }
    $permCodes = get_user_permission_codes($user['id']);
    return in_array($permissionCode, $permCodes, true);
}

function require_authentication() {
    $user = get_current_auth_user();
    if (!$user) {
        json_error('未登录或登录已过期，请重新登录', 401);
    }
    return $user;
}

function require_permission($permissionCode) {
    $user = require_authentication();
    if (!has_permission($permissionCode)) {
        global $auditLogData;
        if (isset($auditLogData)) {
            $auditLogData['response_code'] = 403;
            $auditLogData['status'] = 0;
            $auditLogData['remark'] = '权限不足，需要: ' . $permissionCode;
        }
        json_error('权限不足，无法执行此操作', 403);
    }
    return $user;
}

function init_audit_log($module, $action, $resourceType = null, $resourceId = null, $requestParams = null) {
    global $auditLogData;
    $user = get_current_auth_user();
    $auditLogData = [
        'module' => $module,
        'action' => $action,
        'resource_type' => $resourceType,
        'resource_id' => $resourceId,
        'old_value' => null,
        'new_value' => null,
        'request_params' => $requestParams !== null ? $requestParams : array_merge($_GET, $_POST, get_json_input()),
        'response_code' => null,
        'user_id' => $user ? $user['id'] : null,
        'username' => $user ? $user['username'] : null,
        'ip_address' => get_client_ip(),
        'user_agent' => get_user_agent(),
        'remark' => '',
        'status' => 1,
    ];
}

function set_audit_old_value($value) {
    global $auditLogData;
    if (isset($auditLogData)) {
        $auditLogData['old_value'] = $value;
    }
}

function set_audit_new_value($value) {
    global $auditLogData;
    if (isset($auditLogData)) {
        $auditLogData['new_value'] = $value;
    }
}

function set_audit_remark($remark) {
    global $auditLogData;
    if (isset($auditLogData)) {
        $auditLogData['remark'] = $remark;
    }
}

function set_audit_resource($resourceType, $resourceId) {
    global $auditLogData;
    if (isset($auditLogData)) {
        $auditLogData['resource_type'] = $resourceType;
        $auditLogData['resource_id'] = $resourceId;
    }
}

function write_audit_log() {
    global $auditLogData;
    if (!isset($auditLogData)) {
        return;
    }
    try {
        $pdo = getDbConnection();
        $data = $auditLogData;
        $fields = [];
        $placeholders = [];
        $values = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }
            $fields[] = $key;
            $placeholders[] = '?';
            if (in_array($key, ['old_value', 'new_value', 'request_params'], true) && !is_scalar($value)) {
                $values[] = json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif ($key === 'resource_id' && !is_scalar($value)) {
                $values[] = json_encode($value, JSON_UNESCAPED_UNICODE);
            } else {
                $values[] = $value;
            }
        }
        if (empty($fields)) {
            return;
        }
        $sql = 'INSERT INTO operation_logs (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
    } catch (Exception $e) {
        error_log('Audit log error: ' . $e->getMessage());
    }
}

function validate_date($date, $fieldName = '日期') {
    if ($date === '' || $date === null) {
        return;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        json_error("{$fieldName}格式不正确，请使用 YYYY-MM-DD 格式", 400);
    }
    $parts = explode('-', $date);
    if (!checkdate(intval($parts[1]), intval($parts[2]), intval($parts[0]))) {
        json_error("{$fieldName}不是有效的日期", 400);
    }
}

function validate_date_range($startDate, $endDate, $maxDays = 366) {
    if ($startDate === '' || $startDate === null || $endDate === '' || $endDate === null) {
        return;
    }
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    if ($start > $end) {
        json_error('开始日期不能晚于结束日期', 400);
    }
    $diff = $start->diff($end);
    if ($diff->days > $maxDays) {
        json_error("查询日期范围不能超过{$maxDays}天", 400);
    }
}

function safe_page_params($page, $pageSize, $maxPageSize = 100) {
    $page = max(1, intval($page));
    $pageSize = max(1, min($maxPageSize, intval($pageSize)));
    return ['page' => $page, 'pageSize' => $pageSize];
}

function normalize_summary($summary, $fields) {
    if (!$summary) {
        $summary = [];
        foreach ($fields as $field) {
            $summary[$field] = 0;
        }
    }
    return $summary;
}

function require_export_permission($exportCode, $viewCode) {
    $user = require_permission($exportCode);
    if (!has_permission($viewCode)) {
        json_error('您没有对应数据的查看权限，无法导出', 403);
    }
    return $user;
}

register_shutdown_function(function() {
    write_audit_log();
});
