<?php
/**
 * Tests for the main WP-CLI command.
 *
 * @package LiquidWeb\ShoppToWooCommerce
 * @author  Liquid Web
 */

namespace Tests;

use ShoppDatabaseObject;
use WP_UnitTestCase;

class TestCase extends WP_UnitTestCase {

	/**
	 * Truncate Shopp database tables after each test.
	 *
	 * @global $wpdb
	 *
	 * @after
	 */
	public function delete_shopp_data() {
		global $wpdb;

		$tables = [
			ShoppDatabaseObject::tablename('address'),
			ShoppDatabaseObject::tablename('asset'),
			ShoppDatabaseObject::tablename('customer'),
			ShoppDatabaseObject::tablename('index'),
			ShoppDatabaseObject::tablename('meta'),
			ShoppDatabaseObject::tablename('price'),
			ShoppDatabaseObject::tablename('promo'),
			ShoppDatabaseObject::tablename('purchase'),
			ShoppDatabaseObject::tablename('purchased'),
			ShoppDatabaseObject::tablename('shopping'),
			ShoppDatabaseObject::tablename('summary'),
		];

		foreach ( $tables as $table ) {
			$wpdb->query( 'DELETE FROM ' . esc_sql( $table ) );
		}
	}
}
