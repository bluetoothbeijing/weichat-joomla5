<?php
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;

// 获取组件参数
$params = ComponentHelper::getParams('com_socialorders');

// 检查组件是否启用
if (!$params->get('enable_orders', 1)) {
    echo '<div class="alert alert-warning">订单功能已禁用</div>';
    return;
}

// 检查用户是否登录（如果需要）
$user = Factory::getUser();
$view = Factory::getApplication()->input->get('view', 'socialorders');

// 如果是订单详情页面，需要检查权限
if ($view === 'order' && !$user->guest) {
    $id = Factory::getApplication()->input->getInt('id');
    if ($id) {
        // 检查订单所属用户
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from('#__social_orders')
            ->where('id = ' . (int)$id)
            ->where('user_id = ' . (int)$user->id);
        $db->setQuery($query);
        
        if (!$db->loadResult()) {
            Factory::getApplication()->enqueueMessage('无权查看此订单', 'error');
            Factory::getApplication()->redirect(Route::_('index.php?option=com_socialorders', false));
            return;
        }
    }
}

// 加载前端控制器
require_once JPATH_COMPONENT . '/controller.php';

// 创建控制器实例
$controller = JControllerLegacy::getInstance('Socialorders');

// 执行任务
$controller->execute(Factory::getApplication()->input->get('task'));

// 重定向
$controller->redirect();