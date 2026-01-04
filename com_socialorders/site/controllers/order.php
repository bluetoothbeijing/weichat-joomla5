<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

class SocialordersControllerOrder extends FormController
{
    protected $view_item = 'order';
    protected $view_list = 'socialorders';
    
    public function __construct($config = array())
    {
        parent::__construct($config);
        
        // 注册任务
        $this->registerTask('save2copy', 'save');
    }
    
    public function display($cachable = false, $urlparams = array())
    {
        $app = Factory::getApplication();
        $view = $app->input->getCmd('view', 'order');
        $layout = $app->input->getCmd('layout', 'edit');
        $id = $app->input->getInt('id');
        
        // 检查权限
        if ($id) {
            $model = $this->getModel('Order', 'SocialordersModel');
            $user = Factory::getUser();
            
            if (!$model->canView($id, $user->id)) {
                $app->enqueueMessage('无权查看此订单', 'error');
                $this->setRedirect(Route::_('index.php?option=com_socialorders', false));
                return false;
            }
        }
        
        return parent::display($cachable, $urlparams);
    }
    
    protected function allowAdd($data = array())
    {
        $user = Factory::getUser();
        return $user->authorise('core.create', 'com_socialorders') || $user->id > 0;
    }
    
    protected function allowEdit($data = array(), $key = 'id')
    {
        $recordId = isset($data[$key]) ? $data[$key] : 0;
        $user = Factory::getUser();
        
        if (!$recordId) {
            return false;
        }
        
        // 如果是管理员，允许编辑
        if ($user->authorise('core.edit', 'com_socialorders')) {
            return true;
        }
        
        // 检查订单所属用户
        $model = $this->getModel('Order', 'SocialordersModel');
        return $model->canView($recordId, $user->id);
    }
    
    public function cancel($key = null)
    {
        $app = Factory::getApplication();
        $id = $app->input->getInt('id');
        $user = Factory::getUser();
        
        if (!$id) {
            $app->enqueueMessage('参数错误', 'error');
            $this->setRedirect(Route::_('index.php?option=com_socialorders', false));
            return false;
        }
        
        try {
            $model = $this->getModel('Order', 'SocialordersModel');
            $result = $model->cancel($id, $user->id);
            
            if ($result) {
                $app->enqueueMessage('订单已取消', 'success');
            } else {
                $app->enqueueMessage('取消失败', 'error');
            }
        } catch (Exception $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }
        
        $this->setRedirect(Route::_('index.php?option=com_socialorders&view=order&id=' . $id, false));
    }
    
    public function create()
    {
        $app = Factory::getApplication();
        $data = $app->input->post->getArray();
        
        try {
            $model = $this->getModel('Order', 'SocialordersModel');
            $orderId = $model->create($data);
            
            if ($orderId) {
                $app->enqueueMessage('订单创建成功', 'success');
                $this->setRedirect(Route::_('index.php?option=com_socialorders&view=order&id=' . $orderId, false));
            } else {
                $app->enqueueMessage('订单创建失败', 'error');
                $this->setRedirect(Route::_('index.php?option=com_socialorders&view=socialorders', false));
            }
        } catch (Exception $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
            $this->setRedirect(Route::_('index.php?option=com_socialorders&view=socialorders', false));
        }
    }
    
    public function pay()
    {
        $app = Factory::getApplication();
        $orderId = $app->input->getInt('id');
        
        if (!$orderId) {
            $app->enqueueMessage('订单ID不能为空', 'error');
            $this->setRedirect(Route::_('index.php?option=com_socialorders', false));
            return false;
        }
        
        // 检查订单状态
        $model = $this->getModel('Order', 'SocialordersModel');
        $order = $model->getItem($orderId);
        
        if (!$order) {
            $app->enqueueMessage('订单不存在', 'error');
            $this->setRedirect(Route::_('index.php?option=com_socialorders', false));
            return false;
        }
        
        if ($order->payment_status !== 'unpaid') {
            $app->enqueueMessage('订单已支付或已关闭', 'error');
            $this->setRedirect(Route::_('index.php?option=com_socialorders&view=order&id=' . $orderId, false));
            return false;
        }
        
        // 跳转到支付页面
        $this->setRedirect(Route::_('index.php?option=com_socialorders&view=wechat&layout=pay&order_id=' . $orderId, false));
    }
    
