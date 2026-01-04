<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Component\ComponentHelper;

class SocialordersModelOrder extends ItemModel
{
    protected function populateState()
    {
        $app = Factory::getApplication();
        $id = $app->input->getInt('id');
        $this->setState('order.id', $id);
        
        parent::populateState();
    }
    
    public function getItem($pk = null)
    {
        if ($item = parent::getItem($pk)) {
            // 获取商品信息
            $item->items = $this->getOrderItems($item->id);
            
            // 获取地址信息
            $item->addresses = $this->getOrderAddresses($item->id);
            
            // 获取支付记录
            $item->payments = $this->getPaymentRecords($item->id);
            
            // 获取用户信息
            $item->user_info = $this->getUserInfo($item->user_id);
            
            // 格式化字段
            $item->amount_formatted = $this->formatAmount($item->amount, $item->currency);
            $item->real_amount_formatted = $this->formatAmount($item->real_amount, $item->currency);
            $item->refund_amount_formatted = $this->formatAmount($item->refund_amount, $item->currency);
            
            // 状态文本
            $item->status_text = $this->getStatusText($item->status);
            $item->payment_status_text = $this->getPaymentStatusText($item->payment_status);
            $item->shipping_status_text = $this->getShippingStatusText($item->shipping_status);
        }
        
        return $item;
    }
    
    public function getTable($type = 'Order', $prefix = 'SocialordersTable', $config = array())
    {
        return parent::getTable($type, $prefix, $config);
    }
    
    public function create($data)
    {
        $db = $this->getDbo();
        $user = Factory::getUser();
        $app = Factory::getApplication();
        $now = Factory::getDate()->toSql();
        
        try {
            // 开始事务
            $db->transactionStart();
            
            // 生成订单号
            $orderNo = $this->generateOrderNo();
            
            // 准备订单数据
            $orderData = [
                'order_no' => $orderNo,
                'user_id' => $user->id,
                'title' => $data['title'] ?? '订单',
                'description' => $data['description'] ?? '',
                'amount' => floatval($data['amount'] ?? 0),
                'real_amount' => floatval($data['real_amount'] ?? $data['amount'] ?? 0),
                'currency' => $data['currency'] ?? 'CNY',
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'customer_note' => $data['customer_note'] ?? '',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'params' => json_encode($data['params'] ?? []),
                'created' => $now,
                'created_by' => $user->id,
                'modified' => $now,
                'state' => 1
            ];
            
            // 插入订单
            $db->insertObject('#__social_orders', (object)$orderData);
            $orderId = $db->insertid();
            
            if (!$orderId) {
                throw new Exception('创建订单失败');
            }
            
            // 添加商品
            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $itemData = [
                        'order_id' => $orderId,
                        'product_id' => $item['product_id'] ?? null,
                        'product_type' => $item['product_type'] ?? '',
                        'product_name' => $item['product_name'] ?? '',
                        'product_sku' => $item['product_sku'] ?? '',
                        'product_image' => $item['product_image'] ?? '',
                        'quantity' => intval($item['quantity'] ?? 1),
                        'price' => floatval($item['price'] ?? 0),
                        'total' => floatval(($item['price'] ?? 0) * ($item['quantity'] ?? 1)),
                        'discount' => floatval($item['discount'] ?? 0),
                        'tax' => floatval($item['tax'] ?? 0),
                        'shipping' => floatval($item['shipping'] ?? 0),
                        'weight' => floatval($item['weight'] ?? 0),
                        'weight_unit' => $item['weight_unit'] ?? 'kg',
                        'specifications' => json_encode($item['specifications'] ?? []),
                        'params' => json_encode($item['params'] ?? []),
                        'created' => $now,
                        'modified' => $now
                    ];
                    
                    $db->insertObject('#__social_order_items', (object)$itemData);
                }
            }
            
            // 添加地址
            if (!empty($data['shipping_address'])) {
                $shippingAddress = array_merge($data['shipping_address'], [
                    'order_id' => $orderId,
                    'address_type' => 'shipping',
                    'created' => $now
                ]);
                $db->insertObject('#__social_order_addresses', (object)$shippingAddress);
            }
            
            if (!empty($data['billing_address'])) {
                $billingAddress = array_merge($data['billing_address'], [
                    'order_id' => $orderId,
                    'address_type' => 'billing',
                    'created' => $now
                ]);
                $db->insertObject('#__social_order_addresses', (object)$billingAddress);
            }
            
            // 记录日志
            $logData = [
                'order_id' => $orderId,
                'log_type' => 'order',
                'action' => 'create',
                'message' => '订单创建成功',
                'data' => json_encode(['order_no' => $orderNo]),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created' => $now,
                'created_by' => $user->id
            ];
            $db->insertObject('#__social_payment_logs', (object)$logData);
            
            // 提交事务
            $db->transactionCommit();
            
            // 发送邮件通知（可选）
            if ($this->shouldSendEmail()) {
                $this->sendOrderEmail($orderId, $user->email);
            }
            
