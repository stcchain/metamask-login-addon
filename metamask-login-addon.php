<?php
/**
 * Plugin Name: MetaMask Login Add-On
 * Plugin URI: https://stcchain.io/metamask-login
 * Description: Secure Web3 authentication plugin that enables users to connect their MetaMask wallet and login to WordPress without passwords. Supports wallet linking, signature verification, and seamless authentication.
 * Version: 2.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: STC Chain
 * Author URI: https://stcchain.io
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: metamask-login
 * Domain Path: /languages
 *
 * @package MetaMask_Login
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'METAMASK_LOGIN_VERSION', '2.0.0' );
define( 'METAMASK_LOGIN_PLUGIN_FILE', __FILE__ );
define( 'METAMASK_LOGIN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'METAMASK_LOGIN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'METAMASK_LOGIN_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class to handle all MetaMask login functionality
 *
 * @since 2.0.0
 */
class MetaMask_Login_Addon {

	/**
	 * Plugin version
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const VERSION = METAMASK_LOGIN_VERSION;

	/**
	 * User meta key for storing wallet address
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const USER_META_KEY = 'metamask_wallet_address';

	/**
	 * Plugin options key
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const OPTIONS_KEY = 'metamask_login_options';

	/**
	 * Instance of this class
	 *
	 * @since 2.0.0
	 * @var MetaMask_Login_Addon|null
	 */
	private static $instance = null;

	/**
	 * Plugin directory path
	 *
	 * @since 2.0.0
	 * @var string
	 */
	private $plugin_dir;

	/**
	 * Plugin directory URL
	 *
	 * @since 2.0.0
	 * @var string
	 */
	private $plugin_url;

	/**
	 * Plugin options
	 *
	 * @since 2.0.0
	 * @var array
	 */
	private $options;

	/**
	 * Initialize the plugin
	 *
	 * @since 2.0.0
	 */
	private function __construct() {
		$this->plugin_dir = METAMASK_LOGIN_PLUGIN_DIR;
		$this->plugin_url = METAMASK_LOGIN_PLUGIN_URL;
		$this->options    = $this->get_options();

		$this->load_dependencies();
		$this->setup_actions();
		$this->load_textdomain();
	}

	/**
	 * Get plugin instance
	 *
	 * @since 2.0.0
	 * @return MetaMask_Login_Addon Plugin instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Load required dependencies
	 *
	 * @since 2.0.0
	 */
	private function load_dependencies() {
		// Include core files.
		require_once $this->plugin_dir . 'includes/class-metamask-user-profile.php';
		require_once $this->plugin_dir . 'includes/class-metamask-authentication.php';
		require_once $this->plugin_dir . 'includes/class-metamask-api.php';

		// Include admin settings if in admin.
		if ( is_admin() ) {
			require_once $this->plugin_dir . 'includes/class-metamask-admin.php';
		}
	}

