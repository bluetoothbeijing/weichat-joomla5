<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

class SocialordersControllerPayment extends BaseController
{
    public function create()
    {
        $app = Factory::getApplication();
        $orderId = $app->input->getInt('order_id');
        $paymentMethod = $app->input->get('payment_method', 'wechat', 'cmd');
        
        if (!$orderId) {
            echo json_encode(['success' => false, 'message' => '订单ID不能为空']);
            $app->close();
        }
        
        // 检查令牌
        $this->checkToken();
        
        try {
            // 获取订单信息
            $orderModel = $this->getModel('Order', 'SocialordersModel');
            $order = $orderModel->getItem($orderId);
            
            if (!$order) {
                throw new Exception('订单不存在');
            }
            
            // 检查订单状态
            if ($order->payment_status !== 'unpaid') {
                throw new Exception('订单已支付或已关闭');
            }
            
            // 检查权限
            $user = Factory::getUser();
            if ($order->user_id != $user->id && !$user->authorise('core.edit', 'com_socialorders')) {
                throw new Exception('无权操作此订单');
            }
            
            // 根据支付方式处理
            switch ($paymentMethod) {
                case 'wechat':
                    $result = $this->createWeChatPayment($order);
                    break;
                    
                default:
                    throw new Exception('不支持的支付方式');
            }
            
            echo json_encode([
                'success' => true,
                'data' => $result,
                'message' => '支付创建成功'
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        
        $app->close();
    }
    
    public function status()
    {
        $app = Factory::getApplication();
        $orderId = $app->input->getInt('order_id');
        $paymentId = $app->input->getInt('payment_id');
        
        if (!$orderId && !$paymentId) {
            echo json_encode(['success' => false, 'message' => '参数错误']);
            $app->close();
        }
        
        try {
            $db = Factory::getDbo();
            
            if ($paymentId) {
                // 查询支付记录
                $query = $db->getQuery(true)
                    ->select('*')
                    ->from('#__social_payments')
                    ->where('id = ' . (int)$paymentId);
            } else {
                // 查询订单的支付记录
                $query = $db->getQuery(true)
                    ->select('*')
                    ->from('#__social_payments')
                    ->where('order_id = ' . (int)$orderId)
                    ->order('created DESC');
            }
            
            $db->setQuery($query);
            $payment = $db->loadObject();
            
            if (!$payment) {
                throw new Exception('支付记录不存在');
            }
            
            // 如果支付状态不是最终状态，查询微信支付状态
            if (in_array($payment->status, ['pending', 'processing'])) {
                $wechatModel = $this->getModel('Wechat', 'SocialordersModel');
                $result = $wechatModel->queryPayment($payment->transaction_id, $payment->trade_no);
                
                if ($result['return_code'] === 'SUCCESS' && $result['result_code'] === 'SUCCESS') {
                    // 更新支付状态
                    if ($result['trade_state'] === 'SUCCESS') {
                        $payment->status = 'paid';
                        $payment->transaction_id = $result['transaction_id'] ?? $payment->transaction_id;
                        $payment->notify_data = json_encode($result, JSON_UNESCAPED_UNICODE);
                        $payment->notify_time = Factory::getDate()->toSql();
                        
                        $db->updateObject('#__social_payments', $payment, 'id');
                        
                        // 更新订单状态
                        $this->updateOrderAfterPayment($payment->order_id, $result);
                    }
                }
                
                $payment->wechat_status = $result['trade_state'] ?? '';
                $payment->wechat_result = $result;
            }
            
            echo json_encode([
                'success' => true,
                'data' => $payment,
                'order_id' => $payment->order_id
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        
        $app->close();
    }
    
    public function refund()
    {
        $app = Factory::getApplication();
        $orderId = $app->input->getInt('order_id');
        $refundAmount = $app->input->getFloat('refund_amount');
        $refundReason = $app->input->get('refund_reason', '', 'string');
        
        if (!$orderId || $refundAmount <= 0) {
            echo json_encode(['success' => false, 'message' => '参数错误']);
            $app->close();
        }
        
        // 检查令牌和权限
        $this->checkToken();
        
        try {
            // 获取订单信息
            $orderModel = $this->getModel('Order', 'SocialordersModel');
            $order = $orderModel->getItem($orderId);
            
            if (!$order) {
                throw new Exception('订单不存在');
            }
            
            if ($order->payment_status !== 'paid') {
                throw new Exception('订单未支付，无法退款');
            }
            
            if ($order->refund_status === 'success') {
                throw new Exception('订单已退款');
            }
            
            // 检查退款金额
            $maxRefundAmount = floatval($order->real_amount) - floatval($order->refund_amount);
            if ($refundAmount > $maxRefundAmount) {
                throw new Exception('退款金额不能超过可退金额：' . $maxRefundAmount);
            }
            
            // 获取支付记录
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('*')
                ->from('#__social_payments')
                ->where('order_id = ' . (int)$orderId)
                ->where('status = ' . $db->quote('paid'))
                ->order('created DESC');
            
            $db->setQuery($query);
            $payment = $db->loadObject();
            
            if (!$payment) {
                throw new Exception('支付记录不存在');
            }
            
            // 调用微信退款
            $wechatModel = $this->getModel('Wechat', 'SocialordersModel');
            
            $refundData = [
                'out_trade_no' => $payment->trade_no,
                'out_refund_no' => 'RF' . date('YmdHis') . str_pad($order->id, 8, '0', STR_PAD_LEFT),
                'total_fee' => floatval($order->real_amount),
                'refund_fee' => $refundAmount,
                'refund_desc' => $refundReason
            ];
            
            $result = $wechatModel->refundPayment($refundData);
            
            if ($result['return_code'] === 'SUCCESS' && $result['result_code'] === 'SUCCESS') {
                // 更新订单退款状态
                $newRefundAmount = floatval($order->refund_amount) + $refundAmount;
                
                $updateData = [
                    'id' => $orderId,
                    'refund_amount' => $newRefundAmount,
                    'refund_status' => $newRefundAmount >= floatval($order->real_amount) ? 'success' : 'partial',
                    'refund_time' => Factory::getDate()->toSql(),
                    'refund_data' => json_encode($result, JSON_UNESCAPED_UNICODE),
                    'modified' => Factory::getDate()->toSql()
                ];
                
                $db->updateObject('#__social_orders', (object)$updateData, 'id');
                
                // 更新支付记录
                $paymentUpdate = [
                    'id' => $payment->id,
                    'refund_no' => $refundData['out_refund_no'],
                    'refund_amount' => $refundAmount,
                    'refund_status' => $newRefundAmount >= floatval($order->real_amount) ? 'success' : 'partial',
                    'refund_time' => Factory::getDate()->toSql(),
                    'refund_data' => json_encode($result, JSON_UNESCAPED_UNICODE),
                    'modified' => Factory::getDate()->toSql()
                ];
                
                $db->updateObject('#__social_payments', (object)$paymentUpdate, 'id');
                
                // 记录日志
                $logData = [
                    'order_id' => $orderId,
                    'payment_id' => $payment->id,
                    'log_type' => 'refund',
                    'action' => 'apply',
                    'message' => '申请退款成功',
                    'data' => json_encode([
                        'refund_amount' => $refundAmount,
                        'refund_no' => $refundData['out_refund_no'],
                        'reason' => $refundReason
                    ]),
                    'created' => Factory::getDate()->toSql()
                ];
                
                $db->insertObject('#__social_payment_logs', (object)$logData);
                
                echo json_encode([
                    'success' => true,
                    'message' => '退款申请成功',
                    'data' => $result
                ]);
            } else {
                throw new Exception('退款失败：' . ($result['return_msg'] ?? '未知错误'));
            }
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        
        $app->close();
    }
    
    private function createWeChatPayment($order)
    {
        $app = Factory::getApplication();
        $wechatModel = $this->getModel('Wechat', 'SocialordersModel');
        
        // 获取openid（如果是微信浏览器）
        $openId = null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $isWeChat = strpos($userAgent, 'MicroMessenger') !== false;
        
        if ($isWeChat) {
            $openId = $app->getSession()->get('wechat_openid', '');
            if (!$openId) {
                // 需要授权获取openid
                $authUrl = $this->getWeChatAuthUrl('snsapi_base', 'wechat_pay_' . $order->id);
                return ['require_auth' => true, 'auth_url' => $authUrl];
            }
        }
        
        // 生成支付数据
        $paymentData = [
            'body' => $order->title ?: '订单支付',
            'trade_no' => 'WX' . date('YmdHis') . str_pad($order->id, 8, '0', STR_PAD_LEFT),
            'total_fee' => floatval($order->real_amount ?: $order->amount),
            'attach' => 'order_id=' . $order->id,
            'scene_info' => [
                'h5_info' => [
                    'type' => 'Wap',
                    'wap_url' => JUri::root(),
                    'wap_name' => $app->get('sitename')
                ]
            ]
        ];
        
        $result = $wechatModel->createPayment($paymentData, $openId);
        
        // 保存支付记录
        $this->savePaymentRecord($order->id, $result);
        
        // 准备返回数据
        $returnData = [
            'order_id' => $order->id,
            'trade_no' => $paymentData['trade_no'],
            'amount' => $paymentData['total_fee']
        ];
        
        if ($openId && isset($result['jsapi_params'])) {
            // JSAPI支付
            $returnData['type'] = 'jsapi';
            $returnData['jsapi_params'] = $result['jsapi_params'];
            $returnData['prepay_id'] = $result['prepay_id'];
        } elseif (isset($result['code_url'])) {
            // 扫码支付
            $returnData['type'] = 'native';
            $returnData['code_url'] = $result['code_url'];
            $returnData['qrcode_url'] = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($result['code_url']);
        } elseif (isset($result['mweb_url'])) {
            // H5支付
            $returnData['type'] = 'h5';
            $returnData['mweb_url'] = $result['mweb_url'];
        }
        
        return $returnData;
    }
    
    private function getWeChatAuthUrl($scope = 'snsapi_base', $state = '')
    {
        $app = Factory::getApplication();
        $params = $app->getParams('com_socialorders');
        
        $appId = $params->get('wechat_app_id');
        $redirectUri = urlencode(JUri::current() . '?payment_auth=1');
        
        return 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . $appId 
            . '&redirect_uri=' . $redirectUri 
            . '&response_type=code&scope=' . $scope 
            . '&state=' . ($state ?: 'wechat_auth') . '#wechat_redirect';
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
    
    private function updateOrderAfterPayment($orderId, $paymentResult)
    {
        $db = Factory::getDbo();
        
        $updateData = [
            'id' => $orderId,
            'payment_status' => 'paid',
            'payment_time' => Factory::getDate()->toSql(),
            'transaction_id' => $paymentResult['transaction_id'] ?? '',
            'status' => 'processing',
            'modified' => Factory::getDate()->toSql()
        ];
        
        return $db->updateObject('#__social_orders', (object)$updateData, 'id');
    }
    
    private function checkToken()
    {
        $app = Factory::getApplication();
        $token = $app->getSession()->getFormToken();
        $inputToken = $app->input->get('token', '', 'alnum');
        
        if (!hash_equals($token, $inputToken)) {
            echo json_encode(['success' => false, 'message' => '令牌无效']);
            $app->close();
        }
    }
}