            return $orderId;
            
        } catch (Exception $e) {
            $db->transactionRollback();
            throw $e;
        }
    }
    
    public function cancel($orderId, $userId = null)
    {
        $db = $this->getDbo();
        $user = Factory::getUser();
        $now = Factory::getDate()->toSql();
        
        // 获取订单
        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__social_orders')
            ->where('id = ' . (int)$orderId)
            ->where('state = 1');
        
        $db->setQuery($query);
        $order = $db->loadObject();
        
        if (!$order) {
            throw new Exception('订单不存在');
        }
        
        // 检查权限
        if ($userId && $order->user_id != $userId && !$user->authorise('core.edit', 'com_socialorders')) {
            throw new Exception('无权取消此订单');
        }
        
        // 检查状态是否可以取消
        if (!in_array($order->status, ['pending', 'processing'])) {
            throw new Exception('当前状态不可取消');
        }
        
        // 检查支付状态
        if ($order->payment_status == 'paid') {
            throw new Exception('订单已支付，请联系客服处理');
        }
        
        // 更新订单状态
        $updateData = [
            'id' => $orderId,
            'status' => 'cancelled',
            'admin_note' => $order->admin_note . "\n用户取消订单：" . $now,
            'modified' => $now,
            'modified_by' => $user->id
        ];
        
        $result = $db->updateObject('#__social_orders', (object)$updateData, 'id');
        
        if ($result) {
            // 记录日志
            $logData = [
                'order_id' => $orderId,
                'log_type' => 'order',
                'action' => 'cancel',
                'message' => '用户取消订单',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created' => $now,
                'created_by' => $user->id
            ];
            $db->insertObject('#__social_payment_logs', (object)$logData);
            
            return true;
        }
        
        return false;
    }
    
    public function canView($orderId, $userId)
    {
        $db = $this->getDbo();
        $user = Factory::getUser();
        
        // 管理员可以查看所有订单
        if ($user->authorise('core.admin')) {
            return true;
        }
        
        // 检查订单所属用户
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from('#__social_orders')
            ->where('id = ' . (int)$orderId)
            ->where('user_id = ' . (int)$userId)
            ->where('state = 1');
        
        $db->setQuery($query);
        $count = $db->loadResult();
        
        return $count > 0;
    }
    
    private function generateOrderNo()
    {
        $params = ComponentHelper::getParams('com_socialorders');
        $prefix = $params->get('order_prefix', 'SO');
        
        $date = date('YmdHis');
        $random = mt_rand(1000, 9999);
        
        return $prefix . $date . $random;
    }
    
    private function getOrderItems($orderId)
    {
        $db = $this->getDbo();
        
        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__social_order_items')
            ->where('order_id = ' . (int)$orderId);
        
        $db->setQuery($query);
        return $db->loadObjectList();
    }
    
    private function getOrderAddresses($orderId)
    {
        $db = $this->getDbo();
        
        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__social_order_addresses')
            ->where('order_id = ' . (int)$orderId);
        
        $db->setQuery($query);
        $addresses = $db->loadObjectList();
        
        $result = [];
        foreach ($addresses as $address) {
            $result[$address->address_type] = $address;
        }
        
        return $result;
    }
    
    private function getPaymentRecords($orderId)
    {
        $db = $this->getDbo();
        
        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__social_payments')
            ->where('order_id = ' . (int)$orderId)
            ->order('created DESC');
        
        $db->setQuery($query);
        return $db->loadObjectList();
    }
    
    private function getUserInfo($userId)
    {
        $db = $this->getDbo();
        
        $query = $db->getQuery(true)
            ->select('name, username, email')
            ->from('#__users')
            ->where('id = ' . (int)$userId);
        
        $db->setQuery($query);
        return $db->loadObject();
    }
    
    private function formatAmount($amount, $currency)
    {
        $symbols = [
            'CNY' => '¥',
            'USD' => '$',
            'EUR' => '€'
        ];
        
        $symbol = $symbols[$currency] ?? $currency . ' ';
        return $symbol . number_format(floatval($amount), 2);
    }
    
    private function getStatusText($status)
    {
        $statuses = [
            'pending' => '待处理',
            'processing' => '处理中',
            'completed' => '已完成',
            'cancelled' => '已取消',
            'refunded' => '已退款'
        ];
        
        return $statuses[$status] ?? $status;
    }
    
    private function getPaymentStatusText($status)
    {
        $statuses = [
            'unpaid' => '未支付',
            'paid' => '已支付',
            'refunded' => '已退款',
            'failed' => '支付失败'
        ];
        
        return $statuses[$status] ?? $status;
    }
    
    private function getShippingStatusText($status)
    {
        $statuses = [
            'unshipped' => '未发货',
            'shipped' => '已发货',
            'delivered' => '已送达'
        ];
        
        return $statuses[$status] ?? $status;
    }
    
    private function shouldSendEmail()
    {
        $params = ComponentHelper::getParams('com_socialorders');
        return $params->get('send_order_email', 1);
    }
    
    private function sendOrderEmail($orderId, $toEmail)
    {
        // 这里实现邮件发送逻辑
        // 可以使用Joomla的邮件类或者第三方邮件服务
        return true;
    }
}