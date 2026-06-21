<?php
require_once __DIR__ . '/common.php';

$pdo = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('请求方式错误，请使用GET请求', 405);
}

$type = isset($_GET['type']) ? trim($_GET['type']) : 'daily';
$startDate = isset($_GET['startDate']) ? trim($_GET['startDate']) : '';
$endDate = isset($_GET['endDate']) ? trim($_GET['endDate']) : '';
$settlementDate = isset($_GET['settlementDate']) ? trim($_GET['settlementDate']) : '';
$checkStatus = isset($_GET['checkStatus']) ? trim($_GET['checkStatus']) : '';
$settlementStatus = isset($_GET['settlementStatus']) ? trim($_GET['settlementStatus']) : '';
$settlementType = isset($_GET['settlementType']) ? trim($_GET['settlementType']) : '';
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$format = isset($_GET['format']) ? trim($_GET['format']) : 'csv';

function output_csv($filename, $headers, $rows) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $output = fopen('php://output', 'w');

    $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
    fwrite($output, $bom);

    fputcsv($output, $headers);

    foreach ($rows as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

function output_excel($filename, $headers, $rows) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office"
                   xmlns:x="urn:schemas-microsoft-com:office:excel"
                   xmlns="http://www.w3.org/TR/REC-html40">
             <head><meta charset="utf-8"></head><body><table border="1">';

    $html .= '<tr>';
    foreach ($headers as $h) {
        $html .= '<th style="background:#f0f0f0;">' . htmlspecialchars($h) . '</th>';
    }
    $html .= '</tr>';

    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . htmlspecialchars($cell) . '</td>';
        }
        $html .= '</tr>';
    }

    $html .= '</table></body></html>';
    echo $html;
    exit;
}

if ($type === 'daily') {
    $user = require_permission('export:daily');
    init_audit_log('export', 'export_daily', 'settlement_daily', null, $_GET);

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

    $sql = "
        SELECT
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
            CASE settlement_status
                WHEN 1 THEN '待结算'
                WHEN 2 THEN '已结算'
                WHEN 3 THEN '已对账'
                ELSE '未知'
            END AS settlement_status_text,
            CASE check_status
                WHEN 0 THEN '未核对'
                WHEN 1 THEN '核对通过'
                WHEN 2 THEN '核对异常'
                ELSE '未知'
            END AS check_status_text,
            check_remark,
            checked_at
        FROM settlement_daily
        {$whereSql}
        ORDER BY settlement_date DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    $headers = [
        '结算日期', '订单数量', '商品数量', '订单总金额', '优惠总金额',
        '结算总金额', '手续费总金额', '净收入总额', '退款笔数', '退款金额',
        '结算状态', '核对状态', '核对备注', '核对时间'
    ];

    $rows = [];
    $totalOrder = 0;
    $totalGoods = 0;
    $totalAmount = 0;
    $totalDiscount = 0;
    $totalSettle = 0;
    $totalFee = 0;
    $totalNet = 0;

    foreach ($data as $row) {
        $rows[] = [
            $row['settlement_date'],
            $row['order_count'],
            $row['goods_count'],
            $row['total_order_amount'],
            $row['total_discount_amount'],
            $row['total_settlement_amount'],
            $row['total_commission_fee'],
            $row['total_net_amount'],
            $row['refund_count'],
            $row['refund_amount'],
            $row['settlement_status_text'],
            $row['check_status_text'],
            $row['check_remark'],
            $row['checked_at'],
        ];
        $totalOrder += $row['order_count'];
        $totalGoods += $row['goods_count'];
        $totalAmount += $row['total_order_amount'];
        $totalDiscount += $row['total_discount_amount'];
        $totalSettle += $row['total_settlement_amount'];
        $totalFee += $row['total_commission_fee'];
        $totalNet += $row['total_net_amount'];
    }

    $rows[] = [
        '合计', $totalOrder, $totalGoods,
        $totalAmount, $totalDiscount,
        $totalSettle, $totalFee, $totalNet,
        '', '', '', '', '', ''
    ];

    set_audit_remark('导出日结算汇总报表，共' . count($data) . '条记录');

    $filename = '日结算汇总报表_' . date('YmdHis') . '.' . ($format === 'excel' ? 'xls' : 'csv');

    if ($format === 'excel') {
        output_excel($filename, $headers, $rows);
    } else {
        output_csv($filename, $headers, $rows);
    }
} elseif ($type === 'detail') {
    $user = require_permission('export:detail');
    init_audit_log('export', 'export_detail', 'settlement_detail', $settlementDate, $_GET);

    if (!$settlementDate) {
        json_error('请指定结算日期', 400);
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

    $sql = "
        SELECT
            settlement_date,
            order_no,
            goods_no,
            goods_name,
            quantity,
            unit_price,
            order_amount,
            discount_amount,
            settlement_amount,
            commission_fee,
            net_amount,
            CASE settlement_type
                WHEN 1 THEN '正常结算'
                WHEN 2 THEN '退款'
                WHEN 3 THEN '补款'
                ELSE '未知'
            END AS settlement_type_text,
            CASE settlement_status
                WHEN 1 THEN '待结算'
                WHEN 2 THEN '已结算'
                WHEN 3 THEN '已对账'
                ELSE '未知'
            END AS settlement_status_text,
            remark,
            created_at
        FROM settlement_detail
        {$whereSql}
        ORDER BY id DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    $headers = [
        '结算日期', '订单编号', '商品编号', '商品名称', '数量',
        '单价', '订单金额', '优惠金额', '结算金额', '手续费',
        '净收入', '结算类型', '结算状态', '备注', '创建时间'
    ];

    $rows = [];
    $totalQty = 0;
    $totalOrder = 0;
    $totalDiscount = 0;
    $totalSettle = 0;
    $totalFee = 0;
    $totalNet = 0;

    foreach ($data as $row) {
        $rows[] = [
            $row['settlement_date'],
            $row['order_no'],
            $row['goods_no'],
            $row['goods_name'],
            $row['quantity'],
            $row['unit_price'],
            $row['order_amount'],
            $row['discount_amount'],
            $row['settlement_amount'],
            $row['commission_fee'],
            $row['net_amount'],
            $row['settlement_type_text'],
            $row['settlement_status_text'],
            $row['remark'],
            $row['created_at'],
        ];
        $totalQty += $row['quantity'];
        $totalOrder += $row['order_amount'];
        $totalDiscount += $row['discount_amount'];
        $totalSettle += $row['settlement_amount'];
        $totalFee += $row['commission_fee'];
        $totalNet += $row['net_amount'];
    }

    $rows[] = [
        '合计', '', '', '', $totalQty,
        '', $totalOrder, $totalDiscount,
        $totalSettle, $totalFee, $totalNet,
        '', '', '', ''
    ];

    set_audit_remark('导出结算明细报表，结算日期:' . $settlementDate . '，共' . count($data) . '条记录');

    $filename = '结算明细报表_' . $settlementDate . '_' . date('YmdHis') . '.' . ($format === 'excel' ? 'xls' : 'csv');

    if ($format === 'excel') {
        output_excel($filename, $headers, $rows);
    } else {
        output_csv($filename, $headers, $rows);
    }
} else {
    json_error('未知的导出类型', 400);
}
