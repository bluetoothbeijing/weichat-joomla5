<?php
defined('_JEXEC') or die;

class com_socialordersInstallerScript
{
    public function install($parent)
    {
        echo '<h3>Social Orders Component 安装成功</h3>';
        echo '<p>微信登录支付组件已成功安装！</p>';
        
        // 启用插件
        $this->enablePlugin('authentication', 'socialpay');
        $this->enablePlugin('system', 'socialpayment');
        
        return true;
    }
    
    public function uninstall($parent)
    {
        echo '<h3>Social Orders Component 已卸载</h3>';
        echo '<p>微信登录支付组件已成功移除。</p>';
        return true;
    }
    
    public function update($parent)
    {
        echo '<h3>Social Orders Component 更新成功</h3>';
        echo '<p>微信登录支付组件已更新到最新版本。</p>';
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
            echo '
            <div style="margin:20px;padding:20px;background:#f8f9fa;border:1px solid #dee2e6;border-radius:5px;">
                <h3>🎉 Social Orders 安装完成！</h3>
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
    
    private function enablePlugin($folder, $element)
    {
        $db = JFactory::getDbo();
        
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote($folder))
            ->where($db->quoteName('element') . ' = ' . $db->quote($element));
        
        $db->setQuery($query);
        $db->execute();
    }
}