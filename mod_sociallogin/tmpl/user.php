<?php
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
?>
<div class="mod-sociallogin-user<?php echo $moduleclass_sfx; ?>">
    <div class="user-profile">
        <?php if ($showAvatar && $socialUser): ?>
            <div class="user-avatar text-center mb-3">
                <?php foreach ($socialUser as $social): ?>
                    <?php if ($social->avatar): ?>
                        <img src="<?php echo htmlspecialchars($social->avatar); ?>" 
                             alt="<?php echo htmlspecialchars($social->nickname); ?>" 
                             class="avatar-img rounded-circle">
                        <?php break; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($showWelcome): ?>
            <div class="welcome-message text-center mb-3">
                <h5><?php echo Text::sprintf('MOD_SOCIALLOGIN_WELCOME', htmlspecialchars($user->name)); ?></h5>
            </div>
        <?php endif; ?>
        
        <div class="user-menu">
            <ul class="list-unstyled">
                <li class="mb-2">
                    <a href="<?php echo Route::_('index.php?option=com_socialorders'); ?>" 
                       class="btn btn-outline-primary btn-block">
                        <i class="fas fa-shopping-cart"></i> <?php echo Text::_('MOD_SOCIALLOGIN_MY_ORDERS'); ?>
                    </a>
                </li>
                <li class="mb-2">
                    <a href="<?php echo Route::_('index.php?option=com_users&view=profile'); ?>" 
                       class="btn btn-outline-info btn-block">
                        <i class="fas fa-user-cog"></i> <?php echo Text::_('MOD_SOCIALLOGIN_PROFILE'); ?>
                    </a>
                </li>
                <li>
                    <a href="<?php echo $logoutUrl; ?>" 
                       class="btn btn-outline-danger btn-block">
                        <i class="fas fa-sign-out-alt"></i> <?php echo Text::_('MOD_SOCIALLOGIN_LOGOUT'); ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>

<style>
.mod-sociallogin-user {
    padding: 20px;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.user-avatar .avatar-img {
    width: 100px;
    height: 100px;
    border: 3px solid #f8f9fa;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.welcome-message h5 {
    color: #333;
    font-weight: 600;
}

.user-menu .btn {
    border-radius: 25px;
    padding: 8px 15px;
    text-align: left;
    transition: all 0.3s;
}

.user-menu .btn:hover {
    transform: translateX(5px);
}
</style>