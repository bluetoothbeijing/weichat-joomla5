<?php
defined('_JEXEC') or die;

class SocialordersViewOrder extends JViewLegacy
{
    protected $item;
    
    public function display($tpl = null)
    {
        $this->item = $this->get('Item');
        
        // 检查错误
        if (count($errors = $this->get('Errors'))) {
            JFactory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
            return false;
        }
        
        // 检查权限
        if (!$this->item) {
            JFactory::getApplication()->enqueueMessage('订单不存在', 'error');
            JFactory::getApplication()->redirect(JRoute::_('index.php?option=com_socialorders', false));
            return false;
        }
        
        $user = JFactory::getUser();
        if ($this->item->user_id != $user->id && !$user->authorise('core.admin')) {
            JFactory::getApplication()->enqueueMessage('无权查看此订单', 'error');
            JFactory::getApplication()->redirect(JRoute::_('index.php?option=com_socialorders', false));
            return false;
        }
        
        $this->prepareDocument();
        parent::display($tpl);
    }
    
    protected function prepareDocument()
    {
        $app = JFactory::getApplication();
        $title = '订单详情 - ' . $this->item->order_no;
        
        $this->document->setTitle($title);
    }
}