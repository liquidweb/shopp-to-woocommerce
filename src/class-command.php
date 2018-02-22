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
use RuntimeException;
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
		WP_CLI::confirm(
			__( 'This command will migrate Shopp categories, tags, and products to WooCommerce. Are you sure you want to proceed?', 'shopp-to-woocommerce' )
		);

		$this->migration_step( __( 'Analyzing current content:', 'shopp-to-woocommerce' ) );
		$this->analyze();

		$this->migration_step( __( 'Migrating taxonomy terms:', 'shopp-to-woocommerce' ) );
		//$this->migrate_terms();

		$this->migration_step( __( 'Migrating products:', 'shopp-to-woocommerce' ) );
		//$this->migrate_products();

		WP_CLI::line();
		WP_CLI::success( __( 'Shopp data has been migrated successfully!', 'shopp-to-woocommerce' ) );
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
	 * Helper to display a single step of the migration process.
	 *
	 * @param string $message The line to display.
	 */
	protected function migration_step( $message ) {
		WP_CLI::log( PHP_EOL . WP_CLI::colorize( '%B' . $message . '%n' ) );
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

		// Move media into WordPress.
		$gallery_ids = [];

		foreach ( $product->images as $image ) {
			$attachment_id = $this->sideload_image( $image, $product->id );

			if ( ! has_post_thumbnail( $product->id ) ) {
				set_post_thumbnail( $product->id, $attachment_id );
			} else {
				$gallery_ids[] = $attachment_id;
			}
		}

		$new = new $classname( $product->id );

		$new->set_name( $product->name );
		$new->set_slug( $product->slug );
		$new->set_date_created( $product->post_date_gmt );
		$new->set_date_modified( $product->post_modified_gmt );
		$new->set_featured( Helpers\str_to_bool( $product->featured ) );
		$new->set_description( $product->description );
		$new->set_short_description( $product->summary );
		$new->set_gallery_image_ids( $gallery_ids );

		return $new;
	}

	/**
	 * Given a Shopp ImageAsset object, sideload the media into WordPress.
	 *
	 * @param ImageAsset $image      The Shopp image object.
	 * @param int        $product_id The ID to which the image should be associated.
	 *
	 * @return int The WordPress attachment ID.
	 */
	protected function sideload_image( $image, $product_id ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$image_url = $image->url();
		$tmp       = download_url( $image_url );

		if ( is_wp_error( $tmp ) ) {
			throw new RuntimeException( sprintf(
				__( 'Unable to download file from %1$s: %2$s.', 'shopp-to-woocommerce' ),
				$image_url,
				$tmp->get_error_message()
			) );
		}

		$attachment_id = media_handle_sideload( [
			'name'     => $image->filename,
			'tmp_name' => $tmp,

		], $product_id, $image->title );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
			throw new RuntimeException( sprintf(
				__( 'Unable to sideload file from %1$s: %2$s.', 'shopp-to-woocommerce' ),
				$image_url,
				$attachment_id->get_error_message()
			) );
		}

		// Set additional meta data.
		if ( ! empty( $image->alt ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $image->alt );
		}

		return $attachment_id;
	}
}
