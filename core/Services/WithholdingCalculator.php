<?php

namespace App\Services;

use App\Exceptions\FormulaException;
use App\Models\FundFlow;
use App\Models\OperationLog;
use App\Models\WithholdingDetail;
use App\Models\WithholdingFormula;

class WithholdingCalculator
{
    private $formulaModel;
    private $detailModel;
    private $fundFlowModel;
    private $logModel;
    private $db;

    private $unsafePatterns = [
        '/\beval\s*\(/i',
        '/\bexec\s*\(/i',
        '/\bsystem\s*\(/i',
        '/\bshell_exec\s*\(/i',
        '/\bpassthru\s*\(/i',
        '/\bpopen\s*\(/i',
        '/\bproc_open\s*\(/i',
        '/`/',
        '/\binclude\s*\(/i',
        '/\brequire\s*\(/i',
        '/\binclude_once\s*\(/i',
        '/\brequire_once\s*\(/i',
        '/\bfile_get_contents\s*\(/i',
        '/\bfile_put_contents\s*\(/i',
        '/\bfopen\s*\(/i',
        '/\bunlink\s*\(/i',
        '/\brename\s*\(/i',
        '/\bcurl_exec\s*\(/i',
        '/\$_(GET|POST|REQUEST|SERVER|SESSION|COOKIE|FILES|ENV)\b/',
        '/\bphpinfo\s*\(/i',
    ];

    public function __construct()
    {
        $this->formulaModel = new WithholdingFormula();
        $this->detailModel = new WithholdingDetail();
        $this->fundFlowModel = new FundFlow();
        $this->logModel = new OperationLog();
        $this->db = Database::getInstance();
    }

    public function validateFormula(string $formula): array
    {
        $errors = [];

        if (trim($formula) === '') {
            $errors[] = '公式不能为空';
            return ['valid' => false, 'errors' => $errors];
        }

        foreach ($this->unsafePatterns as $pattern) {
            if (preg_match($pattern, $formula)) {
                $errors[] = '公式包含不安全的代码';
                return ['valid' => false, 'errors' => $errors];
            }
        }

        $vars = $this->extractVariables($formula);
        if (empty($vars)) {
            $errors[] = '公式未包含任何变量';
        }

        $testVars = [];
        foreach ($vars as $var) {
            $testVars[$var] = 100;
        }

        try {
            $this->evaluateFormula($formula, $testVars);
        } catch (FormulaException $e) {
            $errors[] = '公式语法错误: ' . $e->getMessage();
        } catch (\Throwable $e) {
            $errors[] = '公式执行错误: ' . $e->getMessage();
        }

        return ['valid' => empty($errors), 'errors' => $errors, 'variables' => $vars];
    }

    public function extractVariables(string $formula): array
    {
        $pattern = '/\b([a-zA-Z_][a-zA-Z0-9_]*)\b/';
        preg_match_all($pattern, $formula, $matches);
        $reserved = ['true', 'false', 'null', 'and', 'or', 'xor', 'not', 'if', 'else', 'elseif', 'while', 'for', 'foreach', 'return', 'break', 'continue', 'switch', 'case', 'default', 'function', 'class', 'new', 'instanceof', 'clone', 'throw', 'try', 'catch', 'finally', 'echo', 'print', 'isset', 'empty', 'count', 'sizeof', 'abs', 'round', 'ceil', 'floor', 'max', 'min', 'intval', 'floatval', 'strval', 'sqrt', 'pow', 'rand', 'mt_rand', 'date', 'time'];
        $variables = array_values(array_diff(array_unique($matches[1]), $reserved));
        sort($variables);
        return $variables;
    }

