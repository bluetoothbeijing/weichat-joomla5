-- ===========================================
-- 微信登录支付系统数据库表结构
-- 版本：2.1.0
-- 兼容：MySQL 8.0+, MariaDB 10.3+
-- ===========================================

-- 订单表
CREATE TABLE IF NOT EXISTS `#__social_orders` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no` varchar(50) NOT NULL COMMENT '订单号',
  `user_id` int UNSIGNED NOT NULL COMMENT '用户ID',
  `title` varchar(200) DEFAULT NULL COMMENT '订单标题',
  `description` text COMMENT '订单描述',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '订单金额',
  `real_amount` decimal(10,2) DEFAULT '0.00' COMMENT '实付金额',
  `currency` varchar(10) DEFAULT 'CNY' COMMENT '货币类型',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '订单状态',
  `payment_method` varchar(50) DEFAULT NULL COMMENT '支付方式',
  `payment_status` varchar(20) DEFAULT 'unpaid' COMMENT '支付状态',
  `transaction_id` varchar(100) DEFAULT NULL COMMENT '交易号',
  `payment_time` datetime DEFAULT NULL COMMENT '支付时间',
  `payment_data` text COMMENT '支付数据',
  `refund_amount` decimal(10,2) DEFAULT '0.00' COMMENT '退款金额',
  `refund_status` varchar(20) DEFAULT 'none' COMMENT '退款状态',
  `refund_time` datetime DEFAULT NULL COMMENT '退款时间',
  `refund_data` text COMMENT '退款数据',
  `shipping_status` varchar(20) DEFAULT 'unshipped' COMMENT '发货状态',
  `shipping_time` datetime DEFAULT NULL COMMENT '发货时间',
  `shipping_data` text COMMENT '发货数据',
  `customer_note` text COMMENT '客户备注',
  `admin_note` text COMMENT '管理员备注',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP地址',
  `user_agent` text COMMENT '用户代理',
  `params` json DEFAULT NULL COMMENT '扩展参数',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `created_by` int UNSIGNED DEFAULT NULL COMMENT '创建者',
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '修改时间',
  `modified_by` int UNSIGNED DEFAULT NULL COMMENT '修改者',
  `checked_out` int UNSIGNED DEFAULT NULL COMMENT '锁定用户',
  `checked_out_time` datetime DEFAULT NULL COMMENT '锁定时间',
  `publish_up` datetime DEFAULT NULL COMMENT '发布时间起',
  `publish_down` datetime DEFAULT NULL COMMENT '发布时间止',
  `ordering` int DEFAULT '0' COMMENT '排序',
  `state` tinyint DEFAULT '1' COMMENT '状态',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_order_no` (`order_no`),
  UNIQUE KEY `idx_transaction_id` (`transaction_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_created` (`created`),
  KEY `idx_payment_time` (`payment_time`),
  KEY `idx_amount` (`amount`),
  KEY `idx_state` (`state`),
  KEY `idx_ordering` (`ordering`),
  KEY `idx_publish_up` (`publish_up`),
  KEY `idx_publish_down` (`publish_down`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单表';

-- 支付记录表
CREATE TABLE IF NOT EXISTS `#__social_payments` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` int UNSIGNED NOT NULL COMMENT '订单ID',
  `trade_no` varchar(50) NOT NULL COMMENT '商户订单号',
  `transaction_id` varchar(100) DEFAULT NULL COMMENT '支付平台交易号',
  `payment_method` varchar(50) NOT NULL COMMENT '支付方式',
  `payment_type` varchar(20) DEFAULT 'normal' COMMENT '支付类型',
  `amount` decimal(10,2) NOT NULL COMMENT '支付金额',
  `currency` varchar(10) DEFAULT 'CNY' COMMENT '货币',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '支付状态',
  `prepay_id` varchar(100) DEFAULT NULL COMMENT '预支付ID',
  `code_url` varchar(500) DEFAULT NULL COMMENT '二维码URL',
  `mweb_url` varchar(500) DEFAULT NULL COMMENT 'H5支付URL',
  `app_id` varchar(50) DEFAULT NULL COMMENT '应用ID',
  `mch_id` varchar(50) DEFAULT NULL COMMENT '商户号',
  `payer_info` json DEFAULT NULL COMMENT '支付者信息',
  `payment_data` json DEFAULT NULL COMMENT '支付数据',
  `notify_data` text COMMENT '回调数据',
  `notify_time` datetime DEFAULT NULL COMMENT '回调时间',
  `refund_no` varchar(50) DEFAULT NULL COMMENT '退款单号',
  `refund_amount` decimal(10,2) DEFAULT '0.00' COMMENT '退款金额',
  `refund_status` varchar(20) DEFAULT 'none' COMMENT '退款状态',
  `refund_time` datetime DEFAULT NULL COMMENT '退款时间',
  `refund_data` text COMMENT '退款数据',
  `error_code` varchar(50) DEFAULT NULL COMMENT '错误代码',
  `error_msg` varchar(500) DEFAULT NULL COMMENT '错误信息',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP地址',
  `user_agent` text COMMENT '用户代理',
  `params` json DEFAULT NULL COMMENT '扩展参数',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '修改时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_trade_no` (`trade_no`),
  UNIQUE KEY `idx_transaction_id` (`transaction_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_payment_method` (`payment_method`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created`),
  KEY `idx_notify_time` (`notify_time`),
  KEY `idx_refund_status` (`refund_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='支付记录表';

-- 社交用户表
CREATE TABLE IF NOT EXISTS `#__social_users` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL COMMENT 'Joomla用户ID',
  `provider` varchar(50) NOT NULL COMMENT '社交平台',
  `provider_uid` varchar(255) NOT NULL COMMENT '平台用户ID',
  `unionid` varchar(100) DEFAULT NULL COMMENT 'UnionID',
  `nickname` varchar(100) DEFAULT NULL COMMENT '昵称',
  `realname` varchar(100) DEFAULT NULL COMMENT '真实姓名',
  `avatar` varchar(500) DEFAULT NULL COMMENT '头像',
  `gender` tinyint DEFAULT NULL COMMENT '性别',
  `birthday` date DEFAULT NULL COMMENT '生日',
  `country` varchar(50) DEFAULT NULL COMMENT '国家',
  `province` varchar(50) DEFAULT NULL COMMENT '省份',
  `city` varchar(50) DEFAULT NULL COMMENT '城市',
  `address` varchar(500) DEFAULT NULL COMMENT '地址',
  `mobile` varchar(20) DEFAULT NULL COMMENT '手机',
  `email` varchar(100) DEFAULT NULL COMMENT '邮箱',
  `bio` text COMMENT '个人简介',
  `access_token` text COMMENT '访问令牌',
  `refresh_token` text COMMENT '刷新令牌',
  `expires_in` int DEFAULT NULL COMMENT '过期时间(秒)',
  `expires_at` datetime DEFAULT NULL COMMENT '过期时间',
  `scope` varchar(500) DEFAULT NULL COMMENT '授权范围',
  `token_type` varchar(50) DEFAULT NULL COMMENT '令牌类型',
  `is_bound` tinyint DEFAULT '1' COMMENT '是否绑定',
  `is_primary` tinyint DEFAULT '0' COMMENT '是否主账号',
  `last_login` datetime DEFAULT NULL COMMENT '最后登录时间',
  `login_count` int DEFAULT '0' COMMENT '登录次数',
  `profile_data` json DEFAULT NULL COMMENT '完整资料',
  `params` json DEFAULT NULL COMMENT '扩展参数',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '修改时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_provider` (`user_id`,`provider`),
  UNIQUE KEY `idx_provider_uid` (`provider`,`provider_uid`),
  UNIQUE KEY `idx_unionid` (`unionid`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_provider` (`provider`),
  KEY `idx_is_bound` (`is_bound`),
  KEY `idx_is_primary` (`is_primary`),
  KEY `idx_last_login` (`last_login`),
  KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='社交用户表';

-- 微信配置表
CREATE TABLE IF NOT EXISTS `#__social_wechat_config` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` varchar(20) NOT NULL COMMENT '配置类型',
  `app_id` varchar(50) NOT NULL COMMENT '应用ID',
  `app_secret` varchar(100) DEFAULT NULL COMMENT '应用密钥',
  `mch_id` varchar(50) DEFAULT NULL COMMENT '商户号',
  `api_key` varchar(100) DEFAULT NULL COMMENT 'API密钥',
  `cert_path` varchar(500) DEFAULT NULL COMMENT '证书路径',
  `key_path` varchar(500) DEFAULT NULL COMMENT '密钥路径',
  `notify_url` varchar(500) DEFAULT NULL COMMENT '回调地址',
  `redirect_uri` varchar(500) DEFAULT NULL COMMENT '重定向地址',
  `scope` varchar(100) DEFAULT 'snsapi_userinfo' COMMENT '授权范围',
  `is_default` tinyint DEFAULT '0' COMMENT '是否默认',
  `status` tinyint DEFAULT '1' COMMENT '状态',
  `description` varchar(500) DEFAULT NULL COMMENT '描述',
  `params` json DEFAULT NULL COMMENT '扩展参数',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '修改时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_app_id` (`app_id`),
  UNIQUE KEY `idx_mch_id` (`mch_id`),
  KEY `idx_type` (`type`),
  KEY `idx_is_default` (`is_default`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='微信配置表';

-- 支付日志表
CREATE TABLE IF NOT EXISTS `#__social_payment_logs` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_id` int UNSIGNED DEFAULT NULL COMMENT '支付记录ID',
  `order_id` int UNSIGNED DEFAULT NULL COMMENT '订单ID',
  `log_type` varchar(50) NOT NULL COMMENT '日志类型',
  `action` varchar(100) NOT NULL COMMENT '操作',
  `message` text NOT NULL COMMENT '日志内容',
  `data` json DEFAULT NULL COMMENT '日志数据',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP地址',
  `user_agent` text COMMENT '用户代理',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `created_by` int UNSIGNED DEFAULT NULL COMMENT '创建者',
  PRIMARY KEY (`id`),
  KEY `idx_payment_id` (`payment_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_log_type` (`log_type`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='支付日志表';

-- 订单商品表
CREATE TABLE IF NOT EXISTS `#__social_order_items` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` int UNSIGNED NOT NULL COMMENT '订单ID',
  `product_id` int UNSIGNED DEFAULT NULL COMMENT '商品ID',
  `product_type` varchar(50) DEFAULT NULL COMMENT '商品类型',
  `product_name` varchar(200) NOT NULL COMMENT '商品名称',
  `product_sku` varchar(100) DEFAULT NULL COMMENT '商品SKU',
  `product_image` varchar(500) DEFAULT NULL COMMENT '商品图片',
  `quantity` int UNSIGNED NOT NULL DEFAULT '1' COMMENT '数量',
  `price` decimal(10,2) NOT NULL COMMENT '单价',
  `total` decimal(10,2) NOT NULL COMMENT '小计',
  `discount` decimal(10,2) DEFAULT '0.00' COMMENT '折扣',
  `tax` decimal(10,2) DEFAULT '0.00' COMMENT '税费',
  `shipping` decimal(10,2) DEFAULT '0.00' COMMENT '运费',
  `weight` decimal(10,2) DEFAULT '0.00' COMMENT '重量',
  `weight_unit` varchar(10) DEFAULT 'kg' COMMENT '重量单位',
  `specifications` json DEFAULT NULL COMMENT '规格',
  `params` json DEFAULT NULL COMMENT '扩展参数',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '修改时间',
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_product_type` (`product_type`),
  KEY `idx_product_sku` (`product_sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单商品表';

-- 订单地址表
CREATE TABLE IF NOT EXISTS `#__social_order_addresses` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` int UNSIGNED NOT NULL COMMENT '订单ID',
  `address_type` varchar(20) NOT NULL COMMENT '地址类型',
  `firstname` varchar(100) DEFAULT NULL COMMENT '名',
  `lastname` varchar(100) DEFAULT NULL COMMENT '姓',
  `company` varchar(200) DEFAULT NULL COMMENT '公司',
  `phone` varchar(20) DEFAULT NULL COMMENT '电话',
  `mobile` varchar(20) DEFAULT NULL COMMENT '手机',
  `email` varchar(100) DEFAULT NULL COMMENT '邮箱',
  `country` varchar(100) DEFAULT NULL COMMENT '国家',
  `state` varchar(100) DEFAULT NULL COMMENT '州/省',
  `city` varchar(100) DEFAULT NULL COMMENT '城市',
  `district` varchar(100) DEFAULT NULL COMMENT '区县',
  `address` varchar(500) DEFAULT NULL COMMENT '地址',
  `address2` varchar(500) DEFAULT NULL COMMENT '地址2',
  `postcode` varchar(20) DEFAULT NULL COMMENT '邮编',
  `latitude` decimal(10,8) DEFAULT NULL COMMENT '纬度',
  `longitude` decimal(11,8) DEFAULT NULL COMMENT '经度',
  `params` json DEFAULT NULL COMMENT '扩展参数',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_order_type` (`order_id`,`address_type`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_address_type` (`address_type`),
  KEY `idx_country` (`country`),
  KEY `idx_city` (`city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单地址表';

-- ===========================================
-- 初始数据
-- ===========================================

-- 插入默认微信配置
INSERT INTO `#__social_wechat_config` 
(`type`, `app_id`, `app_secret`, `mch_id`, `api_key`, `is_default`, `status`, `description`) 
VALUES 
('mp', 'your_mp_app_id', 'your_mp_app_secret', NULL, NULL, 1, 1, '微信公众号配置'),
('open', 'your_open_app_id', 'your_open_app_secret', NULL, NULL, 0, 1, '微信开放平台配置'),
('pay', 'your_pay_app_id', NULL, 'your_mch_id', 'your_api_key', 1, 1, '微信支付配置');

-- 创建索引优化
CREATE INDEX idx_composite_orders ON `#__social_orders` (`user_id`, `status`, `created`);
CREATE INDEX idx_composite_payments ON `#__social_payments` (`order_id`, `status`, `created`);
CREATE INDEX idx_composite_social_users ON `#__social_users` (`user_id`, `provider`, `is_bound`);

-- 创建视图
CREATE OR REPLACE VIEW `#__social_order_summary` AS
SELECT 
    o.id,
    o.order_no,
    o.user_id,
    o.title,
    o.amount,
    o.status,
    o.payment_status,
    o.payment_time,
    o.created,
    COUNT(i.id) as item_count,
    SUM(i.total) as items_total,
    u.name as customer_name,
    u.username as customer_username,
    u.email as customer_email
FROM `#__social_orders` o
LEFT JOIN `#__social_order_items` i ON o.id = i.order_id
LEFT JOIN `#__users` u ON o.user_id = u.id
WHERE o.state = 1
GROUP BY o.id;

-- ===========================================
-- 存储过程：更新订单统计
-- ===========================================
DELIMITER $$

CREATE PROCEDURE `UpdateOrderStats`(IN orderId INT)
BEGIN
    DECLARE itemsTotal DECIMAL(10,2);
    DECLARE itemsCount INT;
    
    -- 计算订单商品总金额和数量
    SELECT 
        COALESCE(SUM(total), 0),
        COALESCE(COUNT(id), 0)
    INTO itemsTotal, itemsCount
    FROM `#__social_order_items`
    WHERE order_id = orderId;
    
    -- 更新订单
    UPDATE `#__social_orders`
    SET 
        amount = itemsTotal,
        modified = NOW()
    WHERE id = orderId;
END$$

DELIMITER ;

-- ===========================================
-- 触发器：订单状态变化日志
-- ===========================================
DELIMITER $$

CREATE TRIGGER `trg_order_status_change`
AFTER UPDATE ON `#__social_orders`
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO `#__social_payment_logs`
        (order_id, log_type, action, message, created)
        VALUES
        (NEW.id, 'order_status', 'status_changed', 
         CONCAT('订单状态从 ', OLD.status, ' 变更为 ', NEW.status), 
         NOW());
    END IF;
    
    IF OLD.payment_status != NEW.payment_status THEN
        INSERT INTO `#__social_payment_logs`
        (order_id, log_type, action, message, created)
        VALUES
        (NEW.id, 'payment_status', 'status_changed', 
         CONCAT('支付状态从 ', OLD.payment_status, ' 变更为 ', NEW.payment_status), 
         NOW());
    END IF;
END$$

DELIMITER ;