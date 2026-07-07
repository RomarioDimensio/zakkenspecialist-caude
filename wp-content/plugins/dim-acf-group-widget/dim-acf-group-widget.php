<?php
/**
 * Plugin Name: Dimensio - Elementor Widget: ACF Group
 * Description: Elementor widget to render ACF Group subfields for the current post.
 * Author: Dimensio
 * Version: 1.0.0
 */

if ( ! defined('ABSPATH') ) exit;

add_action('plugins_loaded', function () {
    if ( ! did_action('elementor/loaded') || ! function_exists('get_field') ) {
        // Elementor or ACF missing
        return;
    }

    // Register widget
    add_action('elementor/widgets/register', function ($widgets_manager) {
        require_once __DIR__ . '/widget-acf-group.php';
        $widgets_manager->register( new \Dim\Widget_ACF_Group() );
    });

    add_action('elementor/frontend/after_register_styles', function() {
        wp_register_style(
            'dim-acf-group-widget',
             '/wp-content/plugins/dim-acf-group-widget/dim-acf-group-widget.css',
            [],
            filemtime('/wp-content/plugins/dim-acf-group-widget/dim-acf-group-widget.css')
        );
    });
});