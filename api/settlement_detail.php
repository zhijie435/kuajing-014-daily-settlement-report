<?php
require_once __DIR__ . '/common.php';

$pdo = getDbConnection();

$settlementDate = get_param('settlementDate', '');
$settlementType = get_param('settlementType', '');
$settlementStatus = get_param('settlementStatus', '');
$keyword = get_param('keyword', '');

if (!$settlementDate) {
    json_error('请指定结算日期');
}

$where = ['settlement_date = ?'];
$params = [$settlementDate];

if ($settlementType !== '' && $settlementType !== null) {
    $where[] = 'settlement_type = ?';
    $params[] = intval($settlementType);
}
if ($settlementStatus !== '' && $settlementStatus !== null) {
    $where[] = 'settlement_status = ?';
    $params[] = intval($settlementStatus);
}
if ($keyword) {
    $where[] = '(order_no LIKE ? OR goods_name LIKE ? OR goods_no LIKE ?)';
    $keywordParam = "%{$keyword}%";
    $params[] = $keywordParam;
    $params[] = $keywordParam;
    $params[] = $keywordParam;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$countSql = "SELECT COUNT(*) AS total FROM settlement_detail {$whereSql}";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = intval($stmt->fetch()['total']);

$listSql = "
    SELECT 
        id,
        settlement_date,
        order_id,
        order_no,
        goods_id,
        goods_name,
        goods_no,
        quantity,
        unit_price,
        order_amount,
        discount_amount,
        settlement_amount,
        commission_fee,
        net_amount,
        settlement_type,
        settlement_status,
        remark,
        created_at
    FROM settlement_detail
    {$whereSql}
    ORDER BY id DESC
";
$stmt = $pdo->prepare($listSql);
$stmt->execute($params);
$list = $stmt->fetchAll();

$summarySql = "
    SELECT 
        COUNT(*) AS detail_count,
        SUM(quantity) AS total_quantity,
        SUM(order_amount) AS total_order_amount,
        SUM(discount_amount) AS total_discount_amount,
        SUM(settlement_amount) AS total_settlement_amount,
        SUM(commission_fee) AS total_commission_fee,
        SUM(net_amount) AS total_net_amount
    FROM settlement_detail
    {$whereSql}
";
$stmt = $pdo->prepare($summarySql);
$stmt->execute($params);
$summary = $stmt->fetch();

json_success([
    'list'    => $list,
    'total'   => $total,
    'summary' => $summary,
]);
