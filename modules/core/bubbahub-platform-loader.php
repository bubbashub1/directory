<?php
/**
 * BubbaHub platform bootstrap.
 * Loads the core booking/payment, schedule and family hub stack.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! defined( 'BUBBAHUB_PLATFORM_VERSION' ) ) define( 'BUBBAHUB_PLATFORM_VERSION', '2.1.2' );

$bubbahub_platform_files = array(
    'bubbahub-booking-engine.php',
    'bubbahub-stripe-integration.php',
    'bubbahub-getpaid-integration.php',
    'modules/core/bubbahub-schedule-foundation.php',
    'modules/core/bubbahub-schedule-engine.php',
    'modules/core/bubbahub-family-support.php',
    'modules/core/bubbahub-family-favourites.php',
    'modules/core/bubbahub-subscriptions.php',
    'modules/core/bubbahub-booking-flow.php',
    'myhub/myhub-booking-lifecycle.php',
    'myhub/myhub-booking-payment.php',
    'modules/core/bubbahub-ninja-stripe-bridge.php',
);

foreach ( $bubbahub_platform_files as $bubbahub_platform_file ) {
    $bubbahub_platform_path = dirname( __DIR__, 2 ) . '/' . $bubbahub_platform_file;
    if ( ! file_exists( $bubbahub_platform_path ) ) continue;
    try {
        require_once $bubbahub_platform_path;
    } catch ( Throwable $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
            error_log( 'BubbaHub platform module skipped: ' . $bubbahub_platform_file . ' — ' . $e->getMessage() );
        }
    }
}

add_action( 'wp_enqueue_scripts', function() {
    if ( ! defined( 'BUBBAHUB_DIRECTORY_URL' ) || ! defined( 'BUBBAHUB_DIRECTORY_VERSION' ) ) return;
    wp_register_style( 'bubbahub-family-support', BUBBAHUB_DIRECTORY_URL . 'assets/family-support.css', array(), BUBBAHUB_DIRECTORY_VERSION );
    if ( is_page() ) wp_enqueue_style( 'bubbahub-family-support' );
} );

add_action( 'admin_notices', function() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $settings = function_exists( 'bubbahub_stripe_settings' ) ? bubbahub_stripe_settings() : array();
    if ( ! empty( $settings['enabled'] ) && empty( $settings['secret_key'] ) ) {
        echo '<div class="notice notice-warning"><p><strong>Bubba Hub:</strong> Stripe payments are enabled but the Stripe secret key is missing.</p></div>';
    }
} );