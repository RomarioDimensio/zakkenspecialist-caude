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
 * De CSS van de detailtemplates meesturen met de productenpagina.
 *
 * HET PROBLEEM
 * Het detailtemplate wordt pas gerenderd in een losse admin-ajax-request. De
 * HTML komt daar keurig uit, mét alle classes, maar de bijbehorende CSS niet:
 * die hoort bij de <head> van een pagina, en die is op dat moment al verstuurd.
 *
 * WAAROM 'css_print_method' OP internal ZETTEN NIET HELPT
 * Dat stuurt alleen de klassieke per-post CSS inline mee. Elementor V4 zet de
 * styling van atomic elements in een heel ander stelsel: elke render meldt zijn
 * post-ID aan via de action 'elementor/post/render', en aan het eind van
 * wp_enqueue_scripts bouwt Elementor daaruit de bestanden
 * local-<id>-frontend-*.css en global-<id>-frontend-*.css.
 * Zie elementor/includes/frontend.php (rond regel 690) en
 * elementor/modules/atomic-widgets/styles/atomic-styles-manager.php.
 *
 * In de AJAX-request wordt die eerste action wél afgevuurd, maar het moment
 * waarop Elementor de bestanden bouwt komt nooit — dat hoort bij een normale
 * paginaopbouw. Resultaat: styling die wel berekend wordt maar nergens landt.
 *
 * DE OPLOSSING
 * Meld de detailtemplates aan bij het opbouwen van de productenpagina zélf.
 * Eén do_action per template is genoeg; het template hoeft niet gerenderd te
 * worden, alleen aangemeld. Elementor neemt ze dan mee in dezelfde ronde als
 * de pagina zelf, en de CSS staat in de <head> nog vóór er iets via AJAX
 * binnenkomt.
 *
 * TIMING — dit is het addertje. Aanmelden moet gebeuren vóórdat Elementor de
 * verzamelde ID's verwerkt. Op wp_enqueue_scripts priority 15 ben je al te
 * laat: de lijst was dan al doorgegeven als [4319, 1676, 200] (footer, header,
 * de pagina zelf) en onze templates kwamen er ná. Daarom haken we in op
 * 'elementor/frontend/before_enqueue_styles', dat afgevuurd wordt binnen
 * Elementors eigen enqueue_styles() en dus gegarandeerd op het juiste moment.
 */
add_action('elementor/frontend/before_enqueue_styles', function () {
    if (!is_page('onze-producten') && !is_page('just-gloves')) return;

    // Zelfde ID's als in products-overview.js (fetchProductHtml).
    foreach ([1498, 3626] as $template_id) {
        do_action('elementor/post/render', $template_id);

        // De klassieke per-post CSS voor wat er nog niet atomic is.
        if (class_exists('\Elementor\Core\Files\CSS\Post')) {
            \Elementor\Core\Files\CSS\Post::create($template_id)->enqueue();
        }
    }
});

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
            <div class="dim-product-loading"><span class="dim-spinner" role="status" aria-label="Laden"></span></div>
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
/**
 * [dim_product_varianten] — zet de variantkiezers in het detailpaneel AAN.
 *
 * De kleur-, dikte-, formaat-, vorm- en verpakkingsknoppen zijn gebouwd en
 * werken, maar staan niet in het design van 2026. In plaats van de code te
 * slopen hangt hij achter deze schakelaar: zolang deze shortcode niet in het
 * detailtemplate staat, rendert products-overview.js de knoppen niet.
 *
 * Willen ze de knoppen terug? Sleep een Shortcode-widget in het Elementor-
 * detailtemplate (post 1498) en vul [dim_product_varianten] in. Klaar — de
 * knoppen zoeken hun eigen plek in het paneel op.
 *
 * Deze div is puur de schakelaar, geen mountpunt: de knoppen worden door de JS
 * verspreid over de bestaande rijen van het template geïnjecteerd.
 */
add_shortcode('dim_product_varianten', function () {
    return '<div id="dim-varianten" class="dim-varianten-schakelaar" hidden></div>';
});
