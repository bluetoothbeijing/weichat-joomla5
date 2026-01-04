<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;

// 检查组件是否启用
$params = JComponentHelper::getParams('com_socialorders');
if (!$params->get('enable_orders', 1)) {
    echo '<div class="alert alert-warning">订单功能已禁用，请联系管理员</div>';
    return;
}

// 检查访问权限
$app = Factory::getApplication();
$user = Factory::getUser();

// 如果是前台访问
if ($app->isClient('site')) {
    // 检查用户是否登录
    $view = $app->input->get('view', 'socialorders');
    
    if ($view === 'order' && $user->guest) {
        // 重定向到登录页面
        $return = base64_encode(JUri::getInstance()->toString());
        $app->redirect(Route::_('index.php?option=com_users&view=login&return=' . $return, false));
        return;
    }
    
    // 加载前端控制器
    require_once JPATH_COMPONENT . '/site/controller.php';
    
    try {
        $controller = JControllerLegacy::getInstance('Socialorders');
        $controller->execute($app->input->get('task'));
        $controller->redirect();
    } catch (Exception $e) {
        $app->enqueueMessage($e->getMessage(), 'error');
        $app->redirect(Route::_('index.php', false));
    }
} 
// 如果是后台访问
elseif ($app->isClient('administrator')) {
    // 检查管理员权限
    if (!$user->authorise('core.manage', 'com_socialorders')) {
        $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
        return;
    }
    
    // 加载后端控制器
    require_once JPATH_COMPONENT . '/admin/socialorders.php';
}