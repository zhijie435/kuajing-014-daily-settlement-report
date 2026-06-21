<?php
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../autoload.php';

use App\Services\WithholdingCalculator;
use App\Models\WithholdingFormula;
use App\Models\OperationLog;

$pdo = getDbConnection();
$calculator = new WithholdingCalculator();
$formulaModel = new WithholdingFormula();
$logModel = new OperationLog();

$method = $_SERVER['REQUEST_METHOD'];
$input = get_json_input();
$id = get_param('id', '');
$action = get_param('action', '');

switch ($method) {
    case 'GET':
        if ($action === 'validate') {
            $formula = get_param('formula', '');
            
            $errors = [];
            
            if (trim($formula) === '') {
                $errors[] = '公式不能为空';
            }
            
            if (empty($errors)) {
                $result = $calculator->validateFormula($formula);
                if (!$result['valid']) {
                    $errors = array_merge($errors, $result['errors']);
                }
            }
            
            if (!empty($errors)) {
                json_error('公式校验失败，共 ' . count($errors) . ' 处错误', 422, null, $errors);
            }
            
            json_success([
                'valid' => true,
                'variables' => $result['variables'] ?? [],
            ], '公式校验通过');
        }
        
        if ($action === 'enabled_list') {
            $list = $formulaModel->where('is_enabled = 1', [], 'sort_order ASC, id DESC');
            foreach ($list as &$item) {
                if (!empty($item['variables'])) {
                    $item['variables'] = json_decode($item['variables'], true) ?: [];
                }
            }
            json_success(['list' => $list, 'total' => count($list)]);
        }
        
        if ($id !== '') {
            $formula = $formulaModel->find((int)$id);
            if (!$formula) {
                json_error('公式不存在', 404, null, ['公式ID ' . $id . ' 不存在']);
            }
            if (!empty($formula['variables'])) {
                $formula['variables'] = json_decode($formula['variables'], true) ?: [];
            }
            
            $validation = $calculator->validateFormula($formula['formula']);
            $formula['validation'] = $validation;
            
            json_success($formula);
        }
        
        $page = (int)get_param('page', 1);
        $pageSize = (int)get_param('pageSize', 20);
        $keyword = get_param('keyword', '');
        $isEnabled = get_param('is_enabled', '');
        
        $safeParams = safe_page_params($page, $pageSize);
        $page = $safeParams['page'];
        $pageSize = $safeParams['pageSize'];
        
        $where = [];
        $params = [];
        
        if ($keyword !== '') {
            $where[] = '(formula_code LIKE ? OR formula_name LIKE ? OR formula LIKE ?)';
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
        }
        
        if ($isEnabled !== '') {
            $where[] = 'is_enabled = ?';
            $params[] = (int)$isEnabled;
        }
        
        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $countSql = "SELECT COUNT(*) AS total FROM {$formulaModel->getTable()} {$whereSql}";
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetch()['total'];
        
        $offset = ($page - 1) * $pageSize;
        
        $listSql = "SELECT * FROM {$formulaModel->getTable()} {$whereSql} ORDER BY sort_order ASC, id DESC LIMIT ? OFFSET ?";
        $stmt = $pdo->prepare($listSql);
        $execParams = array_merge($params, [$pageSize, $offset]);
        $stmt->execute($execParams);
        $list = $stmt->fetchAll();
        
        foreach ($list as &$item) {
            if (!empty($item['variables'])) {
                $item['variables'] = json_decode($item['variables'], true) ?: [];
            }
        }
        
        json_success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'total_pages' => (int)ceil($total / $pageSize),
        ]);
        break;
        
    case 'POST':
        $postAction = $input['action'] ?? $action;
        
        if ($postAction === 'validate') {
            $formula = $input['formula'] ?? '';
            $variables = $input['variables'] ?? [];
            
            $errors = [];
            
            if (trim($formula) === '') {
                $errors[] = '公式不能为空';
            }
            
            if (!is_array($variables)) {
                $errors[] = '变量配置格式错误，必须是数组';
            }
            
            if (empty($errors)) {
                $result = $calculator->validateFormula($formula);
                if (!$result['valid']) {
                    $errors = array_merge($errors, $result['errors']);
                } else {
                    $definedVarNames = [];
                    if (is_array($variables)) {
                        foreach ($variables as $varDef) {
                            if (isset($varDef['name'])) {
                                $definedVarNames[] = $varDef['name'];
                            }
                        }
                    }
                    
                    $extractedVars = $result['variables'] ?? [];
                    foreach ($extractedVars as $varName) {
                        if (!in_array($varName, $definedVarNames, true)) {
                            $errors[] = "公式中使用的变量 '{$varName}' 未在变量配置中定义";
                        }
                    }
                    
                    foreach ($definedVarNames as $varName) {
                        if (!in_array($varName, $extractedVars, true)) {
                            $errors[] = "变量配置中定义的 '{$varName}' 未在公式中使用";
                        }
                    }
                }
            }
            
            if (!empty($errors)) {
                json_error('公式校验失败，共 ' . count($errors) . ' 处错误', 422, null, $errors);
            }
            
            json_success([
                'valid' => true,
                'variables' => $result['variables'] ?? [],
            ], '公式校验通过');
        }
        
        if ($postAction === 'create' || $postAction === '') {
            $formulaCode = trim($input['formula_code'] ?? '');
            $formulaName = trim($input['formula_name'] ?? '');
            $formula = trim($input['formula'] ?? '');
            $variables = $input['variables'] ?? [];
            $description = trim($input['description'] ?? '');
            $sortOrder = (int)($input['sort_order'] ?? 0);
            $isEnabled = (int)($input['is_enabled'] ?? 1);
            
            $errors = [];
            
            if ($formulaCode === '') {
                $errors[] = '公式编码不能为空';
            } elseif (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,49}$/', $formulaCode)) {
                $errors[] = '公式编码必须以字母开头，只能包含字母、数字和下划线，长度不超过50个字符';
            } else {
                $existing = $formulaModel->findByCode($formulaCode);
                if ($existing) {
                    $errors[] = "公式编码 '{$formulaCode}' 已存在";
                }
            }
            
            if ($formulaName === '') {
                $errors[] = '公式名称不能为空';
            } elseif (mb_strlen($formulaName) > 100) {
                $errors[] = '公式名称长度不能超过100个字符';
            }
            
            if (trim($formula) === '') {
                $errors[] = '公式表达式不能为空';
            }
            
            if (!is_array($variables)) {
                $errors[] = '变量配置格式错误，必须是数组';
            } else {
                $varNames = [];
                foreach ($variables as $index => $varDef) {
                    if (!is_array($varDef)) {
                        $errors[] = "第 " . ($index + 1) . " 个变量配置格式错误";
                        continue;
                    }
                    if (!isset($varDef['name']) || trim($varDef['name']) === '') {
                        $errors[] = "第 " . ($index + 1) . " 个变量缺少变量名";
                    } elseif (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,49}$/', $varDef['name'])) {
                        $errors[] = "变量 '{$varDef['name']}' 命名不合法，必须以字母或下划线开头，只能包含字母、数字和下划线";
                    } else {
                        if (in_array($varDef['name'], $varNames, true)) {
                            $errors[] = "变量名 '{$varDef['name']}' 重复定义";
                        }
                        $varNames[] = $varDef['name'];
                    }
                    if (!isset($varDef['label']) || trim($varDef['label']) === '') {
                        $errors[] = "变量 '" . ($varDef['name'] ?? ('第' . ($index + 1) . '个')) . "' 缺少显示名称";
                    }
                }
            }
            
            if (empty($errors) && trim($formula) !== '') {
                $result = $calculator->validateFormula($formula);
                if (!$result['valid']) {
                    $errors = array_merge($errors, $result['errors']);
                } else {
                    $definedVarNames = [];
                    if (is_array($variables)) {
                        foreach ($variables as $varDef) {
                            if (isset($varDef['name'])) {
                                $definedVarNames[] = $varDef['name'];
                            }
                        }
                    }
                    
                    $extractedVars = $result['variables'] ?? [];
                    foreach ($extractedVars as $varName) {
                        if (!in_array($varName, $definedVarNames, true)) {
                            $errors[] = "公式中使用的变量 '{$varName}' 未在变量配置中定义";
                        }
                    }
                }
            }
            
            if (!empty($errors)) {
                json_error('参数校验失败，共 ' . count($errors) . ' 处错误', 422, null, $errors);
            }
            
            try {
                $formulaId = $formulaModel->create([
                    'formula_code' => $formulaCode,
                    'formula_name' => $formulaName,
                    'formula' => $formula,
                    'variables' => json_encode($variables, JSON_UNESCAPED_UNICODE),
                    'description' => $description,
                    'sort_order' => $sortOrder,
                    'is_enabled' => $isEnabled,
                ]);
                
                $saved = $formulaModel->find($formulaId);
                if (!empty($saved['variables'])) {
                    $saved['variables'] = json_decode($saved['variables'], true) ?: [];
                }
                
                $logModel->log(
                    'withholding_formula',
                    'create',
                    'withholding_formula',
                    $formulaId,
                    null,
                    $saved,
                    null,
                    0,
                    null,
                    $input['operator'] ?? 'system',
                    '',
                    get_client_ip(),
                    get_user_agent()
                );
                
                json_success($saved, '创建成功');
            } catch (\Throwable $e) {
                json_error('创建失败: ' . $e->getMessage(), 500, null, [$e->getMessage()]);
            }
        }
        
        if ($postAction === 'toggle' && $id !== '') {
            $formula = $formulaModel->find((int)$id);
            if (!$formula) {
                json_error('公式不存在', 404, null, ['公式ID ' . $id . ' 不存在']);
            }
            
            $newStatus = (int)$formula['is_enabled'] === 1 ? 0 : 1;
            $oldStatus = (int)$formula['is_enabled'];
            
            try {
                $formulaModel->update((int)$id, ['is_enabled' => $newStatus]);
                
                $logModel->log(
                    'withholding_formula',
                    'status_change',
                    'withholding_formula',
                    (int)$id,
                    ['is_enabled' => $oldStatus],
                    ['is_enabled' => $newStatus],
                    null,
                    0,
                    null,
                    $input['operator'] ?? 'system',
                    '',
                    get_client_ip(),
                    get_user_agent()
                );
                
                $saved = $formulaModel->find((int)$id);
                if (!empty($saved['variables'])) {
                    $saved['variables'] = json_decode($saved['variables'], true) ?: [];
                }
                
                json_success($saved, ($newStatus === 1 ? '已启用' : '已禁用'));
            } catch (\Throwable $e) {
                json_error('操作失败: ' . $e->getMessage(), 500, null, [$e->getMessage()]);
            }
        }
        
        json_error('不支持的操作', 400, null, ['操作类型不支持']);
        break;
        
    case 'PUT':
        if ($id === '') {
            json_error('缺少公式ID', 400, null, ['请指定要更新的公式ID']);
        }
        
        $formula = $formulaModel->find((int)$id);
        if (!$formula) {
            json_error('公式不存在', 404, null, ['公式ID ' . $id . ' 不存在']);
        }
        
        $oldData = $formula;
        
        $formulaCode = trim($input['formula_code'] ?? $formula['formula_code']);
        $formulaName = trim($input['formula_name'] ?? $formula['formula_name']);
        $formulaExpr = trim($input['formula'] ?? $formula['formula']);
        $variables = $input['variables'] ?? (json_decode($formula['variables'], true) ?: []);
        $description = trim($input['description'] ?? $formula['description']);
        $sortOrder = (int)($input['sort_order'] ?? $formula['sort_order']);
        $isEnabled = (int)($input['is_enabled'] ?? $formula['is_enabled']);
        
        $errors = [];
        
        if ($formulaCode === '') {
            $errors[] = '公式编码不能为空';
        } elseif (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,49}$/', $formulaCode)) {
            $errors[] = '公式编码必须以字母开头，只能包含字母、数字和下划线，长度不超过50个字符';
        } else {
            $existing = $formulaModel->findByCode($formulaCode);
            if ($existing && (int)$existing['id'] !== (int)$id) {
                $errors[] = "公式编码 '{$formulaCode}' 已存在";
            }
        }
        
        if ($formulaName === '') {
            $errors[] = '公式名称不能为空';
        } elseif (mb_strlen($formulaName) > 100) {
            $errors[] = '公式名称长度不能超过100个字符';
        }
        
        if (trim($formulaExpr) === '') {
            $errors[] = '公式表达式不能为空';
        }
        
        if (!is_array($variables)) {
            $errors[] = '变量配置格式错误，必须是数组';
        } else {
            $varNames = [];
            foreach ($variables as $index => $varDef) {
                if (!is_array($varDef)) {
                    $errors[] = "第 " . ($index + 1) . " 个变量配置格式错误";
                    continue;
                }
                if (!isset($varDef['name']) || trim($varDef['name']) === '') {
                    $errors[] = "第 " . ($index + 1) . " 个变量缺少变量名";
                } elseif (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,49}$/', $varDef['name'])) {
                    $errors[] = "变量 '{$varDef['name']}' 命名不合法，必须以字母或下划线开头，只能包含字母、数字和下划线";
                } else {
                    if (in_array($varDef['name'], $varNames, true)) {
                        $errors[] = "变量名 '{$varDef['name']}' 重复定义";
                    }
                    $varNames[] = $varDef['name'];
                }
                if (!isset($varDef['label']) || trim($varDef['label']) === '') {
                    $errors[] = "变量 '" . ($varDef['name'] ?? ('第' . ($index + 1) . '个')) . "' 缺少显示名称";
                }
            }
        }
        
        if (empty($errors) && trim($formulaExpr) !== '') {
            $result = $calculator->validateFormula($formulaExpr);
            if (!$result['valid']) {
                $errors = array_merge($errors, $result['errors']);
            } else {
                $definedVarNames = [];
                if (is_array($variables)) {
                    foreach ($variables as $varDef) {
                        if (isset($varDef['name'])) {
                            $definedVarNames[] = $varDef['name'];
                        }
                    }
                }
                
                $extractedVars = $result['variables'] ?? [];
                foreach ($extractedVars as $varName) {
                    if (!in_array($varName, $definedVarNames, true)) {
                        $errors[] = "公式中使用的变量 '{$varName}' 未在变量配置中定义";
                    }
                }
            }
        }
        
        if (!empty($errors)) {
            json_error('参数校验失败，共 ' . count($errors) . ' 处错误', 422, null, $errors);
        }
        
        try {
            $formulaModel->update((int)$id, [
                'formula_code' => $formulaCode,
                'formula_name' => $formulaName,
                'formula' => $formulaExpr,
                'variables' => json_encode($variables, JSON_UNESCAPED_UNICODE),
                'description' => $description,
                'sort_order' => $sortOrder,
                'is_enabled' => $isEnabled,
            ]);
            
            $saved = $formulaModel->find((int)$id);
            if (!empty($saved['variables'])) {
                $saved['variables'] = json_decode($saved['variables'], true) ?: [];
            }
            
            $logModel->log(
                'withholding_formula',
                'update',
                'withholding_formula',
                (int)$id,
                $oldData,
                $saved,
                null,
                0,
                null,
                $input['operator'] ?? 'system',
                '',
                get_client_ip(),
                get_user_agent()
            );
            
            json_success($saved, '更新成功');
        } catch (\Throwable $e) {
            json_error('更新失败: ' . $e->getMessage(), 500, null, [$e->getMessage()]);
        }
        break;
        
    case 'DELETE':
        if ($id === '') {
            json_error('缺少公式ID', 400, null, ['请指定要删除的公式ID']);
        }
        
        $formula = $formulaModel->find((int)$id);
        if (!$formula) {
            json_error('公式不存在', 404, null, ['公式ID ' . $id . ' 不存在']);
        }
        
        try {
            $formulaModel->delete((int)$id);
            
            $logModel->log(
                'withholding_formula',
                'delete',
                'withholding_formula',
                (int)$id,
                $formula,
                null,
                null,
                0,
                null,
                $input['operator'] ?? 'system',
                '',
                get_client_ip(),
                get_user_agent()
            );
            
            json_success(null, '删除成功');
        } catch (\Throwable $e) {
            json_error('删除失败: ' . $e->getMessage(), 500, null, [$e->getMessage()]);
        }
        break;
        
    default:
        json_error('不支持的请求方法', 405, null, ['请求方法 ' . $method . ' 不支持']);
}
