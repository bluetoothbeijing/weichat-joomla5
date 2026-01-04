<?php
/**
 * @package     Socialorders
 * @subpackage  com_socialorders
 *
 * @copyright   Copyright (C) 2024. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Utilities\ArrayHelper;

/**
 * Orders model.
 */
class SocialordersModelOrders extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     */
    public function __construct($config = array())
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'id', 'a.id',
                'order_no', 'a.order_no',
                'user_id', 'a.user_id',
                'title', 'a.title',
                'amount', 'a.amount',
                'status', 'a.status',
                'payment_status', 'a.payment_status',
                'created', 'a.created',
                'payment_time', 'a.payment_time',
                'state', 'a.state',
                'ordering', 'a.ordering',
            );
        }
        
        parent::__construct($config);
    }
    
    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc|desc).
     *
     * @return  void
     */
    protected function populateState($ordering = 'a.id', $direction = 'desc')
    {
        // 加载参数
        $params = ComponentHelper::getParams('com_socialorders');
        $this->setState('params', $params);
        
        // 获取列表状态
        $app = Factory::getApplication();
        $list = $app->getUserState($this->context . '.list', array());
        
        // 排序
        if (isset($list['ordering'])) {
            $ordering = $list['ordering'];
        }
        
        if (isset($list['direction'])) {
            $direction = $list['direction'];
        }
        
        $this->setState('list.ordering', $ordering);
        $this->setState('list.direction', $direction);
        
        // 分页
        $limit = $app->getUserStateFromRequest($this->context . '.list.limit', 'limit', $app->get('list_limit'), 'uint');
        $this->setState('list.limit', $limit);
        
        $limitstart = $app->input->get('limitstart', 0, 'uint');
        $this->setState('list.start', $limitstart);
        
        // 搜索
        $search = $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $search);
        
        // 状态过滤
        $published = $app->getUserStateFromRequest($this->context . '.filter.state', 'filter_state', '', 'string');
        $this->setState('filter.state', $published);
        
        // 订单状态过滤
        $orderStatus = $app->getUserStateFromRequest($this->context . '.filter.order_status', 'filter_order_status', '', 'string');
        $this->setState('filter.order_status', $orderStatus);
        
        // 支付状态过滤
        $paymentStatus = $app->getUserStateFromRequest($this->context . '.filter.payment_status', 'filter_payment_status', '', 'string');
        $this->setState('filter.payment_status', $paymentStatus);
        
        parent::populateState($ordering, $direction);
    }
    
    /**
     * Method to get a store id based on model configuration state.
     *
     * This is necessary because the model is used by the component and
     * different modules that might need different sets of data or different
     * ordering requirements.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string  A store id.
     */
    protected function getStoreId($id = '')
    {
        // 编译存储ID
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.state');
        $id .= ':' . $this->getState('filter.order_status');
        $id .= ':' . $this->getState('filter.payment_status');
        
        return parent::getStoreId($id);
    }
    
    /**
     * Build an SQL query to load the list data.
     *
     * @return  JDatabaseQuery
     */
    protected function getListQuery()
    {
        // 创建数据库查询对象
        $db = $this->getDbo();
        $query = $db->getQuery(true);
        
        // 选择字段
        $query->select(
            $this->getState(
                'list.select',
                'a.*'
            )
        );
        $query->from($db->quoteName('#__social_orders', 'a'));
        
        // 过滤：搜索
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $query->where('a.id = ' . (int) substr($search, 3));
            } elseif (stripos($search, 'order_no:') === 0) {
                $search = $db->quote('%' . $db->escape(substr($search, 9), true) . '%');
                $query->where('a.order_no LIKE ' . $search);
            } else {
                $search = $db->quote('%' . $db->escape($search, true) . '%');
                $query->where('(a.order_no LIKE ' . $search . ' OR a.title LIKE ' . $search . ')');
            }
        }
        
        // 过滤：状态
        $published = $this->getState('filter.state');
        if ($published !== '') {
            if ($published === '*') {
                // 显示所有
            } elseif ($published === '') {
                $query->where('a.state = 1');
            } else {
                $query->where('a.state = ' . (int) $published);
            }
        }
        
        // 过滤：订单状态
        $orderStatus = $this->getState('filter.order_status');
        if ($orderStatus !== '') {
            $query->where('a.status = ' . $db->quote($db->escape($orderStatus)));
        }
        
        // 过滤：支付状态
        $paymentStatus = $this->getState('filter.payment_status');
        if ($paymentStatus !== '') {
            $query->where('a.payment_status = ' . $db->quote($db->escape($paymentStatus)));
        }
        
        // 排序
        $orderCol = $this->state->get('list.ordering', 'a.id');
        $orderDirn = $this->state->get('list.direction', 'desc');
        
        // 安全检查：确保排序列是允许的
        if (!in_array($orderCol, $this->filter_fields)) {
            $orderCol = 'a.id';
        }
        
        $query->order($db->escape($orderCol . ' ' . $orderDirn));
        
        return $query;
    }
    
    /**
     * Method to get an array of data items.
     *
     * @return  mixed  An array of data items on success, false on failure.
     */
    public function getItems()
    {
        $items = parent::getItems();
        
        // 处理每个项目
        if (!empty($items)) {
            foreach ($items as &$item) {
                // 格式化金额
                if (isset($item->amount)) {
                    $item->amount_formatted = number_format($item->amount, 2);
                }
                
                // 格式化日期
                if (isset($item->created) && $item->created !== '0000-00-00 00:00:00') {
                    $item->created_formatted = Factory::getDate($item->created)->format(Text::_('DATE_FORMAT_LC2'));
                }
                
                if (isset($item->payment_time) && $item->payment_time !== '0000-00-00 00:00:00') {
                    $item->payment_time_formatted = Factory::getDate($item->payment_time)->format(Text::_('DATE_FORMAT_LC2'));
                }
            }
        }
        
        return $items;
    }
}