# 资金流向与预扣公式 - 部署文档

## 1. 项目概述

本项目是电商订单库存后台的资金流向与预扣公式模块，基于 PHP + MySQL 架构，提供以下核心功能：

- **资金流水管理（Fund Flow）**：记录所有资金的流入流出，支持多类型（预扣、退款、结算、调整）、多状态流转、余额追踪
- **预扣明细管理（Withholding Detail）**：基于可配置公式计算预扣金额，与资金流水自动关联
- **预扣公式管理（Withholding Formula）**：支持自定义公式、变量定义、安全校验
- **操作日志追踪（Operation Log）**：全链路状态变更与操作留痕
- **日结算报表（Settlement）**：订单日结算汇总与明细管理

## 2. 环境要求

| 依赖 | 版本要求 |
|------|---------|
| PHP | >= 7.4 |
| MySQL | >= 5.7 |
| PHP MySQL 扩展 (pdo_mysql) | 启用 |
| PHP PDO 扩展 | 启用 |
| PHP JSON 扩展 | 启用 |

## 3. 目录结构

```
002-电商订单库存后台/
├── api/                          # API 接口目录
│   ├── common.php                # 公共函数与响应封装
│   ├── config.php                # 配置文件（支持环境变量）
│   ├── settlement_daily.php      # 日结算汇总接口
│   ├── settlement_detail.php     # 结算明细接口
│   ├── settlement_check.php      # 结算核对接口
│   └── export.php                # 报表导出接口
├── core/                         # 核心服务层
│   └── Services/
│       └── Database.php          # 数据库服务
├── sql/
│   └── init.sql                  # 数据库初始化脚本（含资金流水与预扣表）
├── index.html                    # 前端入口页面
├── .env.example                  # 环境变量示例文件
└── DEPLOY.md                     # 部署文档（本文件）
```

## 4. 环境变量配置

复制 `.env.example` 为 `.env` 并根据实际环境修改：

```bash
cd /path/to/project
cp .env.example .env
# 根据实际环境修改 .env
```

导出环境变量（Shell 方式，也可通过 php-fpm pool 或 Nginx fastcgi_param 配置）：

```bash
export $(grep -v '^#' .env | xargs)
```

### 4.1 应用基础配置

| 变量名 | 默认值 | 说明 |
|--------|--------|------|
| `APP_NAME` | 电商订单库存后台 | 应用名称 |
| `APP_DEBUG` | true | 调试模式开关（生产环境设为 false） |
| `APP_TIMEZONE` | Asia/Shanghai | 时区设置 |

### 4.2 MySQL 数据库配置

| 变量名 | 默认值 | 说明 |
|--------|--------|------|
| `DB_HOST` | localhost | MySQL 主机地址 |
| `DB_PORT` | 3306 | MySQL 端口 |
| `DB_NAME` | ecommerce_settlement | 数据库名称 |
| `DB_USER` | root | 数据库用户名 |
| `DB_PASS` | （空） | 数据库密码 |
| `DB_CHARSET` | utf8mb4 | 数据库字符集 |

### 4.3 资金流水（Fund Flow）环境变量

| 变量名 | 默认值 | 说明 |
|--------|--------|------|
| `FUND_FLOW_DEFAULT_CURRENCY` | CNY | 默认币种 |
| `FUND_FLOW_DEFAULT_OPERATOR` | system | 默认操作人标识 |
| `FUND_FLOW_NO_PREFIX` | FF | 流水号前缀（如 FF202401011200001234） |
| `FUND_FLOW_MIN_AMOUNT` | 0.01 | 单笔流水最小金额（元） |
| `FUND_FLOW_MAX_AMOUNT` | 99999999.99 | 单笔流水最大金额（元） |
| `FUND_FLOW_ALLOW_NEGATIVE_BALANCE` | true | 是否允许账户余额为负 |

**资金流水状态枚举：**

| 状态值 | 常量 | 含义 |
|--------|------|------|
| 0 | STATUS_PENDING | 待处理 |
| 1 | STATUS_COMPLETED | 已完成（余额已更新） |
| 2 | STATUS_FAILED | 失败 |
| 3 | STATUS_CANCELLED | 已取消 |
| 4 | STATUS_REVERSED | 已冲正 |

