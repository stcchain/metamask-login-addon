# MetaMask Login Add-On

Integration for MetaMask wallet authentication directly with WordPress user profiles.

## Description

This plugin provides a seamless integration between MetaMask wallets and WordPress user accounts. Unlike other solutions that use separate tables, this plugin directly integrates wallet addresses into the WordPress user profile system.

### Key Features

- Connect MetaMask wallets directly to WordPress user accounts
- Login using MetaMask wallet instead of password
- Shortcodes for login and wallet profile display
- Integration with WordPress login form
- Full user profile integration
- Secure cryptographic verification

## Installation

1. Upload the `metamask-login-addon` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure plugin settings as needed

## Usage

### Login with MetaMask

Users with linked wallets can login using their MetaMask wallet instead of a username/password combination. The plugin adds a "Login with MetaMask" button to the WordPress login page.

### Link a Wallet to Your Account

Users can link their MetaMask wallet to their WordPress account through their user profile.

### Shortcodes

The plugin provides several shortcodes for easy integration:

#### MetaMask Login Button
```
[metamask_login]
```

Options:
- `redirect` - URL to redirect after login
- `button_text` - Custom text for the login button

#### User Wallet Profile
```
[metamask_profile]
```

Options:
- `show_address` - Show wallet address (true/false)
- `show_balance` - Show wallet balance (true/false)

## Security

This plugin implements secure cryptographic verification of Ethereum signatures to ensure only the rightful owner of a wallet can use it for authentication.

## Privacy

We do not collect any personal data beyond what is necessary for the functionality of the plugin. Wallet addresses are stored in the standard WordPress user meta table.

## Developer Information

### Hooks & Filters

The plugin provides several hooks and filters for developers to extend its functionality:

- `metamask_login_redirect` - Filter to modify the redirect URL after login
- `metamask_login_message` - Filter to modify the sign message
- `metamask_login_user` - Action triggered after successful login

### PHP Integration

```php
// Check if user has MetaMask wallet
$wallet_address = get_user_meta($user_id, MetaMask_Login_Addon::USER_META_KEY, true);

// Get user by wallet address
$user = MetaMask_User_Profile::get_user_by_wallet_address($address);
```

## Credits

Developed by STC Chain
Version: 1.0.0

## License

This plugin is licensed under the GPL v2 or later.