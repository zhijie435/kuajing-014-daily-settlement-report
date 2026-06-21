# 资金流向与预扣公式 - 部署文档

## 1. 项目概述

本项目是电商订单库存后台的资金流向与预扣公式模块，提供以下核心功能：

- **资金流水管理（Fund Flow）**：记录所有资金的流入流出，支持多类型（预扣、退款、结算、调整）、多状态流转、余额追踪
- **预扣明细管理（Withholding Detail）**：基于可配置公式计算预扣金额，与资金流水自动关联
- **预扣公式管理（Withholding Formula）**：支持自定义公式、变量定义、安全校验
- **操作日志追踪（Operation Log）**：全链路状态变更与操作留痕

## 2. 环境要求

| 依赖 | 版本要求 |
|------|---------|
| PHP | >= 7.4 |
| PHP SQLite 扩展 | 启用 |
| PHP PDO 扩展 | 启用 |
| PHP JSON 扩展 | 启用 |

## 3. 目录结构

```
002-电商订单库存后台/
├── backend/
│   ├── app/
│   │   ├── Controllers/        # 控制器层
│   │   │   ├── FundFlowController.php
│   │   │   ├── WithholdingController.php
│   │   │   └── WithholdingFormulaController.php
│   │   ├── Models/             # 模型层
│   │   │   ├── FundFlow.php
│   │   │   ├── WithholdingDetail.php
│   │   │   ├── WithholdingFormula.php
│   │   │   └── OperationLog.php
│   │   ├── Services/           # 服务层
│   │   │   ├── Database.php
│   │   │   ├── Router.php
│   │   │   └── WithholdingCalculator.php
│   │   └── Exceptions/
│   │       └── FormulaException.php
│   ├── config/
│   │   └── config.php           # 配置文件（支持环境变量）
│   ├── database/
│   │   ├── migrations/          # 数据库迁移脚本
│   │   ├── migrate.php          # 迁移执行器
│   │   ├── database.sqlite      # 生产数据库
│   │   └── test_database.sqlite # 测试数据库
│   ├── tests/                   # 单元测试
│   │   ├── run.php              # 测试入口
│   │   ├── WithholdingCalculatorTest.php
│   │   └── StatusFlowTest.php
│   ├── public/
│   │   └── index.php            # API 入口
│   └── autoload.php
├── frontend/                    # 前端静态资源
└── .env.example                 # 环境变量示例
```

## 4. 环境变量配置

复制 `.env.example` 为 `.env` 并根据实际环境修改：

```bash
cp .env.example .env
```

### 4.1 应用基础配置

| 变量名 | 默认值 | 说明 |
|--------|--------|------|
| `APP_NAME` | 电商订单库存后台 | 应用名称 |
| `APP_DEBUG` | true | 调试模式开关（生产环境设为 false） |
| `APP_TIMEZONE` | Asia/Shanghai | 时区设置 |

### 4.2 数据库配置

| 变量名 | 默认值 | 说明 |
|--------|--------|------|
| `DB_PATH` | backend/database/database.sqlite | SQLite 数据库文件绝对路径 |

### 4.3 CORS 跨域配置

| 变量名 | 默认值 | 说明 |
|--------|--------|------|
| `CORS_ALLOWED_ORIGINS` | * | 允许的来源域名，多个用逗号分隔 |
| `CORS_ALLOWED_METHODS` | GET,POST,PUT,DELETE,OPTIONS | 允许的 HTTP 方法 |
| `CORS_ALLOWED_HEADERS` | Content-Type,Authorization,X-Requested-With | 允许的请求头 |

### 4.4 资金流水（Fund Flow）环境变量

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

### 4.5 预扣明细（Withholding Detail）环境变量

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
# 根据实际环境修改 .env
```

### 5.3 导出环境变量

```bash
# 使用 shell 导出（或通过其他方式如 php-fpm pool 配置）
export $(grep -v '^#' .env | xargs)
```

### 5.4 数据库迁移

执行数据库迁移创建表结构并初始化默认公式：

```bash
cd backend
php database/migrate.php run
```

其他迁移命令：

```bash
# 查看迁移状态
php database/migrate.php status

