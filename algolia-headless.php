<?php
/**
 * Plugin Name:     Search with Algolia Headless extension
 * Plugin URI:      https://wp-kyoto.net
 * Description:     Simply extension for WP Search with Algolia. Replace the indices domain from the WordPress to custom domain.
 * Author:          Hidetaka Okamoto
 * Author URI:      https://wp-kyoto.net/en
 * Text Domain:     algolia-headless
 * Domain Path:     /languages
 * Version:         0.2.0
 *
 * @package         Algolia_Headless
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Algolia Headless Helper Class
 *
 * Provides utility functions used across the plugin.
 */
class Algolia_Headless_Helper {

	/**
	 * Get headless domain from environment variable or option
	 *
	 * Priority: ALGOLIA_HEADLESS_DOMAIN constant > option
	 *
	 * @return string|false
	 */
	public static function get_headless_domain() {
		// Check for environment variable/constant first.
		if ( defined( 'ALGOLIA_HEADLESS_DOMAIN' ) && ALGOLIA_HEADLESS_DOMAIN ) {
			return ALGOLIA_HEADLESS_DOMAIN;
		}

		return get_option( 'algolia_headless_domain', false );
	}

	/**
	 * Check if WP Search with Algolia plugin is active
	 *
	 * @return bool
	 */
	public static function is_algolia_plugin_active() {
		// Check if Algolia class exists.
		return class_exists( 'Algolia_Plugin' ) || class_exists( 'Algolia_API' );
	}

	/**
	 * Log debug message
	 *
	 * Logs to PHP error log and stores recent logs in database (if WP_DEBUG enabled).
	 *
	 * @param string $message Log message.
	 * @param string $level Log level (info, warning, error).
	 */
	public static function log( $message, $level = 'info' ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		// Log to PHP error log.
		error_log( sprintf( '[Algolia Headless][%s] %s', strtoupper( $level ), $message ) );

		// Store in database (transient, expires in 7 days).
		$logs = get_transient( 'algolia_headless_debug_logs' );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		$logs[] = array(
			'timestamp' => current_time( 'mysql' ),
			'level'     => $level,
			'message'   => $message,
		);

		// Keep only last 100 logs.
		$logs = array_slice( $logs, -100 );

		set_transient( 'algolia_headless_debug_logs', $logs, 7 * DAY_IN_SECONDS );
	}

	/**
	 * Test URL replacement
	 *
	 * @param string $url URL to test.
	 * @return string Replaced URL.
	 */
	public static function test_url_replacement( $url ) {
		$replacer = new Algolia_Headless_Replacer();
		return $replacer->replace_algolia_permalink_to_public_site_domain( $url );
	}
}

/**
 * Algolia Headless Replacer Class
 *
 * Replaces WordPress permalinks with headless site domain in Algolia indices.
 */
class Algolia_Headless_Replacer {

