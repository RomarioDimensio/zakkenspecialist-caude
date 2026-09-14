<?php
/**
 * Header-zoek: het vergrootglas rechts in de header klapt een zoekveld open dat
 * over de menu-items heen schuift. Enter zoekt in dezelfde productenzoeker als
 * /onze-producten/ (route-parameter ?q=, zie algolia-product-search.js).
 *
 * De CSS/JS worden automatisch geladen door includes/assets.php (elke map in
 * /components met gelijknamige .css/.js). Hier geven we alleen de URL van de
 * productenpagina mee, zodat we die niet hardcoderen in de JS.
 */

if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', function () {
    if (!wp_script_is('comp-header-search-js', 'registered')) return;

    $pagina = get_page_by_path('onze-producten');

    wp_localize_script('comp-header-search-js', 'DZS_HEADER_SEARCH', [
        'productenUrl' => $pagina ? get_permalink($pagina) : home_url('/onze-producten/'),
        'placeholder'  => 'Zoek een product...',
        'label'        => 'Zoek een product',
    ]);
}, 30); // na assets.php (priority 20), anders bestaat het script-handle nog niet
