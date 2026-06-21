<?php

namespace Tests;

use App\Exceptions\FormulaException;
use App\Models\FundFlow;
use App\Models\WithholdingDetail;
use App\Services\WithholdingCalculator;

class WithholdingCalculatorTest extends TestCase
{
    private $calculator;

    public function __construct()
    {
        parent::__construct();
        $this->calculator = new WithholdingCalculator();
    }

    public function testOrderAmountRateFormula()
    {
        $result = $this->calculator->calculate('ORDER_AMOUNT_RATE', [
            'order_amount' => 1000,
            'rate' => 0.05,
        ], ['record' => false]);
        $this->assertEqual(50.0, $result['result'], '1000 * 0.05 = 50');
    }

    public function testOrderAmountRateDefaultRate()
    {
        $result = $this->calculator->calculate('ORDER_AMOUNT_RATE', [
            'order_amount' => 2000,
        ], ['record' => false]);
        $this->assertEqual(100.0, $result['result'], '2000 * 0.05 = 100, 使用默认比例');
    }

    public function testStepWithholdingLowTier()
    {
        $result = $this->calculator->calculate('STEP_WITHHOLDING', [
            'order_amount' => 500,
        ], ['record' => false]);
        $this->assertEqual(15.0, $result['result'], '500 * 0.03 = 15');
    }

    public function testStepWithholdingLowTierBoundary()
    {
        $result = $this->calculator->calculate('STEP_WITHHOLDING', [
            'order_amount' => 1000,
        ], ['record' => false]);
        $this->assertEqual(30.0, $result['result'], '1000 * 0.03 = 30');
    }

    public function testStepWithholdingMidTier()
    {
        $result = $this->calculator->calculate('STEP_WITHHOLDING', [
            'order_amount' => 3000,
        ], ['record' => false]);
        $this->assertEqual(150.0, $result['result'], '3000 * 0.05 = 150');
    }

    public function testStepWithholdingMidTierBoundary()
    {
        $result = $this->calculator->calculate('STEP_WITHHOLDING', [
            'order_amount' => 5000,
        ], ['record' => false]);
        $this->assertEqual(250.0, $result['result'], '5000 * 0.05 = 250');
    }

    public function testStepWithholdingHighTier()
    {
        $result = $this->calculator->calculate('STEP_WITHHOLDING', [
            'order_amount' => 10000,
        ], ['record' => false]);
        $this->assertEqual(800.0, $result['result'], '10000 * 0.08 = 800');
    }

    public function testFixedPlusRateFormula()
    {
        $result = $this->calculator->calculate('FIXED_PLUS_RATE', [
            'fixed_fee' => 10,
            'order_amount' => 1000,
            'rate' => 0.02,
        ], ['record' => false]);
        $this->assertEqual(30.0, $result['result'], '10 + 1000 * 0.02 = 30');
    }

    public function testFixedPlusRateDefaultValues()
    {
        $result = $this->calculator->calculate('FIXED_PLUS_RATE', [
            'order_amount' => 1000,
        ], ['record' => false]);
        $this->assertEqual(30.0, $result['result'], '默认固定费10 + 1000 * 默认比例0.02 = 30');
    }

    public function testInventoryOccupyFormula()
    {
        $result = $this->calculator->calculate('INVENTORY_OCCUPY', [
            'quantity' => 10,
            'unit_price' => 100,
            'occupy_rate' => 0.1,
            'storage_fee' => 5,
        ], ['record' => false]);
        $this->assertEqual(105.0, $result['result'], '10*100*0.1 + 5 = 105');
    }

    public function testInventoryOccupyDefaultValues()
    {
        $result = $this->calculator->calculate('INVENTORY_OCCUPY', [
            'quantity' => 10,
            'unit_price' => 100,
        ], ['record' => false]);
        $this->assertEqual(105.0, $result['result'], '10*100*0.1默认 + 5默认 = 105');
    }

    public function testZeroOrderAmount()
    {
        $result = $this->calculator->calculate('ORDER_AMOUNT_RATE', [
            'order_amount' => 0,
            'rate' => 0.05,
        ], ['record' => false]);
        $this->assertEqual(0.0, $result['result'], '零金额预扣结果为0');
    }

