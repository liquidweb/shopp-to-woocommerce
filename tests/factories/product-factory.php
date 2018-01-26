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
		if ( ! self::$faker ) {
			self::$faker = Faker::create();
		}

		$faker = self::$faker;

		$args  = wp_parse_args( $args, [
			'name'        => $faker->text,
			'slug'        => $faker->slug,
			'publish'     => [
				'flag'        => true,
				'publishtime' => [
					'month'    => date( 'n' ),
					'day'      => date( 'j' ),
					'year'     => date( 'Y' ),
					'hour'     => date( 'G' ),
					'minute'   => date( 'm' ),
					'meridian' => date( 'A' ),
				],
			],
			'description' => $faker->paragraphs( 2, true ),
			'summary'     => $faker->paragraph,
			'specs'       => [
				'Some spec' => $faker->text,
			],
			'single'      => [
				'option'       => [],
				'type'         => 'Shipped',
				'taxed'        => false,
				'price'        => $faker->randomFloat( 2, 1 ),
				'sale'         => [],
				'shipping'     => [],
				'inventory'    => [],
				'donation'     => [],
				'subscription' => [],
			],
		] );


		return shopp_add_product( $args );
	}
}
