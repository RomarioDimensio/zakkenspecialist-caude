<?php
/**
 * Plugin Name: Dimensio - Scroll Sequence (Elementor + GSAP)
 * Description: Elementor widget for scroll-based image sequences using GSAP ScrollTrigger.
 * Version: 0.1.0
 * Author: Romario Dindajal
 */

if (!defined('ABSPATH')) exit;

define('DIM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DIM_PLUGIN_PATH', plugin_dir_path(__FILE__));

require_once __DIR__ . '/includes/admin-sequences.php';
add_action('plugins_loaded', ['Dim_Sequences_Admin', 'init']);

add_action('plugins_loaded', function () {
    // Ensure Elementor is loaded
    if (!did_action('elementor/loaded')) {
        return;
    }

    // Register scripts/styles (Elementor loads widget deps only when needed)
    add_action('elementor/frontend/after_register_scripts', 'dim_register_scripts');
    add_action('elementor/frontend/after_register_styles', 'dim_register_styles');

    // Register widgets
    add_action('elementor/widgets/register', function($widgets_manager) {
        require_once DIM_PLUGIN_PATH . 'widgets/dim-scroll-sequence-widget.php';
        $widgets_manager->register(new \Dim_Scroll_Sequence_Widget());
    });

});

function dim_register_scripts(): void
{
    // Vendor GSAP
    wp_enqueue_script( 'gsap-js', 'https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/gsap.min.js', [], false, true );
    // ScrollTrigger - with gsap.js passed as a dependency
    wp_enqueue_script( 'gsap-st', 'https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/ScrollTrigger.min.js', ['gsap-js'], false, true );
    wp_enqueue_script( 'gsap-sto', 'https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/ScrollToPlugin.min.js', ['gsap-js'], false, true );

    // Widget JS
    wp_register_script(
        'dim-scroll-sequence',
        DIM_PLUGIN_URL . 'assets/js/dim-scroll-sequence.js',
        ['gsap-sto'],
        '0.1.0',
        true
    );
}

function dim_register_styles(): void
{
    wp_register_style(
        'dim-scroll-sequence',
        DIM_PLUGIN_URL . 'assets/css/dim-scroll-sequence.css',
        [],
        '0.1.0'
    );
}