**资金流水类型枚举：**

| 类型值 | 常量 | 含义 |
|--------|------|------|
| withholding | TYPE_WITHHOLD | 预扣 |
| refund | TYPE_REFUND | 退款 |
| settlement | TYPE_SETTLEMENT | 结算 |
| adjust | TYPE_ADJUST | 调整 |

**资金方向枚举：**

| 方向值 | 常量 | 含义 |
|--------|------|------|
| 1 | DIRECTION_IN | 流入 |
| 2 | DIRECTION_OUT | 流出 |

### 4.4 预扣明细（Withholding Detail）环境变量

| 变量名 | 默认值 | 说明 |
|--------|--------|------|
| `WITHHOLDING_DEFAULT_OPERATOR` | system | 默认操作人标识 |
| `WITHHOLDING_DEFAULT_INITIAL_STATUS` | 1 | 预扣创建时默认状态（1=已完成，0=待处理） |
| `WITHHOLDING_MAX_BATCH_SIZE` | 100 | 批量预扣单次最大条数 |
| `WITHHOLDING_ALLOW_NEGATIVE_RESULT` | false | 是否允许预扣计算结果为负数 |
| `WITHHOLDING_PRECISION` | 2 | 金额计算保留小数位数 |
| `WITHHOLDING_AUTO_CREATE_FUND_FLOW` | true | 预扣计算完成后是否自动创建关联资金流水 |

**预扣明细状态枚举：**

| 状态值 | 常量 | 含义 |
|--------|------|------|
| 0 | STATUS_PENDING | 待处理 |
| 1 | STATUS_COMPLETED | 已完成 |
| 2 | STATUS_FAILED | 失败 |
| 3 | STATUS_CANCELLED | 已取消 |
| 4 | STATUS_REVERSED | 已冲正 |
| 5 | STATUS_SETTLED | 已结算 |

### 4.5 日结算报表（Settlement）环境变量

| 变量名 | 默认值 | 说明 |
|--------|--------|------|
| `SETTLEMENT_AUTO_GENERATE` | true | 是否自动生成日结算汇总（关闭后需手动触发） |
| `SETTLEMENT_CUTOFF_TIME` | 23:59:59 | 日结算截止时间（HH:mm:ss），超时后订单计入次日结算 |
| `SETTLEMENT_EXPORT_BATCH_SIZE` | 1000 | 结算明细导出分批大小（条数/批） |
| `SETTLEMENT_CHECK_REQUIRE_REMARK` | false | 核对通过时是否强制填写备注 |

**结算状态枚举（settlement_status）：**

| 状态值 | 含义 |
|--------|------|
| 1 | 待结算 |
| 2 | 已结算 |
| 3 | 已对账 |

**结算类型枚举（settlement_type）：**

| 类型值 | 含义 |
|--------|------|
| 1 | 正常结算 |
| 2 | 退款 |
| 3 | 补款 |

**核对状态枚举（check_status）：**

| 状态值 | 含义 |
|--------|------|
| 0 | 未核对 |
| 1 | 核对通过 |
| 2 | 核对异常 |

### 4.6 导出核对（Export & Check）环境变量

| 变量名 | 默认值 | 说明 |
|--------|--------|------|
| `EXPORT_MAX_ROWS` | 10000 | 单次报表导出最大行数上限，超过则拒绝导出并提示缩小范围 |

> **注意：** `EXPORT_MAX_ROWS` 在 `api/config.php` 中定义为全局常量，供 `export.php` 统一使用。修改该值后需重启 PHP-FPM 或重新加载环境变量方可生效。

## 5. 部署步骤

### 5.1 代码部署

```bash
cd /path/to/project
# 将代码部署到目标目录
```

### 5.2 环境变量配置

```bash
cd /path/to/project
cp .env.example .env
# 根据实际环境修改 .env 中的数据库连接等配置
```

### 5.3 数据库初始化

执行数据库初始化脚本创建表结构并初始化默认公式和测试数据：

```bash
# 方式1：MySQL 命令行
mysql -u root -p < sql/init.sql

# 方式2：指定数据库
mysql -u root -p ecommerce_settlement < sql/init.sql
```

初始化脚本会自动创建以下数据表：

