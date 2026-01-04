<?php
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
?>
<div class="wechat-pay">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card mt-5">
                    <div class="card-header text-center bg-success text-white">
                        <h3 class="mb-0"><i class="fab fa-weixin"></i> 微信支付</h3>
                    </div>
                    
                    <div class="card-body">
                        <!-- 订单信息 -->
                        <div class="order-info mb-4 p-3 bg-light rounded">
                            <h5>订单信息</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>订单号：</strong> <?php echo $this->order->order_no; ?></p>
                                    <p><strong>订单标题：</strong> <?php echo htmlspecialchars($this->order->title); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>应付金额：</strong> 
                                        <span class="text-danger font-weight-bold h4">
                                            ¥<?php echo number_format($this->order->real_amount ?: $this->order->amount, 2); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 支付方式选择 -->
                        <div class="payment-methods mb-4">
                            <h5 class="mb-3">选择支付方式</h5>
                            <div class="row">
                                <?php if (strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'MicroMessenger') !== false): ?>
                                <!-- 微信内支付 -->
                                <div class="col-md-6 mb-3">
                                    <div class="card method-card active" data-method="jsapi">
                                        <div class="card-body text-center">
                                            <i class="fab fa-weixin fa-3x text-success mb-3"></i>
                                            <h5>微信内支付</h5>
                                            <p class="text-muted">在微信内完成支付</p>
                                        </div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <!-- 扫码支付 -->
                                <div class="col-md-6 mb-3">
                                    <div class="card method-card active" data-method="native">
                                        <div class="card-body text-center">
                                            <i class="fas fa-qrcode fa-3x text-primary mb-3"></i>
                                            <h5>扫码支付</h5>
                                            <p class="text-muted">使用微信扫一扫完成支付</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- H5支付 -->
                                <div class="col-md-6 mb-3">
                                    <div class="card method-card" data-method="h5">
                                        <div class="card-body text-center">
                                            <i class="fas fa-mobile-alt fa-3x text-info mb-3"></i>
                                            <h5>手机支付</h5>
                                            <p class="text-muted">在手机浏览器中支付</p>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- 支付按钮 -->
                        <div class="text-center mt-4">
                            <button id="pay-button" class="btn btn-success btn-lg" style="padding: 12px 40px;">
                                <i class="fas fa-credit-card"></i> 立即支付
                            </button>
                            
                            <a href="<?php echo Route::_('index.php?option=com_socialorders&view=order&id=' . $this->order->id); ?>" 
                               class="btn btn-outline-secondary btn-lg ml-3">
                                返回订单
                            </a>
                        </div>
                    </div>
                    
                    <div class="card-footer text-muted text-center">
                        <small>支付过程中请不要关闭页面</small>
                    </div>
                </div>
                
                <!-- 支付提示 -->
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i> 
                    支付提示：请在15分钟内完成支付，超时订单将自动取消
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var orderId = <?php echo $this->order->id; ?>;
    var isWeChat = <?php echo strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'MicroMessenger') !== false ? 'true' : 'false'; ?>;
    
    // 支付方式选择
    $('.method-card').click(function() {
        $('.method-card').removeClass('active border-success');
        $(this).addClass('active border-success');
    });
    
    // 支付按钮点击
    $('#pay-button').click(function() {
        var method = $('.method-card.active').data('method') || (isWeChat ? 'jsapi' : 'native');
        
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> 正在创建支付...');
        
        // AJAX请求创建支付
        $.ajax({
            url: '<?php echo Route::_("index.php?option=com_socialorders&task=payment.create&format=json"); ?>',
            type: 'POST',
            data: {
                order_id: orderId,
                payment_method: 'wechat',
                token: '<?php echo JSession::getFormToken(); ?>'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (response.data.type === 'jsapi') {
                        // JSAPI支付
                        invokeWeChatPay(response.data.jsapi_params);
                    } else if (response.data.type === 'native') {
                        // 扫码支付，跳转到二维码页面
                        window.location.href = '<?php echo Route::_("index.php?option=com_socialorders&view=wechat&layout=qrcode&id=' + orderId); ?>';
                    } else if (response.data.type === 'h5') {
                        // H5支付，跳转到支付页面
                        window.location.href = response.data.mweb_url;
                    }
                } else {
                    alert('支付创建失败：' + response.message);
                    $('#pay-button').prop('disabled', false).html('<i class="fas fa-credit-card"></i> 立即支付');
                }
            },
            error: function() {
                alert('网络错误，请重试');
                $('#pay-button').prop('disabled', false).html('<i class="fas fa-credit-card"></i> 立即支付');
            }
        });
    });
    
    // 调用微信JSAPI支付
    function invokeWeChatPay(paymentParams) {
        if (typeof WeixinJSBridge == "undefined") {
            if (document.addEventListener) {
                document.addEventListener('WeixinJSBridgeReady', function() {
                    onBridgeReady(paymentParams);
                }, false);
            } else if (document.attachEvent) {
                document.attachEvent('WeixinJSBridgeReady', function() {
                    onBridgeReady(paymentParams);
                });
                document.attachEvent('onWeixinJSBridgeReady', function() {
                    onBridgeReady(paymentParams);
                });
            }
        } else {
            onBridgeReady(paymentParams);
        }
        
        function onBridgeReady(params) {
            WeixinJSBridge.invoke(
                'getBrandWCPayRequest',
                params,
                function(res) {
                    if (res.err_msg == "get_brand_wcpay_request:ok") {
                        // 支付成功
                        window.location.href = '<?php echo Route::_("index.php?option=com_socialorders&view=order&id=' + orderId + '&payment=success"); ?>';
                    } else {
                        // 支付失败
                        alert('支付失败：' + res.err_msg);
                        $('#pay-button').prop('disabled', false).html('<i class="fas fa-credit-card"></i> 立即支付');
                    }
                }
            );
        }
    }
});
</script>

<style>
.wechat-pay {
    min-height: 80vh;
}

.card-header.bg-success {
    background: linear-gradient(135deg, #09bb07 0%, #08a806 100%);
}

.method-card {
    cursor: pointer;
    border: 2px solid transparent;
    transition: all 0.3s;
}

.method-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.method-card.active {
    border-color: #28a745 !important;
}

.order-info {
    border-left: 4px solid #28a745;
}

#pay-button {
    min-width: 200px;
    background: linear-gradient(135deg, #09bb07 0%, #08a806 100%);
    border: none;
    transition: all 0.3s;
}

#pay-button:hover {
    transform: scale(1.05);
    box-shadow: 0 5px 15px rgba(9, 187, 7, 0.3);
}
</style>