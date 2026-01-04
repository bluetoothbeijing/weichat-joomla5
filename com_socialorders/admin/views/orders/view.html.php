<?php
/**
 * @package     Socialorders
 * @subpackage  com_socialorders
 *
 * @copyright   Copyright (C) 2024. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * Orders view.
 */
class SocialordersViewOrders extends HtmlView
{
    /**
     * 项目数组
     *
     * @var  array
     */
    protected $items;
    
    /**
     * 分页对象
     *
     * @var  JPagination
     */
    protected $pagination;
    
    /**
     * 模型状态
     *
     * @var  object
     */
    protected $state;
    
    /**
     * 过滤表单
     *
     * @var  JForm
     */
    public $filterForm;
    
    /**
     * 活动过滤器
     *
     * @var  array
     */
    public $activeFilters;
    
    /**
     * 显示视图
     *
     * @param   string  $tpl  模板名称
     *
     * @return  void
     */
    public function display($tpl = null)
    {
        // 获取数据
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state = $this->get('State');
        
        // 尝试获取过滤表单，但不强制要求
        try {
            $this->filterForm = $this->get('FilterForm');
            $this->activeFilters = $this->get('ActiveFilters');
        } catch (Exception $e) {
            // 如果过滤表单不存在，设置为null
            $this->filterForm = null;
            $this->activeFilters = array();
        }
        
        // 检查错误
        if (count($errors = $this->get('Errors'))) {
            throw new Exception(implode("\n", $errors), 500);
        }
        
        // 设置工具栏
        $this->addToolbar();
        
        // 设置侧边栏（如果Joomla版本支持）
        SocialordersHelper::addSubmenu('orders');
        
        // 如果是Joomla 3.x，添加筛选栏
        if (version_compare(JVERSION, '4.0', '<')) {
            $this->sidebar = JHtmlSidebar::render();
        }
        
        parent::display($tpl);
    }
    
    /**
     * 添加工具栏
     *
     * @return  void
     */
    protected function addToolbar()
    {
        $canDo = SocialordersHelper::getActions();
        
        // 设置标题
        ToolbarHelper::title(Text::_('COM_SOCIALORDERS_MANAGER_ORDERS'), 'orders');
        
        // 添加按钮
        if ($canDo->get('core.create')) {
            ToolbarHelper::addNew('order.add');
        }
        
        if ($canDo->get('core.edit') || $canDo->get('core.edit.own')) {
            ToolbarHelper::editList('order.edit');
        }
        
        if ($canDo->get('core.edit.state')) {
            ToolbarHelper::publish('orders.publish', 'JTOOLBAR_PUBLISH', true);
            ToolbarHelper::unpublish('orders.unpublish', 'JTOOLBAR_UNPUBLISH', true);
            ToolbarHelper::checkin('orders.checkin');
        }
        
        if ($canDo->get('core.delete')) {
            ToolbarHelper::deleteList('JGLOBAL_CONFIRM_DELETE', 'orders.delete', 'JTOOLBAR_DELETE');
        }
        
        if ($canDo->get('core.admin') || $canDo->get('core.options')) {
            ToolbarHelper::preferences('com_socialorders');
        }
        
        // 添加批量操作
        ToolbarHelper::divider();
        
        // 添加帮助按钮
        ToolbarHelper::help('JHELP_COMPONENTS_SOCIALORDERS_ORDERS');
    }
    
    /**
     * 返回排序列
     *
     * @return  array
     */
    protected function getSortFields()
    {
        return array(
            'a.id' => Text::_('JGRID_HEADING_ID'),
            'a.order_no' => Text::_('COM_SOCIALORDERS_HEADING_ORDER_NO'),
            'a.title' => Text::_('COM_SOCIALORDERS_HEADING_TITLE'),
            'a.amount' => Text::_('COM_SOCIALORDERS_HEADING_AMOUNT'),
            'a.status' => Text::_('COM_SOCIALORDERS_HEADING_STATUS'),
            'a.payment_status' => Text::_('COM_SOCIALORDERS_HEADING_PAYMENT_STATUS'),
            'a.created' => Text::_('COM_SOCIALORDERS_HEADING_CREATED'),
            'a.state' => Text::_('JSTATUS'),
        );
    }
}