<?php
require_once __DIR__ . '/common.php';

$pdo = getDbConnection();

$user = require_authentication();
$token = get_authorization_token();

init_audit_log('auth', 'logout', null, $user['id']);

if ($token) {
    $stmt = $pdo->prepare("DELETE FROM user_tokens WHERE token = ?");
    $stmt->execute([$token]);
}

set_audit_remark('登出成功');

json_success(null, '登出成功');
