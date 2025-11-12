# MetaMask Login Add-On

🚀 **Version 2.0.0** - Secure Web3 authentication for WordPress

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPLv2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

A powerful WordPress plugin that enables passwordless authentication using MetaMask wallets. Built with security, performance, and developer experience in mind.

## 🌟 Features

### Core Functionality
- **Passwordless Authentication** - Login with MetaMask wallet signatures
- **Direct Profile Integration** - Wallet addresses stored in native WordPress user meta
- **Login Page Integration** - Automatic MetaMask button on wp-login.php
- **Shortcode Support** - Easy integration anywhere with `[metamask_login]` and `[metamask_profile]`
- **REST API** - Full REST API for headless WordPress implementations

### Security
- ✅ **Cryptographic Signature Verification** - Ethereum personal_sign verification
- ✅ **Rate Limiting** - Built-in brute force protection
- ✅ **Nonce Protection** - CSRF protection on all AJAX requests
- ✅ **Input Validation** - Comprehensive sanitization and validation
- ✅ **WordPress Standards** - Follows WordPress coding standards and security best practices

### Developer Features
- 🎣 **Extensive Hooks & Filters** - Customize every aspect of the plugin
- 📚 **Well Documented** - Comprehensive inline documentation
- 🔧 **Modular Architecture** - Clean, maintainable code structure
- 🧪 **Production Ready** - Built for WordPress 6.7+ with PHP 7.4+ support

## 📦 Installation

### From WordPress Admin
1. Navigate to **Plugins > Add New**
2. Click **Upload Plugin**
3. Choose the plugin ZIP file
4. Click **Install Now** and then **Activate**
5. Go to **Settings > MetaMask Login** to configure

### Manual Installation
1. Upload the `metamask-login-addon` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu
3. Configure settings under **Settings > MetaMask Login**

### Requirements
- WordPress 6.0 or higher
- PHP 7.4 or higher
- HTTPS enabled (required for MetaMask)
- Modern web browser with MetaMask extension

## 🚀 Quick Start

### For Users
1. **Link Your Wallet**: Go to your user profile and click "Connect MetaMask"
2. **Approve in MetaMask**: Sign the verification message
3. **Login**: Use the "Login with MetaMask" button on the login page

### For Developers
```php
// Check if user has a linked wallet
$wallet_address = get_user_meta($user_id, 'metamask_wallet_address', true);

// Get user by wallet address
$user = MetaMask_User_Profile::get_user_by_wallet_address($address);

// Customize redirect after login
add_filter('metamask_login_redirect', function($redirect_url, $user) {
    return home_url('/dashboard');
}, 10, 2);
```

## 📖 Usage

### Shortcodes

#### Login Button
Display a MetaMask login button anywhere:
```
[metamask_login]
[metamask_login redirect="/my-account" button_text="Sign in with Web3"]
[metamask_login button_class="custom-btn-class"]
```

**Attributes:**
- `redirect` - URL to redirect after successful login
- `button_text` - Custom text for the button (default: "Connect with MetaMask")
- `button_class` - Custom CSS class for styling

#### Wallet Profile
Display wallet information for logged-in users:
```
[metamask_profile]
[metamask_profile show_address="true" show_balance="false"]
```

**Attributes:**
- `show_address` - Display wallet address (default: true)
- `show_balance` - Display wallet balance (default: false)

### Login Page Integration

The plugin automatically adds a MetaMask login button to the WordPress login page. You can disable this in **Settings > MetaMask Login**.

## 🔧 Configuration

Navigate to **Settings > MetaMask Login** to configure:

### General Settings
- **Enable on Login Page** - Show MetaMask button on wp-login.php
- **Enable Registration** - Allow new user registration via MetaMask

### Security Settings
- **Enable Rate Limiting** - Protect against brute force attacks
- **Max Attempts** - Maximum failed login attempts (default: 5)
- **Rate Limit Window** - Time window in seconds (default: 300)

## 🔒 Security

