<?php
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');
HTMLHelper::_('formbehavior.chosen', 'select');

$app = Factory::getApplication();
$input = $app->input;
$id = $input->getInt('id', 0);
$isNew = ($id == 0);

// 如果是编辑模式，尝试从数据库获取数据
$item = null;
if (!$isNew) {
    try {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__social_orders'))
            ->where($db->quoteName('id') . ' = ' . $id);
        $db->setQuery($query);
        $item = $db->loadObject();
    } catch (Exception $e) {
        $item = null;
    }
}

// 设置默认值
$order_no = $item ? $item->order_no : ('SO' . date('YmdHis') . rand(1000, 9999));
$title = $item ? $item->title : '';
$amount = $item ? $item->amount : '0.00';
$status = $item ? $item->status : 'pending';
$state = $item ? $item->state : 1;
?>

<form action="<?php echo Route::_('index.php?option=com_socialorders&task=order.save'); ?>"
      method="post" name="adminForm" id="adminForm" class="form-validate form-horizontal">
    
    <div class="row-fluid">
        <div class="span12">
            <fieldset class="adminform">
                <legend><?php echo Text::_('COM_SOCIALORDERS_ORDER_DETAILS'); ?></legend>
                
                <div class="control-group">
                    <div class="control-label">
                        <label for="order_no" class="required">
                            <?php echo Text::_('COM_SOCIALORDERS_FIELD_ORDER_NO_LABEL'); ?><span class="star">&nbsp;*</span>
                        </label>
                    </div>
                    <div class="controls">
                        <input type="text" name="jform[order_no]" id="order_no" 
                               value="<?php echo htmlspecialchars($order_no, ENT_QUOTES, 'UTF-8'); ?>" 
                               class="inputbox required" size="20" required="required" />
                    </div>
                </div>
                
                <div class="control-group">
                    <div class="control-label">
                        <label for="title" class="required">
                            <?php echo Text::_('COM_SOCIALORDERS_FIELD_TITLE_LABEL'); ?><span class="star">&nbsp;*</span>
                        </label>
                    </div>
                    <div class="controls">
                        <input type="text" name="jform[title]" id="title" 
                               value="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>" 
                               class="inputbox required" size="60" required="required" />
                    </div>
                </div>
                
                <div class="control-group">
                    <div class="control-label">
                        <label for="amount" class="required">
                            <?php echo Text::_('COM_SOCIALORDERS_FIELD_AMOUNT_LABEL'); ?><span class="star">&nbsp;*</span>
                        </label>
                    </div>
                    <div class="controls">
                        <input type="text" name="jform[amount]" id="amount" 
                               value="<?php echo htmlspecialchars($amount, ENT_QUOTES, 'UTF-8'); ?>" 
                               class="inputbox required" size="10" required="required" />
                    </div>
                </div>
                
                <div class="control-group">
                    <div class="control-label">
                        <label for="status">
                            <?php echo Text::_('COM_SOCIALORDERS_FIELD_STATUS_LABEL'); ?>
                        </label>
                    </div>
                    <div class="controls">
                        <select name="jform[status]" id="status" class="inputbox">
                            <option value="pending" <?php echo $status == 'pending' ? 'selected="selected"' : ''; ?>>
                                <?php echo Text::_('COM_SOCIALORDERS_STATUS_PENDING'); ?>
                            </option>
                            <option value="processing" <?php echo $status == 'processing' ? 'selected="selected"' : ''; ?>>
                                <?php echo Text::_('COM_SOCIALORDERS_STATUS_PROCESSING'); ?>
                            </option>
                            <option value="completed" <?php echo $status == 'completed' ? 'selected="selected"' : ''; ?>>
                                <?php echo Text::_('COM_SOCIALORDERS_STATUS_COMPLETED'); ?>
                            </option>
                            <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected="selected"' : ''; ?>>
                                <?php echo Text::_('COM_SOCIALORDERS_STATUS_CANCELLED'); ?>
                            </option>
                        </select>
                    </div>
                </div>
                
                <div class="control-group">
                    <div class="control-label">
                        <label for="state">
                            <?php echo Text::_('JSTATUS'); ?>
                        </label>
                    </div>
                    <div class="controls">
                        <select name="jform[state]" id="state" class="inputbox">
                            <option value="1" <?php echo $state == 1 ? 'selected="selected"' : ''; ?>>
                                <?php echo Text::_('JPUBLISHED'); ?>
                            </option>
                            <option value="0" <?php echo $state == 0 ? 'selected="selected"' : ''; ?>>
                                <?php echo Text::_('JUNPUBLISHED'); ?>
                            </option>
                        </select>
                    </div>
                </div>
            </fieldset>
        </div>
    </div>
    
    <input type="hidden" name="jform[id]" value="<?php echo $id; ?>" />
    <input type="hidden" name="task" value="" />
    <input type="hidden" name="option" value="com_socialorders" />
    <input type="hidden" name="view" value="order" />
    <input type="hidden" name="layout" value="edit" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<script type="text/javascript">
Joomla.submitbutton = function(task) {
    if (task == 'order.cancel' || document.formvalidator.isValid(document.getElementById('adminForm'))) {
        // 如果是取消，直接重定向
        if (task == 'order.cancel') {
            window.location.href = 'index.php?option=com_socialorders&view=orders';
            return;
        }
        
        // 否则提交表单
        Joomla.submitform(task, document.getElementById('adminForm'));
    }
}
</script>