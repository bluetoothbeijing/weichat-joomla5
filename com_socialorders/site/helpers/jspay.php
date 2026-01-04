<?php
defined('_JEXEC') or die;

/**
 * JS支付助手类
 * 处理前端支付相关功能
 */
class JsPayHelper
{
    /**
     * 生成支付表单
     */
    public static function generatePaymentForm($paymentData, $options = [])
    {
        $defaultOptions = [
            'formId' => 'payment-form',
            'submitText' => '立即支付',
            'autoSubmit' => true,
            'showQRCode' => false
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        $form = '<form id="' . $options['formId'] . '" method="post">';
        
        foreach ($paymentData as $key => $value) {
            $form .= '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
        }
        
        if (!$options['autoSubmit']) {
            $form .= '<button type="submit" class="btn btn-primary">' . $options['submitText'] . '</button>';
        }
        
        $form .= '</form>';
        
        // 自动提交脚本
        if ($options['autoSubmit']) {
            $form .= '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    document.getElementById("' . $options['formId'] . '").submit();
                });
            </script>';
        }
        
        return $form;
    }
    
    /**
     * 生成JSAPI支付配置
     */
    public static function getJsApiConfig($appId, $timestamp, $nonceStr, $signature)
    {
        return [
            'appId' => $appId,
            'timestamp' => $timestamp,
            'nonceStr' => $nonceStr,
            'signature' => $signature,
            'jsApiList' => [
                'checkJsApi',
                'onMenuShareTimeline',
                'onMenuShareAppMessage',
                'chooseWXPay',
                'scanQRCode'
            ]
        ];
    }
    
    /**
     * 调用微信JSAPI支付
     */
    public static function invokeWeChatPayment($paymentParams)
    {
        $jsCode = "
        WeixinJSBridge.invoke(
            'getBrandWCPayRequest',
            " . json_encode($paymentParams) . ",
            function(res) {
                if(res.err_msg == 'get_brand_wcpay_request:ok') {
                    // 支付成功
                    if(typeof onPaymentSuccess === 'function') {
                        onPaymentSuccess(res);
                    } else {
                        alert('支付成功！');
                        window.location.href = '" . JUri::base() . "index.php?option=com_socialorders&view=order&id=' + " . ($paymentParams['order_id'] ?? 0) . ";
                    }
                } else {
                    // 支付失败
                    if(typeof onPaymentError === 'function') {
                        onPaymentError(res);
                    } else {
                        alert('支付失败：' + res.err_msg);
                    }
                }
            }
        );";
        
        return $jsCode;
    }
    
    /**
     * 检查微信浏览器环境
     */
    public static function isWeChatBrowser()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return strpos($userAgent, 'MicroMessenger') !== false;
    }
    
    /**
     * 生成支付状态轮询JS
     */
    public static function generatePollingScript($orderId, $options = [])
    {
        $defaultOptions = [
            'interval' => 3000, // 3秒
            'maxAttempts' => 20, // 最多尝试20次
            'successUrl' => JUri::base() . 'index.php?option=com_socialorders&view=order&id=' . $orderId,
            'onSuccess' => 'function() { window.location.href = "' . JUri::base() . 'index.php?option=com_socialorders&view=order&id=' . $orderId . '"; }',
            'onError' => 'function(error) { console.error("支付状态检查失败：", error); }'
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        $script = "
        <script>
        (function() {
            var attempts = 0;
            var maxAttempts = " . $options['maxAttempts'] . ";
            var interval = " . $options['interval'] . ";
            var orderId = " . (int)$orderId . ";
            
            function checkPaymentStatus() {
                if (attempts >= maxAttempts) {
                    console.log('已达到最大检查次数');
                    return;
                }
                
                attempts++;
                
                // AJAX请求检查支付状态
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '" . JUri::base() . "index.php?option=com_ajax&plugin=socialpayment&task=queryPayment&format=json');
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                if (response.data.status === 'paid') {
                                    // 支付成功
                                    " . $options['onSuccess'] . "
                                } else {
                                    // 未支付，继续检查
                                    setTimeout(checkPaymentStatus, interval);
                                }
                            } else {
                                " . $options['onError'] . "
                            }
                        } catch (e) {
                            " . $options['onError'] . "
                        }
                    } else {
                        " . $options['onError'] . "
                    }
                };
                xhr.onerror = function() {
                    " . $options['onError'] . "
                };
                
                var formData = new FormData();
                formData.append('order_id', orderId);
                formData.append('token', '" . JSession::getFormToken() . "');
                
                xhr.send(formData);
            }
            
            // 开始检查
            setTimeout(checkPaymentStatus, interval);
        })();
        </script>";
        
        return $script;
    }
    
    /**
     * 显示二维码支付
     */
    public static function showQrCodePayment($codeUrl, $options = [])
    {
        $defaultOptions = [
            'containerId' => 'qrcode-container',
            'width' => 200,
            'height' => 200,
            'title' => '微信扫码支付',
            'instructions' => '请使用微信扫描二维码完成支付'
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        $html = '
        <div id="' . $options['containerId'] . '">
            <div class="qrcode-title">' . $options['title'] . '</div>
            <div class="qrcode-image">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=' . $options['width'] . 'x' . $options['height'] . '&data=' . urlencode($codeUrl) . '" 
                     width="' . $options['width'] . '" height="' . $options['height'] . '" 
                     alt="微信支付二维码">
            </div>
            <div class="qrcode-instructions">' . $options['instructions'] . '</div>
        </div>';
        
        return $html;
    }
    
    /**
     * 生成支付按钮
     */
    public static function generatePaymentButton($orderId, $amount, $paymentMethod = 'wechat', $options = [])
    {
        $defaultOptions = [
            'buttonId' => 'pay-button-' . $orderId,
            'buttonClass' => 'btn btn-primary btn-payment',
            'buttonText' => '立即支付 ¥' . number_format($amount, 2),
            'onClick' => 'handlePayment(' . $orderId . ', \'' . $paymentMethod . '\')'
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        $button = '<button id="' . $options['buttonId'] . '" 
                         class="' . $options['buttonClass'] . '" 
                         onclick="' . $options['onClick'] . '">
                    ' . $options['buttonText'] . '
                  </button>';
        
        return $button;
    }
}