<?php
/**
 * Mocks for the WP_CLI utility functions.
 *
 * @package LiquidWeb\ShoppToWooCommerce
 * @author  Liquid Web
 */

namespace WP_CLI\Utils;

use WP_CLI\NoOp;

function make_progress_bar() {
	return new NoOp;
}
