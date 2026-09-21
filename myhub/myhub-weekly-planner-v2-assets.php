<?php
if ( ! defined( 'ABSPATH' ) ) exit;
add_action( 'wp_enqueue_scripts', function() {
    if ( ! is_user_logged_in() ) return;
    $should_load = false;
    if ( is_page( 'my-hub' ) || is_page( 'whats-on' ) ) $should_load = true;
    if ( is_singular() ) {
        global $post;
        if ( $post && ( has_shortcode( (string) $post->post_content, 'bubbahub_weekly_planner_v2' ) || has_shortcode( (string) $post->post_content, 'bubbahub_weekly_planner' ) ) ) $should_load = true;
    }
    if ( ! $should_load ) return;
    wp_register_style( 'bubbahub-myhub-weekly-planner-v2', BUBBAHUB_MYHUB_URL . 'myhub-weekly-planner-v2.css', array(), BUBBAHUB_MYHUB_VERSION );
    wp_enqueue_style( 'bubbahub-myhub-weekly-planner-v2' );
    wp_register_script( 'bubbahub-myhub-weekly-planner-v2', BUBBAHUB_MYHUB_URL . 'myhub-weekly-planner-v2.js', array( 'jquery' ), BUBBAHUB_MYHUB_VERSION, true );
    wp_enqueue_script( 'bubbahub-myhub-weekly-planner-v2' );
} );
