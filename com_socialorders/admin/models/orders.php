<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Pagination\Pagination;

class SocialordersModelOrders extends ListModel
{
    public function __construct($config = array())
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'id', 'a.id',
                'order_no', 'a.order_no',
                'title', 'a.title',
                'amount', 'a.amount',
                'status', 'a.status',
                'payment_status', 'a.payment_status',
                'payment_method', 'a.payment_method',
                'user_id', 'a.user_id',
                'created', 'a.created',
                'payment_time', 'a.payment_time',
                'modified', 'a.modified',
                'state', 'a.state'
            );
        }
        
        parent::__construct($config);
    }
    
    protected function getListQuery()
    {
        $db = $this->getDbo();
        $query = $db->getQuery(true);
        
        // 查询订单
        $query->select('a.*')
              ->select('u.name as customer_name')
              ->select('u.username as customer_username')
              ->select('u.email as customer_email')
              ->from($db->quoteName('#__social_orders', 'a'))
              ->leftJoin($db->quoteName('#__users', 'u') . ' ON u.id = a.user_id')
              ->where('a.state = 1');
        
        // 搜索过滤
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $query->where('a.id = ' . (int)substr($search, 3));
            } elseif (stripos($search, 'order:') === 0) {
                $query->where('a.order_no LIKE ' . $db->quote('%' . substr($search, 6) . '%'));
            } elseif (stripos($search, 'user:') === 0) {
                $query->where('(u.name LIKE ' . $db->quote('%' . substr($search, 5) . '%') 
                    . ' OR u.username LIKE ' . $db->quote('%' . substr($search, 5) . '%') 
                    . ' OR u.email LIKE ' . $db->quote('%' . substr($search, 5) . '%') . ')');
            } else {
                $search = $db->quote('%' . $db->escape($search, true) . '%');
                $query->where('(a.order_no LIKE ' . $search 
                    . ' OR a.title LIKE ' . $search 
                    . ' OR a.description LIKE ' . $search . ')');
            }
        }
        
        // 状态过滤
        $status = $this->getState('filter.status');
        if ($status !== null && $status !== '') {
            $query->where('a.status = ' . $db->quote($status));
        }
        
        // 支付状态过滤
        $paymentStatus = $this->getState('filter.payment_status');
        if ($paymentStatus !== null && $paymentStatus !== '') {
            $query->where('a.payment_status = ' . $db->quote($paymentStatus));
        }
        
        // 支付方式过滤
        $paymentMethod = $this->getState('filter.payment_method');
        if ($paymentMethod !== null && $paymentMethod !== '') {
            $query->where('a.payment_method = ' . $db->quote($paymentMethod));
        }
        
        // 用户过滤
        $userId = $this->getState('filter.user_id');
        if ($userId !== null && $userId !== '') {
            $query->where('a.user_id = ' . (int)$userId);
        }
        
        // 时间范围过滤
        $dateFrom = $this->getState('filter.date_from');
        $dateTo = $this->getState('filter.date_to');
        if ($dateFrom) {
            $query->where('a.created >= ' . $db->quote($dateFrom));
        }
        if ($dateTo) {
            $query->where('a.created <= ' . $db->quote($dateTo));
        }
        
        // 金额范围过滤
        $amountFrom = $this->getState('filter.amount_from');
        $amountTo = $this->getState('filter.amount_to');
        if ($amountFrom !== null && $amountFrom !== '') {
            $query->where('a.amount >= ' . (float)$amountFrom);
        }
        if ($amountTo !== null && $amountTo !== '') {
            $query->where('a.amount <= ' . (float)$amountTo);
        }
        
        // 排序
        $orderCol = $this->state->get('list.ordering', 'a.created');
        $orderDirn = $this->state->get('list.direction', 'DESC');
        $query->order($db->escape($orderCol . ' ' . $orderDirn));
        
        return $query;
    }
    
    protected function populateState($ordering = 'a.created', $direction = 'DESC')
    {
        $app = Factory::getApplication();
        
        // 从请求参数中加载状态
        $search = $app->input->get('search', '', 'string');
        $this->setState('filter.search', $search);
        
        $status = $app->input->get('status', '', 'string');
        $this->setState('filter.status', $status);
        
        $paymentStatus = $app->input->get('payment_status', '', 'string');
        $this->setState('filter.payment_status', $paymentStatus);
        
        $paymentMethod = $app->input->get('payment_method', '', 'string');
        $this->setState('filter.payment_method', $paymentMethod);
        
        $userId = $app->input->get('user_id', '', 'int');
        $this->setState('filter.user_id', $userId);
        
        $dateFrom = $app->input->get('date_from', '', 'string');
        $this->setState('filter.date_from', $dateFrom);
        
        $dateTo = $app->input->get('date_to', '', 'string');
        $this->setState('filter.date_to', $dateTo);
        
        $amountFrom = $app->input->get('amount_from', '', 'float');
        $this->setState('filter.amount_from', $amountFrom);
        
        $amountTo = $app->input->get('amount_to', '', 'float');
        $this->setState('filter.amount_to', $amountTo);
        
        // 列表参数
        $limit = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->get('list_limit'), 'uint');
        $this->setState('list.limit', $limit);
        
        $limitstart = $app->input->get('limitstart', 0, 'uint');
        $this->setState('list.start', $limitstart);
        
        // 排序
        $listOrder = $app->input->get('filter_order', $ordering);
        $this->setState('list.ordering', $listOrder);
        
        $listDirn = $app->input->get('filter_order_Dir', $direction);
        $this->setState('list.direction', $listDirn);
        
        parent::populateState($ordering, $direction);
    }
    
    public function getItems()
    {
        $items = parent::getItems();
        
        if (!empty($items)) {
            $db = $this->getDbo();
            
            foreach ($items as $item) {
                // 格式化金额
                $item->amount_formatted = $this->formatAmount($item->amount, $item->currency);
                $item->real_amount_formatted = $this->formatAmount($item->real_amount, $item->currency);
                $item->refund_amount_formatted = $this->formatAmount($item->refund_amount, $item->currency);
                
                // 获取商品数量
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from('#__social_order_items')
                    ->where('order_id = ' . (int)$item->id);
                $db->setQuery($query);
                $item->item_count = $db->loadResult();
                
                // 状态文本
                $item->status_text = $this->getStatusText($item->status);
                $item->payment_status_text = $this->getPaymentStatusText($item->payment_status);
                $item->shipping_status_text = $this->getShippingStatusText($item->shipping_status);
                
                // 格式化时间
                $item->created_formatted = $item->created ? JHtml::_('date', $item->created, 'Y-m-d H:i:s') : '';
                $item->payment_time_formatted = $item->payment_time ? JHtml::_('date', $item->payment_time, 'Y-m-d H:i:s') : '';
                $item->modified_formatted = $item->modified ? JHtml::_('date', $item->modified, 'Y-m-d H:i:s') : '';
            }
        }
        
        return $items;
    }
    
    public function getStatistics()
    {
        $db = $this->getDbo();
        $query = $db->getQuery(true);
        
        $query->select('COUNT(*) as total_orders')
              ->select('SUM(amount) as total_amount')
              ->select('SUM(CASE WHEN payment_status = "paid" THEN amount ELSE 0 END) as paid_amount')
              ->select('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_orders')
              ->select('SUM(CASE WHEN payment_status = "unpaid" THEN 1 ELSE 0 END) as unpaid_orders')
              ->select('SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled_orders')
              ->select('SUM(refund_amount) as refund_amount')
              ->from('#__social_orders')
              ->where('state = 1');
        
        // 应用过滤条件
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $query->where('id = ' . (int)substr($search, 3));
            } else {
                $search = $db->quote('%' . $db->escape($search, true) . '%');
                $query->where('(order_no LIKE ' . $search . ' OR title LIKE ' . $search . ')');
            }
        }
        
        $status = $this->getState('filter.status');
        if ($status !== null && $status !== '') {
            $query->where('status = ' . $db->quote($status));
        }
        
        $paymentStatus = $this->getState('filter.payment_status');
        if ($paymentStatus !== null && $paymentStatus !== '') {
            $query->where('payment_status = ' . $db->quote($paymentStatus));
        }
        
        $dateFrom = $this->getState('filter.date_from');
        $dateTo = $this->getState('filter.date_to');
        if ($dateFrom) {
            $query->where('created >= ' . $db->quote($dateFrom));
        }
        if ($dateTo) {
            $query->where('created <= ' . $db->quote($dateTo));
        }
        
        $db->setQuery($query);
        return $db->loadObject();
    }
    
    public function getDashboardData($period = '7days')
    {
        $db = $this->getDbo();
        $now = Factory::getDate()->toSql();
        
        // 根据时间段确定开始时间
        switch ($period) {
            case 'today':
                $startDate = date('Y-m-d 00:00:00');
                break;
            case 'yesterday':
                $startDate = date('Y-m-d 00:00:00', strtotime('-1 day'));
                $endDate = date('Y-m-d 23:59:59', strtotime('-1 day'));
                break;
            case '7days':
                $startDate = date('Y-m-d 00:00:00', strtotime('-7 days'));
                break;
            case '30days':
                $startDate = date('Y-m-d 00:00:00', strtotime('-30 days'));
                break;
            default:
                $startDate = date('Y-m-d 00:00:00', strtotime('-7 days'));
        }
        
        // 订单统计
        $query = $db->getQuery(true)
            ->select('COUNT(*) as order_count')
            ->select('SUM(amount) as order_amount')
            ->select('SUM(CASE WHEN payment_status = "paid" THEN amount ELSE 0 END) as paid_amount')
            ->select('DATE(created) as order_date')
            ->from('#__social_orders')
            ->where('created >= ' . $db->quote($startDate))
            ->where('state = 1')
            ->group('DATE(created)')
            ->order('order_date');
        
        $db->setQuery($query);
        $orderStats = $db->loadObjectList();
        
        // 支付方式统计
        $query = $db->getQuery(true)
            ->select('payment_method')
            ->select('COUNT(*) as count')
            ->select('SUM(amount) as amount')
            ->from('#__social_orders')
            ->where('created >= ' . $db->quote($startDate))
            ->where('state = 1')
            ->where('payment_method IS NOT NULL')
            ->group('payment_method');
        
        $db->setQuery($query);
        $paymentStats = $db->loadObjectList();
        
        // 状态统计
        $query = $db->getQuery(true)
            ->select('status')
            ->select('COUNT(*) as count')
            ->from('#__social_orders')
            ->where('created >= ' . $db->quote($startDate))
            ->where('state = 1')
            ->group('status');
        
        $db->setQuery($query);
        $statusStats = $db->loadObjectList();
        
        return [
            'order_stats' => $orderStats,
            'payment_stats' => $paymentStats,
            'status_stats' => $statusStats
        ];
    }
    
    public function exportOrders($orderIds = [])
    {
        $db = $this->getDbo();
        
        $query = $db->getQuery(true)
            ->select('o.*')
            ->select('u.name as customer_name')
            ->select('u.email as customer_email')
            ->select('u.username as customer_username')
            ->from('#__social_orders as o')
            ->leftJoin('#__users as u ON u.id = o.user_id')
            ->where('o.state = 1');
        
        if (!empty($orderIds)) {
            $query->where('o.id IN (' . implode(',', array_map('intval', $orderIds)) . ')');
        }
        
        $db->setQuery($query);
        $orders = $db->loadObjectList();
        
        if (empty($orders)) {
            return '';
        }
        
        // 生成CSV
        $output = fopen('php://output', 'w');
        
        // 添加BOM头，确保Excel正确识别UTF-8
        $csvContent = "\xEF\xBB\xBF";
        
        // 添加标题行
        $headers = [
            '订单ID', '订单号', '用户ID', '客户姓名', '客户邮箱', '订单标题',
            '订单金额', '实付金额', '货币', '订单状态', '支付状态', '支付方式',
            '交易号', '支付时间', '退款金额', '退款状态', '发货状态',
            '创建时间', '修改时间'
        ];
        
        $csvContent .= implode(',', array_map(function($header) {
            return '"' . $header . '"';
        }, $headers)) . "\n";
        
        // 添加数据行
        foreach ($orders as $order) {
            $row = [
                $order->id,
                $order->order_no,
                $order->user_id,
                $order->customer_name,
                $order->customer_email,
                $order->title,
                $order->amount,
                $order->real_amount,
                $order->currency,
                $this->getStatusText($order->status),
                $this->getPaymentStatusText($order->payment_status),
                $order->payment_method,
                $order->transaction_id,
                $order->payment_time,
                $order->refund_amount,
                $order->refund_status,
                $order->shipping_status,
                $order->created,
                $order->modified
            ];
            
            $csvContent .= implode(',', array_map(function($value) {
                return '"' . str_replace('"', '""', $value) . '"';
            }, $row)) . "\n";
        }
        
        return $csvContent;
    }
    
    public function batchProcess($action, $orderIds)
    {
        $db = $this->getDbo();
        $user = Factory::getUser();
        $now = Factory::getDate()->toSql();
        
        if (empty($orderIds)) {
            throw new Exception('请选择要处理的订单');
        }
        
        $ids = array_map('intval', $orderIds);
        $idList = implode(',', $ids);
        
        switch ($action) {
            case 'delete':
                // 软删除订单
                $query = $db->getQuery(true)
                    ->update('#__social_orders')
                    ->set('state = 0')
                    ->set('modified = ' . $db->quote($now))
                    ->set('modified_by = ' . (int)$user->id)
                    ->where('id IN (' . $idList . ')')
                    ->where('state = 1');
                
                $db->setQuery($query);
                return $db->execute();
                
            case 'cancel':
                // 取消订单
                $query = $db->getQuery(true)
                    ->update('#__social_orders')
                    ->set('status = ' . $db->quote('cancelled'))
                    ->set('modified = ' . $db->quote($now))
                    ->set('modified_by = ' . (int)$user->id)
                    ->set('admin_note = CONCAT(admin_note, "\n批量取消：' . $now . '")')
                    ->where('id IN (' . $idList . ')')
                    ->where('state = 1')
                    ->where('status IN ("pending", "processing")');
                
                $db->setQuery($query);
                return $db->execute();
                
            case 'complete':
                // 标记为完成
                $query = $db->getQuery(true)
                    ->update('#__social_orders')
                    ->set('status = ' . $db->quote('completed'))
                    ->set('modified = ' . $db->quote($now))
                    ->set('modified_by = ' . (int)$user->id)
                    ->where('id IN (' . $idList . ')')
                    ->where('state = 1')
                    ->where('status = "processing"');
                
                $db->setQuery($query);
                return $db->execute();
                
            case 'export':
                // 导出订单
                return $this->exportOrders($orderIds);
                
            default:
                throw new Exception('未知的操作类型');
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
}