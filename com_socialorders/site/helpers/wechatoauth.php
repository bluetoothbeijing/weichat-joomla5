<?php
defined('_JEXEC') or die;

/**
 * 微信OAuth2.0完整实现类
 * 支持：扫码登录、公众号登录、H5登录
 */
class WeChatOAuth
{
    private $appId;
    private $appSecret;
    private $accessToken;
    private $refreshToken;
    private $openId;
    
    const API_BASE = 'https://api.weixin.qq.com';
    const OAUTH_BASE = 'https://open.weixin.qq.com';
    
    public function __construct($appId, $appSecret)
    {
        $this->appId = $appId;
        $this->appSecret = $appSecret;
    }
    
    /**
     * 获取授权URL
     * @param string $redirectUri 回调地址
     * @param string $scope 授权作用域 (snsapi_base|snsapi_userinfo|snsapi_login)
     * @param string $state 自定义参数
     * @return string 授权URL
     */
    public function getAuthUrl($redirectUri, $scope = 'snsapi_login', $state = '')
    {
        $params = [
            'appid' => $this->appId,
            'redirect_uri' => urlencode($redirectUri),
            'response_type' => 'code',
            'scope' => $scope,
            'state' => $state ?: md5(uniqid())
        ];
        
        if ($scope === 'snsapi_login') {
            // 网站应用扫码登录
            $url = self::OAUTH_BASE . '/connect/qrconnect?' . http_build_query($params);
        } elseif ($scope === 'snsapi_userinfo' || $scope === 'snsapi_base') {
            // 公众号/H5授权登录
            $url = self::OAUTH_BASE . '/connect/oauth2/authorize?' . http_build_query($params);
        }
        
        return $url . '#wechat_redirect';
    }
    
    /**
     * 通过code获取access_token
     * @param string $code 授权码
     * @return array
     * @throws Exception
     */
    public function getAccessTokenByCode($code)
    {
        $url = self::API_BASE . '/sns/oauth2/access_token';
        $params = [
            'appid' => $this->appId,
            'secret' => $this->appSecret,
            'code' => $code,
            'grant_type' => 'authorization_code'
        ];
        
        $response = $this->httpRequest($url, $params);
        $result = json_decode($response, true);
        
        if (isset($result['errcode'])) {
            throw new Exception('微信登录失败：' . $result['errmsg'], $result['errcode']);
        }
        
        $this->accessToken = $result['access_token'];
        $this->openId = $result['openid'];
        $this->refreshToken = $result['refresh_token'];
        
        return $result;
    }
    
    /**
     * 刷新access_token
     * @param string $refreshToken
     * @return array
     */
    public function refreshAccessToken($refreshToken)
    {
        $url = self::API_BASE . '/sns/oauth2/refresh_token';
        $params = [
            'appid' => $this->appId,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken
        ];
        
        $response = $this->httpRequest($url, $params);
        return json_decode($response, true);
    }
    
    /**
     * 获取用户信息
     * @param string $accessToken
     * @param string $openId
     * @return array 用户信息
     * @throws Exception
     */
    public function getUserInfo($accessToken, $openId)
    {
        $url = self::API_BASE . '/sns/userinfo';
        $params = [
            'access_token' => $accessToken,
            'openid' => $openId,
            'lang' => 'zh_CN'
        ];
        
        $response = $this->httpRequest($url, $params);
        $result = json_decode($response, true);
        
        if (isset($result['errcode'])) {
            throw new Exception('获取用户信息失败：' . $result['errmsg']);
        }
        
        return [
            'openid' => $result['openid'],
            'unionid' => isset($result['unionid']) ? $result['unionid'] : '',
            'nickname' => $result['nickname'],
            'sex' => $result['sex'],
            'province' => $result['province'],
            'city' => $result['city'],
            'country' => $result['country'],
            'headimgurl' => $result['headimgurl'],
            'privilege' => $result['privilege']
        ];
    }
    
    /**
     * 获取用户基本信息（不依赖scope）
     * @param string $openId
     * @return array
     */
    public function getUserBaseInfo($openId)
    {
        $accessToken = $this->getClientAccessToken();
        
        $url = self::API_BASE . '/cgi-bin/user/info';
        $params = [
            'access_token' => $accessToken,
            'openid' => $openId,
            'lang' => 'zh_CN'
        ];
        
        $response = $this->httpRequest($url, $params);
        return json_decode($response, true);
    }
    
