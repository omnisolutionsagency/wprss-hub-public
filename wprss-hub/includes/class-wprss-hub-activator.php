<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPRSS_Hub_Activator {

    public static function activate() {
        WPRSS_Hub_DB::create_tables();
        WPRSS_Hub_Crypto::maybe_generate_key();
    }
}
