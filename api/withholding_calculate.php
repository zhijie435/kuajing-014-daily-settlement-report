<?php
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../autoload.php';

use App\Services\WithholdingCalculator;
use App\Models\WithholdingFormula;
use App\Models\WithholdingDetail;
use App\Models\FundFlow;
use App\Models\OperationLog;
use App\Services\Database;
use App\Exceptions\FormulaException;

$pdo = getDbConnection();
$calculator = new WithholdingCalculator();
$formulaModel = new WithholdingFormula();
$detailModel = new WithholdingDetail();
$fundFlowModel = new FundFlow();
$logModel = new OperationLog();
$db = Database::getInstance();

$method = $_SERVER['REQUEST_METHOD'];
$input = get_json_input();
$id = get_param('id', '');
$action = get_param('action', '');

$detailStatusLabels = $detailModel->getStatusLabels();
$fundStatusLabels = $fundFlowModel->getStatusLabels();

switch ($method) {
    case 'GET':
        if ($action === 'status_types') {
            json_success([
                'labels' => $detailStatusLabels,
                'tag_types' => [
                    WithholdingDetail::STATUS_PENDING => 'warning',
                    WithholdingDetail::STATUS_COMPLETED => 'success',
                    WithholdingDetail::STATUS_FAILED => 'danger',
                    WithholdingDetail::STATUS_CANCELLED => 'default',
                    WithholdingDetail::STATUS_REVERSED => 'info',
                    WithholdingDetail::STATUS_SETTLED => 'primary',
                ],
            ]);
        }

        if ($action === 'logs' && $id !== '') {
            $detail = $detailModel->find((int)$id);
            if (!$detail) {
                json_error('预扣明细不存在', 404, null, ['预扣明细ID ' . $id . ' 不存在']);
            }

            $logs = $logModel->findByResource('withholding_detail', (int)$id);
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
            $detail = $detailModel->find((int)$id);
            if (!$detail) {
                json_error('预扣明细不存在', 404, null, ['预扣明细ID ' . $id . ' 不存在']);
            }
            if (!empty($detail['variables'])) {
                $detail['variables'] = json_decode($detail['variables'], true) ?: [];
            }

            $detail['status_label'] = $detailModel->getStatusLabel((int)$detail['status']);
            $detail['status_tag_type'] = $detailModel->getStatusTagType((int)$detail['status']);
            $detail['valid_transitions'] = $detailModel->getValidTransitions((int)$detail['status']);

            $fundFlows = $fundFlowModel->where('withholding_detail_id = ?', [(int)$id], 'id DESC');
            foreach ($fundFlows as &$ff) {
                $ff['status_label'] = $fundFlowModel->getStatusLabel((int)$ff['status']);
                $ff['status_tag_type'] = $fundFlowModel->getStatusTagType((int)$ff['status']);
                $ff['type_label'] = $fundFlowModel->getTypeLabel($ff['flow_type']);
                $ff['direction_label'] = $fundFlowModel->getDirectionLabel((int)$ff['direction']);
            }
            $detail['fund_flows'] = $fundFlows;

            $logs = $logModel->findByResource('withholding_detail', (int)$id);
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
            }
            $detail['operation_logs'] = $logs;

            json_success($detail);
        }

        $page = (int)get_param('page', 1);
        $pageSize = (int)get_param('pageSize', 20);
        $formulaId = (int)get_param('formula_id', 0);
        $formulaCode = get_param('formula_code', '');
        $orderNo = get_param('order_no', '');
        $status = get_param('status', '');

        $safeParams = safe_page_params($page, $pageSize);
        $page = $safeParams['page'];
        $pageSize = $safeParams['pageSize'];

        $where = [];
        $params = [];

        if ($formulaId > 0) {
            $where[] = 'formula_id = ?';
            $params[] = $formulaId;
        }

        if ($formulaCode !== '') {
            $where[] = 'formula_code = ?';
            $params[] = $formulaCode;
        }

        if ($orderNo !== '') {
            $where[] = 'order_no LIKE ?';
            $params[] = "%{$orderNo}%";
        }

        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = (int)$status;
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) AS total FROM {$detailModel->getTable()} {$whereSql}";
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetch()['total'];

        $offset = ($page - 1) * $pageSize;

        $listSql = "SELECT * FROM {$detailModel->getTable()} {$whereSql} ORDER BY id DESC LIMIT ? OFFSET ?";
        $stmt = $pdo->prepare($listSql);
        $execParams = array_merge($params, [$pageSize, $offset]);
        $stmt->execute($execParams);
        $list = $stmt->fetchAll();

        foreach ($list as &$item) {
            if (!empty($item['variables'])) {
                $item['variables'] = json_decode($item['variables'], true) ?: [];
            }
            $item['status_label'] = $detailModel->getStatusLabel((int)$item['status']);
            $item['status_tag_type'] = $detailModel->getStatusTagType((int)$item['status']);
        }

        $statusStats = [];
        foreach (array_keys($detailStatusLabels) as $s) {
            $cnt = $pdo->query(
                "SELECT COUNT(*) as cnt FROM {$detailModel->getTable()} WHERE status = ?",
                [$s]
            )[0]['cnt'] ?? 0;
            $statusStats[$s] = (int)$cnt;
        }

        json_success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'total_pages' => (int)ceil($total / $pageSize),
            'status_stats' => $statusStats,
        ]);
        break;

    case 'POST':
        $postAction = $input['action'] ?? $action;

        if ($postAction === 'preview') {
            $formulaCode = $input['formula_code'] ?? '';
            $variables = $input['variables'] ?? [];

            $errors = [];

            if (empty($formulaCode)) {
                $errors[] = '公式编码不能为空';
            }

            if (!is_array($variables)) {
                $errors[] = '变量参数格式错误，必须是数组';
            }

            if (!empty($formulaCode)) {
                $formula = $formulaModel->findByCode($formulaCode);
                if (!$formula) {
                    $errors[] = "公式编码 '{$formulaCode}' 不存在";
                } elseif (!(int)$formula['is_enabled']) {
                    $errors[] = "公式 '{$formula['formula_name']}' 未启用，无法执行计算";
                } else {
                    $definedVars = [];
                    if (!empty($formula['variables'])) {
                        $decoded = json_decode($formula['variables'], true);
                        if (is_array($decoded)) {
                            foreach ($decoded as $varDef) {
                                if (isset($varDef['name'])) {
                                    $definedVars[$varDef['name']] = $varDef;
                                }
                            }
                        }
                    }

                    $expectedVars = $calculator->extractVariables($formula['formula']);
                    foreach ($expectedVars as $varName) {
                        if (!array_key_exists($varName, $variables) || $variables[$varName] === '' || $variables[$varName] === null) {
                            if (!isset($definedVars[$varName]['default'])) {
                                $varLabel = $definedVars[$varName]['label'] ?? $varName;
                                $errors[] = "变量 '{$varName}' ({$varLabel}) 的值不能为空";
                            }
                        } elseif (!is_numeric($variables[$varName])) {
                            $varLabel = $definedVars[$varName]['label'] ?? $varName;
                            $errors[] = "变量 '{$varName}' ({$varLabel}) 的值必须是数字";
                        }
                    }
                }
            }

            if (is_array($variables)) {
                foreach ($variables as $name => $value) {
                    if ($value !== '' && $value !== null && !is_numeric($value)) {
                        $errors[] = "变量 '{$name}' 的值必须是数字";
                    }
                }
            }

            if (!empty($errors)) {
                json_error('参数校验失败，共 ' . count($errors) . ' 处错误', 422, null, $errors);
            }

            try {
                $result = $calculator->calculate($formulaCode, $variables, ['preview' => true]);
                json_success($result, '预览成功');
            } catch (FormulaException $e) {
                json_error('预览失败: ' . $e->getMessage(), $e->getCode() ?: 1, null, [$e->getMessage()]);
            } catch (\Throwable $e) {
                json_error('预览失败: ' . $e->getMessage(), 1, null, [$e->getMessage()]);
            }
        }

        if ($postAction === 'calculate') {
            $formulaCode = $input['formula_code'] ?? '';
            $variables = $input['variables'] ?? [];
            $orderNo = $input['order_no'] ?? '';
            $operator = $input['operator'] ?? 'system';
            $remark = $input['remark'] ?? '';
            $initialStatus = isset($input['initial_status']) ? (int)$input['initial_status'] : null;

            $errors = [];

            if (empty($formulaCode)) {
                $errors[] = '公式编码不能为空';
            }

            if (empty($operator)) {
                $errors[] = '操作人不能为空';
            }

            if (!is_array($variables)) {
                $errors[] = '变量参数格式错误，必须是数组';
            }

            if (!empty($formulaCode)) {
                $formula = $formulaModel->findByCode($formulaCode);
                if (!$formula) {
                    $errors[] = "公式编码 '{$formulaCode}' 不存在";
                } elseif (!(int)$formula['is_enabled']) {
                    $errors[] = "公式 '{$formula['formula_name']}' 未启用，无法执行计算";
                } else {
                    $definedVars = [];
                    if (!empty($formula['variables'])) {
                        $decoded = json_decode($formula['variables'], true);
                        if (is_array($decoded)) {
                            foreach ($decoded as $varDef) {
                                if (isset($varDef['name'])) {
                                    $definedVars[$varDef['name']] = $varDef;
                                }
                            }
                        }
                    }

                    $expectedVars = $calculator->extractVariables($formula['formula']);
                    foreach ($expectedVars as $varName) {
                        if (!array_key_exists($varName, $variables) || $variables[$varName] === '' || $variables[$varName] === null) {
                            if (!isset($definedVars[$varName]['default'])) {
                                $varLabel = $definedVars[$varName]['label'] ?? $varName;
                                $errors[] = "变量 '{$varName}' ({$varLabel}) 的值不能为空";
                            }
                        } elseif (!is_numeric($variables[$varName])) {
                            $varLabel = $definedVars[$varName]['label'] ?? $varName;
                            $errors[] = "变量 '{$varName}' ({$varLabel}) 的值必须是数字";
                        }
                    }
                }
            }

            if (is_array($variables)) {
                foreach ($variables as $name => $value) {
                    if ($value !== '' && $value !== null && !is_numeric($value)) {
                        $errors[] = "变量 '{$name}' 的值必须是数字";
                    }
                }
            }

            if (!empty($errors)) {
                json_error('参数校验失败，共 ' . count($errors) . ' 处错误', 422, null, $errors);
            }

            try {
                $options = [
                    'preview' => false,
                    'order_no' => $orderNo,
                    'operator' => $operator,
                    'remark' => $remark,
                ];
                if ($initialStatus !== null) {
                    $options['initial_status'] = $initialStatus;
                }

                $result = $calculator->calculate($formulaCode, $variables, $options);

                if (!empty($result['detail_id'])) {
                    $logModel->log(
                        'withholding',
                        'create',
                        'withholding_detail',
                        (int)$result['detail_id'],
                        null,
                        $result,
                        null,
                        0,
                        null,
                        $operator,
                        $remark,
                        get_client_ip(),
                        get_user_agent()
                    );
                }

                json_success($result, '计算成功');
            } catch (FormulaException $e) {
                json_error('计算失败: ' . $e->getMessage(), $e->getCode() ?: 1, null, [$e->getMessage()]);
            } catch (\Throwable $e) {
                json_error('计算失败: ' . $e->getMessage(), 1, null, [$e->getMessage()]);
            }
        }

        if ($postAction === 'batch_calculate') {
            $items = $input['items'] ?? [];

            if (!is_array($items) || empty($items)) {
                json_error('批量计算数据不能为空', 400, null, ['请至少提供一条计算数据']);
            }

            $maxBatch = (int)($GLOBALS['WITHHOLDING_MAX_BATCH_SIZE'] ?? 100);
            if (count($items) > $maxBatch) {
                json_error("批量计算超过最大条数: {$maxBatch}", 400, null, ["批量计算最多支持 {$maxBatch} 条"]);
            }

            $results = [];
            $hasError = false;

            foreach ($items as $index => $item) {
                $itemErrors = [];

                $formulaCode = $item['formula_code'] ?? '';
                $variables = $item['variables'] ?? [];
                $orderNo = $item['order_no'] ?? '';
                $operator = $item['operator'] ?? 'system';
                $remark = $item['remark'] ?? '';

                if (empty($formulaCode)) {
                    $itemErrors[] = '公式编码不能为空';
                }

                if (!is_array($variables)) {
                    $itemErrors[] = '变量参数格式错误，必须是数组';
                }

                if (!empty($formulaCode) && empty($itemErrors)) {
                    $formula = $formulaModel->findByCode($formulaCode);
                    if (!$formula) {
                        $itemErrors[] = "公式编码 '{$formulaCode}' 不存在";
                    } elseif (!(int)$formula['is_enabled']) {
                        $itemErrors[] = "公式 '{$formula['formula_name']}' 未启用";
                    }
                }

                if (!empty($itemErrors)) {
                    $hasError = true;
                    $results[] = [
                        'index' => $index,
                        'success' => false,
                        'error' => implode('; ', $itemErrors),
                        'errors' => $itemErrors,
                    ];
                    continue;
                }

                try {
                    $result = $calculator->calculate($formulaCode, $variables, [
                        'preview' => false,
                        'order_no' => $orderNo,
                        'operator' => $operator,
                        'remark' => $remark,
                    ]);

                    if (!empty($result['detail_id'])) {
                        $logModel->log(
                            'withholding',
                            'create',
                            'withholding_detail',
                            (int)$result['detail_id'],
                            null,
                            $result,
                            null,
                            0,
                            null,
                            $operator,
                            $remark,
                            get_client_ip(),
                            get_user_agent()
                        );
                    }

                    $results[] = [
                        'index' => $index,
                        'success' => true,
                        'data' => $result,
                    ];
                } catch (\Throwable $e) {
                    $hasError = true;
                    $results[] = [
                        'index' => $index,
                        'success' => false,
                        'error' => $e->getMessage(),
                        'errors' => [$e->getMessage()],
                    ];
                }
            }

            $successCount = count(array_filter($results, fn($r) => $r['success']));
            $failCount = count($results) - $successCount;

            json_success([
                'results' => $results,
                'summary' => [
                    'total' => count($results),
                    'success' => $successCount,
                    'failed' => $failCount,
                ],
            ], $failCount > 0 ? "部分计算失败: 成功{$successCount}条, 失败{$failCount}条" : '批量计算成功');
        }

        json_error('不支持的操作', 400, null, ['操作类型不支持']);
        break;

    case 'PUT':
        $putAction = $input['action'] ?? '';

        if ($putAction === 'status' && $id !== '') {
            $detail = $detailModel->find((int)$id);
            if (!$detail) {