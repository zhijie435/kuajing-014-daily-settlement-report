<?php
require_once __DIR__ . '/common.php';

$pdo = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('请求方式错误');
}

$input = get_json_input();
$id = isset($input['id']) ? intval($input['id']) : 0;
$checkStatus = isset($input['check_status']) ? intval($input['check_status']) : 0;
$checkRemark = isset($input['check_remark']) ? trim($input['check_remark']) : '';

if (!$id) {
    json_error('请选择要核对的记录');
}

if (!in_array($checkStatus, [1, 2])) {
    json_error('核对状态不正确');
}

$stmt = $pdo->prepare("SELECT * FROM settlement_daily WHERE id = ?");
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    json_error('记录不存在');
}

$updateSql = "
    UPDATE settlement_daily 
    SET check_status = ?, check_remark = ?, checked_at = NOW()
    WHERE id = ?
";
$stmt = $pdo->prepare($updateSql);
$result = $stmt->execute([$checkStatus, $checkRemark, $id]);

if ($result) {
    json_success(['id' => $id, 'check_status' => $checkStatus], '核对成功');
} else {
    json_error('核对失败');
}
