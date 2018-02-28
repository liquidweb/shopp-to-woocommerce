<?php
/**
 * Bootstrap the Shopp to WooCommerce WP-CLI package.
 *
 * @package LiquidWeb\ShoppToWooCommerce
 * @author  Liquid Web
 */

namespace LiquidWeb\ShoppToWooCommerce;

use WP_CLI;

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

// Load command dependencies.
require_once __DIR__ . '/src/class-command.php';
require_once __DIR__ . '/src/class-verification-exception.php';
require_once __DIR__ . '/src/helpers.php';

WP_CLI::add_command( 'shopp-to-woocommerce', Command::class );
