<?php
// Enqueue on post edit screens so we can add the modal near the ACF taxonomy field
add_action('admin_enqueue_scripts', function ($hook) {
    if (!in_array($hook, ['post.php','post-new.php'], true)) return;
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');


    wp_enqueue_script('acf-color-repeater-js',
        get_stylesheet_directory_uri().'/components/admin/acf-color-repeater/acf-color-repeater.js',
        ['jquery','wp-color-picker', 'acf-input'],
        filemtime(get_stylesheet_directory().'/components/admin/acf-color-repeater/acf-color-repeater.js'),
        true
    );
    wp_localize_script('acf-color-repeater-js', 'DIM_ADD_COLOR', [
        'nonce' => wp_create_nonce('dim_add_color_term'),
        'ajax'  => admin_url('admin-ajax.php'),
        'taxonomy' => 'product_kleur',
    ]);
});

// AJAX: create term + save hex and return JSON
add_action('wp_ajax_dim_create_color_term', function () {
    check_ajax_referer('dim_add_color_term','nonce');

    if (!current_user_can('manage_categories')) {
        wp_send_json_error(['message' => 'Geen rechten.'], 403);
    }

    if (!$_POST['product_id']) {
        wp_send_json_error(['message' => 'Geen Product meegegeven'], 403);
    }

    $tax  = sanitize_key($_POST['taxonomy'] ?? 'product_kleur');
    $name = sanitize_text_field($_POST['name'] ?? '');
    $hex  = sanitize_text_field($_POST['hex'] ?? '');
    $product_id = sanitize_key($_POST['product_id'] ?? '');

    if (!$name) wp_send_json_error(['message' => 'Naam is verplicht.'], 400);

    $res = wp_insert_term($name, $tax);

    if (is_wp_error($res)) wp_send_json_error(['message' => $res->get_error_message()], 400);

    $term_id = (int)$res['term_id'];
    if ($hex) update_term_meta($term_id, 'term_color', $hex);

    $response = wp_set_object_terms((int)$product_id, $term_id, $tax, true);

    if (is_wp_error($response)){
        wp_send_json_error(['message' => $response->get_error_message()], 400);
    }

    $term = get_term($term_id, $tax);
    wp_send_json_success([
        'id'   => $term->term_id,
        'name' => $term->name,
        'slug' => $term->slug,
        'hex'  => $hex,
    ]);
});