<?php
require_once __DIR__ . '/common.php';

$pdo = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('请求方式错误', 405);
}

$input = get_json_input();
$id = isset($input['id']) ? intval($input['id']) : 0;
$checkStatus = isset($input['check_status']) ? intval($input['check_status']) : 0;
$checkRemark = isset($input['check_remark']) ? trim($input['check_remark']) : '';
$forceRecheck = isset($input['force_recheck']) ? boolval($input['force_recheck']) : false;

if (!$id) {
    json_error('请选择要核对的记录', 1001);
}

if (!in_array($checkStatus, [1, 2], true)) {
    json_error('核对状态不正确，仅支持1(通过)或2(异常)', 1002);
}

if ($checkStatus === 2 && $checkRemark === '') {
    json_error('标记为核对异常时，核对备注为必填项', 1003);
}

$stmt = $pdo->prepare("SELECT * FROM settlement_daily WHERE id = ?");
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    json_error('记录不存在', 1004);
}

if (intval($record['settlement_status']) === 1) {
    json_error('该记录尚未结算，无法进行核对，请先完成结算', 1005);
}

if (intval($record['check_status']) === 1) {
    if (!$forceRecheck) {
        json_error('该记录已核对通过，确认重新核对请重试', 1006);
    }
}

if (intval($record['check_status']) === 2 && $checkStatus === 1) {
    if (!$forceRecheck) {
        json_error('该记录已标记为核对异常，确认改为核对通过请重试', 1007);
    }
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

    $newDetailStatus = ($checkStatus === 1) ? 3 : 2;
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
    json_error('核对失败: ' . $e->getMessage(), 1999);
}
