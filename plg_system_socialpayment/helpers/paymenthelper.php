<?php
defined('_JEXEC') or die;

/**
 * 支付助手类
 */
class PlgSystemSocialpaymentHelperPaymenthelper
{
    public static function getPaymentConfig()
    {
        $plugin = JPluginHelper::getPlugin('system', 'socialpayment');
        $params = new JRegistry($plugin->params);
        
        return [
            'app_id' => $params->get('wechat_app_id'),
            'mch_id' => $params->get('wechat_mch_id'),
            'api_key' => $params->get('wechat_api_key')
        ];
    }
    
    public static function generateOrderNo($prefix = 'SO')
    {
        return $prefix . date('YmdHis') . rand(1000, 9999);
    }
}