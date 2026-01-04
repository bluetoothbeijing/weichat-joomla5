<?php
defined('_JEXEC') or die;

/**
 * 微信支付完整实现类
 * 支持：JSAPI支付、扫码支付、H5支付、小程序支付
 */
class WeChatPay
{
    private $appId;
    private $mchId;
    private $apiKey;
    private $certPath;
    private $keyPath;
    private $notifyUrl;
    
    const API_UNIFIEDORDER = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
    const API_ORDERQUERY = 'https://api.mch.weixin.qq.com/pay/orderquery';
    const API_CLOSEORDER = 'https://api.mch.weixin.qq.com/pay/closeorder';
    const API_REFUND = 'https://api.mch.weixin.qq.com/secapi/pay/refund';
    const API_REFUNDQUERY = 'https://api.mch.weixin.qq.com/pay/refundquery';
    const API_DOWNLOADBILL = 'https://api.mch.weixin.qq.com/pay/downloadbill';
    
    public function __construct($config = [])
    {
        $this->appId = $config['app_id'] ?? '';
        $this->mchId = $config['mch_id'] ?? '';
        $this->apiKey = $config['api_key'] ?? '';
        $this->certPath = $config['cert_path'] ?? '';
        $this->keyPath = $config['key_path'] ?? '';
        $this->notifyUrl = $config['notify_url'] ?? '';
    }
    
    /**
     * 设置证书路径
     */
    public function setCertPath($certPath, $keyPath)
    {
        $this->certPath = $certPath;
        $this->keyPath = $keyPath;
    }
    
    /**
     * 设置通知URL
     */
    public function setNotifyUrl($notifyUrl)
    {
        $this->notifyUrl = $notifyUrl;
    }
    
    /**
     * 统一下单（JSAPI支付）
     * @param array $order 订单信息
     * @param string $openId 用户openid
     * @return array
     * @throws Exception
     */
    public function createJsApiOrder($order, $openId)
    {
        $params = [
            'appid'            => $this->appId,
            'mch_id'           => $this->mchId,
            'nonce_str'        => $this->generateNonceStr(),
            'body'             => mb_substr($order['body'], 0, 128, 'UTF-8'),
            'out_trade_no'     => $order['trade_no'],
            'total_fee'        => intval($order['total_fee'] * 100),
            'spbill_create_ip' => $this->getClientIp(),
            'notify_url'       => $this->notifyUrl,
            'trade_type'       => 'JSAPI',
            'openid'           => $openId,
            'time_start'       => date('YmdHis'),
            'time_expire'      => date('YmdHis', time() + 3600),
            'attach'           => $order['attach'] ?? '',
            'detail'           => $this->formatDetail($order['detail'] ?? ''),
            'goods_tag'        => $order['goods_tag'] ?? '',
            'limit_pay'        => $order['limit_pay'] ?? '',
            'scene_info'       => $this->formatSceneInfo($order['scene_info'] ?? [])
        ];
        
        // 签名
        $params['sign'] = $this->generateSign($params);
        
        // 请求微信支付API
        $xml = $this->arrayToXml($params);
        $response = $this->postXmlCurl($xml, self::API_UNIFIEDORDER);
        $result = $this->xmlToArray($response);
        
        // 验证返回结果
        $this->validateResponse($result);
        
        // 生成JSAPI支付参数
        $jsApiParams = $this->getJsApiParams($result['prepay_id']);
        
        return [
            'prepay_id'    => $result['prepay_id'],
            'jsapi_params' => $jsApiParams,
            'mweb_url'     => $result['mweb_url'] ?? '',
            'code_url'     => $result['code_url'] ?? '',
            'trade_no'     => $order['trade_no'],
            'total_fee'    => $order['total_fee']
        ];
    }
    
