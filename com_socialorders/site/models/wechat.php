<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;

class SocialordersModelWechat extends BaseModel
{
    private $appId;
    private $appSecret;
    private $mchId;
    private $apiKey;
    
    public function __construct($config = array())
    {
        parent::__construct($config);
        $this->loadWeChatConfig();
    }
    
    private function loadWeChatConfig()
    {
        $params = ComponentHelper::getParams('com_socialorders');
        
        $this->appId = $params->get('wechat_app_id');
        $this->appSecret = $params->get('wechat_app_secret');
        $this->mchId = $params->get('wechat_mch_id');
        $this->apiKey = $params->get('wechat_api_key');
    }
    
    public function getAccessTokenByCode($code)
    {
        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token';
        $params = [
            'appid' => $this->appId,
            'secret' => $this->appSecret,
            'code' => $code,
            'grant_type' => 'authorization_code'
        ];
        
        $response = $this->httpRequest($url, $params);
        $result = json_decode($response, true);
        
        if (isset($result['errcode'])) {
            throw new Exception('获取access_token失败：' . $result['errmsg'], $result['errcode']);
        }
        
        return $result;
    }
    
    public function getUserInfo($accessToken, $openId)
    {
        $url = 'https://api.weixin.qq.com/sns/userinfo';
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
        
        return $result;
    }
    
    public function getJsApiConfig($url)
    {
        $accessToken = $this->getClientAccessToken();
        
        // 获取jsapi_ticket
        $cache = Factory::getCache('wechat_ticket', 'callback');
        $cache->setCaching(true);
        $cache->setLifeTime(7000);
        
        $jsapiTicket = $cache->get(array($this, 'getJsApiTicket'));
        
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
    
    public function getJsApiTicket()
    {
        $accessToken = $this->getClientAccessToken();
        
        $url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket';
        $params = [
            'access_token' => $accessToken,
            'type' => 'jsapi'
        ];
        
        $response = $this->httpRequest($url, $params);
        $result = json_decode($response, true);
        
        if ($result['errcode'] == 0) {
            return $result['ticket'];
        }
        
        throw new Exception('获取jsapi_ticket失败：' . $result['errmsg']);
    }
    
    public function getClientAccessToken()
    {
        $cache = Factory::getCache('wechat_token', 'callback');
        $cache->setCaching(true);
        $cache->setLifeTime(7000);
        
        $accessToken = $cache->get(array($this, 'requestClientAccessToken'));
        
        return $accessToken;
    }
    
    public function requestClientAccessToken()
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/token';
        $params = [
            'grant_type' => 'client_credential',
            'appid' => $this->appId,
            'secret' => $this->appSecret
        ];
        
        $response = $this->httpRequest($url, $params);
        $result = json_decode($response, true);
        
        if (isset($result['access_token'])) {
            return $result['access_token'];
        }
        
        throw new Exception('获取access_token失败：' . ($result['errmsg'] ?? '未知错误'));
    }
    
    public function createPayment($orderData, $openId = null)
    {
        require_once JPATH_COMPONENT . '/helpers/wechatpay.php';
        
        $config = [
            'app_id' => $this->appId,
            'mch_id' => $this->mchId,
            'api_key' => $this->apiKey,
            'notify_url' => $this->getNotifyUrl()
        ];
        
        $wechatPay = new WeChatPay($config);
        
        if ($openId) {
            // JSAPI支付（公众号支付）
            return $wechatPay->createJsApiOrder($orderData, $openId);
        } else {
            // 扫码支付
            return $wechatPay->createNativeOrder($orderData);
        }
    }
    
    public function queryPayment($transactionId = '', $outTradeNo = '')
    {
        require_once JPATH_COMPONENT . '/helpers/wechatpay.php';
        
        $config = [
            'app_id' => $this->appId,
            'mch_id' => $this->mchId,
            'api_key' => $this->apiKey
        ];
        
        $wechatPay = new WeChatPay($config);
        
        return $wechatPay->queryOrder($transactionId, $outTradeNo);
    }
    
    public function refundPayment($refundData)
    {
        require_once JPATH_COMPONENT . '/helpers/wechatpay.php';
        
        $config = [
            'app_id' => $this->appId,
            'mch_id' => $this->mchId,
            'api_key' => $this->apiKey,
            'cert_path' => JPATH_COMPONENT . '/certs/apiclient_cert.pem',
            'key_path' => JPATH_COMPONENT . '/certs/apiclient_key.pem'
        ];
        
        $wechatPay = new WeChatPay($config);
        
        return $wechatPay->refund($refundData);
    }
    
    private function getNotifyUrl()
    {
        $root = JUri::root();
        return $root . 'index.php?option=com_socialorders&task=wechat.notify';
    }
    
    private function generateNonceStr($length = 16)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }
    
    private function httpRequest($url, $params = [], $method = 'GET')
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
            CURLOPT_USERAGENT => 'Joomla WeChat Payment/2.1.0'
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($params)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
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
}