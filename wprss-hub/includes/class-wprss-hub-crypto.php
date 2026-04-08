<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPRSS_Hub_Crypto {

    public static function maybe_generate_key() {
        if ( get_option( WPRSS_HUB_ENCRYPTION_KEY_OPTION ) ) {
            return;
        }
        $key = sodium_crypto_secretbox_keygen();
        update_option( WPRSS_HUB_ENCRYPTION_KEY_OPTION, base64_encode( $key ) );
    }

    private static function get_key() {
        $encoded = get_option( WPRSS_HUB_ENCRYPTION_KEY_OPTION );
        if ( ! $encoded ) {
            return false;
        }
        return base64_decode( $encoded );
    }

    public static function encrypt( $plaintext ) {
        $key = self::get_key();
        if ( ! $key ) {
            return false;
        }
        $nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        $ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, $key );
        return base64_encode( $nonce . $ciphertext );
    }

    public static function decrypt( $stored ) {
        $key = self::get_key();
        if ( ! $key ) {
            return false;
        }
        $decoded    = base64_decode( $stored );
        $nonce      = substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        $ciphertext = substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

        $plaintext = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );
        if ( $plaintext === false ) {
            return false;
        }
        return $plaintext;
    }
}
