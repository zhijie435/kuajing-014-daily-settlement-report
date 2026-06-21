<?php

namespace Tests;

use App\Models\FundFlow;
use App\Models\WithholdingDetail;
use App\Services\Database;
use App\Services\WithholdingCalculator;

class StatusFlowTest extends TestCase
{
    private $calculator;

    public function __construct()
    {
        parent::__construct();
        $this->calculator = new WithholdingCalculator();
    }

    public function testFundFlowStatusLabels()
    {
        $labels = $this->fundFlowModel->getStatusLabels();
        $this->assertEqual('待处理', $labels[FundFlow::STATUS_PENDING], '待处理标签正确');
        $this->assertEqual('已完成', $labels[FundFlow::STATUS_COMPLETED], '已完成标签正确');
        $this->assertEqual('失败', $labels[FundFlow::STATUS_FAILED], '失败标签正确');
        $this->assertEqual('已取消', $labels[FundFlow::STATUS_CANCELLED], '已取消标签正确');
        $this->assertEqual('已冲正', $labels[FundFlow::STATUS_REVERSED], '已冲正标签正确');
    }

    public function testFundFlowStatusTagTypes()
    {
        $this->assertEqual('warning', $this->fundFlowModel->getStatusTagType(FundFlow::STATUS_PENDING), '待处理标签类型');
        $this->assertEqual('success', $this->fundFlowModel->getStatusTagType(FundFlow::STATUS_COMPLETED), '已完成标签类型');
        $this->assertEqual('danger', $this->fundFlowModel->getStatusTagType(FundFlow::STATUS_FAILED), '失败标签类型');
        $this->assertEqual('default', $this->fundFlowModel->getStatusTagType(FundFlow::STATUS_CANCELLED), '已取消标签类型');
        $this->assertEqual('info', $this->fundFlowModel->getStatusTagType(FundFlow::STATUS_REVERSED), '已冲正标签类型');
    }

    public function testFundFlowTypeLabels()
    {
        $labels = $this->fundFlowModel->getTypeLabels();
        $this->assertEqual('预扣', $labels[FundFlow::TYPE_WITHHOLD], '预扣类型标签');
        $this->assertEqual('退款', $labels[FundFlow::TYPE_REFUND], '退款类型标签');
        $this->assertEqual('结算', $labels[FundFlow::TYPE_SETTLEMENT], '结算类型标签');
        $this->assertEqual('调整', $labels[FundFlow::TYPE_ADJUST], '调整类型标签');
    }

    public function testFundFlowDirectionLabels()
    {
        $this->assertEqual('流入', $this->fundFlowModel->getDirectionLabel(FundFlow::DIRECTION_IN), '流入方向');
        $this->assertEqual('流出', $this->fundFlowModel->getDirectionLabel(FundFlow::DIRECTION_OUT), '流出方向');
    }

    public function testFundFlowValidTransitionsFromPending()
    {
        $this->assertTrue($this->fundFlowModel->canTransition(FundFlow::STATUS_PENDING, FundFlow::STATUS_COMPLETED), '待处理→已完成');
        $this->assertTrue($this->fundFlowModel->canTransition(FundFlow::STATUS_PENDING, FundFlow::STATUS_FAILED), '待处理→失败');
        $this->assertTrue($this->fundFlowModel->canTransition(FundFlow::STATUS_PENDING, FundFlow::STATUS_CANCELLED), '待处理→已取消');
        $this->assertFalse($this->fundFlowModel->canTransition(FundFlow::STATUS_PENDING, FundFlow::STATUS_REVERSED), '待处理不可→已冲正');
    }

    public function testFundFlowValidTransitionsFromCompleted()
    {
        $this->assertTrue($this->fundFlowModel->canTransition(FundFlow::STATUS_COMPLETED, FundFlow::STATUS_REVERSED), '已完成→已冲正');
        $this->assertTrue($this->fundFlowModel->canTransition(FundFlow::STATUS_COMPLETED, FundFlow::STATUS_CANCELLED), '已完成→已取消');
        $this->assertFalse($this->fundFlowModel->canTransition(FundFlow::STATUS_COMPLETED, FundFlow::STATUS_PENDING), '已完成不可→待处理');
        $this->assertFalse($this->fundFlowModel->canTransition(FundFlow::STATUS_COMPLETED, FundFlow::STATUS_FAILED), '已完成不可→失败');
    }

