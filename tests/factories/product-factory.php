<?php
/**
 * Factory for generating new products.
 *
 * @package LiquidWeb\ShoppToWooCommerce
 * @author  Liquid Web
 */

namespace Tests\Factories;

use Faker\Factory as Faker;

class ProductFactory {

	protected static $faker;

	/**
	 * Create a new Shopp product, merging the provided arguments with generated defaults.
	 *
	 * @param array $args Optional. Product arguments. Defaults will be generated for anything
	 *                    not provided. @see _validate_product_data() within shopp/api/products.php
	 *                    for a full explanation of options.
	 *
	 * @return ShoppProduct
	 */
	public static function create( $args = [] ) {
		$faker = self::get_faker();
		$args  = wp_parse_args( $args, [
			'name'        => $faker->text,
			'slug'        => $faker->slug,
			'description' => $faker->paragraphs( 2, true ),
			'summary'     => $faker->paragraph,
			'featured'    => false,
			'packaging'   => true,
			'publish'     => [
				'flag' => true,
			],
			'categories'  => [],
			'tags'        => [],
			'terms'       => [],
			'specs'       => [
				'Some spec' => $faker->text,
			],
		] );

		return shopp_add_product( $args );
	}

	/**
	 * Create what would be considered a "shipped" product.
	 *
	 * @param array $args Optional. Product arguments. Defaults will be generated for anything
	 *                    not provided. @see _validate_product_data() within shopp/api/products.php
	 *                    for a full explanation of options.
	 *
	 * @return ShoppProduct
	 */
	public static function create_shipped_product( $args = [] ) {
		$faker = self::get_faker();

		return self::create( wp_parse_args( $args, [
			'single' => [
				'type'  => 'Shipped',
				'price' => $faker->randomFloat( 2, 1 ),
			],
		] ) );
	}

	/**
	 * Create what would be considered a "variant" product.
	 *
	 * @param array $args Optional. Product arguments. Defaults will be generated for anything
	 *                    not provided. @see _validate_product_data() within shopp/api/products.php
	 *                    for a full explanation of options.
	 *
	 * @return ShoppProduct
	 */
	public static function create_variant_product( $args = [] ) {
		$faker = self::get_faker();

		return self::create( wp_parse_args( $args, [
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
		] ) );
	}

	/**
	 * Retrieve the Faker instance.
	 *
	 * @return Faker
	 */
	protected function get_faker() {
		if ( ! self::$faker ) {
			self::$faker = Faker::create();
		}

		return self::$faker;
	}
}