    /**
     * 获取客户端access_token（用于调用其他API）
     * @return string
     */
    private function getClientAccessToken()
    {
        $cache = JFactory::getCache('wechat_token', 'output');
        $cache->setCaching(true);
        $cache->setLifeTime(7000); // 微信token有效期为7200秒
        
        $accessToken = $cache->get('access_token');
        
        if (!$accessToken) {
            $url = self::API_BASE . '/cgi-bin/token';
            $params = [
                'grant_type' => 'client_credential',
                'appid' => $this->appId,
                'secret' => $this->appSecret
            ];
            
            $response = $this->httpRequest($url, $params);
            $result = json_decode($response, true);
            
            if (isset($result['access_token'])) {
                $accessToken = $result['access_token'];
                $cache->store($accessToken, 'access_token');
            }
        }
        
        return $accessToken;
    }
    
    /**
     * 验证access_token有效性
     * @param string $accessToken
     * @param string $openId
     * @return bool
     */
    public function validateToken($accessToken, $openId)
    {
        $url = self::API_BASE . '/sns/auth';
        $params = [
            'access_token' => $accessToken,
            'openid' => $openId
        ];
        
        $response = $this->httpRequest($url, $params);
        $result = json_decode($response, true);
        
        return isset($result['errmsg']) && $result['errmsg'] === 'ok';
    }
    
    /**
     * 获取JSSDK配置
     * @param string $url 当前页面URL
     * @return array JSSDK配置
     */
    public function getJsSdkConfig($url)
    {
        $accessToken = $this->getClientAccessToken();
        
        // 获取jsapi_ticket
        $cache = JFactory::getCache('wechat_ticket', 'output');
        $cache->setCaching(true);
        $cache->setLifeTime(7000);
        
        $jsapiTicket = $cache->get('jsapi_ticket');
        
        if (!$jsapiTicket) {
            $url = self::API_BASE . '/cgi-bin/ticket/getticket';
            $params = [
                'access_token' => $accessToken,
                'type' => 'jsapi'
            ];
            
            $response = $this->httpRequest($url, $params);
            $result = json_decode($response, true);
            
            if ($result['errcode'] == 0) {
                $jsapiTicket = $result['ticket'];
                $cache->store($jsapiTicket, 'jsapi_ticket');
            }
        }
        
        // 生成签名
        $nonceStr = $this->generateNonceStr();
        $timestamp = time();
        $string = "jsapi_ticket={$jsapiTicket}&noncestr={$nonceStr}&timestamp={$timestamp}&url={$url}";
        $signature = sha1($string);
        
        return [
            'appId' => $this->appId,
            'timestamp' => $timestamp,
            'nonceStr' => $nonceStr,
            'signature' => $signature,
            'jsApiList' => [
                'checkJsApi',
                'onMenuShareTimeline',
                'onMenuShareAppMessage',
                'chooseWXPay',
                'scanQRCode'
            ]
        ];
    }
    
    /**
     * 生成二维码场景值
     * @param string $sceneStr 场景值
     * @param int $expireSeconds 过期时间（秒）
     * @return array
     */
    public function createQrCode($sceneStr, $expireSeconds = 2592000)
    {
        $accessToken = $this->getClientAccessToken();
        $url = self::API_BASE . '/cgi-bin/qrcode/create?access_token=' . $accessToken;
        
        $data = [
            'action_name' => $expireSeconds > 0 ? 'QR_STR_SCENE' : 'QR_LIMIT_STR_SCENE',
            'action_info' => [
                'scene' => [
                    'scene_str' => $sceneStr
                ]
            ]
        ];
        
        if ($expireSeconds > 0) {
            $data['expire_seconds'] = $expireSeconds;
        }
        
        $response = $this->httpRequest($url, [], 'POST', json_encode($data));
        return json_decode($response, true);
    }
    
    /**
     * 获取二维码图片URL
     * @param string $ticket 二维码ticket
     * @return string 二维码图片URL
     */
    public function getQrCodeUrl($ticket)
    {
        return 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' . urlencode($ticket);
    }
    
    /**
     * 生成随机字符串
     * @param int $length 长度
     * @return string
     */
    private function generateNonceStr($length = 16)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }
    
    /**
     * HTTP请求
     * @param string $url
     * @param array $params
     * @param string $method
     * @param string $postData
     * @return string
     * @throws Exception
     */
    private function httpRequest($url, $params = [], $method = 'GET', $postData = '')
    {
        $ch = curl_init();
        
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($params)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            } elseif (!empty($postData)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($postData)
                ]);
            }
        }
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('HTTP请求失败：' . $error);
        }
        
        if ($httpCode != 200) {
            throw new Exception('HTTP请求返回错误代码：' . $httpCode);
        }
        
        return $response;
    }
    
    /**
     * 获取当前用户openid
     * @return string|null
     */
    public function getOpenId()
    {
        return $this->openId;
    }
    
    /**
     * 获取当前access_token
     * @return string|null
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }
}