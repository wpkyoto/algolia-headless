<?php
/**
 * Plugin Name:     Search with Algolia Headless extention
 * Plugin URI:      https://wp-kyoto.net
 * Description:     Simply extension for WP Search with Algolia. Replace the indices domain from the WordPress to custom domain.
 * Author:          Hidetaka Okamoto
 * Author URI:      https://wp-kyoto.net/en
 * Text Domain:     algolia-headless-mode
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Algolia_Headless
 */

// Your code starts here.


class Algolia_Headless_Replacer {
    function __construct() {
        add_filter( 'algolia_term_record', array( $this, "replace_algolia_permalink" ), 10, 1);
        add_filter( 'algolia_post_shared_attributes', array( $this, "replace_algolia_permalink" ), 10, 1);
        add_filter( 'algolia_searchable_post_shared_attributes', array( $this, "replace_algolia_permalink" ), 10, 1);
        add_filter( 'algolia_user_record', array( $this, "replace_algolia_posts_url" ), 10, 1);
    }

    public function replace_algolia_permalink_to_public_site_domain ( $url ) {
        $replaced_url = get_option( 'algolia_headless_domain' );
        if ( ! $replaced_url ) return $url;
        $target = wp_parse_url( $replaced_url );
        $path = $target['path'] ? $target['path']: '';
        $replaced_domain = $target['host'] . $path;
        if ( ! $replaced_domain ) return $url;
        $parsed_url     = wp_parse_url( $url );
        $replace_target = $parsed_url['host'];
        if ( isset( $parsed_url['port'] ) && $parsed_url['port'] ) {
            $replace_target .= ":{$parsed_url['port']}";
        }
        return preg_replace( "#{$replace_target}#i", $replaced_domain, $url );
    }
    
    public function replace_algolia_permalink( $shared_attributes ) {
        $shared_attributes['permalink'] = $this->replace_algolia_permalink_to_public_site_domain( $shared_attributes['permalink'] );
        return $shared_attributes;
    }
    
    public function replace_algolia_posts_url( $user ) {
        $user['posts_url'] = $this->replace_algolia_permalink_to_public_site_domain( $user['posts_url'] );
        return $user;
    }
}


class Algolia_Headless_Settings {
	function __construct() {
        add_action( 'admin_init', array( $this, 'init_options' ) );
        add_action( 'init', array( $this, 'register_opion' ) );
    }
    
    public function register_opion() {
		register_setting( 'reading', 'algolia_headless_domain', array(
			'type' => 'string',
			'sanitize_callback' => 'esc_url',
			'show_in_rest' => true,
		) );
    }

	public function init_options() {
        $this->register_opion();
		add_settings_section(
			'algolia_headless_settings',
			__( 'Algolia Headless extension', 'algolia-headless' ),
			array( $this, 'algolia_setting_description' ),
			'reading',
		);
		add_settings_field(
			'algolia_headless_domain',
			__( 'Public site domain', 'algolia-headless' ),
			array( $this, 'algolia_public_site_domain' ),
			'reading',
			'algolia_headless_settings',
		);
    }
    
	public function algolia_setting_description() {
		_e( 'You can replace the post domain from the WordPress to your public site.', 'algolia-headless' );
	}

	public function algolia_public_site_domain() {
		?>
		<input
			id="algolia_headless_domain"
			name="algolia_headless_domain"
            class="regular-text"
			type="text"
			value="<?php form_option('algolia_headless_domain'); ?>"
		/>
		<?php
	}
}

new Algolia_Headless_Settings();
new Algolia_Headless_Replacer();