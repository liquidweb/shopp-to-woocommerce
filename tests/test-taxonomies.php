<?php
/**
 * Tests for the product migration.
 *
 * @package LiquidWeb\ShoppToWooCommerce
 * @author  Liquid Web
 */

namespace Tests;

use LiquidWeb\ShoppToWooCommerce\Command;
use LiquidWeb\ShoppToWooCommerce\Helpers as Helpers;
use ReflectionMethod;
use Shopp;
use Tests\Factories\ProductFactory;

class TaxonomiesTest extends TestCase {

	/**
	 * @dataProvider taxonomy_provider()
	 */
	public function test_migrate_terms( $old, $new ) {
		$command = new Command();
		$terms   = $this->factory()->term->create_many( 3, array( 'taxonomy' => $old ) );

		$this->assertEquals( 3, wp_count_terms( $old ) );
		$this->assertEquals( 0, wp_count_terms( $new ) );

		$command->migrate_terms();

		$this->assertEquals( 0, wp_count_terms( $old, array( 'cache_domain' => 'test' ) ) );
		$this->assertEquals( 3, wp_count_terms( $new, array( 'cache_domain' => 'test' ) ) );
	}

	/**
	 * Map old taxonomies to new taxonomies.
	 */
	public function taxonomy_provider() {
		return [
			'Categories' => [ 'shopp_category', 'product_cat' ],
			'Tags'       => [ 'shopp_tag', 'product_tag' ],
		];
	}
}
