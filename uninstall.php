<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

delete_option( 'algolia_headless_domain' );