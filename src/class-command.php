<?php
/**
 * Main WP-CLI command for converting Shopp sites to WooCommerce.
 *
 * @package LiquidWeb\ShoppToWooCommerce
 * @author  Liquid Web
 */

namespace LiquidWeb\ShoppToWooCommerce;

use ShoppProduct;
use WC_Product;
use WP_CLI;
use WP_CLI_Command;
use WP_CLI\Utils as Utils;
use WP_Query;

/**
 * Migrate content from Shopp into WooCommerce.
 */
class Command extends WP_CLI_Command {

	/**
	 * Migrate all content from Shopp to WooCommerce.
	 *
	 * This command acts as a single step for each of the other commands, using default settings.
	 *
	 * ## EXAMPLES
	 *
	 *   wp shopp-to-woocommerce migrate
	 */
	public function migrate() {

	}

	/**
	 * Migrate products from Shopp to WooCommerce.
	 *
	 * ## EXAMPLES
	 *
	 *   wp shopp-to-woocommerce migrate-products
	 *
	 * @subcommand migrate-products
	 */
	public function migrate_products() {
		WP_CLI::log( 'Migrating products:' );

		$query_args = array(
			'post_type'      => ShoppProduct::$posttype,
			'posts_per_page' => 50,
			'return'         => 'ids',
		);
		$query      = new WP_Query( $query_args );
		$counter    = 0;

		while ( $query->have_posts() ) {
			$query->the_post();

			$product = shopp_product( get_the_ID() );
			print_r( $product );
			die('hard');
			$this->migrate_single_product( $product )->save();
			$counter++;

			if ( 0 === $counter % $query_args['posts_per_page'] ) {
				$query = new WP_Query( $query_args );
			}
		}
	}

	/**
	 * Migrate a single product from Shopp to WooCommerce.
	 *
	 * @param ShoppProduct $product
	 *
	 * @return WC_Product The newly-created WooCommerce product.
	 */
	protected function migrate_single_product( $product ) {
		WP_CLI::debug( sprintf( 'Migrating product: %s.', $product->name ) );

		set_post_type( $product->id, 'product' );

		$new = new WC_Product( $product->id );

		$new->set_name( $product->name );
		$new->set_slug( $product->slug );
		$new->set_date_created( $product->post_date_gmt );
		$new->set_date_modified( $product->post_modified_gmt );
		$new->set_featured( Helpers\str_to_bool( $product->featured ) );
		$new->set_description( $product->description );
		$new->set_short_description( $product->summary );

		return $new;
	}
}
