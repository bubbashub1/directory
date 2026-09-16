<?php
/**
 * Bubba Hub My Hub - Stage 3 integrations.
 *
 * Connects the account-settings layer to the existing WordPress/Ultimate
 * Member account and to the Stripe payment integration already present in
 * this repository. It does not invent a second customer/payment system.
 *
 * GetPaid note: the repository's existing GetPaid compatibility file routes
 * booking payments to Stripe Connect, so this module exposes a Stripe Billing
 * Portal when Stripe is configured and keeps the existing GetPaid URL filters
 * available for any site-level GetPaid page supplied by the live installation.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'bubbahub_stripe_customer_portal_url', 'bubbahub_stage3_customer_portal_url', 20 );
add_action( 'admin_post_bubbahub_customer_portal', 'bubbahub_stage3_customer_portal_redirect' );
add_action( 'profile_update', 'bubbahub_stage3_sync_account_meta', 20, 2 );
add_action( 'user_register', 'bubbahub_stage3_sync_account_meta', 20, 1 );
add_filter( 'bubbahub_getpaid_account_url', 'bubbahub_stage3_getpaid_url', 20 );
add_filter( 'bubbahub_getpaid_payment_methods_url', 'bubbahub_stage3_payment_methods_url', 20 );

function bubbahub_stage3_getpaid_url( $url ) {
    if ( $url ) return $url;
    $configured = get_option( 'bubbahub_getpaid_account_url', '' );
    return $configured ? esc_url_raw( $configured ) : '';
}

function bubbahub_stage3_payment_methods_url( $url ) {
    if ( $url ) return $url;
    $configured = get_option( 'bubbahub_getpaid_payment_methods_url', '' );
    return $configured ? esc_url_raw( $configured ) : '';
}

function bubbahub_stage3_customer_portal_url( $url ) {
    if ( $url || ! is_user_logged_in() ) return $url;
    $settings = function_exists( 'bubbahub_stripe_settings' ) ? bubbahub_stripe_settings() : array();
    if ( empty( $settings['enabled'] ) || empty( $settings['secret_key'] ) ) return '';
    return wp_nonce_url( admin_url( 'admin-post.php?action=bubbahub_customer_portal' ), 'bubbahub_customer_portal' );
}

function bubbahub_stage3_stripe_customer_id( $user_id ) {
    $id = trim( (string) get_user_meta( $user_id, 'bubbahub_stripe_customer_id', true ) );
    return ( $id && 0 === strpos( $id, 'cus_' ) ) ? $id : '';
}

function bubbahub_stage3_get_or_create_customer( $user_id ) {
    if ( ! function_exists( 'bubbahub_stripe_api_request' ) ) return new WP_Error( 'stripe_unavailable', 'Stripe integration is not loaded.' );
    $existing = bubbahub_stage3_stripe_customer_id( $user_id );
    if ( $existing ) return $existing;

    $user = get_userdata( $user_id );
    if ( ! $user ) return new WP_Error( 'invalid_user', 'User account could not be found.' );

    $customer = bubbahub_stripe_api_request( 'POST', 'customers', array(
        'email' => sanitize_email( $user->user_email ),
        'name' => sanitize_text_field( $user->display_name ),
        'metadata[bubbahub_user_id]' => $user_id,
    ) );
    if ( is_wp_error( $customer ) ) return $customer;
    if ( empty( $customer['id'] ) ) return new WP_Error( 'stripe_customer_missing', 'Stripe did not return a customer ID.' );

    $id = sanitize_text_field( $customer['id'] );
    update_user_meta( $user_id, 'bubbahub_stripe_customer_id', $id );
    return $id;
}

function bubbahub_stage3_customer_portal_redirect() {
    if ( ! is_user_logged_in() ) wp_die( 'Please log in to manage your payment details.' );
    check_admin_referer( 'bubbahub_customer_portal' );

    if ( ! function_exists( 'bubbahub_stripe_settings' ) || ! function_exists( 'bubbahub_stripe_api_request' ) ) {
        wp_die( 'Stripe payments are not available.' );
    }
    $settings = bubbahub_stripe_settings();
    if ( empty( $settings['enabled'] ) || empty( $settings['secret_key'] ) ) {
        wp_die( 'Stripe payments are not configured yet.' );
    }

    $customer_id = bubbahub_stage3_get_or_create_customer( get_current_user_id() );
    if ( is_wp_error( $customer_id ) ) wp_die( esc_html( $customer_id->get_error_message() ) );

    $portal = bubbahub_stripe_api_request( 'POST', 'billing_portal/sessions', array(
        'customer' => $customer_id,
        'return_url' => add_query_arg( 'bh_account_settings', '1', home_url( '/' ) ),
    ) );
    if ( is_wp_error( $portal ) || empty( $portal['url'] ) ) {
        wp_die( esc_html( is_wp_error( $portal ) ? $portal->get_error_message() : 'Stripe did not return a customer portal URL.' ) );
    }
    wp_redirect( esc_url_raw( $portal['url'] ) );
    exit;
}

/**
 * Keep the common Ultimate Member phone field in step with the Bubba Hub
 * account value when that UM field already exists. UM uses the WordPress user
 * record as its identity, so display name and email are already shared.
 */
