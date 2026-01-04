<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Date\Date;

/**
 * Order model.
 */
class SocialordersModelOrder extends AdminModel
{
    /**
     * Returns a Table object.
     */
    public function getTable($type = 'Order', $prefix = 'SocialordersTable', $config = array())
    {
        Table::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/tables');
        return Table::getInstance($type, $prefix, $config);
    }
    
    /**
     * Method to get the record form.
     */
    public function getForm($data = array(), $loadData = true)
    {
        // 返回一个空表单对象，避免错误
        return new JForm('com_socialorders.order');
    }
    
    /**
     * Method to get a single record.
     */
    public function getItem($pk = null)
    {
        $pk = (!empty($pk)) ? $pk : (int) $this->getState($this->getName() . '.id');
        
        if ($pk > 0) {
            $table = $this->getTable();
            if ($table->load($pk)) {
                $properties = $table->getProperties(1);
                $item = ArrayHelper::toObject($properties, 'stdClass');
                return $item;
            }
        }
        
        // 返回新对象
        $item = new stdClass();
        $item->id = 0;
        $item->order_no = 'SO' . date('YmdHis') . rand(1000, 9999);
        $item->title = '';
        $item->amount = '0.00';
        $item->status = 'pending';
        $item->state = 1;
        $item->created = Factory::getDate()->toSql();
        
        return $item;
    }
    
    /**
     * 保存数据（手动处理）
     */
    public function save($data)
    {
        $table = $this->getTable();
        $key = $table->getKeyName();
        $pk = isset($data[$key]) ? $data[$key] : 0;
        
        // 加载现有记录（如果是编辑）
        if ($pk > 0) {
            $table->load($pk);
        }
        
        // 绑定数据
        if (!$table->bind($data)) {
            $this->setError($table->getError());
            return false;
        }
        
        // 设置创建时间（如果是新记录）
        if ($pk == 0 && empty($table->created)) {
            $table->created = Factory::getDate()->toSql();
        }
        
        // 设置修改时间
        $table->modified = Factory::getDate()->toSql();
        
        // 检查数据
        if (!$table->check()) {
            $this->setError($table->getError());
            return false;
        }
        
        // 存储数据
        if (!$table->store()) {
            $this->setError($table->getError());
            return false;
        }
        
        // 设置ID
        $this->setState($this->getName() . '.id', $table->$key);
        
        return true;
    }
}