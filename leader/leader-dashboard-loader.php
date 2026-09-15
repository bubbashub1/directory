<?php
/** BubbaHub Leader Dashboard loader – stable shortcode registration. */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'bubbahub_leader_dashboard_register', 31 );

function bubbahub_leader_dashboard_register() {
    $renderer = function_exists( 'bubbahub_leader_dashboard_shortcode' ) ? 'bubbahub_leader_dashboard_shortcode' : ( function_exists( 'bubbahub_leader_dashboard_render' ) ? 'bubbahub_leader_dashboard_render' : '' );
    if ( ! $renderer ) return;

    remove_shortcode( 'bubbahub_leader_dashboard' );
    remove_shortcode( 'bubbahub-leader-dashboard' );
    add_shortcode( 'bubbahub_leader_dashboard', $renderer );
    add_shortcode( 'bubbahub-leader-dashboard', $renderer );
}

bubbahub_leader_dashboard_register();
