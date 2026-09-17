<?php
/** Bubba Hub membership tiers. */
if ( ! defined( 'ABSPATH' ) ) exit;

function bubbahub_membership_tiers() {
    return array(
        'basic' => array( 'name' => 'Basic', 'price' => 10.00, 'description' => 'Essential Bubba Hub membership.', 'features' => array( 'Directory listing', 'Basic profile tools' ) ),
        'premium' => array( 'name' => 'Premium', 'price' => 25.00, 'description' => 'More visibility and tools for active organisers.', 'features' => array( 'Enhanced listing', 'Booking tools', 'Networking features' ) ),
        'ultimate' => array( 'name' => 'Ultimate', 'price' => 50.00, 'description' => 'Full Bubba Hub organiser toolkit.', 'features' => array( 'Priority visibility', 'Booking and venue tools', 'Community and networking tools' ) ),
    );
}

add_shortcode( 'bubbahub_subscription', 'bubbahub_subscription_shortcode' );
add_shortcode( 'bubbahub_subscriptions', 'bubbahub_subscription_shortcode' );
function bubbahub_subscription_shortcode() {
    if ( ! is_user_logged_in() ) return '<div class="bh-subscriptions"><p>Please log in to choose a Bubba Hub membership.</p></div>';
    if ( ! function_exists( 'bubbahub_stripe_settings' ) ) return '';
    $settings = bubbahub_stripe_settings();
    $current = sanitize_key( get_user_meta( get_current_user_id(), 'bubbahub_membership_tier', true ) );
    $tiers = bubbahub_membership_tiers();
    ob_start(); ?>
    <section class="bh-subscriptions">
        <div class="bh-subscriptions-heading"><span>MEMBERSHIP</span><h2>Choose your Bubba Hub tier</h2><p>Membership is billed annually. You can change your tier later.</p></div>
        <?php if ( ! $settings['enabled'] || ! $settings['secret_key'] ) : ?><div class="bh-subscription-notice">Online membership payments are not configured yet.</div><?php endif; ?>
        <div class="bh-subscription-grid">
            <?php foreach ( $tiers as $slug => $tier ) : ?>
                <article class="bh-subscription-card <?php echo $current === $slug ? 'is-current' : ''; ?>">
                    <h3><?php echo esc_html( $tier['name'] ); ?></h3>
                    <div class="bh-subscription-price">£<?php echo number_format( $tier['price'], 2 ); ?><small>/year</small></div>
                    <p><?php echo esc_html( $tier['description'] ); ?></p>
                    <ul><?php foreach ( $tier['features'] as $feature ) : ?><li><?php echo esc_html( $feature ); ?></li><?php endforeach; ?></ul>
                    <?php if ( $current === $slug ) : ?><span class="bh-subscription-current">Current tier</span><?php elseif ( $settings['enabled'] && $settings['secret_key'] ) : ?><a class="bh-subscription-button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'bh_membership' => 'checkout', 'tier' => $slug ), home_url( '/myhub/' ) ), 'bh_membership_checkout_' . $slug ) ); ?>">Choose <?php echo esc_html( $tier['name'] ); ?> →</a><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <style>
    .bh-subscriptions{margin:24px 0}.bh-subscriptions-heading{text-align:center;margin-bottom:22px}.bh-subscriptions-heading>span{font-size:12px;font-weight:800;letter-spacing:.12em}.bh-subscription-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.bh-subscription-card{padding:24px;border:1px solid #e5e7eb;border-radius:18px;background:#fff}.bh-subscription-card.is-current{box-shadow:0 0 0 2px currentColor}.bh-subscription-price{font-size:30px;font-weight:800;margin:8px 0}.bh-subscription-price small{font-size:13px;font-weight:600}.bh-subscription-card li{margin:7px 0}.bh-subscription-button,.bh-subscription-current{display:inline-block;margin-top:14px;padding:11px 16px;border-radius:10px;font-weight:700}.bh-subscription-button{background:#111;color:#fff;text-decoration:none}.bh-subscription-current{background:#f0fdf4}.bh-subscription-notice{padding:12px;border-radius:10px;background:#fff7ed;margin-bottom:16px}@media(max-width:760px){.bh-subscription-grid{grid-template-columns:1fr}}
    </style>
    <?php return ob_get_clean();
}

add_action( 'init', 'bubbahub_subscription_checkout_action', 20 );
function bubbahub_subscription_checkout_action() {
    if ( ! is_user_logged_in() || empty( $_GET['bh_membership'] ) || 'checkout' !== sanitize_key( wp_unslash( $_GET['bh_membership'] ) ) ) return;
    $tier = sanitize_key( isset( $_GET['tier'] ) ? wp_unslash( $_GET['tier'] ) : '' );
    $tiers = bubbahub_membership_tiers();
    if ( ! isset( $tiers[ $tier ] ) ) return;
    $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'bh_membership_checkout_' . $tier ) ) wp_die( 'Security check failed.' );
    if ( ! function_exists( 'bubbahub_stripe_settings' ) || ! function_exists( 'bubbahub_stripe_api_request' ) ) wp_die( 'Stripe is not available.' );
    $settings = bubbahub_stripe_settings();
    if ( ! $settings['enabled'] || ! $settings['secret_key'] ) wp_die( 'Membership payments are not configured.' );
    $user = wp_get_current_user();
    $price = $tiers[ $tier ]['price'];
    $params = array(
        'mode' => 'subscription',
        'success_url' => add_query_arg( array( 'bubbahub_membership' => 'success', 'tier' => $tier ), home_url( '/myhub/' ) ),
        'cancel_url' => add_query_arg( 'bubbahub_membership', 'cancelled', home_url( '/myhub/' ) ),
        'customer_email' => sanitize_email( $user->user_email ),
        'line_items[0][price_data][currency]' => strtolower( $settings['currency'] ?: 'gbp' ),
        'line_items[0][price_data][product_data][name]' => 'Bubba Hub ' . $tiers[ $tier ]['name'] . ' Membership',
        'line_items[0][price_data][unit_amount]' => (int) round( $price * 100 ),
        'line_items[0][price_data][recurring][interval]' => 'year',
        'line_items[0][quantity]' => 1,
        'metadata[user_id]' => absint( $user->ID ),
        'metadata[tier]' => $tier,
        'subscription_data[metadata][user_id]' => absint( $user->ID ),
        'subscription_data[metadata][tier]' => $tier,
    );
    $checkout = bubbahub_stripe_api_request( 'POST', 'checkout/sessions', $params );
    if ( is_wp_error( $checkout ) || empty( $checkout['url'] ) ) wp_die( esc_html( is_wp_error( $checkout ) ? $checkout->get_error_message() : 'Unable to start membership checkout.' ) );
    update_user_meta( $user->ID, 'bubbahub_membership_checkout_tier', $tier );
    if ( ! empty( $checkout['subscription'] ) ) update_user_meta( $user->ID, 'bubbahub_membership_subscription_id', sanitize_text_field( $checkout['subscription'] ) );
    wp_redirect( esc_url_raw( $checkout['url'] ) );
    exit;
}

