<?php
/**
 * Template for MetaMask user profile shortcode
 *
 * @var array $atts Shortcode attributes
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Get current user
$current_user = wp_get_current_user();

// Get wallet address
$wallet_address = get_user_meta($current_user->ID, MetaMask_Login_Addon::USER_META_KEY, true);

// Determine display options
$show_address = ($atts['show_address'] === 'true');
$show_balance = ($atts['show_balance'] === 'true');

?>

<div class="metamask-shortcode-profile">
    <h3><?php _e('MetaMask Wallet', 'metamask-login'); ?></h3>
    
    <?php if (empty($wallet_address)) : ?>
        <div class="metamask-address-empty">
            <p><?php _e('No MetaMask wallet address linked to your account.', 'metamask-login'); ?></p>
            <button type="button" class="button metamask-connect-shortcode-btn">
                <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__)) . 'assets/images/metamask-fox.svg'); ?>" 
                     alt="MetaMask" class="metamask-login-icon" style="width: 20px; vertical-align: middle; margin-right: 8px;" />
                <?php _e('Connect MetaMask', 'metamask-login'); ?>
            </button>
            <div id="metamask-profile-status" class="metamask-login-status"></div>
        </div>
    <?php else : ?>
        <div class="metamask-address-connected">
            <?php if ($show_address) : ?>
                <div class="metamask-address-info">
                    <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__)) . 'assets/images/metamask-fox.svg'); ?>" 
                         alt="MetaMask" style="width: 24px; height: 24px;" />
                    <div>
                        <p><strong><?php _e('Wallet Address:', 'metamask-login'); ?></strong></p>
                        <p class="metamask-address-display"><?php echo esc_html($wallet_address); ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($show_balance) : ?>
                <div class="metamask-balance-info">
                    <p><strong><?php _e('Balance:', 'metamask-login'); ?></strong></p>
                    <p id="metamask-wallet-balance"><?php _e('Connect to view balance', 'metamask-login'); ?></p>
                </div>
                <script type="text/javascript">
                    // Add balance loading script
                    jQuery(document).ready(function($) {
                        // Check if MetaMask is available
                        if (typeof window.ethereum !== 'undefined') {
                            // Request the balance
                            window.ethereum.request({
                                method: 'eth_getBalance',
                                params: ['<?php echo esc_js($wallet_address); ?>', 'latest']
                            })
                            .then(function(balance) {
                                // Convert from wei to ETH
                                const ethBalance = parseInt(balance, 16) / 1e18;
                                $('#metamask-wallet-balance').text(ethBalance.toFixed(4) + ' ETH');
                            })
                            .catch(function(error) {
                                $('#metamask-wallet-balance').text('<?php _e('Error loading balance', 'metamask-login'); ?>');
                                console.error('Error fetching balance:', error);
                            });
                        }
                    });
                </script>
            <?php endif; ?>
            
            <div class="metamask-action-buttons">
                <button type="button" class="button metamask-disconnect-shortcode-btn">
                    <?php _e('Disconnect Wallet', 'metamask-login'); ?>
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>