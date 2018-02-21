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

	public function test_can_migrate_shipped_product() {
		$product = ProductFactory::create_shipped_product();
		$created = $this->migrate_single_product( $product );

		$this->assertInstanceOf( 'WC_Product_Simple', $created, 'Expected result to be an instance of WC_Product_Simple.' );
		$this->assert_basic_product_attributes( $product, $created );

		// price
		// regular_price
		// sale_price
		// date_on_sale_from
		// date_on_sale_to
	}

	public function test_can_migrate_variant_product() {
		$product = ProductFactory::create_variant_product();
		$created = $this->migrate_single_product( $product );

		$this->assertInstanceOf( 'WC_Product_Variable', $created, 'Expected result to be an instance of WC_Product_Variable.' );
		$this->assert_basic_product_attributes( $product, $created );

		// price
		// regular_price
		// sale_price
		// date_on_sale_from
		// date_on_sale_to
	}

	public function test_preserves_featured_products() {
		$product = ProductFactory::create_shipped_product( [
			'featured' => true,
		] );
		$created = $this->migrate_single_product( $product );

		$this->assertTrue( $created->get_featured(), 'Featured products should stay featured.' );
	}

	public function test_preserves_product_terms() {
		$product = ProductFactory::create_shipped_product();
		$cat     = $this->factory()->term->create_and_get( [
			'taxonomy' => 'shopp_category',
		] );
		$tag     = $this->factory()->term->create_and_get( [
			'taxonomy' => 'shopp_tag',
		] );

		// Assign the terms to the product.
		wp_set_object_terms( $product->id, $category->term_id, 'shopp_category' );
		wp_set_object_terms( $product->id, $tag->term_id, 'shopp_tag' );

		$created = $this->migrate_single_product( $product );
		$terms   = wp_get_object_terms( $created->get_id(), [ 'product_cat', 'product_tag'] );
	}

	/**
	 * Shortcut to execute Command::migrate_single_product() as a ReflectionMethod.
	 *
	 * @param ShoppProduct $product The product to migrate.
	 *
	 * @return WC_Product The WooCommerce representation of $product.
	 */
	protected function migrate_single_product( $product ) {
		$command = new Command();
		$method  = new ReflectionMethod( $command, 'migrate_single_product' );
		$method->setAccessible( true );

		return $method->invoke( $command, $product );
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
		$this->assertEquals(
			$shopp->status,
			get_post_status( $woo->get_id() ),
			'The post status should not change.'
		);
		$this->assertEquals(
			Helpers\str_to_bool( $shopp->featured ),
			$woo->get_featured(),
			'Product featured status does not match.'
		);
		$this->assertEquals(
			'visible',
			$woo->get_catalog_visibility(),
			'Shopp doesn\'t have the concept of hidden products, so this should be "visible".'
		);
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
		$this->assertEquals(
			$shopp->sku,
			$woo->get_sku(),
			'Product SKU does not match.'
		);
		$this->assertEquals(
			$shopp->sold,
			$woo->get_total_sales(),
			'Total sales do not match.'
		);
		// tax_status
		// tax_class
		$this->assertEquals(
			Helpers\str_to_bool( $shopp->inventory ),
			$woo->get_manage_stock(),
			'The inventory management settings do not match.'
		);
		$this->assertEquals(
			$shopp->stock,
			$woo->get_stock_quantity(),
			'The stock does not match.'
		);
		$this->assertEquals(
			$shopp->outofstock,
			'instock' !== $woo->get_stock_status(),
			'The stock status does not match.'
		);
		$this->assertEquals(
			shopp_setting_enabled( 'backorders' ),
			$woo->get_backorders(),
			'Expected backorder status to be inherited from the Shopp store.'
		);
		// weight
		// length
		// width
		// height
		$this->assertEmpty(
			$woo->get_upsell_ids(),
			'Shopp doesn\'t permit upsell relationships.'
		);
		$this->assertEmpty(
			$woo->get_cross_sell_ids(),
			'Shopp doesn\'t permit cross-sell relationships.'
		);
		// parent_id
		$this->assertEquals(
			'open' === $shopp->comment_status,
			$woo->get_reviews_allowed(),
			'The comment settings do not match.'
		);
		// purchase_note
		// attributes
		// default_attributes
		// menu_order
		// category_ids
		// tag_ids
		// shipping_class_id
		// image_id
		// gallery_image_ids
		// rating_counts
		// average_rating
		// review_count
	}
}
