<?php
/**
 * BubbaHub Booking Engine — Ninja Forms Book Now integration.
 *
 * Connects the imported BubbaHub Book Now form to bh_booking records.
 *
 * Form 4 visible fields:
 *   first_name, last_name, email, phone
 *
 * The booking context is supplied by the /book/ page through the hidden
 * fields configured in the imported Ninja Form.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Read a Ninja Forms field by its portable field key.
 */
function bubbahub_ninja_booking_field_value( $form_data, $key ) {
    if ( empty( $form_data['fields'] ) || ! is_array( $form_data['fields'] ) ) return '';

    foreach ( $form_data['fields'] as $field ) {
        if ( ! is_array( $field ) || ! isset( $field['key'] ) || $field['key'] !== $key ) continue;
        return isset( $field['value'] ) ? $field['value'] : '';
    }

    return '';
}

/**
 * Build a safe ticket breakdown from the submitted JSON and the session's
 * current ticket definitions.
 *
 * We deliberately rebuild the name and price from the session rather than
 * trusting values sent by the browser. This also avoids depending on helper
 * functions which are not part of the current booking engine.
 */
function bubbahub_ninja_booking_normalize_ticket_breakdown( $raw_value, $ticket_types ) {
    if ( ! is_array( $ticket_types ) ) $ticket_types = array();

    $decoded = $raw_value;
    if ( is_string( $decoded ) ) {
        $decoded = json_decode( wp_unslash( $decoded ), true );
    }
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
        $remaining = isset( $definition['remaining'] ) && null !== $definition['remaining']
            ? absint( $definition['remaining'] )
            : null;

        if ( $capacity > 0 && null !== $remaining && $quantity > $remaining ) {
            return new WP_Error(
                'ticket_full',
                sprintf( 'There are not enough %s tickets remaining.', $definition['name'] )
            );
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

    if ( '' === $name ) {
        $name = sanitize_text_field( bubbahub_ninja_booking_field_value( $form_data, 'name' ) );
    }

    if ( ! $session_id || get_post_type( $session_id ) !== 'bh_session' ) return;

    if ( ! $group_id ) {
        $group_id = absint( bubbahub_booking_meta( $session_id, '_bh_group_id', 0 ) );
    }

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
    $places = ! empty( $ticket_breakdown )
        ? absint( $calculated['places'] )
        : max( 1, $submitted_places );

    $total_price = ! empty( $ticket_breakdown )
        ? (float) $calculated['total']
        : (float) bubbahub_booking_ticket_price( bubbahub_ninja_booking_field_value( $form_data, 'total_price' ) );

    if ( $places < 1 ) $places = 1;

    $submission_id = ! empty( $form_data['id'] ) ? absint( $form_data['id'] ) : 0;
    if ( $submission_id ) {
        $existing = get_posts( array(
            'post_type' => 'bh_booking',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => '_bh_ninja_submission_id',
                    'value' => $submission_id,
                    'compare' => '=',
                ),
            ),
        ) );

        if ( ! empty( $existing ) ) return;
    }

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
        'status' => 'confirmed',
        'payment_status' => 'pending',
        'payment_method' => 'ninja_form',
        'notes' => $phone ? 'Phone: ' . $phone : '',
    ) );

    if ( is_wp_error( $booking_id ) ) return;

    if ( $submission_id ) update_post_meta( $booking_id, '_bh_ninja_submission_id', $submission_id );

    update_post_meta(
        $booking_id,
        '_bh_booking_date',
        sanitize_text_field( bubbahub_ninja_booking_field_value( $form_data, 'booking_date' ) )
    );
    update_post_meta( $booking_id, '_bh_ninja_form_id', $form_id );
}

/**
 * Populate the actual Ninja Forms fields with the booking context on the /book/ page.
 * Field IDs are resolved by portable field key, so the imported form can be moved between installs.
 */
add_action( 'wp_footer', 'bubbahub_ninja_booking_context_script', 40 );
function bubbahub_ninja_booking_context_script() {
    if ( ! is_page( 'book' ) || ! function_exists( 'Ninja_Forms' ) ) return;

    $group_id = isset( $_GET['group_id'] ) ? absint( $_GET['group_id'] ) : 0;
    $session_id = isset( $_GET['session_id'] ) ? absint( $_GET['session_id'] ) : 0;
    $date = isset( $_GET['date'] )
        ? bubbahub_booking_normalize_session_date( sanitize_text_field( wp_unslash( $_GET['date'] ) ) )
        : '';

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
            booking_status: 'confirmed'
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