    public function calculate(string $formulaCode, array $variables, array $options = []): array
    {
        $preview = $options['preview'] ?? false;
        $record = $options['record'] ?? !$preview;
        $orderNo = $options['order_no'] ?? '';
        $operator = $options['operator'] ?? 'system';
        $remark = $options['remark'] ?? '';
        $precision = $options['precision'] ?? (int)($GLOBALS['WITHHOLDING_PRECISION'] ?? 2);
        $allowNegative = $options['allow_negative'] ?? (bool)($GLOBALS['WITHHOLDING_ALLOW_NEGATIVE_RESULT'] ?? false);
        $initialStatus = $options['initial_status'] ?? (int)($GLOBALS['WITHHOLDING_DEFAULT_INITIAL_STATUS'] ?? WithholdingDetail::STATUS_COMPLETED);
        $autoCreateFlow = $options['auto_create_flow'] ?? (bool)($GLOBALS['WITHHOLDING_AUTO_CREATE_FUND_FLOW'] ?? true);

        $formula = $this->formulaModel->findByCode($formulaCode);
        if (!$formula) {
            throw new FormulaException("公式不存在: {$formulaCode}", FormulaException::FORMULA_NOT_FOUND);
        }

        if (!(int)$formula['is_enabled']) {
            throw new FormulaException("公式已禁用: {$formulaCode}", FormulaException::FORMULA_DISABLED);
        }

        $defaultVars = [];
        if (!empty($formula['variables'])) {
            $decoded = json_decode($formula['variables'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $varDef) {
                    if (isset($varDef['name']) && array_key_exists('default', $varDef)) {
                        $defaultVars[$varDef['name']] = $varDef['default'];
                    }
                }
            }
        }
        $variables = array_merge($defaultVars, $variables);

        $expectedVars = $this->extractVariables($formula['formula']);
        $missing = [];
        foreach ($expectedVars as $var) {
            if (!array_key_exists($var, $variables)) {
                $missing[] = $var;
            }
        }
        if (!empty($missing)) {
            throw new FormulaException('缺少变量: ' . implode(', ', $missing), FormulaException::VARIABLE_MISSING);
        }

        foreach ($this->unsafePatterns as $pattern) {
            if (preg_match($pattern, $formula['formula'])) {
                throw new FormulaException('公式包含不安全的代码', FormulaException::UNSAFE_CODE);
            }
        }

        $result = $this->evaluateFormula($formula['formula'], $variables);
        $result = round((float)$result, $precision);

        if (!$allowNegative && $result < 0) {
            throw new FormulaException("计算结果不能为负数: {$result}", FormulaException::NEGATIVE_RESULT);
        }

        $response = [
            'formula_code' => $formulaCode,
            'formula_name' => $formula['formula_name'],
            'formula' => $formula['formula'],
            'variables' => $variables,
            'result' => $result,
        ];

        if (!$preview && $record) {
            $this->db->beginTransaction();
            try {
                $detailId = $this->recordDetail($formula, $variables, $result, $orderNo, $operator, $remark, $initialStatus);
                $response['detail_id'] = $detailId;

                if ($autoCreateFlow && $initialStatus === WithholdingDetail::STATUS_COMPLETED) {
                    $flowId = $this->recordFundFlow($detailId, $result, $orderNo, $operator, $remark);
                    $response['fund_flow_id'] = $flowId;
                }

                $this->db->commit();
            } catch (\Throwable $e) {
                $this->db->rollBack();
                throw $e;
            }
        }

        return $response;
    }

    private function recordDetail(array $formula, array $variables, float $result, string $orderNo, string $operator, string $remark, int $status): int
    {
        return $this->detailModel->create([
            'formula_id' => (int)$formula['id'],
            'formula_code' => $formula['formula_code'],
            'formula_name' => $formula['formula_name'],
            'formula' => $formula['formula'],
            'variables' => json_encode($variables, JSON_UNESCAPED_UNICODE),
            'result' => $result,
            'order_no' => $orderNo,
            'operator' => $operator,
            'remark' => $remark,
            'status' => $status,
        ]);
    }

    private function recordFundFlow(int $detailId, float $amount, string $orderNo, string $operator, string $remark): int
    {
        $balance = $this->fundFlowModel->calculateNewBalance($amount, FundFlow::DIRECTION_OUT);
        return $this->fundFlowModel->create([
            'flow_no' => $this->fundFlowModel->generateFlowNo(),
            'flow_type' => FundFlow::TYPE_WITHHOLD,
            'direction' => FundFlow::DIRECTION_OUT,
            'amount' => $amount,
            'balance' => $balance,
            'currency' => $GLOBALS['FUND_FLOW_DEFAULT_CURRENCY'] ?? 'CNY',
            'withholding_detail_id' => $detailId,
            'order_no' => $orderNo,
            'operator' => $operator,
            'remark' => $remark,
            'status' => FundFlow::STATUS_COMPLETED,
        ]);
    }

