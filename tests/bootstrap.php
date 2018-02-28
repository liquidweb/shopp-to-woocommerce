<?php
/**
 * Bootstrap file for PHPUnit.
 *
 * @package LiquidWeb\ShoppToWooCommerce
 * @author  Liquid Web
 */

use Tests\Utils as Utils;
use WP_CLI\Loggers\Quiet as Logger;

$_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
$_bootstrap = dirname( __DIR__ ) . '/vendor/woocommerce/woocommerce/tests/bootstrap.php';

// Verify that Composer dependencies have been installed.
if ( ! file_exists( $_bootstrap ) ) {
	echo "\033[0;31mUnable to find the WooCommerce test bootstrap file. Have you run `composer install`?\033[0;m" . PHP_EOL;
	exit( 1 );

} elseif ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "\033[0;31mCould not find $_tests_dir/includes/functions.php, have you run `tests/bin/install-wp-tests.sh`?\033[0;m" . PHP_EOL;
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

tests_add_filter( 'muplugins_loaded', function () {
	require_once dirname( __DIR__ ) . '/command.php';
} );

// Finally, Start up the WP testing environment.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once $_bootstrap;
require_once __DIR__ . '/testcase.php';

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
	new ShoppInstallation();
	do_action( 'shopp_activate' );

	WP_CLI::set_logger( new Logger() );

} catch ( ErrorException $e ) {
	echo esc_html( PHP_EOL . $e->getMessage() );
	echo PHP_EOL . PHP_EOL . "\033[0;31mUnable to proceed with tests, aborting.\033[0m";
	echo PHP_EOL;
	exit( 1 );
}
