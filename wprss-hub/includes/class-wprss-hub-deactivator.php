<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPRSS_Hub_Deactivator {

    public static function deactivate() {
        // Nothing to do on deactivation. Tables are kept intact.
        // Full cleanup happens in uninstall.php.
    }
}
