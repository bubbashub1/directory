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
    wp_enqueue_style( 'bubbahub-booking-frontend', $url . 'assets/booking.css', array(), file_exists( $css ) ? filemtime( $css ) : '1.2.0' );
    if ( is_singular( 'group' ) ) {
        wp_enqueue_script( 'bubbahub-booking-frontend', $url . 'assets/booking.js', array(), file_exists( $js ) ? filemtime( $js ) : '1.2.0', true );
    }
}

add_action( 'wp_ajax_bubbahub_booking_reserve', 'bubbahub_booking_reserve_ajax' );
add_action( 'wp_ajax_nopriv_bubbahub_booking_reserve', 'bubbahub_booking_reserve_ajax' );
function bubbahub_booking_reserve_ajax() {
    check_ajax_referer( 'bubbahub_booking', 'nonce' );
    $session_id = isset( $_POST['session_id'] ) ? absint( $_POST['session_id'] ) : 0;
    $places = isset( $_POST['places'] ) ? max( 1, absint( $_POST['places'] ) ) : 1;
    $name = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
    $email = isset( $_POST['customer_email'] ) ? sanitize_email( wp_unslash( $_POST['customer_email'] ) ) : '';
    if ( ! $session_id || get_post_type( $session_id ) !== 'bh_session' ) wp_send_json_error( array( 'message' => 'Invalid booking session.' ), 400 );
    if ( ! $name || ! is_email( $email ) ) wp_send_json_error( array( 'message' => 'Please enter your name and a valid email address.' ), 400 );
    if ( ! (bool) bubbahub_booking_meta( $session_id, '_bh_reserve_enabled', false ) ) wp_send_json_error( array( 'message' => 'Reservations are not enabled for this session.' ), 400 );
    $booking_id = bubbahub_booking_create( array(
        'session_id' => $session_id,
        'group_id' => absint( bubbahub_booking_meta( $session_id, '_bh_group_id', 0 ) ),
        'venue_id' => absint( bubbahub_booking_meta( $session_id, '_bh_venue_id', 0 ) ),
        'customer_name' => $name, 'customer_email' => $email, 'places' => $places,
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
                    <select id="bh-booking-date" class="bh-booking-select">
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
        if ( ! isset( $_POST['bh_reserve_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bh_reserve_nonce'] ) ), 'bh_reserve_page' ) ) {
            $notice = '<div class="bh-booking-message is-error">Security check failed. Please try again.</div>';
        } else {
            $post_session = isset( $_POST['session_id'] ) ? absint( $_POST['session_id'] ) : 0;
            $name = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
            $email = isset( $_POST['customer_email'] ) ? sanitize_email( wp_unslash( $_POST['customer_email'] ) ) : '';
            $places = isset( $_POST['places'] ) ? max( 1, absint( $_POST['places'] ) ) : 1;
            if ( ! $name || ! is_email( $email ) ) {
                $notice = '<div class="bh-booking-message is-error">Please enter your name and a valid email address.</div>';
            } else {
                $sid_group = absint( bubbahub_booking_meta( $post_session, '_bh_group_id', 0 ) );
                if ( $post_session && get_post_type( $post_session ) === 'bh_session' && $sid_group === $group_id && (bool) bubbahub_booking_meta( $post_session, '_bh_reserve_enabled', false ) ) {
                    $booking_id = bubbahub_booking_create( array(
                        'session_id' => $post_session, 'group_id' => $group_id,
                        'venue_id' => absint( bubbahub_booking_meta( $post_session, '_bh_venue_id', 0 ) ),
                        'customer_name' => $name, 'customer_email' => $email, 'places' => $places,
                        'status' => 'reserved', 'payment_status' => 'not_required', 'payment_method' => 'reservation',
                    ) );
                    $notice = is_wp_error( $booking_id )
                        ? '<div class="bh-booking-message is-error">' . esc_html( $booking_id->get_error_message() ) . '</div>'
                        : '<div class="bh-booking-message is-success">Your space has been reserved. Reference #' . absint( $booking_id ) . '.</div>';
                    if ( ! is_wp_error( $booking_id ) ) $session_id = $post_session;
                } else {
                    $notice = '<div class="bh-booking-message is-error">That reservation is no longer available.</div>';
                }
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
                    <p>Choose the session you want, then follow the booking option provided.</p>
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
                                <?php if ( $item['price'] !== '' ) : ?><div class="bh-booking-session-price"><?php echo esc_html( is_numeric( $item['price'] ) ? '£' . $item['price'] : $item['price'] ); ?></div><?php endif; ?>
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
                    if ( $selected ) : ?>
                        <section id="details" class="bh-booking-next-steps bh-booking-details">
                            <h2>Next step</h2>
                            <p><strong><?php echo esc_html( $selected['title'] ?: $group_title ); ?></strong><br><?php echo esc_html( wp_date( 'l, j F Y', strtotime( $date ) ) ); ?> · <?php echo esc_html( $selected['start_time'] ); ?></p>
                            <?php if ( 'external' === $selected['booking_action'] && ! empty( $selected['external_url'] ) ) : ?>
                                <a class="bh-booking-primary" href="<?php echo esc_url( $selected['external_url'] ); ?>" target="_blank" rel="noopener noreferrer">Continue to External Booking →</a>
                            <?php elseif ( 'reserve_spot' === $selected['booking_action'] ) : ?>
                                <form method="post" class="bh-booking-reserve bh-booking-page-form">
                                    <?php wp_nonce_field( 'bh_reserve_page', 'bh_reserve_nonce' ); ?>
                                    <input type="hidden" name="session_id" value="<?php echo absint( $selected['id'] ); ?>">
                                    <label>Name<input type="text" name="customer_name" autocomplete="name" required></label>
                                    <label>Email<input type="email" name="customer_email" autocomplete="email" required></label>
                                    <label>Places<input type="number" name="places" min="1" value="1" required></label>
                                    <button type="submit" name="bh_reserve_submit" class="bh-booking-primary">Reserve Spot</button>
                                </form>
                            <?php elseif ( ! empty( $selected['ninja_form_id'] ) && shortcode_exists( 'ninja_form' ) ) : ?>
                                <?php echo do_shortcode( '[ninja_form id="' . absint( $selected['ninja_form_id'] ) . '"]' ); ?>
                            <?php else : ?>
                                <p>Continue with the booking option above.</p>
                            <?php endif; ?>
                        </section>
                    <?php endif;
                endif; ?>
            <?php endif; ?>
        </div>
    </main>
    <?php
    return ob_get_clean();
}
