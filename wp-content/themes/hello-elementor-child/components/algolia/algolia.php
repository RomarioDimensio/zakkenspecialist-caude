<?php
// settings SEARCH ENGINGE Algolia
add_filter('algolia_should_index_post', function ($should, WP_Post $post) {
    return $post->post_type === 'product';
}, 10, 2);

add_filter('algolia_should_index_searchable_post', function ($should, WP_Post $post) {
    return $post->post_type === 'product';
}, 10, 2);

// Algolia credentials from Docker env
if (getenv('ALGOLIA_APP_ID')) {
    define('ALGOLIA_APP_ID', getenv('ALGOLIA_APP_ID'));
}
if (getenv('ALGOLIA_ADMIN_API_KEY')) {
    define('ALGOLIA_ADMIN_API_KEY', getenv('ALGOLIA_ADMIN_API_KEY'));
}
if (getenv('ALGOLIA_SEARCH_KEY')) {
    define('ALGOLIA_SEARCH_KEY', getenv('ALGOLIA_SEARCH_KEY'));
}

$attributesToSearch = [
    'unordered(post_title)',
    'unordered(algemene_informatie_artikelcode)',
];

add_filter('algolia_searchable_posts_index_settings', function ($settings) use ($attributesToSearch) {
    $settings['searchableAttributes'] = $attributesToSearch;

    // add facets these are necessary for filtering on attributes
    $settings['attributesForFaceting'] = [
        'searchable(taxonomies.category_product)',
        'searchable(bedrukkingsspecificaties_kleuren)',
        'searchable(materiaalspecificaties_type)',
        'searchable(materiaalspecificaties_dikte)',
        'searchable(algemene_informatie_formaat)',
        'searchable(materiaalspecificaties_kwaliteit)',
        'searchable(materiaalspecificaties_gebruik_van_recycled_materiaal)',
        'searchable(post_type)',
    ];

    $settings['replicas'] = [
        'wp2_searchable_posts_priority_asc',
        'wp2_searchable_posts_name_asc',
        'wp2_searchable_posts_date_desc',
    ];
    return $settings;
});

// Some themes/templates use wp_posts for the results page
add_filter('algolia_posts_index_settings', function ($settings) use ($attributesToSearch) {
    $settings['searchableAttributes'] = $attributesToSearch;
    return $settings;
});

function wds_algolia_custom_fields( array $attributes, WP_Post $post ) {

    if ($post->post_type !== 'product') {
        return $attributes;
    }

    // Eligible post meta fields.
    $fields = [
        'algemene_informatie_artikelcode',
        "algemene_informatie_omschrijving",
        "algemene_informatie_groep",
        "algemene_informatie_formaat",
        "algemene_informatie_verwijzing_naar_docvvo",
        "materiaalspecificaties_type",
        "materiaalspecificaties_pfas",
        "materiaalspecificaties_dikte",
        "materiaalspecificaties_kwaliteit",
        "materiaalspecificaties_gebruik_van_recycled_materiaal",
        "materiaalspecificaties_gebruik_van_polyethyleentereftalaat",
        "materiaalspecificaties_allergene_componenten",
        "materiaalspecificaties_bpa",
        "materiaalspecificaties_voldoet_aan_vlarema_wetgeving_va_2021",
        "materiaalspecificaties_voldoet_aan_vlarema_wetgeving_va_2025",
        "bedrukkingsspecificaties_bedrukking",
        "bedrukkingsspecificaties_kleuren",
        "verpakkingsspecificaties_verpakt_per",
        "verpakkingsspecificaties_afmetingen_colli",
        "verpakkingsspecificaties_aantal_per_pallet",
        "verpakkingsspecificaties_colli_per_laag",
        "verpakkingsspecificaties_colli_per_pallet",
        "geschiktheidsspecificaties_voedselveilig",
        "geschiktheidsspecificaties_opslagtijd_12_maanden",
        "geschiktheidsspecificaties_geschikt_voor",
        "geschiktheidsspecificaties_magnetronbestendig",
        "geschiktheidsspecificaties_ovenbestendig",
        "geschiktheidsspecificaties_diepvriesbestendig",
        "product_3d_model_shortcode",
        "merk_naam",
    ];

    // Loop over each field...
    foreach ( $fields as $field ) {
        $data = get_field( $field, $post->ID );

        // Only index when a field has content.
        if ( ! empty( $data )  ) {
            $attributes[ $field ] = (string) $data ;
        }
    }

    $priority = get_field('prioriteit', $post->ID);     // numeric
    $attributes['priority'] = is_numeric($priority) ? (int) $priority : 0;
    // choose one date format; timestamp sorts fastest:
    $attributes['post_date']  = (int) get_post_time('U', true, $post);       // unix ts (UTC)


    // add bigger image for better resolution
    if (has_post_thumbnail($post->ID)) {
        $attributes['medium_url'] = get_the_post_thumbnail_url($post->ID, 'medium');    // ~300px (default WP)
    }

    return $attributes;
}

add_filter( 'algolia_post_shared_attributes', 'wds_algolia_custom_fields', 10, 2 );
add_filter( 'algolia_searchable_post_shared_attributes', 'wds_algolia_custom_fields', 10, 2 );

// Requires: define('ALGOLIA_APP_ID','xxx'); define('ALGOLIA_ADMIN_API_KEY','xxx'); in wp-config.php
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    if (empty($_GET['dim_push_algolia_replicas'])) return;

    // Try to use the client bundled by the WP Algolia plugin. If not present, you can composer-require it.
    if (!class_exists(\Algolia\AlgoliaSearch\SearchClient::class)) {
        wp_die('Algolia PHP client not found. Make sure the WP Algolia plugin is active.');
    }

    $client = \Algolia\AlgoliaSearch\SearchClient::create(ALGOLIA_APP_ID, ALGOLIA_ADMIN_API_KEY);

    $main = 'wp2_searchable_posts';

    // Define the replicas you already declared in your index settings filter:
    $replicas = [
        "{$main}_name_asc"     => [ 'ranking' => ['asc(post_title)','typo','geo','words','filters','proximity','attribute','exact','custom'] ],
        "{$main}_priority_asc" => [ 'ranking' => ['asc(priority)','typo','geo','words','filters','proximity','attribute','exact','custom'] ],
        "{$main}_date_desc"    => [ 'ranking' => ['desc(post_date)','typo','geo','words','filters','proximity','attribute','exact','custom'] ],
    ];

    // Push ranking to each replica
    foreach ($replicas as $replicaName => $settings) {
        $client->initIndex($replicaName)->setSettings($settings);
    }

    // Ensure the main index lists these replicas (idempotent)
    $client->initIndex($main)->setSettings([ 'replicas' => array_keys($replicas) ]);

    wp_die('Replica settings pushed to Algolia. You can now use sortBy with these replicas.');
});
