<?php
/**
 * Tests for the product migration.
 *
 * @package LiquidWeb\ShoppToWooCommerce
 * @author  Liquid Web
 */

namespace Tests;

use ImageAsset;
use LiquidWeb\ShoppToWooCommerce\Command;
use LiquidWeb\ShoppToWooCommerce\Helpers as Helpers;
use ReflectionMethod;
use Tests\Factories\ProductFactory;

class ProductsTest extends TestCase {

	/**
	 * Listen for Shopp debug messages.
	 *
	 * @before
	 */
	public function shopp_error_listener() {
		add_action( 'shopp_error', 'Tests\Utils\print_shopp_error' );
	}

	/**
	 * Enable download_url() requests to work on WordPress core test data media.
	 *
	 * @before
	 */
	public function enable_download_url() {
		add_filter( 'pre_http_request', function ( $return, $args, $url ) {
			parse_str( wp_parse_url( $url, PHP_URL_QUERY ), $query );

			// We're not pulling an image from Shopp.
			if ( isset( $query['siid'] ) ) {
				return $return;
			}

			$image = new ImageAsset( $query['siid'] );

			copy( DIR_TESTDATA . '/images/' . $image->filename, $args['filename'] );

			return [
				'headers'  => [],
				'body'     => [],
				'response' => [
					'code' => 200,
				],
				'cookies'  => [],
			];
		}, 10, 3 );
	}

	/**
	 * Remove uploads after each test.
	 *
	 * @after
	 */
	public function remove_uploads_on_tear_down() {
		$this->remove_added_uploads();
	}

	public function test_product_factory() {
		$this->assertInstanceOf(
			'ShoppProduct',
			ProductFactory::create(),
			'The test suite\'s ProductFactory should create ShoppProduct objects.'
		);
	}

	public function test_can_migrate_shipped_product() {
		$product = ProductFactory::create_shipped_product( [
			'single' => [
				'type'  => 'Shipped',
				'price' => 50,
				'taxed' => true,
			],
		] );
		$created = $this->migrate_single_product( $product );

		$this->assertInstanceOf( 'WC_Product_Simple', $created, 'Expected result to be an instance of WC_Product_Simple.' );

		$this->assertEquals(
			$product->prices[0]->price,
			$created->get_price(),
			'Expected the price to be the same.'
		);
		$this->assertEquals(
			$product->prices[0]->price,
			$created->get_regular_price(),
			'Expected the regular price to be the same.'
		);
		$this->assertEquals(
			'taxable',
			$created->get_tax_status(),
			'Taxable products should be marked as such.'
		);
	}

	public function test_can_migrate_variant_product() {
		$product = ProductFactory::create_variant_product( [
			'specs'    => [
				'Some spec' => 'Some spec value',
			],
			'variants' => [
				'menu' => [
					'Color' => [ 'Blue', 'Red' ],
					'Size'  => [ 'L', 'XL' ],
				],
				[
					'option' => [
						'Color' => 'Blue',
						'Size'  => 'L',
					],
					'type'     => 'Shipped',
					'price'    => '20.01',
					'shipping' => [ 'flag' => false ],
				],
				[
					'option' => [
						'Color' => 'Blue',
						'Size'  => 'XL',
					],
					'type'     => 'Shipped',
					'price'    => '20.02',
					'shipping' => [ 'flag' => false ],
				],
				[
					'option' => [
						'Color' => 'Red',
						'Size'  => 'L',
					],
					'type'     => 'Shipped',
					'price'    => '20.03',
					'shipping' => [ 'flag' => false ],
				],
				[
					'option' => [
						'Color' => 'Red',
						'Size'  => 'XL',
					],
					'type'     => 'Shipped',
					'price'    => '20.04',
					'shipping' => [ 'flag' => false ],
				],
			],
		] );
		$created    = $this->migrate_single_product( $product );
		$attributes = $created->get_attributes();

		$this->assertInstanceOf( 'WC_Product_Variable', $created, 'Expected result to be an instance of WC_Product_Variable.' );
		$this->assertCount( 3, $attributes, 'Expected to see three attributes.' );

		foreach ( $attributes as $attr ) {
			if ( 'Some spec' === $attr->get_name() ) {
				$this->assertEquals( [ 'Some spec value' ], $attr->get_options() );
				$this->assertFalse( $attr->get_variation() );
			} elseif ( 'Color' === $attr->get_name() ) {
				$this->assertEquals( [ 'Blue', 'Red' ], $attr->get_options() );
				$this->assertTrue( $attr->get_variation() );
			} else {
				$this->assertEquals( [ 'L', 'XL' ], $attr->get_options() );
				$this->assertTrue( $attr->get_variation() );
			}
		}

		// Inspect the variations.
		foreach ( $created->get_children() as $index => $variation_id ) {
			$variation = wc_get_product( $variation_id );

			$this->assertInstanceOf( 'WC_Product_Variation', $variation, 'Expected result to be an instance of WC_Product_Variation.' );

			$this->assertEquals(
				$product->prices[ $index ]->price,
				$variation->get_price(),
				'Expected the price to be the same.'
			);
			$this->assertEquals(
				$product->prices[ $index ]->price,
				$variation->get_regular_price(),
				'Expected the regular price to be the same.'
			);
			$this->assertEquals(
				'taxable',
				$variation->get_tax_status(),
				'Taxable products should be marked as such.'
			);
		}
	}

