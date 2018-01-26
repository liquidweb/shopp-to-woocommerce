<?php
/**
 * Tests for the product migration.
 *
 * @package LiquidWeb\ShoppToWooCommerce
 * @author  Liquid Web
 */

namespace Tests;

use Tests\Factories\ProductFactory;
use WP_UnitTestCase;

class ProductsTest extends WP_UnitTestCase {

	/**
	 * Listen for Shopp debug messages.
	 *
	 * @before
	 */
	public function shopp_error_listener() {
		add_action( 'shopp_error', 'Tests\Utils\print_shopp_error' );
	}

	public function test_product_factory() {
		$this->assertInstanceOf(
			'ShoppProduct',
			ProductFactory::create(),
			'The test suite\'s ProductFactory should create ShoppProduct objects.'
		);
	}
}