# 回滚最近一批迁移
php database/migrate.php rollback

# 回滚最近 N 批
php database/migrate.php rollback 3
```

迁移会自动创建以下数据表：
- `withholding_formulas` - 预扣公式表（含4条默认公式）
- `withholding_details` - 预扣明细表
- `fund_flows` - 资金流水表
- `operation_logs` - 操作日志表

### 5.5 权限配置

确保 web 服务器对数据库目录有读写权限：

```bash
chmod -R 755 backend/database
chown -R www-data:www-data backend/database
```

### 5.6 启动服务

#### 开发环境

```bash
cd backend
php -S localhost:8000 -t public
```

#### 生产环境

配置 Nginx + PHP-FPM，将网站根目录指向 `backend/public`。

Nginx 配置示例：

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/project/backend/public;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## 6. 验收命令

### 6.1 单元测试（核心验收）

执行全部单元测试验证功能正确性：

```bash
cd backend
php tests/run.php
```

测试套件包含：

**WithholdingCalculatorTest（预扣计算器测试）：**
- 4 种默认公式计算验证（订单比例、阶梯预扣、固定+比例、库存占用）
- 边界值测试（阶梯区间边界值）
- 默认值参数测试
- 零金额/小数精度测试
- 异常场景测试（公式不存在、禁用公式、非法变量、负数结果、不安全公式）
- 预览模式不持久化测试
- 计算记录创建预扣明细和关联资金流水测试
- 公式验证器测试
- 初始状态配置测试
- 复杂算术表达式测试

**StatusFlowTest（状态流转测试）：**
- 资金流水状态流转合法性验证
- 预扣明细状态流转合法性验证
- 状态标签/Tag类型/描述文本验证
- 已完成流水更新余额测试
- 待处理流水不影响余额测试
- 预扣明细与资金流水联动状态变更测试
- 余额多笔流水累计计算测试
- 操作日志记录验证
- 终态不可转换测试
- 完整生命周期测试（待处理→已完成→已结算→已冲正）

### 6.2 数据库迁移验收

```bash
cd backend
php database/migrate.php status
```

预期输出：所有迁移显示 `✓ Ran`

### 6.3 API 健康检查

```bash
# 启动服务后
curl http://localhost:8000/api/dashboard
```

### 6.4 资金流水 API 验收

```bash
# 1. 查询流水类型枚举
curl http://localhost:8000/api/fund-flows/types

# 2. 创建一笔流入流水（结算）
curl -X POST http://localhost:8000/api/fund-flows \
  -H "Content-Type: application/json" \
  -d '{
    "flow_type": "settlement",
    "direction": 1,
    "amount": 1000.00,
    "operator": "tester",
    "remark": "验收测试-结算入账"
  }'

# 3. 创建一笔流出流水（预扣）
curl -X POST http://localhost:8000/api/fund-flows \
  -H "Content-Type: application/json" \
  -d '{
    "flow_type": "withholding",
    "direction": 2,
    "amount": 50.00,
    "order_no": "TEST20240101001",
    "operator": "tester",
    "remark": "验收测试-订单预扣"
  }'

# 4. 查询资金流水列表
curl "http://localhost:8000/api/fund-flows?page=1&per_page=20"

# 5. 查询资金统计
curl "http://localhost:8000/api/fund-flows/stats"

# 6. 查看单笔流水详情（使用返回的 id）
curl http://localhost:8000/api/fund-flows/1

# 7. 变更流水状态（已完成→已冲正）
curl -X PUT http://localhost:8000/api/fund-flows/2/status \
  -H "Content-Type: application/json" \
  -d '{
    "status": 4,
    "operator": "tester",
    "remark": "验收测试-冲正预扣流水"
  }'

# 8. 添加备注
curl -X PUT http://localhost:8000/api/fund-flows/1/remark \
  -H "Content-Type: application/json" \
  -d '{
    "remark": "验收测试-添加补充备注",
    "operator": "tester"
  }'

