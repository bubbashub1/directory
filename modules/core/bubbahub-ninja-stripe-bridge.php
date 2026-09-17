<?php
/**
 * BubbaHub Ninja Forms -> Stripe booking bridge.
 * Runs after the existing Ninja booking importer, so it never duplicates a booking.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'ninja_forms_after_submission', 'bubbahub_ninja_stripe_after_submission', 35 );
function bubbahub_ninja_stripe_after_submission( $form_data ) {
    if ( ! is_array( $form_data ) || ! function_exists( 'bubbahub_booking_create' ) || ! function_exists( 'bubbahub_stripe_create_checkout' ) ) return;
    if ( ! function_exists( 'bubbahub_stripe_settings' ) ) return;
    $stripe = bubbahub_stripe_settings();
    if ( empty( $stripe['enabled'] ) || empty( $stripe['secret_key'] ) ) return;

    $submission_id = ! empty( $form_data['id'] ) ? absint( $form_data['id'] ) : 0;
    if ( ! $submission_id ) return;

    $bookings = get_posts( array(
        'post_type' => 'bh_booking', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids',
        'meta_query' => array( array( 'key' => '_bh_ninja_submission_id', 'value' => $submission_id, 'compare' => '=' ) ),
        'no_found_rows' => true,
    ) );
    if ( empty( $bookings ) ) return;
    $booking_id = absint( $bookings[0] );

    $total = (float) get_post_meta( $booking_id, '_bh_total_price', true );
    if ( $total <= 0 ) return;

    // Do not replace an existing provider checkout.
    $provider = sanitize_key( get_post_meta( $booking_id, '_bh_payment_provider', true ) );
    if ( $provider && 'stripe' !== $provider ) return;
    if ( get_post_meta( $booking_id, '_bh_stripe_checkout_session_id', true ) ) return;

    $checkout = bubbahub_stripe_create_checkout( $booking_id );
    if ( is_wp_error( $checkout ) ) {
        update_post_meta( $booking_id, '_bh_payment_status', 'failed' );
        update_post_meta( $booking_id, '_bh_status', 'payment_failed' );
        update_post_meta( $booking_id, '_bh_stripe_error', sanitize_text_field( $checkout->get_error_message() ) );
        return;
    }

    // Reserve capacity while the customer is on Stripe Checkout. The webhook
    // promotes this reservation to confirmed after Stripe reports payment.
    update_post_meta( $booking_id, '_bh_status', 'reserved' );
    update_post_meta( $booking_id, '_bh_payment_status', 'pending' );
    update_post_meta( $booking_id, '_bh_payment_method', 'stripe' );
}

add_filter( 'ninja_forms_submit_response', 'bubbahub_ninja_stripe_submit_response', 40, 1 );
function bubbahub_ninja_stripe_submit_response( $response ) {
    // Ninja Forms responses vary between versions. We intentionally leave the
    // normal success response intact; the payment link is surfaced in My Hub,
    // where the booking owner is authenticated before Stripe Checkout starts.
    return $response;
}
