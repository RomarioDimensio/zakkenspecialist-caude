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
        file_exists($enqueue_url . '/components/shortcodes/algolia-product-search/algolia-product-search.js') ? filemtime($enqueue_url . '/components/shortcodes/algolia-product-search/algolia-product-search.js'): null,
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

    $default_index = function_exists('dim_algolia_index_prefix')
        ? dim_algolia_index_prefix() . 'searchable_posts'
        : 'wp_searchable_posts';

    $atts = shortcode_atts([
        'index'         => $default_index,   // Algolia index name
        'hits_per_page' => 24,
        'filters'       => "",
    ], $atts);

    if ($isTaxonomyPage) {
        // quotes: taxonomienamen kunnen spaties bevatten
        $atts['filters'] = "taxonomies.{$term->taxonomy}:'{$term->name}'";
    }

    wp_localize_script('dim-product-search', 'DIM_PRODUCT_SEARCH', [
        'appId'       => function_exists('dim_algolia_app_id') ? dim_algolia_app_id() : (defined('ALGOLIA_APP_ID') ? ALGOLIA_APP_ID : get_option('algolia_application_id', '')),
        'searchKey'   => function_exists('dim_algolia_search_key') ? dim_algolia_search_key() : (defined('ALGOLIA_SEARCH_KEY') ? ALGOLIA_SEARCH_KEY : get_option('algolia_search_api_key', '')),
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
                            <div id="dim-product-search-ais-searchbox"></div>
                        </div>

                        <div class="filter-wrapper">
                            <button class="filter-title filter-ais-dropdown-btn" aria-expanded="false"> Toepassing </button>
                            <div id="dim-product-search-filter-toepassing" class="filter-dropdown"></div>
                        </div>
                        <div class="filter-wrapper">
                            <button class="filter-title filter-ais-dropdown-btn" aria-expanded="false"> Producttype </button>
                            <div id="dim-product-search-filter-type" class="filter-dropdown"></div>
                        </div>
                        <div class="filter-wrapper">
                            <button class="filter-title filter-ais-dropdown-btn" aria-expanded="false"> Materiaal </button>
                            <div id="dim-product-search-filter-quality" class="filter-dropdown"></div>
                        </div>
                        <div class="filter-wrapper">
                            <button class="filter-title filter-ais-dropdown-btn" aria-expanded="false"> Kleur </button>
                            <div id="dim-product-search-filter-color" class="filter-dropdown"></div>
                        </div>
                        <div class="filter-wrapper">
                            <button class="filter-title filter-ais-dropdown-btn" aria-expanded="false"> Breedte (cm) </button>
                            <div id="dim-product-search-filter-width" class="filter-dropdown"></div>
                        </div>
                        <div class="filter-wrapper">
                            <button class="filter-title filter-ais-dropdown-btn" aria-expanded="false"> Lengte (cm) </button>
                            <div id="dim-product-search-filter-length" class="filter-dropdown"></div>
                        </div>
                        <div class="filter-wrapper">
                            <button class="filter-title filter-ais-dropdown-btn" aria-expanded="false"> Dikte </button>
                            <div class="filter-dropdown">
                                <div id="dim-product-search-filter-dikte-eenheid"></div>
                                <div id="dim-product-search-filter-dikte-waarde"></div>
                            </div>
                        </div>
                        <div class="filter-wrapper">
                            <button class="filter-title filter-ais-dropdown-btn" aria-expanded="false"> Verpakt </button>
                            <div id="dim-product-search-filter-verpakt" class="filter-dropdown"></div>
                        </div>
                        <div class="filter-wrapper">
                            <button class="filter-title filter-ais-dropdown-btn" aria-expanded="false"> Merk </button>
                            <div id="dim-product-search-filter-brand" class="filter-dropdown"></div>
                        </div>
                        <div class="filter-wrapper">
                            <button class="filter-title filter-ais-dropdown-btn" aria-expanded="false"> Opties </button>
                            <div class="filter-dropdown">
                                <div id="dim-product-search-filter-trekband"></div>
                                <div id="dim-product-search-filter-geperforeerd"></div>
                            </div>
                        </div>
                    </div>

                </aside>
            </div>
            <main class="dim-ais__results">
                <div class="dim-ais-body-actions">
                    <div id="dim-product-search-ais-stats"></div>
                    <div id="dim-product-search-ais-sortby"></div>
                </div>
                <?php // Skeleton-kaarten (server-side): reserveren direct de echte hoogte van
                      // het grid terwijl Algolia nog laadt. Zo komt een #anker-jump
                      // (bv. /onze-producten/#section-zakkencalculator) meteen goed uit en
                      // verspringt de pagina niet. JS haalt dit weg na de eerste render. ?>
                <div id="dim-product-search-ais-skeleton" class="dim-ais-skeleton" aria-hidden="true">
                    <?php for ($i = 0; $i < min(24, (int) $atts['hits_per_page']); $i++) : ?>
                        <div class="dim-ais-sk-card">
                            <div class="dim-ais-sk dim-ais-sk-thumb"></div>
                            <div class="dim-ais-sk-body">
                                <div class="dim-ais-sk dim-ais-sk-titel"></div>
                                <div class="dim-ais-sk dim-ais-sk-regel"></div>
                                <div class="dim-ais-sk dim-ais-sk-regel is-kort"></div>
                            </div>
                        </div>
                    <?php endfor; ?>
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