	/**
	 * Cached headless domain
	 *
	 * @var string|false
	 */
	private $headless_domain = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'algolia_term_record', array( $this, 'replace_algolia_permalink' ), 10, 1 );
		add_filter( 'algolia_post_shared_attributes', array( $this, 'replace_algolia_permalink' ), 10, 1 );
		add_filter( 'algolia_searchable_post_shared_attributes', array( $this, 'replace_algolia_permalink' ), 10, 1 );
		add_filter( 'algolia_user_record', array( $this, 'replace_algolia_posts_url' ), 10, 1 );
	}

	/**
	 * Get headless domain from options (cached)
	 *
	 * Supports environment variable override via ALGOLIA_HEADLESS_DOMAIN constant.
	 *
	 * @return string|false
	 */
	private function get_headless_domain() {
		if ( null === $this->headless_domain ) {
			$this->headless_domain = Algolia_Headless_Helper::get_headless_domain();
		}
		return $this->headless_domain;
	}

	/**
	 * Replace WordPress domain with headless site domain
	 *
	 * @param string $url URL to replace.
	 * @return string Modified URL.
	 */
	public function replace_algolia_permalink_to_public_site_domain( $url ) {
		$replaced_url = $this->get_headless_domain();
		if ( ! $replaced_url || ! is_string( $replaced_url ) ) {
			Algolia_Headless_Helper::log( 'Headless domain not configured', 'warning' );
			return $url;
		}

		// Parse headless domain.
		$target = wp_parse_url( $replaced_url );
		if ( ! $target || ! isset( $target['host'] ) ) {
			Algolia_Headless_Helper::log( "Failed to parse headless domain: {$replaced_url}", 'error' );
			return $url;
		}

		$path            = isset( $target['path'] ) ? $target['path'] : '';
		$replaced_domain = $target['host'] . $path;

		if ( empty( $replaced_domain ) ) {
			Algolia_Headless_Helper::log( 'Replaced domain is empty after parsing', 'error' );
			return $url;
		}

		// Parse original URL.
		$parsed_url = wp_parse_url( $url );
		if ( ! $parsed_url || ! isset( $parsed_url['host'] ) ) {
			Algolia_Headless_Helper::log( "Failed to parse original URL: {$url}", 'error' );
			return $url;
		}

		$replace_target = $parsed_url['host'];
		if ( isset( $parsed_url['port'] ) && $parsed_url['port'] ) {
			$replace_target .= ':' . $parsed_url['port'];
		}

		// Use str_replace instead of preg_replace to avoid ReDoS vulnerability.
		$result = str_replace( $replace_target, $replaced_domain, $url );

		Algolia_Headless_Helper::log( "URL replaced: {$url} -> {$result}", 'info' );

		return $result;
	}

	/**
	 * Replace permalink in shared attributes
	 *
	 * @param array $shared_attributes Shared attributes.
	 * @return array Modified attributes.
	 */
	public function replace_algolia_permalink( $shared_attributes ) {
		if ( isset( $shared_attributes['permalink'] ) ) {
			$shared_attributes['permalink'] = $this->replace_algolia_permalink_to_public_site_domain( $shared_attributes['permalink'] );
		}
		return $shared_attributes;
	}

	/**
	 * Replace posts_url in user record
	 *
	 * @param array $user User record.
	 * @return array Modified user record.
	 */
	public function replace_algolia_posts_url( $user ) {
		if ( isset( $user['posts_url'] ) ) {
			$user['posts_url'] = $this->replace_algolia_permalink_to_public_site_domain( $user['posts_url'] );
		}
		return $user;
	}
}


/**
 * Algolia Headless Settings Class
 *
 * Manages plugin settings in WordPress admin.
 */
class Algolia_Headless_Settings {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'init_options' ) );
		add_action( 'init', array( $this, 'register_option' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		// Only load on reading settings page.
		if ( 'options-reading.php' !== $hook ) {
			return;
		}

		wp_add_inline_script(
			'jquery',
			$this->get_preview_script(),
			'after'
		);

		wp_add_inline_style(
			'wp-admin',
			$this->get_preview_styles()
		);
	}

	/**
	 * Get preview JavaScript
	 *
	 * @return string JavaScript code.
	 */
	private function get_preview_script() {
		$wp_url = home_url();
		return "
		jQuery(document).ready(function($) {
			var domainInput = $('#algolia_headless_domain');
			var previewContainer = $('<div id=\"algolia-headless-preview\" style=\"margin-top:10px;\"></div>');

			domainInput.after(previewContainer);

			function updatePreview() {
				var headlessDomain = domainInput.val().trim();
				var wpUrl = '" . esc_js( $wp_url ) . "';

				if (!headlessDomain) {
					previewContainer.html('<em style=\"color:#666;\">" . esc_js( __( 'Enter a domain to see preview', 'algolia-headless' ) ) . "</em>');
					return;
				}

				// Simple replacement logic (matches PHP logic)
				var wpParsed = new URL(wpUrl);
				var headlessParsed;
				try {
					headlessParsed = new URL(headlessDomain);
				} catch(e) {
					previewContainer.html('<span style=\"color:#d63638;\">" . esc_js( __( 'Invalid URL format', 'algolia-headless' ) ) . "</span>');
					return;
				}

				var samplePath = '/sample-post/';
				var beforeUrl = wpUrl + samplePath;
				var afterUrl = headlessDomain.replace(/\/$/, '') + samplePath;

				previewContainer.html(
					'<div style=\"background:#f0f0f1;padding:12px;border-left:4px solid #2271b1;\">' +
					'<strong>" . esc_js( __( 'URL Preview:', 'algolia-headless' ) ) . "</strong><br>' +
					'<span style=\"color:#666;\">" . esc_js( __( 'Before:', 'algolia-headless' ) ) . "</span> <code>' + beforeUrl + '</code><br>' +
					'<span style=\"color:#666;\">" . esc_js( __( 'After:', 'algolia-headless' ) ) . "</span> <code style=\"color:#2271b1;\">' + afterUrl + '</code>' +
					'</div>'
				);
			}

			domainInput.on('input', updatePreview);
			updatePreview();
		});
		";
	}

	/**
	 * Get preview CSS styles
	 *
	 * @return string CSS code.
	 */
	private function get_preview_styles() {
		return "
		#algolia-headless-preview {
			margin-top: 10px;
		}
		#algolia-headless-preview code {
			background: #fff;
			padding: 2px 6px;
			border-radius: 3px;
		}
		";
	}

	/**
	 * Register plugin option
	 */
	public function register_option() {
		register_setting(
			'reading',
			'algolia_headless_domain',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'show_in_rest'      => true,
				'default'           => '',
			)
		);
	}

	/**
	 * Initialize plugin options in admin
	 */
	public function init_options() {
		add_settings_section(
			'algolia_headless_settings',
			__( 'Algolia Headless extension', 'algolia-headless' ),
			array( $this, 'algolia_setting_description' ),
			'reading'
		);
		add_settings_field(
			'algolia_headless_domain',
			__( 'Public site domain', 'algolia-headless' ),
			array( $this, 'algolia_public_site_domain' ),
			'reading',
			'algolia_headless_settings'
		);
	}

	/**
	 * Display settings section description
	 */
	public function algolia_setting_description() {
		esc_html_e( 'You can replace the post domain from the WordPress to your public site.', 'algolia-headless' );
	}

	/**
	 * Display public site domain input field
	 */
	public function algolia_public_site_domain() {
		$value = get_option( 'algolia_headless_domain', '' );
		?>
		<input
			id="algolia_headless_domain"
			name="algolia_headless_domain"
			class="regular-text"
			type="url"
			value="<?php echo esc_attr( $value ); ?>"
			placeholder="https://example.com"
		/>
		<p class="description">
			<?php esc_html_e( 'Enter the full URL of your headless site (e.g., https://example.com)', 'algolia-headless' ); ?>
		</p>
		<?php
	}
}

