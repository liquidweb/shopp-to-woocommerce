<?php
/**
 * Tests for the main WP-CLI command.
 *
 * @package LiquidWeb\ShoppToWooCommerce
 * @author  Liquid Web
 */

namespace Tests;

use WP_UnitTestCase;

class CommandTest extends WP_UnitTestCase {

	/**
	 * Verify that both Shopp and WooCommerce are installed and active.
	 */
	public function test_shopp_and_woocommerce_are_active() {
		$this->assertTrue( is_plugin_active( 'shopp/Shopp.php' ), 'Shopp is not active.' );
		$this->assertTrue( is_plugin_active( 'woocommerce/woocommerce.php' ), 'WooCommerce is not active.' );
	}
}
