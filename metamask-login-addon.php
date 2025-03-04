<?php
/**
 * Plugin Name: MetaMask Login Add-On
 * Plugin URI: https://stcchain.io
 * Description: Integration for MetaMask wallet authentication directly with WordPress user profiles.
 * Version: 1.0.0
 * Author: STC Chain
 * Author URI: https://stcchain.io
 * Text Domain: metamask-login
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class to handle all MetaMask login functionality
 */
class MetaMask_Login_Addon {
    
    /**
     * Plugin version
     */
    const VERSION = '1.0.0';
    
    /**
     * User meta key for storing wallet address
     */
    const USER_META_KEY = 'metamask_wallet_address';
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Plugin directory path
     */
    private $plugin_dir;
    
    /**
     * Plugin directory URL
     */
    private $plugin_url;
    
    /**
     * Initialize the plugin
     */
    private function __construct() {
        $this->plugin_dir = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);
        
        $this->load_dependencies();
        $this->setup_actions();
    }
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Include core files
        require_once $this->plugin_dir . 'includes/class-metamask-user-profile.php';
        require_once $this->plugin_dir . 'includes/class-metamask-authentication.php';
        require_once $this->plugin_dir . 'includes/class-metamask-api.php';
    }
    
    /**
     * Setup WordPress actions and filters
     */
    private function setup_actions() {
        // Register assets
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('admin_enqueue_scripts', array($this, 'register_admin_assets'));
        
        // Add shortcodes
        add_shortcode('metamask_login', array($this, 'login_shortcode'));
        add_shortcode('metamask_profile', array($this, 'profile_shortcode'));
        
        // Initialize other components
        MetaMask_User_Profile::init();
        MetaMask_Authentication::init();
        MetaMask_API::init();
    }
    
    /**
     * Register frontend scripts and styles
     */
    public function register_assets() {
        // Main plugin CSS
        wp_register_style(
            'metamask-login-style',
            $this->plugin_url . 'assets/css/metamask-login.css',
            array(),
            self::VERSION
        );
        
        // Main plugin JavaScript
        wp_register_script(
            'metamask-login-js',
            $this->plugin_url . 'assets/js/metamask-login.js',
            array('jquery'),
            self::VERSION,
            true
        );
        
        // Localize script with WordPress data
        wp_localize_script('metamask-login-js', 'MetaMaskLogin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'apiEndpoint' => rest_url('metamask-login/v1'),
            'nonce' => wp_create_nonce('metamask_login_nonce'),
            'isLoggedIn' => is_user_logged_in(),
            'loginRedirect' => site_url(),
            'i18n' => array(
                'connectWallet' => __('Connect MetaMask', 'metamask-login'),
                'connecting' => __('Connecting...', 'metamask-login'),
                'signing' => __('Signing...', 'metamask-login'),
                'success' => __('Success!', 'metamask-login'),
                'error' => __('Error', 'metamask-login'),
                'noMetaMask' => __('MetaMask not detected. Please install the MetaMask extension.', 'metamask-login'),
                'signatureRejected' => __('Signature request rejected.', 'metamask-login'),
                'confirmAddress' => __('Please confirm your wallet address', 'metamask-login'),
                'addressLinked' => __('Wallet address successfully linked to your account.', 'metamask-login'),
                'addressRemoved' => __('Wallet address removed from your account.', 'metamask-login')
            )
        ));
    }
    
    /**
     * Register admin scripts and styles
     */
    public function register_admin_assets($hook) {
        // Only load on profile pages or plugin settings
        if ($hook !== 'profile.php' && $hook !== 'user-edit.php') {
            return;
        }
        
        wp_enqueue_style(
            'metamask-login-admin-style',
            $this->plugin_url . 'assets/css/metamask-login-admin.css',
            array(),
            self::VERSION
        );
        
        wp_enqueue_script(
            'metamask-login-admin-js',
            $this->plugin_url . 'assets/js/metamask-login-admin.js',
            array('jquery'),
            self::VERSION,
            true
        );
        
        wp_localize_script('metamask-login-admin-js', 'MetaMaskLoginAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('metamask_login_nonce'), // Use the same nonce as frontend
            'debug' => true, // Add debug flag
            'i18n' => array(
                'confirmRemove' => __('Are you sure you want to remove this wallet address?', 'metamask-login')
            )
        ));
    }
    
    /**
     * Shortcode for login button
     */
    public function login_shortcode($atts) {
        // Enqueue assets
        wp_enqueue_style('metamask-login-style');
        wp_enqueue_script('metamask-login-js');
        
        // Parse attributes
        $atts = shortcode_atts(array(
            'redirect' => '',
            'button_text' => __('Connect with MetaMask', 'metamask-login'),
        ), $atts, 'metamask_login');
        
        // Start output buffering
        ob_start();
        
        // Include the template
        include $this->plugin_dir . 'templates/login-button.php';
        
        // Return the buffered content
        return ob_get_clean();
    }
    
    /**
     * Shortcode for wallet profile section
     */
    public function profile_shortcode($atts) {
        // Only for logged in users
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to manage your wallet.', 'metamask-login') . '</p>';
        }
        
        // Enqueue assets
        wp_enqueue_style('metamask-login-style');
        wp_enqueue_script('metamask-login-js');
        
        // Parse attributes
        $atts = shortcode_atts(array(
            'show_address' => 'true',
            'show_balance' => 'false',
        ), $atts, 'metamask_profile');
        
        // Start output buffering
        ob_start();
        
        // Include the template
        include $this->plugin_dir . 'templates/user-profile.php';
        
        // Return the buffered content
        return ob_get_clean();
    }
    
    /**
     * Get plugin directory path
     */
    public function get_plugin_dir() {
        return $this->plugin_dir;
    }
    
    /**
     * Get plugin URL
     */
    public function get_plugin_url() {
        return $this->plugin_url;
    }
}

/**
 * Initialize the plugin
 */
function metamask_login_addon_init() {
    // Create necessary directories and files if they don't exist
    $plugin_dir = plugin_dir_path(__FILE__);
    
    // Create directories
    $directories = array(
        'includes',
        'assets/css',
        'assets/js',
        'assets/images',
        'templates',
    );
    
    foreach ($directories as $dir) {
        $full_path = $plugin_dir . $dir;
        if (!file_exists($full_path)) {
            wp_mkdir_p($full_path);
        }
    }
    
    // Return the main plugin instance
    return MetaMask_Login_Addon::get_instance();
}

// Initialize the plugin
add_action('plugins_loaded', 'metamask_login_addon_init');