<?php

namespace Tests;

use App\Models\FundFlow;
use App\Models\OperationLog;
use App\Models\WithholdingDetail;
use App\Models\WithholdingFormula;
use App\Services\Database;

abstract class TestCase
{
    protected $testDbPath;
    protected $formulaModel;
    protected $detailModel;
    protected $fundFlowModel;
    protected $logModel;
    protected $db;

    protected $passed = 0;
    protected $failed = 0;
    protected $errors = [];
    protected $passedBefore = 0;

    public function __construct()
    {
        $this->testDbPath = __DIR__ . '/test_data/test_' . uniqid() . '.sqlite';
        $this->setupTestDatabase();

        $this->formulaModel = new WithholdingFormula();
        $this->detailModel = new WithholdingDetail();
        $this->fundFlowModel = new FundFlow();
        $this->logModel = new OperationLog();
        $this->db = Database::getInstance();
    }

    protected function setupTestDatabase(): void
    {
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }

        $dir = dirname($this->testDbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pdo = new \PDO('sqlite:' . $this->testDbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS withholding_formulas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                formula_code TEXT NOT NULL UNIQUE,
                formula_name TEXT NOT NULL,
                formula TEXT NOT NULL,
                variables TEXT,
                description TEXT,
                is_enabled INTEGER DEFAULT 1,
                sort_order INTEGER DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS withholding_details (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                formula_id INTEGER,
                formula_code TEXT,
                formula_name TEXT,
                formula TEXT,
                variables TEXT,
                result REAL DEFAULT 0,
                order_no TEXT,
                related_type TEXT,
                related_id TEXT,
                operator TEXT,
                remark TEXT,
                status INTEGER DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS fund_flows (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                flow_no TEXT NOT NULL UNIQUE,
                flow_type TEXT NOT NULL,
                direction INTEGER NOT NULL,
                amount REAL NOT NULL DEFAULT 0,
                balance REAL DEFAULT 0,
                currency TEXT DEFAULT 'CNY',
                withholding_detail_id INTEGER,
                order_no TEXT,
                related_type TEXT,
                related_id TEXT,
                operator TEXT,
                remark TEXT,
                status INTEGER DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS operation_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                module TEXT,
                resource_type TEXT,
                resource_id TEXT,
                action TEXT,
                old_value TEXT,
                new_value TEXT,
                operator TEXT,
                remark TEXT,
                ip_address TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("
            INSERT INTO withholding_formulas (formula_code, formula_name, formula, variables, description, is_enabled, sort_order) VALUES
            ('ORDER_AMOUNT_RATE', '订单金额比例预扣', 'order_amount * rate', '[{\"name\":\"order_amount\",\"label\":\"订单金额\"},{\"name\":\"rate\",\"label\":\"比例\",\"default\":0.05}]', '按订单金额乘以比例计算预扣金额', 1, 1),
            ('STEP_WITHHOLDING', '阶梯式预扣', 'order_amount <= 1000 ? order_amount * 0.03 : (order_amount <= 5000 ? order_amount * 0.05 : order_amount * 0.08)', '[{\"name\":\"order_amount\",\"label\":\"订单金额\"}]', '订单金额1000以内3%，1001-5000部分5%，5000以上部分8%', 1, 2),
            ('FIXED_PLUS_RATE', '固定金额加比例', 'fixed_fee + order_amount * rate', '[{\"name\":\"fixed_fee\",\"label\":\"固定手续费\",\"default\":10},{\"name\":\"order_amount\",\"label\":\"订单金额\"},{\"name\":\"rate\",\"label\":\"比例\",\"default\":0.02}]', '固定手续费加上订单金额比例', 1, 3),
            ('INVENTORY_OCCUPY', '库存占用预扣', 'quantity * unit_price * occupy_rate + storage_fee', '[{\"name\":\"quantity\",\"label\":\"数量\"},{\"name\":\"unit_price\",\"label\":\"单价\"},{\"name\":\"occupy_rate\",\"label\":\"占用费率\",\"default\":0.1},{\"name\":\"storage_fee\",\"label\":\"仓储费\",\"default\":5}]', '库存占用费用：数量×单价×占用费率+仓储费', 1, 4),
            ('DISABLED_FORMULA', '已禁用公式', 'order_amount * 0.1', '[{\"name\":\"order_amount\",\"label\":\"订单金额\"}]', '用于测试禁用公式', 0, 99)
        ");

        $reflection = new \ReflectionProperty(Database::class, 'instance');
        $reflection->setAccessible(true);
        $reflection->setValue(null, null);

        $GLOBALS['test_db_path'] = $this->testDbPath;
    }

    protected function assertEqual($expected, $actual, string $message = ''): void
    {
        $method = debug_backtrace()[1]['function'] ?? 'unknown';
        if ($expected === $actual) {
            $this->passed++;
            return;
        }
        $this->failed++;
        $msg = "{$method} - {$message}: 期望 " . var_export($expected, true) . ", 实际 " . var_export($actual, true);
        $this->errors[] = $msg;
        echo "  ✗ {$msg}\n";
    }

    protected function assertTrue(bool $condition, string $message = ''): void
    {
        $this->assertEqual(true, $condition, $message);
    }

    protected function assertFalse(bool $condition, string $message = ''): void
    {
        $this->assertEqual(false, $condition, $message);
    }

    protected function assertNotEmpty($actual, string $message = ''): void
    {
        $this->assertTrue(!empty($actual), $message);
    }

    protected function assertNull($actual, string $message = ''): void
    {
        $this->assertEqual(null, $actual, $message);
    }

    protected function assertNotNull($actual, string $message = ''): void
    {
        $this->assertTrue($actual !== null, $message);
    }

    protected function assertException(callable $fn, string $expectedExceptionClass = \Exception::class, string $message = ''): void
    {
        $method = debug_backtrace()[1]['function'] ?? 'unknown';
        $thrown = false;
        $actualClass = '';
        try {
            $fn();
        } catch (\Exception $e) {
            $thrown = true;
            $actualClass = get_class($e);
        }
        if ($thrown && is_a($actualClass, $expectedExceptionClass, true)) {
            $this->passed++;
        } else {
            $this->failed++;
            $msg = "{$method} - {$message}: 期望抛出 {$expectedExceptionClass}";
            if ($thrown) {
                $msg .= ", 实际抛出 {$actualClass}";
            } else {
                $msg .= ", 但未抛出任何异常";
            }
            $this->errors[] = $msg;
            echo "  ✗ {$msg}\n";
        }
    }

    protected function setUp(): void
    {
        $this->db->execute('DELETE FROM operation_logs');
        $this->db->execute('DELETE FROM fund_flows');
        $this->db->execute('DELETE FROM withholding_details');
    }

    public function run(): void
    {
        $className = static::class;
        echo "=== {$className} ===\n";

        $reflection = new \ReflectionClass($this);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            $name = $method->getName();
            if (strpos($name, 'test') === 0) {
                echo "  运行: {$name}\n";
                $this->setUp();
                $this->passedBefore = $this->passed;
                try {
                    $this->$name();
                } catch (\Throwable $e) {
                    $this->failed++;
                    $msg = "{$name} - 异常: " . $e->getMessage() . " ({$e->getFile()}:{$e->getLine()})";
                    $this->errors[] = $msg;
                    echo "  ✗ {$msg}\n";
                }
            }
        }

        $total = $this->passed + $this->failed;
        echo "  结果: {$this->passed}/{$total} 通过\n\n";
    }

    public function getPassed(): int { return $this->passed; }
    public function getFailed(): int { return $this->failed; }
    public function getErrors(): array { return $this->errors; }

    public function __destruct()
    {
        if (file_exists($this->testDbPath)) {
            @unlink($this->testDbPath);
        }
    }
}
