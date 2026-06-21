<?php
header('Content-Type: text/html; charset=utf-8');

function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    if ($value === 'true') return true;
    if ($value === 'false') return false;
    if ($value === 'null') return null;
    return $value;
}

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', (int)env('DB_PORT', 3306));
define('DB_NAME', env('DB_NAME', 'ecommerce_settlement'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

define('APP_NAME', env('APP_NAME', '电商订单库存后台'));
define('APP_DEBUG', env('APP_DEBUG', true));
define('APP_TIMEZONE', env('APP_TIMEZONE', 'Asia/Shanghai'));

define('FUND_FLOW_DEFAULT_CURRENCY', env('FUND_FLOW_DEFAULT_CURRENCY', 'CNY'));
define('FUND_FLOW_DEFAULT_OPERATOR', env('FUND_FLOW_DEFAULT_OPERATOR', 'system'));
define('FUND_FLOW_NO_PREFIX', env('FUND_FLOW_NO_PREFIX', 'FF'));
define('FUND_FLOW_MIN_AMOUNT', (float)env('FUND_FLOW_MIN_AMOUNT', 0.01));
define('FUND_FLOW_MAX_AMOUNT', (float)env('FUND_FLOW_MAX_AMOUNT', 99999999.99));
define('FUND_FLOW_ALLOW_NEGATIVE_BALANCE', env('FUND_FLOW_ALLOW_NEGATIVE_BALANCE', true));

define('WITHHOLDING_DEFAULT_OPERATOR', env('WITHHOLDING_DEFAULT_OPERATOR', 'system'));
define('WITHHOLDING_DEFAULT_INITIAL_STATUS', (int)env('WITHHOLDING_DEFAULT_INITIAL_STATUS', 1));
define('WITHHOLDING_MAX_BATCH_SIZE', (int)env('WITHHOLDING_MAX_BATCH_SIZE', 100));
define('WITHHOLDING_ALLOW_NEGATIVE_RESULT', env('WITHHOLDING_ALLOW_NEGATIVE_RESULT', false));
define('WITHHOLDING_PRECISION', (int)env('WITHHOLDING_PRECISION', 2));
define('WITHHOLDING_AUTO_CREATE_FUND_FLOW', env('WITHHOLDING_AUTO_CREATE_FUND_FLOW', true));

function getDbConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die(json_encode([
                'code' => 500,
                'msg'  => '数据库连接失败: ' . $e->getMessage(),
                'data' => null,
            ], JSON_UNESCAPED_UNICODE));
        }
    }
    return $pdo;
}