    public function testFundFlowTerminalStatus()
    {
        $this->assertTrue($this->fundFlowModel->isTerminalStatus(FundFlow::STATUS_CANCELLED), '已取消是终态');
        $this->assertTrue($this->fundFlowModel->isTerminalStatus(FundFlow::STATUS_REVERSED), '已冲正是终态');
        $this->assertFalse($this->fundFlowModel->isTerminalStatus(FundFlow::STATUS_PENDING), '待处理不是终态');
        $this->assertFalse($this->fundFlowModel->isTerminalStatus(FundFlow::STATUS_COMPLETED), '已完成不是终态');
    }

    public function testWithholdingDetailStatusLabels()
    {
        $labels = $this->detailModel->getStatusLabels();
        $this->assertEqual('待处理', $labels[WithholdingDetail::STATUS_PENDING], '待处理标签');
        $this->assertEqual('已完成', $labels[WithholdingDetail::STATUS_COMPLETED], '已完成标签');
        $this->assertEqual('失败', $labels[WithholdingDetail::STATUS_FAILED], '失败标签');
        $this->assertEqual('已取消', $labels[WithholdingDetail::STATUS_CANCELLED], '已取消标签');
        $this->assertEqual('已冲正', $labels[WithholdingDetail::STATUS_REVERSED], '已冲正标签');
        $this->assertEqual('已结算', $labels[WithholdingDetail::STATUS_SETTLED], '已结算标签');
    }

    public function testWithholdingDetailStatusTagTypes()
    {
        $this->assertEqual('warning', $this->detailModel->getStatusTagType(WithholdingDetail::STATUS_PENDING));
        $this->assertEqual('success', $this->detailModel->getStatusTagType(WithholdingDetail::STATUS_COMPLETED));
        $this->assertEqual('danger', $this->detailModel->getStatusTagType(WithholdingDetail::STATUS_FAILED));
        $this->assertEqual('default', $this->detailModel->getStatusTagType(WithholdingDetail::STATUS_CANCELLED));
        $this->assertEqual('info', $this->detailModel->getStatusTagType(WithholdingDetail::STATUS_REVERSED));
        $this->assertEqual('primary', $this->detailModel->getStatusTagType(WithholdingDetail::STATUS_SETTLED));
    }

    public function testWithholdingDetailValidTransitionsFromPending()
    {
        $this->assertTrue($this->detailModel->canTransition(WithholdingDetail::STATUS_PENDING, WithholdingDetail::STATUS_COMPLETED));
        $this->assertTrue($this->detailModel->canTransition(WithholdingDetail::STATUS_PENDING, WithholdingDetail::STATUS_FAILED));
        $this->assertTrue($this->detailModel->canTransition(WithholdingDetail::STATUS_PENDING, WithholdingDetail::STATUS_CANCELLED));
        $this->assertFalse($this->detailModel->canTransition(WithholdingDetail::STATUS_PENDING, WithholdingDetail::STATUS_SETTLED));
    }

    public function testWithholdingDetailValidTransitionsFromCompleted()
    {
        $this->assertTrue($this->detailModel->canTransition(WithholdingDetail::STATUS_COMPLETED, WithholdingDetail::STATUS_SETTLED));
        $this->assertTrue($this->detailModel->canTransition(WithholdingDetail::STATUS_COMPLETED, WithholdingDetail::STATUS_REVERSED));
        $this->assertTrue($this->detailModel->canTransition(WithholdingDetail::STATUS_COMPLETED, WithholdingDetail::STATUS_CANCELLED));
        $this->assertFalse($this->detailModel->canTransition(WithholdingDetail::STATUS_COMPLETED, WithholdingDetail::STATUS_PENDING));
    }

