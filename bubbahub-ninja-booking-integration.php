<?php
/**
 * BubbaHub Booking Engine — Ninja Forms Book Now integration.
 *
 * Connects the imported BubbaHub Book Now form to bh_booking records and,
 * when enabled, creates a GetPaid hosted checkout for paid bookings.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Load GetPaid independently so the booking engine remains usable if this file
// is loaded before the main engine's optional integration includes are changed.
$getpaid_file = plugin_dir_path( __FILE__ ) . 'bubbahub-getpaid-integration.php';
if ( file_exists( $getpaid_file ) ) require_once $getpaid_file;

/**
 * GetPaid can operate BubbaHub as the platform/seller, so a separate seller
 * ID does not need to be entered manually. The authenticated OAuth token is
 * the best source for the connected platform account; the accounts query is
 * retained as a fallback for credentials that expose exactly one account.
 */
function bubbahub_getpaid_auto_seller_id( $value ) {
    $value = trim( (string) $value );
    if ( $value ) return $value;

    static $resolved = null;
    if ( null !== $resolved ) return $resolved;

    $client_id = trim( (string) get_option( 'bubbahub_getpaid_client_id', '' ) );
    $client_secret = trim( (string) get_option( 'bubbahub_getpaid_client_secret', '' ) );
    $environment = get_option( 'bubbahub_getpaid_environment', 'sandbox' );

    if ( ! $client_id || ! $client_secret ) {
        $resolved = '';
        return '';
    }

    $audience = 'live' === $environment ? 'https://api.getpaid.io' : 'https://api.sandbox.getpaid.io';
    $api_base = 'live' === $environment ? 'https://api.getpaid.io/v2' : 'https://api.sandbox.getpaid.io/v2';

    $token_response = wp_remote_post( 'https://auth.getpaid.io/oauth/token', array(
        'timeout' => 20,
        'headers' => array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
        'body' => wp_json_encode( array(
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'audience' => $audience,
            'grant_type' => 'client_credentials',
        ) ),
    ) );

    if ( is_wp_error( $token_response ) ) {
        $resolved = '';
        return '';
    }

    $token_code = wp_remote_retrieve_response_code( $token_response );
    $token_body = json_decode( wp_remote_retrieve_body( $token_response ), true );
    $token = ( $token_code >= 200 && $token_code < 300 && ! empty( $token_body['access_token'] ) )
        ? sanitize_text_field( $token_body['access_token'] )
        : '';

    if ( ! $token ) {
        $resolved = '';
        return '';
    }

    // GetPaid's authenticated platform identity is represented by the OAuth
    // token subject. Only accept it when it is explicitly an account ID.
    $parts = explode( '.', $token );
    if ( count( $parts ) >= 2 ) {
        $payload = json_decode( base64_decode( strtr( $parts[1], '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $parts[1] ) % 4 ) % 4 ) ), true );
        if ( is_array( $payload ) && ! empty( $payload['sub'] ) && 0 === strpos( (string) $payload['sub'], 'acc_' ) ) {
            $resolved = sanitize_text_field( $payload['sub'] );
            return $resolved;
        }
    }

    // Fallback: if the credentials expose exactly one GetPaid account, use it.
    $accounts_response = wp_remote_post( trailingslashit( $api_base ) . 'accounts/query', array(
        'timeout' => 20,
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Getpaid-Idempotency-Key' => 'bh_accounts_lookup_' . md5( $environment . '|' . $client_id ),
        ),
        'body' => wp_json_encode( array(
            'type' => 'accounts',
            'first' => 20,
            'sorts' => array(
                array( 'field' => 'created_at', 'direction' => 'descending' ),
            ),
        ) ),
    ) );

    if ( is_wp_error( $accounts_response ) ) {
        $resolved = '';
        return '';
    }

    $accounts_code = wp_remote_retrieve_response_code( $accounts_response );
    $accounts_body = json_decode( wp_remote_retrieve_body( $accounts_response ), true );
    if ( $accounts_code < 200 || $accounts_code >= 300 || empty( $accounts_body['data'] ) || ! is_array( $accounts_body['data'] ) ) {
        $resolved = '';
        return '';
    }

    $accounts = array_values( array_filter( $accounts_body['data'], function( $account ) {
        return is_array( $account ) && ! empty( $account['id'] ) && 0 === strpos( (string) $account['id'], 'acc_' );
    } ) );

    // Never guess if the credentials expose multiple seller accounts.
    if ( 1 !== count( $accounts ) ) {
        $resolved = '';
        return '';
    }

    $resolved = sanitize_text_field( $accounts[0]['id'] );
    return $resolved;
}
add_filter( 'option_bubbahub_getpaid_seller_id', 'bubbahub_getpaid_auto_seller_id', 10, 1 );

add_filter( 'ninja_forms_run_action_settings', 'bubbahub_contact_organiser_email_action_settings', 20, 4 );
function bubbahub_contact_organiser_email_action_settings( $action_settings, $form_id, $action_id, $form_settings ) {
    if ( empty( $form_settings['title'] ) || false === stripos( (string) $form_settings['title'], 'contact organiser' ) && false === stripos( (string) $form_settings['title'], 'contact organizer' ) ) return $action_settings;
    if ( isset( $action_settings['type'] ) && 'email' !== $action_settings['type'] ) return $action_settings;
    $group_id = 0;
    if ( isset( $_COOKIE['bubbahub_contact_group_id'] ) ) $group_id = absint( wp_unslash( $_COOKIE['bubbahub_contact_group_id'] ) );
    $listing_email = $group_id && 'group' === get_post_type( $group_id ) && function_exists( 'bubbahub_directory_get_field' )
        ? sanitize_email( bubbahub_directory_get_field( $group_id, 'email', '' ) )
        : '';
    if ( $listing_email && is_email( $listing_email ) ) {
        if ( isset( $action_settings['email_to'] ) ) $action_settings['email_to'] = $listing_email;
        if ( isset( $action_settings['to'] ) ) $action_settings['to'] = $listing_email;
    } else {
        if ( isset( $action_settings['email_to'] ) ) $action_settings['email_to'] = '{wp:post_author_email}';
        if ( isset( $action_settings['to'] ) ) $action_settings['to'] = '{wp:post_author_email}';
    }
    return $action_settings;
}

function bubbahub_ninja_booking_field_value( $form_data, $key ) {
    if ( empty( $form_data['fields'] ) || ! is_array( $form_data['fields'] ) ) return '';
    foreach ( $form_data['fields'] as $field ) {
        if ( ! is_array( $field ) || ! isset( $field['key'] ) || $field['key'] !== $key ) continue;
        return isset( $field['value'] ) ? $field['value'] : '';
    }
    return '';
}

function bubbahub_ninja_booking_normalize_ticket_breakdown( $raw_value, $ticket_types ) {
    if ( ! is_array( $ticket_types ) ) $ticket_types = array();
    $decoded = $raw_value;
    if ( is_string( $decoded ) ) $decoded = json_decode( wp_unslash( $decoded ), true );
    if ( ! is_array( $decoded ) ) $decoded = array();

    $ticket_map = array();
    foreach ( $ticket_types as $ticket ) {
        if ( ! is_array( $ticket ) || empty( $ticket['slug'] ) ) continue;
        $slug = sanitize_key( $ticket['slug'] );
        if ( '' === $slug ) continue;
        $ticket_map[ $slug ] = $ticket;
    }

    $breakdown = array();
    foreach ( $decoded as $ticket ) {
        if ( ! is_array( $ticket ) ) continue;
        $slug = ! empty( $ticket['slug'] ) ? sanitize_key( $ticket['slug'] ) : '';
        $quantity = isset( $ticket['quantity'] ) ? absint( $ticket['quantity'] ) : 0;
        if ( '' === $slug || $quantity < 1 || ! isset( $ticket_map[ $slug ] ) ) continue;

        $definition = $ticket_map[ $slug ];
        $capacity = isset( $definition['capacity'] ) ? absint( $definition['capacity'] ) : 0;
        $remaining = isset( $definition['remaining'] ) && null !== $definition['remaining'] ? absint( $definition['remaining'] ) : null;
        if ( $capacity > 0 && null !== $remaining && $quantity > $remaining ) {
            return new WP_Error( 'ticket_full', sprintf( 'There are not enough %s tickets remaining.', $definition['name'] ) );
        }

        $breakdown[] = array(
            'slug' => $slug,
            'name' => isset( $definition['name'] ) ? sanitize_text_field( $definition['name'] ) : $slug,
            'price' => isset( $definition['price'] ) ? $definition['price'] : 0,
            'quantity' => $quantity,
        );
    }
    return $breakdown;
}

add_action( 'ninja_forms_after_submission', 'bubbahub_ninja_booking_after_submission', 20 );
function bubbahub_ninja_booking_after_submission( $form_data ) {
    if ( ! function_exists( 'bubbahub_booking_create' ) ) return;
    if ( ! is_array( $form_data ) ) return;

    $booking_method = sanitize_key( bubbahub_ninja_booking_field_value( $form_data, 'booking_method' ) );
    if ( 'book_now' !== $booking_method ) return;

    $form_id = isset( $form_data['form_id'] ) ? absint( $form_data['form_id'] ) : 0;
    $session_id = absint( bubbahub_ninja_booking_field_value( $form_data, 'session_id' ) );
    $group_id = absint( bubbahub_ninja_booking_field_value( $form_data, 'group_id' ) );
    $email = sanitize_email( bubbahub_ninja_booking_field_value( $form_data, 'email' ) );
    $first_name = sanitize_text_field( bubbahub_ninja_booking_field_value( $form_data, 'first_name' ) );
    $last_name = sanitize_text_field( bubbahub_ninja_booking_field_value( $form_data, 'last_name' ) );
    $phone = sanitize_text_field( bubbahub_ninja_booking_field_value( $form_data, 'phone' ) );
    $name = trim( $first_name . ' ' . $last_name );
    if ( '' === $name ) $name = sanitize_text_field( bubbahub_ninja_booking_field_value( $form_data, 'name' ) );

    if ( ! $session_id || get_post_type( $session_id ) !== 'bh_session' ) return;
    if ( ! $group_id ) $group_id = absint( bubbahub_booking_meta( $session_id, '_bh_group_id', 0 ) );
    if ( ! $group_id || get_post_type( $group_id ) !== 'group' ) return;
    if ( ! is_email( $email ) || '' === $name ) return;

    $configured_form_id = absint( bubbahub_booking_meta( $session_id, '_bh_ninja_form_id', 0 ) );
    if ( $configured_form_id && $form_id && $configured_form_id !== $form_id ) return;

    $stats = bubbahub_booking_session_stats( $session_id );
    $ticket_types = ! empty( $stats['ticket_types'] ) ? $stats['ticket_types'] : array();
    $ticket_breakdown = bubbahub_ninja_booking_normalize_ticket_breakdown(
        bubbahub_ninja_booking_field_value( $form_data, 'ticket_breakdown' ),
        $ticket_types
    );
    if ( is_wp_error( $ticket_breakdown ) ) return;
    if ( $ticket_types && empty( $ticket_breakdown ) ) return;

    $calculated = function_exists( 'bubbahub_booking_ticket_breakdown_total' )
        ? bubbahub_booking_ticket_breakdown_total( $ticket_breakdown )
        : array( 'places' => 0, 'total' => 0.0 );
    $submitted_places = absint( bubbahub_ninja_booking_field_value( $form_data, 'total_places' ) );
    $places = ! empty( $ticket_breakdown ) ? absint( $calculated['places'] ) : max( 1, $submitted_places );
    $total_price = ! empty( $ticket_breakdown )
        ? (float) $calculated['total']
        : (float) bubbahub_booking_ticket_price( bubbahub_ninja_booking_field_value( $form_data, 'total_price' ) );
    if ( $places < 1 ) $places = 1;

    $submission_id = ! empty( $form_data['id'] ) ? absint( $form_data['id'] ) : 0;
    if ( $submission_id ) {
        $existing = get_posts( array(
            'post_type' => 'bh_booking', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids',
            'meta_query' => array( array( 'key' => '_bh_ninja_submission_id', 'value' => $submission_id, 'compare' => '=' ) ),
        ) );
        if ( ! empty( $existing ) ) return;
    }

    $getpaid_enabled = function_exists( 'bubbahub_getpaid_settings' ) && bubbahub_getpaid_settings()['enabled'] && $total_price > 0;
    $booking_status = $getpaid_enabled ? 'pending_payment' : 'confirmed';
    $payment_status = $getpaid_enabled ? 'pending' : ( $total_price > 0 ? 'pending' : 'not_required' );
    $payment_method = $getpaid_enabled ? 'getpaid' : ( $total_price > 0 ? 'ninja_form' : '' );

    $booking_id = bubbahub_booking_create( array(
        'session_id' => $session_id,
        'group_id' => $group_id,
        'venue_id' => absint( bubbahub_booking_meta( $session_id, '_bh_venue_id', 0 ) ),
        'user_id' => get_current_user_id(),
        'customer_name' => $name,
        'customer_email' => $email,
        'places' => $places,
        'ticket_breakdown' => $ticket_breakdown,
        'total_price' => $total_price,
        'status' => $booking_status,
        'payment_status' => $payment_status,
        'payment_method' => $payment_method,
        'notes' => $phone ? 'Phone: ' . $phone : '',
    ) );
    if ( is_wp_error( $booking_id ) ) return;

    if ( $submission_id ) update_post_meta( $booking_id, '_bh_ninja_submission_id', $submission_id );
    update_post_meta( $booking_id, '_bh_booking_date', sanitize_text_field( bubbahub_ninja_booking_field_value( $form_data, 'booking_date' ) ) );
    update_post_meta( $booking_id, '_bh_ninja_form_id', $form_id );

    // Create the hosted payment session after the booking has a stable ID.
    // A failure leaves the booking retryable rather than falsely marking it paid.
    if ( $getpaid_enabled && function_exists( 'bubbahub_getpaid_create_checkout' ) ) {
        $checkout = bubbahub_getpaid_create_checkout( $booking_id );
        if ( is_wp_error( $checkout ) ) {
            update_post_meta( $booking_id, '_bh_getpaid_error', sanitize_text_field( $checkout->get_error_message() ) );
            update_post_meta( $booking_id, '_bh_payment_status', 'failed' );
            update_post_meta( $booking_id, '_bh_status', 'payment_failed' );
        }
    }
}

add_action( 'wp_footer', 'bubbahub_ninja_booking_context_script', 40 );
function bubbahub_ninja_booking_context_script() {
    if ( ! is_page( 'book' ) || ! function_exists( 'Ninja_Forms' ) ) return;

    $group_id = isset( $_GET['group_id'] ) ? absint( $_GET['group_id'] ) : 0;
    $session_id = isset( $_GET['session_id'] ) ? absint( $_GET['session_id'] ) : 0;
    $date = isset( $_GET['date'] ) ? bubbahub_booking_normalize_session_date( sanitize_text_field( wp_unslash( $_GET['date'] ) ) ) : '';
    if ( ! $group_id || ! $session_id || get_post_type( $group_id ) !== 'group' || get_post_type( $session_id ) !== 'bh_session' ) return;

    $form_id = absint( bubbahub_booking_meta( $session_id, '_bh_ninja_form_id', 0 ) );
    if ( ! $form_id ) $form_id = 4;

    $field_map = array();
    foreach ( Ninja_Forms()->form( $form_id )->get_fields() as $field ) {
        $key = $field->get_setting( 'key' );
        if ( $key ) $field_map[ sanitize_key( $key ) ] = absint( $field->get_id() );
    }
    if ( empty( $field_map ) ) return;
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var map = <?php echo wp_json_encode( $field_map ); ?>;
        var context = {
            group_id: <?php echo absint( $group_id ); ?>,
            session_id: <?php echo absint( $session_id ); ?>,
            booking_date: <?php echo wp_json_encode( $date ); ?>,
            booking_method: 'book_now',
            booking_status: 'pending'
        };
        var rows = Array.prototype.slice.call(document.querySelectorAll('[data-ticket-row]'));
        var form = document.querySelector('.nf-form-cont');
        if (!form) return;

        function inputFor(key) {
            if (!map[key]) return null;
            return document.getElementById('nf-field-' + map[key]) || form.querySelector('[name="nf-field-' + map[key] + '"]');
        }
        function setField(key, value) {
            var input = inputFor(key);
            if (!input) return;
            input.value = value == null ? '' : String(value);
            input.dispatchEvent(new Event('input', {bubbles:true}));
            input.dispatchEvent(new Event('change', {bubbles:true}));
        }
        function sync() {
            var items = [];
            var places = 0;
            var total = 0;
            rows.forEach(function (row) {
                var input = row.querySelector('[data-ticket-quantity]');
                if (!input) return;
                var qty = Math.max(0, parseInt(input.value || '0', 10) || 0);
                var slug = row.getAttribute('data-ticket-slug') || '';
                var price = parseFloat(row.getAttribute('data-ticket-price') || '0') || 0;
                if (qty > 0 && slug) items.push({slug: slug, quantity: qty});
                places += qty;
                total += qty * price;
            });
            setField('group_id', context.group_id);
            setField('session_id', context.session_id);
            setField('booking_date', context.booking_date);
            setField('ticket_breakdown', JSON.stringify(items));
            setField('total_places', places);
            setField('total_price', total.toFixed(2));
            setField('booking_method', context.booking_method);
            setField('booking_status', context.booking_status);
        }
        sync();
        document.addEventListener('click', function (event) {
            if (event.target.closest('[data-ticket-plus]') || event.target.closest('[data-ticket-minus]')) window.setTimeout(sync, 0);
        });
        document.addEventListener('input', function (event) {
            if (event.target.matches('[data-ticket-quantity]')) sync();
        });
    });
    </script>
    <?php
}