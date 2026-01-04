<?php
defined('_JEXEC') or die;

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;

$app = Factory::getApplication();

// 检查访问权限
if (!$app->getIdentity()->authorise('core.manage', 'com_socialorders')) {
    throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
}

// 加载语言文件
$app->getLanguage()->load('com_socialorders', JPATH_ADMINISTRATOR);

// 获取请求参数
$task = $app->input->get('task', 'display');
$view = $app->input->get('view', 'orders');

// 设置默认控制器
$controllerClass = 'SocialordersController' . ucfirst($view);
$controllerFile = JPATH_COMPONENT_ADMINISTRATOR . '/controllers/' . strtolower($view) . '.php';

if (file_exists($controllerFile)) {
    require_once $controllerFile;
    
    if (class_exists($controllerClass)) {
        $controller = new $controllerClass();
    } else {
        throw new Exception(Text::sprintf('JLIB_APPLICATION_ERROR_INVALID_CONTROLLER_CLASS', $controllerClass));
    }
} else {
    // 使用默认控制器
    $controller = BaseController::getInstance('Socialorders');
}

// 执行任务
$controller->execute($task);
$controller->redirect();