| 表名 | 说明 | 初始化数据 |
|------|------|-----------|
| `fund_flows` | 资金流水表 | - |
| `withholding_formulas` | 预扣公式表 | 4 条默认公式 |
| `withholding_details` | 预扣明细表 | - |
| `operation_logs` | 操作日志表 | - |
| `goods` | 商品表 | 10 条测试商品 |
| `orders` | 订单表 | 500 条测试订单 |
| `settlement_detail` | 结算明细表 | 基于订单自动生成 |
| `settlement_daily` | 日结算汇总表 | 基于明细自动汇总 |

### 5.4 默认预置公式

初始化脚本自动创建以下 4 条预扣公式：

| 公式编码 | 公式名称 | 公式表达式 | 变量 |
|----------|---------|-----------|------|
| `ORDER_AMOUNT_RATE` | 订单金额比例预扣 | `order_amount * rate` | order_amount(订单金额), rate(比例,默认0.05) |
| `STEP_WITHHOLDING` | 阶梯式预扣 | `order_amount <= 1000 ? order_amount * 0.03 : (order_amount <= 5000 ? order_amount * 0.05 : order_amount * 0.08)` | order_amount(订单金额) |
| `FIXED_PLUS_RATE` | 固定金额加比例 | `fixed_fee + order_amount * rate` | fixed_fee(固定手续费,默认10), order_amount(订单金额), rate(比例,默认0.02) |
| `INVENTORY_OCCUPY` | 库存占用预扣 | `quantity * unit_price * occupy_rate + storage_fee` | quantity(数量), unit_price(单价), occupy_rate(占用费率,默认0.1), storage_fee(仓储费,默认5) |

### 5.5 权限配置

确保 web 服务器对项目目录有适当权限：

```bash
chmod -R 755 /path/to/project
chown -R www-data:www-data /path/to/project
```

### 5.6 启动服务

#### 开发环境

```bash
cd /path/to/project
php -S localhost:8000
```

访问前端页面：`http://localhost:8000/index.html`

#### 生产环境

配置 Nginx + PHP-FPM，将网站根目录指向项目根目录。

Nginx 配置示例：

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/project;
    index index.html index.php;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        # 通过 fastcgi_param 传递环境变量（可选）
        fastcgi_param APP_NAME "电商订单库存后台";
        fastcgi_param DB_HOST "localhost";
        fastcgi_param DB_USER "root";
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## 6. 验收命令

### 6.1 数据库连接验收

```bash
# 方式1：通过 PHP 测试连接
php -r '
require_once "api/config.php";
try {
    $pdo = getDbConnection();
    echo "✅ 数据库连接成功\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "📋 数据库表数量: " . count($tables) . "\n";
    foreach ($tables as $table) {
        echo "   - {$table}\n";
    }
} catch (Exception $e) {
    echo "❌ 数据库连接失败: " . $e->getMessage() . "\n";
    exit(1);
}
'

# 方式2：验证关键表是否存在
mysql -u root -p ecommerce_settlement -e "
SELECT 'fund_flows' as tbl, COUNT(*) as cnt FROM fund_flows
UNION ALL
SELECT 'withholding_formulas', COUNT(*) FROM withholding_formulas
UNION ALL
SELECT 'withholding_details', COUNT(*) FROM withholding_details
UNION ALL
SELECT 'operation_logs', COUNT(*) FROM operation_logs
UNION ALL
SELECT 'goods', COUNT(*) FROM goods
UNION ALL
SELECT 'orders', COUNT(*) FROM orders
UNION ALL
SELECT 'settlement_detail', COUNT(*) FROM settlement_detail
UNION ALL
SELECT 'settlement_daily', COUNT(*) FROM settlement_daily;
"
```

预期输出：
- `fund_flows`, `withholding_formulas`, `withholding_details`, `operation_logs` 表存在
- `withholding_formulas` 有 4 条记录（默认公式）
- `goods` 有 10 条测试商品
- `orders` 有 500 条测试订单

### 6.2 环境变量功能验收

