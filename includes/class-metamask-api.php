<?php
/**
 * MetaMask REST API endpoints
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * MetaMask API class
 * 
 * Handles REST API endpoints for MetaMask integration
 */
class MetaMask_API {
    
    /**
     * Initialize the class
     */
    public static function init() {
        // Register REST API routes
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public static function register_routes() {
        register_rest_route('metamask-login/v1', '/nonce', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'get_nonce'),
            'permission_callback' => '__return_true',
        ));
        
        register_rest_route('metamask-login/v1', '/auth', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'authenticate'),
            'permission_callback' => '__return_true',
        ));
        
        register_rest_route('metamask-login/v1', '/verify', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'verify_wallet'),
            'permission_callback' => function() {
                return is_user_logged_in();
            },
        ));
        
        register_rest_route('metamask-login/v1', '/link', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'link_wallet'),
            'permission_callback' => function() {
                return is_user_logged_in();
            },
        ));
        
        register_rest_route('metamask-login/v1', '/unlink', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'unlink_wallet'),
            'permission_callback' => function() {
                return is_user_logged_in();
            },
        ));
    }
    
    /**
     * Get a nonce for signing
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function get_nonce($request) {
        // Get address from request
        $address = $request->get_param('address');
        
        if (empty($address)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Wallet address is required.', 'metamask-login')
            ), 400);
        }
        
        // Sanitize address
        $address = sanitize_text_field($address);
        
        // Generate signing message
        $message = MetaMask_Authentication::generate_sign_message($address);
        
        // Return the message
        return new WP_REST_Response(array(
            'success' => true,
            'message' => $message
        ));
    }
    
    /**
     * Authenticate a user with MetaMask
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function authenticate($request) {
        // Get parameters
        $address = $request->get_param('address');
        $signature = $request->get_param('signature');
        $message = $request->get_param('message');
        
        if (empty($address) || empty($signature) || empty($message)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Missing required parameters.', 'metamask-login')
            ), 400);
        }
        
        // Sanitize inputs
        $address = sanitize_text_field($address);
        $signature = sanitize_text_field($signature);
        $message = sanitize_textarea_field($message);
        
        // Verify signature
        $is_valid = MetaMask_Authentication::verify_signature($message, $signature, $address);
        
        if (!$is_valid) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Signature verification failed.', 'metamask-login')
            ), 400);
        }
        
        // Find user by wallet address
        $user = MetaMask_User_Profile::get_user_by_wallet_address($address);
        
        if (!$user) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('No WordPress user is linked to this wallet address.', 'metamask-login')
            ), 404);
        }
        
        // Log the user in
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);
        do_action('wp_login', $user->user_login, $user);
        
        // Create a login token for frontend verification
        $token = wp_generate_uuid4();
        set_transient('metamask_login_' . $token, $user->ID, 5 * MINUTE_IN_SECONDS);
        
        // Return success
        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Login successful!', 'metamask-login'),
            'user_id' => $user->ID,
            'token' => $token,
            'redirect' => apply_filters('metamask_login_redirect', admin_url())
        ));
    }
    
    /**
     * Verify wallet ownership for a logged-in user
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function verify_wallet($request) {
        // Get user ID from current user
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('You must be logged in to verify wallet ownership.', 'metamask-login')
            ), 401);
        }
        
        // Get parameters
        $address = $request->get_param('address');
        $signature = $request->get_param('signature');
        $message = $request->get_param('message');
        
        if (empty($address) || empty($signature) || empty($message)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Missing required parameters.', 'metamask-login')
            ), 400);
        }
        
        // Sanitize inputs
        $address = sanitize_text_field($address);
        $signature = sanitize_text_field($signature);
        $message = sanitize_textarea_field($message);
        
        // Verify signature
        $is_valid = MetaMask_Authentication::verify_signature($message, $signature, $address);
        
        if (!$is_valid) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Signature verification failed.', 'metamask-login')
            ), 400);
        }
        
        // Check if address is already linked to another user
        $existing_user = MetaMask_User_Profile::get_user_by_wallet_address($address);
        if ($existing_user && $existing_user->ID !== $user_id) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('This wallet address is already linked to another user.', 'metamask-login')
            ), 409);
        }
        
        // Return success
        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Wallet verified successfully!', 'metamask-login'),
            'address' => $address
        ));
    }
    
    /**
     * Link a wallet to a user account
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function link_wallet($request) {
        // Get user ID from current user
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('You must be logged in to link a wallet.', 'metamask-login')
            ), 401);
        }
        
        // Get parameters
        $address = $request->get_param('address');
        $signature = $request->get_param('signature');
        $message = $request->get_param('message');
        
        if (empty($address) || empty($signature) || empty($message)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Missing required parameters.', 'metamask-login')
            ), 400);
        }
        
        // Sanitize inputs
        $address = sanitize_text_field($address);
        $signature = sanitize_text_field($signature);
        $message = sanitize_textarea_field($message);
        
        // Verify signature
        $is_valid = MetaMask_Authentication::verify_signature($message, $signature, $address);
        
        if (!$is_valid) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Signature verification failed.', 'metamask-login')
            ), 400);
        }
        
        // Check if address is already linked to another user
        $existing_user = MetaMask_User_Profile::get_user_by_wallet_address($address);
        if ($existing_user && $existing_user->ID !== $user_id) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('This wallet address is already linked to another user.', 'metamask-login')
            ), 409);
        }
        
        // Update user meta
        update_user_meta($user_id, MetaMask_Login_Addon::USER_META_KEY, $address);
        
        // Return success
        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Wallet linked successfully!', 'metamask-login'),
            'address' => $address
        ));
    }
    
    /**
     * Unlink a wallet from a user account
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function unlink_wallet($request) {
        // Get user ID from current user
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('You must be logged in to unlink a wallet.', 'metamask-login')
            ), 401);
        }
        
        // Delete user meta
        delete_user_meta($user_id, MetaMask_Login_Addon::USER_META_KEY);
        
        // Return success
        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Wallet unlinked successfully!', 'metamask-login')
        ));
    }
}