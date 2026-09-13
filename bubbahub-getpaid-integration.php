<?php
/**
 * BubbaHub Booking Engine — GetPaid integration.
 *
 * Creates GetPaid hosted checkouts for Book Now bookings, exposes the
 * checkout URL to the booking page, and confirms bookings only after the
 * GetPaid checkout has been verified as completed.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function bubbahub_getpaid_settings() {
    return array(
        'enabled'       => (bool) get_option( 'bubbahub_getpaid_enabled', false ),
        'environment'   => get_option( 'bubbahub_getpaid_environment', 'sandbox' ),
        'client_id'     => trim( (string) get_option( 'bubbahub_getpaid_client_id', '' ) ),
        'client_secret' => trim( (string) get_option( 'bubbahub_getpaid_client_secret', '' ) ),
        'seller_id'     => trim( (string) get_option( 'bubbahub_getpaid_seller_id', '' ) ),
        'currency'      => strtoupper( trim( (string) get_option( 'bubbahub_getpaid_currency', 'GBP' ) ) ),
    );
}

function bubbahub_getpaid_api_base() {
    $settings = bubbahub_getpaid_settings();
    return 'live' === $settings['environment']
        ? 'https://api.getpaid.io/v2'
        : 'https://api.sandbox.getpaid.io/v2';
}

function bubbahub_getpaid_audience() {
    $settings = bubbahub_getpaid_settings();
    return 'live' === $settings['environment']
        ? 'https://api.getpaid.io'
        : 'https://api.sandbox.getpaid.io';
}

function bubbahub_getpaid_access_token() {
    $settings = bubbahub_getpaid_settings();
    if ( ! $settings['client_id'] || ! $settings['client_secret'] ) return new WP_Error( 'getpaid_credentials', 'GetPaid Client ID and Client Secret are not configured.' );

    $cache_key = 'bubbahub_getpaid_token_' . md5( $settings['environment'] . '|' . $settings['client_id'] );
    $cached = get_transient( $cache_key );
    if ( $cached ) return $cached;

    $response = wp_remote_post( 'https://auth.getpaid.io/oauth/token', array(
        'timeout' => 20,
        'headers' => array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
        'body' => wp_json_encode( array(
            'client_id' => $settings['client_id'],
            'client_secret' => $settings['client_secret'],
            'audience' => bubbahub_getpaid_audience(),
            'grant_type' => 'client_credentials',
        ) ),
    ) );

    if ( is_wp_error( $response ) ) return $response;
    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( $code < 200 || $code >= 300 || empty( $body['access_token'] ) ) {
        $message = ! empty( $body['message'] ) ? sanitize_text_field( $body['message'] ) : 'GetPaid authentication failed.';
        return new WP_Error( 'getpaid_auth', $message, array( 'status' => $code ) );
    }

    $expires = ! empty( $body['expires_in'] ) ? absint( $body['expires_in'] ) : 3600;
    set_transient( $cache_key, sanitize_text_field( $body['access_token'] ), max( 60, $expires - 60 ) );
    return $body['access_token'];
}

function bubbahub_getpaid_request( $method, $path, $body = null, $idempotency_key = '' ) {
    $token = bubbahub_getpaid_access_token();
    if ( is_wp_error( $token ) ) return $token;

    $headers = array(
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    );
    if ( $idempotency_key ) $headers['Getpaid-Idempotency-Key'] = preg_replace( '/[^A-Za-z0-9_-]/', '', $idempotency_key );

    $args = array( 'method' => strtoupper( $method ), 'timeout' => 25, 'headers' => $headers );
    if ( null !== $body ) $args['body'] = wp_json_encode( $body );

    $response = wp_remote_request( trailingslashit( bubbahub_getpaid_api_base() ) . ltrim( $path, '/' ), $args );
    if ( is_wp_error( $response ) ) return $response;

    $code = wp_remote_retrieve_response_code( $response );
    $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( $code < 200 || $code >= 300 ) {
        $message = 'GetPaid API request failed.';
        if ( is_array( $decoded ) ) {
            if ( ! empty( $decoded['message'] ) ) $message = sanitize_text_field( $decoded['message'] );
            elseif ( ! empty( $decoded['detail'] ) ) $message = sanitize_text_field( $decoded['detail'] );
        }
        return new WP_Error( 'getpaid_api', $message, array( 'status' => $code, 'response' => $decoded ) );
    }
    return is_array( $decoded ) ? $decoded : array();
}

function bubbahub_getpaid_create_checkout( $booking_id ) {
    $booking_id = absint( $booking_id );
    if ( ! $booking_id || 'bh_booking' !== get_post_type( $booking_id ) ) return new WP_Error( 'invalid_booking', 'Invalid BubbaHub booking.' );

    $settings = bubbahub_getpaid_settings();
    if ( ! $settings['enabled'] ) return new WP_Error( 'getpaid_disabled', 'GetPaid is not enabled.' );
    if ( ! $settings['seller_id'] ) return new WP_Error( 'getpaid_seller', 'GetPaid seller account ID is not configured.' );

    $amount = (float) bubbahub_booking_meta( $booking_id, '_bh_total_price', 0 );
    if ( $amount <= 0 ) return new WP_Error( 'zero_amount', 'This booking does not require payment.' );

    $existing_url = get_post_meta( $booking_id, '_bh_getpaid_checkout_url', true );
    $existing_id = get_post_meta( $booking_id, '_bh_getpaid_checkout_id', true );
    $existing_status = get_post_meta( $booking_id, '_bh_getpaid_checkout_status', true );
    if ( $existing_url && $existing_id && ! in_array( $existing_status, array( 'failed', 'unattempted' ), true ) ) {
        return array( 'id' => $existing_id, 'url' => $existing_url, 'status' => $existing_status ?: 'initiated' );
    }

    $reference = 'BH-' . $booking_id;
    $redirect = add_query_arg(
        array( 'bh_payment' => 'complete', 'booking' => $booking_id ),
        home_url( '/myhub/' )
    );
    $webhook = rest_url( 'bubbahub/v1/getpaid/webhook' );

    $payload = array(
        'reference' => $reference,
        'amount' => (int) round( $amount * 100 ),
        'currency' => $settings['currency'] ?: 'GBP',
        'description' => 'Bubba Hub booking #' . $booking_id,
        'payment_methods' => array( 'type' => 'default' ),
        'splits' => array(
            'type' => 'per_transaction',
            'accounts' => array(
                array(
                    'type' => 'seller',
                    'id' => $settings['seller_id'],
                    'split' => array( 'type' => 'remaining' ),
                ),
            ),
        ),
        'redirect' => array( 'default' => esc_url_raw( $redirect ) ),
        'webhooks' => array( 'url' => esc_url_raw( $webhook ) ),
    );

    $idempotency = 'bh_checkout_' . $booking_id;
    $result = bubbahub_getpaid_request( 'POST', '/checkouts', $payload, $idempotency );
    if ( is_wp_error( $result ) ) return $result;

    $checkout_id = ! empty( $result['id'] ) ? sanitize_text_field( $result['id'] ) : '';
    $checkout_url = ! empty( $result['flow']['next_step']['url'] ) ? esc_url_raw( $result['flow']['next_step']['url'] ) : '';
    $status = ! empty( $result['status'] ) ? sanitize_key( $result['status'] ) : 'initiated';

    if ( ! $checkout_id || ! $checkout_url ) return new WP_Error( 'getpaid_checkout_response', 'GetPaid returned an incomplete checkout response.' );

    update_post_meta( $booking_id, '_bh_getpaid_checkout_id', $checkout_id );
    update_post_meta( $booking_id, '_bh_getpaid_checkout_url', $checkout_url );
    update_post_meta( $booking_id, '_bh_getpaid_checkout_status', $status );
    update_post_meta( $booking_id, '_bh_getpaid_reference', $reference );
    update_post_meta( $booking_id, '_bh_getpaid_currency', $settings['currency'] ?: 'GBP' );
    update_post_meta( $booking_id, '_bh_getpaid_amount_minor', (int) round( $amount * 100 ) );

    return array( 'id' => $checkout_id, 'url' => $checkout_url, 'status' => $status );
}

function bubbahub_getpaid_find_booking_by_submission( $submission_id ) {
    $submission_id = absint( $submission_id );
    if ( ! $submission_id ) return 0;
    $ids = get_posts( array(
        'post_type' => 'bh_booking', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids',
        'meta_query' => array( array( 'key' => '_bh_ninja_submission_id', 'value' => $submission_id, 'compare' => '=' ) ),
    ) );
    return ! empty( $ids ) ? absint( $ids[0] ) : 0;
}

function bubbahub_getpaid_find_booking_by_reference( $reference ) {
    $reference = sanitize_text_field( $reference );
    if ( '' === $reference || 0 !== strpos( $reference, 'BH-' ) ) return 0;
    $booking_id = absint( substr( $reference, 3 ) );
    return $booking_id && 'bh_booking' === get_post_type( $booking_id ) ? $booking_id : 0;
}

function bubbahub_getpaid_verify_checkout( $checkout_id ) {
    $checkout_id = sanitize_text_field( $checkout_id );
    if ( '' === $checkout_id ) return new WP_Error( 'invalid_checkout', 'Invalid GetPaid checkout.' );
    return bubbahub_getpaid_request( 'GET', '/checkouts/' . rawurlencode( $checkout_id ) );
}

add_action( 'rest_api_init', function() {
    register_rest_route( 'bubbahub/v1', '/getpaid/checkout', array(
        'methods' => WP_REST_Server::READABLE,
        'permission_callback' => '__return_true',
        'callback' => 'bubbahub_getpaid_checkout_endpoint',
    ) );
    register_rest_route( 'bubbahub/v1', '/getpaid/webhook', array(
        'methods' => WP_REST_Server::CREATABLE,
        'permission_callback' => '__return_true',
        'callback' => 'bubbahub_getpaid_webhook_endpoint',
    ) );
} );

function bubbahub_getpaid_checkout_endpoint( WP_REST_Request $request ) {
    $submission_id = absint( $request->get_param( 'submission_id' ) );
    $session_id = absint( $request->get_param( 'session_id' ) );
    $email = sanitize_email( $request->get_param( 'email' ) );

    $booking_id = bubbahub_getpaid_find_booking_by_submission( $submission_id );
    if ( ! $booking_id && $session_id && is_email( $email ) ) {
        $ids = get_posts( array(
            'post_type' => 'bh_booking', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids',
            'orderby' => 'date', 'order' => 'DESC',
            'meta_query' => array(
                array( 'key' => '_bh_session_id', 'value' => $session_id, 'compare' => '=' ),
                array( 'key' => '_bh_customer_email', 'value' => $email, 'compare' => '=' ),
                array( 'key' => '_bh_payment_method', 'value' => 'getpaid', 'compare' => '=' ),
            ),
            'date_query' => array( array( 'after' => '10 minutes ago', 'inclusive' => true ) ),
        ) );
        $booking_id = ! empty( $ids ) ? absint( $ids[0] ) : 0;
    }

    if ( ! $booking_id ) return new WP_Error( 'booking_not_found', 'Booking not found.', array( 'status' => 404 ) );
    if ( 'getpaid' !== bubbahub_booking_meta( $booking_id, '_bh_payment_method', '' ) ) return new WP_Error( 'payment_not_required', 'This booking is not awaiting GetPaid payment.', array( 'status' => 409 ) );

    $url = get_post_meta( $booking_id, '_bh_getpaid_checkout_url', true );
    if ( ! $url ) {
        $created = bubbahub_getpaid_create_checkout( $booking_id );
        if ( is_wp_error( $created ) ) return $created;
        $url = $created['url'];
    }

    return rest_ensure_response( array( 'success' => true, 'booking_id' => $booking_id, 'url' => esc_url_raw( $url ) ) );
}

function bubbahub_getpaid_webhook_endpoint( WP_REST_Request $request ) {
    $payload = $request->get_json_params();
    if ( ! is_array( $payload ) ) return new WP_Error( 'invalid_webhook', 'Invalid webhook payload.', array( 'status' => 400 ) );

    $event_type = isset( $payload['type'] ) ? sanitize_key( $payload['type'] ) : '';
    $data = isset( $payload['data'] ) && is_array( $payload['data'] ) ? $payload['data'] : array();
    $checkout_id = ! empty( $data['id'] ) ? sanitize_text_field( $data['id'] ) : '';
    if ( ! $checkout_id && ! empty( $data['origin']['id'] ) ) $checkout_id = sanitize_text_field( $data['origin']['id'] );

    if ( ! $checkout_id ) return new WP_Error( 'missing_checkout', 'Webhook did not contain a checkout ID.', array( 'status' => 400 ) );

    $checkout = bubbahub_getpaid_verify_checkout( $checkout_id );
    if ( is_wp_error( $checkout ) ) return $checkout;

    $reference = ! empty( $checkout['reference'] ) ? sanitize_text_field( $checkout['reference'] ) : '';
    $booking_id = bubbahub_getpaid_find_booking_by_reference( $reference );
    if ( ! $booking_id ) return new WP_Error( 'booking_not_found', 'No BubbaHub booking matches this GetPaid checkout.', array( 'status' => 404 ) );

    $stored_checkout_id = get_post_meta( $booking_id, '_bh_getpaid_checkout_id', true );
    if ( $stored_checkout_id && ! hash_equals( (string) $stored_checkout_id, (string) $checkout_id ) ) return new WP_Error( 'checkout_mismatch', 'Checkout does not match booking.', array( 'status' => 409 ) );

    $status = ! empty( $checkout['status'] ) ? sanitize_key( $checkout['status'] ) : '';
    update_post_meta( $booking_id, '_bh_getpaid_checkout_status', $status );
    update_post_meta( $booking_id, '_bh_getpaid_last_event', $event_type );
    update_post_meta( $booking_id, '_bh_getpaid_last_event_id', ! empty( $payload['id'] ) ? sanitize_text_field( $payload['id'] ) : '' );

    if ( 'completed' === $status ) {
        update_post_meta( $booking_id, '_bh_status', 'confirmed' );
        update_post_meta( $booking_id, '_bh_payment_status', 'paid' );
        update_post_meta( $booking_id, '_bh_payment_method', 'getpaid' );
        if ( ! empty( $checkout['payment']['id'] ) ) update_post_meta( $booking_id, '_bh_getpaid_payment_id', sanitize_text_field( $checkout['payment']['id'] ) );
        if ( ! empty( $data['payment']['id'] ) ) update_post_meta( $booking_id, '_bh_getpaid_payment_id', sanitize_text_field( $data['payment']['id'] ) );
    } elseif ( in_array( $status, array( 'failed', 'unattempted' ), true ) ) {
        update_post_meta( $booking_id, '_bh_status', 'payment_failed' );
        update_post_meta( $booking_id, '_bh_payment_status', 'failed' );
    } elseif ( 'in_progress' === $status ) {
        update_post_meta( $booking_id, '_bh_payment_status', 'processing' );
    }

    return rest_ensure_response( array( 'received' => true ) );
}

add_action( 'admin_menu', function() {
    add_options_page( 'BubbaHub Payments', 'BubbaHub Payments', 'manage_options', 'bubbahub-payments', 'bubbahub_getpaid_settings_page' );
} );

add_action( 'admin_init', function() {
    register_setting( 'bubbahub_getpaid', 'bubbahub_getpaid_enabled', array( 'type' => 'boolean', 'sanitize_callback' => function( $v ) { return ! empty( $v ); } ) );
    register_setting( 'bubbahub_getpaid', 'bubbahub_getpaid_environment', array( 'sanitize_callback' => function( $v ) { return in_array( $v, array( 'sandbox', 'live' ), true ) ? $v : 'sandbox'; } ) );
    register_setting( 'bubbahub_getpaid', 'bubbahub_getpaid_client_id', array( 'sanitize_callback' => 'sanitize_text_field' ) );
    register_setting( 'bubbahub_getpaid', 'bubbahub_getpaid_client_secret', array( 'sanitize_callback' => 'sanitize_text_field' ) );
    register_setting( 'bubbahub_getpaid', 'bubbahub_getpaid_seller_id', array( 'sanitize_callback' => 'sanitize_text_field' ) );
    register_setting( 'bubbahub_getpaid', 'bubbahub_getpaid_currency', array( 'sanitize_callback' => function( $v ) { $v = strtoupper( sanitize_text_field( $v ) ); return preg_match( '/^[A-Z]{3}$/', $v ) ? $v : 'GBP'; } ) );
} );

function bubbahub_getpaid_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $settings = bubbahub_getpaid_settings();
    ?>
    <div class="wrap">
        <h1>BubbaHub Payments — GetPaid</h1>
        <p>Book Now payments use GetPaid hosted checkout. Reserve Spot bookings are unchanged.</p>
        <form method="post" action="options.php">
            <?php settings_fields( 'bubbahub_getpaid' ); ?>
            <table class="form-table" role="presentation">
                <tr><th scope="row">Enable GetPaid</th><td><label><input type="checkbox" name="bubbahub_getpaid_enabled" value="1" <?php checked( $settings['enabled'] ); ?>> Enable payment checkout for paid Book Now sessions</label></td></tr>
                <tr><th scope="row">Environment</th><td><select name="bubbahub_getpaid_environment"><option value="sandbox" <?php selected( $settings['environment'], 'sandbox' ); ?>>Sandbox / testing</option><option value="live" <?php selected( $settings['environment'], 'live' ); ?>>Live</option></select></td></tr>
                <tr><th scope="row">Client ID</th><td><input type="text" class="regular-text" name="bubbahub_getpaid_client_id" value="<?php echo esc_attr( $settings['client_id'] ); ?>"></td></tr>
                <tr><th scope="row">Client Secret</th><td><input type="password" class="regular-text" name="bubbahub_getpaid_client_secret" value="<?php echo esc_attr( $settings['client_secret'] ); ?>" autocomplete="new-password"></td></tr>
                <tr><th scope="row">Seller account ID</th><td><input type="text" class="regular-text" name="bubbahub_getpaid_seller_id" value="<?php echo esc_attr( $settings['seller_id'] ); ?>"><p class="description">The GetPaid account that should receive the booking funds.</p></td></tr>
                <tr><th scope="row">Currency</th><td><input type="text" class="small-text" maxlength="3" name="bubbahub_getpaid_currency" value="<?php echo esc_attr( $settings['currency'] ); ?>"></td></tr>
            </table>
            <?php submit_button( 'Save GetPaid settings' ); ?>
        </form>
        <hr>
        <h2>Webhook</h2>
        <p><code><?php echo esc_html( rest_url( 'bubbahub/v1/getpaid/webhook' ) ); ?></code></p>
        <p>GetPaid payment methods are left on <strong>default</strong>, so the hosted checkout uses the payment methods available to your GetPaid account.</p>
    </div>
    <?php
}
