<?php
/**
 * Bubba Hub My Hub - Stage 8.
 * Adds a dedicated My Bookings view using the existing bh_booking / bh_session data.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'bubbahub_my_bookings', 'bubbahub_stage8_my_bookings_shortcode' );
add_action( 'wp_enqueue_scripts', 'bubbahub_stage8_assets', 60 );

function bubbahub_stage8_assets() {
    if ( ! is_user_logged_in() ) return;
    wp_register_style( 'bubbahub-myhub-stage8', false, array(), defined( 'BUBBAHUB_MYHUB_VERSION' ) ? BUBBAHUB_MYHUB_VERSION : '1.6.3' );
    wp_enqueue_style( 'bubbahub-myhub-stage8' );
    wp_add_inline_style( 'bubbahub-myhub-stage8', '
        .bh-stage8-bookings{display:grid;gap:14px;margin:18px 0}.bh-stage8-booking{border:1px solid #e2ebe7;border-radius:18px;background:#fff;padding:18px;box-shadow:0 4px 18px rgba(38,70,63,.06)}.bh-stage8-booking-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.bh-stage8-booking h3{margin:0 0 5px;font-size:17px;color:#203b35}.bh-stage8-eyebrow{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#718a84;margin-bottom:5px}.bh-stage8-status{display:inline-flex;white-space:nowrap;border-radius:999px;padding:5px 9px;background:#e7f2ed;color:#315b4f;font-size:10px;font-weight:800}.bh-stage8-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:14px}.bh-stage8-meta div{background:#f7faf8;border-radius:12px;padding:9px 11px;color:#536d67;font-size:11px}.bh-stage8-meta strong{display:block;color:#213b35;font-size:10px;margin-bottom:2px}.bh-stage8-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}.bh-stage8-actions a{display:inline-flex;align-items:center;justify-content:center;border-radius:10px;padding:9px 12px;background:#edf4f1;color:#31564d;text-decoration:none;font-size:11px;font-weight:800}.bh-stage8-empty{padding:24px;border:1px dashed #ccdcd6;border-radius:16px;text-align:center;color:#647a75;background:#fafcfb}.bh-stage8-empty strong{display:block;color:#29443e;margin-bottom:5px}@media(max-width:600px){.bh-stage8-meta{grid-template-columns:1fr}.bh-stage8-booking-head{flex-direction:column}.bh-stage8-status{align-self:flex-start}}
    ' );
}

function bubbahub_stage8_get_bookings() {
    if ( ! is_user_logged_in() ) return array();

    $ids = get_posts( array(
        'post_type'      => 'bh_booking',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => true,
        'meta_query'     => array(
            array( 'key' => '_bh_user_id', 'value' => get_current_user_id(), 'compare' => '=' ),
            array( 'key' => '_bh_status', 'value' => array( 'confirmed', 'reserved' ), 'compare' => 'IN' ),
        ),
    ) );

    $items = array();
    $now = current_time( 'timestamp' );

    foreach ( $ids as $booking_id ) {
        $session_id = absint( get_post_meta( $booking_id, '_bh_session_id', true ) );
        if ( ! $session_id || 'bh_session' !== get_post_type( $session_id ) ) continue;

        $date  = sanitize_text_field( get_post_meta( $session_id, '_bh_date', true ) );
        $start = sanitize_text_field( get_post_meta( $session_id, '_bh_start_time', true ) );
        $stamp = strtotime( trim( $date . ' ' . $start ) );
        if ( ! $stamp || $stamp < $now ) continue;

        $group_id = absint( get_post_meta( $session_id, '_bh_group_id', true ) );
        $venue_id = absint( get_post_meta( $session_id, '_bh_venue_id', true ) );
        $status   = sanitize_key( get_post_meta( $booking_id, '_bh_status', true ) );

        $items[] = array(
            'booking_id' => $booking_id,
            'session_id' => $session_id,
            'title'      => get_the_title( $session_id ),
            'group'      => $group_id ? get_the_title( $group_id ) : '',
            'group_url'  => $group_id ? get_permalink( $group_id ) : '',
            'venue'      => $venue_id ? get_the_title( $venue_id ) : '',
            'date'       => $date,
            'start'      => $start,
            'stamp'      => $stamp,
            'status'     => $status,
        );
    }

    usort( $items, function( $a, $b ) { return $a['stamp'] <=> $b['stamp']; } );
    return $items;
}

function bubbahub_stage8_my_bookings_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<div class="bh-stage8-empty"><strong>Please log in</strong>Log in to view your bookings.</div>';
    }

    $bookings = bubbahub_stage8_get_bookings();
    ob_start();
    ?>
    <section class="bh-stage8-bookings" aria-label="My bookings">
        <?php if ( empty( $bookings ) ) : ?>
            <div class="bh-stage8-empty">
                <strong>No upcoming bookings</strong>
                Your confirmed or reserved sessions will appear here.
            </div>
        <?php else : ?>
            <?php foreach ( $bookings as $booking ) : ?>
                <article class="bh-stage8-booking">
                    <div class="bh-stage8-booking-head">
                        <div>
                            <div class="bh-stage8-eyebrow">My Booking</div>
                            <h3><?php echo esc_html( $booking['title'] ?: $booking['group'] ?: 'Booked session' ); ?></h3>
                            <?php if ( $booking['group'] ) : ?><div><?php echo esc_html( $booking['group'] ); ?></div><?php endif; ?>
                        </div>
                        <span class="bh-stage8-status"><?php echo esc_html( 'confirmed' === $booking['status'] ? 'Confirmed' : 'Reserved' ); ?></span>
                    </div>
                    <div class="bh-stage8-meta">
                        <div><strong>Date & time</strong><?php echo esc_html( function_exists( 'bubbahub_myhub_booking_date_label' ) ? bubbahub_myhub_booking_date_label( $booking['date'], $booking['start'] ) : wp_date( 'D j M Y, g:i A', $booking['stamp'] ) ); ?></div>
                        <div><strong>Venue</strong><?php echo esc_html( $booking['venue'] ?: 'Venue to be confirmed' ); ?></div>
                    </div>
                    <div class="bh-stage8-actions">
                        <?php if ( $booking['group_url'] ) : ?><a href="<?php echo esc_url( $booking['group_url'] ); ?>">View group</a><?php endif; ?>
                        <a href="<?php echo esc_url( home_url( '/book/' ) ); ?>">Booking page</a>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}
