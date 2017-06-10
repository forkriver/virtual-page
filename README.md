# Virtual Pages

Provides an interface for plugins to define virtual pages (ie, pages that are not part of WordPress's content stream).

## Usage

To create a virtual page:

```php
add_action( 'forkriver_virtual_page_init', 'my_virtual_page_creator' );
function my_virtual_page_creator() {
    if ( function_exists( '\forkriver\virtual_page\register_virtual_page' ) ) {
        \forkriver\virtual_page\register_virtual_page( array(
            'slug'        => 'desired-slug',  // required
            'title'       => 'My Page Title', // required
        ) );
    }
}
```

### Filters

Filters exist for the virtual page's content, title, and template. In these filters, replace any slash characters (`/`) with `__slash__`. Eg., to target the virtual page at `my-page/my-child-page`, the `$slug` should be `my-page__slash__my-child-page`.

Content filtering:

```php
add_filter( "fr_virtual_page_content_{$slug}", 'my_virtual_page_content' );
function my_virtual_page_content( $content ) {
    // Make changes to $content here.
    // If you want to use WP's automatic formatting, you need to explicitly add it:
    $content = wpautop( $content );
    return $content;
}
```

By default, the content is unset.

Title filtering:

```php
add_filter( "fr_virtual_page_title_{$slug}", 'my_virtual_page_title' );
function my_virtual_page_title( $title ) {
    // Make changes to $title here.
    return $title;
}
```

By default, the title is the value of the `title` used in the `\forkriver\virtual_page\register_virtual_page()` call.

Template filtering:

```php
add_filter( "fr_virtual_page_template_{$slug}", 'my_virtual_page_template' );
function my_virtual_page_template( $template ) {
    // Make changes to $template here.
    return $template;
}
```

By default, `$template` is `page-{$slug}.php`, `page.php`, or `index.php` in your active theme.

## Todo

* Implement `show_in_nav` and `menu_order`.
* `__()` all the (text) things.