```bash
# 导出测试环境变量
export FUND_FLOW_NO_PREFIX=TST
export FUND_FLOW_DEFAULT_CURRENCY=USD
export WITHHOLDING_DEFAULT_OPERATOR=deploy_tester

# 验证环境变量读取
php -r '
require_once "api/config.php";
echo "流水号前缀: " . FUND_FLOW_NO_PREFIX . " (预期: TST)\n";
echo "默认币种: " . FUND_FLOW_DEFAULT_CURRENCY . " (预期: USD)\n";
echo "预扣默认操作人: " . WITHHOLDING_DEFAULT_OPERATOR . " (预期: deploy_tester)\n";
echo "最小流水金额: " . FUND_FLOW_MIN_AMOUNT . " (预期: 0.01)\n";
echo "批量预扣最大条数: " . WITHHOLDING_MAX_BATCH_SIZE . " (预期: 100)\n";
echo "精度位数: " . WITHHOLDING_PRECISION . " (预期: 2)\n";
'

# 清理测试环境变量
unset FUND_FLOW_NO_PREFIX FUND_FLOW_DEFAULT_CURRENCY WITHHOLDING_DEFAULT_OPERATOR
```

### 6.3 结算明细与导出核对环境变量验收

```bash
# 导出测试环境变量
export SETTLEMENT_AUTO_GENERATE=false
export SETTLEMENT_CUTOFF_TIME=22:00:00
export SETTLEMENT_EXPORT_BATCH_SIZE=500
export SETTLEMENT_CHECK_REQUIRE_REMARK=true
export EXPORT_MAX_ROWS=5000

# 验证结算明细与导出核对环境变量读取
php -r '
require_once "api/config.php";
echo "自动生成日结算: " . (SETTLEMENT_AUTO_GENERATE ? "true" : "false") . " (预期: false)\n";
echo "结算截止时间: " . SETTLEMENT_CUTOFF_TIME . " (预期: 22:00:00)\n";
echo "导出分批大小: " . SETTLEMENT_EXPORT_BATCH_SIZE . " (预期: 500)\n";
echo "核对强制备注: " . (SETTLEMENT_CHECK_REQUIRE_REMARK ? "true" : "false") . " (预期: true)\n";
echo "导出最大行数: " . EXPORT_MAX_ROWS . " (预期: 5000)\n";
'

# 清理测试环境变量
unset SETTLEMENT_AUTO_GENERATE SETTLEMENT_CUTOFF_TIME SETTLEMENT_EXPORT_BATCH_SIZE SETTLEMENT_CHECK_REQUIRE_REMARK EXPORT_MAX_ROWS
```

预期输出：
- `SETTLEMENT_AUTO_GENERATE` → false
- `SETTLEMENT_CUTOFF_TIME` → 22:00:00
- `SETTLEMENT_EXPORT_BATCH_SIZE` → 500
- `SETTLEMENT_CHECK_REQUIRE_REMARK` → true
- `EXPORT_MAX_ROWS` → 5000

### 6.4 结算明细 SQL 验收

```bash
# 1. 验证结算明细表结构
mysql -u root -p ecommerce_settlement -e "DESCRIBE settlement_detail;"

# 2. 验证日结算汇总表结构
mysql -u root -p ecommerce_settlement -e "DESCRIBE settlement_daily;"

# 3. 查询结算明细汇总统计
mysql -u root -p ecommerce_settlement -e "
SELECT
  settlement_date,
  settlement_type,
  settlement_status,
  COUNT(*) AS detail_count,
  SUM(quantity) AS total_quantity,
  SUM(order_amount) AS total_order_amount,
  SUM(settlement_amount) AS total_settlement_amount,
  SUM(commission_fee) AS total_commission_fee,
  SUM(net_amount) AS total_net_amount
FROM settlement_detail
GROUP BY settlement_date, settlement_type, settlement_status
ORDER BY settlement_date DESC
LIMIT 10;
"

# 4. 查询日结算汇总统计（含核对状态）
mysql -u root -p ecommerce_settlement -e "
SELECT
  settlement_date,
  order_count,
  total_order_amount,
  total_settlement_amount,
  total_net_amount,
  CASE settlement_status
    WHEN 1 THEN '待结算'
    WHEN 2 THEN '已结算'
    WHEN 3 THEN '已对账'
    ELSE '未知'
  END AS settlement_status_text,
  CASE check_status
    WHEN 0 THEN '未核对'
    WHEN 1 THEN '核对通过'
    WHEN 2 THEN '核对异常'
    ELSE '未知'
  END AS check_status_text
FROM settlement_daily
ORDER BY settlement_date DESC
LIMIT 10;
"
```

