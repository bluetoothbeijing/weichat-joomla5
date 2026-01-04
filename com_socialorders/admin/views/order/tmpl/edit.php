<?php
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');
HTMLHelper::_('formbehavior.chosen', 'select');

$app = JFactory::getApplication();
$input = $app->input;

// 隐藏主菜单
$app->input->set('hidemainmenu', true);

// 字段集
$fieldSets = $this->form->getFieldsets();
?>

<form action="<?php echo Route::_('index.php?option=com_socialorders&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="order-form" class="form-validate">
    
    <div class="form-horizontal">
        <div class="row-fluid">
            <div class="span9">
                <?php echo HTMLHelper::_('bootstrap.startTabSet', 'myTab', array('active' => 'general')); ?>
                
                <?php echo HTMLHelper::_('bootstrap.addTab', 'myTab', 'general', Text::_('COM_SOCIALORDERS_ORDER_DETAILS', true)); ?>
                <div class="row-fluid">
                    <div class="span6">
                        <fieldset class="adminform">
                            <legend><?php echo Text::_('COM_SOCIALORDERS_ORDER_BASIC_INFO'); ?></legend>
                            <?php foreach ($this->form->getFieldset('basic') as $field) : ?>
                                <div class="control-group">
                                    <div class="control-label"><?php echo $field->label; ?></div>
                                    <div class="controls"><?php echo $field->input; ?></div>
                                </div>
                            <?php endforeach; ?>
                        </fieldset>
                    </div>
                    <div class="span6">
                        <fieldset class="adminform">
                            <legend><?php echo Text::_('COM_SOCIALORDERS_ORDER_STATUS'); ?></legend>
                            <?php foreach ($this->form->getFieldset('status') as $field) : ?>
                                <div class="control-group">
                                    <div class="control-label"><?php echo $field->label; ?></div>
                                    <div class="controls"><?php echo $field->input; ?></div>
                                </div>
                            <?php endforeach; ?>
                        </fieldset>
                        
                        <fieldset class="adminform">
                            <legend><?php echo Text::_('COM_SOCIALORDERS_ORDER_PAYMENT'); ?></legend>
                            <?php foreach ($this->form->getFieldset('payment') as $field) : ?>
                                <div class="control-group">
                                    <div class="control-label"><?php echo $field->label; ?></div>
                                    <div class="controls"><?php echo $field->input; ?></div>
                                </div>
                            <?php endforeach; ?>
                        </fieldset>
                    </div>
                </div>
                <?php echo HTMLHelper::_('bootstrap.endTab'); ?>
                
                <?php echo HTMLHelper::_('bootstrap.addTab', 'myTab', 'items', Text::_('COM_SOCIALORDERS_ORDER_ITEMS', true)); ?>
                <div class="row-fluid">
                    <div class="span12">
                        <fieldset class="adminform">
                            <legend><?php echo Text::_('COM_SOCIALORDERS_ORDER_ITEMS'); ?></legend>
                            <div class="alert alert-info">
                                <?php echo Text::_('COM_SOCIALORDERS_ORDER_ITEMS_DESC'); ?>
                            </div>
                            <!-- 这里可以添加商品项的动态表单 -->
                        </fieldset>
                    </div>
                </div>
                <?php echo HTMLHelper::_('bootstrap.endTab'); ?>
                
                <?php echo HTMLHelper::_('bootstrap.addTab', 'myTab', 'address', Text::_('COM_SOCIALORDERS_ORDER_ADDRESS', true)); ?>
                <div class="row-fluid">
                    <div class="span6">
                        <fieldset class="adminform">
                            <legend><?php echo Text::_('COM_SOCIALORDERS_ORDER_BILLING_ADDRESS'); ?></legend>
                            <?php foreach ($this->form->getFieldset('billing_address') as $field) : ?>
                                <div class="control-group">
                                    <div class="control-label"><?php echo $field->label; ?></div>
                                    <div class="controls"><?php echo $field->input; ?></div>
                                </div>
                            <?php endforeach; ?>
                        </fieldset>
                    </div>
                    <div class="span6">
                        <fieldset class="adminform">
                            <legend><?php echo Text::_('COM_SOCIALORDERS_ORDER_SHIPPING_ADDRESS'); ?></legend>
                            <?php foreach ($this->form->getFieldset('shipping_address') as $field) : ?>
                                <div class="control-group">
                                    <div class="control-label"><?php echo $field->label; ?></div>
                                    <div class="controls"><?php echo $field->input; ?></div>
                                </div>
                            <?php endforeach; ?>
                        </fieldset>
                    </div>
                </div>
                <?php echo HTMLHelper::_('bootstrap.endTab'); ?>
                
                <?php echo HTMLHelper::_('bootstrap.addTab', 'myTab', 'notes', Text::_('COM_SOCIALORDERS_ORDER_NOTES', true)); ?>
                <div class="row-fluid">
                    <div class="span12">
                        <fieldset class="adminform">
                            <legend><?php echo Text::_('COM_SOCIALORDERS_ORDER_NOTES'); ?></legend>
                            <?php foreach ($this->form->getFieldset('notes') as $field) : ?>
                                <div class="control-group">
                                    <div class="control-label"><?php echo $field->label; ?></div>
                                    <div class="controls"><?php echo $field->input; ?></div>
                                </div>
                            <?php endforeach; ?>
                        </fieldset>
                    </div>
                </div>
                <?php echo HTMLHelper::_('bootstrap.endTab'); ?>
                
                <?php echo HTMLHelper::_('bootstrap.endTabSet'); ?>
            </div>
            
            <div class="span3">
                <fieldset class="form-vertical">
                    <legend><?php echo Text::_('COM_SOCIALORDERS_ORDER_SUMMARY'); ?></legend>
                    
                    <?php if ($this->item->id) : ?>
                    <div class="control-group">
                        <div class="control-label"><?php echo Text::_('COM_SOCIALORDERS_ORDER_NO'); ?></div>
                        <div class="controls">
                            <strong><?php echo $this->item->order_no; ?></strong>
                        </div>
                    </div>
                    
                    <div class="control-group">
                        <div class="control-label"><?php echo Text::_('COM_SOCIALORDERS_ORDER_DATE'); ?></div>
                        <div class="controls">
                            <?php echo HTMLHelper::_('date', $this->item->created, Text::_('DATE_FORMAT_LC2')); ?>
                        </div>
                    </div>
                    
                    <div class="control-group">
                        <div class="control-label"><?php echo Text::_('COM_SOCIALORDERS_ORDER_AMOUNT'); ?></div>
                        <div class="controls">
                            <span class="label label-success" style="font-size: 16px;">
                                <?php echo $this->item->amount_formatted; ?>
                            </span>
                        </div>
                    </div>
                    
                    <hr />
                    
                    <div class="control-group">
                        <div class="control-label"><?php echo Text::_('JSTATUS'); ?></div>
                        <div class="controls">
                            <?php echo $this->form->getValue('state') ? Text::_('JPUBLISHED') : Text::_('JUNPUBLISHED'); ?>
                        </div>
                    </div>
                    
                    <div class="control-group">
                        <div class="control-label"><?php echo Text::_('COM_SOCIALORDERS_ORDER_STATUS'); ?></div>
                        <div class="controls">
                            <span class="label label-<?php echo $this->item->status == 'completed' ? 'success' : ($this->item->status == 'pending' ? 'warning' : 'danger'); ?>">
                                <?php echo $this->item->status_text; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="control-group">
                        <div class="control-label"><?php echo Text::_('COM_SOCIALORDERS_PAYMENT_STATUS'); ?></div>
                        <div class="controls">
                            <span class="label label-<?php echo $this->item->payment_status == 'paid' ? 'success' : ($this->item->payment_status == 'unpaid' ? 'warning' : 'danger'); ?>">
                                <?php echo $this->item->payment_status_text; ?>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php echo $this->form->renderField('created_by'); ?>
                    <?php echo $this->form->renderField('created'); ?>
                    <?php echo $this->form->renderField('modified_by'); ?>
                    <?php echo $this->form->renderField('modified'); ?>
                </fieldset>
            </div>
        </div>
    </div>
    
    <input type="hidden" name="task" value="" />
    <input type="hidden" name="return" value="<?php echo $input->getCmd('return'); ?>" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>