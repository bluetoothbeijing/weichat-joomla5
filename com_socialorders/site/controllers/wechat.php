<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Session\Session;

class SocialordersControllerWechat extends BaseController
{
    public function auth()
    {
        $app = Factory::getApplication();
        $code = $app->input->get('code', '', 'string');
        $state = $app->input->get('state', '', 'string');
        
        if ($code && $state === 'wechat_login') {
            try {
                // 获取微信用户信息
                $model = $this->getModel('Wechat', 'SocialordersModel');
                $tokenData = $model->getAccessTokenByCode($code);
                $userInfo = $model->getUserInfo($tokenData['access_token'], $tokenData['openid']);
                
                // 保存到session
                $session = $app->getSession();
                $session->set('wechat_user', $userInfo);
                $session->set('wechat_access_token', $tokenData['access_token']);
                $session->set('wechat_openid', $tokenData['openid']);
                
                // 设置cookie（可选）
                $app->input->cookie->set('wechat_openid', $tokenData['openid'], time() + 3600, '/');
                
                // 跳转到订单页面或返回页面
                $return = $session->get('wechat_return', Route::_('index.php?option=com_socialorders', false));
                $this->setRedirect($return);
                
            } catch (Exception $e) {
                $app->enqueueMessage($e->getMessage(), 'error');
                $this->setRedirect(Route::_('index.php?option=com_socialorders', false));
            }
        } else {
            // 没有code参数，重定向到微信授权页面
            $this->redirectToWeChatAuth();
        }
    }
    