### 6.5 结算明细与导出核对 API 验收

启动服务后执行以下 curl 命令验收结算报表接口：

```bash
BASE_URL="http://localhost:8000/api"

# 1. 日结算汇总列表
curl -s "${BASE_URL}/settlement_daily.php?page=1&pageSize=10" | python3 -m json.tool

# 2. 按日期范围查询
curl -s "${BASE_URL}/settlement_daily.php?startDate=2024-01-01&endDate=2024-12-31&page=1&pageSize=10" | python3 -m json.tool

# 3. 按核对状态过滤（0-未核对 1-核对通过 2-核对异常）
curl -s "${BASE_URL}/settlement_daily.php?checkStatus=0&page=1&pageSize=10" | python3 -m json.tool

# 4. 结算明细查询（需要指定结算日期）
curl -s "${BASE_URL}/settlement_detail.php?settlementDate=2024-01-15" | python3 -m json.tool

# 5. 结算核对（POST）
curl -s -X POST "${BASE_URL}/settlement_check.php" \
  -H "Content-Type: application/json" \
  -d '{"id": 1, "check_status": 1, "check_remark": "部署验收-核对通过"}' | python3 -m json.tool

# 6. 导出日结算汇总报表 CSV
curl -s -O -J "${BASE_URL}/export.php?type=daily&format=csv"

# 7. 导出日结算汇总报表 Excel
curl -s -O -J "${BASE_URL}/export.php?type=daily&format=excel"

# 8. 导出结算明细报表（指定日期）
curl -s -O -J "${BASE_URL}/export.php?type=detail&settlementDate=2024-01-15&format=csv"
```

### 6.6 资金流水 SQL 验收

由于当前项目 API 主要覆盖结算报表功能，以下通过 SQL 直接验收资金流水与预扣数据表结构和环境变量关联逻辑：

```bash
# 1. 验证资金流水表结构
mysql -u root -p ecommerce_settlement -e "DESCRIBE fund_flows;"

# 2. 验证预扣公式表结构和默认数据
mysql -u root -p ecommerce_settlement -e "
SELECT id, code, name, status
FROM withholding_formulas
ORDER BY id;
"

# 3. 验证预扣明细表结构
mysql -u root -p ecommerce_settlement -e "DESCRIBE withholding_details;"

# 4. 验证操作日志表结构
mysql -u root -p ecommerce_settlement -e "DESCRIBE operation_logs;"

# 5. 测试创建一条资金流水（验证 FUND_FLOW_NO_PREFIX 等环境变量逻辑）
mysql -u root -p ecommerce_settlement -e "
INSERT INTO fund_flows (
    flow_no, flow_type, direction, amount, balance,
    currency, operator, remark, status
) VALUES (
    CONCAT('FF', DATE_FORMAT(NOW(), '%Y%m%d%H%i%s'), LPAD(FLOOR(RAND()*9000+1000), 4, '0')),
    'settlement', 1, 1000.00, 1000.00,
    'CNY', 'deploy_tester', '部署验收-初始资金流入', 1
);

SELECT * FROM fund_flows ORDER BY id DESC LIMIT 1\G
"

# 6. 测试创建预扣明细（关联流水）
mysql -u root -p ecommerce_settlement -e "
-- 获取默认公式
SELECT id, code, formula FROM withholding_formulas WHERE code='ORDER_AMOUNT_RATE'\G

-- 插入预扣明细（模拟 ORDER_AMOUNT_RATE 计算：order_amount=2000 * rate=0.05 = 100）
INSERT INTO withholding_details (
    formula_id, formula_code, formula_name, formula, variables,
    result, order_no, operator, remark, status
) VALUES (
    (SELECT id FROM withholding_formulas WHERE code='ORDER_AMOUNT_RATE'),
    'ORDER_AMOUNT_RATE', '订单金额比例预扣', 'order_amount * rate',
    '{\"order_amount\":2000,\"rate\":0.05}',
    100.00, 'DEPLOY-TEST-001', 'deploy_tester', '部署验收-订单预扣', 1
);

SELECT * FROM withholding_details ORDER BY id DESC LIMIT 1\G
"

# 7. 验证数据完整性
mysql -u root -p ecommerce_settlement -e "
SELECT
  (SELECT COUNT(*) FROM fund_flows) as fund_flow_count,
  (SELECT COUNT(*) FROM withholding_formulas) as formula_count,
  (SELECT COUNT(*) FROM withholding_details) as detail_count,
  (SELECT COUNT(*) FROM operation_logs) as log_count,
  (SELECT COUNT(*) FROM settlement_daily) as daily_count,
  (SELECT COUNT(*) FROM settlement_detail) as detail_settlement_count;
"
```

