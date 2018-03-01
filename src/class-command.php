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
use WC_Product_Attribute;
use WC_Product_Factory;
use WC_Product_Variation;
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
			'specs'      => 0,
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

			if ( ! empty( $product->specs ) ) {
				$product_attributes['specs'] += count( $product->specs );

				// Look for any specs that match "Height", "Width", or "Length".
				$spec_keys = array_map( 'strtolower', array_keys( $product->specnames ) );
				if ( $embedded_dimensions = array_intersect( [ 'height', 'width', 'length' ], $spec_keys ) ) {
					$product_attributes['dimensions'] = count( $embedded_dimensions );
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
			'Product specs'         => $product_attributes['specs'],
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
	 * Ensure both WooCommerce and Shopp are installed and active.
	 *
	 * ## EXAMPLES
	 *
	 *   wp shopp-to-woocommerce install-plugins
	 *
	 * @subcommand install-plugins
	 */
	public function install_plugins() {
		if ( is_plugin_active( 'shopp/Shopp.php' ) ) {
			WP_CLI::log( __( 'Shopp is already active.', 'shopp-to-woocommerce' ) );
		} else {
			WP_CLI::runcommand( 'plugin install shopp --activate' );
			require_once WP_PLUGIN_DIR . '/shopp/Shopp.php';
		}

		if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			WP_CLI::log( __( 'WooCommerce is already active.', 'shopp-to-woocommerce' ) );
		} else {
			WP_CLI::runcommand( 'plugin install woocommerce --activate' );
			require_once WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
		}
	}

	/**
	 * Empty the trash, reducing the amount of content that needs to be moved.
	 *
	 * ## EXAMPLES
	 *
	 *   wp shopp-to-woocommerce install-plugins
	 *
	 * @subcommand empty-trash
	 */
	public function empty_trash() {
		$trashed = new WP_Query( array(
			'post_type'      => 'any',
			'post_status'    => 'trash',
			'posts_per_page' => 1000,
			'fields'         => 'ids',
			'cache_results'  => false,
		) );
		$deleted = 0;

		// Loop through trashed posts.
		foreach ( $trashed->posts as $post_id ) {
			if ( wp_delete_post( $post_id ) ) {
				$deleted++;
			}
		}

		WP_CLI::log( sprintf(
			_n( '%1$d trashed item were permanently deleted.', '%1$d trashed items were permanently deleted.', $deleted, 'shopp-to-woocommerce' ),
			$deleted
		) );
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

		$this->migration_step( __( 'Ensuring both Shopp and WooCommerce are installed and active:', 'shopp-to-woocommerce' ) );
		$this->install_plugins();

		$this->migration_step( __( 'Emptying trash:', 'shopp-to-woocommerce' ) );
		$this->empty_trash();

		$this->migration_step( __( 'Analyzing current content:', 'shopp-to-woocommerce' ) );
		$this->analyze();

		$this->migration_step( __( 'Migrating taxonomy terms:', 'shopp-to-woocommerce' ) );
		$this->migrate_terms();

		$this->migration_step( __( 'Migrating products:', 'shopp-to-woocommerce' ) );
		$this->migrate_products();

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
		$query_args = array(
			'post_type'      => ShoppProduct::$posttype,
			'post_status'    => 'any',
			'posts_per_page' => 50,
			'return'         => 'ids',
			'cache_results'  => false,
			'post__not_in'   => [],
		);
		$query      = new WP_Query( $query_args );
		$counter    = 0;
		$failures   = 0;

		while ( $query->have_posts() ) {
			$query->the_post();

			$product = shopp_product( get_the_ID() );
			if ( ! $this->migrate_single_product( $product ) ) {
				$query_args['post__not_in'][] = get_the_ID();
				$failures++;
			}
			$counter++;

			if ( 0 === $counter % $query_args['posts_per_page'] ) {
				$query = new WP_Query( $query_args );
			}
		}

		if ( $failures ) {
			WP_CLI::line();
			WP_CLI::warning( sprintf(
				_n( '%1$d product had errors.', '%1$d products had errors.', $failures, 'shopp-to-woocommerce' ),
				$failures
			) );
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
		WP_CLI::log( sprintf( '- Migrating product: %s.', $product->name ) );

		// Determine the product type and start populating.
		$product_type = 'on' === $product->variants ? 'variable' : 'simple';
		$classname    = WC_Product_Factory::get_classname_from_product_type( $product_type );
		$props        = [
			'name'               => $product->name,
			'slug'               => $product->slug,
			'date_created'       => $product->post_date_gmt,
			'date_modified'      => $product->post_modified_gmt,
			'featured'           => Helpers\str_to_bool( $product->featured ),
			'description'        => $product->description,
			'short_description'  => $product->summary,
			'image_id'           => null,
			'gallery_image_ids'  => [],
			'attributes'         => [],
			'default_attributes' => [],
			'total_sales'        => $product->sold,
		];
		$variants     = [];

		// Move media into WordPress.
		foreach ( $product->images as $image ) {
			try {
				$attachment_id = $this->sideload_image( $image, $product->id );
			} catch ( RuntimeException $e ) {
				WP_CLI::warning( sprintf(
					/* Translators: %1$s is the product name, %2$s is the error message. */
					__( 'Unable to import %1$s, skipping: %2$s.', 'shopp-to-woocommerce' ),
					$product->name,
					$e->getMessage()
				) );

				// Restore the post type.
				set_post_type( $product->id, ShoppProduct::$posttype );

				return false;
			}

			if ( empty( $props['image_id'] ) ) {
				$props['image_id'] = $attachment_id;
			} else {
				$props['gallery_image_ids'][] = $attachment_id;
			}
		}

		// Handle product prices.
		if ( 'simple' === $product_type ) {
			$props = array_merge( $props, $this->parse_price( current( $product->prices ) ) );

		} else {
			foreach ( shopp_product_variant_options( $product->id ) as $label => $values ) {
				$attribute = new WC_Product_Attribute();
				$attribute->set_id( 0 );
				$attribute->set_name( $label );
				$attribute->set_options( (array) $values );
				$attribute->set_visible( true );
				$attribute->set_variation( true );

				$props['attributes'][] = $attribute;
				$props['default_attributes'][ sanitize_title( $label ) ] = current( $values );
			}

			foreach ( shopp_product_variants( $product->id ) as $variant ) {
				$price   = shopp_product_variant( $variant->id );
				$variant = shopp_product_variant_to_item( $price );
				$attributes = array_combine(
					array_map( 'sanitize_title', array_keys( $variant->variant ) ),
					array_values( $variant->variant )
				);

				$variation = new WC_Product_Variation();
				$variation->set_props( $this->parse_price( $price ) );
				$variation->set_attributes( $attributes );
				$variation->set_parent_id( $product->id );

				$variants[] = $variation;
			}
		}

		// Attempt to extract product attributes.
		foreach ( $product->specs as $spec ) {
			$attribute = new WC_Product_Attribute();
			$attribute->set_id( 0 );
			$attribute->set_name( $spec->name );
			$attribute->set_options( array( $spec->value ) );
			$attribute->set_position( $spec->sortorder );
			$attribute->set_visible( true );

			$props['attributes'][] = $attribute;
		}

		// Update the post type populate the new product.
		set_post_type( $product->id, 'product' );

		// Save the variants.
		if ( ! empty( $variants ) ) {
			array_walk( $variants, function ( $variant ) {
				$variant->save();
			} );
		}

		// Assemble a new WooCommerce product.
		$new = new $classname( $product->id );
		$new->set_props( $props );
		$new->save();

		// Verify the migration.
		try {
			$this->verify( $product, $new );

		} catch ( VerificationException $e ) {
			WP_CLI::warning( sprintf(
				/* Translators: %1$s is the product name, %2$s is the error message. */
				__( 'Verification of "%1$s" failed: %2$s', 'shopp-to-woocommerce' ),
				$product->name,
				$e->getMessage()
			) );

			// Remove the variations.
			foreach ( $new->get_children() as $variation_id ) {
				wp_delete_post( $variation_id, true );
			}

			// Restore the post type.
			set_post_type( $product->id, ShoppProduct::$posttype );

			return false;
		}

		return $new;
	}

	/**
	 * Given a Shopp product and a WooCommerce product, ensure the two match.
	 *
	 * @throws VerificationException If the two products don't match.
	 *
	 * @param ShoppProduct $shopp The Shopp representation of the product.
	 * @param WC_Product   $woo   The newly-created WooCommerce product.
	 */
	protected function verify( $shopp, $woo ) {
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
		$this->assertTrue(
			$shopp->post_modified_gmt <= $woo->get_date_modified()->getTimestamp(),
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
			Helpers\str_to_bool( $woo->get_backorders() ),
			'Expected backorder status to be inherited from the Shopp store.'
		);
		$this->assertTrue(
			empty( $woo->get_upsell_ids() ),
			'Shopp doesn\'t permit upsell relationships.'
		);
		$this->assertTrue(
			empty( $woo->get_cross_sell_ids() ),
			'Shopp doesn\'t permit cross-sell relationships.'
		);
		$this->assertEquals(
			$shopp->post_parent,
			$woo->get_parent_id(),
			'The post parent_id should not be changed.'
		);
		$this->assertEquals(
			'open' === $shopp->comment_status,
			$woo->get_reviews_allowed(),
			'The comment settings do not match.'
		);
		$this->assertEquals(
			$shopp->menu_order,
			$woo->get_menu_order(),
			'The post menu_order should not be changed.'
		);

		// Only worry about post date for published products.
		if ( 'publish' === get_post_status( $woo->get_id() ) ) {
			$this->assertTrue(
				$shopp->post_date_gmt <= $woo->get_date_created()->getTimestamp(),
				'Product creation timestamp does not match.'
			);
		}

		// Only check for post thumbnails if the product had an image to begin with.
		if ( ! empty( $product->images ) ) {
			$this->assertTrue(
				! empty( $woo->get_image_id() ),
				'Expected all products to have a featured image.'
			);
		}

		WP_CLI::debug( sprintf(
			/* Translators: %1$s is the product name. */
			__( 'Product "%1$s" passed verification checks.', 'shopp-to-woocommerce' ),
			$shopp->name
		) );
	}

	/**
	 * Assert that a statement evaluates as true, or throw an exception.
	 *
	 * This may or may not be a nice way to recycle PHPUnit tests.
	 *
	 * @throws VerificationException If the statement does not evaluate as true.
	 *
	 * @param bool   $statement The evaluated statement.
	 * @param string $message   Optional. The exception message if the values do not match.
	 *                          Default is empty.
	 */
	protected function assertTrue( $statement, $message = '' ) {
		if ( true !== $statement ) {
			throw new VerificationException( $message );
		}
	}

	/**
	 * Assert two values are equal, or throw an exception.
	 *
	 * @throws VerificationException If $actual does not equal $expected.
	 *
	 * @param mixed  $expected The expected value.
	 * @param mixed  $actual   The actual value.
	 * @param string $message  Optional. The exception message if the values do not match.
	 *                         Default is empty.
	 */
	protected function assertEquals( $expected, $actual, $message = '' ) {
		$this->assertTrue( $expected == $actual, $message );
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

	/**
	 * Given a Shopp price object, convert it into WooCommerce properties.
	 *
	 * @param object $price The Shopp representation of a price.
	 *
	 * @return array Properties that can be applied to WooCommerce products.
	 */
	protected function parse_price( $price ) {
		return [
			'price'         => Helpers\str_to_bool( $price->sale ) ? $price->saleprice : $price->price,
			'regular_price' => $price->price,
			'sale_price'    => $price->saleprice ? $price->saleprice : null,
			'sku'           => $price->sku,
			'tax_status'    => Helpers\str_to_bool( $price->tax ) ? 'taxable' : 'none',
		];
	}
}
