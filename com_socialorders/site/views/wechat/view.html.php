<?php
defined('_JEXEC') or die;

class SocialordersViewWechat extends JViewLegacy
{
    public function display($tpl = null)
    {
        $layout = $this->getLayout();
        
        switch ($layout) {
            case 'auth':
                $this->prepareAuthView();
                break;
            case 'pay':
                $this->preparePayView();
                break;
            case 'callback':
                $this->prepareCallbackView();
                break;
            case 'jsapi':
                $this->prepareJsapiView();
                break;
            case 'qrcode':
                $this->prepareQrcodeView();
                break;
            default:
                $this->prepareDefaultView();
        }
        
        parent::display($tpl);
    }
    
    protected function prepareAuthView()
    {
        $app = JFactory::getApplication();
        $code = $app->input->get('code', '', 'string');
        
        if ($code) {
            $this->code = $code;
        } else {
            $app->enqueueMessage('授权失败：未收到授权码', 'error');
            $app->redirect(JRoute::_('index.php?option=com_socialorders', false));
        }
    }
    
    protected function preparePayView()
    {
        $app = JFactory::getApplication();
        $orderId = $app->input->getInt('order_id');
        
        if (!$orderId) {
            $app->enqueueMessage('订单ID不能为空', 'error');
            $app->redirect(JRoute::_('index.php?option=com_socialorders', false));
            return;
        }
        
        // 获取订单信息
        $model = $this->getModel('Order', 'SocialordersModel');
        $this->order = $model->getItem($orderId);
        
        if (!$this->order) {
            $app->enqueueMessage('订单不存在', 'error');
            $app->redirect(JRoute::_('index.php?option=com_socialorders', false));
            return;
        }
        
        // 检查用户权限
        $user = JFactory::getUser();
        if ($this->order->user_id != $user->id && !$user->authorise('core.edit', 'com_socialorders')) {
            $app->enqueueMessage('无权支付此订单', 'error');
            $app->redirect(JRoute::_('index.php?option=com_socialorders', false));
            return;
        }
        
        // 检查支付状态
        if ($this->order->payment_status !== 'unpaid') {
            $app->enqueueMessage('订单已支付或已关闭', 'error');
            $app->redirect(JRoute::_('index.php?option=com_socialorders&view=order&id=' . $orderId, false));
            return;
        }
        
        // 获取支付配置
        $this->paymentConfig = $this->getPaymentConfig();
    }
    
    protected function prepareJsapiView()
    {
        $app = JFactory::getApplication();
        $orderId = $app->input->getInt('id');
        
        if (!$orderId) {
            $app->enqueueMessage('订单ID不能为空', 'error');
            $app->redirect(JRoute::_('index.php?option=com_socialorders', false));
            return;
        }
        
        // 获取支付参数
        $paymentData = $app->getUserState('com_socialorders.payment_data');
        
        if (!$paymentData) {
            $app->enqueueMessage('支付数据不存在或已过期', 'error');
            $app->redirect(JRoute::_('index.php?option=com_socialorders&view=order&id=' . $orderId, false));
            return;
        }
        
        $this->paymentData = $paymentData;
        $this->orderId = $orderId;
    }
    
    protected function prepareQrcodeView()
    {
        $app = JFactory::getApplication();
        $orderId = $app->input->getInt('id');
        
        if (!$orderId) {
            $app->enqueueMessage('订单ID不能为空', 'error');
            $app->redirect(JRoute::_('index.php?option=com_socialorders', false));
            return;
        }
        
        // 获取支付数据
        $paymentData = $app->getUserState('com_socialorders.payment_data');
        
        if (!$paymentData || empty($paymentData['code_url'])) {
            $app->enqueueMessage('支付二维码数据不存在', 'error');
            $app->redirect(JRoute::_('index.php?option=com_socialorders&view=order&id=' . $orderId, false));
            return;
        }
        
        $this->codeUrl = $paymentData['code_url'];
        $this->orderId = $orderId;
    }
    
    protected function prepareDefaultView()
    {
        // 默认视图
        $this->message = '微信支付页面';
    }
    
    private function getPaymentConfig()
    {
        $params = JComponentHelper::getParams('com_socialorders');
        
        return [
            'app_id' => $params->get('wechat_app_id'),
            'mch_id' => $params->get('wechat_mch_id'),
            'api_key' => $params->get('wechat_api_key'),
            'notify_url' => JUri::root() . 'index.php?option=com_socialorders&task=wechat.notify'
        ];
    }
}