	public function test_can_migrate_sale_prices() {
		$product = ProductFactory::create_shipped_product( [
			'single' => [
				'type'  => 'Shipped',
				'price' => 50,
				'taxed' => true,
				'sale'  => [
					'flag'  => true,
					'price' => 40,
				],
			],
		] );
		$created = $this->migrate_single_product( $product );

		$this->assertEquals( 40, $created->get_price() );
		$this->assertEquals( 40, $created->get_sale_price() );
	}

	public function test_preserves_featured_products() {
		$product = ProductFactory::create_shipped_product( [
			'featured' => true,
		] );
		$created = $this->migrate_single_product( $product );

		$this->assertTrue( $created->get_featured(), 'Featured products should stay featured.' );
	}

	public function test_preserves_product_terms() {
		$category = $this->factory()->term->create( [
			'taxonomy' => 'shopp_category',
		] );
		$tag      = $this->factory()->term->create( [
			'taxonomy' => 'shopp_tag',
		] );
		$product  = ProductFactory::create_shipped_product( [
			'categories' => [
				'terms' => [ $category ],
			],
			'tags'       => [
				'terms' => [ $tag ],
			],
		] );
		$created  = $this->migrate_single_product( $product );

		$terms = wp_list_pluck( wp_get_object_terms( $created->get_id(), [ 'shopp_category', 'shopp_tag' ] ), 'term_id' );

		$this->assertContains( $category, $terms, 'Expected the category to still be attached.' );
		$this->assertContains( $tag, $terms, 'Expected the term to still be attached.' );
	}

	public function test_preserves_multiple_media_objects() {
		$product = ProductFactory::create_shipped_product();
		shopp_add_product_image( $product->id, DIR_TESTDATA . '/images/2004-07-22-DSC_0007.jpg' );
		shopp_add_product_image( $product->id, DIR_TESTDATA . '/images/2004-07-22-DSC_0008.jpg' );
		$product = shopp_product( $product->id );

		$created = $this->migrate_single_product( $product );

		$this->assertContains(
			basename( current( $product->images )->filename, '.jpg'),
			basename( wp_get_attachment_url( $created->get_image_id() ) ),
			'Expected the first Shopp product image to be the post thumbnail.'
		);
		$this->assertCount(
			2,
			$created->get_gallery_image_ids(),
			'Expected the other images to be added to the WooCommerce product gallery.'
		);
	}

	public function test_can_migrate_product_attributes() {
		$product    = ProductFactory::create_shipped_product( [
			'specs' => [
				'First Name' => 'George',
				'Last Name'  => 'Harrison',
				'Instrument' => 'Guitar',
			],
		] );
		$created    = $this->migrate_single_product( $product );
		$attributes = $created->get_attributes();

		$this->assertCount( 3, $attributes, 'Expected to see three product attributes.' );
		$this->assertEquals( [ 'first-name', 'last-name', 'instrument' ], array_keys( $attributes ) );
		$this->assertEquals( 'First Name', $attributes['first-name']->get_name() );
		$this->assertEquals( [ 'George' ], $attributes['first-name']->get_options() );
		$this->assertEquals( 'Last Name', $attributes['last-name']->get_name() );
		$this->assertEquals( [ 'Harrison' ], $attributes['last-name']->get_options() );
		$this->assertEquals( 'Instrument', $attributes['instrument']->get_name() );
		$this->assertEquals( [ 'Guitar' ], $attributes['instrument']->get_options() );
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
}