/**
 * Algolia Headless Admin Notices Class
 *
 * Displays admin notices for configuration issues.
 */
class Algolia_Headless_Admin_Notices {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'display_notices' ) );
	}

	/**
	 * Display admin notices
	 */
	public function display_notices() {
		// Only show on admin pages.
		if ( ! is_admin() ) {
			return;
		}

		// Check if Algolia plugin is active.
		if ( ! Algolia_Headless_Helper::is_algolia_plugin_active() ) {
			$this->show_notice(
				__( 'Algolia Headless extension requires the <strong>WP Search with Algolia</strong> plugin to be installed and activated.', 'algolia-headless' ),
				'warning',
				'algolia-plugin-missing'
			);
		}

		// Check if headless domain is configured.
		$headless_domain = Algolia_Headless_Helper::get_headless_domain();
		if ( empty( $headless_domain ) ) {
			// Check if we're using constant.
			$using_constant = defined( 'ALGOLIA_HEADLESS_DOMAIN' );

			if ( ! $using_constant ) {
				$settings_url = admin_url( 'options-reading.php#algolia_headless_domain' );
				$this->show_notice(
					sprintf(
						/* translators: %s: Settings page URL */
						__( 'Algolia Headless domain is not configured. Please <a href="%s">configure it in Settings &raquo; Reading</a>.', 'algolia-headless' ),
						esc_url( $settings_url )
					),
					'info',
					'algolia-domain-not-configured'
				);
			}
		}

		// Validate domain format if configured.
		if ( ! empty( $headless_domain ) ) {
			$parsed = wp_parse_url( $headless_domain );
			if ( ! $parsed || ! isset( $parsed['host'] ) ) {
				$this->show_notice(
					sprintf(
						/* translators: %s: Invalid domain URL */
						__( 'Invalid headless domain format: <code>%s</code>. Please use a full URL (e.g., https://example.com).', 'algolia-headless' ),
						esc_html( $headless_domain )
					),
					'error',
					'algolia-invalid-domain'
				);
			} elseif ( isset( $parsed['scheme'] ) && 'http' === $parsed['scheme'] ) {
				// Warn about HTTP (not HTTPS).
				$this->show_notice(
					__( 'Your headless domain uses HTTP instead of HTTPS. Consider using HTTPS for better security.', 'algolia-headless' ),
					'warning',
					'algolia-http-warning',
					true
				);
			}
		}
	}

	/**
	 * Show admin notice
	 *
	 * @param string $message Notice message (HTML allowed).
	 * @param string $type Notice type (info, warning, error, success).
	 * @param string $id Unique notice ID for dismissible notices.
	 * @param bool   $dismissible Whether notice is dismissible.
	 */
	private function show_notice( $message, $type = 'info', $id = '', $dismissible = false ) {
		// Check if notice was dismissed.
		if ( $dismissible && $id && get_user_meta( get_current_user_id(), "algolia_headless_dismissed_{$id}", true ) ) {
			return;
		}

		$classes = array( 'notice', "notice-{$type}" );
		if ( $dismissible ) {
			$classes[] = 'is-dismissible';
		}

		printf(
			'<div class="%s" data-notice-id="%s"><p>%s</p></div>',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( $id ),
			wp_kses_post( $message )
		);
	}
}


