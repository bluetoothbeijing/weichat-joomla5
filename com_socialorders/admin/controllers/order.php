<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Order controller class.
 */
class SocialordersControllerOrder extends FormController
{
    /**
     * The context for storing internal data.
     */
    protected $context = 'order';
    
    /**
     * The prefix to use with controller messages.
     */
    protected $text_prefix = 'COM_SOCIALORDERS_ORDER';
    
    /**
     * Method to save a record.
     */
    public function save($key = null, $urlVar = 'id')
    {
        // 检查令牌
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        
        $app = Factory::getApplication();
        $input = $app->input;
        $data = $input->get('jform', array(), 'array');
        
        // 获取模型
        $model = $this->getModel();
        
        // 保存数据
        try {
            $model->save($data);
            $app->enqueueMessage(Text::_('COM_SOCIALORDERS_SAVE_SUCCESS'), 'message');
        } catch (Exception $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
            $this->setRedirect(
                Route::_('index.php?option=com_socialorders&view=order&layout=edit&id=' . $input->getInt('id'), false)
            );
            return false;
        }
        
        // 根据任务决定重定向
        $task = $this->getTask();
        switch ($task) {
            case 'apply':
                $redirect = 'index.php?option=com_socialorders&view=order&layout=edit&id=' . $model->getState('order.id');
                break;
                
            case 'save2new':
                $redirect = 'index.php?option=com_socialorders&view=order&layout=edit';
                break;
                
            case 'save2copy':
                $redirect = 'index.php?option=com_socialorders&view=order&layout=edit&id=0';
                break;
                
            default:
                $redirect = 'index.php?option=com_socialorders&view=orders';
                break;
        }
        
        $this->setRedirect(Route::_($redirect, false));
        return true;
    }
    
    /**
     * Method to cancel an edit.
     */
    public function cancel($key = null)
    {
        $this->setRedirect(Route::_('index.php?option=com_socialorders&view=orders', false));
    }
}