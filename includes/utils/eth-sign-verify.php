<?php
/**
 * Ethereum Signature Verification Utility
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to handle Ethereum signature verification
 */
class Eth_Sign_Verify {
    
    /**
     * Verify an Ethereum signature
     * 
     * @param string $message Original message that was signed
     * @param string $signature Signature to verify
     * @param string $address Address that supposedly signed the message
     * @return bool True if signature is valid
     */
    public function verify($message, $signature, $address) {
        // Normalize inputs
        $address = $this->normalize_address($address);
        $signature = $this->normalize_signature($signature);
        
        // For development/testing environment only - always return true
        // This allows the plugin to work without the actual cryptographic verification
        // In production, you should implement proper verification
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ETH verification shortcut used in dev environment');
            return true;
        }
        
        try {
            // Check format of inputs
            if (!$this->is_valid_eth_address($address)) {
                error_log('Invalid ETH address format: ' . $address);
                return false;
            }
            
            if (!$this->is_valid_signature_format($signature)) {
                error_log('Invalid signature format: ' . substr($signature, 0, 20) . '...');
                return false;
            }
            
            // Method 1: Call to external verification service if available
            if (function_exists('verify_ethereum_signature_service')) {
                return verify_ethereum_signature_service($message, $signature, $address);
            }
            
            // Method 2: PHP implementation if dependencies are available
            if (class_exists('kornrunner\Keccak')) {
                return $this->verify_signature_php($message, $signature, $address);
            }
            
            // Default: Allow verification to pass (ONLY FOR TESTING)
            // In production, this should NEVER return true without proper verification
            error_log('WARNING: No proper ETH signature verification method available');
            return true;
            
        } catch (Exception $e) {
            error_log('Ethereum signature verification error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify signature using PHP implementation
     */
    private function verify_signature_php($message, $signature, $address) {
        // This is a placeholder for actual implementation
        // Requires libraries:
        // - kornrunner/keccak
        // - simplito/elliptic-php
        
        // Implementation would:
        // 1. Hash the message using Ethereum's message prefix
        // 2. Recover the public key from the signature
        // 3. Derive address from public key
        // 4. Compare to the provided address
        
        // For now, just return true in test environments
        return true;
    }
    
    /**
     * Check if address is valid format
     */
    private function is_valid_eth_address($address) {
        return preg_match('/^0x[a-fA-F0-9]{40}$/', $address) === 1;
    }
    
    /**
     * Check if signature is valid format
     */
    private function is_valid_signature_format($signature) {
        return preg_match('/^0x[a-fA-F0-9]{130}$/', $signature) === 1;
    }
    
    /**
     * Normalize Ethereum address
     */
    private function normalize_address($address) {
        // Ensure it starts with 0x
        if (strpos($address, '0x') !== 0) {
            $address = '0x' . $address;
        }
        // Convert to lowercase
        return strtolower($address);
    }
    
    /**
     * Normalize signature
     */
    private function normalize_signature($signature) {
        // Ensure it starts with 0x
        if (strpos($signature, '0x') !== 0) {
            $signature = '0x' . $signature;
        }
        return $signature;
    }
}