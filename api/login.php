<?php
require_once __DIR__ . '/common.php';

$pdo = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('请求方式错误', 405);
}

$input = get_json_input();
$username = isset($input['username']) ? trim($input['username']) : '';
$password = isset($input['password']) ? trim($input['password']) : '';

init_audit_log('auth', 'login', null, null, ['username' => $username]);

if (!$username || !$password) {
    json_error('用户名和密码不能为空', 1001);
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    json_error('用户名或密码错误', 1002);
}

if (intval($user['status']) !== 1) {
    json_error('账号已被禁用，请联系管理员', 1003);
}

if (!password_verify($password, $user['password_hash'])) {
    json_error('用户名或密码错误', 1002);
}

$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', time() + 86400 * 7);
$ip = get_client_ip();
$userAgent = get_user_agent();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO user_tokens (user_id, token, expires_at, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user['id'], $token, $expiresAt, $ip, $userAgent]);

    $stmt = $pdo->prepare("
        UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?
    ");
    $stmt->execute([$ip, $user['id']]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    json_error('登录失败: ' . $e->getMessage(), 1999);
}

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

set_audit_remark('登录成功');

json_success([
    'token' => $token,
    'expires_at' => $expiresAt,
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'real_name' => $user['real_name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
    ],
    'roles' => $roles,
    'permissions' => array_column($permissions, 'code'),
], '登录成功');
