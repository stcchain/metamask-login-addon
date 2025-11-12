<?php
/**
 * MetaMask authentication functionality
 *
 * @package MetaMask_Login
 * @since 2.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MetaMask Authentication class
 *
 * Handles authentication with MetaMask wallet
 *
 * @since 2.0.0
 */
class MetaMask_Authentication {

	/**
	 * Rate limit transient prefix
	 *
	 * @since 2.0.0
	 */
	const RATE_LIMIT_PREFIX = 'metamask_rate_limit_';

	/**
	 * Maximum login attempts per IP
	 *
	 * @since 2.0.0
	 */
	const MAX_ATTEMPTS = 5;

	/**
	 * Rate limit window in seconds
	 *
	 * @since 2.0.0
	 */
	const RATE_LIMIT_WINDOW = 300; // 5 minutes

	/**
	 * Initialize the class
	 *
	 * @since 2.0.0
	 */
	public static function init() {
		// Filter to authenticate users.
		add_filter( 'authenticate', array( __CLASS__, 'authenticate_metamask_user' ), 20, 3 );

		// Login form integration.
		add_action( 'login_form', array( __CLASS__, 'add_metamask_login_button' ) );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'enqueue_login_scripts' ) );

		// Login handlers.
		add_action( 'wp_ajax_nopriv_metamask_get_nonce', array( __CLASS__, 'ajax_get_nonce' ) );
		add_action( 'wp_ajax_metamask_get_nonce', array( __CLASS__, 'ajax_get_nonce' ) );
		add_action( 'wp_ajax_nopriv_metamask_authentication', array( __CLASS__, 'ajax_authentication' ) );
		add_action( 'wp_ajax_metamask_verify_wallet', array( __CLASS__, 'ajax_verify_wallet' ) );
	}

	/**
	 * Add MetaMask login button to WordPress login form
	 *
	 * @since 2.0.0
	 */
	public static function add_metamask_login_button() {
		?>
		<div class="metamask-login-container">
			<p class="metamask-login-or"><?php esc_html_e( 'Or', 'metamask-login' ); ?></p>
			<button type="button" id="metamask-login-button" class="button button-primary button-large">
				<?php esc_html_e( 'Login with MetaMask', 'metamask-login' ); ?>
			</button>
			<div id="metamask-login-status" class="metamask-login-status"></div>
		</div>
		<?php
	}

	/**
	 * Enqueue scripts for the login page
	 *
	 * @since 2.0.0
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
			array( 'jquery' ),
			MetaMask_Login_Addon::VERSION,
			true
		);

		wp_localize_script(
			'metamask-login-js',
			'MetaMaskLogin',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'metamask_login_nonce' ),
				'isLogin'  => true,
				'i18n'     => array(
					'connectWallet'      => __( 'Connect MetaMask', 'metamask-login' ),
					'connecting'         => __( 'Connecting...', 'metamask-login' ),
					'signing'            => __( 'Signing...', 'metamask-login' ),
					'success'            => __( 'Success!', 'metamask-login' ),
					'error'              => __( 'Error', 'metamask-login' ),
					'noMetaMask'         => __( 'MetaMask not detected. Please install the MetaMask extension.', 'metamask-login' ),
					'signatureRejected'  => __( 'Signature request rejected.', 'metamask-login' ),
					'noAccountFound'     => __( 'No account found for this wallet address.', 'metamask-login' ),
					'rateLimitExceeded'  => __( 'Too many login attempts. Please try again later.', 'metamask-login' ),
				),
			)
		);
	}

	/**
	 * Main authentication function
	 *
	 * @since 2.0.0
	 * @param WP_User|WP_Error|null $user     User object or error.
	 * @param string                $username Username.
	 * @param string                $password Password.
	 * @return WP_User|WP_Error User object on success, error object on failure.
	 */
	public static function authenticate_metamask_user( $user, $username, $password ) {
		// If another authentication system already worked, return that user.
		if ( $user instanceof WP_User ) {
			return $user;
		}

		// Check if MetaMask login is active.
		if ( ! isset( $_POST['metamask_login'] ) || '1' !== $_POST['metamask_login'] ) {
			return $user;
		}

		// Verify nonce.
		if ( ! isset( $_POST['metamask_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['metamask_nonce'] ) ), 'metamask_login_nonce' ) ) {
			return new WP_Error( 'metamask_auth_failed', __( 'Security check failed.', 'metamask-login' ) );
		}

		// Get provided data.
		$address   = isset( $_POST['metamask_address'] ) ? sanitize_text_field( wp_unslash( $_POST['metamask_address'] ) ) : '';
		$signature = isset( $_POST['metamask_signature'] ) ? sanitize_text_field( wp_unslash( $_POST['metamask_signature'] ) ) : '';
		$message   = isset( $_POST['metamask_message'] ) ? sanitize_text_field( wp_unslash( $_POST['metamask_message'] ) ) : '';

		// Validate data.
		if ( empty( $address ) || empty( $signature ) || empty( $message ) ) {
			return new WP_Error( 'metamask_auth_failed', __( 'Missing required data for MetaMask authentication.', 'metamask-login' ) );
		}

		// Verify signature.
		$is_valid = self::verify_signature( $message, $signature, $address );

		if ( ! $is_valid ) {
			return new WP_Error( 'metamask_auth_failed', __( 'Signature verification failed.', 'metamask-login' ) );
		}

		// Find user by wallet address.
		$found_user = MetaMask_User_Profile::get_user_by_wallet_address( $address );

		if ( ! $found_user ) {
			return new WP_Error( 'metamask_auth_failed', __( 'No WordPress user is linked to this wallet address.', 'metamask-login' ) );
		}

		// Log successful authentication.
		do_action( 'metamask_login_successful', $found_user->ID, $address );

		// Return the authenticated user.
		return $found_user;
	}

	/**
	 * AJAX handler for getting nonce
	 *
	 * @since 2.0.0
	 */
	public static function ajax_get_nonce() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'metamask_login_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security verification failed.', 'metamask-login' ),
				),
				403
			);
		}

		// Check rate limit.
		if ( ! self::check_rate_limit() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Too many requests. Please try again later.', 'metamask-login' ),
				),
				429
			);
		}

		// Get address from request.
		$address = isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '';

		if ( empty( $address ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Wallet address is required.', 'metamask-login' ),
				)
			);
		}

		// Validate address format.
		if ( ! preg_match( '/^0x[a-fA-F0-9]{40}$/', $address ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid wallet address format.', 'metamask-login' ),
				)
			);
		}

		// Generate signing message.
		$message = self::generate_sign_message( $address );

		// Return the message for signing.
		wp_send_json_success(
			array(
				'message' => $message,
			)
		);
	}

	/**
	 * AJAX handler for authentication
	 *
	 * @since 2.0.0
	 */
	public static function ajax_authentication() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'metamask_login_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security verification failed.', 'metamask-login' ),
				),
				403
			);
		}

		// Check rate limit.
		if ( ! self::check_rate_limit() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Too many login attempts. Please try again later.', 'metamask-login' ),
				),
				429
			);
		}

		// Get data from request.
		$address   = isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '';
		$signature = isset( $_POST['signature'] ) ? sanitize_text_field( wp_unslash( $_POST['signature'] ) ) : '';
		$message   = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';

		// Validate data.
		if ( empty( $address ) || empty( $signature ) || empty( $message ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Missing required data for authentication.', 'metamask-login' ),
				)
			);
		}

		// Verify signature.
		$is_valid = self::verify_signature( $message, $signature, $address );

		if ( ! $is_valid ) {
			self::increment_rate_limit(); // Increment on failed attempt.
			wp_send_json_error(
				array(
					'message' => __( 'Signature verification failed.', 'metamask-login' ),
				)
			);
		}

		// Find user by wallet address.
		$user = MetaMask_User_Profile::get_user_by_wallet_address( $address );

		if ( ! $user ) {
			self::increment_rate_limit(); // Increment on failed attempt.
			wp_send_json_error(
				array(
					'message' => __( 'No WordPress user is linked to this wallet address.', 'metamask-login' ),
				)
			);
		}

		// Log the user in.
		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		do_action( 'wp_login', $user->user_login, $user );
		do_action( 'metamask_login_successful', $user->ID, $address );

		// Clear rate limit on successful login.
		self::clear_rate_limit();

		// Return success.
		wp_send_json_success(
			array(
				'message'  => __( 'Login successful!', 'metamask-login' ),
				'redirect' => apply_filters( 'metamask_login_redirect', admin_url(), $user ),
			)
		);
	}

	/**
	 * AJAX handler for verifying wallet ownership
	 *
	 * @since 2.0.0
	 */
	public static function ajax_verify_wallet() {
		// Only logged-in users can verify wallet ownership.
		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'You must be logged in to verify wallet ownership.', 'metamask-login' ),
				),
				401
			);
		}

		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'metamask_login_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security verification failed.', 'metamask-login' ),
				),
				403
			);
		}

		// Get data from request.
		$address   = isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '';
		$signature = isset( $_POST['signature'] ) ? sanitize_text_field( wp_unslash( $_POST['signature'] ) ) : '';
		$message   = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';

		// Validate data.
		if ( empty( $address ) || empty( $signature ) || empty( $message ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Missing required data for verification.', 'metamask-login' ),
				)
			);
		}

		// Verify signature.
		$is_valid = self::verify_signature( $message, $signature, $address );

		if ( ! $is_valid ) {
			wp_send_json_error(
				array(
					'message' => __( 'Signature verification failed.', 'metamask-login' ),
				)
			);
		}

		// Check if the address is already linked to another user.
		$existing_user = MetaMask_User_Profile::get_user_by_wallet_address( $address );
		if ( $existing_user && $existing_user->ID !== get_current_user_id() ) {
			wp_send_json_error(
				array(
					'message' => __( 'This wallet address is already linked to another user.', 'metamask-login' ),
				)
			);
		}

		// Return success.
		wp_send_json_success(
			array(
				'message' => __( 'Wallet ownership verified.', 'metamask-login' ),
				'address' => $address,
			)
		);
	}

	/**
	 * Generate a message for signing
	 *
	 * @since 2.0.0
	 * @param string $address Ethereum address.
	 * @return string Message to sign.
	 */
	public static function generate_sign_message( $address ) {
		$site_name = get_bloginfo( 'name' );
		$nonce     = wp_create_nonce( 'metamask_auth_' . $address . '_' . time() );
		$timestamp = current_time( 'mysql' );

		$message = sprintf(
			// translators: %1$s: Site name, %2$s: Wallet address, %3$s: Nonce, %4$s: Timestamp.
			__(
				"Sign this message to prove you own this wallet address and to log in to %1\$s\n\nThis request will not trigger a blockchain transaction or cost any gas fees.\n\nWallet address:\n%2\$s\n\nNonce: %3\$s\nTimestamp: %4\$s",
				'metamask-login'
			),
			$site_name,
			$address,
			$nonce,
			$timestamp
		);

		return apply_filters( 'metamask_login_sign_message', $message, $address );
	}

	/**
	 * Verify an Ethereum signature
	 *
	 * @since 2.0.0
	 * @param string $message   Original message that was signed.
	 * @param string $signature Signature to verify.
	 * @param string $address   Address that supposedly signed the message.
	 * @return bool True if signature is valid.
	 */
	public static function verify_signature( $message, $signature, $address ) {
		try {
			require_once plugin_dir_path( __FILE__ ) . 'utils/eth-sign-verify.php';
			$eth_sign = new Eth_Sign_Verify();
			$result   = $eth_sign->verify( $message, $signature, $address );

			// Log verification attempts (for security monitoring).
			if ( ! $result ) {
				error_log(
					sprintf(
						'MetaMask Login: Failed signature verification for address %s from IP %s',
						$address,
						self::get_client_ip()
					)
				);
			}

			return $result;
		} catch ( Exception $e ) {
			error_log( 'MetaMask signature verification error: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Check rate limit for current IP
	 *
	 * @since 2.0.0
	 * @return bool True if within rate limit, false otherwise.
	 */
	private static function check_rate_limit() {
		$ip             = self::get_client_ip();
		$transient_key  = self::RATE_LIMIT_PREFIX . md5( $ip );
		$current_count  = get_transient( $transient_key );

		if ( false === $current_count ) {
			return true;
		}

		return (int) $current_count < self::MAX_ATTEMPTS;
	}

	/**
	 * Increment rate limit counter
	 *
	 * @since 2.0.0
	 */
	private static function increment_rate_limit() {
		$ip            = self::get_client_ip();
		$transient_key = self::RATE_LIMIT_PREFIX . md5( $ip );
		$current_count = get_transient( $transient_key );

		if ( false === $current_count ) {
			set_transient( $transient_key, 1, self::RATE_LIMIT_WINDOW );
		} else {
			set_transient( $transient_key, (int) $current_count + 1, self::RATE_LIMIT_WINDOW );
		}
	}

	/**
	 * Clear rate limit for current IP
	 *
	 * @since 2.0.0
	 */
	private static function clear_rate_limit() {
		$ip            = self::get_client_ip();
		$transient_key = self::RATE_LIMIT_PREFIX . md5( $ip );
		delete_transient( $transient_key );
	}

	/**
	 * Get client IP address
	 *
	 * @since 2.0.0
	 * @return string IP address.
	 */
	private static function get_client_ip() {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP', // CloudFlare
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'HTTP_CLIENT_IP',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) ) {
				$ip_list = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );
				$ip      = trim( $ip_list[0] );

				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}
}
