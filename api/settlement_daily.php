<?php
require_once __DIR__ . '/common.php';

$pdo = getDbConnection();

$user = require_permission('settlement:daily:view');
init_audit_log('settlement', 'view_daily', 'settlement_daily', null, $_GET);

$page      = get_param('page', 1);
$pageSize  = get_param('pageSize', 20);
$startDate = get_param('startDate', '');
$endDate   = get_param('endDate', '');
$checkStatus = get_param('checkStatus', '');
$settlementStatus = get_param('settlementStatus', '');

$safeParams = safe_page_params($page, $pageSize);
$page = $safeParams['page'];
$pageSize = $safeParams['pageSize'];

validate_date($startDate, '开始日期');
validate_date($endDate, '结束日期');
validate_date_range($startDate, $endDate, 366);

if ($checkStatus !== '' && $checkStatus !== null && !in_array(intval($checkStatus), [0, 1, 2], true)) {
    json_error('核对状态参数不正确，可选值：0-未核对 1-核对通过 2-核对异常', 400);
}
if ($settlementStatus !== '' && $settlementStatus !== null && !in_array(intval($settlementStatus), [1, 2, 3], true)) {
    json_error('结算状态参数不正确，可选值：1-待结算 2-已结算 3-已对账', 400);
}

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

if ($total === 0) {
    json_success([
        'list'     => [],
        'total'    => 0,
        'page'     => $page,
        'pageSize' => $pageSize,
        'summary'  => normalize_summary(null, [
            'total_days', 'total_orders', 'total_goods',
            'total_order_amount', 'total_discount_amount',
            'total_settlement_amount', 'total_commission_fee',
            'total_net_amount', 'total_refund_count', 'total_refund_amount',
        ]),
    ]);
}

$offset = ($page - 1) * $pageSize;
if ($offset >= $total) {
    $page = max(1, intval(ceil($total / $pageSize)));
    $offset = ($page - 1) * $pageSize;
}

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
$summary = normalize_summary($stmt->fetch(), [
    'total_days', 'total_orders', 'total_goods',
    'total_order_amount', 'total_discount_amount',
    'total_settlement_amount', 'total_commission_fee',
    'total_net_amount', 'total_refund_count', 'total_refund_amount',
]);

json_success([
    'list'     => $list,
    'total'    => $total,
    'page'     => $page,
    'pageSize' => $pageSize,
    'summary'  => $summary,
]);
