<?php
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../autoload.php';

use App\Models\FundFlow;
use App\Models\WithholdingDetail;
use App\Models\OperationLog;
use App\Services\Database;

$pdo = getDbConnection();
$fundFlowModel = new FundFlow();
$detailModel = new WithholdingDetail();
$logModel = new OperationLog();
$db = Database::getInstance();

$method = $_SERVER['REQUEST_METHOD'];
$input = get_json_input();
$id = get_param('id', '');
$action = get_param('action', '');

$statusLabels = $fundFlowModel->getStatusLabels();
$typeLabels = $fundFlowModel->getTypeLabels();
$directionLabels = $fundFlowModel->getDirectionLabels();

switch ($method) {
    case 'GET':
        if ($action === 'status_types') {
            json_success([
                'labels' => $statusLabels,
                'tag_types' => [
                    FundFlow::STATUS_PENDING => 'warning',
                    FundFlow::STATUS_COMPLETED => 'success',
                    FundFlow::STATUS_FAILED => 'danger',
                    FundFlow::STATUS_CANCELLED => 'default',
                    FundFlow::STATUS_REVERSED => 'info',
                ],
                'type_labels' => $typeLabels,
                'direction_labels' => $directionLabels,
            ]);
        }

        if ($action === 'stats') {
            $currentBalance = $fundFlowModel->getCurrentBalance();
            
            $totalIn = $pdo->query(
                "SELECT COALESCE(SUM(amount), 0) as total FROM {$fundFlowModel->getTable()} WHERE direction = ? AND status = ?",
                [FundFlow::DIRECTION_IN, FundFlow::STATUS_COMPLETED]
            )[0]['total'] ?? 0;
            
            $totalOut = $pdo->query(
                "SELECT COALESCE(SUM(amount), 0) as total FROM {$fundFlowModel->getTable()} WHERE direction = ? AND status = ?",
                [FundFlow::DIRECTION_OUT, FundFlow::STATUS_COMPLETED]
            )[0]['total'] ?? 0;

            $statusStats = [];
            foreach (array_keys($statusLabels) as $status) {
                $count = $pdo->query(
                    "SELECT COUNT(*) as cnt FROM {$fundFlowModel->getTable()} WHERE status = ?",
                    [$status]
                )[0]['cnt'] ?? 0;
                $statusStats[$status] = (int)$count;
            }

            json_success([
                'current_balance' => round((float)$currentBalance, 2),
                'total_in' => round((float)$totalIn, 2),
                'total_out' => round((float)$totalOut, 2),
                'status_stats' => $statusStats,
            ]);
        }

        if ($action === 'logs' && $id !== '') {
            $flow = $fundFlowModel->find((int)$id);
            if (!$flow) {
                json_error('资金流水不存在', 404, null, ['资金流水ID ' . $id . ' 不存在']);
            }

            $logs = $logModel->findByResource('fund_flow', (int)$id);
            foreach ($logs as &$log) {
                if (!empty($log['old_value'])) {
                    $decoded = json_decode($log['old_value'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $log['old_value'] = $decoded;
                    }
                }
                if (!empty($log['new_value'])) {
                    $decoded = json_decode($log['new_value'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $log['new_value'] = $decoded;
                    }
                }
                if (!empty($log['request_params'])) {
                    $decoded = json_decode($log['request_params'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $log['request_params'] = $decoded;
                    }
                }
                $log['action_label'] = $log['action'];
            }

            json_success([
                'list' => $logs,
                'total' => count($logs),
            ]);
        }

        if ($id !== '') {
            $flow = $fundFlowModel->find((int)$id);
            if (!$flow) {
                json_error('资金流水不存在', 404, null, ['资金流水ID ' . $id . ' 不存在']);
            }

            $flow['status_label'] = $fundFlowModel->getStatusLabel((int)$flow['status']);
            $flow['status_tag_type'] = $fundFlowModel->getStatusTagType((int)$flow['status']);
            $flow['type_label'] = $fundFlowModel->getTypeLabel($flow['flow_type']);
            $flow['direction_label'] = $fundFlowModel->getDirectionLabel((int)$flow['direction']);
            $flow['valid_transitions'] = $fundFlowModel->getValidTransitions((int)$flow['status']);

            if (!empty($flow['withholding_detail_id'])) {
                $detail = $detailModel->find((int)$flow['withholding_detail_id']);
                if ($detail) {
                    if (!empty($detail['variables'])) {
                        $detail['variables'] = json_decode($detail['variables'], true) ?: [];
                    }
                    $detail['status_label'] = $detailModel->getStatusLabel((int)$detail['status']);
                    $detail['status_tag_type'] = $detailModel->getStatusTagType((int)$detail['status']);
                    $flow['withholding_detail'] = $detail;
                }
            }

            $logs = $logModel->findByResource('fund_flow', (int)$id);
            foreach ($logs as &$log) {
                if (!empty($log['old_value'])) {
                    $decoded = json_decode($log['old_value'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $log['old_value'] = $decoded;
                    }
                }
                if (!empty($log['new_value'])) {
                    $decoded = json_decode($log['new_value'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $log['new_value'] = $decoded;
                    }
                }
            }
            $flow['operation_logs'] = $logs;

            json_success($flow);
        }

        $page = (int)get_param('page', 1);
        $pageSize = (int)get_param('pageSize', 20);
        $status = get_param('status', '');
        $flowType = get_param('flow_type', '');
        $direction = get_param('direction', '');
        $keyword = get_param('keyword', '');
        $minAmount = get_param('min_amount', '');
        $maxAmount = get_param('max_amount', '');
        $withholdingDetailId = get_param('withholding_detail_id', '');
        $startDate = get_param('start_date', '');
        $endDate = get_param('end_date', '');

        $safeParams = safe_page_params($page, $pageSize);
        $page = $safeParams['page'];
        $pageSize = $safeParams['pageSize'];

        validate_date($startDate, '开始日期');
        validate_date($endDate, '结束日期');
        validate_date_range($startDate, $endDate);

        $where = [];
        $params = [];

        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = (int)$status;
        }

        if ($flowType !== '') {
            $where[] = 'flow_type = ?';
            $params[] = $flowType;
        }

        if ($direction !== '') {
            $where[] = 'direction = ?';
            $params[] = (int)$direction;
        }

        if ($keyword !== '') {
            $where[] = '(flow_no LIKE ? OR order_no LIKE ? OR remark LIKE ?)';
            $keywordParam = "%{$keyword}%";
            $params[] = $keywordParam;
            $params[] = $keywordParam;
            $params[] = $keywordParam;
        }

        if ($minAmount !== '') {
            $where[] = 'amount >= ?';
            $params[] = (float)$minAmount;
        }

        if ($maxAmount !== '') {
            $where[] = 'amount <= ?';
            $params[] = (float)$maxAmount;
        }

        if ($withholdingDetailId !== '') {
            $where[] = 'withholding_detail_id = ?';
            $params[] = (int)$withholdingDetailId;
        }

        if ($startDate !== '') {
            $where[] = 'DATE(created_at) >= ?';
            $params[] = $startDate;
        }

        if ($endDate !== '') {
            $where[] = 'DATE(created_at) <= ?';
            $params[] = $endDate;
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) AS total FROM {$fundFlowModel->getTable()} {$whereSql}";
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetch()['total'];

        $offset = ($page - 1) * $pageSize;

        $listSql = "SELECT * FROM {$fundFlowModel->getTable()} {$whereSql} ORDER BY id DESC LIMIT ? OFFSET ?";
        $stmt = $pdo->prepare($listSql);
        $execParams = array_merge($params, [$pageSize, $offset]);
        $stmt->execute($execParams);
        $list = $stmt->fetchAll();

        foreach ($list as &$item) {
            $item['status_label'] = $fundFlowModel->getStatusLabel((int)$item['status']);
            $item['status_tag_type'] = $fundFlowModel->getStatusTagType((int)$item['status']);
            $item['type_label'] = $fundFlowModel->getTypeLabel($item['flow_type']);
            $item['direction_label'] = $fundFlowModel->getDirectionLabel((int)$item['direction']);
        }

        json_success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'total_pages' => (int)ceil($total / $pageSize),
        ]);
        break;

    case 'PUT':
        $putAction = $input['action'] ?? '';

        if ($putAction === 'status' && $id !== '') {
            $flow = $fundFlowModel->find((int)$id);
            if (!$flow) {
                json_error('资金流水不存在', 404, null, ['资金流水ID ' . $id . ' 不存在']);
            }

            $newStatus = (int)($input['status'] ?? 0);
            $oldStatus = (int)$flow['status'];
            $remark = $input['remark'] ?? '';
            $operator = $input['operator'] ?? 'system';

            if (!$fundFlowModel->canTransition($oldStatus, $newStatus)) {
                $oldLabel = $statusLabels[$oldStatus] ?? '未知';
                $newLabel = $statusLabels[$newStatus] ?? '未知';
                json_error("不允许从[{$oldLabel}]变更为[{$newLabel}]", 400, null, ["无法从 [{$oldLabel}] 变更为 [{$newLabel}]"]);
            }

            $db->beginTransaction();
            try {
                $oldCompleted = $oldStatus === FundFlow::STATUS_COMPLETED;
                $newCompleted = $newStatus === FundFlow::STATUS_COMPLETED;

                $balanceAdjust = 0.0;
                if ($oldCompleted !== $newCompleted) {
                    $amount = (float)$flow['amount'];
                    $direction = (int)$flow['direction'];
                    $sign = $direction === FundFlow::DIRECTION_IN ? 1 : -1;
                    $balanceAdjust = $newCompleted ? ($sign * $amount) : (-$sign * $amount);
                }

                $fundFlowModel->update((int)$id, [
                    'status' => $newStatus,
                    'remark' => $remark ?: $flow['remark'],
                ]);

                if ($balanceAdjust !== 0.0) {
                    $adjustSql = "UPDATE {$fundFlowModel->getTable()} SET balance = balance + ? WHERE id > ?";
                    $stmt = $pdo->prepare($adjustSql);
                    $stmt->execute([round($balanceAdjust, 2), (int)$id]);
                }

                $logModel->log(
                    'fund_flow',
                    'status_change',
                    'fund_flow',
                    (int)$id,
                    ['status' => $oldStatus, 'status_label' => $statusLabels[$oldStatus] ?? '未知'],
                    ['status' => $newStatus, 'status_label' => $statusLabels[$newStatus] ?? '未知', 'balance_adjust' => round($balanceAdjust, 2)],
                    null,
                    0,
                    null,
                    $operator,
                    $remark,
                    get_client_ip(),
                    get_user_agent()
                );

                $db->commit();

                $saved = $fundFlowModel->find((int)$id);
                $saved['status_label'] = $fundFlowModel->getStatusLabel((int)$saved['status']);
                $saved['status_tag_type'] = $fundFlowModel->getStatusTagType((int)$saved['status']);
                $saved['type_label'] = $fundFlowModel->getTypeLabel($saved['flow_type']);
                $saved['direction_label'] = $fundFlowModel->getDirectionLabel((int)$saved['direction']);

                json_success($saved, '状态更新成功');
            } catch (\Throwable $e) {
                $db->rollBack();
                json_error('状态更新失败: ' . $e->getMessage(), 500, null, [$e->getMessage()]);
            }
        }

        if ($putAction === 'remark' && $id !== '') {
            $flow = $fundFlowModel->find((int)$id);
            if (!$flow) {
                json_error('资金流水不存在', 404, null, ['资金流水ID ' . $id . ' 不存在']);
            }

            $remark = $input['remark'] ?? '';
            $operator = $input['operator'] ?? 'system';
            $oldRemark = $flow['remark'] ?? '';

            $newRemark = $oldRemark ? ($oldRemark . "\n" . $remark) : $remark;

            $fundFlowModel->update((int)$id, ['remark' => $newRemark]);

            $logModel->log(
                'fund_flow',
                'remark',
                'fund_flow',
                (int)$id,
                ['remark' => $oldRemark],
                ['remark' => $newRemark],
                null,
                0,
                null,
                $operator,
                '',
                get_client_ip(),
                get_user_agent()
            );

            json_success(null, '备注更新成功');
        }

        json_error('不支持的操作', 400, null, ['操作类型不支持']);
        break;

    default:
        json_error('不支持的请求方法', 405, null, ['请求方法 ' . $method . ' 不支持']);
}
