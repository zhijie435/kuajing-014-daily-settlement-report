-- 电商订单库存后台 - 日结算报表系统
-- 数据库初始化脚本

CREATE DATABASE IF NOT EXISTS `ecommerce_settlement` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ecommerce_settlement`;

-- 商品表
DROP TABLE IF EXISTS `goods`;
CREATE TABLE `goods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `goods_name` varchar(200) NOT NULL COMMENT '商品名称',
  `goods_no` varchar(50) NOT NULL COMMENT '商品编号',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '商品单价',
  `stock` int(11) NOT NULL DEFAULT '0' COMMENT '库存数量',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_goods_no` (`goods_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品表';

-- 订单表
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_no` varchar(32) NOT NULL COMMENT '订单编号',
  `goods_id` int(11) NOT NULL COMMENT '商品ID',
  `goods_name` varchar(200) NOT NULL COMMENT '商品名称',
  `goods_no` varchar(50) NOT NULL COMMENT '商品编号',
  `quantity` int(11) NOT NULL DEFAULT '1' COMMENT '购买数量',
  `unit_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '单价',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '订单总金额',
  `discount_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '优惠金额',
  `pay_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '实付金额',
  `order_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '订单状态：1-待付款 2-已付款 3-已发货 4-已完成 5-已取消',
  `pay_time` datetime DEFAULT NULL COMMENT '支付时间',
  `buyer_name` varchar(100) DEFAULT NULL COMMENT '买家名称',
  `buyer_phone` varchar(20) DEFAULT NULL COMMENT '买家电话',
  `receiver_name` varchar(100) DEFAULT NULL COMMENT '收货人',
  `receiver_phone` varchar(20) DEFAULT NULL COMMENT '收货电话',
  `receiver_address` varchar(500) DEFAULT NULL COMMENT '收货地址',
  `remark` varchar(500) DEFAULT NULL COMMENT '备注',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_order_no` (`order_no`),
  KEY `idx_goods_id` (`goods_id`),
  KEY `idx_order_status` (`order_status`),
  KEY `idx_pay_time` (`pay_time`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单表';

-- 结算明细表
DROP TABLE IF EXISTS `settlement_detail`;
CREATE TABLE `settlement_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `settlement_date` date NOT NULL COMMENT '结算日期',
  `order_id` int(11) NOT NULL COMMENT '订单ID',
  `order_no` varchar(32) NOT NULL COMMENT '订单编号',
  `goods_id` int(11) NOT NULL COMMENT '商品ID',
  `goods_name` varchar(200) NOT NULL COMMENT '商品名称',
  `goods_no` varchar(50) NOT NULL COMMENT '商品编号',
  `quantity` int(11) NOT NULL DEFAULT '0' COMMENT '数量',
  `unit_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '单价',
  `order_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '订单金额',
  `discount_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '优惠金额',
  `settlement_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '结算金额',
  `commission_fee` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '手续费/佣金',
  `net_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '净收入',
  `settlement_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '结算类型：1-正常结算 2-退款 3-补款',
  `settlement_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '结算状态：1-待结算 2-已结算 3-已对账',
  `remark` varchar(500) DEFAULT NULL COMMENT '备注',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_settlement_date` (`settlement_date`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_order_no` (`order_no`),
  KEY `idx_goods_id` (`goods_id`),
  KEY `idx_settlement_status` (`settlement_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='结算明细表';

-- 日结算汇总表
DROP TABLE IF EXISTS `settlement_daily`;
CREATE TABLE `settlement_daily` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `settlement_date` date NOT NULL COMMENT '结算日期',
  `order_count` int(11) NOT NULL DEFAULT '0' COMMENT '订单数量',
  `goods_count` int(11) NOT NULL DEFAULT '0' COMMENT '商品数量',
  `total_order_amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '订单总金额',
  `total_discount_amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '优惠总金额',
  `total_settlement_amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '结算总金额',
  `total_commission_fee` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '手续费总金额',
  `total_net_amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '净收入总额',
  `refund_count` int(11) NOT NULL DEFAULT '0' COMMENT '退款笔数',
  `refund_amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '退款金额',
  `settlement_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '结算状态：1-待结算 2-已结算 3-已对账',
  `check_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '核对状态：0-未核对 1-核对通过 2-核对异常',
  `check_remark` varchar(500) DEFAULT NULL COMMENT '核对备注',
  `checked_at` datetime DEFAULT NULL COMMENT '核对时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_settlement_date` (`settlement_date`),
  KEY `idx_settlement_status` (`settlement_status`),
  KEY `idx_check_status` (`check_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='日结算汇总表';

-- 插入测试商品数据
INSERT INTO `goods` (`goods_name`, `goods_no`, `price`, `stock`) VALUES
('苹果 iPhone 15 Pro 256G', 'IP15P-256', 8999.00, 100),
('华为 Mate 60 Pro 12+512G', 'HW-M60P-512', 6999.00, 150),
('小米 14 Ultra 16+512G', 'MI-14U-512', 5999.00, 200),
('OPPO Find X7 Ultra 16+512G', 'OPPO-FX7U-512', 5999.00, 120),
('vivo X100 Pro 12+256G', 'VIVO-X100P-256', 4999.00, 180),
('苹果 AirPods Pro 2', 'APP-APP2', 1899.00, 300),
('华为 FreeBuds Pro 3', 'HW-FBP3', 1299.00, 250),
('小米手环 8 Pro', 'MI-B8P', 399.00, 500),
('iPad Pro 11寸 256G WiFi', 'IPAD-P11-256', 6799.00, 80),
('MacBook Air 13寸 M3 256G', 'MBA-13-M3-256', 8999.00, 60);

-- 插入测试订单数据（最近30天）
DROP PROCEDURE IF EXISTS `generate_test_orders`;
DELIMITER $$
CREATE PROCEDURE `generate_test_orders`()
BEGIN
  DECLARE i INT DEFAULT 0;
  DECLARE order_date DATE;
  DECLARE order_no VARCHAR(32);
  DECLARE goods_id INT;
  DECLARE goods_name VARCHAR(200);
  DECLARE goods_no VARCHAR(50);
  DECLARE unit_price DECIMAL(10,2);
  DECLARE quantity INT;
  DECLARE total_amount DECIMAL(10,2);
  DECLARE discount_amount DECIMAL(10,2);
  DECLARE pay_amount DECIMAL(10,2);
  DECLARE order_status TINYINT;
  DECLARE pay_time DATETIME;
  DECLARE buyer_name VARCHAR(100);
  DECLARE buyer_phone VARCHAR(20);

  WHILE i < 500 DO
    SET order_date = DATE_SUB(CURDATE(), INTERVAL FLOOR(RAND() * 30) DAY);
    SET order_no = CONCAT('ORD', DATE_FORMAT(order_date, '%Y%m%d'), LPAD(FLOOR(RAND() * 10000), 5, '0'));
    
    SET goods_id = FLOOR(RAND() * 10) + 1;
    SELECT `goods_name`, `goods_no`, `price` INTO goods_name, goods_no, unit_price FROM `goods` WHERE `id` = goods_id;
    
    SET quantity = FLOOR(RAND() * 5) + 1;
    SET total_amount = unit_price * quantity;
    SET discount_amount = ROUND(total_amount * RAND() * 0.1, 2);
    SET pay_amount = total_amount - discount_amount;
    
    SET order_status = FLOOR(RAND() * 4) + 2;
    IF order_status >= 2 THEN
      SET pay_time = DATE_ADD(order_date, INTERVAL FLOOR(RAND() * 86400) SECOND);
    ELSE
      SET pay_time = NULL;
    END IF;
    
    SET buyer_name = CONCAT('用户', FLOOR(RAND() * 10000));
    SET buyer_phone = CONCAT('138', LPAD(FLOOR(RAND() * 100000000), 8, '0'));

    INSERT INTO `orders` (
      `order_no`, `goods_id`, `goods_name`, `goods_no`, `quantity`, 
      `unit_price`, `total_amount`, `discount_amount`, `pay_amount`, 
      `order_status`, `pay_time`, `buyer_name`, `buyer_phone`,
      `receiver_name`, `receiver_phone`, `receiver_address`, `remark`
    ) VALUES (
      order_no, goods_id, goods_name, goods_no, quantity,
      unit_price, total_amount, discount_amount, pay_amount,
      order_status, pay_time, buyer_name, buyer_phone,
      buyer_name, buyer_phone, '北京市朝阳区某某街道某某小区', '测试订单'
    );

    SET i = i + 1;
  END WHILE;
END$$
DELIMITER ;

CALL generate_test_orders();
DROP PROCEDURE IF EXISTS `generate_test_orders`;

-- 生成结算明细数据
INSERT INTO `settlement_detail` (
  `settlement_date`, `order_id`, `order_no`, `goods_id`, `goods_name`, `goods_no`,
  `quantity`, `unit_price`, `order_amount`, `discount_amount`, 
  `settlement_amount`, `commission_fee`, `net_amount`,
  `settlement_type`, `settlement_status`, `remark`
)
SELECT 
  DATE(o.pay_time) AS settlement_date,
  o.id AS order_id,
  o.order_no,
  o.goods_id,
  o.goods_name,
  o.goods_no,
  o.quantity,
  o.unit_price,
  o.total_amount,
  o.discount_amount,
  o.pay_amount AS settlement_amount,
  ROUND(o.pay_amount * 0.006, 2) AS commission_fee,
  ROUND(o.pay_amount * 0.994, 2) AS net_amount,
  1 AS settlement_type,
  2 AS settlement_status,
  '系统自动结算' AS remark
FROM `orders` o
WHERE o.order_status >= 2 AND o.pay_time IS NOT NULL
ORDER BY o.pay_time;

-- 生成日结算汇总数据
INSERT INTO `settlement_daily` (
  `settlement_date`, `order_count`, `goods_count`, 
  `total_order_amount`, `total_discount_amount`, 
  `total_settlement_amount`, `total_commission_fee`, `total_net_amount`,
  `refund_count`, `refund_amount`,
  `settlement_status`, `check_status`
)
SELECT 
  sd.settlement_date,
  COUNT(*) AS order_count,
  SUM(sd.quantity) AS goods_count,
  SUM(sd.order_amount) AS total_order_amount,
  SUM(sd.discount_amount) AS total_discount_amount,
  SUM(sd.settlement_amount) AS total_settlement_amount,
  SUM(sd.commission_fee) AS total_commission_fee,
  SUM(sd.net_amount) AS total_net_amount,
  0 AS refund_count,
  0.00 AS refund_amount,
  2 AS settlement_status,
  IF(RAND() > 0.7, 1, 0) AS check_status
FROM `settlement_detail` sd
GROUP BY sd.settlement_date
ORDER BY sd.settlement_date;

-- 为部分已核对的数据添加核对时间
UPDATE `settlement_daily` 
SET `checked_at` = DATE_ADD(`settlement_date`, INTERVAL 1 DAY),
    `check_remark` = '核对无误'
WHERE `check_status` = 1;
