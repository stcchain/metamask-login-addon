/**
 * MetaMask Login Add-On JavaScript
 */
(function($) {
    'use strict';
    
    // Check if MetaMask is available
    function isMetaMaskAvailable() {
        return typeof window.ethereum !== 'undefined';
    }
    
    // Connect to MetaMask
    async function connectMetaMask() {
        try {
            // Request account access
            const accounts = await window.ethereum.request({
                method: 'eth_requestAccounts'
            });
            
            // Return the first account
            return accounts[0];
        } catch (error) {
            console.error('MetaMask connection error:', error);
            throw error;
        }
    }
    
    // Sign a message with MetaMask
    async function signMessage(address, message) {
        try {
            // Request personal signature
            const signature = await window.ethereum.request({
                method: 'personal_sign',
                params: [message, address]
            });
            
            return signature;
        } catch (error) {
            console.error('MetaMask signing error:', error);
            throw error;
        }
    }
    
    // Display status message
    function showStatus(message, type, selector) {
        const statusElement = $(selector || '#metamask-login-status');
        statusElement.removeClass('error success info').addClass(type);
        statusElement.text(message).show();
        
        // For login forms, also handle the regular WordPress message area
        if (selector === '#login_error' || statusElement.attr('id') === 'login_error') {
            if (type === 'error') {
                if ($('#login_error').length === 0) {
                    $('#loginform').before('<div id="login_error">' + message + '</div>');
                } else {
                    $('#login_error').html(message).show();
                }
            } else {
                $('#login_error').hide();
            }
        }
    }
    
    // Handle login process
    async function handleLogin() {
        const button = $(this);
        const i18n = MetaMaskLogin.i18n;
        
        // Disable button and show loading state
        button.prop('disabled', true).html('<span class="metamask-loading"></span> ' + i18n.connecting);
        
        try {
            // Check if MetaMask is available
            if (!isMetaMaskAvailable()) {
                showStatus(i18n.noMetaMask, 'error');
                button.prop('disabled', false).text(i18n.connectWallet);
                return;
            }
            
            // Connect to MetaMask
            const address = await connectMetaMask();
            
            showStatus(i18n.signing, 'info');
            
            // Get nonce message from server
            const response = await $.ajax({
                url: MetaMaskLogin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'metamask_get_nonce',
                    address: address,
                    nonce: MetaMaskLogin.nonce
                }
            });
            
            if (!response.success) {
                throw new Error(response.data.message || 'Error getting nonce');
            }
            
            // Sign the message
            const message = response.data.message;
            const signature = await signMessage(address, message);
            
            // Authenticate with the server
            const authResponse = await $.ajax({
                url: MetaMaskLogin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'metamask_authentication',
                    address: address,
                    signature: signature,
                    message: message,
                    nonce: MetaMaskLogin.nonce
                }
            });
            
            if (!authResponse.success) {
                throw new Error(authResponse.data.message || 'Authentication failed');
            }
            
            showStatus(i18n.success, 'success');
            
            // Redirect after successful login
            setTimeout(function() {
                window.location.href = authResponse.data.redirect || MetaMaskLogin.loginRedirect;
            }, 1000);
            
        } catch (error) {
            console.error('Login error:', error);
            
            // Handle different error types
            if (error.code === 4001) {
                // User rejected request
                showStatus(i18n.signatureRejected, 'error');
            } else {
                showStatus(error.message || i18n.error, 'error');
            }
            
            // Reset button
            button.prop('disabled', false).text(i18n.connectWallet);
        }
    }
    
    // Handle wallet connection for profile pages
    async function handleConnectWallet() {
        const button = $(this);
        const statusElement = $('#metamask-connect-status');
        const i18n = MetaMaskLogin.i18n;
        
        // Disable button and show loading state
        button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt"></span> ' + i18n.connecting);
        statusElement.hide();
        
        try {
            // Check if MetaMask is available
            if (!isMetaMaskAvailable()) {
                statusElement.text(i18n.noMetaMask).addClass('error').show();
                button.prop('disabled', false).html('<span class="dashicons dashicons-admin-plugins"></span> ' + i18n.connectWallet);
                return;
            }
            
            // Connect to MetaMask
            const address = await connectMetaMask();
            
            // Update status
            statusElement.text(i18n.signing).removeClass('error').addClass('info').show();
            
            // Get nonce message from server
            const response = await $.ajax({
                url: MetaMaskLogin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'metamask_get_nonce',
                    address: address,
                    nonce: MetaMaskLogin.nonce
                }
            });
            
            if (!response.success) {
                throw new Error(response.data.message || 'Error getting nonce');
            }
            
            // Sign the message
            const message = response.data.message;
            const signature = await signMessage(address, message);
            
            // Verify wallet ownership
            const verifyResponse = await $.ajax({
                url: MetaMaskLogin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'metamask_verify_wallet',
                    address: address,
                    signature: signature,
                    message: message,
                    nonce: MetaMaskLogin.nonce
                }
            });
            
            if (!verifyResponse.success) {
                throw new Error(verifyResponse.data.message || 'Verification failed');
            }
            
            // Connect the wallet to the user account
            const connectResponse = await $.ajax({
                url: MetaMaskLogin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'metamask_connect_wallet',
                    address: address,
                    signature: signature,
                    message: message,
                    nonce: MetaMaskLogin.nonce
                }
            });
            
            if (!connectResponse.success) {
                throw new Error(connectResponse.data.message || 'Connection failed');
            }
            
            // Update status and refresh
            statusElement.text(i18n.addressLinked).removeClass('error info').addClass('success').show();
            
            // Refresh the page after a delay
            setTimeout(function() {
                window.location.reload();
            }, 1500);
            
        } catch (error) {
            console.error('Connect wallet error:', error);
            
            // Handle different error types
            if (error.code === 4001) {
                // User rejected request
                statusElement.text(i18n.signatureRejected).removeClass('info success').addClass('error').show();
            } else {
                statusElement.text(error.message || i18n.error).removeClass('info success').addClass('error').show();
            }
            
            // Reset button
            button.prop('disabled', false).html('<span class="dashicons dashicons-admin-plugins"></span> ' + i18n.connectWallet);
        }
    }
    
    // Handle wallet disconnection for profile pages
    async function handleDisconnectWallet() {
        const button = $(this);
        const i18n = MetaMaskLogin.i18n;
        
        // Confirm disconnect
        if (!confirm(i18n.confirmAddress)) {
            return;
        }
        
        // Disable button and show loading
        button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt"></span> ' + i18n.disconnecting);
        
        try {
            // Disconnect the wallet from the user account
            const response = await $.ajax({
                url: MetaMaskLogin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'metamask_disconnect_wallet',
                    nonce: MetaMaskLogin.nonce
                }
            });
            
            if (!response.success) {
                throw new Error(response.data.message || 'Disconnection failed');
            }
            
            // Refresh the page after successful disconnection
            window.location.reload();
            
        } catch (error) {
            console.error('Disconnect wallet error:', error);
            
            // Show error
            $('#metamask-connect-status').text(error.message || i18n.error).removeClass('info success').addClass('error').show();
            
            // Reset button
            button.prop('disabled', false).text(i18n.disconnect);
        }
    }
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        // Login page
        $('#metamask-login-button').on('click', handleLogin);
        
        // Profile page
        $('#metamask-connect-btn').on('click', handleConnectWallet);
        $('#metamask-disconnect-btn').on('click', handleDisconnectWallet);
        
        // Shortcode buttons
        $('.metamask-login-shortcode-btn').on('click', handleLogin);
        $('.metamask-connect-shortcode-btn').on('click', handleConnectWallet);
        $('.metamask-disconnect-shortcode-btn').on('click', handleDisconnectWallet);
        
        // Handle MetaMask account changes
        if (isMetaMaskAvailable()) {
            window.ethereum.on('accountsChanged', function (accounts) {
                // If on a profile page and the address is connected, show an update notification
                if ($('#metamask_wallet_address').length > 0 && $('#metamask_wallet_address').val()) {
                    $('#metamask-connect-status').text(i18n.accountChanged).removeClass('error success').addClass('info').show();
                }
            });
        }
    });
    
})(jQuery);