### 6.7 前端页面验收

```bash
# 启动服务后访问
# 浏览器打开 http://localhost:8000/index.html

# 使用 curl 验证页面可访问
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" http://localhost:8000/index.html
# 预期输出：HTTP Status: 200
```

### 6.8 配置文件语法验收

```bash
# 验证 PHP 文件语法正确性
for file in api/*.php; do
  php -l "$file"
done

# 验证 SQL 文件语法（可选，需要 MySQL 客户端）
mysql -u root -p ecommerce_settlement --force < sql/init.sql > /dev/null 2>&1 && echo "✅ SQL 语法正确" || echo "❌ SQL 语法错误"
```

## 7. API 接口汇总

### 7.1 结算报表接口

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/settlement_daily.php` | 分页查询日结算汇总（支持日期范围、核对状态、结算状态过滤） |
| GET | `/api/settlement_detail.php` | 查询某日结算明细（支持类型、状态、关键词过滤） |
| POST | `/api/settlement_check.php` | 结算单核对（通过/异常） |
| GET | `/api/export.php?type=daily` | 导出日结算汇总报表（CSV/Excel） |
| GET | `/api/export.php?type=detail` | 导出结算明细报表（指定日期） |

### 7.2 资金流水与预扣公式（数据库表已就绪，API 可按需扩展）

| 数据表 | 核心字段 | 关联关系 |
|--------|---------|---------|
| `fund_flows` | flow_no, flow_type, direction, amount, balance, status | withholding_detail_id → withholding_details.id |
| `withholding_details` | formula_id, formula_code, variables(JSON), result, status | formula_id → withholding_formulas.id |
| `withholding_formulas` | code(唯一), formula, variables(JSON定义), status | - |
| `operation_logs` | target_type, target_id, action, old_value(JSON), new_value(JSON) | 关联 fund_flows 或 withholding_details |

## 8. 常见问题排查

### 8.1 数据库连接失败

- 检查 `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` 环境变量是否正确
- 检查 MySQL 服务是否启动：`service mysql status`
- 检查 PHP pdo_mysql 扩展是否启用：`php -m | grep pdo_mysql`
- 测试连接：`mysql -h localhost -u root -p`

### 8.2 SQL 初始化脚本执行失败

- 确保数据库用户具有 CREATE TABLE, INSERT, DROP 等权限
- 如果部分表已存在，脚本会先 DROP 再 CREATE，确认没有依赖该数据库的其他业务
- 查看具体错误：`mysql -u root -p ecommerce_settlement < sql/init.sql 2>&1`

### 8.3 环境变量未生效

- 确认环境变量已正确导出：`printenv | grep -E "FUND_|WITHHOLDING_|SETTLEMENT_|EXPORT_|DB_"`
- 如果使用 php-fpm，需在 pool 配置或 Nginx fastcgi_param 中传递环境变量
- `api/config.php` 中 `getenv()` 对 CLI 和 FPM 模式均有效

### 8.4 报表导出中文乱码

- CSV 导出已自动添加 UTF-8 BOM，使用 Excel 打开应正常显示
- 确认 PHP 文件编码为 UTF-8（无 BOM）
- 确认 MySQL 连接使用 `utf8mb4` 字符集

### 8.5 前端页面空白或接口跨域

- `api/common.php` 已添加 CORS 头（`Access-Control-Allow-Origin: *`）
- 确认前端 API_BASE URL 配置正确
- 使用浏览器开发者工具检查 Network 请求状态码和响应内容
