<?php
require_once __DIR__ . '/common.php';

$pdo = getDbConnection();

$user = require_authentication();

init_audit_log('auth', 'get_user_info', null, $user['id']);

$rolesStmt = $pdo->prepare("
    SELECT r.* FROM roles r
    INNER JOIN user_roles ur ON r.id = ur.role_id
    WHERE ur.user_id = ? AND r.status = 1
");
$rolesStmt->execute([$user['id']]);
$roles = $rolesStmt->fetchAll();

$permsStmt = $pdo->prepare("
    SELECT DISTINCT p.code, p.name, p.module
    FROM permissions p
    INNER JOIN role_permissions rp ON p.id = rp.permission_id
    INNER JOIN user_roles ur ON rp.role_id = ur.role_id
    INNER JOIN roles r ON ur.role_id = r.id
    WHERE ur.user_id = ? AND r.status = 1
");
$permsStmt->execute([$user['id']]);
$permissions = $permsStmt->fetchAll();

json_success([
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'real_name' => $user['real_name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'last_login_at' => $user['last_login_at'],
    ],
    'roles' => $roles,
    'permissions' => array_column($permissions, 'code'),
]);
