<?php
/**
 * Helper functions.
 *
 * @package LiquidWeb\ShoppToWooCommerce
 * @author  Liquid Web
 */

namespace LiquidWeb\ShoppToWooCommerce\Helpers;

/**
 * Shopp likes to use 'yes' or 'no' instead of native Booleans. This function converts them.
 *
 * @param string|bool $value A string value that can be coerced into a Boolean, or a Boolean (which
 *                    will be left untouched).
 *
 * @return bool The Boolean representation of $value.
 */
function str_to_bool( $value ) {
	if ( is_bool( $value ) ) {
		return $value;
	}

	$affirmative = [ 'yes', '1', 'true', 'on' ];

	return in_array( $affirmative, strtolower( trim( $value ) ), true );
}
