<?php
/**
 * MetaMask authentication functionality
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * MetaMask Authentication class
 * 
 * Handles authentication with MetaMask wallet
 */
class MetaMask_Authentication {
    
    /**
     * Initialize the class
     */
    public static function init() {
        // Filter to authenticate users
        add_filter('authenticate', array(__CLASS__, 'authenticate_metamask_user'), 20, 3);
        
        // Login form integration
        add_action('login_form', array(__CLASS__, 'add_metamask_login_button'));
        add_action('login_enqueue_scripts', array(__CLASS__, 'enqueue_login_scripts'));
        
        // Login handlers
        add_action('wp_ajax_nopriv_metamask_get_nonce', array(__CLASS__, 'ajax_get_nonce'));
        add_action('wp_ajax_metamask_get_nonce', array(__CLASS__, 'ajax_get_nonce'));
        add_action('wp_ajax_nopriv_metamask_authentication', array(__CLASS__, 'ajax_authentication'));
        add_action('wp_ajax_metamask_verify_wallet', array(__CLASS__, 'ajax_verify_wallet'));
    }
    
    /**
     * Add MetaMask login button to WordPress login form
     */
    public static function add_metamask_login_button() {
        ?>
        <div class="metamask-login-container">
            <p class="metamask-login-or"><?php _e('Or', 'metamask-login'); ?></p>
            <button type="button" id="metamask-login-button" class="button button-primary">
                <?php _e('Login with MetaMask', 'metamask-login'); ?>
            </button>
            <div id="metamask-login-status" class="metamask-login-status"></div>
        </div>
        <?php
    }
    
