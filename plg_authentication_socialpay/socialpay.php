<?php
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserHelper;
use Joomla\Registry\Registry;

class PlgAuthenticationSocialpay extends CMSPlugin
{
    protected $autoloadLanguage = true;
    protected $app;
    protected $db;
    
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->app = Factory::getApplication();
        $this->db = Factory::getDbo();
    }
    
    public function onUserAuthenticate($credentials, $options, &$response)
    {
        $response->type = 'SocialPay';
        
        // 检查是否是微信回调
        $code = $this->app->input->get('code', '', 'string');
        $state = $this->app->input->get('state', '', 'string');
        
        if ($code && $state === 'wechat_login') {
            try {
                $this->handleWeChatCallback($code, $response);
                return true;
            } catch (Exception $e) {
                $response->status = \Joomla\CMS\Authentication\Authentication::STATUS_FAILURE;
                $response->error_message = $e->getMessage();
                return false;
            }
        }
        
        $response->status = \Joomla\CMS\Authentication\Authentication::STATUS_FAILURE;
        $response->error_message = Text::_('PLG_AUTHENTICATION_SOCIALPAY_AUTH_FAILED');
        return false;
    }
    
    public function onUserLoginButtons($form)
    {
        $appId = $this->params->get('wechat_app_id');
        
        if (!$appId) {
            return [];
        }
        
        $currentUrl = Uri::getInstance()->toString();
        $redirectUri = urlencode($currentUrl);
        
        $authUrl = 'https://open.weixin.qq.com/connect/qrconnect?appid=' . $appId 
            . '&redirect_uri=' . $redirectUri 
            . '&response_type=code&scope=snsapi_login&state=wechat_login#wechat_redirect';
        
        $buttons[] = [
            'label'  => '<i class="fab fa-weixin"></i> ' . Text::_('PLG_AUTHENTICATION_SOCIALPAY_WECHAT_LOGIN'),
            'id'     => 'socialpay-wechat',
            'class'  => 'btn btn-success btn-block',
            'href'   => $authUrl,
            'onclick' => 'return true;',
            'modal'  => false,
            'target' => '_self'
        ];
        
        return $buttons;
    }
    
    private function handleWeChatCallback($code, &$response)
    {
        $appId = $this->params->get('wechat_app_id');
        $appSecret = $this->params->get('wechat_app_secret');
        
        if (!$appId || !$appSecret) {
            throw new Exception('微信配置不完整');
        }
        
        // 获取access_token
        $tokenData = $this->getWeChatAccessToken($appId, $appSecret, $code);
        
        if (isset($tokenData['errcode'])) {
            throw new Exception('微信登录失败：' . $tokenData['errmsg']);
        }
        
        // 获取用户信息
        $userInfo = $this->getWeChatUserInfo($tokenData['access_token'], $tokenData['openid']);
        
        // 查找或创建用户
        $userId = $this->findOrCreateUser($userInfo);
        
        if ($userId) {
            // 保存社交账号信息
            $this->saveSocialUser($userId, $userInfo, $tokenData);
            
            // 登录用户
            $this->loginUser($userId, $response);
            
            // 重定向到首页
            $this->app->redirect(Route::_('index.php', false));
            return true;
        }
        
        throw new Exception('用户创建失败');
    }
    
    private function getWeChatAccessToken($appId, $appSecret, $code)
    {
        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token';
        $params = [
            'appid' => $appId,
            'secret' => $appSecret,
            'code' => $code,
            'grant_type' => 'authorization_code'
        ];
        
        $response = $this->httpRequest($url, $params);
        return json_decode($response, true);
    }
    
    private function getWeChatUserInfo($accessToken, $openId)
    {
        $url = 'https://api.weixin.qq.com/sns/userinfo';
        $params = [
            'access_token' => $accessToken,
            'openid' => $openId,
            'lang' => 'zh_CN'
        ];
        
        $response = $this->httpRequest($url, $params);
        $data = json_decode($response, true);
        
        if (isset($data['errcode'])) {
            throw new Exception('获取用户信息失败：' . $data['errmsg']);
        }
        
        return $data;
    }
    
    private function findOrCreateUser($socialUser)
    {
        $db = $this->db;
        
        // 1. 检查是否已有绑定记录
        $query = $db->getQuery(true)
            ->select('user_id')
            ->from('#__social_users')
            ->where('provider = ' . $db->quote('wechat'))
            ->where('provider_uid = ' . $db->quote($socialUser['openid']))
            ->where('is_bound = 1');
        
        $db->setQuery($query);
        $userId = $db->loadResult();
        
        if ($userId) {
            return $userId;
        }
        
        // 2. 创建新用户
        return $this->createNewUser($socialUser);
    }
    
    private function createNewUser($socialUser)
    {
        $user = new User();
        
        // 生成唯一用户名
        $username = 'wx_' . substr(md5($socialUser['openid']), 0, 8);
        $email = $socialUser['openid'] . '@wechat.com';
        
        $userData = [
            'name'      => $socialUser['nickname'] ?: $username,
            'username'  => $username,
            'email'     => $email,
            'password'  => UserHelper::genRandomPassword(),
            'password2' => UserHelper::genRandomPassword(),
            'block'     => 0,
            'sendEmail' => 0,
            'groups'    => [2] // 注册用户组
        ];
        
        if (!$user->bind($userData)) {
            throw new Exception('绑定用户数据失败：' . $user->getError());
        }
        
        if (!$user->save()) {
            throw new Exception('保存用户失败：' . $user->getError());
        }
        
        return $user->id;
    }
    
    private function saveSocialUser($userId, $userInfo, $tokenData)
    {
        $db = $this->db;
        $now = Factory::getDate()->toSql();
        
        $data = [
            'user_id'       => (int)$userId,
            'provider'      => 'wechat',
            'provider_uid'  => $userInfo['openid'],
            'nickname'      => $userInfo['nickname'],
            'avatar'        => $userInfo['headimgurl'],
            'access_token'  => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'],
            'expires_in'    => $tokenData['expires_in'],
            'is_bound'      => 1,
            'last_login'    => $now,
            'created'       => $now,
            'modified'      => $now
        ];
        
        // 检查是否已存在
        $query = $db->getQuery(true)
            ->select('id')
            ->from('#__social_users')
            ->where('user_id = ' . (int)$userId)
            ->where('provider = ' . $db->quote('wechat'));
        
        $db->setQuery($query);
        $existingId = $db->loadResult();
        
        if ($existingId) {
            // 更新
            $data['id'] = $existingId;
            $db->updateObject('#__social_users', (object)$data, 'id');
        } else {
            // 插入
            $db->insertObject('#__social_users', (object)$data);
        }
    }
    
    private function loginUser($userId, &$response)
    {
        $user = Factory::getUser($userId);
        
        // 设置用户到session
        $this->app->loadIdentity($user);
        
        // 准备登录选项
        $options = [
            'action'   => 'core.login.site',
            'remember' => $this->params->get('remember_login', 1),
            'silent'   => false,
            'provider' => 'wechat'
        ];
        
        // 准备用户数据
        $userData = [
            'username' => $user->username,
            'password' => '', // 社交登录不需要密码
            'fullname' => $user->name,
            'email'    => $user->email,
            'id'       => $user->id
        ];
        
        // 触发登录事件
        $results = $this->app->triggerEvent('onUserLogin', [$userData, $options]);
        
        // 检查登录结果
        foreach ($results as $result) {
            if ($result === true || (is_array($result) && isset($result['status']) && $result['status'] === true)) {
                $response->status = \Joomla\CMS\Authentication\Authentication::STATUS_SUCCESS;
                $response->error_message = '';
                $response->username = $user->username;
                $response->fullname = $user->name;
                $response->email = $user->email;
                $response->type = 'SocialPay';
                return true;
            }
        }
        
        throw new Exception('登录失败');
    }
    
    private function httpRequest($url, $params = [], $method = 'GET')
    {
        $ch = curl_init();
        
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'Joomla SocialPay/2.1.0'
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('HTTP请求失败：' . $error);
        }
        
        return $response;
    }
}