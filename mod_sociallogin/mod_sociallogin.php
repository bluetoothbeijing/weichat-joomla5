<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;

// 获取当前用户
$user = Factory::getUser();
$isLoggedIn = !$user->guest;

// 获取模块参数
$moduleclass_sfx = htmlspecialchars($params->get('moduleclass_sfx', ''));
$showWelcome = $params->get('show_welcome', 1);
$showAvatar = $params->get('show_avatar', 1);
$showWeChatLogin = $params->get('show_wechat_login', 1);
$redirectUrl = $params->get('redirect_url', '');

// 准备登录URL
$return = base64_encode(Uri::current());
$loginUrl = Route::_('index.php?option=com_users&view=login&return=' . $return);
$logoutUrl = Route::_('index.php?option=com_users&task=user.logout&' . JSession::getFormToken() . '=1&return=' . $return);

// 准备微信登录URL
$wechatLoginUrl = '';
if ($showWeChatLogin) {
    $wechatLoginUrl = $this->getWeChatLoginUrl($redirectUrl);
}

// 获取用户社交账号信息
$socialUser = null;
if ($isLoggedIn) {
    $socialUser = $this->getSocialUserInfo($user->id);
}

// 加载布局
require ModuleHelper::getLayoutPath('mod_sociallogin', $params->get('layout', 'default'));

/**
 * 获取用户社交账号信息
 */
function getSocialUserInfo($userId)
{
    $db = Factory::getDbo();
    
    $query = $db->getQuery(true)
        ->select('provider, provider_uid, nickname, avatar, is_primary')
        ->from('#__social_users')
        ->where('user_id = ' . (int)$userId)
        ->where('is_bound = 1')
        ->order('is_primary DESC');
    
    $db->setQuery($query);
    return $db->loadObjectList();
}

/**
 * 获取微信登录URL
 */
function getWeChatLoginUrl($redirectUrl = '')
{
    $plugin = JPluginHelper::getPlugin('authentication', 'socialpay');
    
    if (!$plugin) {
        return '';
    }
    
    $params = new JRegistry($plugin->params);
    $appId = $params->get('wechat_app_id');
    
    if (!$appId) {
        return '';
    }
    
    if (empty($redirectUrl)) {
        $redirectUrl = Uri::current();
    }
    
    $scope = $params->get('wechat_scope', 'snsapi_login');
    
    return 'https://open.weixin.qq.com/connect/qrconnect?appid=' . $appId 
        . '&redirect_uri=' . urlencode($redirectUrl)
        . '&response_type=code&scope=' . $scope
        . '&state=wechat_login#wechat_redirect';
}