<?php
header('Content-Type: text/html; charset=utf-8');

define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'ecommerce_settlement');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

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