	/**
	 * Setup WordPress actions and filters
	 *
	 * @since 2.0.0
	 */
	private function setup_actions() {
		// Register assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_assets' ) );

		// Add shortcodes.
		add_shortcode( 'metamask_login', array( $this, 'login_shortcode' ) );
		add_shortcode( 'metamask_profile', array( $this, 'profile_shortcode' ) );

		// Initialize other components.
		MetaMask_User_Profile::init();
		MetaMask_Authentication::init();
		MetaMask_API::init();

		// Admin settings.
		if ( is_admin() ) {
			MetaMask_Admin::init();
		}

		// Plugin action links.
		add_filter( 'plugin_action_links_' . METAMASK_LOGIN_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
	}

	/**
	 * Load plugin textdomain
	 *
	 * @since 2.0.0
	 */
	private function load_textdomain() {
		load_plugin_textdomain(
			'metamask-login',
			false,
			dirname( METAMASK_LOGIN_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Get plugin options
	 *
	 * @since 2.0.0
	 * @return array Plugin options.
	 */
	private function get_options() {
		$defaults = array(
			'enable_login_page'      => true,
			'enable_registration'    => false,
			'custom_sign_message'    => '',
			'rate_limit_enabled'     => true,
			'rate_limit_attempts'    => 5,
			'rate_limit_window'      => 300,
			'require_verification'   => true,
			'dev_mode'               => false,
		);

		$options = get_option( self::OPTIONS_KEY, array() );
		return wp_parse_args( $options, $defaults );
	}

	/**
	 * Register frontend scripts and styles
	 *
	 * @since 2.0.0
	 */
	public function register_assets() {
		// Main plugin CSS.
		wp_register_style(
			'metamask-login-style',
			$this->plugin_url . 'assets/css/metamask-login.css',
			array(),
			self::VERSION
		);

		// Main plugin JavaScript.
		wp_register_script(
			'metamask-login-js',
			$this->plugin_url . 'assets/js/metamask-login.js',
			array( 'jquery' ),
			self::VERSION,
			true
		);

		// Localize script with WordPress data.
		wp_localize_script(
			'metamask-login-js',
			'MetaMaskLogin',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'apiEndpoint'   => rest_url( 'metamask-login/v1' ),
				'nonce'         => wp_create_nonce( 'metamask_login_nonce' ),
				'isLoggedIn'    => is_user_logged_in(),
				'loginRedirect' => apply_filters( 'metamask_login_default_redirect', home_url() ),
				'i18n'          => array(
					'connectWallet'      => __( 'Connect MetaMask', 'metamask-login' ),
					'connecting'         => __( 'Connecting...', 'metamask-login' ),
					'signing'            => __( 'Signing...', 'metamask-login' ),
					'success'            => __( 'Success!', 'metamask-login' ),
					'error'              => __( 'Error', 'metamask-login' ),
					'noMetaMask'         => __( 'MetaMask not detected. Please install the MetaMask extension.', 'metamask-login' ),
					'signatureRejected'  => __( 'Signature request rejected.', 'metamask-login' ),
					'confirmAddress'     => __( 'Please confirm your wallet address', 'metamask-login' ),
					'addressLinked'      => __( 'Wallet address successfully linked to your account.', 'metamask-login' ),
					'addressRemoved'     => __( 'Wallet address removed from your account.', 'metamask-login' ),
					'noAccountFound'     => __( 'No account found for this wallet address.', 'metamask-login' ),
					'rateLimitExceeded'  => __( 'Too many attempts. Please try again later.', 'metamask-login' ),
				),
			)
		);
	}

	/**
	 * Register admin scripts and styles
	 *
	 * @since 2.0.0
	 * @param string $hook Current admin page hook.
	 */
	public function register_admin_assets( $hook ) {
		// Only load on profile pages or plugin settings.
		$allowed_hooks = array( 'profile.php', 'user-edit.php', 'settings_page_metamask-login' );

		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
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
			array( 'jquery' ),
			self::VERSION,
			true
		);

		wp_localize_script(
			'metamask-login-admin-js',
			'MetaMaskLoginAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'metamask_login_nonce' ),
				'i18n'    => array(
					'confirmRemove' => __( 'Are you sure you want to remove this wallet address?', 'metamask-login' ),
					'connectWallet' => __( 'Connect MetaMask', 'metamask-login' ),
					'connecting'    => __( 'Connecting...', 'metamask-login' ),
					'disconnect'    => __( 'Disconnect', 'metamask-login' ),
				),
			)
		);
	}

	/**
	 * Shortcode for login button
	 *
	 * @since 2.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function login_shortcode( $atts ) {
		// Enqueue assets.
		wp_enqueue_style( 'metamask-login-style' );
		wp_enqueue_script( 'metamask-login-js' );

		// Parse attributes.
		$atts = shortcode_atts(
			array(
				'redirect'    => '',
				'button_text' => __( 'Connect with MetaMask', 'metamask-login' ),
				'button_class' => 'metamask-login-button',
			),
			$atts,
			'metamask_login'
		);

		// Start output buffering.
		ob_start();

		// Include the template.
		$template_path = $this->plugin_dir . 'templates/login-button.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		}

		// Return the buffered content.
		return ob_get_clean();
	}

	/**
	 * Shortcode for wallet profile section
	 *
	 * @since 2.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function profile_shortcode( $atts ) {
		// Only for logged in users.
		if ( ! is_user_logged_in() ) {
			return '<p class="metamask-notice">' . esc_html__( 'Please log in to manage your wallet.', 'metamask-login' ) . '</p>';
		}

		// Enqueue assets.
		wp_enqueue_style( 'metamask-login-style' );
		wp_enqueue_script( 'metamask-login-js' );

		// Parse attributes.
		$atts = shortcode_atts(
			array(
				'show_address' => 'true',
				'show_balance' => 'false',
			),
			$atts,
			'metamask_profile'
		);

		// Start output buffering.
		ob_start();

		// Include the template.
		$template_path = $this->plugin_dir . 'templates/user-profile.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		}

		// Return the buffered content.
		return ob_get_clean();
	}

	/**
	 * Add plugin action links
	 *
	 * @since 2.0.0
	 * @param array $links Existing links.
	 * @return array Modified links.
	 */
	public function add_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'options-general.php?page=metamask-login' ),
			__( 'Settings', 'metamask-login' )
		);

		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Get plugin directory path
	 *
	 * @since 2.0.0
	 * @return string Plugin directory path.
	 */
	public function get_plugin_dir() {
		return $this->plugin_dir;
	}

	/**
	 * Get plugin URL
	 *
	 * @since 2.0.0
	 * @return string Plugin URL.
	 */
	public function get_plugin_url() {
		return $this->plugin_url;
	}

	/**
	 * Get plugin option
	 *
	 * @since 2.0.0
	 * @param string $key     Option key.
	 * @param mixed  $default Default value.
	 * @return mixed Option value.
	 */
	public function get_option( $key, $default = null ) {
		return isset( $this->options[ $key ] ) ? $this->options[ $key ] : $default;
	}
}

