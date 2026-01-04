<?php
defined('_JEXEC') or die;

class pkg_socialpayInstallerScript
{
    public function install($parent)
    {
        JFactory::getApplication()->enqueueMessage('微信登录支付包安装成功！', 'success');
        return true;
    }
    
    public function uninstall($parent)
    {
        JFactory::getApplication()->enqueueMessage('微信登录支付包已卸载', 'message');
        return true;
    }
    
    public function update($parent)
    {
        JFactory::getApplication()->enqueueMessage('微信登录支付包更新成功', 'success');
        return true;
    }
    
    public function preflight($type, $parent)
    {
        // 检查Joomla版本
        if (version_compare(JVERSION, '5.0', '<')) {
            JFactory::getApplication()->enqueueMessage('需要Joomla 5.0或更高版本', 'error');
            return false;
        }
        
        // 检查PHP版本
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            JFactory::getApplication()->enqueueMessage('需要PHP 8.0或更高版本', 'error');
            return false;
        }
        
        return true;
    }
    
    public function postflight($type, $parent)
    {
        if ($type == 'install' || $type == 'update') {
            $db = JFactory::getDbo();
            
            // 启用系统插件
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('socialpayment'));
            $db->setQuery($query);
            $db->execute();
            
            // 启用认证插件
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('authentication'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('socialpay'));
            $db->setQuery($query);
            $db->execute();
            
            echo '<div class="alert alert-success" style="margin:20px;padding:20px;">
                <h3>🎉 微信登录支付包安装完成！</h3>
                <p><strong>版本：</strong>2.1.0</p>
                <p><strong>安装时间：</strong>' . date('Y-m-d H:i:s') . '</p>
                <hr>
                <h4>🎯 下一步操作：</h4>
                <ol>
                    <li>配置微信登录插件（系统 → 插件 → 认证 → Social Pay Authentication）</li>
                    <li>配置微信支付插件（系统 → 插件 → 系统 → Social Payment System）</li>
                    <li>发布登录模块（系统 → 站点模块 → 新建 → Social Login）</li>
                    <li>访问订单组件：<a href="index.php?option=com_socialorders" target="_blank">前台</a> | <a href="administrator/index.php?option=com_socialorders" target="_blank">后台</a></li>
                </ol>
            </div>';
        }
        return true;
    }
}