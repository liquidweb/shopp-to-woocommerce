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

	public function test_can_migrate_simple_product() {
		$product = ProductFactory::create();
		$command = new Command();
		$method  = new ReflectionMethod( $command, 'migrate_single_product' );
		$method->setAccessible( true );

		$created = $method->invoke( $command, $product );

		$this->assertInstanceOf( 'WC_Product', $created );
		$this->assert_basic_product_attributes( $product, $created );
	}

	/**
	 * Compare fields that aren't specific to the product type, like "name", "description", etc.
	 *
	 * @param ShoppProduct $shopp The Shopp representation of the product.
	 * @param WC_Product   $woo   The WooCommerce version of the product.
	 */
	protected function assert_basic_product_attributes( $shopp, $woo ) {
		$this->assertEquals(
			$shopp->name,
			$woo->get_name(),
			'Product name does not match.'
		);
		$this->assertEquals(
			$shopp->slug,
			$woo->get_slug(),
			'Product slug does not match.'
		);
		$this->assertEquals(
			$shopp->post_date_gmt,
			$woo->get_date_created()->getTimestamp(),
			'Product creation timestamp does not match.'
		);
		$this->assertEquals(
			$shopp->post_modified_gmt,
			$woo->get_date_modified()->getTimestamp(),
			'Product modification timestamp does not match.'
		);
		// status
		$this->assertEquals(
			Helpers\str_to_bool( $shopp->featured ),
			$woo->get_featured(),
			'Product featured status does not match.'
		);
		// catalog_visibility
		$this->assertEquals(
			$shopp->description,
			$woo->get_description(),
			'Product description does not match.'
		);
		$this->assertEquals(
			$shopp->summary,
			$woo->get_short_description(),
			'Product short description does not match.'
		);
		// sku
		// price
		// regular_price
		// sale_price
		// date_on_sale_from
		// date_on_sale_to
		// total_sales
		// tax_status
		// tax_class
		// manage_stock
		// stock_quantity
		// stock_status
		// backorders
		// sold_individually
		// weight
		// length
		// width
		// height
		// upsell_ids
		// cross_sell_ids
		// parent_id
		// reviews_allowed
		// purchase_note
		// attributes
		// default_attributes
		// menu_order
		// virtual
		// downloadable
		// category_ids
		// tag_ids
		// shipping_class_id
		// downloads
		// image_id
		// gallery_image_ids
		// download_limit
		// download_expiry
		// rating_counts
		// average_rating
		// review_count
	}
}
