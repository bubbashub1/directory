<?php
/**
 * BubbaHub Leader Dashboard loader – stable shortcode registration and theme integration.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'bubbahub_leader_dashboard_register', 99 );

function bubbahub_leader_dashboard_register() {
    $renderer = '';
    if ( function_exists( 'bubbahub_leader_dashboard_shortcode' ) ) {
        $renderer = 'bubbahub_leader_dashboard_shortcode';
    } elseif ( function_exists( 'bubbahub_leader_dashboard_render' ) ) {
        $renderer = 'bubbahub_leader_dashboard_render';
    }
    if ( ! $renderer ) return;

    remove_shortcode( 'bubbahub_leader_dashboard' );
    remove_shortcode( 'bubbahub-leader-dashboard' );
    add_shortcode( 'bubbahub_leader_dashboard', $renderer );
    add_shortcode( 'bubbahub-leader-dashboard', $renderer );
}

add_action( 'wp_enqueue_scripts', 'bubbahub_leader_theme_overrides', 20 );
function bubbahub_leader_theme_overrides() {
    if ( ! defined( 'BUBBAHUB_LEADER_DASHBOARD_URL' ) || ! defined( 'BUBBAHUB_LEADER_DASHBOARD_VERSION' ) ) return;

    $is_leader_page = is_page( 'leader' );
    $management_ids = function_exists( 'bubbahub_leader_management_page_ids' ) ? bubbahub_leader_management_page_ids() : array();
    $is_management_page = $management_ids && is_page( array_values( $management_ids ) );

    if ( ! $is_leader_page && ! $is_management_page ) return;

    $css = BUBBAHUB_LEADER_DASHBOARD_DIR . 'leader-theme-overrides.css';
    if ( ! file_exists( $css ) ) return;

    wp_enqueue_style(
        'bubbahub-leader-theme-overrides',
        BUBBAHUB_LEADER_DASHBOARD_URL . 'leader-theme-overrides.css',
        array( 'bubbahub-leader-dashboard' ),
        BUBBAHUB_LEADER_DASHBOARD_VERSION . '.1'
    );
}

bubbahub_leader_dashboard_register();
