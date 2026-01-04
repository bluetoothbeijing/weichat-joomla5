<?php
defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;
use Joomla\CMS\Factory;

/**
 * Order table.
 */
class SocialordersTableOrder extends Table
{
    /**
     * Constructor
     */
    public function __construct(&$db)
    {
        parent::__construct('#__social_orders', 'id', $db);
    }
    
    /**
     * Overloaded check function
     */
    public function check()
    {
        // 检查订单号
        if (trim($this->order_no) == '') {
            $this->setError('Order number is required');
            return false;
        }
        
        // 检查金额
        if ($this->amount <= 0) {
            $this->setError('Amount must be greater than 0');
            return false;
        }
        
        return true;
    }
}