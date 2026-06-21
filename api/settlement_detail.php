<?php
require_once __DIR__ . '/common.php';

$pdo = getDbConnection();

$user = require_permission('settlement:detail:view');
init_audit_log('settlement', 'view_detail', 'settlement_detail', null, $_GET);

$settlementDate = get_param('settlementDate', '');
$settlementType = get_param('settlementType', '');
$settlementStatus = get_param('settlementStatus', '');
$keyword = get_param('keyword', '');
$page     = get_param('page', 1);
$pageSize = get_param('pageSize', 20);

if (!$settlementDate) {
    json_error('请指定结算日期', 400);
}

validate_date($settlementDate, '结算日期');

if ($settlementType !== '' && $settlementType !== null && !in_array(intval($settlementType), [1, 2, 3], true)) {
    json_error('结算类型参数不正确，可选值：1-正常结算 2-退款 3-补款', 400);
}
if ($settlementStatus !== '' && $settlementStatus !== null && !in_array(intval($settlementStatus), [1, 2, 3], true)) {
    json_error('结算状态参数不正确，可选值：1-待结算 2-已结算 3-已对账', 400);
}

if ($keyword !== '' && mb_strlen($keyword) > 100) {
    json_error('搜索关键词长度不能超过100个字符', 400);
}

$safeParams = safe_page_params($page, $pageSize);
$page = $safeParams['page'];
$pageSize = $safeParams['pageSize'];

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

if ($total === 0) {
    set_audit_remark('查看结算明细，结算日期:' . $settlementDate . '，无匹配记录');
    set_audit_resource('settlement_detail', $settlementDate);
    json_success([
        'list'    => [],
        'total'   => 0,
        'page'    => $page,
        'pageSize' => $pageSize,
        'summary' => normalize_summary(null, [
            'detail_count', 'total_quantity',
            'total_order_amount', 'total_discount_amount',
            'total_settlement_amount', 'total_commission_fee', 'total_net_amount',
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
    LIMIT {$offset}, {$pageSize}
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
$summary = normalize_summary($stmt->fetch(), [
    'detail_count', 'total_quantity',
    'total_order_amount', 'total_discount_amount',
    'total_settlement_amount', 'total_commission_fee', 'total_net_amount',
]);

set_audit_remark('查看结算明细，结算日期:' . $settlementDate . '，共' . count($list) . '条记录');
set_audit_resource('settlement_detail', $settlementDate);

json_success([
    'list'    => $list,
    'total'   => $total,
    'page'    => $page,
    'pageSize' => $pageSize,
    'summary' => $summary,
]);
