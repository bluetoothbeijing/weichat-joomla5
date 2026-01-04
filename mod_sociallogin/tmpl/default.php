<?php
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

?>
<div class="mod-sociallogin<?php echo $moduleclass_sfx; ?>">
    
    <?php if ($isLoggedIn): ?>
        <!-- 已登录状态 -->
        <div class="user-info">
            <?php if ($showWelcome): ?>
                <div class="welcome-message">
                    <?php echo Text::sprintf('MOD_SOCIALLOGIN_WELCOME', htmlspecialchars($user->name)); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($showAvatar && $socialUser): ?>
                <div class="user-avatar">
                    <?php foreach ($socialUser as $social): ?>
                        <?php if ($social->avatar): ?>
                            <img src="<?php echo htmlspecialchars($social->avatar); ?>" 
                                 alt="<?php echo htmlspecialchars($social->nickname); ?>" 
                                 class="avatar-img">
                            <?php break; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="user-actions">
                <a href="<?php echo Route::_('index.php?option=com_socialorders'); ?>" 
                   class="btn btn-primary btn-sm mb-2">
                    <i class="fas fa-shopping-cart"></i> 我的订单
                </a>
                
                <?php if ($socialUser): ?>
                <a href="<?php echo Route::_('index.php?option=com_users&view=profile'); ?>" 
                   class="btn btn-info btn-sm mb-2">
                    <i class="fas fa-user-circle"></i> 账号管理
                </a>
                <?php endif; ?>
                
                <a href="<?php echo Route::_('index.php?option=com_users&task=user.logout&' . JSession::getFormToken() . '=1'); ?>" 
                   class="btn btn-outline-danger btn-sm">
                    <i class="fas fa-sign-out-alt"></i> 退出登录
                </a>
            </div>
        </div>
        
    <?php else: ?>
        <!-- 未登录状态 -->
        <div class="login-options">
            <div class="social-login-title">
                <?php echo Text::_('MOD_SOCIALLOGIN_SOCIAL_LOGIN'); ?>
            </div>
            
            <?php if ($showWeChatLogin && $wechatLoginUrl): ?>
                <div class="social-login-buttons">
                    <a href="<?php echo $wechatLoginUrl; ?>" 
                       class="btn btn-success btn-block mb-3 wechat-login-btn">
                        <i class="fab fa-weixin"></i> <?php echo Text::_('MOD_SOCIALLOGIN_WECHAT_LOGIN'); ?>
                    </a>
                </div>
            <?php endif; ?>
            
            <div class="traditional-login">
                <div class="or-separator">
                    <span><?php echo Text::_('MOD_SOCIALLOGIN_OR'); ?></span>
                </div>
                
                <a href="<?php echo $loginUrl; ?>" class="btn btn-outline-primary btn-block mb-2">
                    <i class="fas fa-user"></i> <?php echo Text::_('MOD_SOCIALLOGIN_EMAIL_LOGIN'); ?>
                </a>
                
                <?php if (JComponentHelper::isEnabled('com_users', true)): ?>
                <div class="register-link text-center mt-2">
                    <small>
                        <?php echo Text::_('MOD_SOCIALLOGIN_NO_ACCOUNT'); ?>
                        <a href="<?php echo Route::_('index.php?option=com_users&view=registration'); ?>">
                            <?php echo Text::_('MOD_SOCIALLOGIN_REGISTER_NOW'); ?>
                        </a>
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.mod-sociallogin {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.user-info .welcome-message {
    font-size: 16px;
    font-weight: bold;
    margin-bottom: 15px;
    color: #333;
}

.user-info .user-avatar {
    text-align: center;
    margin-bottom: 15px;
}

.user-info .avatar-img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 3px solid #fff;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.user-info .user-actions .btn {
    width: 100%;
}

.login-options .social-login-title {
    text-align: center;
    font-weight: bold;
    margin-bottom: 15px;
    color: #555;
}

.wechat-login-btn {
    background-color: #09bb07;
    border-color: #09bb07;
    color: white;
    font-weight: bold;
}

.wechat-login-btn:hover {
    background-color: #08a806;
    border-color: #08a806;
}

.or-separator {
    position: relative;
    text-align: center;
    margin: 20px 0;
    color: #999;
}

.or-separator:before {
    content: "";
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: #ddd;
}

.or-separator span {
    background: #f8f9fa;
    padding: 0 15px;
    position: relative;
    font-size: 14px;
}

.register-link a {
    color: #007bff;
    text-decoration: none;
}

.register-link a:hover {
    text-decoration: underline;
}
</style>