<?php

if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', function () {
    $theme_uri = get_stylesheet_directory_uri();
    $enqueue_url = get_stylesheet_directory();

    wp_register_style(
        'dim-product-search',
        $theme_uri . '/components/shortcodes/algolia-product-search/algolia-product-search.css',
        ['instantsearch-theme'],
        filemtime($enqueue_url . '/components/shortcodes/algolia-product-search/algolia-product-search.css')
    );

    wp_register_script(
        'dim-product-search',
        $theme_uri . '/components/shortcodes/algolia-product-search/algolia-product-search.js',
        ['instantsearch'],
        file_exists($theme_uri . '/components/shortcodes/algolia-product-search/algolia-product-search.js') ? filemtime($enqueue_url . '/components/shortcodes/algolia-product-search/algolia-product-search.js'): null,
        true
    );
});

// 2. Track if shortcode is used
global $dim_product_search_used;
$dim_product_search_used = false;

add_shortcode('dim_product_search', function ($atts = []) use (&$dim_product_search_used) {
    $dim_product_search_used = true;

    $term = get_queried_object();
    $isTaxonomyPage = is_tax() && $term;

    if ($isTaxonomyPage && (!$term || is_wp_error( $term ) || empty( $term->taxonomy ) || empty( $term->slug )) ) {
        return '';
    }

    $atts = shortcode_atts([
        'index'         => 'diff',        // Algolia index name
        'hits_per_page' => 24,
        'filters'       => "",
    ], $atts);

    if ($isTaxonomyPage) {
        $atts['filters'] = "taxonomies.category_product:$term->name";
    }

    // @todo define keys in php defines
    wp_localize_script('dim-product-search', 'DIM_PRODUCT_SEARCH', [
        'appId'       => defined('ALGOLIA_APP_ID') ? ALGOLIA_APP_ID : '',
        'searchKey'   => defined('ALGOLIA_SEARCH_KEY') ? ALGOLIA_SEARCH_KEY : '',
        'indexNameAlgoliaSearch'   => $atts['index'],
        'hitsPerPage' => (int) $atts['hits_per_page'],
        'filters'     => $atts['filters'],
        'isTaxonomyPage' => $isTaxonomyPage,
        'placeHolder'  => get_stylesheet_directory_uri() . '/components/shortcodes/algolia-product-search/placeholder-no-image.png',
    ]);

    ob_start(); ?>
    <div id="dim-products-fsearch" class="dim-ais">
        <div class="dim-ais__body">
            <div class="filter-pin-skeleton">
                <aside class="dim-ais__filters" id="dim-ais-filter-container">
                    <div id="dim-product-search-ais-clear"></div>

                    <div class="dim-ais-filters-inner-container">
                        <div class="filter-wrapper">

                            <?php if (!$isTaxonomyPage) : ?>
                            <button class="filter-title filter-ais-dropdown-btn" aria-expanded="false"> Category </button>
                            <div id="dim-product-search-filter-category" class="filter-dropdown"></div>
                            <?php endif; ?>
                        </div>
                        <div class="filter-wrapper">
                            <button class="filter-title filter-ais-dropdown-btn" aria-expanded="false"> Materiaal type </button>
                            <div id="dim-product-search-filter-material-type" class="filter-dropdown"></div>
                        </div>
                        <div class="filter-wrapper">
                            <button class="filter-title filter-ais-dropdown-btn" aria-expanded="false">Dikte</button>
                            <div id="dim-product-search-filter-material-thickness" class="filter-dropdown"></div>
                        </div>
                        <div class="filter-wrapper">
                            <button class="filter-title filter-ais-dropdown-btn" aria-expanded="false">Kleuren</button>
                            <div id="dim-product-search-filter-color" class="filter-dropdown"></div>
                        </div>
                        <div class="filter-wrapper">
                            <button class="filter-title filter-ais-dropdown-btn" aria-expanded="false">Formaat</button>
                            <div id="dim-product-search-filter-size" class="filter-dropdown"></div>
                        </div>
                        <div class="filter-wrapper">
                            <button class="filter-title filter-ais-dropdown-btn" aria-expanded="false">Kwaliteit</button>
                            <div id="dim-product-search-filter-quality" class="filter-dropdown"></div>
                        </div>
                        <div class="filter-wrapper">
                            <button class="filter-title filter-ais-dropdown-btn" aria-expanded="false">Recycled materiaal</button>
                            <div id="dim-product-search-filter-recycled-material" class="filter-dropdown"></div>
                        </div>
                    </div>

                </aside>
            </div>
            <main class="dim-ais__results">
                <div class="dim-ais-body-actions">
                    <div id="dim-product-search-ais-stats"></div>
                    <div id="dim-product-search-ais-sortby"></div>
                </div>
                <div id="dim-product-search-ais-hits"></div>
                <div id="dim-product-search-ais-pagination"></div>
            </main>
        </div>
    </div>
    <?php
    return ob_get_clean();
});

// enqueue only if shortcode was used
add_action('wp_footer', function () use (&$dim_product_search_used) {
    if ($dim_product_search_used) {
        wp_enqueue_style('dim-product-search');
        wp_enqueue_script('dim-product-search');
    }
});