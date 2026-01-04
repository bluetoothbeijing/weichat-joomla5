<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;

class SocialordersViewOrders extends HtmlView
{
    protected $items;
    protected $pagination;
    protected $state;
    protected $filterForm;
    protected $activeFilters;
    
    public function display($tpl = null)
    {
        $this->items         = $this->get('Items');
        $this->pagination    = $this->get('Pagination');
        $this->state         = $this->get('State');
        $this->filterForm    = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');
        
        // 检查错误
        if (count($errors = $this->get('Errors'))) {
            throw new Exception(implode("\n", $errors), 500);
        }
        
        $this->addToolbar();
        parent::display($tpl);
    }
    
    protected function addToolbar()
    {
        $canDo = JHelperContent::getActions('com_socialorders');
        
        ToolbarHelper::title(Text::_('COM_SOCIALORDERS_MANAGER_ORDERS'), 'stack order');
        
        if ($canDo->get('core.create')) {
            ToolbarHelper::addNew('order.add');
        }
        
        if ($canDo->get('core.edit')) {
            ToolbarHelper::editList('order.edit');
        }
        
        if ($canDo->get('core.edit.state')) {
            ToolbarHelper::publish('orders.publish', 'JTOOLBAR_PUBLISH', true);
            ToolbarHelper::unpublish('orders.unpublish', 'JTOOLBAR_UNPUBLISH', true);
            ToolbarHelper::archiveList('orders.archive');
            ToolbarHelper::checkin('orders.checkin');
        }
        
        if ($this->state->get('filter.state') == -2 && $canDo->get('core.delete')) {
            ToolbarHelper::deleteList('', 'orders.delete', 'JTOOLBAR_EMPTY_TRASH');
        } elseif ($canDo->get('core.edit.state')) {
            ToolbarHelper::trash('orders.trash');
        }
        
        if ($canDo->get('core.admin')) {
            ToolbarHelper::preferences('com_socialorders');
        }
        
        // 导出按钮
        ToolbarHelper::custom('orders.export', 'download', 'download', 'COM_SOCIALORDERS_EXPORT', false);
    }
}