<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
?>
<div class="wechat-auth">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card mt-5">
                    <div class="card-header text-center">
                        <h3 class="mb-0">微信授权</h3>
                    </div>
                    <div class="card-body text-center">
                        <?php if (isset($this->code) && $this->code): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle fa-3x mb-3"></i>
                                <h4>授权成功！</h4>
                                <p>正在获取用户信息，请稍候...</p>
                            </div>
                            
                            <script>
                            setTimeout(function() {
                                window.location.href = '<?php echo Route::_("index.php?option=com_socialorders&task=wechat.auth&code=" . $this->code); ?>';
                            }, 1500);
                            </script>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                                <h4>授权失败</h4>
                                <p>未收到授权码，请重试</p>
                            </div>
                            
                            <a href="<?php echo Route::_('index.php?option=com_socialorders'); ?>" class="btn btn-primary">
                                返回首页
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.wechat-auth {
    min-height: 70vh;
    display: flex;
    align-items: center;
}

.card {
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    border: none;
    border-radius: 10px;
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px 10px 0 0 !important;
    padding: 20px;
}

.alert {
    border: none;
    border-radius: 8px;
}

.fa-check-circle {
    color: #28a745;
}

.fa-exclamation-triangle {
    color: #ffc107;
}
</style>