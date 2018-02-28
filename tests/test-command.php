<?php
/**
 * Tests for the main WP-CLI command.
 *
 * @package LiquidWeb\ShoppToWooCommerce
 * @author  Liquid Web
 */

namespace Tests;

class CommandTest extends TestCase {

	/**
	 * Verify that both Shopp and WooCommerce are installed and active.
	 */
	public function test_shopp_and_woocommerce_are_active() {
		$this->assertTrue( is_plugin_active( 'shopp/Shopp.php' ), 'Shopp is not active.' );
		$this->assertTrue( class_exists( 'WooCommerce' ), 'WooCommerce is not active.' );
	}
}
