<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:

if ( !function_exists( 'chld_thm_cfg_locale_css' ) ):
    function chld_thm_cfg_locale_css( $uri ) {
        if ( empty( $uri ) && is_rtl() && file_exists( get_template_directory() . '/rtl.css' ) )
            $uri = get_template_directory_uri() . '/rtl.css';
        return $uri;
    }
endif;
add_filter( 'locale_stylesheet_uri', 'chld_thm_cfg_locale_css' );
         
if ( !function_exists( 'child_theme_configurator_css' ) ):
    function child_theme_configurator_css() {
        wp_enqueue_style( 'chld_thm_cfg_child', trailingslashit( get_stylesheet_directory_uri() ) . 'style.css', array( 'hello-elementor','hello-elementor-theme-style','hello-elementor-header-footer' ) );
    }
endif;
add_action( 'wp_enqueue_scripts', 'child_theme_configurator_css', 10 );

// END ENQUEUE PARENT ACTION

require_once __DIR__ . '/includes/assets.php';

// require once what is in the components folder
foreach (glob(__DIR__ . '/components/*/*.php') as $php) {
    require_once $php;
}

// require once ADMIN folder
foreach (glob(__DIR__ . '/components/admin/*/*.php') as $php) {
    require_once $php;
}

// --------------------------       Add in the wp admin pages the column last edited        --------------------------
add_filter('manage_pages_columns', function ($cols) {
    $cols['last_modified'] = 'Laatst bewerkt';
    return $cols;
});

// Kolom vullen
add_action('manage_pages_custom_column', function ($col, $post_id) {
    if ($col === 'last_modified') {
        $p = get_post($post_id);
        echo esc_html(get_the_modified_date('d-m-Y H:i', $p));
        $user = get_userdata(get_post_meta($post_id, '_edit_last', true));
        if ($user) echo '<br><small>door ' . esc_html($user->display_name) . '</small>';
    }
}, 10, 2);

// Sorteerbaar maken
add_filter('manage_edit-page_sortable_columns', function ($cols) {
    $cols['last_modified'] = 'modified';
    return $cols;
});

// --------------------------      END of Column adding column on wp admin pages        --------------------------

// Add upload svg
function add_file_types_to_uploads($file_types){
    $new_filetypes = array();
    $new_filetypes['svg'] = 'image/svg+xml';
    $new_filetypes['woff'] = 'font/woff';
    $new_filetypes['woff2'] = 'font/woff2';
    $file_types = array_merge($file_types, $new_filetypes );
    return $file_types;
}
add_filter('upload_mimes', 'add_file_types_to_uploads');


add_filter( 'bp3d_model_attribute', function ($defaults){
    return wp_parse_args( [
        'interaction-prompt' => 'none'
    ], $defaults );
}, 10, 2 );



add_filter('elementor_pro/theme_builder/archive_template/taxonomies', function ($taxonomies) {

    $taxonomies['product-group'] = 'Product Groups';

    $taxonomies['category_product'] = 'Categories producten';

    return $taxonomies;

});