    public function download()
    {
        $app = Factory::getApplication();
        $id = $app->input->getInt('id');
        
        if (!$id) {
            $app->enqueueMessage('参数错误', 'error');
            $this->setRedirect(Route::_('index.php?option=com_socialorders', false));
            return false;
        }
        
        try {
            $model = $this->getModel('Order', 'SocialordersModel');
            $order = $model->getItem($id);
            
            if (!$order) {
                throw new Exception('订单不存在');
            }
            
            // 检查权限
            $user = Factory::getUser();
            if (!$model->canView($id, $user->id)) {
                throw new Exception('无权下载此订单');
            }
            
            // 生成PDF
            $pdfContent = $this->generateOrderPDF($order);
            
            // 设置HTTP头
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="订单_' . $order->order_no . '.pdf"');
            header('Content-Length: ' . strlen($pdfContent));
            
            echo $pdfContent;
            $app->close();
            
        } catch (Exception $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
            $this->setRedirect(Route::_('index.php?option=com_socialorders&view=order&id=' . $id, false));
        }
    }
    
    private function generateOrderPDF($order)
    {
        // 简单HTML生成PDF（实际项目中应使用TCPDF或mPDF）
        $html = '
        <html>
        <head>
            <meta charset="UTF-8">
            <title>订单详情 - ' . $order->order_no . '</title>
            <style>
                body { font-family: "SimSun", sans-serif; }
                .header { text-align: center; margin-bottom: 30px; }
                .header h1 { margin: 0; }
                .order-info { margin-bottom: 20px; }
                .order-info table { width: 100%; border-collapse: collapse; }
                .order-info td { padding: 5px; border: 1px solid #ddd; }
                .items { margin-bottom: 20px; }
                .items table { width: 100%; border-collapse: collapse; }
                .items th, .items td { padding: 8px; border: 1px solid #ddd; text-align: center; }
                .total { text-align: right; font-weight: bold; font-size: 18px; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>订单详情</h1>
                <p>订单号: ' . $order->order_no . '</p>
            </div>
            
            <div class="order-info">
                <table>
                    <tr>
                        <td width="25%">订单标题</td>
                        <td>' . htmlspecialchars($order->title) . '</td>
                        <td width="25%">订单状态</td>
                        <td>' . $order->status_text . '</td>
                    </tr>
                    <tr>
                        <td>订单金额</td>
                        <td>' . $order->amount_formatted . '</td>
                        <td>实付金额</td>
                        <td>' . $order->real_amount_formatted . '</td>
                    </tr>
                    <tr>
                        <td>支付状态</td>
                        <td>' . $order->payment_status_text . '</td>
                        <td>支付时间</td>
                        <td>' . ($order->payment_time ? date('Y-m-d H:i:s', strtotime($order->payment_time)) : '未支付') . '</td>
                    </tr>
                    <tr>
                        <td>创建时间</td>
                        <td>' . date('Y-m-d H:i:s', strtotime($order->created)) . '</td>
                        <td>更新时间</td>
                        <td>' . date('Y-m-d H:i:s', strtotime($order->modified)) . '</td>
                    </tr>
                </table>
            </div>';
        
        // 商品信息
        if (!empty($order->items)) {
            $html .= '
            <div class="items">
                <h3>商品清单</h3>
                <table>
                    <thead>
                        <tr>
                            <th>商品名称</th>
                            <th>单价</th>
                            <th>数量</th>
                            <th>小计</th>
                        </tr>
                    </thead>
                    <tbody>';
            
            foreach ($order->items as $item) {
                $html .= '
                        <tr>
                            <td>' . htmlspecialchars($item->product_name) . '</td>
                            <td>' . number_format($item->price, 2) . '</td>
                            <td>' . $item->quantity . '</td>
                            <td>' . number_format($item->total, 2) . '</td>
                        </tr>';
            }
            
            $html .= '
                    </tbody>
                </table>
            </div>';
        }
        
        // 地址信息
        if (!empty($order->addresses)) {
            foreach ($order->addresses as $type => $address) {
                $typeText = $type === 'shipping' ? '收货地址' : '账单地址';
                $html .= '
                <div class="address">
                    <h3>' . $typeText . '</h3>
                    <p>' . htmlspecialchars($address->firstname . ' ' . $address->lastname) . '</p>
                    <p>' . htmlspecialchars($address->phone) . '</p>
                    <p>' . htmlspecialchars($address->address . ' ' . $address->city . ' ' . $address->state . ' ' . $address->country) . '</p>
                </div>';
            }
        }
        
        $html .= '
            <div class="total">
                <p>订单总额: ' . $order->amount_formatted . '</p>
                <p>实付金额: ' . $order->real_amount_formatted . '</p>
            </div>
            
            <div class="footer">
                <p>本订单由 ' . Factory::getApplication()->get('sitename') . ' 生成</p>
                <p>生成时间: ' . date('Y-m-d H:i:s') . '</p>
            </div>
        </body>
        </html>';
        
        // 返回HTML（实际项目中应转换为PDF）
        return $html;
    }
}