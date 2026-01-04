<?php
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Router\Route;

class PlgSystemSocialpayment extends CMSPlugin
{
    protected $app;
    protected $db;
    protected $autoloadLanguage = true;
    
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->app = Factory::getApplication();
        $this->db = Factory::getDbo();
        
        // 初始化日志
        Log::addLogger(
            array('text_file' => 'socialpayment.php'),
            Log::ALL,
            array('socialpayment')
        );
    }
    
    public function onAfterInitialise()
    {
        if (!$this->params->get('enable_payment', 1)) {
            return;
        }
        
        // 处理支付回调
        $this->handlePaymentCallback();
        
        // 处理支付通知
        $this->handlePaymentNotify();
    }
    
    public function onAfterRoute()
    {
        // 添加支付JS库
        if ($this->app->isClient('site')) {
            $doc = Factory::getDocument();
            $option = $this->app->input->getCmd('option');
            
            if ($option === 'com_socialorders') {
                // 添加支付相关JS
                $doc->addScriptOptions('socialpayment', [
                    'site_url' => Uri::root(),
                    'ajax_url' => Route::_('index.php?option=com_ajax&plugin=socialpayment&format=json'),
                    'wechat_config' => $this->getWeChatConfig()
                ]);
                
                $doc->addScript(Uri::root(true) . '/plugins/system/socialpayment/js/payment.js');
            }
        }
    }
    
    public function onAjaxSocialpayment()
    {
        $task = $this->app->input->get('task', '', 'cmd');
        
        switch ($task) {
            case 'createPayment':
                return $this->createPayment();
            case 'queryPayment':
                return $this->queryPayment();
            case 'refund':
                return $this->processRefund();
            default:
                return json_encode(['error' => '未知操作']);
        }
    }
    
    private function handlePaymentCallback()
    {
        $option = $this->app->input->getCmd('option');
        $view = $this->app->input->getCmd('view');
        $task = $this->app->input->getCmd('task');
        
        if ($option === 'com_socialorders' && $view === 'wechat' && $task === 'callback') {
            $this->log('处理微信支付回调', 'info');
            
            require_once JPATH_PLUGINS . '/system/socialpayment/helpers/wechatpay.php';
            require_once JPATH_COMPONENT . '/helpers/wechatpay.php';
            
            $wechatPay = new WeChatPay($this->getWeChatPayConfig());
            
            $result = $wechatPay->handleNotify(function($data) {
                return $this->processPaymentNotify($data);
            });
            
            echo $result;
            $this->app->close();
        }
    }
    
    private function handlePaymentNotify()
    {
        if (!$this->params->get('auto_notify', 1)) {
            return;
        }
        
        $option = $this->app->input->getCmd('option');
        $task = $this->app->input->getCmd('task');
        
        if ($option === 'com_ajax' && $task === 'payment.notify') {
            $this->log('处理支付通知请求', 'info');
            
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            if ($data) {
                $this->processPaymentData($data);
            }
            
            echo json_encode(['success' => true]);
            $this->app->close();
        }
    }
    
    private function createPayment()
    {
        $this->checkAjaxToken();
        
        $orderId = $this->app->input->getInt('order_id');
        $paymentMethod = $this->app->input->get('payment_method', 'wechat', 'cmd');
        
        if (!$orderId) {
            return json_encode(['error' => '订单ID不能为空']);
        }
        
        try {
            // 加载订单信息
            $order = $this->getOrder($orderId);
            
            if (!$order) {
                throw new Exception('订单不存在');
            }
            
            if ($order->payment_status !== 'unpaid') {
                throw new Exception('订单支付状态异常');
            }
            
            switch ($paymentMethod) {
                case 'wechat':
                    $result = $this->createWeChatPayment($order);
                    break;
                case 'alipay':
                    $result = $this->createAlipayPayment($order);
                    break;
                default:
                    throw new Exception('不支持的支付方式');
            }
            
            // 更新订单支付信息
            $this->updateOrderPayment($orderId, $result);
            
            return json_encode([
                'success' => true,
                'data' => $result,
                'message' => '支付创建成功'
            ]);
            
        } catch (Exception $e) {
            $this->log('创建支付失败：' . $e->getMessage(), 'error');
            return json_encode(['error' => $e->getMessage()]);
        }
    }
    
    private function createWeChatPayment($order)
    {
        require_once JPATH_PLUGINS . '/system/socialpayment/helpers/wechatpay.php';
        
        $config = $this->getWeChatPayConfig();
        $wechatPay = new WeChatPay($config);
        
        // 生成商户订单号
        $tradeNo = 'WX' . date('YmdHis') . str_pad($order->id, 8, '0', STR_PAD_LEFT);
        
        $paymentData = [
            'body' => $order->title ?: '订单支付',
            'trade_no' => $tradeNo,
            'total_fee' => floatval($order->real_amount ?: $order->amount),
            'attach' => 'order_id=' . $order->id,
            'scene_info' => [
                'h5_info' => [
                    'type' => 'Wap',
                    'wap_url' => Uri::root(),
                    'wap_name' => $this->app->get('sitename')
                ]
            ]
        ];
        
        // 根据客户端类型选择支付方式
        $clientType = $this->detectClientType();
        
        switch ($clientType) {
            case 'wechat':
                // 微信公众号支付需要openid
                $openId = $this->getWeChatOpenId();
                if (!$openId) {
                    throw new Exception('请在微信中打开进行支付');
                }
                return $wechatPay->createJsApiOrder($paymentData, $openId);
                
            case 'mobile':
                return $wechatPay->createH5Order($paymentData);
                
            default:
                return $wechatPay->createNativeOrder($paymentData);
        }
    }
    
    private function processPaymentNotify($data)
    {
        $this->log('收到支付通知：' . json_encode($data), 'info');
        
        $tradeNo = $data['out_trade_no'];
        $transactionId = $data['transaction_id'];
        $totalFee = floatval($data['total_fee']) / 100; // 微信单位为分
        
        // 解析附加数据
        parse_str($data['attach'] ?? '', $attach);
        $orderId = $attach['order_id'] ?? 0;
        
        if (!$orderId) {
            $this->log('无法获取订单ID', 'error');
            return false;
        }
        
        // 验证订单金额
        $order = $this->getOrder($orderId);
        if (!$order) {
            $this->log('订单不存在：' . $orderId, 'error');
            return false;
        }
        
        $orderAmount = floatval($order->real_amount ?: $order->amount);
        if (abs($totalFee - $orderAmount) > 0.01) {
            $this->log('金额不匹配：订单' . $orderAmount . '，支付' . $totalFee, 'error');
            return false;
        }
        
        // 更新订单状态
        $updateData = [
            'id' => $orderId,
            'payment_status' => 'paid',
            'payment_time' => Factory::getDate()->toSql(),
            'transaction_id' => $transactionId,
            'status' => 'paid',
            'modified' => Factory::getDate()->toSql()
        ];
        
        if ($this->updateOrder($updateData)) {
            $this->log('订单支付成功：' . $orderId, 'info');
            
            // 保存支付记录
            $this->savePaymentRecord($orderId, $data);
            
            // 触发订单支付完成事件
            $this->triggerOrderPaid($orderId);
            
            return true;
        }
        
        return false;
    }
    
    private function getWeChatConfig()
    {
        return [
            'app_id' => $this->params->get('wechat_app_id', ''),
            'mch_id' => $this->params->get('wechat_mch_id', ''),
            'api_key' => $this->params->get('wechat_api_key', ''),
            'notify_url' => $this->params->get('wechat_notify_url', '')
        ];
    }
    
    private function getWeChatPayConfig()
    {
        $config = $this->getWeChatConfig();
        
        // 添加证书路径（如果存在）
        $certPath = JPATH_PLUGINS . '/system/socialpayment/certs/';
        if (is_dir($certPath)) {
            $config['cert_path'] = $certPath . 'apiclient_cert.pem';
            $config['key_path'] = $certPath . 'apiclient_key.pem';
        }
        
        return $config;
    }
    
    private function detectClientType()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (strpos($userAgent, 'MicroMessenger') !== false) {
            return 'wechat';
        }
        
        if (preg_match('/(android|iphone|ipad|mobile)/i', $userAgent)) {
            return 'mobile';
        }
        
        return 'pc';
    }
    
    private function getWeChatOpenId()
    {
        $session = $this->app->getSession();
        $openId = $session->get('wechat_openid', '');
        
        if (!$openId) {
            // 尝试从cookie获取
            $openId = $this->app->input->cookie->get('wechat_openid', '');
        }
        
        return $openId;
    }
    
    private function getOrder($orderId)
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from('#__social_orders')
            ->where('id = ' . (int)$orderId)
            ->where('state = 1');
        
        $this->db->setQuery($query);
        return $this->db->loadObject();
    }
    
    private function updateOrder($data)
    {
        return $this->db->updateObject('#__social_orders', (object)$data, 'id');
    }
    
    private function updateOrderPayment($orderId, $paymentData)
    {
        $updateData = [
            'id' => $orderId,
            'payment_method' => 'wechat',
            'modified' => Factory::getDate()->toSql()
        ];
        
        if (isset($paymentData['prepay_id'])) {
            $updateData['payment_data'] = json_encode($paymentData);
        }
        
        return $this->updateOrder($updateData);
    }
    
    private function savePaymentRecord($orderId, $paymentData)
    {
        $now = Factory::getDate()->toSql();
        $data = [
            'order_id' => $orderId,
            'trade_no' => $paymentData['out_trade_no'],
            'transaction_id' => $paymentData['transaction_id'],
            'payment_method' => 'wechat',
            'amount' => floatval($paymentData['total_fee']) / 100,
            'status' => 'paid',
            'payment_data' => json_encode($paymentData),
            'notify_data' => json_encode($paymentData),
            'notify_time' => $now,
            'created' => $now,
            'modified' => $now
        ];
        
        return $this->db->insertObject('#__social_payments', (object)$data);
    }
    
    private function triggerOrderPaid($orderId)
    {
        // 导入事件插件
        JPluginHelper::importPlugin('system');
        
        // 触发订单支付完成事件
        $this->app->triggerEvent('onSocialOrderPaid', [$orderId]);
    }
    
    private function checkAjaxToken()
    {
        $token = $this->app->getSession()->getFormToken();
        $inputToken = $this->app->input->get('token', '', 'alnum');
        
        if (!hash_equals($token, $inputToken)) {
            echo json_encode(['error' => '令牌无效']);
            $this->app->close();
        }
    }
    
    private function log($message, $level = 'info')
    {
        $logLevel = $this->params->get('log_level', 'error');
        $levels = ['debug' => 1, 'info' => 2, 'warning' => 3, 'error' => 4];
        
        if ($levels[$level] >= $levels[$logLevel]) {
            Log::add($message, constant('Log::' . strtoupper($level)), 'socialpayment');
        }
    }
}