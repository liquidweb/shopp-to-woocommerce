<?php
/**
 * Main WP-CLI command for converting Shopp sites to WooCommerce.
 *
 * @package LiquidWeb\ShoppToWooCommerce
 * @author  Liquid Web
 */

namespace LiquidWeb\ShoppToWooCommerce;

use LiquidWeb\ShoppToWooCommerce\Helpers as Helpers;
use ProductCollection;
use ShoppProduct;
use WC_Product;
use WC_Product_Factory;
use WP_CLI;
use WP_CLI_Command;
use WP_CLI\Utils as Utils;
use WP_Query;

/**
 * Migrate content from Shopp into WooCommerce.
 */
class Command extends WP_CLI_Command {

	/**
	 * Analyze all of the products in the Shopp database to determine what needs to be migrated.
	 *
	 * ## EXAMPLES
	 *
	 *   wp shopp-to-woocommerce analyze
	 */
	public function analyze() {
		global $wpdb;

		$products = new ProductCollection();
		$products->load( [
			'published' => false,
		] );
		$products->rewind();

		// Iterate over products and get details about them.
		$price_types        = [
			'Shipped'  => 0,
			'Virtual'  => 0,
			'Download' => 0,
			'Donation' => 0,
			'N/A'      => 0,
		];
		$product_attributes = [
			'addons'     => 0,
			'dimensions' => 0,
		];

		$progress = Utils\make_progress_bar( 'Scanning Shopp products', $products->total );

		while ( $products->valid() ) {
			$product = $products->current();

			$product->load_data();

			// Get details about the product pricing schemes.
			foreach ( $product->prices as $price ) {
				$price_types[ $price->type ]++;

				// Don't count dimensions that are just weight = 0, as that's a default.
				if ( ! empty( $price->dimensions ) && 0 !== (int) $price->dimensions['weight'] ) {
					$product_attributes['dimensions']++;
				}
			}

			// Products with add-ons.
			if ( Helpers\str_to_bool( $product->addons ) ) {
				$product_attributes['addons']++;
			}

			$progress->tick();
			$products->next();

			if ( ! $products->valid() && $products->pages > $products->page ) {
				$products->load( [
					'page'      => $products->page +1,
					'published' => false,
				] );
				$products->rewind();
			}
		}

		$progress->finish();

		$components = [
			'All products'          => $products->total,
			'Shipped prices'        => $price_types['Shipped'],
			'Virtual prices'        => $price_types['Virtual'],
			'Download prices'       => $price_types['Download'],
			'Donation prices'       => $price_types['Donation'],
			'Disabled prices'       => $price_types['N/A'],
			'Product categories'    => count( shopp_product_categories() ),
			'Product tags'          => count( shopp_product_tags() ),
			'Products with add-ons' => $product_attributes['addons'],
			'Product dimensions'    => $product_attributes['dimensions'],
		];

		// Assemble a table of results.
		$table = [];

		foreach ( $components as $label => $count ) {
			$table[] = [
				'component' => $label,
				'records'   => (int) $count,
				'required'  => $count ? '   ✔' : '',
			];
		}

		Utils\format_items( 'table', $table, [ 'component', 'records', 'required' ] );
	}

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
	 * Migrate all taxonomy terms from Shopp to WooCommerce.
	 *
	 * ## EXAMPLES
	 *
	 *   wp shopp-to-woocommerce migrate-terms
	 *
	 * @global $wpdb
	 *
	 * @subcommand migrate-terms
	 */
	public function migrate_terms() {
		global $wpdb;

		$taxonomies = array(
			'shopp_category' => 'product_cat',
			'shopp_tag'      => 'product_tag',
		);

		// Loop through each taxonomy to be converted.
		foreach ( $taxonomies as $old => $new ) {
			$count = wp_count_terms( $old );

			WP_CLI::log( sprintf( 'Migrating %d terms from %s to %s.', $count, $old, $new ) );

			$wpdb->update( $wpdb->term_taxonomy, array( 'taxonomy' => $new ), array( 'taxonomy' => $old ) );

			clean_taxonomy_cache( $old );
			clean_taxonomy_cache( $new );
		}
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

		// Determine the product type.
		$product_type = 'on' === $product->variants ? 'variable' : 'simple';
		$classname    = WC_Product_Factory::get_classname_from_product_type( $product_type );

		// Update the post type populate the new product.
		set_post_type( $product->id, 'product' );

		$new = new $classname( $product->id );

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