    public function testWithholdingDetailValidTransitionsFromSettled()
    {
        $this->assertTrue($this->detailModel->canTransition(WithholdingDetail::STATUS_SETTLED, WithholdingDetail::STATUS_REVERSED), '已结算→已冲正');
        $this->assertFalse($this->detailModel->canTransition(WithholdingDetail::STATUS_SETTLED, WithholdingDetail::STATUS_COMPLETED), '已结算不可→已完成');
    }

    public function testWithholdingDetailTerminalStatus()
    {
        $this->assertTrue($this->detailModel->isTerminalStatus(WithholdingDetail::STATUS_CANCELLED), '已取消是终态');
        $this->assertTrue($this->detailModel->isTerminalStatus(WithholdingDetail::STATUS_REVERSED), '已冲正是终态');
        $this->assertFalse($this->detailModel->isTerminalStatus(WithholdingDetail::STATUS_SETTLED), '已结算不是终态（可冲正）');
        $this->assertFalse($this->detailModel->isTerminalStatus(WithholdingDetail::STATUS_COMPLETED), '已完成不是终态');
    }

    public function testCompletedFundFlowUpdatesBalance()
    {
        $this->assertEqual(0.0, $this->fundFlowModel->getCurrentBalance(), '初始余额为0');

        $flowId = $this->fundFlowModel->create([
            'flow_no' => $this->fundFlowModel->generateFlowNo(),
            'flow_type' => FundFlow::TYPE_WITHHOLD,
            'direction' => FundFlow::DIRECTION_OUT,
            'amount' => 100.0,
            'balance' => -100.0,
            'currency' => 'CNY',
            'operator' => 'tester',
            'status' => FundFlow::STATUS_COMPLETED,
        ]);

        $this->assertEqual(-100.0, $this->fundFlowModel->getCurrentBalance(), '已完成流出流水更新余额为-100');
    }

    public function testPendingFlowDoesNotAffectBalance()
    {
        $this->fundFlowModel->create([
            'flow_no' => $this->fundFlowModel->generateFlowNo(),
            'flow_type' => FundFlow::TYPE_WITHHOLD,
            'direction' => FundFlow::DIRECTION_OUT,
            'amount' => 200.0,
            'balance' => -200.0,
            'status' => FundFlow::STATUS_PENDING,
        ]);

        $this->assertEqual(0.0, $this->fundFlowModel->getCurrentBalance(), '待处理流水不影响余额');
    }

    public function testMultipleCompletedFlowsCumulativeBalance()
    {
        $this->fundFlowModel->create([
            'flow_no' => $this->fundFlowModel->generateFlowNo(),
            'flow_type' => FundFlow::TYPE_SETTLEMENT,
            'direction' => FundFlow::DIRECTION_IN,
            'amount' => 1000.0,
            'balance' => 1000.0,
            'status' => FundFlow::STATUS_COMPLETED,
        ]);

        $this->fundFlowModel->create([
            'flow_no' => $this->fundFlowModel->generateFlowNo(),
            'flow_type' => FundFlow::TYPE_WITHHOLD,
            'direction' => FundFlow::DIRECTION_OUT,
            'amount' => 200.0,
            'balance' => 800.0,
            'status' => FundFlow::STATUS_COMPLETED,
        ]);

        $this->fundFlowModel->create([
            'flow_no' => $this->fundFlowModel->generateFlowNo(),
            'flow_type' => FundFlow::TYPE_REFUND,
            'direction' => FundFlow::DIRECTION_OUT,
            'amount' => 100.0,
            'balance' => 700.0,
            'status' => FundFlow::STATUS_COMPLETED,
        ]);

        $this->assertEqual(700.0, $this->fundFlowModel->getCurrentBalance(), '多笔流水累计余额正确');
    }

