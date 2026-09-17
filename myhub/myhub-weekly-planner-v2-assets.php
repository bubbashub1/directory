<?php
if ( ! defined( 'ABSPATH' ) ) exit;
add_action( 'wp_enqueue_scripts', function() {
    if ( ! is_user_logged_in() ) return;
    wp_register_style( 'bubbahub-myhub-weekly-planner-v2', BUBBAHUB_MYHUB_URL . 'myhub-weekly-planner-v2.css', array(), BUBBAHUB_MYHUB_VERSION );
    wp_enqueue_style( 'bubbahub-myhub-weekly-planner-v2' );
} );
