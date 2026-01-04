<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;

class SocialordersController extends BaseController
{
    protected $default_view = 'socialorders';
    
    public function display($cachable = false, $urlparams = array())
    {
        $app = Factory::getApplication();
        $view = $app->input->getCmd('view', $this->default_view);
        $layout = $app->input->getCmd('layout', 'default');
        $id = $app->input->getInt('id');
        
        // 检查视图访问权限
        if ($view === 'order' && $id) {
            $user = Factory::getUser();
            $model = $this->getModel('Order', 'SocialordersModel');
            
            if (!$model->canView($id, $user->id)) {
                $this->setMessage('无权查看此订单', 'error');
                $this->setRedirect(Route::_('index.php?option=com_socialorders', false));
                return false;
            }
        }
        
        return parent::display($cachable, $urlparams);
    }
    
    public function createOrder()
    {
        $app = Factory::getApplication();
        $model = $this->getModel('Order', 'SocialordersModel');
        $data = $app->input->post->getArray();
        
        try {
            $orderId = $model->create($data);
            
            if ($orderId) {
                $this->setMessage('订单创建成功');
                $this->setRedirect(Route::_('index.php?option=com_socialorders&view=order&id=' . $orderId, false));
            } else {
                $this->setMessage('订单创建失败', 'error');
                $this->setRedirect(Route::_('index.php?option=com_socialorders&view=socialorders', false));
            }
        } catch (Exception $e) {
            $this->setMessage($e->getMessage(), 'error');
            $this->setRedirect(Route::_('index.php?option=com_socialorders&view=socialorders', false));
        }
        
        return false;
    }
    
    public function cancelOrder()
    {
        $app = Factory::getApplication();
        $id = $app->input->getInt('id');
        $user = Factory::getUser();
        
        if (!$id || !$user->id) {
            $this->setMessage('参数错误', 'error');
            $this->setRedirect(Route::_('index.php?option=com_socialorders', false));
            return false;
        }
        
        try {
            $model = $this->getModel('Order', 'SocialordersModel');
            $result = $model->cancel($id, $user->id);
            
            if ($result) {
                $this->setMessage('订单已取消');
            } else {
                $this->setMessage('取消失败', 'error');
            }
        } catch (Exception $e) {
            $this->setMessage($e->getMessage(), 'error');
        }
        
        $this->setRedirect(Route::_('index.php?option=com_socialorders&view=order&id=' . $id, false));
    }
}