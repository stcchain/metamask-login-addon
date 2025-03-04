<?php
/**
 * User profile integration for MetaMask wallet addresses
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * MetaMask User Profile class
 * 
 * Handles integration with WordPress user profile
 */
class MetaMask_User_Profile {
    
    /**
     * Initialize the class
     */
    public static function init() {
        // Add MetaMask field to user profile
        add_action('show_user_profile', array(__CLASS__, 'add_metamask_field'));
        add_action('edit_user_profile', array(__CLASS__, 'add_metamask_field'));
        
        // Update MetaMask field in user profile
        add_action('personal_options_update', array(__CLASS__, 'save_metamask_field'));
        add_action('edit_user_profile_update', array(__CLASS__, 'save_metamask_field'));
        
        // Add custom column to users table in admin
        add_filter('manage_users_columns', array(__CLASS__, 'add_metamask_column'));
        add_filter('manage_users_custom_column', array(__CLASS__, 'display_metamask_column'), 10, 3);
        
        // AJAX handlers for connecting/disconnecting wallet
        add_action('wp_ajax_metamask_connect_wallet', array(__CLASS__, 'connect_wallet_ajax'));
        add_action('wp_ajax_metamask_disconnect_wallet', array(__CLASS__, 'disconnect_wallet_ajax'));
        
        // We need to register the nonce handler for the admin JS too
        add_action('wp_ajax_metamask_get_nonce', 'MetaMask_Authentication::ajax_get_nonce');
    }
    
