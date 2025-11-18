<?php
/**
 * Plugin Name:     Search with Algolia Headless extention
 * Plugin URI:      https://wp-kyoto.net
 * Description:     Simply extension for WP Search with Algolia. Replace the indices domain from the WordPress to custom domain.
 * Author:          Hidetaka Okamoto
 * Author URI:      https://wp-kyoto.net/en
 * Text Domain:     algolia-headless
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Algolia_Headless
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
	 * @return string|false
	 */
	private function get_headless_domain() {
		if ( null === $this->headless_domain ) {
			$this->headless_domain = get_option( 'algolia_headless_domain', false );
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
			return $url;
		}

		// Parse headless domain.
		$target = wp_parse_url( $replaced_url );
		if ( ! $target || ! isset( $target['host'] ) ) {
			return $url;
		}

		$path            = isset( $target['path'] ) ? $target['path'] : '';
		$replaced_domain = $target['host'] . $path;

		if ( empty( $replaced_domain ) ) {
			return $url;
		}

		// Parse original URL.
		$parsed_url = wp_parse_url( $url );
		if ( ! $parsed_url || ! isset( $parsed_url['host'] ) ) {
			return $url;
		}

		$replace_target = $parsed_url['host'];
		if ( isset( $parsed_url['port'] ) && $parsed_url['port'] ) {
			$replace_target .= ':' . $parsed_url['port'];
		}

		// Use str_replace instead of preg_replace to avoid ReDoS vulnerability.
		return str_replace( $replace_target, $replaced_domain, $url );
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
		$this->register_option();
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

new Algolia_Headless_Settings();
new Algolia_Headless_Replacer();