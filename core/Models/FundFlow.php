<?php

namespace App\Models;

class FundFlow extends Model
{
    protected $table = 'fund_flows';

    const STATUS_PENDING = 0;
    const STATUS_COMPLETED = 1;
    const STATUS_FAILED = 2;
    const STATUS_CANCELLED = 3;
    const STATUS_REVERSED = 4;

    const TYPE_WITHHOLD = 'withholding';
    const TYPE_REFUND = 'refund';
    const TYPE_SETTLEMENT = 'settlement';
    const TYPE_ADJUST = 'adjust';

    const DIRECTION_IN = 1;
    const DIRECTION_OUT = 2;

    protected $fillable = [
        'flow_no', 'flow_type', 'direction', 'amount', 'balance',
        'currency', 'withholding_detail_id', 'order_no', 'related_type',
        'related_id', 'operator', 'remark', 'status'
    ];

    private $statusLabels = [
        self::STATUS_PENDING => '待处理',
        self::STATUS_COMPLETED => '已完成',
        self::STATUS_FAILED => '失败',
        self::STATUS_CANCELLED => '已取消',
        self::STATUS_REVERSED => '已冲正',
    ];

    private $statusTagTypes = [
        self::STATUS_PENDING => 'warning',
        self::STATUS_COMPLETED => 'success',
        self::STATUS_FAILED => 'danger',
        self::STATUS_CANCELLED => 'default',
        self::STATUS_REVERSED => 'info',
    ];

    private $typeLabels = [
        self::TYPE_WITHHOLD => '预扣',
        self::TYPE_REFUND => '退款',
        self::TYPE_SETTLEMENT => '结算',
        self::TYPE_ADJUST => '调整',
    ];

    private $directionLabels = [
        self::DIRECTION_IN => '流入',
        self::DIRECTION_OUT => '流出',
    ];

    private $transitionRules = [
        self::STATUS_PENDING => [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED => [self::STATUS_REVERSED, self::STATUS_CANCELLED],
        self::STATUS_FAILED => [self::STATUS_PENDING, self::STATUS_CANCELLED],
        self::STATUS_CANCELLED => [],
        self::STATUS_REVERSED => [],
    ];

    public function generateFlowNo(): string
    {
        $prefix = $GLOBALS['FUND_FLOW_NO_PREFIX'] ?? 'FF';
        return $prefix . date('YmdHis') . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function getStatusLabels(): array
    {
        return $this->statusLabels;
    }

    public function getStatusLabel(int $status): string
    {
        return $this->statusLabels[$status] ?? '未知';
    }

    public function getStatusTagType(int $status): string
    {
        return $this->statusTagTypes[$status] ?? 'default';
    }

    public function getTypeLabels(): array
    {
        return $this->typeLabels;
    }

    public function getTypeLabel(string $type): string
    {
        return $this->typeLabels[$type] ?? '未知';
    }

    public function getDirectionLabels(): array
    {
        return $this->directionLabels;
    }

    public function getDirectionLabel(int $direction): string
    {
        return $this->directionLabels[$direction] ?? '未知';
    }

    public function canTransition(int $from, int $to): bool
    {
        if (!isset($this->transitionRules[$from])) {
            return false;
        }
        return in_array($to, $this->transitionRules[$from], true);
    }

    public function getValidTransitions(int $status): array
    {
        return $this->transitionRules[$status] ?? [];
    }

    public function isTerminalStatus(int $status): bool
    {
        return isset($this->transitionRules[$status]) && empty($this->transitionRules[$status]);
    }

    public function findByWithholdingDetailId(int $detailId): array
    {
        return $this->where('withholding_detail_id = ?', [$detailId], 'id DESC');
    }

    public function findByOrderNo(string $orderNo): array
    {
        return $this->where('order_no = ?', [$orderNo], 'id DESC');
    }

    public function getCurrentBalance(): float
    {
        $rows = $this->db->query(
            "SELECT balance FROM {$this->table} WHERE status = ? ORDER BY id DESC LIMIT 1",
            [self::STATUS_COMPLETED]
        );
        return !empty($rows) ? (float)$rows[0]['balance'] : 0.0;
    }

    public function calculateNewBalance(float $amount, int $direction): float
    {
        $current = $this->getCurrentBalance();
        if ($direction === self::DIRECTION_IN) {
            return $current + $amount;
        }
        return $current - $amount;
    }
}