/**
 * Algolia Headless Site Health Class
 *
 * Integrates with WordPress Site Health.
 */
class Algolia_Headless_Site_Health {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'site_status_tests', array( $this, 'add_tests' ) );
	}

	/**
	 * Add custom tests to Site Health
	 *
	 * @param array $tests Existing tests.
	 * @return array Modified tests.
	 */
	public function add_tests( $tests ) {
		$tests['direct']['algolia_headless_plugin'] = array(
			'label' => __( 'Algolia Headless Configuration', 'algolia-headless' ),
			'test'  => array( $this, 'test_configuration' ),
		);

		return $tests;
	}

	/**
	 * Test Algolia Headless configuration
	 *
	 * @return array Test result.
	 */
	public function test_configuration() {
		$result = array(
			'label'       => __( 'Algolia Headless is configured correctly', 'algolia-headless' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Performance', 'algolia-headless' ),
				'color' => 'blue',
			),
			'description' => '',
			'actions'     => '',
			'test'        => 'algolia_headless_plugin',
		);

		// Check if Algolia plugin is active.
		if ( ! Algolia_Headless_Helper::is_algolia_plugin_active() ) {
			$result['status']      = 'critical';
			$result['label']       = __( 'WP Search with Algolia plugin is not active', 'algolia-headless' );
			$result['description'] = sprintf(
				'<p>%s</p>',
				__( 'Algolia Headless extension requires the WP Search with Algolia plugin to function.', 'algolia-headless' )
			);
			return $result;
		}

		// Check if domain is configured.
		$headless_domain = Algolia_Headless_Helper::get_headless_domain();
		if ( empty( $headless_domain ) ) {
			$result['status']      = 'recommended';
			$result['label']       = __( 'Headless domain is not configured', 'algolia-headless' );
			$result['description'] = sprintf(
				'<p>%s</p>',
				__( 'Please configure the headless site domain in Settings &raquo; Reading.', 'algolia-headless' )
			);
			$result['actions']     = sprintf(
				'<a href="%s">%s</a>',
				admin_url( 'options-reading.php#algolia_headless_domain' ),
				__( 'Configure Domain', 'algolia-headless' )
			);
			return $result;
		}

		// Validate domain format.
		$parsed = wp_parse_url( $headless_domain );
		if ( ! $parsed || ! isset( $parsed['host'] ) ) {
			$result['status']      = 'critical';
			$result['label']       = __( 'Invalid headless domain format', 'algolia-headless' );
			$result['description'] = sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %s: Invalid domain */
					__( 'The configured domain <code>%s</code> is not a valid URL.', 'algolia-headless' ),
					esc_html( $headless_domain )
				)
			);
			return $result;
		}

		// Check HTTPS.
		if ( isset( $parsed['scheme'] ) && 'http' === $parsed['scheme'] ) {
			$result['status']      = 'recommended';
			$result['label']       = __( 'Headless domain uses HTTP instead of HTTPS', 'algolia-headless' );
			$result['description'] = sprintf(
				'<p>%s</p>',
				__( 'For better security, consider using HTTPS for your headless domain.', 'algolia-headless' )
			);
		}

		// Check if using constant.
		if ( defined( 'ALGOLIA_HEADLESS_DOMAIN' ) && ALGOLIA_HEADLESS_DOMAIN ) {
			$result['description'] = sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %s: Domain configured via constant */
					__( 'Domain is configured via ALGOLIA_HEADLESS_DOMAIN constant: <code>%s</code>', 'algolia-headless' ),
					esc_html( ALGOLIA_HEADLESS_DOMAIN )
				)
			);
		} else {
			$result['description'] = sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %s: Configured domain */
					__( 'Headless domain: <code>%s</code>', 'algolia-headless' ),
					esc_html( $headless_domain )
				)
			);
		}

		return $result;
	}
}


// Initialize plugin classes.
new Algolia_Headless_Admin_Notices();
new Algolia_Headless_Site_Health();
new Algolia_Headless_Settings();
new Algolia_Headless_Replacer();

// Register WP-CLI commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once dirname( __FILE__ ) . '/includes/class-wp-cli.php';
	WP_CLI::add_command( 'algolia-headless', 'Algolia_Headless_CLI' );
}