<?php
defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

class SocialordersTableOrder extends Table
{
    public function __construct(&$db)
    {
        parent::__construct('#__social_orders', 'id', $db);
        
        // 设置默认值
        $this->created = Factory::getDate()->toSql();
        $this->state = 1;
        $this->status = 'pending';
        $this->payment_status = 'unpaid';
        $this->currency = 'CNY';
        $this->amount = 0;
        $this->real_amount = 0;
        $this->refund_amount = 0;
        $this->ordering = 0;
    }
    
    public function bind($array, $ignore = '')
    {
        // 生成订单号
        if (empty($array['order_no'])) {
            $array['order_no'] = $this->generateOrderNo();
        }
        
        // 处理JSON字段
        if (isset($array['params']) && is_array($array['params'])) {
            $array['params'] = json_encode($array['params']);
        }
        
        if (isset($array['payment_data']) && is_array($array['payment_data'])) {
            $array['payment_data'] = json_encode($array['payment_data']);
        }
        
        if (isset($array['refund_data']) && is_array($array['refund_data'])) {
            $array['refund_data'] = json_encode($array['refund_data']);
        }
        
        if (isset($array['shipping_data']) && is_array($array['shipping_data'])) {
            $array['shipping_data'] = json_encode($array['shipping_data']);
        }
        
        return parent::bind($array, $ignore);
    }
    
    public function check()
    {
        // 检查必填字段
        if (empty($this->order_no)) {
            $this->setError(Text::_('COM_SOCIALORDERS_ERROR_ORDER_NO_REQUIRED'));
            return false;
        }
        
        if (empty($this->user_id)) {
            $this->setError(Text::_('COM_SOCIALORDERS_ERROR_USER_ID_REQUIRED'));
            return false;
        }
        
        if (empty($this->title)) {
            $this->setError(Text::_('COM_SOCIALORDERS_ERROR_TITLE_REQUIRED'));
            return false;
        }
        
        // 验证金额
        if ($this->amount < 0) {
            $this->setError(Text::_('COM_SOCIALORDERS_ERROR_AMOUNT_INVALID'));
            return false;
        }
        
        if ($this->real_amount < 0) {
            $this->setError(Text::_('COM_SOCIALORDERS_ERROR_REAL_AMOUNT_INVALID'));
            return false;
        }
        
        if ($this->refund_amount < 0) {
            $this->setError(Text::_('COM_SOCIALORDERS_ERROR_REFUND_AMOUNT_INVALID'));
            return false;
        }
        
        // 检查订单号唯一性
        if (!$this->checkOrderNoUnique()) {
            $this->setError(Text::_('COM_SOCIALORDERS_ERROR_ORDER_NO_EXISTS'));
            return false;
        }
        
        // 设置默认值
        if (empty($this->created)) {
            $this->created = Factory::getDate()->toSql();
        }
        
        if (empty($this->created_by)) {
            $user = Factory::getUser();
            $this->created_by = $user->id;
        }
        
        $this->modified = Factory::getDate()->toSql();
        
        return true;
    }
    
    public function store($updateNulls = false)
    {
        $isNew = empty($this->id);
        
        // 检查数据
        if (!$this->check()) {
            return false;
        }
        
        // 保存前触发事件
        $dispatcher = JEventDispatcher::getInstance();
        $dispatcher->trigger('onBeforeOrderSave', array('com_socialorders.order', &$this, $isNew));
        
        // 保存数据
        $result = parent::store($updateNulls);
        
        if ($result) {
            // 保存后触发事件
            $dispatcher->trigger('onAfterOrderSave', array('com_socialorders.order', &$this, $isNew));
        }
        
        return $result;
    }
    
    public function delete($pk = null)
    {
        $db = Factory::getDbo();
        
        // 获取订单ID
        $orderId = $pk ?: $this->id;
        
        if (!$orderId) {
            $this->setError(Text::_('JLIB_DATABASE_ERROR_NO_ROWS_SELECTED'));
            return false;
        }
        
        // 检查订单是否可以删除
        if (!$this->canDelete($orderId)) {
            $this->setError(Text::_('COM_SOCIALORDERS_ERROR_CANNOT_DELETE'));
            return false;
        }
        
        try {
            // 开始事务
            $db->transactionStart();
            
            // 删除关联数据
            $this->deleteRelatedData($orderId);
            
            // 删除主记录
            $result = parent::delete($pk);
            
            if ($result) {
                // 记录日志
                $logData = [
                    'order_id' => $orderId,
                    'log_type' => 'order',
                    'action' => 'delete',
                    'message' => '订单删除',
                    'created' => Factory::getDate()->toSql(),
                    'created_by' => Factory::getUser()->id
                ];
                
                $db->insertObject('#__social_payment_logs', (object)$logData);
            }
            
            // 提交事务
            $db->transactionCommit();
            
            return $result;
            
        } catch (Exception $e) {
            $db->transactionRollback();
            $this->setError($e->getMessage());
            return false;
        }
    }
    
