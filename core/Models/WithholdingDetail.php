<?php

namespace App\Models;

class WithholdingDetail extends Model
{
    protected $table = 'withholding_details';

    const STATUS_PENDING = 0;
    const STATUS_COMPLETED = 1;
    const STATUS_FAILED = 2;
    const STATUS_CANCELLED = 3;
    const STATUS_REVERSED = 4;
    const STATUS_SETTLED = 5;

    protected $fillable = [
        'formula_id', 'formula_code', 'formula_name', 'formula',
        'variables', 'result', 'order_no', 'related_type', 'related_id',
        'operator', 'remark', 'status'
    ];

    private $statusLabels = [
        self::STATUS_PENDING => '待处理',
        self::STATUS_COMPLETED => '已完成',
        self::STATUS_FAILED => '失败',
        self::STATUS_CANCELLED => '已取消',
        self::STATUS_REVERSED => '已冲正',
        self::STATUS_SETTLED => '已结算',
    ];

    private $statusTagTypes = [
        self::STATUS_PENDING => 'warning',
        self::STATUS_COMPLETED => 'success',
        self::STATUS_FAILED => 'danger',
        self::STATUS_CANCELLED => 'default',
        self::STATUS_REVERSED => 'info',
        self::STATUS_SETTLED => 'primary',
    ];

    private $transitionRules = [
        self::STATUS_PENDING => [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED => [self::STATUS_SETTLED, self::STATUS_REVERSED, self::STATUS_CANCELLED],
        self::STATUS_FAILED => [self::STATUS_PENDING, self::STATUS_CANCELLED],
        self::STATUS_CANCELLED => [],
        self::STATUS_REVERSED => [],
        self::STATUS_SETTLED => [self::STATUS_REVERSED],
    ];

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

    public function findByOrderNo(string $orderNo): array
    {
        return $this->where('order_no = ?', [$orderNo], 'id DESC');
    }
}
