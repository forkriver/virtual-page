<?php
/**
 * Core class file for the Virtual Page plugin.
 *
 * @package forkriver\virtual-page
 * @since 1.0.0
 */

namespace forkriver\virtual_page;

/**
 * Virtual Page class.
 *
 * Provides the ability to add virtual pages to the site. Includes tools to add pages to BU's left-hand nav.
 *
 * @since 1.0.0
 */
class Virtual_Page {

	/**
	 * The prefix for the plugin.
	 *
	 * @var const PREFIX The plugin prefix.
	 * @since 1.0.0
	 */
	const PREFIX = '_frvp_';

	/**
	 * The query var to register.
	 *
	 * @var const QUERY_VAR The string that should be added to the query_vars.
	 * @since 1.0.0
	 */
	const QUERY_VAR = 'fr_virtual_page';

	/**
	 * The page slug, title, etc. desired by the plugin.
	 *
	 * @var array $default_args The defaults for the arguments.
	 * @since 1.0.0
	 */
	public static $default_page_args = array(
		'slug'        => '',
		'title'       => '',
		'show_in_nav' => true,
		'menu_order'  => 0,
	);

	/**
	 * Should a rewrite be triggered?
	 *
	 * @var boolean trigger_rewrite_flush Whether a flush of the rewrite rules should be triggered. Defaults to false.
	 * @since 1.0.0
	 */
	public static $trigger_rewrite_flush = false;

