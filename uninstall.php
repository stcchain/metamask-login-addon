<?php
/**
 * Uninstall script for MetaMask Login Add-On
 *
 * This file is executed when the plugin is uninstalled via the WordPress admin.
 *
 * @package MetaMask_Login
 * @since 2.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin options.
delete_option( 'metamask_login_options' );

// Remove all user meta data for wallet addresses.
delete_metadata( 'user', 0, 'metamask_wallet_address', '', true );

// Clean up all transients.
global $wpdb;

// Delete rate limit transients.
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	WHERE option_name LIKE '_transient_metamask_rate_limit_%'
	OR option_name LIKE '_transient_timeout_metamask_rate_limit_%'
	OR option_name LIKE '_transient_metamask_login_%'
	OR option_name LIKE '_transient_timeout_metamask_login_%'"
);

// Clean up any custom database tables if they were created (currently none).
// If you added custom tables in the future, clean them up here.

// Flush rewrite rules.
flush_rewrite_rules();