add_action( 'rest_api_init', 'bubbahub_subscription_rest_hooks', 30 );
function bubbahub_subscription_rest_hooks() {
    // The main Stripe webhook is registered by the Stripe integration. This
    // secondary route lets membership events use the same signed payload rules
    // without replacing the booking webhook.
    register_rest_route( 'bubbahub/v1', '/membership/webhook', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'bubbahub_membership_webhook' ) );
}
function bubbahub_membership_webhook( WP_REST_Request $request ) {
    $settings = bubbahub_stripe_settings(); $payload = $request->get_body(); $signature = $request->get_header( 'stripe-signature' );
    if ( empty( $settings['webhook_secret'] ) || empty( $signature ) ) return new WP_Error( 'invalid_signature', 'Webhook signing secret is not configured.', array( 'status' => 400 ) );
    $timestamp = 0; $signatures = array(); foreach ( explode( ',', $signature ) as $item ) { $parts = explode( '=', trim( $item ), 2 ); if ( count( $parts ) !== 2 ) continue; if ( 't' === $parts[0] ) $timestamp = absint( $parts[1] ); if ( 'v1' === $parts[0] ) $signatures[] = $parts[1]; }
    if ( ! $timestamp || abs( time() - $timestamp ) > 300 ) return new WP_Error( 'invalid_signature', 'Expired webhook.', array( 'status' => 400 ) );
    $expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $settings['webhook_secret'] ); $valid = false; foreach ( $signatures as $sig ) if ( hash_equals( $expected, $sig ) ) $valid = true;
    if ( ! $valid ) return new WP_Error( 'invalid_signature', 'Invalid webhook signature.', array( 'status' => 400 ) );
    $event = json_decode( $payload, true ); $type = isset( $event['type'] ) ? $event['type'] : ''; $object = isset( $event['data']['object'] ) ? $event['data']['object'] : array();
    if ( 'checkout.session.completed' === $type && isset( $object['mode'] ) && 'subscription' === $object['mode'] ) {
        $uid = ! empty( $object['metadata']['user_id'] ) ? absint( $object['metadata']['user_id'] ) : 0; $tier = ! empty( $object['metadata']['tier'] ) ? sanitize_key( $object['metadata']['tier'] ) : '';
        if ( $uid && isset( bubbahub_membership_tiers()[ $tier ] ) ) { update_user_meta( $uid, 'bubbahub_membership_tier', $tier ); update_user_meta( $uid, 'bubbahub_membership_status', 'active' ); if ( ! empty( $object['subscription'] ) ) update_user_meta( $uid, 'bubbahub_membership_subscription_id', sanitize_text_field( $object['subscription'] ) ); }
    }
    if ( in_array( $type, array( 'customer.subscription.deleted', 'customer.subscription.paused' ), true ) && ! empty( $object['id'] ) ) {
        $users = get_users( array( 'meta_key' => 'bubbahub_membership_subscription_id', 'meta_value' => sanitize_text_field( $object['id'] ), 'number' => 1, 'fields' => 'ids' ) );
        if ( ! empty( $users ) ) update_user_meta( absint( $users[0] ), 'bubbahub_membership_status', 'inactive' );
    }
    return rest_ensure_response( array( 'received' => true ) );
}