/**
 * Plugin activation hook
 *
 * @since 2.0.0
 */
function metamask_login_activate() {
	// Set default options.
	$default_options = array(
		'enable_login_page'      => true,
		'enable_registration'    => false,
		'rate_limit_enabled'     => true,
		'rate_limit_attempts'    => 5,
		'rate_limit_window'      => 300,
		'require_verification'   => true,
		'dev_mode'               => false,
	);

	if ( ! get_option( MetaMask_Login_Addon::OPTIONS_KEY ) ) {
		add_option( MetaMask_Login_Addon::OPTIONS_KEY, $default_options );
	}

	// Set activation flag.
	set_transient( 'metamask_login_activated', true, 30 );

	// Flush rewrite rules for REST API.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'metamask_login_activate' );

/**
 * Plugin deactivation hook
 *
 * @since 2.0.0
 */
function metamask_login_deactivate() {
	// Clean up transients.
	global $wpdb;
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE %s",
			'_transient_metamask_rate_limit_%'
		)
	);
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE %s",
			'_transient_timeout_metamask_rate_limit_%'
		)
	);

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'metamask_login_deactivate' );

/**
 * Initialize the plugin
 *
 * @since 2.0.0
 * @return MetaMask_Login_Addon Plugin instance.
 */
function metamask_login_addon_init() {
	return MetaMask_Login_Addon::get_instance();
}

// Initialize the plugin.
add_action( 'plugins_loaded', 'metamask_login_addon_init' );

/**
 * Show admin notice on activation
 *
 * @since 2.0.0
 */
function metamask_login_activation_notice() {
	if ( get_transient( 'metamask_login_activated' ) ) {
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %s: Settings page URL */
					__( 'MetaMask Login Add-On has been activated! <a href="%s">Configure settings</a>', 'metamask-login' ),
					esc_url( admin_url( 'options-general.php?page=metamask-login' ) )
				);
				?>
			</p>
		</div>
		<?php
		delete_transient( 'metamask_login_activated' );
	}
}
add_action( 'admin_notices', 'metamask_login_activation_notice' );
