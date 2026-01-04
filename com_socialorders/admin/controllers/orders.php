<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

class SocialordersControllerOrders extends AdminController
{
    protected $text_prefix = 'COM_SOCIALORDERS';
    
    public function getModel($name = 'Order', $prefix = 'SocialordersModel', $config = array('ignore_request' => true))
    {
        return parent::getModel($name, $prefix, $config);
    }
    
    public function export()
    {
        $app = Factory::getApplication();
        $cid = $app->input->get('cid', array(), 'array');
        
        if (empty($cid)) {
            $this->setMessage(Text::_('COM_SOCIALORDERS_NO_ITEMS_SELECTED'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_socialorders&view=orders', false));
            return false;
        }
        
        $model = $this->getModel('Orders');
        $result = $model->exportOrders($cid);
        
        if ($result) {
            $app->setHeader('Content-Type', 'text/csv; charset=utf-8');
            $app->setHeader('Content-Disposition', 'attachment; filename=orders_export_' . date('YmdHis') . '.csv');
            $app->sendHeaders();
            
            echo $result;
            $app->close();
        } else {
            $this->setMessage(Text::_('COM_SOCIALORDERS_EXPORT_FAILED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_socialorders&view=orders', false));
        }
    }
    
    public function refund()
    {
        $app = Factory::getApplication();
        $id = $app->input->getInt('id', 0);
        
        if (!$id) {
            $this->setMessage(Text::_('COM_SOCIALORDERS_NO_ITEM_SELECTED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_socialorders&view=orders', false));
            return false;
        }
        
        try {
            $model = $this->getModel('Order');
            $result = $model->refund($id);
            
            if ($result) {
                $this->setMessage(Text::_('COM_SOCIALORDERS_REFUND_SUCCESS'));
            } else {
                $this->setMessage(Text::_('COM_SOCIALORDERS_REFUND_FAILED'), 'error');
            }
        } catch (Exception $e) {
            $this->setMessage($e->getMessage(), 'error');
        }
        
        $this->setRedirect(Route::_('index.php?option=com_socialorders&view=order&layout=edit&id=' . $id, false));
    }
}