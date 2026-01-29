<?php
/**
 * WP-CLI Commands for Algolia Headless
 *
 * @package Algolia_Headless
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Algolia Headless WP-CLI Commands
 */
class Algolia_Headless_CLI {

	/**
	 * Display current Algolia Headless configuration status
	 *
	 * ## EXAMPLES
	 *
	 *     wp algolia-headless status
	 *
	 * @when after_wp_load
	 */
	public function status() {
		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( '%G=== Algolia Headless Status ===%n' ) );
		WP_CLI::line( '' );

		// Check Algolia plugin.
		if ( Algolia_Headless_Helper::is_algolia_plugin_active() ) {
			WP_CLI::success( 'WP Search with Algolia plugin: Active' );
		} else {
			WP_CLI::warning( 'WP Search with Algolia plugin: Not Active' );
		}

		// Check domain configuration.
		$headless_domain = Algolia_Headless_Helper::get_headless_domain();
		if ( empty( $headless_domain ) ) {
			WP_CLI::warning( 'Headless domain: Not configured' );
		} else {
			// Check if using constant.
			if ( defined( 'ALGOLIA_HEADLESS_DOMAIN' ) && ALGOLIA_HEADLESS_DOMAIN ) {
				WP_CLI::line( WP_CLI::colorize( "%GHeadless domain:%n {$headless_domain} %Y(via constant)%n" ) );
			} else {
				WP_CLI::success( "Headless domain: {$headless_domain}" );
			}

			// Validate domain.
			$parsed = wp_parse_url( $headless_domain );
			if ( ! $parsed || ! isset( $parsed['host'] ) ) {
				WP_CLI::error( 'Invalid domain format' );
			}

			// Check HTTPS.
			if ( isset( $parsed['scheme'] ) ) {
				if ( 'https' === $parsed['scheme'] ) {
					WP_CLI::success( 'Using HTTPS: Yes' );
				} else {
					WP_CLI::warning( 'Using HTTPS: No (Consider using HTTPS)' );
				}
			}
		}

		// Show WordPress URL.
		$wp_url = home_url();
		WP_CLI::line( "WordPress URL: {$wp_url}" );

		// Show sample URL transformation.
		if ( ! empty( $headless_domain ) ) {
			WP_CLI::line( '' );
			WP_CLI::line( WP_CLI::colorize( '%G=== Sample URL Transformation ===%n' ) );
			$sample_url = home_url( '/sample-post/' );
			$replaced   = Algolia_Headless_Helper::test_url_replacement( $sample_url );
			WP_CLI::line( WP_CLI::colorize( "%YBefore:%n {$sample_url}" ) );
			WP_CLI::line( WP_CLI::colorize( "%GAfter:%n  {$replaced}" ) );
		}

