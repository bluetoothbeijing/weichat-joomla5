<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;

class SocialordersModelOrder extends AdminModel
{
    protected $text_prefix = 'COM_SOCIALORDERS';
    
    public function getTable($type = 'Order', $prefix = 'SocialordersTable', $config = array())
    {
        return Table::getInstance($type, $prefix, $config);
    }
    
    public function getForm($data = array(), $loadData = true)
    {
        $form = $this->loadForm(
            'com_socialorders.order',
            'order',
            array('control' => 'jform', 'load_data' => $loadData)
        );
        
        if (empty($form)) {
            return false;
        }
        
        return $form;
    }
    
    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_socialorders.edit.order.data', array());
        
        if (empty($data)) {
            $data = $this->getItem();
        }
        
        return $data;
    }
    
    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);
        
        if ($item) {
            // 加载关联数据
            $item->items = $this->getOrderItems($item->id);
            $item->addresses = $this->getOrderAddresses($item->id);
            $item->payments = $this->getPaymentRecords($item->id);
            
            // 格式化金额
            $item->amount_formatted = $this->formatAmount($item->amount, $item->currency);
            $item->real_amount_formatted = $this->formatAmount($item->real_amount, $item->currency);
            $item->refund_amount_formatted = $this->formatAmount($item->refund_amount, $item->currency);
            
            // 用户信息
            if ($item->user_id) {
                $item->user_info = $this->getUserInfo($item->user_id);
            }
        }
        
        return $item;
    }
    
    public function save($data)
    {
        $db = Factory::getDbo();
        $user = Factory::getUser();
        $now = Factory::getDate()->toSql();
        $pk = (!empty($data['id'])) ? $data['id'] : (int)$this->getState($this->getName() . '.id');
        $isNew = empty($pk);
        
        try {
            // 开始事务
            $db->transactionStart();
            
            if ($isNew) {
                // 创建新订单
                if (empty($data['order_no'])) {
                    $data['order_no'] = $this->generateOrderNo();
                }
                
                if (empty($data['created'])) {
                    $data['created'] = $now;
                }
                
                if (empty($data['created_by'])) {
                    $data['created_by'] = $user->id;
                }
                
                $data['state'] = 1;
            }
            
            $data['modified'] = $now;
            $data['modified_by'] = $user->id;
            
            // 保存主订单
            $result = parent::save($data);
            
            if (!$result) {
                throw new Exception('保存订单失败：' . $this->getError());
            }
            
            // 获取订单ID
            $orderId = $this->getState($this->getName() . '.id');
            
            // 保存商品项
            if (isset($data['items']) && is_array($data['items'])) {
                $this->saveOrderItems($orderId, $data['items']);
            }
            
            // 保存地址信息
            if (isset($data['addresses']) && is_array($data['addresses'])) {
                $this->saveOrderAddresses($orderId, $data['addresses']);
            }
            
            // 提交事务
            $db->transactionCommit();
            
            return $orderId;
            
        } catch (Exception $e) {
            $db->transactionRollback();
            $this->setError($e->getMessage());
            return false;
        }
    }
    
    public function refund($orderId)
    {
        $db = Factory::getDbo();
        $user = Factory::getUser();
        $now = Factory::getDate()->toSql();
        
        // 获取订单信息
        $order = $this->getItem($orderId);
        
        if (!$order) {
            throw new Exception('订单不存在');
        }
        
        if ($order->payment_status !== 'paid') {
            throw new Exception('订单未支付，无法退款');
        }
        
        if ($order->refund_status === 'success') {
            throw new Exception('订单已退款');
        }
        
        // 获取支付记录
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
        
        // 获取退款金额
        $refundAmount = $order->real_amount - $order->refund_amount;
        
        // 调用微信退款
        try {
            $wechatModel = $this->getModel('Wechat', 'SocialordersModel');
            
            $refundData = [
                'out_trade_no' => $payment->trade_no,
                'out_refund_no' => 'RF' . date('YmdHis') . str_pad($order->id, 8, '0', STR_PAD_LEFT),
                'total_fee' => floatval($order->real_amount),
                'refund_fee' => $refundAmount,
                'refund_desc' => '管理员退款'
            ];
            
            $result = $wechatModel->refundPayment($refundData);
            
            if ($result['return_code'] === 'SUCCESS' && $result['result_code'] === 'SUCCESS') {
                // 更新订单退款状态
                $newRefundAmount = floatval($order->refund_amount) + $refundAmount;
                
                $updateData = [
                    'id' => $orderId,
                    'refund_amount' => $newRefundAmount,
                    'refund_status' => $newRefundAmount >= floatval($order->real_amount) ? 'success' : 'partial',
                    'refund_time' => $now,
                    'refund_data' => json_encode($result, JSON_UNESCAPED_UNICODE),
                    'modified' => $now,
                    'modified_by' => $user->id
                ];
                
                $db->updateObject('#__social_orders', (object)$updateData, 'id');
                
                // 更新支付记录
                $paymentUpdate = [
                    'id' => $payment->id,
                    'refund_no' => $refundData['out_refund_no'],
                    'refund_amount' => $refundAmount,
                    'refund_status' => $newRefundAmount >= floatval($order->real_amount) ? 'success' : 'partial',
                    'refund_time' => $now,
                    'refund_data' => json_encode($result, JSON_UNESCAPED_UNICODE),
                    'modified' => $now
                ];
                
                $db->updateObject('#__social_payments', (object)$paymentUpdate, 'id');
                
                // 记录日志
                $logData = [
                    'order_id' => $orderId,
                    'payment_id' => $payment->id,
                    'log_type' => 'refund',
                    'action' => 'admin_refund',
                    'message' => '管理员发起退款',
                    'data' => json_encode([
                        'refund_amount' => $refundAmount,
                        'refund_no' => $refundData['out_refund_no'],
                        'operator' => $user->username
                    ]),
                    'created' => $now,
                    'created_by' => $user->id
                ];
                
                $db->insertObject('#__social_payment_logs', (object)$logData);
                
                return true;
            } else {
                throw new Exception('微信退款失败：' . ($result['return_msg'] ?? '未知错误'));
            }
            
        } catch (Exception $e) {
            throw new Exception('退款失败：' . $e->getMessage());
        }
    }
    
    public function sendEmail($orderId, $type = 'order_created')
    {
        $order = $this->getItem($orderId);
        
        if (!$order) {
            throw new Exception('订单不存在');
        }
        
        $userInfo = $this->getUserInfo($order->user_id);
        if (!$userInfo || empty($userInfo->email)) {
            throw new Exception('用户邮箱不存在');
        }
        
        $mailer = Factory::getMailer();
        
        // 邮件配置
        $config = Factory::getConfig();
        $mailer->setSender([
            $config->get('mailfrom'),
            $config->get('fromname')
        ]);
        
        $mailer->addRecipient($userInfo->email);
        $mailer->setSubject($this->getEmailSubject($type, $order));
        $mailer->setBody($this->getEmailBody($type, $order));
        $mailer->isHtml(true);
        
        return $mailer->Send();
    }
    
    private function getOrderItems($orderId)
    {
        $db = Factory::getDbo();
        
        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__social_order_items')
            ->where('order_id = ' . (int)$orderId);
        
        $db->setQuery($query);
        return $db->loadObjectList();
    }
    
    private function getOrderAddresses($orderId)
    {
        $db = Factory::getDbo();
        
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
        $db = Factory::getDbo();
        
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
        $db = Factory::getDbo();
        
        $query = $db->getQuery(true)
            ->select('name, username, email')
            ->from('#__users')
            ->where('id = ' . (int)$userId);
        
        $db->setQuery($query);
        return $db->loadObject();
    }
    
    private function generateOrderNo()
    {
        $params = JComponentHelper::getParams('com_socialorders');
        $prefix = $params->get('order_prefix', 'SO');
        
        $date = date('YmdHis');
        $random = mt_rand(1000, 9999);
        
        return $prefix . $date . $random;
    }
    
    private function saveOrderItems($orderId, $items)
    {
        $db = Factory::getDbo();
        $now = Factory::getDate()->toSql();
        
        // 删除原有商品项
        $query = $db->getQuery(true)
            ->delete('#__social_order_items')
            ->where('order_id = ' . (int)$orderId);
        
        $db->setQuery($query);
        $db->execute();
        
        // 插入新商品项
        foreach ($items as $item) {
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
    
    private function saveOrderAddresses($orderId, $addresses)
    {
        $db = Factory::getDbo();
        $now = Factory::getDate()->toSql();
        
        // 删除原有地址
        $query = $db->getQuery(true)
            ->delete('#__social_order_addresses')
            ->where('order_id = ' . (int)$orderId);
        
        $db->setQuery($query);
        $db->execute();
        
        // 插入新地址
        foreach ($addresses as $type => $address) {
            $addressData = array_merge($address, [
                'order_id' => $orderId,
                'address_type' => $type,
                'created' => $now
            ]);
            
            $db->insertObject('#__social_order_addresses', (object)$addressData);
        }
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
    
    private function getEmailSubject($type, $order)
    {
        $subjects = [
            'order_created' => '您的订单已创建 - ' . $order->order_no,
            'order_paid' => '您的订单已支付 - ' . $order->order_no,
            'order_shipped' => '您的订单已发货 - ' . $order->order_no,
            'order_completed' => '您的订单已完成 - ' . $order->order_no,
            'order_cancelled' => '您的订单已取消 - ' . $order->order_no
        ];
        
        $sitename = Factory::getConfig()->get('sitename');
        return isset($subjects[$type]) ? '[' . $sitename . '] ' . $subjects[$type] : '订单通知';
    }
    
    private function getEmailBody($type, $order)
    {
        $userInfo = $this->getUserInfo($order->user_id);
        $sitename = Factory::getConfig()->get('sitename');
        
        $body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>订单通知</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #f8f9fa; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #fff; }
                .order-info { margin: 20px 0; }
                .order-info table { width: 100%; border-collapse: collapse; }
                .order-info td { padding: 8px; border: 1px solid #ddd; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; color: #666; font-size: 12px; }
                .button { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>' . $sitename . '</h1>
                </div>
                
                <div class="content">
                    <h2>尊敬的' . ($userInfo->name ?? '用户') . '，您好！</h2>';
        
        switch ($type) {
            case 'order_created':
                $body .= '
                    <p>您的订单已创建成功，订单详情如下：</p>';
                break;
            case 'order_paid':
                $body .= '
                    <p>您的订单已支付成功，订单详情如下：</p>';
                break;
            case 'order_shipped':
                $body .= '
                    <p>您的订单已发货，订单详情如下：</p>';
                break;
            case 'order_completed':
                $body .= '
                    <p>您的订单已完成，订单详情如下：</p>';
                break;
            case 'order_cancelled':
                $body .= '
                    <p>您的订单已取消，订单详情如下：</p>';
                break;
        }
        
        $body .= '
                    <div class="order-info">
                        <table>
                            <tr>
                                <td width="30%">订单号</td>
                                <td>' . $order->order_no . '</td>
                            </tr>
                            <tr>
                                <td>订单标题</td>
                                <td>' . htmlspecialchars($order->title) . '</td>
                            </tr>
                            <tr>
                                <td>订单金额</td>
                                <td>' . $this->formatAmount($order->amount, $order->currency) . '</td>
                            </tr>
                            <tr>
                                <td>订单状态</td>
                                <td>' . $this->getStatusText($order->status) . '</td>
                            </tr>
                            <tr>
                                <td>支付状态</td>
                                <td>' . $this->getPaymentStatusText($order->payment_status) . '</td>
                            </tr>
                            <tr>
                                <td>创建时间</td>
                                <td>' . ($order->created ? date('Y-m-d H:i:s', strtotime($order->created)) : '') . '</td>
                            </tr>
                        </table>
                    </div>';
        
        if ($type === 'order_created') {
            $body .= '
                    <p style="text-align: center;">
                        <a href="' . JUri::root() . 'index.php?option=com_socialorders&view=order&id=' . $order->id . '" class="button">
                            查看订单详情
                        </a>
                    </p>';
        }
        
        $body .= '
                </div>
                
                <div class="footer">
                    <p>此邮件由系统自动发送，请勿回复。</p>
                    <p>&copy; ' . date('Y') . ' ' . $sitename . ' 版权所有</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $body;
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
}