    public function publish($pks = null, $state = 1, $userId = 0)
    {
        // 简化处理，只更新state字段
        return parent::publish($pks, $state, $userId);
    }
    
    public function checkout($userId, $pk = null)
    {
        $pk = $pk ?: $this->id;
        
        if (!$pk) {
            $this->setError(Text::_('JLIB_DATABASE_ERROR_NO_ROWS_SELECTED'));
            return false;
        }
        
        $this->checked_out = $userId;
        $this->checked_out_time = Factory::getDate()->toSql();
        
        return $this->store();
    }
    
    public function checkin($pk = null)
    {
        $pk = $pk ?: $this->id;
        
        if (!$pk) {
            $this->setError(Text::_('JLIB_DATABASE_ERROR_NO_ROWS_SELECTED'));
            return false;
        }
        
        $this->checked_out = 0;
        $this->checked_out_time = null;
        
        return $this->store();
    }
    
    public function isCheckedOut($userId = 0)
    {
        if ($this->checked_out && $this->checked_out != $userId) {
            $checkedOutTime = strtotime($this->checked_out_time);
            $now = time();
            
            // 如果锁定超过24小时，自动释放
            if (($now - $checkedOutTime) > 86400) {
                $this->checkin();
                return false;
            }
            
            return true;
        }
        
        return false;
    }
    
    private function generateOrderNo()
    {
        $params = JComponentHelper::getParams('com_socialorders');
        $prefix = $params->get('order_prefix', 'SO');
        
        $date = date('YmdHis');
        $random = mt_rand(1000, 9999);
        
        return $prefix . $date . $random;
    }
    
    private function checkOrderNoUnique()
    {
        $db = Factory::getDbo();
        
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->_tbl)
            ->where('order_no = ' . $db->quote($this->order_no));
        
        if ($this->id) {
            $query->where('id != ' . (int)$this->id);
        }
        
        $db->setQuery($query);
        $count = $db->loadResult();
        
        return $count == 0;
    }
    
    private function canDelete($orderId)
    {
        $db = Factory::getDbo();
        
        // 检查订单状态
        $query = $db->getQuery(true)
            ->select('status, payment_status')
            ->from($this->_tbl)
            ->where('id = ' . (int)$orderId);
        
        $db->setQuery($query);
        $order = $db->loadObject();
        
        if (!$order) {
            return false;
        }
        
        // 已支付或已发货的订单不能删除
        if ($order->payment_status === 'paid') {
            return false;
        }
        
        // 处理中或已完成的订单不能删除
        if (in_array($order->status, ['processing', 'completed', 'shipped'])) {
            return false;
        }
        
        return true;
    }
    
    private function deleteRelatedData($orderId)
    {
        $db = Factory::getDbo();
        
        // 删除商品项
        $query = $db->getQuery(true)
            ->delete('#__social_order_items')
            ->where('order_id = ' . (int)$orderId);
        
        $db->setQuery($query);
        $db->execute();
        
        // 删除地址
        $query = $db->getQuery(true)
            ->delete('#__social_order_addresses')
            ->where('order_id = ' . (int)$orderId);
        
        $db->setQuery($query);
        $db->execute();
        
        // 删除支付记录
        $query = $db->getQuery(true)
            ->delete('#__social_payments')
            ->where('order_id = ' . (int)$orderId);
        
        $db->setQuery($query);
        $db->execute();
        
        // 删除相关日志
        $query = $db->getQuery(true)
            ->delete('#__social_payment_logs')
            ->where('order_id = ' . (int)$orderId);
        
        $db->setQuery($query);
        $db->execute();
    }
    
    public function getNextOrder($where = '', $order = 'ordering')
    {
        return $this->getNeighbor($where, $order, 'next');
    }
    
    public function getPrevOrder($where = '', $order = 'ordering')
    {
        return $this->getNeighbor($where, $order, 'prev');
    }
    
    private function getNeighbor($where = '', $order = 'ordering', $direction = 'next')
    {
        $db = Factory::getDbo();
        
        $query = $db->getQuery(true)
            ->select('id')
            ->from($this->_tbl)
            ->where('state = 1');
        
        if ($where) {
            $query->where($where);
        }
        
        if ($direction === 'next') {
            $query->where('ordering > ' . (int)$this->ordering)
                  ->order('ordering ASC');
        } else {
            $query->where('ordering < ' . (int)$this->ordering)
                  ->order('ordering DESC');
        }
        
        $db->setQuery($query, 0, 1);
        $neighborId = $db->loadResult();
        
        if ($neighborId) {
            $neighbor = Table::getInstance('Order', 'SocialordersTable');
            $neighbor->load($neighborId);
            return $neighbor;
        }
        
        return null;
    }
}