    public function pay()
    {
        $app = Factory::getApplication();
        $orderId = $app->input->getInt('order_id');
        
        if (!$orderId) {
            $app->enqueueMessage('订单ID不能为空', 'error');
            $this->setRedirect(Route::_('index.php?option=com_socialorders', false));
            return false;
        }
        
        // 获取订单信息
        $orderModel = $this->getModel('Order', 'SocialordersModel');
        $order = $orderModel->getItem($orderId);
        
        if (!$order) {
            $app->enqueueMessage('订单不存在', 'error');
            $this->setRedirect(Route::_('index.php?option=com_socialorders', false));
            return false;
        }
        
        // 检查订单支付状态
        if ($order->payment_status !== 'unpaid') {
            $app->enqueueMessage('订单已支付或已关闭', 'error');
            $this->setRedirect(Route::_('index.php?option=com_socialorders&view=order&id=' . $orderId, false));
            return false;
        }
        
        // 获取微信支付配置
        $wechatModel = $this->getModel('Wechat', 'SocialordersModel');
        
        try {
            // 获取openid（如果是微信浏览器）
            $openId = null;
            if (strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'MicroMessenger') !== false) {
                $openId = $app->getSession()->get('wechat_openid', '');
                if (!$openId) {
                    // 需要微信授权获取openid
                    $this->redirectToWeChatAuth('snsapi_base', 'wechat_pay_' . $orderId);
                    return false;
                }
            }
            
            // 创建支付订单
            $paymentData = [
                'body' => $order->title ?: '订单支付',
                'trade_no' => 'WX' . date('YmdHis') . str_pad($order->id, 8, '0', STR_PAD_LEFT),
                'total_fee' => floatval($order->real_amount ?: $order->amount),
                'attach' => 'order_id=' . $order->id,
                'scene_info' => [
                    'h5_info' => [
                        'type' => 'Wap',
                        'wap_url' => Uri::root(),
                        'wap_name' => $app->get('sitename')
                    ]
                ]
            ];
            
            $result = $wechatModel->createPayment($paymentData, $openId);
            
            // 保存支付信息
            $this->savePaymentRecord($order->id, $result);
            
            // 根据支付方式跳转
            if ($openId && isset($result['jsapi_params'])) {
                // JSAPI支付（公众号支付）
                $app->setUserState('com_socialorders.payment_data', $result['jsapi_params']);
                $this->setRedirect(Route::_('index.php?option=com_socialorders&view=wechat&layout=jsapi&id=' . $orderId, false));
            } elseif (isset($result['code_url'])) {
                // 扫码支付
                $app->setUserState('com_socialorders.payment_data', $result);
                $this->setRedirect(Route::_('index.php?option=com_socialorders&view=wechat&layout=qrcode&id=' . $orderId, false));
            } elseif (isset($result['mweb_url'])) {
                // H5支付
                $this->setRedirect($result['mweb_url']);
            } else {
                throw new Exception('支付创建失败');
            }
            
        } catch (Exception $e) {
            $app->enqueueMessage('支付创建失败：' . $e->getMessage(), 'error');
            $this->setRedirect(Route::_('index.php?option=com_socialorders&view=order&id=' . $orderId, false));
        }
    }
    
    public function notify()
    {
        $app = Factory::getApplication();
        
        try {
            // 获取微信支付配置
            $wechatModel = $this->getModel('Wechat', 'SocialordersModel');
            
            // 加载WeChatPay类
            require_once JPATH_COMPONENT . '/helpers/wechatpay.php';
            
            $params = Factory::getApplication()->getParams('com_socialorders');
            $config = [
                'app_id' => $params->get('wechat_app_id'),
                'mch_id' => $params->get('wechat_mch_id'),
                'api_key' => $params->get('wechat_api_key'),
                'notify_url' => Uri::root() . 'index.php?option=com_socialorders&task=wechat.notify'
            ];
            
            $wechatPay = new WeChatPay($config);
            
            // 处理回调
            $result = $wechatPay->handleNotify(function($data) use ($wechatModel, $app) {
                return $this->processPaymentNotify($data);
            });
            
            echo $result;
            $app->close();
            
        } catch (Exception $e) {
            $app->enqueueMessage('回调处理失败：' . $e->getMessage(), 'error');
            
            $reply = [
                'return_code' => 'FAIL',
                'return_msg' => $e->getMessage()
            ];
            
            echo $this->arrayToXml($reply);
            $app->close();
        }
    }
    
    public function query()
    {
        $app = Factory::getApplication();
        $orderId = $app->input->getInt('order_id');
        $tradeNo = $app->input->get('trade_no', '', 'string');
        
        if (!$orderId && !$tradeNo) {
            echo json_encode(['success' => false, 'message' => '参数错误']);
            $app->close();
        }
        
        try {
            $wechatModel = $this->getModel('Wechat', 'SocialordersModel');
            $result = $wechatModel->queryPayment('', $tradeNo);
            
            if ($result['return_code'] === 'SUCCESS' && $result['result_code'] === 'SUCCESS') {
                // 更新订单状态
                if ($orderId && $result['trade_state'] === 'SUCCESS') {
                    $this->updateOrderPaymentStatus($orderId, $result);
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $result,
                    'status' => $result['trade_state'] === 'SUCCESS' ? 'paid' : strtolower($result['trade_state'])
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => $result['return_msg'] ?? '查询失败',
                    'error_code' => $result['err_code'] ?? ''
                ]);
            }
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        
        $app->close();
    }
    
    private function redirectToWeChatAuth($scope = 'snsapi_userinfo', $state = 'wechat_login')
    {
        $app = Factory::getApplication();
        $params = $app->getParams('com_socialorders');
        
        $appId = $params->get('wechat_app_id');
        if (!$appId) {
            $app->enqueueMessage('微信配置未完成', 'error');
            $this->setRedirect(Route::_('index.php?option=com_socialorders', false));
            return;
        }
        
        $redirectUri = urlencode(Uri::current());
        $authUrl = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . $appId 
            . '&redirect_uri=' . $redirectUri 
            . '&response_type=code&scope=' . $scope 
            . '&state=' . $state . '#wechat_redirect';
        
        $app->redirect($authUrl);
    }
    
    private function savePaymentRecord($orderId, $paymentData)
    {
        $db = Factory::getDbo();
        $now = Factory::getDate()->toSql();
        
        $data = [
            'order_id' => $orderId,
            'trade_no' => $paymentData['trade_no'] ?? '',
            'payment_method' => 'wechat',
            'payment_type' => isset($paymentData['jsapi_params']) ? 'jsapi' : (isset($paymentData['code_url']) ? 'native' : 'h5'),
            'amount' => $paymentData['total_fee'] ?? 0,
            'status' => 'pending',
            'prepay_id' => $paymentData['prepay_id'] ?? '',
            'code_url' => $paymentData['code_url'] ?? '',
            'mweb_url' => $paymentData['mweb_url'] ?? '',
            'payment_data' => json_encode($paymentData, JSON_UNESCAPED_UNICODE),
            'created' => $now,
            'modified' => $now
        ];
        
        return $db->insertObject('#__social_payments', (object)$data);
    }
    
    private function processPaymentNotify($data)
    {
        $db = Factory::getDbo();
        $app = Factory::getApplication();
        
        // 记录通知数据
        $this->logPaymentNotify($data);
        
        // 验证商户号
        $params = $app->getParams('com_socialorders');
        if ($data['mch_id'] != $params->get('wechat_mch_id')) {
            throw new Exception('商户号不匹配');
        }
        
        // 解析附加数据
        parse_str($data['attach'] ?? '', $attach);
        $orderId = $attach['order_id'] ?? 0;
        
        if (!$orderId) {
            throw new Exception('订单ID不存在');
        }
        
        // 获取订单
        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__social_orders')
            ->where('id = ' . (int)$orderId);
        
        $db->setQuery($query);
        $order = $db->loadObject();
        
        if (!$order) {
            throw new Exception('订单不存在');
        }
        
        // 验证金额（微信单位为分）
        $paymentAmount = floatval($data['total_fee']) / 100;
        $orderAmount = floatval($order->real_amount ?: $order->amount);
        
        if (abs($paymentAmount - $orderAmount) > 0.01) {
            throw new Exception('支付金额不匹配');
        }
        
        // 更新订单状态
        $updateData = [
            'id' => $order->id,
            'payment_status' => 'paid',
            'payment_time' => Factory::getDate()->toSql(),
            'transaction_id' => $data['transaction_id'] ?? '',
            'status' => 'processing',
            'modified' => Factory::getDate()->toSql()
        ];
        
        $result = $db->updateObject('#__social_orders', (object)$updateData, 'id');
        
        if ($result) {
            // 更新支付记录
            $paymentUpdate = [
                'transaction_id' => $data['transaction_id'] ?? '',
                'status' => 'paid',
                'notify_data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'notify_time' => Factory::getDate()->toSql(),
                'modified' => Factory::getDate()->toSql()
            ];
            
            // 查找支付记录
            $query = $db->getQuery(true)
                ->select('id')
                ->from('#__social_payments')
                ->where('order_id = ' . (int)$orderId)
                ->order('created DESC')
                ->setLimit(1);
            
            $db->setQuery($query);
            $paymentId = $db->loadResult();
            
            if ($paymentId) {
                $paymentUpdate['id'] = $paymentId;
                $db->updateObject('#__social_payments', (object)$paymentUpdate, 'id');
            }
            
            // 记录日志
            $logData = [
                'order_id' => $orderId,
                'payment_id' => $paymentId,
                'log_type' => 'payment',
                'action' => 'notify',
                'message' => '微信支付回调成功',
                'data' => json_encode(['transaction_id' => $data['transaction_id'] ?? '']),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created' => Factory::getDate()->toSql()
            ];
            
            $db->insertObject('#__social_payment_logs', (object)$logData);
            
            return true;
        }
        
        return false;
    }
    
    private function updateOrderPaymentStatus($orderId, $paymentData)
    {
        $db = Factory::getDbo();
        
        $updateData = [
            'id' => $orderId,
            'payment_status' => $paymentData['trade_state'] === 'SUCCESS' ? 'paid' : 'unpaid',
            'transaction_id' => $paymentData['transaction_id'] ?? '',
            'modified' => Factory::getDate()->toSql()
        ];
        
        if ($paymentData['trade_state'] === 'SUCCESS') {
            $updateData['payment_time'] = Factory::getDate()->toSql();
            $updateData['status'] = 'processing';
        }
        
        return $db->updateObject('#__social_orders', (object)$updateData, 'id');
    }
    
    private function logPaymentNotify($data)
    {
        $db = Factory::getDbo();
        
        $logData = [
            'order_id' => 0,
            'log_type' => 'payment_notify',
            'action' => 'receive',
            'message' => '收到支付通知',
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created' => Factory::getDate()->toSql()
        ];
        
        $db->insertObject('#__social_payment_logs', (object)$logData);
    }
    
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
}