    private function evaluateFormula(string $formula, array $variables): float
    {
        $safeFormula = $this->normalizeNestedTernary($formula);

        $phpCode = 'return (float)(' . $safeFormula . ');';

        $sanitizedVars = [];
        foreach ($variables as $name => $value) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
                throw new FormulaException("变量名不合法: {$name}", FormulaException::INVALID_FORMULA);
            }
            if (!is_numeric($value)) {
                throw new FormulaException("变量 {$name} 的值必须是数字", FormulaException::INVALID_FORMULA);
            }
            $sanitizedVars[$name] = (float)$value;
        }

        $calculator = function () use ($phpCode, $sanitizedVars) {
            extract($sanitizedVars, EXTR_SKIP);
            return eval($phpCode);
        };

        set_error_handler(function ($errno, $errstr) {
            throw new FormulaException("公式执行错误: {$errstr}", FormulaException::CALCULATION_ERROR);
        });

        try {
            $result = $calculator();
        } catch (FormulaException $e) {
            restore_error_handler();
            throw $e;
        } catch (\Throwable $e) {
            restore_error_handler();
            throw new FormulaException("公式计算异常: " . $e->getMessage(), FormulaException::CALCULATION_ERROR);
        }

        restore_error_handler();
        return (float)$result;
    }

    private function normalizeNestedTernary(string $formula): string
    {
        $expr = trim($formula);
        if (strpos($expr, '?') === false) {
            return $expr;
        }
        return $this->addTernaryParentheses($expr);
    }

    private function addTernaryParentheses(string $expr): string
    {
        $depth = 0;
        $ternaryPositions = [];
        $colonPositions = [];
        $len = strlen($expr);

        for ($i = 0; $i < $len; $i++) {
            $ch = $expr[$i];
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
            } elseif ($depth === 0 && $ch === '?' && ($i === 0 || $expr[$i - 1] !== ':')) {
                $ternaryPositions[] = $i;
            } elseif ($depth === 0 && $ch === ':') {
                $colonPositions[] = $i;
            }
        }

        if (count($ternaryPositions) <= 1) {
            return $expr;
        }

        $count = count($ternaryPositions);
        if (count($colonPositions) < $count) {
            return $expr;
        }

        $result = $expr;
        for ($i = $count - 1; $i >= 1; $i--) {
            $qPos = $ternaryPositions[$i];
            $cPos = $colonPositions[$i];

            if ($cPos <= $qPos) {
                continue;
            }

            $beforeQ = $this->findTernaryConditionEnd($result, $qPos);
            $truePart = trim(substr($result, $qPos + 1, $cPos - $qPos - 1));
            $falsePart = trim(substr($result, $cPos + 1));

            if ($truePart === '' || $falsePart === '') {
                continue;
            }

            $condition = trim(substr($result, $beforeQ, $qPos - $beforeQ));
            $wrapped = "({$condition} ? {$truePart} : {$falsePart})";
            $result = substr($result, 0, $beforeQ) . $wrapped;
        }

        return $result;
    }

    private function findTernaryConditionEnd(string $expr, int $questionPos): int
    {
        $depth = 0;
        for ($i = $questionPos - 1; $i >= 0; $i--) {
            $ch = $expr[$i];
            if ($ch === ')') {
                $depth++;
            } elseif ($ch === '(') {
                $depth--;
                if ($depth < 0) {
                    return $i + 1;
                }
            }
            if ($depth === 0 && ($ch === ' ' || $i === 0)) {
                if ($i === 0) {
                    return 0;
                }
                $remaining = trim(substr($expr, $i, $questionPos - $i));
                if ($remaining !== '') {
                    return $i;
                }
            }
        }
        return 0;
    }

    public function batchCalculate(array $items): array
    {
        $maxBatch = (int)($GLOBALS['WITHHOLDING_MAX_BATCH_SIZE'] ?? 100);
        if (count($items) > $maxBatch) {
            throw new FormulaException("批量计算超过最大条数: {$maxBatch}", FormulaException::INVALID_FORMULA);
        }

        $results = [];
        foreach ($items as $index => $item) {
            try {
                $results[$index] = [
                    'success' => true,
                    'data' => $this->calculate(
                        $item['formula_code'],
                        $item['variables'] ?? [],
                        [
                            'record' => $item['record'] ?? true,
                            'order_no' => $item['order_no'] ?? '',
                            'operator' => $item['operator'] ?? 'system',
                            'remark' => $item['remark'] ?? '',
                        ]
                    ),
                ];
            } catch (\Throwable $e) {
                $results[$index] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'error_code' => $e instanceof FormulaException ? $e->getErrorCode() : 'UNKNOWN',
                ];
            }
        }
        return $results;
    }
}
