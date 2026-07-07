<?php


function dim_render_product_detail(int $product_id, int $template_id): string
{
    if (!$product_id || get_post_status($product_id) !== 'publish') {
        return '<div class="dim-product-empty">Product not found.</div>';
    }

    global $post;
    $original_post = $post;

    $post = get_post($product_id);
    setup_postdata($post);

    $html = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display($template_id, true);

    wp_reset_postdata();
    $post = $original_post;

    return $html ?: '<div class="dim-product-empty">Template not found.</div>';
}

/**
 * AJAX: load one product detail.
 */
function dim_ajax_get_product_detail(): void
{
    $product_id = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;
    $template_id = isset($_GET['template_id']) ? (int) $_GET['template_id'] : 1498;

    if (!$product_id) {
        wp_send_json_error(['message' => 'Missing product ID.'], 400);
    }

    $html = dim_render_product_detail($product_id, $template_id);

    wp_send_json_success([
        'html' => $html,
    ]);
}
add_action('wp_ajax_dim_get_product_detail', 'dim_ajax_get_product_detail');
add_action('wp_ajax_nopriv_dim_get_product_detail', 'dim_ajax_get_product_detail');

/**
 * Shortcode for the right detail panel container.
 * Use this in an Elementor Shortcode widget.
 */
function dim_product_detail_panel_shortcode(): string
{
    ob_start();
    ?>
    <div class="dim-product-panel-shell">
        <div id="dim-product-detail-content" class="dim-product-detail-content-wrap">
            <div class="dim-product-empty">Select a product to view details.</div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('dim_product_detail_panel', 'dim_product_detail_panel_shortcode');

/**
 * Enqueue frontend assets.
 */
function dim_enqueue_product_panel_assets(): void
{
    if (is_admin()) {
        return;
    }

    wp_enqueue_script( 'barcode-js', 'https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js', array(), false, true );


    wp_localize_script('dim-product-panel', 'dimProductPanel', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'queryParam' => 'product',
    ]);
}
add_action('wp_enqueue_scripts', 'dim_enqueue_product_panel_assets');