<?php
add_action('wp_enqueue_scripts', function ($hook) {
    $theme_uri = get_stylesheet_directory_uri();
    $theme_dir = get_stylesheet_directory();

    // Don't add the files when EDITING IN ELEMENTOR
    if ( defined('ELEMENTOR_VERSION') && isset($_GET['elementor-preview']) && isset($_GET['elementor_library']) && str_starts_with($_GET['elementor_library'], 'elementor-header') ) {
        return;
    }

    // Helper
    $enqueue = function ($handle, $path, $deps = [], $in_footer = true) use ($theme_dir, $theme_uri) {
        $file = $theme_dir . $path;
        if (!file_exists($file)) return;
        $ver = filemtime($file);
        $ext = pathinfo($file, PATHINFO_EXTENSION);

        if ($ext === 'css') {
            wp_enqueue_style($handle, $theme_uri . $path, [], $ver);
        } elseif ($ext === 'js') {
            wp_enqueue_script($handle, $theme_uri . $path, $deps, $ver, $in_footer);
        }

        if($handle == "comp-algolia-js") {
            wp_localize_script($handle, 'DIM_PRODUCT_SEARCH_MEGA_MENU', [
                'appId'       => function_exists('dim_algolia_app_id') ? dim_algolia_app_id() : (defined('ALGOLIA_APP_ID') ? ALGOLIA_APP_ID : ''),
                'searchKey'   => function_exists('dim_algolia_search_key') ? dim_algolia_search_key() : (defined('ALGOLIA_SEARCH_KEY') ? ALGOLIA_SEARCH_KEY : ''),
                'placeHolder'  => get_stylesheet_directory_uri() . '/components/shortcodes/algolia-product-search/placeholder-no-image.png',
            ]);
        }
    };

    // ----- LOAD css styling -------

    // Scan /components for css/js
    $dirs = glob($theme_dir . '/components/*', GLOB_ONLYDIR);

    foreach ($dirs as $key => $dir) {
        $slug = basename($dir);
        $css  = "/components/$slug/$slug.css";
        $js   = "/components/$slug/$slug.js";

        // Optional “guard”: only enqueue when a page needs it
        // Example rule: load agolia js and css only when needed
        // @todo fix the right guard and loading
        if ($slug === 'products-overview' && (!is_page('onze-producten') && !is_page('just-gloves'))) {
            continue;
        }

        $enqueue("comp-$slug-css", $css);
        $enqueue("comp-$slug-js",  $js, ['gsap-st', 'gsap-sto', 'instantsearch']);
    }

    // ENQUEUE GSAP library for the animation/scroll triggers
    // The core GSAP library
    wp_enqueue_script( 'gsap-js', 'https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/gsap.min.js', array(), false, true );
    // ScrollTrigger - with gsap.js passed as a dependency
    wp_enqueue_script( 'gsap-st', 'https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/ScrollTrigger.min.js', array('gsap-js'), false, true );
    wp_enqueue_script( 'gsap-sto', 'https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/ScrollToPlugin.min.js', array('gsap-js'), false, true );

    wp_enqueue_script(
        'algoliasearch-lite',
        'https://cdn.jsdelivr.net/npm/algoliasearch@4/dist/algoliasearch-lite.umd.js',
        [],
        null,
        true
    );

    wp_enqueue_script(
        'instantsearch',
        'https://cdn.jsdelivr.net/npm/instantsearch.js@4/dist/instantsearch.production.min.js',
        ['algoliasearch-lite'],
        null,
        true
    );

    wp_enqueue_style('instantsearch-theme', 'https://cdn.jsdelivr.net/npm/instantsearch.css@8/themes/satellite-min.css', [], null);

}, 20);