    /**
     * 扫码支付（模式二）
     * @param array $order 订单信息
     * @return array
     * @throws Exception
     */
    public function createNativeOrder($order)
    {
        $params = [
            'appid'            => $this->appId,
            'mch_id'           => $this->mchId,
            'nonce_str'        => $this->generateNonceStr(),
            'body'             => mb_substr($order['body'], 0, 128, 'UTF-8'),
            'out_trade_no'     => $order['trade_no'],
            'total_fee'        => intval($order['total_fee'] * 100),
            'spbill_create_ip' => $this->getClientIp(),
            'notify_url'       => $this->notifyUrl,
            'trade_type'       => 'NATIVE',
            'product_id'       => $order['product_id'] ?? '',
            'time_start'       => date('YmdHis'),
            'time_expire'      => date('YmdHis', time() + 3600)
        ];
        
        $params['sign'] = $this->generateSign($params);
        
        $xml = $this->arrayToXml($params);
        $response = $this->postXmlCurl($xml, self::API_UNIFIEDORDER);
        $result = $this->xmlToArray($response);
        
        $this->validateResponse($result);
        
        return [
            'code_url'  => $result['code_url'],
            'prepay_id' => $result['prepay_id'],
            'trade_no'  => $order['trade_no']
        ];
    }
    
    /**
     * H5支付
     * @param array $order 订单信息
     * @return array
     * @throws Exception
     */
    public function createH5Order($order)
    {
        $params = [
            'appid'            => $this->appId,
            'mch_id'           => $this->mchId,
            'nonce_str'        => $this->generateNonceStr(),
            'body'             => mb_substr($order['body'], 0, 128, 'UTF-8'),
            'out_trade_no'     => $order['trade_no'],
            'total_fee'        => intval($order['total_fee'] * 100),
            'spbill_create_ip' => $this->getClientIp(),
            'notify_url'       => $this->notifyUrl,
            'trade_type'       => 'MWEB',
            'scene_info'       => $this->formatSceneInfo($order['scene_info'] ?? []),
            'time_start'       => date('YmdHis'),
            'time_expire'      => date('YmdHis', time() + 3600)
        ];
        
        $params['sign'] = $this->generateSign($params);
        
        $xml = $this->arrayToXml($params);
        $response = $this->postXmlCurl($xml, self::API_UNIFIEDORDER);
        $result = $this->xmlToArray($response);
        
        $this->validateResponse($result);
        
        return [
            'mweb_url'  => $result['mweb_url'],
            'prepay_id' => $result['prepay_id'],
            'trade_no'  => $order['trade_no']
        ];
    }
    
    /**
     * 查询订单状态
     * @param string $transactionId 微信订单号
     * @param string $outTradeNo 商户订单号
     * @return array
     * @throws Exception
     */
    public function queryOrder($transactionId = '', $outTradeNo = '')
    {
        $params = [
            'appid' => $this->appId,
            'mch_id' => $this->mchId,
            'nonce_str' => $this->generateNonceStr()
        ];
        
        if ($transactionId) {
            $params['transaction_id'] = $transactionId;
        } else {
            $params['out_trade_no'] = $outTradeNo;
        }
        
        $params['sign'] = $this->generateSign($params);
        
        $xml = $this->arrayToXml($params);
        $response = $this->postXmlCurl($xml, self::API_ORDERQUERY);
        $result = $this->xmlToArray($response);
        
        $this->validateResponse($result);
        
        return $result;
    }
    
    /**
     * 关闭订单
     * @param string $outTradeNo 商户订单号
     * @return array
     * @throws Exception
     */
    public function closeOrder($outTradeNo)
    {
        $params = [
            'appid' => $this->appId,
            'mch_id' => $this->mchId,
            'out_trade_no' => $outTradeNo,
            'nonce_str' => $this->generateNonceStr()
        ];
        
        $params['sign'] = $this->generateSign($params);
        
        $xml = $this->arrayToXml($params);
        $response = $this->postXmlCurl($xml, self::API_CLOSEORDER);
        $result = $this->xmlToArray($response);
        
        $this->validateResponse($result);
        
        return $result;
    }
    
