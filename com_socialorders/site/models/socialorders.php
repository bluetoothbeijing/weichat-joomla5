<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Pagination\Pagination;

class SocialordersModelSocialorders extends ListModel
{
    public function __construct($config = array())
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'id', 'o.id',
                'order_no', 'o.order_no',
                'title', 'o.title',
                'amount', 'o.amount',
                'status', 'o.status',
                'payment_status', 'o.payment_status',
                'created', 'o.created',
                'user_id', 'o.user_id'
            );
        }
        
        parent::__construct($config);
    }
    
    protected function getListQuery()
    {
        $db = $this->getDbo();
        $query = $db->getQuery(true);
        $user = Factory::getUser();
        
        // 获取组件参数
        $params = ComponentHelper::getParams('com_socialorders');
        $allowUserOrders = $params->get('allow_user_orders', 1);
        
        // 查询订单
        $query->select('o.*')
              ->from($db->quoteName('#__social_orders', 'o'))
              ->where('o.state = 1');
        
        // 如果不是管理员，只能查看自己的订单
        if (!$user->authorise('core.admin') && $allowUserOrders) {
            $query->where('o.user_id = ' . (int)$user->id);
        }
        
        // 搜索过滤
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $query->where('o.id = ' . (int)substr($search, 3));
            } else {
                $search = $db->quote('%' . $db->escape($search, true) . '%');
                $query->where('(o.order_no LIKE ' . $search . ' OR o.title LIKE ' . $search . ')');
            }
        }
        
        // 状态过滤
        $status = $this->getState('filter.status');
        if ($status !== null && $status !== '') {
            $query->where('o.status = ' . $db->quote($status));
        }
        
        // 支付状态过滤
        $paymentStatus = $this->getState('filter.payment_status');
        if ($paymentStatus !== null && $paymentStatus !== '') {
            $query->where('o.payment_status = ' . $db->quote($paymentStatus));
        }
        
        // 时间范围过滤
        $dateFrom = $this->getState('filter.date_from');
        $dateTo = $this->getState('filter.date_to');
        if ($dateFrom) {
            $query->where('o.created >= ' . $db->quote($dateFrom));
        }
        if ($dateTo) {
            $query->where('o.created <= ' . $db->quote($dateTo));
        }
        
        // 排序
        $orderCol = $this->state->get('list.ordering', 'o.created');
        $orderDirn = $this->state->get('list.direction', 'DESC');
        $query->order($db->escape($orderCol . ' ' . $orderDirn));
        
        return $query;
    }
    
    protected function populateState($ordering = 'o.created', $direction = 'DESC')
    {
        $app = Factory::getApplication();
        
        // 从请求参数中加载状态
        $search = $app->input->get('search', '', 'string');
        $this->setState('filter.search', $search);
        
        $status = $app->input->get('status', '', 'string');
        $this->setState('filter.status', $status);
        
        $paymentStatus = $app->input->get('payment_status', '', 'string');
        $this->setState('filter.payment_status', $paymentStatus);
        
        $dateFrom = $app->input->get('date_from', '', 'string');
        $this->setState('filter.date_from', $dateFrom);
        
        $dateTo = $app->input->get('date_to', '', 'string');
        $this->setState('filter.date_to', $dateTo);
        
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
        
        // 处理每个订单的额外信息
        if (!empty($items)) {
            $db = $this->getDbo();
            $user = Factory::getUser();
            
            foreach ($items as $item) {
                // 格式化金额
                $item->amount_formatted = $this->formatAmount($item->amount, $item->currency);
                $item->real_amount_formatted = $this->formatAmount($item->real_amount, $item->currency);
                
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
                
                // 检查是否可操作
                $item->canEdit = $user->id == $item->user_id || $user->authorise('core.edit', 'com_socialorders');
                $item->canCancel = $item->canEdit && $item->status == 'pending';
                $item->canPay = $item->canEdit && $item->payment_status == 'unpaid';
            }
        }
        
        return $items;
    }
    
    public function getOrderStatistics($userId = null)
    {
        $db = $this->getDbo();
        $user = Factory::getUser();
        
        $query = $db->getQuery(true)
            ->select('COUNT(*) as total_orders')
            ->select('SUM(amount) as total_amount')
            ->select('SUM(CASE WHEN payment_status = "paid" THEN amount ELSE 0 END) as paid_amount')
            ->select('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_orders')
            ->from('#__social_orders')
            ->where('state = 1');
        
        if ($userId) {
            $query->where('user_id = ' . (int)$userId);
        } elseif (!$user->authorise('core.admin')) {
            $query->where('user_id = ' . (int)$user->id);
        }
        
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
}