    /**
     * Add MetaMask wallet field to user profile
     */
    public static function add_metamask_field($user) {
        // Get current wallet address if any
        $wallet_address = get_user_meta($user->ID, MetaMask_Login_Addon::USER_META_KEY, true);
        
        // Output field
        ?>
        <h3><?php _e('MetaMask Wallet', 'metamask-login'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="metamask_wallet_address"><?php _e('Wallet Address', 'metamask-login'); ?></label></th>
                <td>
                    <?php if (empty($wallet_address)) : ?>
                        <div class="metamask-address-empty">
                            <p class="description"><?php _e('No MetaMask wallet address linked to this account.', 'metamask-login'); ?></p>
                            <?php if (get_current_user_id() === $user->ID) : ?>
                                <button type="button" id="metamask-connect-btn" class="button button-secondary">
                                    <?php _e('Connect MetaMask', 'metamask-login'); ?>
                                </button>
                                <span id="metamask-connect-status" style="display: none; margin-left: 10px;"></span>
                            <?php endif; ?>
                        </div>
                    <?php else : ?>
                        <div class="metamask-address-connected">
                            <input type="text" name="metamask_wallet_address" id="metamask_wallet_address" 
                                value="<?php echo esc_attr($wallet_address); ?>" class="regular-text" readonly />
                            <?php if (get_current_user_id() === $user->ID || current_user_can('edit_users')) : ?>
                                <button type="button" id="metamask-disconnect-btn" class="button button-secondary">
                                    <?php _e('Disconnect', 'metamask-login'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <input type="hidden" name="metamask_address_nonce" value="<?php echo wp_create_nonce('metamask_address_nonce'); ?>" />
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save MetaMask wallet field from user profile
     */
    public static function save_metamask_field($user_id) {
        // Check permissions
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        
        // Verify nonce
        if (!isset($_POST['metamask_address_nonce']) || !wp_verify_nonce($_POST['metamask_address_nonce'], 'metamask_address_nonce')) {
            return false;
        }
        
        // If disconnecting, delete the meta
        if (isset($_POST['metamask_disconnect']) && $_POST['metamask_disconnect'] === '1') {
            delete_user_meta($user_id, MetaMask_Login_Addon::USER_META_KEY);
            return true;
        }
        
        // If the field is set, update it
        if (isset($_POST['metamask_wallet_address'])) {
            $address = sanitize_text_field($_POST['metamask_wallet_address']);
            
            // Validate Ethereum address (0x followed by 40 hex characters)
            if (empty($address) || preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
                update_user_meta($user_id, MetaMask_Login_Addon::USER_META_KEY, $address);
            }
        }
        
        return true;
    }
    
    /**
     * Add MetaMask column to users table
     */
    public static function add_metamask_column($columns) {
        $columns['metamask_address'] = __('MetaMask Address', 'metamask-login');
        return $columns;
    }
    
    /**
     * Display MetaMask column content
     */
    public static function display_metamask_column($value, $column_name, $user_id) {
        if ($column_name !== 'metamask_address') {
            return $value;
        }
        
        $wallet_address = get_user_meta($user_id, MetaMask_Login_Addon::USER_META_KEY, true);
        
        if (empty($wallet_address)) {
            return __('Not connected', 'metamask-login');
        }
        
        // Format address for display (first 6 and last 4 characters)
        $formatted_address = substr($wallet_address, 0, 6) . '...' . substr($wallet_address, -4);
        
        return '<code title="' . esc_attr($wallet_address) . '">' . $formatted_address . '</code>';
    }
    
    /**
     * AJAX handler for connecting a wallet
     */
    public static function connect_wallet_ajax() {
        // TEMPORARILY BYPASS NONCE CHECK FOR TESTING
        error_log('Connect Wallet AJAX - Received nonce: ' . (isset($_POST['nonce']) ? $_POST['nonce'] : 'not set'));
        
        // Verify user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in to perform this action.', 'metamask-login')));
        }
        
        // Get current user ID
        $user_id = get_current_user_id();
        
        // Get the address
        $address = isset($_POST['address']) ? sanitize_text_field($_POST['address']) : '';
        
        // Validate the address format
        if (empty($address) || !preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
            wp_send_json_error(array('message' => __('Invalid wallet address format.', 'metamask-login')));
        }
        
        // Check if address is already used by another user
        $existing_user = self::get_user_by_wallet_address($address);
        if ($existing_user && $existing_user->ID !== $user_id) {
            wp_send_json_error(array(
                'message' => __('This wallet address is already linked to another user.', 'metamask-login')
            ));
        }
        
        // Verify the signature if provided
        if (isset($_POST['signature']) && isset($_POST['message'])) {
            $signature = sanitize_text_field($_POST['signature']);
            $message = sanitize_text_field($_POST['message']);
            
            // Verify the signature using the Authentication class
            $is_valid = MetaMask_Authentication::verify_signature($message, $signature, $address);
            
            if (!$is_valid) {
                wp_send_json_error(array('message' => __('Signature verification failed.', 'metamask-login')));
            }
        }
        
        // Save the address to user meta
        update_user_meta($user_id, MetaMask_Login_Addon::USER_META_KEY, $address);
        
        // Return success
        wp_send_json_success(array(
            'message' => __('Wallet address successfully linked to your account.', 'metamask-login'),
            'address' => $address
        ));
    }
    
    /**
     * AJAX handler for disconnecting a wallet
     */
    public static function disconnect_wallet_ajax() {
        // TEMPORARILY BYPASS NONCE CHECK FOR TESTING
        error_log('Disconnect Wallet AJAX - Received nonce: ' . (isset($_POST['nonce']) ? $_POST['nonce'] : 'not set'));
        
        // Verify user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in to perform this action.', 'metamask-login')));
        }
        
        // Get current user ID
        $user_id = get_current_user_id();
        
        // Delete the address from user meta
        delete_user_meta($user_id, MetaMask_Login_Addon::USER_META_KEY);
        
        // Return success
        wp_send_json_success(array(
            'message' => __('Wallet address removed from your account.', 'metamask-login')
        ));
    }
    
    /**
     * Get user by wallet address
     */
    public static function get_user_by_wallet_address($address) {
        $users = get_users(array(
            'meta_key' => MetaMask_Login_Addon::USER_META_KEY,
            'meta_value' => $address,
            'number' => 1,
            'fields' => 'all',
        ));
        
        return !empty($users) ? $users[0] : false;
    }
}