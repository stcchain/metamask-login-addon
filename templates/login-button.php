<?php
/**
 * Template for MetaMask login button shortcode
 *
 * @var array $atts Shortcode attributes
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Get redirect URL
$redirect_url = !empty($atts['redirect']) ? esc_url($atts['redirect']) : '';

// Get button text
$button_text = !empty($atts['button_text']) ? esc_html($atts['button_text']) : __('Connect with MetaMask', 'metamask-login');

// Check if user is already logged in
if (is_user_logged_in()) {
    $current_user = wp_get_current_user();
    ?>
    <div class="metamask-shortcode-login">
        <p><?php printf(__('You are already logged in as %s.', 'metamask-login'), '<strong>' . esc_html($current_user->display_name) . '</strong>'); ?></p>
        <a href="<?php echo esc_url(wp_logout_url($redirect_url ?: home_url())); ?>" class="button">
            <?php _e('Log Out', 'metamask-login'); ?>
        </a>
    </div>
    <?php
    return;
}
?>

<div class="metamask-shortcode-login">
    <div class="metamask-login-container">
        <button type="button" class="metamask-login-button metamask-login-shortcode-btn">
            <?php echo esc_html($button_text); ?>
        </button>
        <div class="metamask-login-status" id="metamask-login-status"></div>
    </div>
</div>

<script type="text/javascript">
    // Set custom redirect URL if provided
    <?php if (!empty($redirect_url)) : ?>
    if (typeof MetaMaskLogin !== 'undefined') {
        MetaMaskLogin.loginRedirect = '<?php echo esc_js($redirect_url); ?>';
    }
    <?php endif; ?>
</script>