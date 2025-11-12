<?php
/**
 * Admin settings page
 *
 * @package MetaMask_Login
 * @since 2.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MetaMask Admin class
 *
 * Handles admin settings and configuration
 *
 * @since 2.0.0
 */
class MetaMask_Admin {

	/**
	 * Initialize the class
	 *
	 * @since 2.0.0
	 */
	public static function init() {
		// Add settings page.
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Add settings page to admin menu
	 *
	 * @since 2.0.0
	 */
	public static function add_settings_page() {
		add_options_page(
			__( 'MetaMask Login Settings', 'metamask-login' ),
			__( 'MetaMask Login', 'metamask-login' ),
			'manage_options',
			'metamask-login',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings
	 *
	 * @since 2.0.0
	 */
	public static function register_settings() {
		register_setting(
			'metamask_login_settings',
			MetaMask_Login_Addon::OPTIONS_KEY,
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
			)
		);

		// General settings section.
		add_settings_section(
			'metamask_general_settings',
			__( 'General Settings', 'metamask-login' ),
			array( __CLASS__, 'render_general_section' ),
			'metamask-login'
		);

		// Security settings section.
		add_settings_section(
			'metamask_security_settings',
			__( 'Security Settings', 'metamask-login' ),
			array( __CLASS__, 'render_security_section' ),
			'metamask-login'
		);

		// Add fields.
		self::add_settings_fields();
	}

	/**
	 * Add settings fields
	 *
	 * @since 2.0.0
	 */
	private static function add_settings_fields() {
		// Enable on login page.
		add_settings_field(
			'enable_login_page',
			__( 'Enable on Login Page', 'metamask-login' ),
			array( __CLASS__, 'render_checkbox_field' ),
			'metamask-login',
			'metamask_general_settings',
			array(
				'label_for'   => 'enable_login_page',
				'description' => __( 'Show MetaMask login button on the WordPress login page.', 'metamask-login' ),
			)
		);

		// Rate limiting enabled.
		add_settings_field(
			'rate_limit_enabled',
			__( 'Enable Rate Limiting', 'metamask-login' ),
			array( __CLASS__, 'render_checkbox_field' ),
			'metamask-login',
			'metamask_security_settings',
			array(
				'label_for'   => 'rate_limit_enabled',
				'description' => __( 'Limit the number of login attempts to prevent brute force attacks.', 'metamask-login' ),
			)
		);

		// Rate limit attempts.
		add_settings_field(
			'rate_limit_attempts',
			__( 'Max Attempts', 'metamask-login' ),
			array( __CLASS__, 'render_number_field' ),
			'metamask-login',
			'metamask_security_settings',
			array(
				'label_for'   => 'rate_limit_attempts',
				'description' => __( 'Maximum number of failed login attempts allowed.', 'metamask-login' ),
				'min'         => 1,
				'max'         => 20,
			)
		);

		// Rate limit window.
		add_settings_field(
			'rate_limit_window',
			__( 'Rate Limit Window (seconds)', 'metamask-login' ),
			array( __CLASS__, 'render_number_field' ),
			'metamask-login',
			'metamask_security_settings',
			array(
				'label_for'   => 'rate_limit_window',
				'description' => __( 'Time window for rate limiting in seconds.', 'metamask-login' ),
				'min'         => 60,
				'max'         => 3600,
			)
		);
	}

	/**
	 * Render general settings section
	 *
	 * @since 2.0.0
	 */
	public static function render_general_section() {
		echo '<p>' . esc_html__( 'Configure general MetaMask login settings.', 'metamask-login' ) . '</p>';
	}

	/**
	 * Render security settings section
	 *
	 * @since 2.0.0
	 */
	public static function render_security_section() {
		echo '<p>' . esc_html__( 'Configure security-related settings for MetaMask login.', 'metamask-login' ) . '</p>';
	}

	/**
	 * Render checkbox field
	 *
	 * @since 2.0.0
	 * @param array $args Field arguments.
	 */
	public static function render_checkbox_field( $args ) {
		$options = get_option( MetaMask_Login_Addon::OPTIONS_KEY, array() );
		$value   = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : false;
		?>
		<label>
			<input type="checkbox"
				id="<?php echo esc_attr( $args['label_for'] ); ?>"
				name="<?php echo esc_attr( MetaMask_Login_Addon::OPTIONS_KEY . '[' . $args['label_for'] . ']' ); ?>"
				value="1"
				<?php checked( $value, true ); ?> />
			<?php if ( isset( $args['description'] ) ) : ?>
				<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
			<?php endif; ?>
		</label>
		<?php
	}

	/**
	 * Render number field
	 *
	 * @since 2.0.0
	 * @param array $args Field arguments.
	 */
	public static function render_number_field( $args ) {
		$options = get_option( MetaMask_Login_Addon::OPTIONS_KEY, array() );
		$value   = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '';
		?>
		<input type="number"
			id="<?php echo esc_attr( $args['label_for'] ); ?>"
			name="<?php echo esc_attr( MetaMask_Login_Addon::OPTIONS_KEY . '[' . $args['label_for'] . ']' ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			<?php echo isset( $args['min'] ) ? 'min="' . esc_attr( $args['min'] ) . '"' : ''; ?>
			<?php echo isset( $args['max'] ) ? 'max="' . esc_attr( $args['max'] ) . '"' : ''; ?>
			class="small-text" />
		<?php if ( isset( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Sanitize settings
	 *
	 * @since 2.0.0
	 * @param array $input Settings input.
	 * @return array Sanitized settings.
	 */
	public static function sanitize_settings( $input ) {
		$sanitized = array();

		// Sanitize checkbox fields.
		$checkbox_fields = array(
			'enable_login_page',
			'enable_registration',
			'rate_limit_enabled',
			'require_verification',
			'dev_mode',
		);

		foreach ( $checkbox_fields as $field ) {
			$sanitized[ $field ] = isset( $input[ $field ] ) && '1' === $input[ $field ];
		}

		// Sanitize number fields.
		if ( isset( $input['rate_limit_attempts'] ) ) {
			$sanitized['rate_limit_attempts'] = absint( $input['rate_limit_attempts'] );
			$sanitized['rate_limit_attempts'] = max( 1, min( 20, $sanitized['rate_limit_attempts'] ) );
		}

		if ( isset( $input['rate_limit_window'] ) ) {
			$sanitized['rate_limit_window'] = absint( $input['rate_limit_window'] );
			$sanitized['rate_limit_window'] = max( 60, min( 3600, $sanitized['rate_limit_window'] ) );
		}

		// Sanitize text fields.
		if ( isset( $input['custom_sign_message'] ) ) {
			$sanitized['custom_sign_message'] = sanitize_textarea_field( $input['custom_sign_message'] );
		}

		return $sanitized;
	}

	/**
	 * Render settings page
	 *
	 * @since 2.0.0
	 */
	public static function render_settings_page() {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Show success message if settings saved.
		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error(
				'metamask_login_messages',
				'metamask_login_message',
				__( 'Settings saved successfully.', 'metamask-login' ),
				'success'
			);
		}

		settings_errors( 'metamask_login_messages' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'metamask_login_settings' );
				do_settings_sections( 'metamask-login' );
				submit_button( __( 'Save Settings', 'metamask-login' ) );
				?>
			</form>

			<div class="metamask-login-info-box">
				<h2><?php esc_html_e( 'Usage Instructions', 'metamask-login' ); ?></h2>
				<h3><?php esc_html_e( 'Shortcodes', 'metamask-login' ); ?></h3>
				<p><strong>[metamask_login]</strong> - <?php esc_html_e( 'Displays a MetaMask login button.', 'metamask-login' ); ?></p>
				<p><strong>[metamask_profile]</strong> - <?php esc_html_e( 'Displays wallet profile information for logged-in users.', 'metamask-login' ); ?></p>

				<h3><?php esc_html_e( 'Developer Filters', 'metamask-login' ); ?></h3>
				<ul>
					<li><code>metamask_login_redirect</code> - <?php esc_html_e( 'Filter the redirect URL after successful login.', 'metamask-login' ); ?></li>
					<li><code>metamask_login_sign_message</code> - <?php esc_html_e( 'Filter the message users sign to authenticate.', 'metamask-login' ); ?></li>
					<li><code>metamask_login_custom_signature_verify</code> - <?php esc_html_e( 'Provide custom signature verification logic.', 'metamask-login' ); ?></li>
				</ul>

				<h3><?php esc_html_e( 'Developer Actions', 'metamask-login' ); ?></h3>
				<ul>
					<li><code>metamask_login_successful</code> - <?php esc_html_e( 'Fired after successful login.', 'metamask-login' ); ?></li>
					<li><code>metamask_wallet_connected</code> - <?php esc_html_e( 'Fired when wallet is connected to user account.', 'metamask-login' ); ?></li>
					<li><code>metamask_wallet_disconnected</code> - <?php esc_html_e( 'Fired when wallet is disconnected.', 'metamask-login' ); ?></li>
				</ul>
			</div>
		</div>
		<style>
			.metamask-login-info-box {
				background: #fff;
				border: 1px solid #ccd0d4;
				border-left: 4px solid #2271b1;
				padding: 20px;
				margin-top: 30px;
			}
			.metamask-login-info-box h2 {
				margin-top: 0;
			}
			.metamask-login-info-box code {
				background: #f0f0f1;
				padding: 2px 6px;
				border-radius: 3px;
			}
		</style>
		<?php
	}
}
