<?php
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
?>
<div class="order-detail">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="page-header">
                    <h1>订单详情</h1>
                    <p class="text-muted">订单号：<?php echo $this->item->order_no; ?></p>
                </div>
                
                <!-- 订单状态 -->
                <div class="order-status mb-4">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h5 class="card-title">订单状态</h5>
                                    <span class="badge badge-<?php echo $this->item->status == 'completed' ? 'success' : ($this->item->status == 'pending' ? 'warning' : 'danger'); ?>">
                                        <?php echo $this->item->status_text; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h5 class="card-title">支付状态</h5>
                                    <span class="badge badge-<?php echo $this->item->payment_status == 'paid' ? 'success' : ($this->item->payment_status == 'unpaid' ? 'warning' : 'danger'); ?>">
                                        <?php echo $this->item->payment_status_text; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h5 class="card-title">订单金额</h5>
                                    <h4 class="text-success"><?php echo $this->item->amount_formatted; ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h5 class="card-title">创建时间</h5>
                                    <p><?php echo date('Y-m-d H:i:s', strtotime($this->item->created)); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 订单信息 -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">订单信息</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>订单标题：</strong> <?php echo htmlspecialchars($this->item->title); ?></p>
                                <p><strong>订单描述：</strong> <?php echo htmlspecialchars($this->item->description); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>实付金额：</strong> <?php echo $this->item->real_amount_formatted; ?></p>
                                <p><strong>支付方式：</strong> <?php echo $this->item->payment_method ?: '未选择'; ?></p>
                                <?php if ($this->item->payment_time): ?>
                                <p><strong>支付时间：</strong> <?php echo date('Y-m-d H:i:s', strtotime($this->item->payment_time)); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 操作按钮 -->
                <div class="order-actions mb-4">
                    <?php if ($this->item->payment_status == 'unpaid' && $this->item->status == 'pending'): ?>
                    <a href="<?php echo Route::_('index.php?option=com_socialorders&task=order.pay&id=' . $this->item->id . '&' . JSession::getFormToken() . '=1'); ?>" 
                       class="btn btn-success btn-lg">
                        <i class="fas fa-credit-card"></i> 立即支付
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($this->item->status == 'pending'): ?>
                    <a href="<?php echo Route::_('index.php?option=com_socialorders&task=order.cancel&id=' . $this->item->id . '&' . JSession::getFormToken() . '=1'); ?>" 
                       class="btn btn-warning btn-lg" 
                       onclick="return confirm('确定要取消订单吗？')">
                        <i class="fas fa-times"></i> 取消订单
                    </a>
                    <?php endif; ?>
                    
                    <a href="<?php echo Route::_('index.php?option=com_socialorders'); ?>" class="btn btn-secondary btn-lg">
                        <i class="fas fa-arrow-left"></i> 返回列表
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.order-detail {
    padding: 20px 0;
}

.order-detail .card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.order-detail .card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #eee;
}

.order-actions .btn {
    margin-right: 10px;
    margin-bottom: 10px;
}

.badge-success { background-color: #28a745; }
.badge-warning { background-color: #ffc107; color: #212529; }
.badge-danger { background-color: #dc3545; }
</style>