# 9. 查看操作日志
curl http://localhost:8000/api/fund-flows/1/logs
```

### 6.5 预扣明细 API 验收

```bash
# 1. 查询可用公式列表
curl http://localhost:8000/api/withholding-formulas/active

# 2. 预扣计算预览（不入库）
curl -X POST http://localhost:8000/api/withholding/preview \
  -H "Content-Type: application/json" \
  -d '{
    "formula_code": "ORDER_AMOUNT_RATE",
    "variables": {
      "order_amount": 1000,
      "rate": 0.05
    }
  }'

# 3. 执行预扣计算（入库并自动创建资金流水）
curl -X POST http://localhost:8000/api/withholding/calculate \
  -H "Content-Type: application/json" \
  -d '{
    "formula_code": "ORDER_AMOUNT_RATE",
    "variables": {
      "order_amount": 2000,
      "rate": 0.05
    },
    "order_no": "TEST-ORDER-001",
    "operator": "tester",
    "remark": "验收测试-订单比例预扣"
  }'

# 4. 阶梯预扣计算
curl -X POST http://localhost:8000/api/withholding/calculate \
  -H "Content-Type: application/json" \
  -d '{
    "formula_code": "STEP_WITHHOLDING",
    "variables": {
      "order_amount": 3000
    },
    "order_no": "TEST-ORDER-002",
    "operator": "tester"
  }'

# 5. 批量预扣计算
curl -X POST http://localhost:8000/api/withholding/batch-calculate \
  -H "Content-Type: application/json" \
  -d '{
    "items": [
      {
        "formula_code": "ORDER_AMOUNT_RATE",
        "variables": {"order_amount": 500, "rate": 0.05},
        "order_no": "BATCH-001",
        "operator": "tester"
      },
      {
        "formula_code": "FIXED_PLUS_RATE",
        "variables": {"fixed_fee": 10, "order_amount": 500, "rate": 0.02},
        "order_no": "BATCH-002",
        "operator": "tester"
      }
    ]
  }'

# 6. 查询预扣明细列表
curl "http://localhost:8000/api/withholding/details?page=1&per_page=20"

# 7. 查询预扣统计
curl http://localhost:8000/api/withholding/details/stats

# 8. 查看预扣明细详情（含关联资金流水）
curl http://localhost:8000/api/withholding/details/1

# 9. 变更预扣状态（待处理→已完成，同步关联流水）
curl -X PUT http://localhost:8000/api/withholding/details/1/status \
  -H "Content-Type: application/json" \
  -d '{
    "status": 1,
    "operator": "tester",
    "remark": "验收测试-确认预扣完成"
  }'

# 10. 结算预扣明细
curl -X PUT http://localhost:8000/api/withholding/details/1/status \
  -H "Content-Type: application/json" \
  -d '{
    "status": 5,
    "operator": "tester",
    "remark": "验收测试-完成最终结算"
  }'
```

### 6.6 环境变量功能验收

```bash
# 1. 测试自定义流水号前缀
export FUND_FLOW_NO_PREFIX=TST
php -r '
require "backend/autoload.php";
$f = new App\Models\FundFlow();
echo "Generated flow no: " . $f->generateFlowNo() . PHP_EOL;
'
# 预期输出以 TST 开头

# 2. 测试最小金额限制
export FUND_FLOW_MIN_AMOUNT=100
# 然后调用创建流水 API 传入 50 元，预期返回 AMOUNT_TOO_SMALL 错误

# 3. 测试余额限制
export FUND_FLOW_ALLOW_NEGATIVE_BALANCE=false
# 清空数据库后创建一笔流出流水，预期返回 INSUFFICIENT_BALANCE 错误

# 4. 测试批量大小限制
export WITHHOLDING_MAX_BATCH_SIZE=5
# 调用批量预扣 API 传入 10 条，预期返回 BATCH_ITEMS_TOO_MANY 错误