		WP_CLI::line( '' );
	}

	/**
	 * Set the headless domain
	 *
	 * ## OPTIONS
	 *
	 * <domain>
	 * : The full URL of your headless site (e.g., https://example.com)
	 *
	 * ## EXAMPLES
	 *
	 *     wp algolia-headless set-domain https://example.com
	 *
	 * @when after_wp_load
	 */
	public function set_domain( $args ) {
		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Please provide a domain URL' );
		}

		$domain = $args[0];

		// Validate URL format.
		$parsed = wp_parse_url( $domain );
		if ( ! $parsed || ! isset( $parsed['host'] ) ) {
			WP_CLI::error( "Invalid URL format: {$domain}" );
		}

		// Warn if using HTTP.
		if ( isset( $parsed['scheme'] ) && 'http' === $parsed['scheme'] ) {
			WP_CLI::warning( 'You are using HTTP instead of HTTPS. Consider using HTTPS for better security.' );
		}

		// Check if constant is defined.
		if ( defined( 'ALGOLIA_HEADLESS_DOMAIN' ) && ALGOLIA_HEADLESS_DOMAIN ) {
			WP_CLI::warning( 'ALGOLIA_HEADLESS_DOMAIN constant is defined in wp-config.php and will override this setting.' );
			WP_CLI::confirm( 'Do you want to continue updating the database option anyway?' );
		}

		// Update option.
		update_option( 'algolia_headless_domain', esc_url_raw( $domain ) );

		WP_CLI::success( "Headless domain set to: {$domain}" );

		// Show sample transformation.
		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( '%G=== Sample URL Transformation ===%n' ) );
		$sample_url = home_url( '/sample-post/' );
		$replaced   = Algolia_Headless_Helper::test_url_replacement( $sample_url );
		WP_CLI::line( WP_CLI::colorize( "%YBefore:%n {$sample_url}" ) );
		WP_CLI::line( WP_CLI::colorize( "%GAfter:%n  {$replaced}" ) );
	}

	/**
	 * Test URL replacement
	 *
	 * ## OPTIONS
	 *
	 * <url>
	 * : The URL to test
	 *
	 * ## EXAMPLES
	 *
	 *     wp algolia-headless test https://wp.example.com/sample-post/
	 *
	 * @when after_wp_load
	 */
	public function test( $args ) {
		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Please provide a URL to test' );
		}

		$url = $args[0];

		// Check if domain is configured.
		$headless_domain = Algolia_Headless_Helper::get_headless_domain();
		if ( empty( $headless_domain ) ) {
			WP_CLI::error( 'Headless domain is not configured. Use "wp algolia-headless set-domain <url>" first.' );
		}

		// Test replacement.
		$replaced = Algolia_Headless_Helper::test_url_replacement( $url );

		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( '%G=== URL Replacement Test ===%n' ) );
		WP_CLI::line( WP_CLI::colorize( "%YOriginal URL:%n  {$url}" ) );
		WP_CLI::line( WP_CLI::colorize( "%GReplaced URL:%n  {$replaced}" ) );

		if ( $url === $replaced ) {
			WP_CLI::warning( 'URL was not changed. This may happen if the original URL does not match your WordPress domain.' );
		} else {
			WP_CLI::success( 'URL replacement successful' );
		}

		WP_CLI::line( '' );
	}

	/**
	 * View debug logs (WP_DEBUG must be enabled)
	 *
	 * ## OPTIONS
	 *
	 * [--lines=<number>]
	 * : Number of log lines to display (default: 20)
	 *
	 * [--level=<level>]
	 * : Filter by log level (info, warning, error)
	 *
	 * ## EXAMPLES
	 *
	 *     wp algolia-headless logs
	 *     wp algolia-headless logs --lines=50
	 *     wp algolia-headless logs --level=error
	 *
	 * @when after_wp_load
	 */
	public function logs( $assoc_args ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			WP_CLI::warning( 'WP_DEBUG is not enabled. Debug logging is disabled.' );
			return;
		}

		$logs = get_transient( 'algolia_headless_debug_logs' );

		if ( empty( $logs ) || ! is_array( $logs ) ) {
			WP_CLI::line( 'No debug logs found.' );
			return;
		}

		// Filter by level if specified.
		$level = isset( $assoc_args['level'] ) ? $assoc_args['level'] : null;
		if ( $level ) {
			$logs = array_filter(
				$logs,
				function ( $log ) use ( $level ) {
					return isset( $log['level'] ) && $log['level'] === $level;
				}
			);
		}

		// Limit number of lines.
		$lines = isset( $assoc_args['lines'] ) ? intval( $assoc_args['lines'] ) : 20;
		$logs  = array_slice( $logs, -$lines );

		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( '%G=== Algolia Headless Debug Logs ===%n' ) );
		WP_CLI::line( '' );

		foreach ( $logs as $log ) {
			$timestamp = isset( $log['timestamp'] ) ? $log['timestamp'] : 'N/A';
			$log_level = isset( $log['level'] ) ? strtoupper( $log['level'] ) : 'INFO';
			$message   = isset( $log['message'] ) ? $log['message'] : '';

			// Color code by level.
			$color = '%n';
			if ( 'ERROR' === $log_level ) {
				$color = '%R';
			} elseif ( 'WARNING' === $log_level ) {
				$color = '%Y';
			} elseif ( 'INFO' === $log_level ) {
				$color = '%G';
			}

			WP_CLI::line( WP_CLI::colorize( "[{$timestamp}] {$color}{$log_level}:%n {$message}" ) );
		}

		WP_CLI::line( '' );
		WP_CLI::line( "Total logs: " . count( $logs ) );
	}

	/**
	 * Clear debug logs
	 *
	 * ## EXAMPLES
	 *
	 *     wp algolia-headless clear-logs
	 *
	 * @when after_wp_load
	 */
	public function clear_logs() {
		delete_transient( 'algolia_headless_debug_logs' );
		WP_CLI::success( 'Debug logs cleared' );
	}
}
