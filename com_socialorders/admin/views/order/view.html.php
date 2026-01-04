<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;

class SocialordersViewOrder extends HtmlView
{
    protected $form;
    protected $item;
    protected $state;
    
    public function display($tpl = null)
    {
        $this->form  = $this->get('Form');
        $this->item  = $this->get('Item');
        $this->state = $this->get('State');
        
        // 检查错误
        if (count($errors = $this->get('Errors'))) {
            throw new Exception(implode("\n", $errors), 500);
        }
        
        $this->addToolbar();
        parent::display($tpl);
    }
    
    protected function addToolbar()
    {
        Factory::getApplication()->input->set('hidemainmenu', true);
        
        $user       = Factory::getUser();
        $userId     = $user->id;
        $isNew      = ($this->item->id == 0);
        $canDo      = JHelperContent::getActions('com_socialorders');
        
        ToolbarHelper::title($isNew ? Text::_('COM_SOCIALORDERS_ORDER_NEW') : Text::_('COM_SOCIALORDERS_ORDER_EDIT'), 'stack order');
        
        // 如果是新建或编辑状态
        if ($isNew) {
            // 新建
            if ($canDo->get('core.create')) {
                ToolbarHelper::apply('order.apply');
                ToolbarHelper::save('order.save');
                ToolbarHelper::save2new('order.save2new');
            }
            ToolbarHelper::cancel('order.cancel');
        } else {
            // 编辑
            if ($canDo->get('core.edit') || ($canDo->get('core.edit.own') && $this->item->created_by == $userId)) {
                ToolbarHelper::apply('order.apply');
                ToolbarHelper::save('order.save');
                
                // 保存并复制
                if ($canDo->get('core.create')) {
                    ToolbarHelper::save2new('order.save2new');
                    ToolbarHelper::save2copy('order.save2copy');
                }
            }
            
            // 支付操作
            if ($this->item->payment_status == 'unpaid') {
                ToolbarHelper::custom('order.pay', 'credit', 'credit', 'COM_SOCIALORDERS_ORDER_PAY', false);
            }
            
            // 退款操作
            if ($this->item->payment_status == 'paid' && $this->item->refund_status != 'success') {
                ToolbarHelper::custom('order.refund', 'undo', 'undo', 'COM_SOCIALORDERS_ORDER_REFUND', false);
            }
            
            // 发货操作
            if ($this->item->status == 'processing' && $this->item->shipping_status == 'unshipped') {
                ToolbarHelper::custom('order.ship', 'truck', 'truck', 'COM_SOCIALORDERS_ORDER_SHIP', false);
            }
            
            ToolbarHelper::cancel('order.cancel', 'JTOOLBAR_CLOSE');
        }
    }
}