    public function testWithholdingDetailLinkedWithFundFlow()
    {
        $calcResult = $this->calculator->calculate('ORDER_AMOUNT_RATE', [
            'order_amount' => 500,
            'rate' => 0.05,
        ], [
            'record' => true,
            'order_no' => 'LINK-TEST-001',
            'operator' => 'tester',
        ]);

        $detailId = $calcResult['detail_id'];
        $flows = $this->fundFlowModel->findByWithholdingDetailId($detailId);

        $this->assertEqual(1, count($flows), '明细关联1条流水');
        $this->assertEqual((int)$detailId, (int)$flows[0]['withholding_detail_id'], '流水关联正确的明细ID');
        $this->assertEqual(25.0, (float)$flows[0]['amount'], '流水金额正确');
        $this->assertEqual(FundFlow::TYPE_WITHHOLD, $flows[0]['flow_type'], '流水类型正确');
        $this->assertEqual(FundFlow::DIRECTION_OUT, (int)$flows[0]['direction'], '流水方向正确');

        $detail = $this->detailModel->find($detailId);
        $this->assertEqual(WithholdingDetail::STATUS_COMPLETED, (int)$detail['status'], '明细初始状态为已完成');
        $this->assertEqual(FundFlow::STATUS_COMPLETED, (int)$flows[0]['status'], '流水初始状态为已完成');
    }

    public function testUpdateDetailStatusToSettledSyncsFlowStatus()
    {
        $calcResult = $this->calculator->calculate('ORDER_AMOUNT_RATE', [
            'order_amount' => 1000,
        ], [
            'record' => true,
            'order_no' => 'SYNC-TEST-001',
            'operator' => 'tester',
        ]);

        $detailId = $calcResult['detail_id'];
        $flowId = $calcResult['fund_flow_id'];

        $this->detailModel->update($detailId, ['status' => WithholdingDetail::STATUS_SETTLED]);

        $updatedDetail = $this->detailModel->find($detailId);
        $this->assertEqual(WithholdingDetail::STATUS_SETTLED, (int)$updatedDetail['status'], '明细状态更新为已结算');

        $flows = $this->fundFlowModel->findByWithholdingDetailId($detailId);
        $this->assertEqual(FundFlow::STATUS_COMPLETED, (int)$flows[0]['status'], '流水状态保持已完成（未同步）');
    }

    public function testOperationLogRecorded()
    {
        $beforeCount = $this->logModel->count();

        $this->logModel->log(
            'withholding',
            'status_update',
            'withholding_detail',
            99,
            WithholdingDetail::STATUS_PENDING,
            WithholdingDetail::STATUS_COMPLETED,
            null,
            null,
            null,
            'tester',
            '确认预扣完成',
            '127.0.0.1'
        );

        $afterCount = $this->logModel->count();
        $this->assertEqual($beforeCount + 1, $afterCount, '操作日志已记录');

        $logs = $this->logModel->findByResource('withholding_detail', 99);
        $this->assertEqual(1, count($logs), '查询到对应日志');
        $this->assertEqual('status_update', $logs[0]['action'], '操作类型正确');
        $this->assertEqual('tester', $logs[0]['username'], '操作人正确');
    }