function bubbahub_stage3_sync_account_meta( $user_id, $old_user_data = null ) {
    $user_id = absint( $user_id );
    if ( ! $user_id ) return;

    $phone = get_user_meta( $user_id, 'bubbahub_phone', true );
    if ( '' !== $phone ) {
        foreach ( array( 'mobile_number', 'phone_number', 'user_phone' ) as $key ) {
            if ( metadata_exists( 'user', $user_id, $key ) ) update_user_meta( $user_id, $key, $phone );
        }
    }
}

/**
 * Optional site-level GetPaid page configuration. This deliberately does not
 * guess a page slug: if the live site has a GetPaid customer page, set its
 * URL in these filters/options and Stage 2 will use it. Otherwise the existing
 * Bubba Hub bookings area remains the safe fallback.
 */
add_action( 'admin_init', 'bubbahub_stage3_register_settings' );
function bubbahub_stage3_register_settings() {
    register_setting( 'general', 'bubbahub_getpaid_account_url', array( 'type' => 'string', 'sanitize_callback' => 'esc_url_raw', 'default' => '' ) );
    register_setting( 'general', 'bubbahub_getpaid_payment_methods_url', array( 'type' => 'string', 'sanitize_callback' => 'esc_url_raw', 'default' => '' ) );
}

add_action( 'admin_menu', 'bubbahub_stage3_settings_page' );
function bubbahub_stage3_settings_page() {
    add_options_page( 'Bubba Hub Account Integrations', 'Bubba Hub Integrations', 'manage_options', 'bubbahub-account-integrations', 'bubbahub_stage3_settings_screen' );
}

function bubbahub_stage3_settings_screen() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    ?>
    <div class="wrap">
        <h1>Bubba Hub Account Integrations</h1>
        <p>Ultimate Member uses the site's WordPress user account. Payment bookings in this repository currently use the Stripe integration; GetPaid URL fields below are optional compatibility settings for a live GetPaid customer page.</p>
        <form method="post" action="options.php">
            <?php settings_fields( 'general' ); ?>
            <table class="form-table" role="presentation">
                <tr><th scope="row"><label for="bubbahub_getpaid_account_url">GetPaid account / invoices URL</label></th><td><input class="regular-text" type="url" id="bubbahub_getpaid_account_url" name="bubbahub_getpaid_account_url" value="<?php echo esc_attr( get_option( 'bubbahub_getpaid_account_url', '' ) ); ?>"><p class="description">Optional. Leave blank to use the existing Bubba Hub bookings fallback.</p></td></tr>
                <tr><th scope="row"><label for="bubbahub_getpaid_payment_methods_url">GetPaid payment methods URL</label></th><td><input class="regular-text" type="url" id="bubbahub_getpaid_payment_methods_url" name="bubbahub_getpaid_payment_methods_url" value="<?php echo esc_attr( get_option( 'bubbahub_getpaid_payment_methods_url', '' ) ); ?>"><p class="description">Optional. If supplied, the Payment Methods tile opens this page.</p></td></tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
