<?php

require_once __DIR__ . '/../autoload.php';

use Tests\WithholdingCalculatorTest;
use Tests\StatusFlowTest;

echo "========================================\n";
echo "  电商订单库存后台 - 单元测试\n";
echo "========================================\n\n";

$totalPassed = 0;
$totalFailed = 0;
$allErrors = [];

$tests = [
    new WithholdingCalculatorTest(),
    new StatusFlowTest(),
];

foreach ($tests as $test) {
    $test->run();
    $totalPassed += $test->getPassed();
    $totalFailed += $test->getFailed();
    $allErrors = array_merge($allErrors, $test->getErrors());
}

$total = $totalPassed + $totalFailed;
echo "========================================\n";
echo "  测试汇总: {$totalPassed}/{$total} 通过";
if ($totalFailed > 0) {
    echo " ({$totalFailed} 失败)";
}
echo "\n";
echo "========================================\n";

if (!empty($allErrors)) {
    echo "\n详细错误:\n";
    foreach ($allErrors as $i => $error) {
        echo ($i + 1) . ". {$error}\n";
    }
}

exit($totalFailed > 0 ? 1 : 0);
