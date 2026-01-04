<?php
/**
 * @package     Socialorders
 * @subpackage  com_socialorders
 *
 * @copyright   Copyright (C) 2024. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Session\Session;

// 访问检查
$user = Factory::getUser();
if (!$user->authorise('core.manage', 'com_socialorders')) {
    $app = Factory::getApplication();
    $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
    $app->redirect('index.php');
    return;
}

// 引入组件助手
JLoader::register('SocialordersHelper', JPATH_COMPONENT . '/helpers/socialorders.php');

// 获取输入
$input = Factory::getApplication()->input;

// 获取视图和任务
$view   = $input->get('view', 'orders');
$task   = $input->get('task', 'display');
$format = $input->get('format', 'html');

// 设置视图
$input->set('view', $view);

// 如果任务中没有'.'，则根据视图添加前缀
if (strpos($task, '.') === false) {
    $input->set('task', $view . '.' . $task);
} else {
    list($controllerName, $taskName) = explode('.', $task);
    $input->set('controller', $controllerName);
    $input->set('task', $taskName);
}

// 获取控制器名称
$controllerName = $input->get('controller', $view);

// 根据控制器名称加载对应的控制器文件
$controllerPath = JPATH_COMPONENT . '/controllers/' . $controllerName . '.php';
if (file_exists($controllerPath)) {
    require_once $controllerPath;
} else {
    // 默认控制器
    require_once JPATH_COMPONENT . '/controller.php';
}

// 构建控制器类名
$controllerClass = 'SocialordersController' . ucfirst($controllerName);

// 检查控制器类是否存在
if (class_exists($controllerClass)) {
    $controller = new $controllerClass();
} else {
    // 回退到基础控制器
    require_once JPATH_COMPONENT . '/controller.php';
    $controller = new SocialordersController();
}

// 执行任务
$controller->execute($input->get('task'));

// 重定向
$controller->redirect();