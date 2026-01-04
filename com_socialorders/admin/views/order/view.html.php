<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

/**
 * Order view.
 */
class SocialordersViewOrder extends HtmlView
{
    /**
     * Display the view
     */
    public function display($tpl = null)
    {
        $this->addToolbar();
        parent::display($tpl);
    }
    
    /**
     * Add the page title and toolbar.
     */
    protected function addToolbar()
    {
        Factory::getApplication()->input->set('hidemainmenu', true);
        
        $input = Factory::getApplication()->input;
        $id = $input->getInt('id', 0);
        $isNew = ($id == 0);
        
        // 设置标题
        if ($isNew) {
            ToolbarHelper::title(Text::_('COM_SOCIALORDERS_MANAGER_ORDER_NEW'), 'order');
        } else {
            ToolbarHelper::title(Text::_('COM_SOCIALORDERS_MANAGER_ORDER_EDIT'), 'order');
        }
        
        // 添加工具栏按钮
        ToolbarHelper::apply('order.apply', 'JTOOLBAR_APPLY');
        ToolbarHelper::save('order.save', 'JTOOLBAR_SAVE');
        
        ToolbarHelper::save2new('order.save2new');
        
        if (!$isNew) {
            ToolbarHelper::save2copy('order.save2copy');
        }
        
        if (empty($id)) {
            ToolbarHelper::cancel('order.cancel', 'JTOOLBAR_CANCEL');
        } else {
            ToolbarHelper::cancel('order.cancel', 'JTOOLBAR_CLOSE');
        }
    }
}