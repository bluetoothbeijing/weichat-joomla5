<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;

class SocialordersViewSocialorders extends HtmlView
{
    protected $items;
    protected $pagination;
    protected $state;
    protected $params;
    protected $statistics;
    
    public function display($tpl = null)
    {
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state = $this->get('State');
        $this->params = $this->state->get('params');
        $this->statistics = $this->get('OrderStatistics');
        
        // 检查错误
        if (count($errors = $this->get('Errors'))) {
            throw new Exception(implode("\n", $errors), 500);
        }
        
        $this->prepareDocument();
        parent::display($tpl);
    }
    
    protected function prepareDocument()
    {
        $app = Factory::getApplication();
        $title = Text::_('COM_SOCIALORDERS_PAGE_TITLE');
        
        $this->document->setTitle($title);
        $this->document->setDescription(Text::_('COM_SOCIALORDERS_PAGE_DESC'));
        
        // 添加面包屑
        $pathway = $app->getPathway();
        $pathway->addItem(Text::_('COM_SOCIALORDERS_ORDERS'));
        
        // 添加CSS和JS
        JHtml::_('jquery.framework');
        $this->document->addStyleSheet(JUri::root(true) . '/media/com_socialorders/css/frontend.css');
        $this->document->addScript(JUri::root(true) . '/media/com_socialorders/js/socialorders.js');
    }
    
    public function getFilterStatus()
    {
        return [
            '' => Text::_('JALL'),
            'pending' => Text::_('COM_SOCIALORDERS_STATUS_PENDING'),
            'processing' => Text::_('COM_SOCIALORDERS_STATUS_PROCESSING'),
            'completed' => Text::_('COM_SOCIALORDERS_STATUS_COMPLETED'),
            'cancelled' => Text::_('COM_SOCIALORDERS_STATUS_CANCELLED'),
            'refunded' => Text::_('COM_SOCIALORDERS_STATUS_REFUNDED')
        ];
    }
    
    public function getFilterPaymentStatus()
    {
        return [
            '' => Text::_('JALL'),
            'unpaid' => Text::_('COM_SOCIALORDERS_PAYMENT_STATUS_UNPAID'),
            'paid' => Text::_('COM_SOCIALORDERS_PAYMENT_STATUS_PAID'),
            'refunded' => Text::_('COM_SOCIALORDERS_PAYMENT_STATUS_REFUNDED'),
            'failed' => Text::_('COM_SOCIALORDERS_PAYMENT_STATUS_FAILED')
        ];
    }
}