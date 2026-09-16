<?php
/**
 * BubbaHub Leader Dashboard loader – stable shortcode registration.
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

bubbahub_leader_dashboard_register();
