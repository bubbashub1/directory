<?php
/**
 * BubbaHub Booking Engine — Group frontend integration.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! function_exists( 'bubbahub_booking_get_available_sessions' ) ) return;

add_action( 'init', 'bubbahub_booking_register_page', 20 );
function bubbahub_booking_register_page() {
    if ( ! get_page_by_path( 'book' ) ) {
        wp_insert_post( array(
            'post_title' => 'Book', 'post_name' => 'book',
            'post_content' => '[bubbahub_booking_page]', 'post_status' => 'publish', 'post_type' => 'page',
        ) );
    }
}

add_shortcode( 'bubbahub_booking_page', 'bubbahub_booking_page_shortcode' );

add_action( 'wp_enqueue_scripts', 'bubbahub_booking_frontend_assets', 30 );
function bubbahub_booking_frontend_assets() {
    if ( ! is_singular( 'group' ) && ! is_page( 'book' ) ) return;
    $url = plugin_dir_url( __FILE__ );
    $css = plugin_dir_path( __FILE__ ) . 'assets/booking.css';
    $js  = plugin_dir_path( __FILE__ ) . 'assets/booking.js';
    wp_enqueue_style( 'bubbahub-booking-frontend', $url . 'assets/booking.css', array(), file_exists( $css ) ? filemtime( $css ) : '1.3.0' );
    wp_enqueue_script( 'bubbahub-booking-frontend', $url . 'assets/booking.js', array(), file_exists( $js ) ? filemtime( $js ) : '1.3.0', true );
}

function bubbahub_booking_parse_ticket_selection( $raw, $session_ticket_types ) {
    $selection = array();
    if ( ! is_array( $raw ) ) return $selection;
    $definitions = array();
    foreach ( $session_ticket_types as $ticket ) {
        if ( ! empty( $ticket['slug'] ) ) $definitions[ sanitize_key( $ticket['slug'] ) ] = $ticket;
    }
    foreach ( $raw as $slug => $quantity ) {
        $slug = sanitize_key( $slug );
        if ( ! isset( $definitions[ $slug ] ) ) continue;
        $quantity = max( 0, absint( $quantity ) );
        if ( ! $quantity ) continue;
        $ticket = $definitions[ $slug ];
        if ( isset( $ticket['remaining'] ) && null !== $ticket['remaining'] && $quantity > absint( $ticket['remaining'] ) ) {
            return new WP_Error( 'ticket_capacity', sprintf( 'There are not enough %s tickets remaining.', $ticket['name'] ) );
        }
        $selection[] = array(
            'slug' => $slug,
            'name' => sanitize_text_field( $ticket['name'] ),
            'price' => isset( $ticket['price'] ) ? sanitize_text_field( $ticket['price'] ) : '',
            'quantity' => $quantity,
        );
    }
    return $selection;
}

function bubbahub_booking_ticket_summary( $tickets ) {
    $places = 0;
    $total = 0.0;
    $lines = array();
    if ( ! is_array( $tickets ) ) return array( 'places' => 0, 'total' => 0, 'lines' => array() );
    foreach ( $tickets as $ticket ) {
        if ( ! is_array( $ticket ) ) continue;
        $quantity = isset( $ticket['quantity'] ) ? absint( $ticket['quantity'] ) : 0;
        if ( ! $quantity ) continue;
        $price = bubbahub_booking_ticket_price( isset( $ticket['price'] ) ? $ticket['price'] : '' );
        $places += $quantity;
        $total += $quantity * $price;
        $lines[] = $ticket['name'] . ' × ' . $quantity;
    }
    return array( 'places' => $places, 'total' => round( $total, 2 ), 'lines' => $lines );
}

add_action( 'wp_ajax_bubbahub_booking_reserve', 'bubbahub_booking_reserve_ajax' );
add_action( 'wp_ajax_nopriv_bubbahub_booking_reserve', 'bubbahub_booking_reserve_ajax' );
function bubbahub_booking_reserve_ajax() {
    check_ajax_referer( 'bubbahub_booking', 'nonce' );
    $session_id = isset( $_POST['session_id'] ) ? absint( $_POST['session_id'] ) : 0;
    $places = isset( $_POST['places'] ) ? max( 1, absint( $_POST['places'] ) ) : 1;
    $name = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
    $email = isset( $_POST['customer_email'] ) ? sanitize_email( wp_unslash( $_POST['customer_email'] ) ) : '';
    if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Please log in to complete a booking. Your booking consent and child profile must be attached to every booking.' ), 403 );
    if ( ! function_exists( 'bubbahub_stage2_consent_is_valid' ) || ! bubbahub_stage2_consent_is_valid( get_current_user_id() ) ) {
        wp_send_json_error( array( 'message' => 'Please complete your booking consent in Account Settings before booking.' ), 403 );
    }
    if ( ! $session_id || get_post_type( $session_id ) !== 'bh_session' ) wp_send_json_error( array( 'message' => 'Invalid booking session.' ), 400 );
    if ( ! $name || ! is_email( $email ) ) wp_send_json_error( array( 'message' => 'Please enter your name and a valid email address.' ), 400 );
    if ( ! (bool) bubbahub_booking_meta( $session_id, '_bh_reserve_enabled', false ) ) wp_send_json_error( array( 'message' => 'Reservations are not enabled for this session.' ), 400 );
    $ticket_types = bubbahub_booking_session_stats( $session_id )['ticket_types'];
    $raw_tickets = isset( $_POST['tickets'] ) ? wp_unslash( $_POST['tickets'] ) : array();
    $ticket_breakdown = bubbahub_booking_parse_ticket_selection( $raw_tickets, $ticket_types );
    if ( is_wp_error( $ticket_breakdown ) ) wp_send_json_error( array( 'message' => $ticket_breakdown->get_error_message() ), 400 );
    $booking_id = bubbahub_booking_create( array(
        'session_id' => $session_id,
        'group_id' => absint( bubbahub_booking_meta( $session_id, '_bh_group_id', 0 ) ),
        'venue_id' => absint( bubbahub_booking_meta( $session_id, '_bh_venue_id', 0 ) ),
        'customer_name' => $name, 'customer_email' => $email, 'places' => $places,
        'ticket_breakdown' => $ticket_breakdown,
        'status' => 'reserved', 'payment_status' => 'not_required', 'payment_method' => 'reservation',
    ) );
    if ( is_wp_error( $booking_id ) ) wp_send_json_error( array( 'message' => $booking_id->get_error_message() ), 400 );
    wp_send_json_success( array( 'booking_id' => (int) $booking_id, 'message' => 'Your space has been reserved.' ) );
}

function bubbahub_booking_render_group_widget( $group_id ) {
    if ( ! $group_id || get_post_type( $group_id ) !== 'group' ) return;
    $sessions = bubbahub_booking_get_available_sessions( $group_id );
    $dates = array();
    foreach ( $sessions as $session ) if ( ! empty( $session['date'] ) ) $dates[ $session['date'] ] = $session['date'];
    ksort( $dates );
    ?>
    <div id="bh-booking-modal" class="bh-booking-modal" hidden aria-hidden="true">
        <div class="bh-booking-modal-backdrop" data-booking-close></div>
        <div class="bh-booking-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="bh-booking-title">
            <button type="button" class="bh-booking-modal-close" data-booking-close aria-label="Close booking">×</button>
            <section id="bh-booking" class="bh-booking-widget"
                data-group-id="<?php echo esc_attr( $group_id ); ?>"
                data-book-url="<?php echo esc_url( home_url( '/book/' ) ); ?>">
                <div class="bh-booking-heading">
                    <span class="bh-booking-eyebrow">BOOKING</span>
                    <h2 id="bh-booking-title">Book this group</h2>
                    <p>Select your date and continue to the booking page.</p>
                </div>
                <div class="bh-booking-step">
                    <label for="bh-booking-date">Select a date</label>
                    <select id="bh-booking-date" class="bh-booking-select" data-booking-date>
                        <option value="">Choose a date</option>
                        <?php foreach ( $dates as $date ) : ?>
                            <option value="<?php echo esc_attr( $date ); ?>"><?php echo esc_html( wp_date( 'l, j F Y', strtotime( $date ) ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bh-booking-continue-wrap">
                    <a href="#" class="bh-booking-primary bh-booking-continue is-disabled" data-booking-continue aria-disabled="true">Continue to Book →</a>
                </div>
            </section>
        </div>
    </div>
    <?php
}

function bubbahub_booking_page_shortcode() {
    $group_id = isset( $_GET['group_id'] ) ? absint( $_GET['group_id'] ) : 0;
    $date = isset( $_GET['date'] ) ? bubbahub_booking_normalize_session_date( sanitize_text_field( wp_unslash( $_GET['date'] ) ) ) : '';
    $session_id = isset( $_GET['session_id'] ) ? absint( $_GET['session_id'] ) : 0;
    $notice = '';

    if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['bh_reserve_submit'] ) ) {
        if ( ! is_user_logged_in() ) {
            $notice = '<div class="bh-booking-message is-error">Please log in to complete a booking. Your booking consent and child profile must be attached to every booking.</div>';
        } elseif ( ! function_exists( 'bubbahub_stage2_consent_is_valid' ) || ! bubbahub_stage2_consent_is_valid( get_current_user_id() ) ) {
            $notice = '<div class="bh-booking-message is-error">Please complete your booking consent in Account Settings before booking.</div>';
        } elseif ( ! isset( $_POST['bh_reserve_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bh_reserve_nonce'] ) ), 'bh_reserve_page' ) ) {
            $notice = '<div class="bh-booking-message is-error">Security check failed. Please try again.</div>';
        } else {
            $post_session = isset( $_POST['session_id'] ) ? absint( $_POST['session_id'] ) : 0;
            $name = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
            $email = isset( $_POST['customer_email'] ) ? sanitize_email( wp_unslash( $_POST['customer_email'] ) ) : '';
            $sid_group = absint( bubbahub_booking_meta( $post_session, '_bh_group_id', 0 ) );
            $ticket_types = $post_session ? bubbahub_booking_session_stats( $post_session )['ticket_types'] : array();
            $raw_tickets = isset( $_POST['tickets'] ) ? wp_unslash( $_POST['tickets'] ) : array();
            $ticket_breakdown = bubbahub_booking_parse_ticket_selection( $raw_tickets, $ticket_types );
            if ( ! $name || ! is_email( $email ) ) {
                $notice = '<div class="bh-booking-message is-error">Please enter your name and a valid email address.</div>';
            } elseif ( is_wp_error( $ticket_breakdown ) ) {
                $notice = '<div class="bh-booking-message is-error">' . esc_html( $ticket_breakdown->get_error_message() ) . '</div>';
            } elseif ( ! $post_session || get_post_type( $post_session ) !== 'bh_session' || $sid_group !== $group_id || ! (bool) bubbahub_booking_meta( $post_session, '_bh_reserve_enabled', false ) ) {
                $notice = '<div class="bh-booking-message is-error">That reservation is no longer available.</div>';
            } else {
                $summary = bubbahub_booking_ticket_summary( $ticket_breakdown );
                $booking_id = bubbahub_booking_create( array(
                    'session_id' => $post_session, 'group_id' => $group_id,
                    'venue_id' => absint( bubbahub_booking_meta( $post_session, '_bh_venue_id', 0 ) ),
                    'customer_name' => $name, 'customer_email' => $email,
                    'places' => max( 1, $summary['places'] ), 'ticket_breakdown' => $ticket_breakdown,
                    'total_price' => $summary['total'], 'status' => 'reserved', 'payment_status' => 'not_required', 'payment_method' => 'reservation',
                ) );
                $notice = is_wp_error( $booking_id )
                    ? '<div class="bh-booking-message is-error">' . esc_html( $booking_id->get_error_message() ) . '</div>'
                    : '<div class="bh-booking-message is-success">Your booking has been reserved. Reference #' . absint( $booking_id ) . '.</div>';
                if ( ! is_wp_error( $booking_id ) ) $session_id = $post_session;
            }
        }
    }

    if ( ! $group_id || get_post_type( $group_id ) !== 'group' ) {
        return '<div class="bh-booking-page"><div class="bh-booking-page-card"><h1>Booking</h1><p>Please return to the group page and choose a booking date.</p></div></div>';
    }

    $group_title = get_the_title( $group_id );
    $sessions = bubbahub_booking_get_available_sessions( $group_id, $date );
    ob_start();
    ?>
    <main class="bh-booking-page">
        <div class="bh-booking-page-card">
            <div class="bh-booking-back-wrap">
                <a class="bh-booking-back" href="<?php echo esc_url( get_permalink( $group_id ) ); ?>">← Back to listing</a>
            </div>
            <span class="bh-booking-eyebrow">BUBBA HUB BOOKING</span>
            <h1><?php echo esc_html( $group_title ); ?></h1>
            <?php if ( $date ) : ?><p class="bh-booking-page-date"><strong>Date:</strong> <?php echo esc_html( wp_date( 'l, j F Y', strtotime( $date ) ) ); ?></p><?php endif; ?>
            <?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <?php if ( ! $date ) : ?>
                <p>Choose a date from the group page to continue.</p>
                <a class="bh-booking-primary" href="<?php echo esc_url( get_permalink( $group_id ) . '#bh-booking' ); ?>">Choose a date →</a>
            <?php elseif ( empty( $sessions ) ) : ?>
                <div class="bh-booking-empty">There are currently no bookable sessions for this date.</div>
                <a class="bh-booking-secondary" href="<?php echo esc_url( get_permalink( $group_id ) . '#bh-booking' ); ?>">Choose another date</a>
            <?php else : ?>
                <div class="bh-booking-next-steps">
                    <h2>Next steps</h2>
                    <p>Choose the session you want, then choose your ticket types.</p>
                </div>
                <div class="bh-booking-sessions bh-booking-page-sessions">
                    <?php foreach ( $sessions as $item ) :
                        $action = isset( $item['booking_action'] ) ? $item['booking_action'] : 'book_now';
                        $session_url = add_query_arg( array( 'group_id' => $group_id, 'date' => $date, 'session_id' => absint( $item['id'] ) ), home_url( '/book/' ) );
                        ?>
                        <article class="bh-booking-session-card">
                            <div>
                                <strong><?php echo esc_html( $item['title'] ?: $group_title ); ?></strong>
                                <div class="bh-booking-session-time"><?php echo esc_html( $item['start_time'] ); ?><?php echo ! empty( $item['end_time'] ) ? ' – ' . esc_html( $item['end_time'] ) : ''; ?></div>
                                <?php if ( ! empty( $item['ticket_types'] ) ) : ?><div class="bh-booking-ticket-hint"><?php echo esc_html( count( $item['ticket_types'] ) ); ?> ticket types available</div><?php elseif ( $item['price'] !== '' ) : ?><div class="bh-booking-session-price"><?php echo esc_html( is_numeric( $item['price'] ) ? '£' . $item['price'] : $item['price'] ); ?></div><?php endif; ?>
                            </div>
                            <div>
                                <?php if ( 'external' === $action && ! empty( $item['external_url'] ) ) : ?>
                                    <a class="bh-booking-external" href="<?php echo esc_url( $item['external_url'] ); ?>" target="_blank" rel="noopener noreferrer">Continue to External Booking →</a>
                                <?php elseif ( 'reserve_spot' === $action ) : ?>
                                    <a class="bh-booking-primary" href="<?php echo esc_url( $session_url ); ?>#details">Reserve Spot →</a>
                                <?php elseif ( 'none' === $action ) : ?>
                                    <span class="bh-booking-secondary">Booking unavailable</span>
                                <?php else : ?>
                                    <a class="bh-booking-primary" href="<?php echo esc_url( $session_url ); ?>#details">Book Now →</a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ( $session_id ) :
                    $selected = null;
                    foreach ( $sessions as $item ) if ( absint( $item['id'] ) === $session_id ) { $selected = $item; break; }
                    if ( $selected ) :
                        $ticket_types = ! empty( $selected['ticket_types'] ) ? $selected['ticket_types'] : array();
                        $selected_quantities = array();
                        if ( isset( $_POST['tickets'] ) && is_array( $_POST['tickets'] ) ) {
                            foreach ( $_POST['tickets'] as $slug => $quantity ) $selected_quantities[ sanitize_key( $slug ) ] = absint( $quantity );
                        }
                        ?>
                        <section id="details" class="bh-booking-next-steps bh-booking-details">
                            <h2>Choose your tickets</h2>
                            <p><strong><?php echo esc_html( $selected['title'] ?: $group_title ); ?></strong><br><?php echo esc_html( wp_date( 'l, j F Y', strtotime( $date ) ) ); ?> · <?php echo esc_html( $selected['start_time'] ); ?></p>

                            <?php if ( ! empty( $ticket_types ) ) : ?>
                                <div class="bh-ticket-selector" data-ticket-selector data-session-action="<?php echo esc_attr( $selected['booking_action'] ); ?>">
                                    <div class="bh-ticket-selector-heading">
                                        <h3>Ticket types</h3>
                                        <p>Select how many of each ticket you need.</p>
                                    </div>
                                    <div class="bh-ticket-list">
                                        <?php foreach ( $ticket_types as $ticket ) :
                                            $slug = sanitize_key( $ticket['slug'] );
                                            $remaining = null !== $ticket['remaining'] ? absint( $ticket['remaining'] ) : 20;
                                            $max = min( 20, max( 0, $remaining ) );
                                            $value = isset( $selected_quantities[ $slug ] ) ? min( $max, $selected_quantities[ $slug ] ) : 0;
                                            $price_number = bubbahub_booking_ticket_price( $ticket['price'] );
                                            ?>
                                            <div class="bh-ticket-item" data-ticket-row data-ticket-slug="<?php echo esc_attr( $slug ); ?>" data-ticket-price="<?php echo esc_attr( $price_number ); ?>">
                                                <div class="bh-ticket-info">
                                                    <strong><?php echo esc_html( $ticket['name'] ); ?></strong>
                                                    <span><?php echo '' === $ticket['price'] ? 'Price on request' : ( $price_number > 0 ? '£' . number_format( $price_number, 2 ) : 'Free' ); ?></span>
                                                    <?php if ( null !== $ticket['remaining'] ) : ?><small><?php echo absint( $ticket['remaining'] ); ?> remaining</small><?php endif; ?>
                                                </div>
                                                <div class="bh-ticket-quantity">
                                                    <button type="button" class="bh-ticket-minus" data-ticket-minus aria-label="Decrease <?php echo esc_attr( $ticket['name'] ); ?>">−</button>
                                                    <input type="number" class="bh-ticket-input" name="tickets[<?php echo esc_attr( $slug ); ?>]" value="<?php echo absint( $value ); ?>" min="0" max="<?php echo absint( $max ); ?>" inputmode="numeric" data-ticket-quantity>
                                                    <button type="button" class="bh-ticket-plus" data-ticket-plus aria-label="Increase <?php echo esc_attr( $ticket['name'] ); ?>">+</button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="bh-ticket-summary" aria-live="polite">
                                        <div><span>Total tickets</span><strong data-ticket-total-places>0</strong></div>
                                        <div><span>Total</span><strong>£<span data-ticket-total-price>0.00</span></strong></div>
                                    </div>
                                </div>
                            <?php else : ?>
                                <div class="bh-booking-message">This session uses the default session booking capacity and price.</div>
                            <?php endif; ?>

                            <?php if ( 'external' === $selected['booking_action'] && ! empty( $selected['external_url'] ) ) : ?>
                                <a class="bh-booking-primary bh-ticket-action" data-ticket-action href="<?php echo esc_url( $selected['external_url'] ); ?>" target="_blank" rel="noopener noreferrer">Continue to External Booking →</a>
                            <?php elseif ( 'reserve_spot' === $selected['booking_action'] ) : ?>
                                <form method="post" class="bh-booking-reserve bh-booking-page-form" data-ticket-form>
                                    <?php wp_nonce_field( 'bh_reserve_page', 'bh_reserve_nonce' ); ?>
                                    <input type="hidden" name="session_id" value="<?php echo absint( $selected['id'] ); ?>">
                                    <div class="bh-booking-form-fields">
                                        <label>Name<input type="text" name="customer_name" autocomplete="name" required></label>
                                        <label>Email<input type="email" name="customer_email" autocomplete="email" required></label>
                                    </div>
                                    <div class="bh-ticket-hidden-fields" data-ticket-hidden-fields></div>
                                    <input type="hidden" name="places" value="0" data-ticket-places>
                                    <button type="submit" name="bh_reserve_submit" class="bh-booking-primary bh-ticket-submit" data-ticket-submit disabled>Reserve Spot</button>
                                </form>
                            <?php elseif ( ! empty( $selected['ninja_form_id'] ) && shortcode_exists( 'ninja_form' ) ) : ?>
                                <div class="bh-ninja-booking-wrap" data-ticket-form>
                                    <?php echo do_shortcode( '[ninja_form id="' . absint( $selected['ninja_form_id'] ) . '"]' ); ?>
                                    <div class="bh-ticket-hidden-fields" data-ticket-hidden-fields></div>
                                </div>
                            <?php else : ?>
                                <p>Continue with the booking option above.</p>
                            <?php endif; ?>
                        </section>
                    <?php endif;
                endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <?php if ( $session_id && ! empty( $selected ) ) : ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var selector = document.querySelector('[data-ticket-selector]');
        if (!selector) return;
        var rows = Array.prototype.slice.call(selector.querySelectorAll('[data-ticket-row]'));
        var totalPlaces = selector.querySelector('[data-ticket-total-places]');
        var totalPrice = selector.querySelector('[data-ticket-total-price]');
        var action = document.querySelector('[data-ticket-action]');
        var forms = document.querySelectorAll('[data-ticket-form]');
        var submit = document.querySelector('[data-ticket-submit]');
        var bookingBase = <?php echo wp_json_encode( home_url( '/book/' ) ); ?>;
        var groupId = <?php echo absint( $group_id ); ?>;
        var date = <?php echo wp_json_encode( $date ); ?>;
        var sessionId = <?php echo absint( $selected['id'] ); ?>;

        function values() {
            var result = [], places = 0, price = 0;
            rows.forEach(function (row) {
                var input = row.querySelector('[data-ticket-quantity]');
                if (!input) return;
                var qty = Math.max(0, parseInt(input.value || '0', 10) || 0);
                var max = parseInt(input.max || '20', 10) || 20;
                if (qty > max) { qty = max; input.value = qty; }
                var itemPrice = parseFloat(row.getAttribute('data-ticket-price') || '0') || 0;
                var slug = row.getAttribute('data-ticket-slug') || '';
                if (qty > 0 && slug) result.push({slug: slug, quantity: qty});
                places += qty;
                price += qty * itemPrice;
            });
            return {items: result, places: places, price: price};
        }

        function sync() {
            var data = values();
            if (totalPlaces) totalPlaces.textContent = data.places;
            if (totalPrice) totalPrice.textContent = data.price.toFixed(2);
            if (submit) submit.disabled = data.places < 1;

            forms.forEach(function (form) {
                var hidden = form.querySelector('[data-ticket-hidden-fields]');
                if (!hidden) return;
                hidden.innerHTML = '';
                data.items.forEach(function (item) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'tickets[' + item.slug + ']';
                    input.value = item.quantity;
                    hidden.appendChild(input);
                });
                var places = form.querySelector('[data-ticket-places]');
                if (places) places.value = data.places;
            });

            if (action) {
                var url = new URL(action.href, window.location.origin);
                url.searchParams.set('group_id', groupId);
                url.searchParams.set('date', date);
                url.searchParams.set('session_id', sessionId);
                data.items.forEach(function (item) { url.searchParams.set('ticket_' + item.slug, item.quantity); });
                url.searchParams.set('ticket_total', data.price.toFixed(2));
                action.href = url.toString();
                action.setAttribute('aria-disabled', data.places < 1 ? 'true' : 'false');
                action.classList.toggle('is-disabled', data.places < 1);
            }
        }

        selector.addEventListener('click', function (event) {
            var row = event.target.closest('[data-ticket-row]');
            if (!row) return;
            var input = row.querySelector('[data-ticket-quantity]');
            if (!input) return;
            if (event.target.closest('[data-ticket-plus]')) input.stepUp();
            if (event.target.closest('[data-ticket-minus]')) input.stepDown();
            sync();
        });
        selector.addEventListener('input', sync);
        sync();
    });
    </script>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}
