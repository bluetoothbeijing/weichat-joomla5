<?php
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.multiselect');
HTMLHelper::_('formbehavior.chosen', 'select');

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$saveOrder = $listOrder == 'a.ordering';
?>

<form action="<?php echo Route::_('index.php?option=com_socialorders&view=orders'); ?>" method="post" name="adminForm" id="adminForm">
    <?php if (!empty($this->sidebar)): ?>
        <div id="j-sidebar-container" class="span2">
            <?php echo $this->sidebar; ?>
        </div>
        <div id="j-main-container" class="span10">
    <?php else: ?>
        <div id="j-main-container">
    <?php endif; ?>
    
    <?php echo JLayoutHelper::render('joomla.searchtools.default', array('view' => $this)); ?>
    
    <?php if (empty($this->items)) : ?>
        <div class="alert alert-no-items">
            <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
        </div>
    <?php else : ?>
        <table class="table table-striped" id="orderList">
            <thead>
                <tr>
                    <th width="1%" class="nowrap center hidden-phone">
                        <?php echo HTMLHelper::_('grid.sort', '<i class="icon-menu-2"></i>', 'a.ordering', $listDirn, $listOrder, null, 'asc', 'JGRID_HEADING_ORDERING'); ?>
                    </th>
                    <th width="1%" class="hidden-phone">
                        <?php echo HTMLHelper::_('grid.checkall'); ?>
                    </th>
                    <th width="1%" style="min-width:55px" class="nowrap center">
                        <?php echo HTMLHelper::_('grid.sort', 'JSTATUS', 'a.state', $listDirn, $listOrder); ?>
                    </th>
                    <th class="title">
                        <?php echo HTMLHelper::_('grid.sort', 'COM_SOCIALORDERS_ORDER_NO', 'a.order_no', $listDirn, $listOrder); ?>
                    </th>
                    <th width="15%">
                        <?php echo HTMLHelper::_('grid.sort', 'COM_SOCIALORDERS_CUSTOMER', 'customer_name', $listDirn, $listOrder); ?>
                    </th>
                    <th width="10%" class="nowrap hidden-phone">
                        <?php echo HTMLHelper::_('grid.sort', 'COM_SOCIALORDERS_AMOUNT', 'a.amount', $listDirn, $listOrder); ?>
                    </th>
                    <th width="10%" class="nowrap hidden-phone">
                        <?php echo HTMLHelper::_('grid.sort', 'COM_SOCIALORDERS_STATUS', 'a.status', $listDirn, $listOrder); ?>
                    </th>
                    <th width="10%" class="nowrap hidden-phone">
                        <?php echo HTMLHelper::_('grid.sort', 'COM_SOCIALORDERS_PAYMENT_STATUS', 'a.payment_status', $listDirn, $listOrder); ?>
                    </th>
                    <th width="10%" class="nowrap hidden-phone">
                        <?php echo HTMLHelper::_('grid.sort', 'JDATE', 'a.created', $listDirn, $listOrder); ?>
                    </th>
                    <th width="5%" class="nowrap hidden-phone">
                        <?php echo HTMLHelper::_('grid.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($this->items as $i => $item) : ?>
                    <?php $ordering = ($listOrder == 'a.ordering'); ?>
                    <?php $canCreate  = $user->authorise('core.create', 'com_socialorders'); ?>
                    <?php $canEdit    = $user->authorise('core.edit', 'com_socialorders.order.' . $item->id); ?>
                    <?php $canCheckin = $user->authorise('core.manage', 'com_checkin') || $item->checked_out == $userId || $item->checked_out == 0; ?>
                    <?php $canChange  = $user->authorise('core.edit.state', 'com_socialorders.order.' . $item->id) && $canCheckin; ?>
                    
                    <tr class="row<?php echo $i % 2; ?>" sortable-group-id="<?php echo $item->catid; ?>">
                        <td class="order nowrap center hidden-phone">
                            <?php if ($canChange && $saveOrder) : ?>
                                <?php $iconClass = ''; ?>
                                <span class="sortable-handler <?php echo $iconClass; ?>">
                                    <i class="icon-menu"></i>
                                </span>
                                <input type="text" style="display:none" name="order[]" size="5" value="<?php echo $item->ordering; ?>" class="width-20 text-area-order" />
                            <?php else : ?>
                                <span class="sortable-handler inactive">
                                    <i class="icon-menu"></i>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="center hidden-phone">
                            <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                        </td>
                        <td class="center">
                            <div class="btn-group">
                                <?php echo HTMLHelper::_('jgrid.published', $item->state, $i, 'orders.', $canChange, 'cb', $item->publish_up, $item->publish_down); ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($canEdit) : ?>
                                <a href="<?php echo Route::_('index.php?option=com_socialorders&task=order.edit&id=' . (int) $item->id); ?>" title="<?php echo Text::_('JACTION_EDIT'); ?>">
                                    <?php echo $this->escape($item->order_no); ?>
                                </a>
                            <?php else : ?>
                                <?php echo $this->escape($item->order_no); ?>
                            <?php endif; ?>
                            <div class="small">
                                <?php echo $this->escape($item->title); ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($item->customer_name) : ?>
                                <?php echo $this->escape($item->customer_name); ?>
                                <div class="small">
                                    <?php echo $this->escape($item->customer_email); ?>
                                </div>
                            <?php else : ?>
                                <span class="label label-warning"><?php echo Text::_('COM_SOCIALORDERS_GUEST'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="nowrap hidden-phone">
                            <span class="badge badge-info"><?php echo $item->amount_formatted; ?></span>
                        </td>
                        <td class="nowrap hidden-phone">
                            <span class="label label-<?php echo $item->status == 'completed' ? 'success' : ($item->status == 'pending' ? 'warning' : 'danger'); ?>">
                                <?php echo $item->status_text; ?>
                            </span>
                        </td>
                        <td class="nowrap hidden-phone">
                            <span class="label label-<?php echo $item->payment_status == 'paid' ? 'success' : ($item->payment_status == 'unpaid' ? 'warning' : 'danger'); ?>">
                                <?php echo $item->payment_status_text; ?>
                            </span>
                        </td>
                        <td class="nowrap hidden-phone">
                            <?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC4')); ?>
                        </td>
                        <td class="center hidden-phone">
                            <?php echo (int) $item->id; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="10">
                        <?php echo $this->pagination->getListFooter(); ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>
    
    <input type="hidden" name="task" value="" />
    <input type="hidden" name="boxchecked" value="0" />
    <input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>" />
    <input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>" />
    <?php echo HTMLHelper::_('form.token'); ?>
    
    </div>
</form>