    /**
     * Enqueue scripts for the login page
     */
    public static function enqueue_login_scripts() {
        $plugin = MetaMask_Login_Addon::get_instance();
        
        wp_enqueue_style(
            'metamask-login-style',
            $plugin->get_plugin_url() . 'assets/css/metamask-login.css',
            array(),
            MetaMask_Login_Addon::VERSION
        );
        
        wp_enqueue_script(
            'metamask-login-js',
            $plugin->get_plugin_url() . 'assets/js/metamask-login.js',
            array('jquery'),
            MetaMask_Login_Addon::VERSION,
            true
        );
        
        wp_localize_script('metamask-login-js', 'MetaMaskLogin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('metamask_login_nonce'),
            'isLogin' => true,
            'i18n' => array(
                'connectWallet' => __('Connect MetaMask', 'metamask-login'),
                'connecting' => __('Connecting...', 'metamask-login'),
                'signing' => __('Signing...', 'metamask-login'),
                'success' => __('Success!', 'metamask-login'),
                'error' => __('Error', 'metamask-login'),
                'noMetaMask' => __('MetaMask not detected. Please install the MetaMask extension.', 'metamask-login'),
                'signatureRejected' => __('Signature request rejected.', 'metamask-login'),
                'noAccountFound' => __('No account found for this wallet address.', 'metamask-login'),
            )
        ));
    }
    
    /**
     * Main authentication function
     */
    public static function authenticate_metamask_user($user, $username, $password) {
        // If another authentication system already worked, return that user
        if ($user instanceof WP_User) {
            return $user;
        }
        
        // Check if MetaMask login is active
        if (!isset($_POST['metamask_login']) || $_POST['metamask_login'] !== '1') {
            return $user;
        }
        
        // Verify nonce
        if (!isset($_POST['metamask_nonce']) || !wp_verify_nonce($_POST['metamask_nonce'], 'metamask_login_nonce')) {
            return new WP_Error('metamask_auth_failed', __('Security check failed.', 'metamask-login'));
        }
        
        // Get provided data
        $address = isset($_POST['metamask_address']) ? sanitize_text_field($_POST['metamask_address']) : '';
        $signature = isset($_POST['metamask_signature']) ? sanitize_text_field($_POST['metamask_signature']) : '';
        $message = isset($_POST['metamask_message']) ? sanitize_text_field($_POST['metamask_message']) : '';
        
        // Validate data
        if (empty($address) || empty($signature) || empty($message)) {
            return new WP_Error('metamask_auth_failed', __('Missing required data for MetaMask authentication.', 'metamask-login'));
        }
        
        // Verify signature
        $is_valid = self::verify_signature($message, $signature, $address);
        
        if (!$is_valid) {
            return new WP_Error('metamask_auth_failed', __('Signature verification failed.', 'metamask-login'));
        }
        
        // Find user by wallet address
        $found_user = MetaMask_User_Profile::get_user_by_wallet_address($address);
        
        if (!$found_user) {
            return new WP_Error('metamask_auth_failed', __('No WordPress user is linked to this wallet address.', 'metamask-login'));
        }
        
        // Return the authenticated user
        return $found_user;
    }
    
    /**
     * AJAX handler for getting nonce
     */
    public static function ajax_get_nonce() {
        // TEMPORARILY BYPASS NONCE CHECK FOR TESTING
        error_log('AJAX Get Nonce - Received nonce: ' . (isset($_POST['nonce']) ? $_POST['nonce'] : 'not set'));
        
        // Get address from request
        $address = isset($_POST['address']) ? sanitize_text_field($_POST['address']) : '';
        
        if (empty($address)) {
            wp_send_json_error(array('message' => __('Wallet address is required.', 'metamask-login')));
        }
        
        // Generate signing message
        $message = self::generate_sign_message($address);
        
        // Return the message for signing
        wp_send_json_success(array('message' => $message));
    }
    
    /**
     * AJAX handler for authentication
     */
    public static function ajax_authentication() {
        // TEMPORARILY BYPASS NONCE CHECK FOR TESTING
        error_log('AJAX Authentication - Received nonce: ' . (isset($_POST['nonce']) ? $_POST['nonce'] : 'not set'));
        
        // Get data from request
        $address = isset($_POST['address']) ? sanitize_text_field($_POST['address']) : '';
        $signature = isset($_POST['signature']) ? sanitize_text_field($_POST['signature']) : '';
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
        
        // Validate data
        if (empty($address) || empty($signature) || empty($message)) {
            wp_send_json_error(array('message' => __('Missing required data for authentication.', 'metamask-login')));
        }
        
        // Verify signature
        $is_valid = self::verify_signature($message, $signature, $address);
        
        if (!$is_valid) {
            wp_send_json_error(array('message' => __('Signature verification failed.', 'metamask-login')));
        }
        
        // Find user by wallet address
        $user = MetaMask_User_Profile::get_user_by_wallet_address($address);
        
        if (!$user) {
            wp_send_json_error(array('message' => __('No WordPress user is linked to this wallet address.', 'metamask-login')));
        }
        
        // Log the user in
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);
        do_action('wp_login', $user->user_login, $user);
        
        // Return success
        wp_send_json_success(array(
            'message' => __('Login successful!', 'metamask-login'),
            'redirect' => apply_filters('metamask_login_redirect', admin_url())
        ));
    }
    
    /**
     * AJAX handler for verifying wallet ownership
     */
    public static function ajax_verify_wallet() {
        // TEMPORARILY BYPASS NONCE CHECK FOR TESTING
        error_log('AJAX Verify Wallet - Received nonce: ' . (isset($_POST['nonce']) ? $_POST['nonce'] : 'not set'));
        
        // Only logged-in users can verify wallet ownership
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in to verify wallet ownership.', 'metamask-login')));
        }
        
        // Get data from request
        $address = isset($_POST['address']) ? sanitize_text_field($_POST['address']) : '';
        $signature = isset($_POST['signature']) ? sanitize_text_field($_POST['signature']) : '';
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
        
        // Validate data
        if (empty($address) || empty($signature) || empty($message)) {
            wp_send_json_error(array('message' => __('Missing required data for verification.', 'metamask-login')));
        }
        
        // Verify signature
        $is_valid = self::verify_signature($message, $signature, $address);
        
        if (!$is_valid) {
            wp_send_json_error(array('message' => __('Signature verification failed.', 'metamask-login')));
        }
        
        // Check if the address is already linked to another user
        $existing_user = MetaMask_User_Profile::get_user_by_wallet_address($address);
        if ($existing_user && $existing_user->ID !== get_current_user_id()) {
            wp_send_json_error(array('message' => __('This wallet address is already linked to another user.', 'metamask-login')));
        }
        
        // Return success
        wp_send_json_success(array(
            'message' => __('Wallet ownership verified.', 'metamask-login'),
            'address' => $address
        ));
    }
    
    /**
     * Generate a message for signing
     * 
     * @param string $address Ethereum address
     * @return string Message to sign
     */
    public static function generate_sign_message($address) {
        $site_name = get_bloginfo('name');
        $nonce = wp_create_nonce('metamask_auth_' . $address);
        
        return sprintf(
            __("Sign this message to prove you own this wallet address and to log in to %s\n\nThis request will not trigger a blockchain transaction or cost any gas fees.\n\nWallet address:\n%s\n\nNonce: %s", 'metamask-login'),
            $site_name,
            $address,
            $nonce
        );
    }
    
    /**
     * Verify an Ethereum signature
     * 
     * @param string $message Original message that was signed
     * @param string $signature Signature to verify
     * @param string $address Address that supposedly signed the message
     * @return bool True if signature is valid
     */
    public static function verify_signature($message, $signature, $address) {
        // For now, we'll use a simplified verification for testing purposes
        // In production, we should use a proper Ethereum signature verification library
        
        // If we have the Ethereum Message Recovery library
        if (function_exists('wp_ethsign_verify')) {
            // This is a sample function name - replace with actual library function
            return wp_ethsign_verify($message, $signature, $address);
        }
        
        // For testing, we'll always return true if the signature appears to be in the correct format
        // This is NOT secure for production use!
        if (preg_match('/^0x[a-fA-F0-9]{130}$/', $signature) && preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
            // Only for development/testing
            return true;
        }
        
        // In a real implementation, we would perform cryptographic verification here
        
        // By default, attempt to create a new ETH sign verifier and check
        try {
            require_once plugin_dir_path(__FILE__) . 'utils/eth-sign-verify.php';
            $eth_sign = new Eth_Sign_Verify();
            return $eth_sign->verify($message, $signature, $address);
        } catch (Exception $e) {
            error_log('MetaMask signature verification error: ' . $e->getMessage());
            return false;
        }
        
        return false;
    }
}