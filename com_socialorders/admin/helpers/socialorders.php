<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

class SocialordersHelper
{
    /**
     * Configure the Linkbar.
     */
    public static function addSubmenu($vName = 'orders')
    {
        // 简化的子菜单添加，避免依赖旧API
        if (version_compare(JVERSION, '4.0', '<')) {
            // Joomla 3.x
            if (class_exists('JHtmlSidebar')) {
                JHtmlSidebar::addEntry(
                    Text::_('COM_SOCIALORDERS_SUBMENU_ORDERS'),
                    'index.php?option=com_socialorders&view=orders',
                    $vName == 'orders'
                );
            }
        }
    }
    
    /**
     * Gets the list of actions that can be performed.
     */
    public static function getActions()
    {
        $user = Factory::getUser();
        $result = new JObject;
        
        $actions = array(
            'core.admin', 'core.manage', 'core.create', 'core.edit',
            'core.edit.own', 'core.edit.state', 'core.delete'
        );
        
        foreach ($actions as $action) {
            $result->set($action, $user->authorise($action, 'com_socialorders'));
        }
        
        return $result;
    }
}