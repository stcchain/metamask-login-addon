=== MetaMask Login Add-On ===
Contributors: stcchain
Tags: metamask, web3, ethereum, crypto, wallet, login, authentication, blockchain
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure Web3 authentication plugin that enables users to connect their MetaMask wallet and login to WordPress without passwords.

== Description ==

MetaMask Login Add-On is a powerful WordPress plugin that brings Web3 authentication to your website. Allow your users to login using their MetaMask wallet instead of traditional username/password combinations.

= Key Features =

* **Passwordless Login** - Users can authenticate using their MetaMask wallet
* **Wallet Linking** - Link MetaMask addresses directly to WordPress user profiles
* **Cryptographic Verification** - Secure signature-based authentication
* **Rate Limiting** - Built-in protection against brute force attacks
* **User Profile Integration** - Seamlessly integrates with WordPress user management
* **Developer Friendly** - Extensive hooks and filters for customization
* **REST API** - Full REST API support for headless implementations
* **Shortcodes** - Easy integration with shortcodes

= Security Features =

* Ethereum signature verification using personal_sign
* Nonce-based CSRF protection
* Rate limiting to prevent brute force attacks
* IP-based request throttling
* Secure cryptographic validation

= Use Cases =

* **NFT Membership Sites** - Grant access based on wallet ownership
* **DAO Governance** - Authenticate DAO members
* **Web3 Communities** - Build Web3-native communities
* **Token-Gated Content** - Restrict content to token holders
* **DeFi Platforms** - Secure authentication for DeFi dashboards

= Shortcodes =

**[metamask_login]** - Display a MetaMask login button
* `redirect` - URL to redirect after login
* `button_text` - Custom button text
* `button_class` - Custom CSS class

**[metamask_profile]** - Display wallet profile information
* `show_address` - Show wallet address (true/false)
* `show_balance` - Show wallet balance (true/false)

= Developer Hooks =

**Filters:**
* `metamask_login_redirect` - Customize redirect URL after login
* `metamask_login_sign_message` - Customize the signature message
* `metamask_login_custom_signature_verify` - Custom signature verification
* `metamask_login_default_redirect` - Default redirect URL

**Actions:**
* `metamask_login_successful` - Fired after successful login
* `metamask_wallet_connected` - Fired when wallet is connected
* `metamask_wallet_disconnected` - Fired when wallet is disconnected
* `metamask_wallet_updated` - Fired when wallet address is updated

== Installation ==

1. Upload the `metamask-login-addon` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings under Settings > MetaMask Login
4. Add MetaMask login to your site using shortcodes or the built-in login page integration

= Manual Installation =

1. Download the plugin ZIP file
2. Go to WordPress Admin > Plugins > Add New
3. Click "Upload Plugin" and select the ZIP file
4. Click "Install Now" and then "Activate"

== Frequently Asked Questions ==

= What is MetaMask? =

MetaMask is a popular cryptocurrency wallet and gateway to blockchain applications. It's available as a browser extension for Chrome, Firefox, Brave, and Edge.

= Do users need MetaMask to use my site? =

Users only need MetaMask if they want to login using their wallet. Traditional username/password authentication continues to work alongside MetaMask login.

= Is this secure? =

Yes! The plugin uses cryptographic signature verification to ensure only the wallet owner can authenticate. All authentication requests are protected with nonces and rate limiting.

= Can I customize the login button? =

Yes! Use the shortcode attributes or WordPress hooks to customize the appearance and behavior of the login button.

= Does this work with WooCommerce? =

Yes! The plugin integrates seamlessly with WooCommerce and any other WordPress plugin that uses standard WordPress authentication.

= What blockchains are supported? =

Currently, the plugin supports Ethereum and all EVM-compatible chains (Polygon, BSC, Avalanche, etc.) through MetaMask.

= Can I link multiple wallets to one account? =

Currently, each user account can be linked to one wallet address. This ensures unique authentication.

= How do I handle signature verification in production? =

For production environments, we recommend implementing proper elliptic curve cryptography libraries or using the filter hooks to integrate with external verification services.

== Screenshots ==

1. MetaMask login button on WordPress login page
2. Wallet connection interface in user profile
3. Plugin settings page
4. Users table showing connected wallets

== Changelog ==

= 2.0.0 - 2025-11-12 =
* **Major Update** - Complete rewrite for WordPress 6.7+ compatibility
* Added: Comprehensive security improvements with proper nonce verification
* Added: Rate limiting to prevent brute force attacks
* Added: Advanced Ethereum signature verification
* Added: Admin settings page with configuration options
* Added: Activation, deactivation, and uninstall hooks
* Added: Extensive WordPress hooks and filters for developers
* Added: Better error handling and user feedback
* Improved: WordPress coding standards compliance
* Improved: PHPDoc documentation throughout
* Improved: Sanitization and validation of all inputs
* Improved: User interface and experience
* Fixed: Security vulnerabilities in nonce handling
* Fixed: Signature verification bypasses removed

= 1.0.0 - 2024-01-01 =
* Initial release
* Basic MetaMask login functionality
* User profile integration
* Simple wallet linking

== Upgrade Notice ==

= 2.0.0 =
Major security and feature update. Highly recommended for all users. Includes breaking changes - please review documentation before upgrading.

== Development ==

= Requirements =

* WordPress 6.0 or higher
* PHP 7.4 or higher
* MetaMask browser extension

= For Developers =

The plugin is designed to be developer-friendly with extensive hooks and filters. Check out the documentation at [https://stcchain.io/metamask-login](https://stcchain.io/metamask-login) for detailed developer guides.

**GitHub Repository:** [https://github.com/stcchain/metamask-login-addon](https://github.com/stcchain/metamask-login-addon)

= Contributing =

Contributions are welcome! Please submit pull requests or open issues on our GitHub repository.

== Privacy Policy ==

This plugin stores wallet addresses in the WordPress user meta table. No data is sent to external services unless you implement custom verification methods. All authentication happens between the user's browser and your WordPress installation.

= Data Collected =

* Ethereum wallet addresses (stored in wp_usermeta)
* Login attempt metadata (temporarily cached for rate limiting)

= Data Sharing =

* No data is shared with third parties by default
* All data remains within your WordPress installation

== Credits ==

Developed by STC Chain
Website: [https://stcchain.io](https://stcchain.io)

== Support ==

For support, please visit our [support forum](https://wordpress.org/support/plugin/metamask-login-addon/) or contact us through our website.
