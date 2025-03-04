/**
 * MetaMask Login Add-On Admin JavaScript
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
    
    // Handle wallet connection
    async function handleConnectWallet() {
        const button = $(this);
        const statusElement = $('#metamask-connect-status');
        
        // Disable button and show loading state
        button.prop('disabled', true).html('<span class="metamask-loading"></span> Connecting...');
        statusElement.hide();
        
        try {
            // Check if MetaMask is available
            if (!isMetaMaskAvailable()) {
                statusElement.text('MetaMask not detected. Please install the MetaMask extension.').addClass('error').show();
                button.prop('disabled', false).text('Connect MetaMask');
                return;
            }
            
            // Connect to MetaMask
            const address = await connectMetaMask();
            
            // Update status
            statusElement.text('Signing...').removeClass('error').addClass('info').show();
            
            // Debug what nonce we're sending
            console.log('Sending nonce to server:', MetaMaskLoginAdmin.nonce);
            
            // Get nonce message from server
            const response = await $.ajax({
                url: MetaMaskLoginAdmin.ajaxUrl, // Use the proper URL from the localized script
                type: 'POST',
                data: {
                    action: 'metamask_get_nonce',
                    address: address,
                    nonce: MetaMaskLoginAdmin.nonce
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
                url: MetaMaskLoginAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'metamask_verify_wallet',
                    address: address,
                    signature: signature,
                    message: message,
                    nonce: MetaMaskLoginAdmin.nonce
                }
            });
            
            if (!verifyResponse.success) {
                throw new Error(verifyResponse.data.message || 'Verification failed');
            }
            
            // Connect the wallet to the user account
            const connectResponse = await $.ajax({
                url: MetaMaskLoginAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'metamask_connect_wallet',
                    address: address,
                    signature: signature,
                    message: message,
                    nonce: MetaMaskLoginAdmin.nonce
                }
            });
            
            if (!connectResponse.success) {
                throw new Error(connectResponse.data.message || 'Connection failed');
            }
            
            // Update the form field
            $('#metamask_wallet_address').val(address);
            
            // Show success message
            statusElement.text('Wallet address successfully linked to this account.').removeClass('error info').addClass('success').show();
            
            // Refresh the page after a delay
            setTimeout(function() {
                window.location.reload();
            }, 1500);
            
        } catch (error) {
            console.error('Connect wallet error:', error);
            
            // Handle different error types
            if (error.code === 4001) {
                // User rejected request
                statusElement.text('Signature request rejected.').removeClass('info success').addClass('error').show();
            } else {
                statusElement.text(error.message || 'An error occurred.').removeClass('info success').addClass('error').show();
            }
            
            // Reset button
            button.prop('disabled', false).text('Connect MetaMask');
        }
    }
    
    // Handle wallet disconnection
    async function handleDisconnectWallet() {
        const button = $(this);
        
        // Confirm disconnect
        if (!confirm(MetaMaskLoginAdmin.i18n.confirmRemove)) {
            return;
        }
        
        // Disable button and show loading
        button.prop('disabled', true).html('<span class="metamask-loading"></span> Disconnecting...');
        
        try {
            // Set a hidden field to indicate disconnection
            $('input[name="metamask_disconnect"]').val('1');
            
            // Submit the form
            button.closest('form').submit();
            
        } catch (error) {
            console.error('Disconnect wallet error:', error);
            
            // Show error
            $('#metamask-connect-status').text(error.message || 'An error occurred.').removeClass('info success').addClass('error').show();
            
            // Reset button
            button.prop('disabled', false).text('Disconnect');
        }
    }
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        // Add hidden field for disconnect action
        $('form#your-profile').append('<input type="hidden" name="metamask_disconnect" value="0">');
        
        // Connect/disconnect buttons
        $('#metamask-connect-btn').on('click', handleConnectWallet);
        $('#metamask-disconnect-btn').on('click', handleDisconnectWallet);
        
        // Handle MetaMask account changes
        if (isMetaMaskAvailable()) {
            window.ethereum.on('accountsChanged', function (accounts) {
                // If wallet is connected, show an update notification
                if ($('#metamask_wallet_address').length > 0 && $('#metamask_wallet_address').val()) {
                    $('#metamask-connect-status').text('MetaMask account changed. Please reconnect your wallet.').removeClass('error success').addClass('info').show();
                }
            });
        }
    });
    
})(jQuery);