	/**
	 * Class constructor.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	function __construct() {

		add_action( 'init', array( $this, 'virtual_page_init' ) );

		add_action( 'forkriver_virtual_page_init', array( $this, 'parse_virtual_page_registry' ), 10000 );
		add_action( 'forkriver_virtual_page_init', array( $this, 'maybe_flush_rules' ), 10001 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'template_include', array( $this, 'template_selector' ) );

		add_action( 'pre_get_posts', array( $this, 'virtual_page_query' ) );
		add_filter( 'the_content', array( $this, 'virtual_page_content' ) );
		add_filter( 'the_title', array( $this, 'virtual_page_title' ) );

	}

	/**
	 * Init hook for the virtual page system to use.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function virtual_page_init() {
		/**
		 * Provides an action hook for plugins to add virtual pages.
		 *
		 * @since 1.0.0
		 */
		do_action( 'forkriver_virtual_page_init' );
	}

	/**
	 * Registers a virtual page.
	 *
	 * @param array $_page The page to be registered.
	 * @return boolean|WP_Error True if successful, or a WP_Error on failure.
	 * @since 1.0.0
	 */
	public static function register( $_page ) {
		$registry = get_option( Virtual_Page::PREFIX . 'registry', array() );
		$page = wp_parse_args( $_page, Virtual_Page::$default_page_args );
		if ( ! $page['slug'] || ! $page['title'] ) {
			if ( WP_DEBUG ) {
				error_log( 'Error: Your virtual page requires at least a slug and a title. (Line ' . __LINE__ . ' in ' . __FILE__ . ')' );
			}
			return new WP_Error( 'missing_required_item', 'Your virtual page requires at least a slug and a title', $page );
		}
		// Sanitizes the page slug.
		$page['slug'] = Virtual_Page::sanitize_slug( $page['slug'] );
		if ( empty( $registry['registered_pages'] ) ) {
			$registry['registered_pages'] = array();
		}
		$registry['registered_pages'][ $page['slug'] ] = $page;
		$registry['updated'] = time();
		update_option( Virtual_Page::PREFIX . 'registry', $registry );
		return $page;
	}

	/**
	 * Parses the virtual page registry, adding and pruning pages as appropriate.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function parse_virtual_page_registry() {
		$trigger_rewrite_flush = Virtual_Page::$trigger_rewrite_flush;
		$registry = get_option( Virtual_Page::PREFIX . 'registry' );
		// Adds the new virtual pages to the list.
		if ( ! empty( $registry['registered_pages'] ) ) {
			foreach ( $registry['registered_pages'] as $slug => $registered_page ) {
				if ( empty( $registry['virtual_pages'][ $slug ] ) ) {
					$registry['virtual_pages'][ $slug ] = $registered_page;
					if ( ! $trigger_rewrite_flush ) {
						$trigger_rewrite_flush = true;
					}
				}
			}
		}

		// Prunes the virtual pages that no longer need to be in the site.
		$pages_to_remove = array_diff( array_keys( $registry['virtual_pages'] ), array_keys( $registry['registered_pages'] ) );
		if ( ! empty( $pages_to_remove ) ) {
			foreach ( $pages_to_remove as $page_to_remove ) {
				unset( $registry['virtual_pages'][ $page_to_remove ] );
			}
			if ( ! $trigger_rewrite_flush ) {
				$trigger_rewrite_flush = true;
			}
		}

		// Removes the "added" list.
		unset( $registry['registered_pages'] );

		// Updates the stored registry.
		update_option( Virtual_Page::PREFIX . 'registry', $registry );

		// Adds the rewrite rules.
		foreach ( array_keys( $registry['virtual_pages'] ) as $slug ) {
			Virtual_Page::add_rewrite( $slug );
		}

		// If there's a reason to trigger the rewrites, do so.
		if ( $trigger_rewrite_flush ) {
			Virtual_Page::$trigger_rewrite_flush = true;
		}
	}

	/**
	 * Adds a rewrite for a virtual page.
	 *
	 * @param array $slug The slug of the virtual page to be added to the rewrite rules.
	 * @return void
	 * @since 1.0.0
	 */
	public static function add_rewrite( $slug ) {
		add_rewrite_rule( '^(' . $slug . ')$', 'index.php?' . Virtual_Page::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * Optionally flushes the rewrite rules for the given page. Normally, this should happen only once.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	function maybe_flush_rules() {
		if ( true === Virtual_Page::$trigger_rewrite_flush ) {
			flush_rewrite_rules();
			Virtual_Page::$trigger_rewrite_flush = false;
		}
	}

	/**
	 * Registers our query_var.
	 *
	 * @param array $vars The query variables.
	 * @return array The filtered query variables.
	 */
	function query_vars( $vars ) {
		if ( ! in_array( Virtual_Page::QUERY_VAR, $vars ) ) {
			$vars[] = Virtual_Page::QUERY_VAR;
		}
		return $vars;
	}

	/**
	 * Selects the template we want for the virtual page.
	 *
	 * @param string $template The current template.
	 * @return string The filtered template.
	 * @since 1.0.0
	 */
	function template_selector( $template ) {
		$page_slug = get_query_var( Virtual_Page::QUERY_VAR, false );
		if ( false !== $page_slug ) {
			$page_slug = str_replace( '/', '__slash__', $page_slug );
			$template_names = array( "page-{$page_slug}.php", 'page.php', 'index.php' );
			$located_template = locate_template( $template_names );
			if ( '' !== $located_template ) {
				$template = $located_template;
			}
			$template = apply_filters( "fr_virtual_page_template_{$page_slug}", $template );
		}
		return $template;
	}

	/**
	 * Modifies the query for a virtual page.
	 *
	 * @param WP_Query $query The query object. Passed by reference, so no need to return it.
	 * @return void
	 * @since 1.0.0
	 */
	function virtual_page_query( $query ) {
		$slug = get_query_var( Virtual_Page::QUERY_VAR, false );
		if ( false !== $slug && $query->is_main_query() ) {
			$registry = get_option( Virtual_Page::PREFIX . 'registry', array() );
			if ( ! empty( $registry['virtual_pages'] ) && ! empty( $registry['virtual_pages'][ $slug ] ) ) {
				$title = $registry['virtual_pages'][ $slug ]['title'];
			}
			$query->set( 'post_type', 'page' );
			$query->set( 'p', null );
			$query->set( 'page_id', null );
			$query->set( 'post_title', $title );
			$query->set( 'post_content', 'some default content' );
			$query->set( 'is_page', true );
			$query->set( 'posts_per_page', 1 );
		}
	}

	/**
	 * Filters the virtual page content.
	 *
	 * @param string $content The content.
	 * @return string The filtered content.
	 */
	function virtual_page_content( $content ) {
		if ( in_the_loop() ) {
			global $wp_query;
			$page_slug = get_query_var( Virtual_Page::QUERY_VAR, false );
			if ( false !== $page_slug ) {
				$page_slug = str_replace( '/', '__slash__', $page_slug );
				/**
				 * Filters the content for a given virtual page.
				 *
				 * @param string $content The content.
				 * @since 1.0.0
				 */
				$content = apply_filters( 'fr_virtual_page_content_' . $page_slug, $content );
			}
		}
		return $content;
	}

	/**
	 * Filters the title of the virtual page.
	 *
	 * @param string $title The title.
	 * @return string The filtered title.
	 */
	function virtual_page_title( $title ) {
		if ( in_the_loop() ) {
			$page_slug = get_query_var( Virtual_Page::QUERY_VAR, false );
			if ( false !== $page_slug ) {
				// Get the default title from the registry.
				$registry = get_option( Virtual_Page::PREFIX . 'registry' );
				if ( ! empty( $registry['virtual_pages'] ) && ! empty( $registry['virtual_pages'][ $page_slug ] ) ) {
					$title = $registry['virtual_pages'][ $page_slug ]['title'];
				}
				$page_slug = str_replace( '/', '__slash__', $page_slug );
				/**
				 * Filters the title for a given virtual page.
				 *
				 * @param string $title The title.
				 * @since 1.0.0
				 */
				$title = apply_filters( 'fr_virtual_page_title_' . $page_slug, $title );
			}
		}
		return $title;
	}

	/**
	 * Sanitizes a virtual page slug. Allows for slashes in the slug.
	 *
	 * @param string $_slug The desired page slug.
	 * @return string The sanitized slug.
	 */
	public static function sanitize_slug( $_slug ) {
		$slug = str_replace( '/', '__slash__', $_slug );
		$slug = sanitize_title( $slug );
		$slug = str_replace( '__slash__', '/', $slug );
		return $slug;
	}

}

new \forkriver\virtual_page\Virtual_Page;

/**
 * Usage notes.
 *
 * @usage: register_virtual_page( array( 'slug' => '{your page slug}', 'title' => '{Your Page Title}' ) );
 * @usage: filter content with buvp_page_content_{$page_slug} (replace slashes in the slug with '_' )
 * @usage: filter template with buvp_page_template_{$page_slug} (replace slashes in the slug with '_' )
 */

/**
 * Helper function to register virtual pages.
 *
 * @param array $page The page arguments.
 * @return boolean|WP_Error True on success, WP_Error on failure.
 */
function register_virtual_page( $page = array() ) {
	return \forkriver\virtual_page\Virtual_Page::register( $page );
}
