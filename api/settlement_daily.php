<?php
require_once __DIR__ . '/common.php';

$pdo = getDbConnection();

$page      = max(1, intval(get_param('page', 1)));
$pageSize  = max(1, min(100, intval(get_param('pageSize', 20))));
$startDate = get_param('startDate', '');
$endDate   = get_param('endDate', '');
$checkStatus = get_param('checkStatus', '');
$settlementStatus = get_param('settlementStatus', '');

$where = [];
$params = [];

if ($startDate) {
    $where[] = 'settlement_date >= ?';
    $params[] = $startDate;
}
if ($endDate) {
    $where[] = 'settlement_date <= ?';
    $params[] = $endDate;
}
if ($checkStatus !== '' && $checkStatus !== null) {
    $where[] = 'check_status = ?';
    $params[] = intval($checkStatus);
}
if ($settlementStatus !== '' && $settlementStatus !== null) {
    $where[] = 'settlement_status = ?';
    $params[] = intval($settlementStatus);
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

$countSql = "SELECT COUNT(*) AS total FROM settlement_daily {$whereSql}";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = intval($stmt->fetch()['total']);

$offset = ($page - 1) * $pageSize;
$listSql = "
    SELECT 
        id,
        settlement_date,
        order_count,
        goods_count,
        total_order_amount,
        total_discount_amount,
        total_settlement_amount,
        total_commission_fee,
        total_net_amount,
        refund_count,
        refund_amount,
        settlement_status,
        check_status,
        check_remark,
        checked_at,
        created_at
    FROM settlement_daily 
    {$whereSql}
    ORDER BY settlement_date DESC
    LIMIT {$offset}, {$pageSize}
";
$stmt = $pdo->prepare($listSql);
$stmt->execute($params);
$list = $stmt->fetchAll();

$summarySql = "
    SELECT 
        COUNT(*) AS total_days,
        SUM(order_count) AS total_orders,
        SUM(goods_count) AS total_goods,
        SUM(total_order_amount) AS total_order_amount,
        SUM(total_discount_amount) AS total_discount_amount,
        SUM(total_settlement_amount) AS total_settlement_amount,
        SUM(total_commission_fee) AS total_commission_fee,
        SUM(total_net_amount) AS total_net_amount,
        SUM(refund_count) AS total_refund_count,
        SUM(refund_amount) AS total_refund_amount
    FROM settlement_daily
    {$whereSql}
";
$stmt = $pdo->prepare($summarySql);
$stmt->execute($params);
$summary = $stmt->fetch();

json_success([
    'list'     => $list,
    'total'    => $total,
    'page'     => $page,
    'pageSize' => $pageSize,
    'summary'  => $summary,
]);
