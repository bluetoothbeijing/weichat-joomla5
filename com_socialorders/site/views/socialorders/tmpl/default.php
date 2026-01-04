<?php
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');

$user = Factory::getUser();
$app = Factory::getApplication();
?>

<div class="socialorders-container">
    <div class="page-header">
        <h1><?php echo Text::_('COM_SOCIALORDERS_MY_ORDERS'); ?></h1>
    </div>
    
    <?php if ($user->guest): ?>
        <div class="alert alert-warning">
            <?php echo Text::_('COM_SOCIALORDERS_LOGIN_REQUIRED'); ?>
            <a href="<?php echo Route::_('index.php?option=com_users&view=login'); ?>" class="btn btn-primary btn-sm">
                <?php echo Text::_('JLOGIN'); ?>
            </a>
        </div>
    <?php else: ?>
    
    <!-- 订单统计 -->
    <?php if ($this->statistics): ?>
    <div class="order-statistics mb-4">
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card card">
                    <div class="card-body text-center">
                        <h3 class="text-primary"><?php echo $this->statistics->total_orders; ?></h3>
                        <p class="text-muted"><?php echo Text::_('COM_SOCIALORDERS_TOTAL_ORDERS'); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card card">
                    <div class="card-body text-center">
                        <h3 class="text-success">¥<?php echo number_format($this->statistics->total_amount, 2); ?></h3>
                        <p class="text-muted"><?php echo Text::_('COM_SOCIALORDERS_TOTAL_AMOUNT'); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card card">
                    <div class="card-body text-center">
                        <h3 class="text-info">¥<?php echo number_format($this->statistics->paid_amount, 2); ?></h3>
                        <p class="text-muted"><?php echo Text::_('COM_SOCIALORDERS_PAID_AMOUNT'); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card card">
                    <div class="card-body text-center">
                        <h3 class="text-warning"><?php echo $this->statistics->completed_orders; ?></h3>
                        <p class="text-muted"><?php echo Text::_('COM_SOCIALORDERS_COMPLETED_ORDERS'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- 搜索和过滤 -->
    <div class="order-filters card mb-4">
        <div class="card-body">
            <form action="<?php echo Route::_('index.php?option=com_socialorders&view=socialorders'); ?>" method="get" id="filterForm">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="filter_search"><?php echo Text::_('JSEARCH_FILTER'); ?></label>
                            <input type="text" name="search" id="filter_search" 
                                   value="<?php echo htmlspecialchars($this->state->get('filter.search')); ?>" 
                                   class="form-control" placeholder="<?php echo Text::_('COM_SOCIALORDERS_SEARCH_PLACEHOLDER'); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="filter_status"><?php echo Text::_('COM_SOCIALORDERS_STATUS'); ?></label>
                            <select name="status" id="filter_status" class="form-control">
                                <?php foreach ($this->getFilterStatus() as $key => $value): ?>
                                <option value="<?php echo $key; ?>" <?php echo $this->state->get('filter.status') == $key ? 'selected' : ''; ?>>
                                    <?php echo $value; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="filter_payment_status"><?php echo Text::_('COM_SOCIALORDERS_PAYMENT_STATUS'); ?></label>
                            <select name="payment_status" id="filter_payment_status" class="form-control">
                                <?php foreach ($this->getFilterPaymentStatus() as $key => $value): ?>
                                <option value="<?php echo $key; ?>" <?php echo $this->state->get('filter.payment_status') == $key ? 'selected' : ''; ?>>
                                    <?php echo $value; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="filter_date_from"><?php echo Text::_('COM_SOCIALORDERS_DATE_FROM'); ?></label>
                            <input type="date" name="date_from" id="filter_date_from" 
                                   value="<?php echo $this->state->get('filter.date_from'); ?>" 
                                   class="form-control">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="filter_date_to"><?php echo Text::_('COM_SOCIALORDERS_DATE_TO'); ?></label>
                            <input type="date" name="date_to" id="filter_date_to" 
                                   value="<?php echo $this->state->get('filter.date_to'); ?>" 
                                   class="form-control">
                        </div>
                    </div>
                    <div class="col-md-1">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="option" value="com_socialorders">
                <input type="hidden" name="view" value="socialorders">
                <input type="hidden" name="task" value="">
            </form>
        </div>
    </div>
    
    <!-- 订单列表 -->
    <?php if (empty($this->items)): ?>
        <div class="alert alert-info">
            <?php echo Text::_('COM_SOCIALORDERS_NO_ORDERS'); ?>
            <a href="<?php echo Route::_('index.php?option=com_socialorders&view=order&layout=create'); ?>" class="btn btn-success btn-sm ml-2">
                <?php echo Text::_('COM_SOCIALORDERS_CREATE_NEW_ORDER'); ?>
            </a>
        </div>
    <?php else: ?>
    
    <div class="orders-list">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th width="5%"><?php echo Text::_('COM_SOCIALORDERS_ORDER_NO'); ?></th>
                        <th width="20%"><?php echo Text::_('COM_SOCIALORDERS_ORDER_TITLE'); ?></th>
                        <th width="10%"><?php echo Text::_('COM_SOCIALORDERS_AMOUNT'); ?></th>
                        <th width="10%"><?php echo Text::_('COM_SOCIALORDERS_STATUS'); ?></th>
                        <th width="10%"><?php echo Text::_('COM_SOCIALORDERS_PAYMENT_STATUS'); ?></th>
                        <th width="15%"><?php echo Text::_('COM_SOCIALORDERS_CREATED_DATE'); ?></th>
                        <th width="15%"><?php echo Text::_('COM_SOCIALORDERS_ACTIONS'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->items as $i => $item): ?>
                    <tr>
                        <td><?php echo $item->order_no; ?></td>
                        <td>
                            <a href="<?php echo Route::_('index.php?option=com_socialorders&view=order&id=' . $item->id); ?>">
                                <?php echo htmlspecialchars($item->title, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                            <?php if ($item->item_count > 0): ?>
                            <small class="text-muted d-block">
                                <?php echo Text::sprintf('COM_SOCIALORDERS_ITEMS_COUNT', $item->item_count); ?>
                            </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong class="text-primary"><?php echo $item->amount_formatted; ?></strong>
                            <?php if ($item->real_amount != $item->amount): ?>
                            <small class="text-muted d-block">
                                <?php echo Text::_('COM_SOCIALORDERS_REAL_AMOUNT'); ?>: <?php echo $item->real_amount_formatted; ?>
                            </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $item->status; ?>">
                                <?php echo $item->status_text; ?>
                            </span>
                        </td>
                        <td>
                            <span class="payment-status-badge status-<?php echo $item->payment_status; ?>">
                                <?php echo $item->payment_status_text; ?>
                            </span>
                        </td>
                        <td><?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC2')); ?></td>
                        <td>
                            <div class="btn-group">
                                <a href="<?php echo Route::_('index.php?option=com_socialorders&view=order&id=' . $item->id); ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <?php if ($item->canCancel): ?>
                                <a href="<?php echo Route::_('index.php?option=com_socialorders&task=order.cancel&id=' . $item->id . '&' . JSession::getFormToken() . '=1'); ?>" 
                                   class="btn btn-sm btn-outline-warning" 
                                   onclick="return confirm('<?php echo Text::_('COM_SOCIALORDERS_CONFIRM_CANCEL'); ?>')">
                                    <i class="fas fa-times"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($item->canPay && $item->payment_status == 'unpaid'): ?>
                                <a href="<?php echo Route::_('index.php?option=com_socialorders&view=order&layout=pay&id=' . $item->id); ?>" 
                                   class="btn btn-sm btn-success">
                                    <i class="fas fa-credit-card"></i> <?php echo Text::_('COM_SOCIALORDERS_PAY_NOW'); ?>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- 分页 -->
        <?php if ($this->pagination->pagesTotal > 1): ?>
        <div class="pagination-wrapper">
            <?php echo $this->pagination->getPagesLinks(); ?>
            <div class="pagination-counter">
                <?php echo $this->pagination->getPagesCounter(); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <?php endif; ?>
    
    <!-- 创建新订单按钮 -->
    <div class="mt-4 text-center">
        <a href="<?php echo Route::_('index.php?option=com_socialorders&view=order&layout=create'); ?>" class="btn btn-lg btn-success">
            <i class="fas fa-plus"></i> <?php echo Text::_('COM_SOCIALORDERS_CREATE_NEW_ORDER'); ?>
        </a>
    </div>
    
    <?php endif; ?>
</div>

<style>
.socialorders-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.stat-card {
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stat-card h3 {
    margin: 0;
    font-weight: bold;
}

.order-filters .form-group {
    margin-bottom: 0;
}

.status-badge, .payment-status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-pending { background-color: #fff3cd; color: #856404; }
.status-processing { background-color: #d1ecf1; color: #0c5460; }
.status-completed { background-color: #d4edda; color: #155724; }
.status-cancelled { background-color: #f8d7da; color: #721c24; }
.status-refunded { background-color: #e2e3e5; color: #383d41; }

.status-unpaid { background-color: #f8d7da; color: #721c24; }
.status-paid { background-color: #d4edda; color: #155724; }
.status-failed { background-color: #f8d7da; color: #721c24; }

.pagination-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
}

@media (max-width: 768px) {
    .order-filters .col-md-3,
    .order-filters .col-md-2 {
        margin-bottom: 10px;
    }
    
    .orders-list table {
        font-size: 14px;
    }
    
    .btn-group .btn {
        padding: 3px 6px;
        font-size: 12px;
    }
}
</style>