### Signature Verification
- Uses Ethereum `personal_sign` standard
- Cryptographic verification of wallet ownership
- Time-based nonces prevent replay attacks

### Rate Limiting
- IP-based request throttling
- Configurable attempt limits
- Automatic lockout on suspicious activity

### Best Practices
1. **Always use HTTPS** - MetaMask requires secure connections
2. **Keep WordPress Updated** - Use latest WordPress version
3. **Monitor Login Attempts** - Check for unusual activity
4. **Backup Regularly** - Maintain regular backups

## 🛠️ Advanced Usage

### Custom Signature Verification

Implement custom signature verification:

```php
add_filter('metamask_login_custom_signature_verify', function($result, $hash, $r, $s, $v) {
    // Your custom verification logic
    // Return the recovered address or false
    return $recovered_address;
}, 10, 5);
```

### Customize Sign Message

```php
add_filter('metamask_login_sign_message', function($message, $address) {
    return "Custom sign message for: " . $address;
}, 10, 2);
```

### After Login Actions

```php
// Log successful MetaMask logins
add_action('metamask_login_successful', function($user_id, $wallet_address) {
    error_log("User {$user_id} logged in with wallet {$wallet_address}");
}, 10, 2);

// Track wallet connections
add_action('metamask_wallet_connected', function($user_id, $address, $old_address) {
    // Send notification, update records, etc.
}, 10, 3);
```

## 🔌 REST API

### Endpoints

**Get Nonce**
```
POST /wp-json/metamask-login/v1/nonce
Body: { "address": "0x..." }
```

**Authenticate**
```
POST /wp-json/metamask-login/v1/auth
Body: {
    "address": "0x...",
    "signature": "0x...",
    "message": "..."
}
```

**Link Wallet** (requires authentication)
```
POST /wp-json/metamask-login/v1/link
Body: {
    "address": "0x...",
    "signature": "0x...",
    "message": "..."
}
```

**Unlink Wallet** (requires authentication)
```
POST /wp-json/metamask-login/v1/unlink
```

## 📚 Hooks & Filters

### Filters

| Filter | Description | Parameters |
|--------|-------------|------------|
| `metamask_login_redirect` | Customize redirect URL after login | `$url`, `$user` |
| `metamask_login_sign_message` | Customize signature message | `$message`, `$address` |
| `metamask_login_custom_signature_verify` | Custom signature verification | `$result`, `$hash`, `$r`, `$s`, `$v` |
| `metamask_login_default_redirect` | Default redirect destination | `$url` |

### Actions

| Action | Description | Parameters |
|--------|-------------|------------|
| `metamask_login_successful` | After successful login | `$user_id`, `$address` |
| `metamask_wallet_connected` | When wallet is linked | `$user_id`, `$address`, `$old_address` |
| `metamask_wallet_disconnected` | When wallet is unlinked | `$user_id`, `$old_address` |
| `metamask_wallet_updated` | When wallet is updated | `$user_id`, `$new_address`, `$old_address` |

## 🐛 Troubleshooting

### MetaMask Not Detected
- Ensure MetaMask extension is installed
- Check browser compatibility
- Verify site is using HTTPS

### Signature Verification Fails
- Check server time is synchronized
- Verify nonce generation
- Enable debug logging

### Rate Limit Errors
- Clear rate limit transients
- Adjust rate limit settings
- Check IP detection

## 🤝 Contributing

Contributions are welcome! Please:
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## 📄 License

This plugin is licensed under GPL v2 or later.

```
Copyright (C) 2025 STC Chain

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
```

## 👨‍💻 Developer Information

**Author:** STC Chain
**Website:** [https://stcchain.io](https://stcchain.io)
**Version:** 2.0.0
**License:** GPL v2 or later

### Support

- **Documentation:** [https://stcchain.io/metamask-login](https://stcchain.io/metamask-login)
- **Issues:** Please report bugs on GitHub
- **Questions:** Visit WordPress support forums

---

Made with ❤️ by [STC Chain](https://stcchain.io)