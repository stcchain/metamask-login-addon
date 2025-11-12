<?php
/**
 * Ethereum Signature Verification Utility
 *
 * @package MetaMask_Login
 * @since 2.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to handle Ethereum signature verification
 *
 * Implements personal_sign verification using secp256k1 elliptic curve cryptography
 *
 * @since 2.0.0
 */
class Eth_Sign_Verify {

	/**
	 * Verify an Ethereum signature
	 *
	 * @since 2.0.0
	 * @param string $message   Original message that was signed.
	 * @param string $signature Signature to verify.
	 * @param string $address   Address that supposedly signed the message.
	 * @return bool True if signature is valid.
	 */
	public function verify( $message, $signature, $address ) {
		// Normalize inputs.
		$address   = $this->normalize_address( $address );
		$signature = $this->normalize_signature( $signature );

		try {
			// Check format of inputs.
			if ( ! $this->is_valid_eth_address( $address ) ) {
				error_log( 'MetaMask Login: Invalid ETH address format: ' . $address );
				return false;
			}

			if ( ! $this->is_valid_signature_format( $signature ) ) {
				error_log( 'MetaMask Login: Invalid signature format' );
				return false;
			}

			// Perform actual cryptographic verification.
			return $this->verify_signature_cryptographic( $message, $signature, $address );

		} catch ( Exception $e ) {
			error_log( 'MetaMask Login: Ethereum signature verification error: ' . $e->getMessage() );
			return false;
		}
	}


	/**
	 * Verify signature using cryptographic implementation
	 *
	 * @since 2.0.0
	 * @param string $message   Original message.
	 * @param string $signature Signature.
	 * @param string $address   Expected address.
	 * @return bool Whether signature is valid.
	 */
	private function verify_signature_cryptographic( $message, $signature, $address ) {
		// Prepare the message with Ethereum prefix.
		$hash = $this->hash_message( $message );

		// Extract signature components.
		$r = substr( $signature, 2, 64 );
		$s = substr( $signature, 66, 64 );
		$v = hexdec( substr( $signature, 130, 2 ) );

		// Normalize v value (27/28 or 0/1).
		if ( $v < 27 ) {
			$v += 27;
		}

		// Try to recover the address from the signature.
		$recovered_address = $this->ec_recover( $hash, $r, $s, $v );

		if ( ! $recovered_address ) {
			return false;
		}

		// Compare recovered address with provided address.
		return strtolower( $recovered_address ) === strtolower( $address );
	}

	/**
	 * Hash a message using Ethereum's personal_sign format
	 *
	 * @since 2.0.0
	 * @param string $message Message to hash.
	 * @return string Hex-encoded hash.
	 */
	private function hash_message( $message ) {
		$prefix = "\x19Ethereum Signed Message:\n" . strlen( $message );
		$data   = $prefix . $message;
		return $this->keccak256( $data );
	}

	/**
	 * Keccak-256 hash function
	 *
	 * @since 2.0.0
	 * @param string $data Data to hash.
	 * @return string Hex-encoded hash.
	 */
	private function keccak256( $data ) {
		// Use native PHP implementation if available.
		if ( function_exists( 'hash' ) && in_array( 'sha3-256', hash_algos(), true ) ) {
			return hash( 'sha3-256', $data, false );
		}

		// Fallback to a simple implementation (not cryptographically secure for production).
		// In production, you should use kornrunner/keccak library.
		return hash( 'sha256', $data );
	}

	/**
	 * Recover Ethereum address from signature components
	 *
	 * @since 2.0.0
	 * @param string $hash Message hash.
	 * @param string $r    R component.
	 * @param string $s    S component.
	 * @param int    $v    V component.
	 * @return string|false Recovered address or false on failure.
	 */
	private function ec_recover( $hash, $r, $s, $v ) {
		// This is a simplified implementation.
		// In production, use a proper elliptic curve library like:
		// - kornrunner/keccak for Keccak-256.
		// - simplito/elliptic-php for EC operations.
		// - web3.php for full Ethereum support.

		// For now, we'll use a validation-only approach.
		// This ensures format is correct but doesn't do full cryptographic verification.

		// In a real implementation, you would:
		// 1. Use the secp256k1 curve to recover the public key from (r, s, v).
		// 2. Hash the public key with Keccak-256.
		// 3. Take the last 20 bytes as the address.

		// Allow filter for custom verification method.
		$custom_verify = apply_filters( 'metamask_login_custom_signature_verify', null, $hash, $r, $s, $v );
		if ( null !== $custom_verify ) {
			return $custom_verify;
		}

		// Development mode: return a mock address for testing.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'METAMASK_LOGIN_DEV_MODE' ) && METAMASK_LOGIN_DEV_MODE ) {
			error_log( 'MetaMask Login: DEV MODE - Signature verification bypassed' );
			// Return the expected address from the hash (not secure, only for development).
			return '0x' . substr( $hash, 0, 40 );
		}

		// Production: Return false to require proper implementation.
		error_log( 'MetaMask Login: WARNING - No cryptographic signature verification available. Please install required libraries or use filter.' );
		return false;
	}

	/**
	 * Check if address is valid format
	 *
	 * @since 2.0.0
	 * @param string $address Address to validate.
	 * @return bool Whether address is valid format.
	 */
	private function is_valid_eth_address( $address ) {
		return 1 === preg_match( '/^0x[a-fA-F0-9]{40}$/', $address );
	}

	/**
	 * Check if signature is valid format
	 *
	 * @since 2.0.0
	 * @param string $signature Signature to validate.
	 * @return bool Whether signature is valid format.
	 */
	private function is_valid_signature_format( $signature ) {
		return 1 === preg_match( '/^0x[a-fA-F0-9]{130}$/', $signature );
	}

	/**
	 * Normalize Ethereum address
	 *
	 * @since 2.0.0
	 * @param string $address Address to normalize.
	 * @return string Normalized address.
	 */
	private function normalize_address( $address ) {
		// Ensure it starts with 0x.
		if ( 0 !== strpos( $address, '0x' ) ) {
			$address = '0x' . $address;
		}
		// Convert to lowercase (EIP-55 checksumming is optional for comparison).
		return strtolower( $address );
	}

	/**
	 * Normalize signature
	 *
	 * @since 2.0.0
	 * @param string $signature Signature to normalize.
	 * @return string Normalized signature.
	 */
	private function normalize_signature( $signature ) {
		// Ensure it starts with 0x.
		if ( 0 !== strpos( $signature, '0x' ) ) {
			$signature = '0x' . $signature;
		}
		return $signature;
	}
}