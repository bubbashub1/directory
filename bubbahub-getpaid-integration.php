<?php
/**
 * BubbaHub payment compatibility bridge.
 *
 * The booking engine historically called this file GetPaid. The live payment
 * provider is now Stripe Connect, so this file intentionally contains no
 * GetPaid API calls. It keeps the existing booking/JavaScript hooks stable
 * while routing them to Stripe.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$stripe_file = plugin_dir_path( __FILE__ ) . 'bubbahub-stripe-integration.php';
if ( file_exists( $stripe_file ) ) require_once $stripe_file;

function bubbahub_getpaid_settings() {
    if ( function_exists( 'bubbahub_stripe_settings' ) ) {
        $stripe = bubbahub_stripe_settings();
        return array(
            'enabled'       => ! empty( $stripe['enabled'] ),
            'environment'   => 'live',
            'client_id'     => '',
            'client_secret' => '',
            'seller_id'     => '',
            'currency'      => isset( $stripe['currency'] ) ? $stripe['currency'] : 'GBP',
        );
    }
    return array( 'enabled' => false, 'environment' => 'live', 'client_id' => '', 'client_secret' => '', 'seller_id' => '', 'currency' => 'GBP' );
}

function bubbahub_getpaid_create_checkout( $booking_id ) {
    if ( function_exists( 'bubbahub_stripe_create_checkout' ) ) return bubbahub_stripe_create_checkout( $booking_id );
    return new WP_Error( 'payment_unavailable', 'Stripe payment integration is not loaded.' );
}

function bubbahub_getpaid_find_booking_compat( $request ) {
    $submission_id = absint( $request->get_param( 'submission_id' ) );
    $session_id = absint( $request->get_param( 'session_id' ) );
    $email = sanitize_email( $request->get_param( 'email' ) );
    if ( $submission_id ) {
        $posts = get_posts( array( 'post_type' => 'bh_booking', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_bh_ninja_submission_id', 'value' => $submission_id, 'compare' => '=' ) ) ) );
        if ( ! empty( $posts ) ) return absint( $posts[0] );
    }
    if ( $session_id && $email ) {
        $posts = get_posts( array( 'post_type' => 'bh_booking', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_query' => array( 'relation' => 'AND', array( 'key' => '_bh_session_id', 'value' => $session_id, 'compare' => '=' ), array( 'key' => '_bh_customer_email', 'value' => $email, 'compare' => '=' ) ), 'orderby' => 'ID', 'order' => 'DESC' ) );
        if ( ! empty( $posts ) ) return absint( $posts[0] );
    }
    return 0;
}

add_action( 'rest_api_init', function() {
    register_rest_route( 'bubbahub/v1', '/getpaid/checkout', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => 'bubbahub_getpaid_checkout_compat_rest' ) );
} );

function bubbahub_getpaid_checkout_compat_rest( WP_REST_Request $request ) {
    $booking_id = bubbahub_getpaid_find_booking_compat( $request );
    if ( ! $booking_id ) return new WP_Error( 'booking_not_found', 'Booking not found.', array( 'status' => 404 ) );
    $status = get_post_meta( $booking_id, '_bh_payment_status', true );
    $total = (float) get_post_meta( $booking_id, '_bh_total_price', true );
    if ( $total <= 0 || 'not_required' === $status ) return new WP_Error( 'payment_not_required', 'Payment is not required for this booking.', array( 'status' => 409 ) );
    $existing = esc_url_raw( get_post_meta( $booking_id, '_bh_stripe_checkout_url', true ) );
    if ( $existing && 'paid' !== $status ) return rest_ensure_response( array( 'success' => true, 'url' => $existing, 'booking_id' => $booking_id, 'provider' => 'stripe' ) );
    $checkout = bubbahub_getpaid_create_checkout( $booking_id );
    if ( is_wp_error( $checkout ) ) return new WP_Error( $checkout->get_error_code(), $checkout->get_error_message(), array( 'status' => 400 ) );
    return rest_ensure_response( array( 'success' => true, 'url' => $checkout['url'], 'booking_id' => $booking_id, 'provider' => 'stripe' ) );
}
