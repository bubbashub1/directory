<?php
/**
 * BubbaHub platform bootstrap.
 * Loads the core booking/payment stack before the optional leader and My Hub layers.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'BUBBAHUB_PLATFORM_VERSION' ) ) define( 'BUBBAHUB_PLATFORM_VERSION', '2.0.0' );

$bubbahub_platform_files = array(
    'bubbahub-booking-engine.php',
    'bubbahub-stripe-integration.php',
    'bubbahub-getpaid-integration.php',
    'modules/core/bubbahub-schedule-foundation.php',
    'modules/core/bubbahub-schedule-engine.php',
    'myhub/myhub-booking-lifecycle.php',
    'modules/core/bubbahub-ninja-stripe-bridge.php',
);

foreach ( $bubbahub_platform_files as $bubbahub_platform_file ) {
    $bubbahub_platform_path = dirname( __DIR__, 2 ) . '/' . $bubbahub_platform_file;
    if ( file_exists( $bubbahub_platform_path ) ) require_once $bubbahub_platform_path;
}

add_action( 'admin_notices', function() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $settings = function_exists( 'bubbahub_stripe_settings' ) ? bubbahub_stripe_settings() : array();
    if ( ! empty( $settings['enabled'] ) && empty( $settings['secret_key'] ) ) {
        echo '<div class="notice notice-warning"><p><strong>Bubba Hub:</strong> Stripe payments are enabled but the Stripe secret key is missing.</p></div>';
    }
} );
