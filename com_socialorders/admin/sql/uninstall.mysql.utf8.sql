-- ===========================================
-- 微信登录支付系统数据库卸载脚本
-- ===========================================

-- 删除表
DROP TABLE IF EXISTS `#__social_orders`;
DROP TABLE IF EXISTS `#__social_order_items`;
DROP TABLE IF EXISTS `#__social_order_addresses`;
DROP TABLE IF EXISTS `#__social_payments`;
DROP TABLE IF EXISTS `#__social_payment_logs`;
DROP TABLE IF EXISTS `#__social_users`;
DROP TABLE IF EXISTS `#__social_wechat_config`;

-- 删除视图
DROP VIEW IF EXISTS `#__social_order_summary`;

-- 删除存储过程
DROP PROCEDURE IF EXISTS `UpdateOrderStats`;

-- 删除触发器
DROP TRIGGER IF EXISTS `trg_order_status_change`;