    public function testFullLifeCyclePendingToCompletedToSettledToReversed()
    {
        $detailId = $this->detailModel->create([
            'formula_id' => 1,
            'formula_code' => 'ORDER_AMOUNT_RATE',
            'formula_name' => '订单金额比例预扣',
            'formula' => 'order_amount * rate',
            'variables' => json_encode(['order_amount' => 5000, 'rate' => 0.05]),
            'result' => 250.0,
            'order_no' => 'LIFECYCLE-001',
            'operator' => 'tester',
            'status' => WithholdingDetail::STATUS_PENDING,
        ]);

        $detail = $this->detailModel->find($detailId);
        $this->assertEqual(WithholdingDetail::STATUS_PENDING, (int)$detail['status'], '初始状态待处理');
        $this->assertTrue($this->detailModel->canTransition(WithholdingDetail::STATUS_PENDING, WithholdingDetail::STATUS_COMPLETED), '待处理→已完成 合法');

        $this->detailModel->update($detailId, ['status' => WithholdingDetail::STATUS_COMPLETED]);
        $detail = $this->detailModel->find($detailId);
        $this->assertEqual(WithholdingDetail::STATUS_COMPLETED, (int)$detail['status'], '状态更新为已完成');

        $flowId = $this->fundFlowModel->create([
            'flow_no' => $this->fundFlowModel->generateFlowNo(),
            'flow_type' => FundFlow::TYPE_WITHHOLD,
            'direction' => FundFlow::DIRECTION_OUT,
            'amount' => 250.0,
            'balance' => -250.0,
            'withholding_detail_id' => $detailId,
            'status' => FundFlow::STATUS_COMPLETED,
        ]);

        $this->assertTrue($this->detailModel->canTransition(WithholdingDetail::STATUS_COMPLETED, WithholdingDetail::STATUS_SETTLED), '已完成→已结算 合法');

        $this->detailModel->update($detailId, ['status' => WithholdingDetail::STATUS_SETTLED]);
        $detail = $this->detailModel->find($detailId);
        $this->assertEqual(WithholdingDetail::STATUS_SETTLED, (int)$detail['status'], '状态更新为已结算');

        $this->assertTrue($this->detailModel->canTransition(WithholdingDetail::STATUS_SETTLED, WithholdingDetail::STATUS_REVERSED), '已结算→已冲正 合法');

        $this->detailModel->update($detailId, ['status' => WithholdingDetail::STATUS_REVERSED]);
        $this->fundFlowModel->update($flowId, ['status' => FundFlow::STATUS_REVERSED]);

        $detail = $this->detailModel->find($detailId);
        $flow = $this->fundFlowModel->find($flowId);

        $this->assertEqual(WithholdingDetail::STATUS_REVERSED, (int)$detail['status'], '明细最终状态已冲正');
        $this->assertEqual(FundFlow::STATUS_REVERSED, (int)$flow['status'], '流水最终状态已冲正');

        $this->assertTrue($this->detailModel->isTerminalStatus(WithholdingDetail::STATUS_REVERSED), '已冲正是明细终态');
        $this->assertTrue($this->fundFlowModel->isTerminalStatus(FundFlow::STATUS_REVERSED), '已冲正是流水终态');
    }

    public function testTerminalStatusCannotTransition()
    {
        $this->assertFalse(
            $this->fundFlowModel->canTransition(FundFlow::STATUS_CANCELLED, FundFlow::STATUS_COMPLETED),
            '流水已取消不可转换为已完成'
        );
        $this->assertFalse(
            $this->fundFlowModel->canTransition(FundFlow::STATUS_REVERSED, FundFlow::STATUS_PENDING),
            '流水已冲正不可转换为待处理'
        );
        $this->assertFalse(
            $this->detailModel->canTransition(WithholdingDetail::STATUS_CANCELLED, WithholdingDetail::STATUS_COMPLETED),
            '明细已取消不可转换为已完成'
        );
        $this->assertFalse(
            $this->detailModel->canTransition(WithholdingDetail::STATUS_REVERSED, WithholdingDetail::STATUS_SETTLED),
            '明细已冲正不可转换为已结算'
        );
    }

    public function testGenerateFundFlowNo()
    {
        $flowNo = $this->fundFlowModel->generateFlowNo();
        $this->assertTrue(strpos($flowNo, 'FF') === 0, '流水号以FF开头');
        $this->assertEqual(20, strlen($flowNo), '流水号长度为20（FF+14位日期时间+4位随机）');

        $flowNo2 = $this->fundFlowModel->generateFlowNo();
        $this->assertTrue($flowNo !== $flowNo2, '两次生成的流水号不相同');
    }

    public function testCalculateNewBalanceOutFlow()
    {
        $newBalance = $this->fundFlowModel->calculateNewBalance(100.0, FundFlow::DIRECTION_OUT);
        $this->assertEqual(-100.0, $newBalance, '流出100，余额变为-100');
    }

    public function testCalculateNewBalanceInFlow()
    {
        $newBalance = $this->fundFlowModel->calculateNewBalance(500.0, FundFlow::DIRECTION_IN);
        $this->assertEqual(500.0, $newBalance, '流入500，余额变为500');
    }
}
