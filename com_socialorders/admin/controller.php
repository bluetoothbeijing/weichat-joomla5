<?php
/**
 * @package     Socialorders
 * @subpackage  com_socialorders
 *
 * @copyright   Copyright (C) 2024. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/**
 * Socialorders Base Controller
 */
class SocialordersController extends BaseController
{
    /**
     * The default view.
     *
     * @var    string
     * @since  1.0
     */
    protected $default_view = 'orders';
    
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     */
    public function __construct($config = array())
    {
        parent::__construct($config);
        
        // 注册任务到方法映射
        $this->registerTask('apply', 'save');
        $this->registerTask('save2new', 'save');
        $this->registerTask('save2copy', 'save');
    }
    
    /**
     * Method to display a view.
     *
     * @param   boolean  $cachable   If true, the view output will be cached
     * @param   array    $urlparams  An array of safe URL parameters and their variable types
     *
     * @return  static  This object to support chaining.
     */
    public function display($cachable = false, $urlparams = array())
    {
        // 获取视图和布局
        $view   = $this->input->get('view', $this->default_view);
        $layout = $this->input->get('layout', 'default');
        $id     = $this->input->getInt('id');
        
        // 检查编辑权限
        if ($view == 'order' && $layout == 'edit') {
            if (!$this->checkEditId('com_socialorders.edit.order', $id)) {
                $this->setError(Text::sprintf('JLIB_APPLICATION_ERROR_UNHELD_ID', $id));
                $this->setMessage($this->getError(), 'error');
                
                // 重定向到列表
                $this->setRedirect(
                    Route::_('index.php?option=com_socialorders&view=orders', false)
                );
                
                return false;
            }
        }
        
        // 设置子菜单
        SocialordersHelper::addSubmenu($view);
        
        return parent::display($cachable, $urlparams);
    }
    
    /**
     * 保存方法
     *
     * @return  boolean  True on success, false on failure.
     */
    public function save()
    {
        // 检查令牌
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        
        $app   = Factory::getApplication();
        $input = $app->input;
        $model = $this->getModel('order');
        $data  = $input->get('jform', array(), 'array');
        
        // 尝试保存
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
     * 取消方法
     *
     * @return  void
     */
    public function cancel()
    {
        $this->setRedirect(Route::_('index.php?option=com_socialorders&view=orders', false));
    }
}