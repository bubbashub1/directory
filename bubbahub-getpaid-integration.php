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

function bubbahub_getpaid_wallet_checkout( $booking_id ) {
    $booking_id = absint( $booking_id );
    if ( ! $booking_id || ! is_user_logged_in() ) return new WP_Error( 'wallet_login_required', 'Please log in to use your Bubba Hub Wallet.' );
    if ( ! function_exists( 'wpinv_insert_invoice' ) || ! function_exists( 'wpinv_create_item' ) || ! function_exists( 'wpinv_get_invoice' ) ) return new WP_Error( 'wallet_unavailable', 'GetPaid Wallet is not available on this site.' );

    $email = sanitize_email( get_post_meta( $booking_id, '_bh_customer_email', true ) );
    $user = wp_get_current_user();
    if ( $email && strtolower( $email ) !== strtolower( (string) $user->user_email ) ) return new WP_Error( 'wallet_customer_mismatch', 'Please use the same account that made the booking.' );

    $total = (float) get_post_meta( $booking_id, '_bh_total_price', true );
    if ( $total <= 0 ) return new WP_Error( 'payment_not_required', 'Payment is not required for this booking.' );

    $existing_invoice = absint( get_post_meta( $booking_id, '_bh_getpaid_wallet_invoice_id', true ) );
    if ( $existing_invoice ) {
        $existing = wpinv_get_invoice( $existing_invoice );
        if ( $existing && method_exists( $existing, 'needs_payment' ) && $existing->needs_payment() && method_exists( $existing, 'get_checkout_payment_url' ) ) return array( 'invoice_id' => $existing_invoice, 'url' => esc_url_raw( $existing->get_checkout_payment_url() ) );
    }

    $group_id = absint( get_post_meta( $booking_id, '_bh_group_id', true ) );
    $title = $group_id ? get_the_title( $group_id ) : 'Bubba Hub booking';
    $item = wpinv_create_item( array(
        'type' => 'custom',
        'title' => 'Bubba Hub booking #' . $booking_id . ' - ' . wp_strip_all_tags( $title ),
        'custom_id' => 'bh_booking_' . $booking_id,
        'price' => number_format( $total, 2, '.', '' ),
        'status' => 'publish',
        'editable' => false,
    ), true );
    if ( is_wp_error( $item ) || ! $item ) return new WP_Error( 'wallet_item_failed', 'Unable to prepare the booking payment.' );
    $item_id = is_object( $item ) && isset( $item->ID ) ? absint( $item->ID ) : absint( $item );
    if ( ! $item_id ) return new WP_Error( 'wallet_item_failed', 'Unable to prepare the booking payment item.' );

    $invoice = wpinv_insert_invoice( array(
        'status' => 'wpi-pending',
        'user_id' => get_current_user_id(),
        'cart_details' => array( array( 'id' => $item_id, 'quantity' => 1, 'custom_price' => number_format( $total, 2, '.', '' ), 'meta' => array( 'booking_id' => $booking_id, 'group_id' => $group_id ) ) ),
        'user_note' => 'Bubba Hub booking #' . $booking_id,
        'private_note' => 'Bubba Hub booking ID: ' . $booking_id,
    ), true );
    if ( is_wp_error( $invoice ) || ! $invoice ) return new WP_Error( 'wallet_invoice_failed', 'Unable to create the booking invoice.' );
    $invoice_id = is_object( $invoice ) && isset( $invoice->ID ) ? absint( $invoice->ID ) : absint( $invoice );
    if ( ! $invoice_id ) return new WP_Error( 'wallet_invoice_failed', 'Unable to create the booking invoice.' );

    update_post_meta( $invoice_id, '_bh_booking_id', $booking_id );
    update_post_meta( $booking_id, '_bh_getpaid_wallet_invoice_id', $invoice_id );
    update_post_meta( $booking_id, '_bh_payment_provider', 'getpaid_wallet' );
    update_post_meta( $booking_id, '_bh_payment_method', 'wallet' );

    $invoice_object = wpinv_get_invoice( $invoice_id );
    if ( ! $invoice_object || ! method_exists( $invoice_object, 'get_checkout_payment_url' ) ) return new WP_Error( 'wallet_checkout_missing', 'GetPaid could not create a wallet checkout URL.' );
    return array( 'invoice_id' => $invoice_id, 'url' => esc_url_raw( $invoice_object->get_checkout_payment_url() ) );
}

add_action( 'wpinv_update_status', function( $invoice_id, $new_status, $old_status ) {
    if ( 'publish' !== $new_status ) return;
    $booking_id = absint( get_post_meta( $invoice_id, '_bh_booking_id', true ) );
    if ( ! $booking_id ) return;
    update_post_meta( $booking_id, '_bh_payment_status', 'paid' );
    update_post_meta( $booking_id, '_bh_payment_method', 'wallet' );
    update_post_meta( $booking_id, '_bh_payment_provider', 'getpaid_wallet' );
    update_post_meta( $booking_id, '_bh_status', 'confirmed' );
}, 10, 3 );

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
    if ( 'wallet' === sanitize_key( $request->get_param( 'payment_method' ) ) ) {
        $wallet = bubbahub_getpaid_wallet_checkout( $booking_id );
        if ( is_wp_error( $wallet ) ) return new WP_Error( $wallet->get_error_code(), $wallet->get_error_message(), array( 'status' => 400 ) );
        return rest_ensure_response( array( 'success' => true, 'url' => $wallet['url'], 'booking_id' => $booking_id, 'provider' => 'getpaid_wallet', 'payment_method' => 'wallet' ) );
    }
    $status = get_post_meta( $booking_id, '_bh_payment_status', true );
    $total = (float) get_post_meta( $booking_id, '_bh_total_price', true );
    if ( $total <= 0 || 'not_required' === $status ) return new WP_Error( 'payment_not_required', 'Payment is not required for this booking.', array( 'status' => 409 ) );
    $existing = esc_url_raw( get_post_meta( $booking_id, '_bh_stripe_checkout_url', true ) );
    if ( $existing && 'paid' !== $status ) return rest_ensure_response( array( 'success' => true, 'url' => $existing, 'booking_id' => $booking_id, 'provider' => 'stripe' ) );
    $checkout = bubbahub_getpaid_create_checkout( $booking_id );
    if ( is_wp_error( $checkout ) ) return new WP_Error( $checkout->get_error_code(), $checkout->get_error_message(), array( 'status' => 400 ) );
    $wallet_url = '';
    if ( is_user_logged_in() && function_exists( 'bubbahub_getpaid_wallet_checkout' ) ) {
        $wallet = bubbahub_getpaid_wallet_checkout( $booking_id );
        if ( ! is_wp_error( $wallet ) && ! empty( $wallet['url'] ) ) $wallet_url = $wallet['url'];
    }
    return rest_ensure_response( array( 'success' => true, 'url' => $checkout['url'], 'wallet_url' => $wallet_url, 'booking_id' => $booking_id, 'provider' => 'stripe' ) );
}