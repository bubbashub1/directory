<?php
/**
 * BubbaHub Stripe Connect integration.
 * Each leader connects their own Stripe account.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function bubbahub_stripe_settings() {
    return array(
        'enabled'        => (bool) get_option( 'bubbahub_stripe_enabled', false ),
        'secret_key'     => trim( (string) get_option( 'bubbahub_stripe_secret_key', '' ) ),
        'webhook_secret' => trim( (string) get_option( 'bubbahub_stripe_webhook_secret', '' ) ),
        'currency'       => strtoupper( trim( (string) get_option( 'bubbahub_stripe_currency', 'GBP' ) ) ),
        'fee_percent'    => max( 0, min( 100, (float) get_option( 'bubbahub_stripe_fee_percent', 0 ) ) ),
    );
}

function bubbahub_stripe_api_request( $method, $path, $params = array(), $connected_account = '' ) {
    $settings = bubbahub_stripe_settings();
    if ( ! $settings['secret_key'] ) return new WP_Error( 'stripe_not_configured', 'Stripe platform secret key is not configured.' );
    $args = array(
        'method' => strtoupper( $method ),
        'timeout' => 30,
        'headers' => array(
            'Authorization' => 'Bearer ' . $settings['secret_key'],
            'Content-Type' => 'application/x-www-form-urlencoded',
        ),
    );
    if ( $connected_account ) $args['headers']['Stripe-Account'] = $connected_account;
    if ( $params ) $args['body'] = http_build_query( $params, '', '&' );
    $response = wp_remote_request( 'https://api.stripe.com/v1/' . ltrim( $path, '/' ), $args );
    if ( is_wp_error( $response ) ) return $response;
    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( $code < 200 || $code >= 300 ) {
        $message = ! empty( $body['error']['message'] ) ? $body['error']['message'] : 'Stripe returned an unexpected error.';
        return new WP_Error( 'stripe_api_error', sanitize_text_field( $message ), array( 'status' => $code ) );
    }
    return is_array( $body ) ? $body : array();
}

function bubbahub_stripe_user_can_connect() {
    if ( ! is_user_logged_in() ) return false;
    $user = wp_get_current_user();
    return $user && ( in_array( 'leader', (array) $user->roles, true ) || in_array( 'leaderpro', (array) $user->roles, true ) || current_user_can( 'manage_options' ) );
}

function bubbahub_stripe_connected_account_id( $group_id = 0, $user_id = 0 ) {
    $group_id = absint( $group_id );
    $user_id = absint( $user_id );
    if ( $group_id ) {
        $override = trim( (string) get_post_meta( $group_id, '_bh_stripe_account_id', true ) );
        if ( $override && 0 === strpos( $override, 'acct_' ) ) return $override;
        $author_id = absint( get_post_field( 'post_author', $group_id ) );
        if ( $author_id ) $user_id = $author_id;
    }
    if ( ! $user_id ) $user_id = get_current_user_id();
    $id = trim( (string) get_user_meta( $user_id, 'bubbahub_stripe_account_id', true ) );
    return ( $id && 0 === strpos( $id, 'acct_' ) ) ? $id : '';
}

function bubbahub_stripe_create_account_link( $user_id ) {
    if ( ! bubbahub_stripe_user_can_connect() ) return new WP_Error( 'not_allowed', 'You do not have permission to connect a Stripe account.' );
    $user_id = absint( $user_id );
    $account_id = bubbahub_stripe_connected_account_id( 0, $user_id );
    if ( ! $account_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) return new WP_Error( 'invalid_user', 'Invalid leader account.' );
        $account = bubbahub_stripe_api_request( 'POST', 'accounts', array(
            'type' => 'standard',
            'email' => sanitize_email( $user->user_email ),
            'metadata[bubbahub_user_id]' => $user_id,
        ) );
        if ( is_wp_error( $account ) || empty( $account['id'] ) ) return is_wp_error( $account ) ? $account : new WP_Error( 'stripe_account_missing', 'Stripe did not return a connected account ID.' );
        $account_id = sanitize_text_field( $account['id'] );
        update_user_meta( $user_id, 'bubbahub_stripe_account_id', $account_id );
    }
    return bubbahub_stripe_api_request( 'POST', 'account_links', array(
        'account' => $account_id,
        'refresh_url' => add_query_arg( 'bubbahub_stripe', 'refresh', home_url( '/myhub/' ) ),
        'return_url' => add_query_arg( 'bubbahub_stripe', 'return', home_url( '/myhub/' ) ),
        'type' => 'account_onboarding',
        'collect' => 'eventually_due',
    ) );
}

add_action( 'admin_post_bubbahub_stripe_connect', 'bubbahub_stripe_connect_redirect' );
function bubbahub_stripe_connect_redirect() {
    if ( ! is_user_logged_in() || ! bubbahub_stripe_user_can_connect() ) wp_die( 'You are not authorised to connect Stripe.' );
    check_admin_referer( 'bubbahub_stripe_connect' );
    $link = bubbahub_stripe_create_account_link( get_current_user_id() );
    if ( is_wp_error( $link ) || empty( $link['url'] ) ) {
        $message = is_wp_error( $link ) ? $link->get_error_message() : 'Unable to start Stripe onboarding.';
        wp_safe_redirect( add_query_arg( 'bubbahub_stripe_error', rawurlencode( $message ), home_url( '/myhub/' ) ) );
        exit;
    }
    wp_redirect( esc_url_raw( $link['url'] ) );
    exit;
}

add_shortcode( 'bubbahub_stripe_connect', 'bubbahub_stripe_connect_shortcode' );
function bubbahub_stripe_connect_shortcode() {
    if ( ! is_user_logged_in() ) return '<p>Please log in to connect your payment account.</p>';
    if ( ! bubbahub_stripe_user_can_connect() ) return '';
    $settings = bubbahub_stripe_settings();
    $account_id = bubbahub_stripe_connected_account_id( 0, get_current_user_id() );
    $url = wp_nonce_url( admin_url( 'admin-post.php?action=bubbahub_stripe_connect' ), 'bubbahub_stripe_connect' );
    ob_start(); ?>
    <div class="bh-payment-connect bh-payment-connect-stripe">
        <h3>Stripe <small><?php echo $account_id ? 'Connected' : 'Not connected'; ?></small></h3>
        <?php if ( ! $settings['enabled'] || ! $settings['secret_key'] ) : ?>
            <p>Stripe payments are not enabled yet.</p>
        <?php elseif ( $account_id ) : ?>
            <p>Your Stripe account is connected to BubbaHub.</p><a class="button" href="<?php echo esc_url( $url ); ?>">Continue Stripe setup</a>
        <?php else : ?>
            <p>Connect your own Stripe account so parents can pay you through BubbaHub.</p><a class="button" href="<?php echo esc_url( $url ); ?>">Connect Stripe</a>
        <?php endif; ?>
    </div>
    <?php return ob_get_clean();
}

function bubbahub_stripe_create_checkout( $booking_id ) {
    $booking_id = absint( $booking_id );
    if ( ! $booking_id ) return new WP_Error( 'invalid_booking', 'Invalid booking.' );
    $settings = bubbahub_stripe_settings();
    if ( ! $settings['enabled'] || ! $settings['secret_key'] ) return new WP_Error( 'stripe_not_configured', 'Stripe payments are not configured.' );
    $group_id = absint( get_post_meta( $booking_id, '_bh_group_id', true ) );
    $account_id = bubbahub_stripe_connected_account_id( $group_id );
    if ( ! $account_id ) return new WP_Error( 'stripe_account_not_connected', 'This organiser has not connected a Stripe account yet.' );
    $amount = (float) get_post_meta( $booking_id, '_bh_total_price', true );
    if ( $amount <= 0 ) return new WP_Error( 'payment_not_required', 'Payment is not required for this booking.' );
    $minor = (int) round( $amount * 100 );
    $currency = strtolower( $settings['currency'] ?: 'gbp' );
    $email = sanitize_email( get_post_meta( $booking_id, '_bh_customer_email', true ) );
    $name = get_the_title( $group_id ) ?: 'BubbaHub booking';
    $params = array(
        'mode' => 'payment',
        'success_url' => add_query_arg( array( 'bubbahub_payment' => 'success', 'booking_id' => $booking_id ), home_url( '/myhub/' ) ),
        'cancel_url' => add_query_arg( array( 'bubbahub_payment' => 'cancelled', 'booking_id' => $booking_id ), home_url( '/myhub/' ) ),
        'line_items[0][price_data][currency]' => $currency,
        'line_items[0][price_data][product_data][name]' => $name,
        'line_items[0][price_data][product_data][description]' => 'BubbaHub booking #' . $booking_id,
        'line_items[0][price_data][unit_amount]' => $minor,
        'line_items[0][quantity]' => 1,
        'metadata[booking_id]' => $booking_id,
        'metadata[group_id]' => $group_id,
        'metadata[session_id]' => absint( get_post_meta( $booking_id, '_bh_session_id', true ) ),
    );
    if ( $email ) $params['customer_email'] = $email;
    if ( $settings['fee_percent'] > 0 ) {
        $fee = (int) round( $minor * $settings['fee_percent'] / 100 );
        if ( $fee > 0 && $fee < $minor ) $params['payment_intent_data[application_fee_amount]'] = $fee;
    }
    $checkout = bubbahub_stripe_api_request( 'POST', 'checkout/sessions', $params, $account_id );
    if ( is_wp_error( $checkout ) ) return $checkout;
    if ( empty( $checkout['id'] ) || empty( $checkout['url'] ) ) return new WP_Error( 'stripe_checkout_missing', 'Stripe did not return a checkout URL.' );
    update_post_meta( $booking_id, '_bh_stripe_account_id', $account_id );
    update_post_meta( $booking_id, '_bh_stripe_checkout_session_id', sanitize_text_field( $checkout['id'] ) );
    update_post_meta( $booking_id, '_bh_stripe_checkout_url', esc_url_raw( $checkout['url'] ) );
    update_post_meta( $booking_id, '_bh_payment_provider', 'stripe' );
    update_post_meta( $booking_id, '_bh_payment_status', 'pending' );
    update_post_meta( $booking_id, '_bh_status', 'pending_payment' );
    return array( 'id' => $checkout['id'], 'url' => esc_url_raw( $checkout['url'] ) );
}

add_action( 'rest_api_init', function() {
    register_rest_route( 'bubbahub/v1', '/payment/checkout', array( 'methods' => 'POST', 'permission_callback' => function() {
        return is_user_logged_in();
    }, 'callback' => 'bubbahub_stripe_checkout_rest' ) );
    register_rest_route( 'bubbahub/v1', '/stripe/webhook', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'bubbahub_stripe_webhook_rest' ) );
} );

function bubbahub_stripe_checkout_rest( WP_REST_Request $request ) {
    $booking_id = absint( $request->get_param( 'booking_id' ) );
    if ( ! $booking_id || 'bh_booking' !== get_post_type( $booking_id ) ) return new WP_Error( 'invalid_booking', 'Booking ID is required.', array( 'status' => 400 ) );
    if ( absint( get_post_meta( $booking_id, '_bh_user_id', true ) ) !== get_current_user_id() ) return new WP_Error( 'forbidden_booking', 'You are not authorised to pay for this booking.', array( 'status' => 403 ) );
    $payment_status = get_post_meta( $booking_id, '_bh_payment_status', true );
    if ( ! in_array( $payment_status, array( 'pending', 'failed' ), true ) ) return new WP_Error( 'payment_not_due', 'This booking is not awaiting payment.', array( 'status' => 400 ) );
    $email = sanitize_email( $request->get_param( 'email' ) );
    if ( $email && is_email( $email ) ) update_post_meta( $booking_id, '_bh_customer_email', $email );
    $checkout = bubbahub_stripe_create_checkout( $booking_id );
    if ( is_wp_error( $checkout ) ) return new WP_Error( $checkout->get_error_code(), $checkout->get_error_message(), array( 'status' => 400 ) );
    return rest_ensure_response( array( 'success' => true, 'checkout' => $checkout ) );
}

function bubbahub_stripe_webhook_rest( WP_REST_Request $request ) {
    $settings = bubbahub_stripe_settings();
    $payload = $request->get_body();
    $signature = $request->get_header( 'stripe-signature' );
    if ( ! $settings['webhook_secret'] || ! $signature ) return new WP_Error( 'invalid_signature', 'Stripe webhook signing secret is not configured.', array( 'status' => 400 ) );
    $timestamp = 0; $signatures = array();
    foreach ( explode( ',', $signature ) as $item ) { $parts = explode( '=', trim( $item ), 2 ); if ( count( $parts ) !== 2 ) continue; if ( 't' === $parts[0] ) $timestamp = absint( $parts[1] ); if ( 'v1' === $parts[0] ) $signatures[] = $parts[1]; }
    if ( ! $timestamp || abs( time() - $timestamp ) > 300 ) return new WP_Error( 'invalid_signature', 'Expired Stripe webhook signature.', array( 'status' => 400 ) );
    $expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $settings['webhook_secret'] );
    $valid = false; foreach ( $signatures as $sig ) if ( hash_equals( $expected, $sig ) ) { $valid = true; break; }
    if ( ! $valid ) return new WP_Error( 'invalid_signature', 'Invalid Stripe webhook signature.', array( 'status' => 400 ) );
    $event = json_decode( $payload, true );
    $object = isset( $event['data']['object'] ) && is_array( $event['data']['object'] ) ? $event['data']['object'] : array();
    $booking_id = ! empty( $object['metadata']['booking_id'] ) ? absint( $object['metadata']['booking_id'] ) : 0;
    $type = isset( $event['type'] ) ? $event['type'] : '';
    if ( $booking_id && 'bh_booking' !== get_post_type( $booking_id ) ) return new WP_Error( 'invalid_booking', 'The Stripe event references an invalid booking.', array( 'status' => 400 ) );
    $stored_checkout = $booking_id ? sanitize_text_field( get_post_meta( $booking_id, '_bh_stripe_checkout_session_id', true ) ) : '';
    if ( $booking_id && $stored_checkout && ! empty( $object['id'] ) && ! hash_equals( $stored_checkout, sanitize_text_field( $object['id'] ) ) ) return new WP_Error( 'checkout_mismatch', 'The Stripe checkout session does not match the booking.', array( 'status' => 400 ) );
    $event_id = ! empty( $event['id'] ) ? sanitize_text_field( $event['id'] ) : '';
    if ( $event_id && get_option( 'bubbahub_stripe_event_' . md5( $event_id ) ) ) return rest_ensure_response( array( 'received' => true ) );
    if ( $booking_id && 'checkout.session.completed' === $type && 'paid' === ( isset( $object['payment_status'] ) ? $object['payment_status'] : '' ) ) {
        update_post_meta( $booking_id, '_bh_payment_status', 'paid' );
        update_post_meta( $booking_id, '_bh_status', 'confirmed' );
        if ( ! empty( $object['payment_intent'] ) ) update_post_meta( $booking_id, '_bh_stripe_payment_intent', sanitize_text_field( $object['payment_intent'] ) );
    } elseif ( $booking_id && 'checkout.session.expired' === $type ) {
        update_post_meta( $booking_id, '_bh_payment_status', 'failed' );
        update_post_meta( $booking_id, '_bh_status', 'payment_failed' );
    }
    if ( $event_id ) add_option( 'bubbahub_stripe_event_' . md5( $event_id ), time(), '', 'no' );
    return rest_ensure_response( array( 'received' => true ) );
}

add_action( 'admin_menu', function() { add_options_page( 'BubbaHub Payments', 'BubbaHub Payments', 'manage_options', 'bubbahub-payments', 'bubbahub_stripe_admin_page' ); } );
add_action( 'admin_init', function() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    register_setting( 'bubbahub_payments', 'bubbahub_stripe_enabled', array( 'type' => 'boolean', 'sanitize_callback' => function( $v ) { return (bool) $v; } ) );
    register_setting( 'bubbahub_payments', 'bubbahub_stripe_secret_key', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ) );
    register_setting( 'bubbahub_payments', 'bubbahub_stripe_webhook_secret', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ) );
    register_setting( 'bubbahub_payments', 'bubbahub_stripe_currency', array( 'type' => 'string', 'sanitize_callback' => function( $v ) { return strtoupper( sanitize_text_field( $v ) ); } ) );
    register_setting( 'bubbahub_payments', 'bubbahub_stripe_fee_percent', array( 'type' => 'number', 'sanitize_callback' => 'floatval' ) );
} );

function bubbahub_stripe_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $webhook_url = rest_url( 'bubbahub/v1/stripe/webhook' ); ?>
    <div class="wrap"><h1>BubbaHub Payments</h1><h2>Stripe Connect</h2>
    <p>Leaders connect their own Stripe accounts. Keep secret keys in WordPress settings only.</p>
    <form method="post" action="options.php"><?php settings_fields( 'bubbahub_payments' ); ?>
    <table class="form-table"><tr><th>Enable Stripe</th><td><input type="checkbox" name="bubbahub_stripe_enabled" value="1" <?php checked( get_option( 'bubbahub_stripe_enabled', false ) ); ?>></td></tr>
    <tr><th>Platform secret key</th><td><input class="regular-text" type="password" name="bubbahub_stripe_secret_key" value="<?php echo esc_attr( get_option( 'bubbahub_stripe_secret_key', '' ) ); ?>" autocomplete="new-password"></td></tr>
    <tr><th>Webhook signing secret</th><td><input class="regular-text" type="password" name="bubbahub_stripe_webhook_secret" value="<?php echo esc_attr( get_option( 'bubbahub_stripe_webhook_secret', '' ) ); ?>" autocomplete="new-password"><p class="description">Endpoint: <code><?php echo esc_html( $webhook_url ); ?></code></p></td></tr>
    <tr><th>Currency</th><td><input class="small-text" type="text" name="bubbahub_stripe_currency" value="<?php echo esc_attr( get_option( 'bubbahub_stripe_currency', 'GBP' ) ); ?>"></td></tr>
    <tr><th>BubbaHub application fee %</th><td><input class="small-text" type="number" min="0" max="100" step="0.01" name="bubbahub_stripe_fee_percent" value="<?php echo esc_attr( get_option( 'bubbahub_stripe_fee_percent', 0 ) ); ?>"></td></tr></table>
    <?php submit_button( 'Save payment settings' ); ?></form>
    <hr><p>Add <code>[bubbahub_stripe_connect]</code> to the leader payment/settings page.</p></div><?php
}
