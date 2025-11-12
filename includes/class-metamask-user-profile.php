<?php
/**
 * User profile integration for MetaMask wallet addresses
 *
 * @package MetaMask_Login
 * @since 2.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MetaMask User Profile class
 *
 * Handles integration with WordPress user profile
 *
 * @since 2.0.0
 */
class MetaMask_User_Profile {

	/**
	 * Initialize the class
	 *
	 * @since 2.0.0
	 */
	public static function init() {
		// Add MetaMask field to user profile.
		add_action( 'show_user_profile', array( __CLASS__, 'add_metamask_field' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'add_metamask_field' ) );

		// Update MetaMask field in user profile.
		add_action( 'personal_options_update', array( __CLASS__, 'save_metamask_field' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_metamask_field' ) );

		// Add custom column to users table in admin.
		add_filter( 'manage_users_columns', array( __CLASS__, 'add_metamask_column' ) );
		add_filter( 'manage_users_custom_column', array( __CLASS__, 'display_metamask_column' ), 10, 3 );

		// AJAX handlers for connecting/disconnecting wallet.
		add_action( 'wp_ajax_metamask_connect_wallet', array( __CLASS__, 'connect_wallet_ajax' ) );
		add_action( 'wp_ajax_metamask_disconnect_wallet', array( __CLASS__, 'disconnect_wallet_ajax' ) );
	}

	/**
	 * Add MetaMask wallet field to user profile
	 *
	 * @since 2.0.0
	 * @param WP_User $user User object.
	 */
	public static function add_metamask_field( $user ) {
		// Get current wallet address if any.
		$wallet_address = get_user_meta( $user->ID, MetaMask_Login_Addon::USER_META_KEY, true );

		// Output field.
		?>
		<h2><?php esc_html_e( 'MetaMask Wallet', 'metamask-login' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="metamask_wallet_address"><?php esc_html_e( 'Wallet Address', 'metamask-login' ); ?></label></th>
				<td>
					<?php if ( empty( $wallet_address ) ) : ?>
						<div class="metamask-address-empty">
							<p class="description"><?php esc_html_e( 'No MetaMask wallet address linked to this account.', 'metamask-login' ); ?></p>
							<?php if ( get_current_user_id() === $user->ID ) : ?>
								<button type="button" id="metamask-connect-btn" class="button button-secondary">
									<?php esc_html_e( 'Connect MetaMask', 'metamask-login' ); ?>
								</button>
								<span id="metamask-connect-status" class="metamask-status-message" style="display: none; margin-left: 10px;"></span>
							<?php endif; ?>
						</div>
					<?php else : ?>
						<div class="metamask-address-connected">
							<input type="text" name="metamask_wallet_address" id="metamask_wallet_address"
								value="<?php echo esc_attr( $wallet_address ); ?>" class="regular-text code" readonly />
							<p class="description">
								<?php esc_html_e( 'This wallet address is linked to your account and can be used to log in.', 'metamask-login' ); ?>
							</p>
							<?php if ( get_current_user_id() === $user->ID || current_user_can( 'edit_users' ) ) : ?>
								<button type="button" id="metamask-disconnect-btn" class="button button-secondary">
									<?php esc_html_e( 'Disconnect Wallet', 'metamask-login' ); ?>
								</button>
								<span id="metamask-disconnect-status" class="metamask-status-message" style="display: none; margin-left: 10px;"></span>
							<?php endif; ?>
						</div>
					<?php endif; ?>
					<?php wp_nonce_field( 'metamask_address_update', 'metamask_address_nonce' ); ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save MetaMask wallet field from user profile
	 *
	 * @since 2.0.0
	 * @param int $user_id User ID.
	 * @return bool True on success, false on failure.
	 */
	public static function save_metamask_field( $user_id ) {
		// Check permissions.
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		// Verify nonce.
		if ( ! isset( $_POST['metamask_address_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['metamask_address_nonce'] ) ), 'metamask_address_update' ) ) {
			return false;
		}

		// If disconnecting, delete the meta.
		if ( isset( $_POST['metamask_disconnect'] ) && '1' === $_POST['metamask_disconnect'] ) {
			delete_user_meta( $user_id, MetaMask_Login_Addon::USER_META_KEY );
			do_action( 'metamask_wallet_disconnected', $user_id );
			return true;
		}

		// If the field is set, update it.
		if ( isset( $_POST['metamask_wallet_address'] ) ) {
			$address = sanitize_text_field( wp_unslash( $_POST['metamask_wallet_address'] ) );

			// Validate Ethereum address (0x followed by 40 hex characters).
			if ( empty( $address ) || preg_match( '/^0x[a-fA-F0-9]{40}$/', $address ) ) {
				$old_address = get_user_meta( $user_id, MetaMask_Login_Addon::USER_META_KEY, true );
				update_user_meta( $user_id, MetaMask_Login_Addon::USER_META_KEY, $address );

				if ( $old_address !== $address ) {
					do_action( 'metamask_wallet_updated', $user_id, $address, $old_address );
				}
			}
		}

		return true;
	}

	/**
	 * Add MetaMask column to users table
	 *
	 * @since 2.0.0
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public static function add_metamask_column( $columns ) {
		$columns['metamask_address'] = __( 'MetaMask Wallet', 'metamask-login' );
		return $columns;
	}

	/**
	 * Display MetaMask column content
	 *
	 * @since 2.0.0
	 * @param string $value       Value to display.
	 * @param string $column_name Column name.
	 * @param int    $user_id     User ID.
	 * @return string Column content.
	 */
	public static function display_metamask_column( $value, $column_name, $user_id ) {
		if ( 'metamask_address' !== $column_name ) {
			return $value;
		}

		$wallet_address = get_user_meta( $user_id, MetaMask_Login_Addon::USER_META_KEY, true );

		if ( empty( $wallet_address ) ) {
			return '<span class="dashicons dashicons-minus" title="' . esc_attr__( 'Not connected', 'metamask-login' ) . '"></span>';
		}

		// Format address for display (first 6 and last 4 characters).
		$formatted_address = substr( $wallet_address, 0, 6 ) . '...' . substr( $wallet_address, -4 );

		return '<code class="metamask-address" title="' . esc_attr( $wallet_address ) . '">' . esc_html( $formatted_address ) . '</code>';
	}

	/**
	 * AJAX handler for connecting a wallet
	 *
	 * @since 2.0.0
	 */
	public static function connect_wallet_ajax() {
		// Verify user is logged in.
		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'You must be logged in to perform this action.', 'metamask-login' ),
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

		// Get current user ID.
		$user_id = get_current_user_id();

		// Get the address.
		$address = isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '';

		// Validate the address format.
		if ( empty( $address ) || ! preg_match( '/^0x[a-fA-F0-9]{40}$/', $address ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid wallet address format.', 'metamask-login' ),
				)
			);
		}

		// Check if address is already used by another user.
		$existing_user = self::get_user_by_wallet_address( $address );
		if ( $existing_user && $existing_user->ID !== $user_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'This wallet address is already linked to another user.', 'metamask-login' ),
				),
				409
			);
		}

		// Verify the signature if provided.
		if ( isset( $_POST['signature'] ) && isset( $_POST['message'] ) ) {
			$signature = sanitize_text_field( wp_unslash( $_POST['signature'] ) );
			$message   = sanitize_text_field( wp_unslash( $_POST['message'] ) );

			// Verify the signature using the Authentication class.
			$is_valid = MetaMask_Authentication::verify_signature( $message, $signature, $address );

			if ( ! $is_valid ) {
				wp_send_json_error(
					array(
						'message' => __( 'Signature verification failed.', 'metamask-login' ),
					)
				);
			}
		}

		// Save the address to user meta.
		$old_address = get_user_meta( $user_id, MetaMask_Login_Addon::USER_META_KEY, true );
		update_user_meta( $user_id, MetaMask_Login_Addon::USER_META_KEY, $address );

		// Fire action hook.
		do_action( 'metamask_wallet_connected', $user_id, $address, $old_address );

		// Return success.
		wp_send_json_success(
			array(
				'message' => __( 'Wallet address successfully linked to your account.', 'metamask-login' ),
				'address' => $address,
			)
		);
	}

	/**
	 * AJAX handler for disconnecting a wallet
	 *
	 * @since 2.0.0
	 */
	public static function disconnect_wallet_ajax() {
		// Verify user is logged in.
		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'You must be logged in to perform this action.', 'metamask-login' ),
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

		// Get current user ID.
		$user_id = get_current_user_id();

		// Get the old address before deleting.
		$old_address = get_user_meta( $user_id, MetaMask_Login_Addon::USER_META_KEY, true );

		// Delete the address from user meta.
		delete_user_meta( $user_id, MetaMask_Login_Addon::USER_META_KEY );

		// Fire action hook.
		do_action( 'metamask_wallet_disconnected', $user_id, $old_address );

		// Return success.
		wp_send_json_success(
			array(
				'message' => __( 'Wallet address removed from your account.', 'metamask-login' ),
			)
		);
	}

	/**
	 * Get user by wallet address
	 *
	 * @since 2.0.0
	 * @param string $address Wallet address.
	 * @return WP_User|false User object if found, false otherwise.
	 */
	public static function get_user_by_wallet_address( $address ) {
		// Normalize address for comparison.
		$address = strtolower( $address );

		$users = get_users(
			array(
				'meta_key'   => MetaMask_Login_Addon::USER_META_KEY,
				'meta_value' => $address,
				'number'     => 1,
				'fields'     => 'all',
			)
		);

		if ( ! empty( $users ) ) {
			return $users[0];
		}

		// Also try case-insensitive search.
		global $wpdb;
		$user_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta}
				WHERE meta_key = %s AND LOWER(meta_value) = %s LIMIT 1",
				MetaMask_Login_Addon::USER_META_KEY,
				$address
			)
		);

		if ( $user_id ) {
			return get_user_by( 'id', $user_id );
		}

		return false;
	}
}
