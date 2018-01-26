<?php
/**
 * Utility functions to help with testing.
 *
 * @package LiquidWeb\ShoppToWooCommerce
 * @author  Liquid Web
 */

namespace Tests\Utils;

use Automatic_Upgrader_Skin;
use ErrorException;
use Plugin_Upgrader;

/**
 * Activate a plugin, installing it if it doesn't already exist.
 *
 * @param string $plugin The plugin slug, in directory/plugin-name.php form.
 */
function install_and_activate_plugin( $plugin, $name = '' ) {
	$activated = activate_plugin( $plugin );
	$name      = $name ?: dirname( $plugin );

	if ( ! is_wp_error( $activated ) ) {
		return;
	}

	if ( 'plugin_not_found' === $activated->get_error_code() ) {
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		echo esc_html( sprintf(
			__( '%s is not currently installed in the test environment, attempting to install...', 'shopp-to-woocommerce' ),
			$name
		) );

		// Retrieve information about the plugin.
		$plugin_data = wp_remote_get( 'https://api.wordpress.org/plugins/info/1.0/' . dirname( $plugin ) . '.json' );

		if ( ! is_wp_error( $plugin_data ) ) {
			$plugin_data = json_decode( wp_remote_retrieve_body( $plugin_data ) );
			$plugin_url  = $plugin_data->download_link;
		} else {
			$plugin_url = false;
		}

		// Download the plugin from the WordPress.org repository.
		$upgrader  = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		$installed = $upgrader->install( $plugin_url );

		if ( true === $installed ) {
			echo "\033[0;32mOK\033[0;m" . PHP_EOL;
		} else {
			echo "\033[0;31mFAIL\033[0;m" . PHP_EOL;

			if ( is_wp_error( $installed ) ) {
				throw new ErrorException( sprintf(
					/* Translators: %1$s is the plugin name, %2$s is the WP Error message. */
					__( 'Installation of %1$s failed: %2$s', 'shopp-to-woocommerce' ),
					$name,
					$installed->get_error_message()
				) );
			}

			/* Translators: %1$s is the plugin name. */
			throw new ErrorException( sprintf( __( 'Installation of %1$s failed.', 'shopp-to-woocommerce' ), $name ) );
		}
	}

	/**
	 * Perform actions after the plugin file has been installed but before activating it.
	 */
	do_action( 'before_activate_' . $plugin );

	// Try once again to activate.
	$activated = activate_plugin( $plugin );

	if ( is_wp_error( $activated ) ) {
		throw new ErrorException( "\033[0;33mUnable to activate {$name}:\033[0;m {$activated->get_error_message()}" );
	}
}

/**
 * Output a Shopp error to the console.
 *
 * @param ShoppError $error The ShoppError to display.
 */
function print_shopp_error( $error ) {
	if ( 512 <= $error->level ) {
		echo $error->message();
	}
}
