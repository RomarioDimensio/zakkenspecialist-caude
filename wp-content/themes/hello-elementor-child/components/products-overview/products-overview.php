<?php

add_action('acf/save_post', 'dim_set_wp_post_id_field', 20);

function dim_set_wp_post_id_field($post_id): void
{

    // Only for normal posts/CPTs, not options/users/terms.
    if (!is_numeric($post_id)) {
        return;
    }

    // Optional: only for your product post type
    if (get_post_type($post_id) !== 'product') {
        return;
    }

    // Save the real WordPress post ID into the ACF field
    update_field('custom_product_url', home_url('/onze-producten/#product=' . $post_id), $post_id);
}

add_filter('acf/prepare_field/name=wp_post_id', 'dim_hide_wp_post_id_field_for_editors');

function dim_hide_wp_post_id_field_for_editors($field) {
    if (!current_user_can('manage_options')) {
        return false;
    }

    return $field;
}