# 5. 测试默认初始状态
export WITHHOLDING_DEFAULT_INITIAL_STATUS=0
# 创建预扣后查看详情，预期 status=0（待处理）
```

## 7. 默认预置公式

迁移完成后自动创建以下 4 条公式：

| 公式编码 | 公式名称 | 公式表达式 | 变量 |
|----------|---------|-----------|------|
| `ORDER_AMOUNT_RATE` | 订单金额比例预扣 | `order_amount * rate` | order_amount(订单金额), rate(比例,默认0.05) |
| `STEP_WITHHOLDING` | 阶梯式预扣 | `order_amount <= 1000 ? order_amount * 0.03 : (order_amount <= 5000 ? order_amount * 0.05 : order_amount * 0.08)` | order_amount(订单金额) |
| `FIXED_PLUS_RATE` | 固定金额加比例 | `fixed_fee + order_amount * rate` | fixed_fee(固定手续费,默认10), order_amount(订单金额), rate(比例,默认0.02) |
| `INVENTORY_OCCUPY` | 库存占用预扣 | `quantity * unit_price * occupy_rate + storage_fee` | quantity(数量), unit_price(单价), occupy_rate(占用费率,默认0.1), storage_fee(仓储费,默认5) |

## 8. API 接口汇总

### 8.1 资金流水接口

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/fund-flows` | 分页查询流水列表 |
| GET | `/api/fund-flows/types` | 获取流水类型/方向/状态枚举 |
| GET | `/api/fund-flows/stats` | 获取资金统计汇总 |
| GET | `/api/fund-flows/{id}` | 获取流水详情 |
| GET | `/api/fund-flows/{id}/logs` | 获取流水操作日志 |
| POST | `/api/fund-flows` | 创建资金流水 |
| PUT | `/api/fund-flows/{id}/status` | 变更流水状态 |
| PUT | `/api/fund-flows/{id}/remark` | 添加流水备注 |

### 8.2 预扣明细接口

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | `/api/withholding/calculate` | 执行预扣计算（入库） |
| POST | `/api/withholding/preview` | 预扣计算预览（不入库） |
| POST | `/api/withholding/batch-calculate` | 批量预扣计算 |
| GET | `/api/withholding/details` | 分页查询预扣明细 |
| GET | `/api/withholding/details/status-types` | 获取预扣状态枚举 |
| GET | `/api/withholding/details/stats` | 获取预扣统计 |
| GET | `/api/withholding/details/{id}` | 获取预扣详情（含关联流水） |
| GET | `/api/withholding/details/{id}/logs` | 获取预扣操作日志 |
| PUT | `/api/withholding/details/{id}/status` | 变更预扣状态（同步关联流水） |
| PUT | `/api/withholding/details/{id}/remark` | 添加预扣备注 |

### 8.3 预扣公式接口

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/withholding-formulas` | 分页查询公式列表 |
| GET | `/api/withholding-formulas/active` | 获取所有启用的公式 |
| GET | `/api/withholding-formulas/{id}` | 获取公式详情 |
| POST | `/api/withholding-formulas` | 创建公式 |
| PUT | `/api/withholding-formulas/{id}` | 更新公式 |
| DELETE | `/api/withholding-formulas/{id}` | 删除公式 |
| POST | `/api/withholding-formulas/validate` | 验证公式合法性 |

## 9. 常见问题排查

### 9.1 数据库连接失败

- 检查 `DB_PATH` 路径是否存在且可写
- 检查 PHP SQLite 扩展是否启用：`php -m | grep sqlite`

### 9.2 跨域问题

- 确认 `CORS_ALLOWED_ORIGINS` 配置包含前端域名
- 确认 Nginx 正确处理 OPTIONS 请求

### 9.3 公式计算错误

- 检查公式变量是否全部传入
- 检查公式是否包含安全函数（eval/system 等被禁止）
- 检查计算结果是否为负数（默认禁止）

### 9.4 余额不正确

- 确认所有流水创建时状态为 `STATUS_COMPLETED` 才会影响余额
- 使用 `FUND_FLOW_ALLOW_NEGATIVE_BALANCE` 控制是否允许负余额
- 状态从非完成态转为完成态时会自动补记余额变动