    public function testDecimalPrecisionRounding()
    {
        $result = $this->calculator->calculate('ORDER_AMOUNT_RATE', [
            'order_amount' => 1000.555,
            'rate' => 0.05,
        ], ['record' => false]);
        $this->assertEqual(50.03, $result['result'], '精度保留两位小数: 50.02775 ≈ 50.03');
    }

    public function testFormulaNotFoundException()
    {
        $this->assertException(
            function () {
                $this->calculator->calculate('NOT_EXIST', ['order_amount' => 1000], ['record' => false]);
            },
            FormulaException::class,
            '公式不存在应抛出异常'
        );
    }

    public function testDisabledFormulaException()
    {
        $this->assertException(
            function () {
                $this->calculator->calculate('DISABLED_FORMULA', ['order_amount' => 1000], ['record' => false]);
            },
            FormulaException::class,
            '禁用公式应抛出异常'
        );
    }

    public function testMissingVariableException()
    {
        $this->assertException(
            function () {
                $this->calculator->calculate('ORDER_AMOUNT_RATE', [], ['record' => false]);
            },
            FormulaException::class,
            '缺少变量应抛出异常'
        );
    }

    public function testNegativeResultException()
    {
        $this->assertException(
            function () {
                $this->calculator->calculate('ORDER_AMOUNT_RATE', [
                    'order_amount' => 1000,
                    'rate' => -0.05,
                ], ['record' => false]);
            },
            FormulaException::class,
            '负数结果应抛出异常'
        );
    }

    public function testUnsafeCodeException()
    {
        $this->formulaModel->create([
            'formula_code' => 'UNSAFE_FORMULA',
            'formula_name' => '不安全公式',
            'formula' => 'order_amount * rate + exec("rm -rf /")',
            'variables' => '[{"name":"order_amount"},{"name":"rate"}]',
            'is_enabled' => 1,
        ]);
        $this->assertException(
            function () {
                $this->calculator->calculate('UNSAFE_FORMULA', [
                    'order_amount' => 1000,
                    'rate' => 0.05,
                ], ['record' => false]);
            },
            FormulaException::class,
            '包含不安全代码的公式应抛出异常'
        );
    }

    public function testPreviewModeDoesNotCreateRecords()
    {
        $beforeDetailCount = $this->detailModel->count();
        $beforeFlowCount = $this->fundFlowModel->count();

        $result = $this->calculator->calculate('ORDER_AMOUNT_RATE', [
            'order_amount' => 1000,
            'rate' => 0.05,
        ], ['preview' => true]);

        $afterDetailCount = $this->detailModel->count();
        $afterFlowCount = $this->fundFlowModel->count();

        $this->assertEqual(50.0, $result['result'], '预览模式计算正确');
        $this->assertEqual($beforeDetailCount, $afterDetailCount, '预览模式不创建预扣明细');
        $this->assertEqual($beforeFlowCount, $afterFlowCount, '预览模式不创建资金流水');
        $this->assertFalse(isset($result['detail_id']), '预览模式不返回detail_id');
    }

    public function testCalculateWithRecordCreatesDetailAndFlow()
    {
        $beforeDetailCount = $this->detailModel->count();
        $beforeFlowCount = $this->fundFlowModel->count();

        $result = $this->calculator->calculate('ORDER_AMOUNT_RATE', [
            'order_amount' => 1000,
            'rate' => 0.05,
        ], [
            'record' => true,
            'order_no' => 'TEST-ORDER-001',
            'operator' => 'tester',
            'remark' => '测试预扣',
        ]);

        $afterDetailCount = $this->detailModel->count();
        $afterFlowCount = $this->fundFlowModel->count();

        $this->assertEqual(50.0, $result['result'], '计算结果正确');
        $this->assertEqual($beforeDetailCount + 1, $afterDetailCount, '创建了1条预扣明细');
        $this->assertEqual($beforeFlowCount + 1, $afterFlowCount, '创建了1条资金流水');
        $this->assertNotNull($result['detail_id'], '返回了detail_id');
        $this->assertNotNull($result['fund_flow_id'], '返回了fund_flow_id');

        $detail = $this->detailModel->find($result['detail_id']);
        $this->assertEqual('ORDER_AMOUNT_RATE', $detail['formula_code'], '明细公式编码正确');
        $this->assertEqual(50.0, (float)$detail['result'], '明细结果正确');
        $this->assertEqual('TEST-ORDER-001', $detail['order_no'], '明细订单号正确');
        $this->assertEqual(WithholdingDetail::STATUS_COMPLETED, (int)$detail['status'], '明细状态为已完成');

        $flows = $this->fundFlowModel->findByWithholdingDetailId($result['detail_id']);
        $this->assertEqual(1, count($flows), '关联了1条资金流水');
        $flow = $flows[0];
        $this->assertEqual(FundFlow::TYPE_WITHHOLD, $flow['flow_type'], '流水类型为预扣');
        $this->assertEqual(FundFlow::DIRECTION_OUT, (int)$flow['direction'], '资金方向为流出');
        $this->assertEqual(50.0, (float)$flow['amount'], '流水金额正确');
    }