    /**
     * 申请退款
     * @param array $refundData 退款数据
     * @return array
     * @throws Exception
     */
    public function refund($refundData)
    {
        if (empty($this->certPath) || empty($this->keyPath)) {
            throw new Exception('退款操作需要证书文件');
        }
        
        $params = [
            'appid' => $this->appId,
            'mch_id' => $this->mchId,
            'nonce_str' => $this->generateNonceStr(),
            'out_trade_no' => $refundData['out_trade_no'],
            'out_refund_no' => $refundData['out_refund_no'],
            'total_fee' => intval($refundData['total_fee'] * 100),
            'refund_fee' => intval($refundData['refund_fee'] * 100),
            'refund_desc' => $refundData['refund_desc'] ?? '',
            'notify_url' => $refundData['notify_url'] ?? $this->notifyUrl
        ];
        
        $params['sign'] = $this->generateSign($params);
        
        $xml = $this->arrayToXml($params);
        $response = $this->postXmlCurl($xml, self::API_REFUND, true);
        $result = $this->xmlToArray($response);
        
        $this->validateResponse($result);
        
        return $result;
    }
    
    /**
     * 查询退款
     * @param string $refundNo 退款单号
     * @return array
     * @throws Exception
     */
    public function queryRefund($refundNo)
    {
        $params = [
            'appid' => $this->appId,
            'mch_id' => $this->mchId,
            'nonce_str' => $this->generateNonceStr(),
            'out_refund_no' => $refundNo
        ];
        
        $params['sign'] = $this->generateSign($params);
        
        $xml = $this->arrayToXml($params);
        $response = $this->postXmlCurl($xml, self::API_REFUNDQUERY);
        $result = $this->xmlToArray($response);
        
        $this->validateResponse($result);
        
        return $result;
    }
    
    /**
     * 验证支付回调签名
     * @param array $data 回调数据
     * @return bool
     */
    public function verifyNotify($data)
    {
        if (!isset($data['sign'])) {
            return false;
        }
        
        $sign = $data['sign'];
        unset($data['sign']);
        
        return $this->generateSign($data) === $sign;
    }
    
    /**
     * 处理支付回调
     * @param callable $callback 回调函数
     * @return string XML响应
     */
    public function handleNotify($callback)
    {
        $xml = file_get_contents('php://input');
        $data = $this->xmlToArray($xml);
        
        // 验证签名
        if (!$this->verifyNotify($data)) {
            return $this->replyNotify('FAIL', '签名验证失败');
        }
        
        // 验证商户号
        if ($data['mch_id'] != $this->mchId) {
            return $this->replyNotify('FAIL', '商户号不匹配');
        }
        
        // 验证应用ID
        if ($data['appid'] != $this->appId) {
            return $this->replyNotify('FAIL', '应用ID不匹配');
        }
        
        try {
            // 执行回调处理
            $result = call_user_func($callback, $data);
            
            if ($result) {
                return $this->replyNotify('SUCCESS', 'OK');
            } else {
                return $this->replyNotify('FAIL', '处理失败');
            }
        } catch (Exception $e) {
            return $this->replyNotify('FAIL', $e->getMessage());
        }
    }
    
    /**
     * 生成JSAPI支付参数
     * @param string $prepayId 预支付ID
     * @return array
     */
    private function getJsApiParams($prepayId)
    {
        $params = [
            'appId'     => $this->appId,
            'timeStamp' => (string)time(),
            'nonceStr'  => $this->generateNonceStr(),
            'package'   => 'prepay_id=' . $prepayId,
            'signType'  => 'MD5'
        ];
        
        $params['paySign'] = $this->generateSign($params);
        
        return $params;
    }
    
