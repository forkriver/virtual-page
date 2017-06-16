<?php
/**
 * Loader file for the Virtual Page plugin.
 *
 * @package forkriver\virtual-page
 * @since 1.0.0
 */

namespace forkriver\virtual_page;

/**
 * Plugin Name: Virtual Page
 * Description: Creates virtual pages for a site. Mostly for programmatic use by other plugins.
 * Author Name: Patrick Johanneson
 * Version: 1.0.0
 * Props: Based on an idea from @link https://return-true.com/create-a-virtual-page-using-rewrite-rules-in-wordpress/
 */


require 'class-virtual-page.php';

// Registers the query variables.
add_filter( 'query_vars', array( '\forkriver\virtual_page\Virtual_Page', 'query_vars' ) );
