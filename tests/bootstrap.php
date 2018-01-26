<?php
/**
 * Bootstrap file for PHPUnit.
 *
 * @package LiquidWeb\ShoppToWooCommerce
 * @author  Liquid Web
 */

use Tests\Utils as Utils;

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

require $_tests_dir . '/includes/bootstrap.php';
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

/**
 * Shopp's core/library/Core.php file includes a floatvalue() function, which conflicts with
 * Hamcrest's function of the same name.
 *
 * Since Shopp's is marked as deprecated (and not used in the plugin), this will remove it to
 * prevent fatal "Cannot redeclare floatvalue()" errors.
 */
add_action( 'before_activate_shopp/Shopp.php', function () {
	shell_exec( 'sed -i "s/function floatvalue (/function __floatvalue (/g" ' . escapeshellarg( ABSPATH . 'wp-content/plugins/shopp/core/library/Core.php' ) );
} );

try {
	Utils\install_and_activate_plugin( 'shopp/Shopp.php', 'Shopp' );
	Utils\install_and_activate_plugin( 'woocommerce/woocommerce.php', 'WooCommerce' );

} catch ( ErrorException $e ) {
	echo esc_html( PHP_EOL . $e->getMessage() );
	echo PHP_EOL . PHP_EOL . "\033[0;31mUnable to proceed with tests, aborting.\033[0m";
	echo PHP_EOL;
	exit( 1 );
}