    /**
     * 生成签名
     * @param array $data 待签名数据
     * @return string
     */
    private function generateSign($data)
    {
        // 过滤空值
        $data = array_filter($data, function($value) {
            return $value !== '' && $value !== null;
        });
        
        // 按键名排序
        ksort($data);
        
        // 拼接字符串
        $string = '';
        foreach ($data as $key => $value) {
            $string .= $key . '=' . $value . '&';
        }
        
        $string .= 'key=' . $this->apiKey;
        
        // MD5签名
        return strtoupper(md5($string));
    }
    
    /**
     * 验证响应结果
     * @param array $result
     * @throws Exception
     */
    private function validateResponse($result)
    {
        if (!isset($result['return_code']) || $result['return_code'] != 'SUCCESS') {
            throw new Exception('微信支付请求失败：' . ($result['return_msg'] ?? '未知错误'));
        }
        
        if (!isset($result['result_code']) || $result['result_code'] != 'SUCCESS') {
            $errorCode = $result['err_code'] ?? '';
            $errorMsg = $result['err_code_des'] ?? $result['return_msg'] ?? '未知错误';
            throw new Exception('微信支付下单失败：' . $errorMsg . ' (' . $errorCode . ')');
        }
    }
    
    /**
     * 回复通知
     * @param string $returnCode
     * @param string $returnMsg
     * @return string
     */
    private function replyNotify($returnCode, $returnMsg)
    {
        $data = [
            'return_code' => $returnCode,
            'return_msg'  => $returnMsg
        ];
        
        return $this->arrayToXml($data);
    }
    
    /**
     * 格式化商品详情
     * @param string|array $detail
     * @return string
     */
    private function formatDetail($detail)
    {
        if (is_array($detail)) {
            return json_encode($detail, JSON_UNESCAPED_UNICODE);
        }
        return $detail;
    }
    
    /**
     * 格式化场景信息
     * @param array $sceneInfo
     * @return string
     */
    private function formatSceneInfo($sceneInfo)
    {
        if (empty($sceneInfo)) {
            return '';
        }
        
        $default = [
            'h5_info' => [
                'type' => 'Wap',
                'wap_url' => JUri::root(),
                'wap_name' => JFactory::getConfig()->get('sitename')
            ]
        ];
        
        $sceneInfo = array_merge($default, $sceneInfo);
        return json_encode($sceneInfo, JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * 获取客户端IP
     * @return string
     */
    private function getClientIp()
    {
        $ip = '';
        
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }
        
        // 处理多级代理的情况
        $ipArr = explode(',', $ip);
        return trim($ipArr[0]);
    }
    
    /**
     * 生成随机字符串
     * @param int $length 长度
     * @return string
     */
    private function generateNonceStr($length = 32)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }
    
    /**
     * 数组转XML
     * @param array $data
     * @return string
     */
    private function arrayToXml($data)
    {
        $xml = '<xml>';
        foreach ($data as $key => $value) {
            if (is_numeric($value)) {
                $xml .= "<{$key}>{$value}</{$key}>";
            } else {
                $xml .= "<{$key}><![CDATA[{$value}]]></{$key}>";
            }
        }
        $xml .= '</xml>';
        return $xml;
    }
    
    /**
     * XML转数组
     * @param string $xml
     * @return array
     */
    private function xmlToArray($xml)
    {
        libxml_disable_entity_loader(true);
        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $data ?: [];
    }
    
    /**
     * 发送XML请求
     * @param string $xml XML数据
     * @param string $url 请求地址
     * @param bool $useCert 是否使用证书
     * @return string
     * @throws Exception
     */
    private function postXmlCurl($xml, $url, $useCert = false)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        if ($useCert) {
            if (!file_exists($this->certPath) || !file_exists($this->keyPath)) {
                throw new Exception('SSL证书文件不存在');
            }
            curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLCERT, $this->certPath);
            curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLKEY, $this->keyPath);
        }
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('CURL请求失败：' . $error);
        }
        
        if ($httpCode != 200) {
            throw new Exception('HTTP请求返回错误代码：' . $httpCode);
        }
        
        return $response;
    }
}