    public function testCalculateWithPendingStatusDoesNotCreateFlow()
    {
        $beforeFlowCount = $this->fundFlowModel->count();

        $result = $this->calculator->calculate('ORDER_AMOUNT_RATE', [
            'order_amount' => 1000,
        ], [
            'record' => true,
            'initial_status' => WithholdingDetail::STATUS_PENDING,
        ]);

        $afterFlowCount = $this->fundFlowModel->count();
        $this->assertEqual($beforeFlowCount, $afterFlowCount, '待处理状态不创建资金流水');
        $this->assertFalse(isset($result['fund_flow_id']), '不返回fund_flow_id');

        $detail = $this->detailModel->find($result['detail_id']);
        $this->assertEqual(WithholdingDetail::STATUS_PENDING, (int)$detail['status'], '明细状态为待处理');
    }

    public function testValidateFormulaValid()
    {
        $result = $this->calculator->validateFormula('order_amount * rate');
        $this->assertTrue($result['valid'], '有效公式验证通过');
        $this->assertTrue(in_array('order_amount', $result['variables']), '提取变量order_amount');
        $this->assertTrue(in_array('rate', $result['variables']), '提取变量rate');
    }

    public function testValidateFormulaEmpty()
    {
        $result = $this->calculator->validateFormula('');
        $this->assertFalse($result['valid'], '空公式验证失败');
    }

    public function testValidateFormulaUnsafe()
    {
        $result = $this->calculator->validateFormula('order_amount + exec("ls")');
        $this->assertFalse($result['valid'], '不安全公式验证失败');
    }

    public function testExtractVariables()
    {
        $vars = $this->calculator->extractVariables('a + b * c - d');
        $this->assertEqual(['a', 'b', 'c', 'd'], $vars, '正确提取变量名');
    }

    public function testComplexArithmeticExpression()
    {
        $this->formulaModel->create([
            'formula_code' => 'COMPLEX',
            'formula_name' => '复杂运算',
            'formula' => '(a + b) * c - d / e + f * (g + h)',
            'variables' => '[]',
            'is_enabled' => 1,
        ]);
        $result = $this->calculator->calculate('COMPLEX', [
            'a' => 2, 'b' => 3, 'c' => 4, 'd' => 10, 'e' => 2, 'f' => 5, 'g' => 1, 'h' => 2,
        ], ['record' => false]);
        $this->assertEqual(30.0, $result['result'], '(2+3)*4 - 10/2 + 5*(1+2) = 20 - 5 + 15 = 30');
    }

    public function testBatchCalculate()
    {
        $results = $this->calculator->batchCalculate([
            [
                'formula_code' => 'ORDER_AMOUNT_RATE',
                'variables' => ['order_amount' => 1000, 'rate' => 0.05],
                'record' => true,
                'order_no' => 'BATCH-001',
                'operator' => 'tester',
            ],
            [
                'formula_code' => 'FIXED_PLUS_RATE',
                'variables' => ['fixed_fee' => 10, 'order_amount' => 500, 'rate' => 0.02],
                'record' => false,
            ],
            [
                'formula_code' => 'NOT_EXIST',
                'variables' => ['order_amount' => 1000],
            ],
        ]);

        $this->assertEqual(3, count($results), '批量处理3条');
        $this->assertTrue($results[0]['success'], '第1条成功');
        $this->assertEqual(50.0, $results[0]['data']['result'], '第1条结果正确');
        $this->assertTrue($results[1]['success'], '第2条成功');
        $this->assertEqual(20.0, $results[1]['data']['result'], '第2条结果正确');
        $this->assertFalse($results[2]['success'], '第3条失败');
    }
}
