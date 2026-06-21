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

try {
    $pdo->beginTransaction();

    $settlementDate = $record['settlement_date'];

    $updateDailySql = "
        UPDATE settlement_daily 
        SET check_status = ?, check_remark = ?, checked_at = NOW()
        WHERE id = ?
    ";
    $stmt = $pdo->prepare($updateDailySql);
    $stmt->execute([$checkStatus, $checkRemark, $id]);

    $newDetailStatus = ($checkStatus == 1) ? 3 : 2;
    $updateDetailSql = "
        UPDATE settlement_detail 
        SET settlement_status = ?, updated_at = NOW()
        WHERE settlement_date = ?
    ";
    $stmt = $pdo->prepare($updateDetailSql);
    $stmt->execute([$newDetailStatus, $settlementDate]);

    $pdo->commit();

    json_success([
        'id' => $id,
        'check_status' => $checkStatus,
        'settlement_status' => $newDetailStatus,
        'settlement_date' => $settlementDate,
    ], '核对成功');
} catch (Exception $e) {
    $pdo->rollBack();
    json_error('核对失败: ' . $e->getMessage());
}
