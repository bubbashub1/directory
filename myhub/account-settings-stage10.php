<?php
/**
 * Bubba Hub My Hub - Stage 10.
 * Adds a secure booking-details view and downloadable calendar event.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'bubbahub_booking_details', 'bubbahub_stage10_booking_details_shortcode' );
add_action( 'init', 'bubbahub_stage10_calendar_download' );
add_action( 'wp_enqueue_scripts', 'bubbahub_stage10_assets', 70 );

function bubbahub_stage10_assets() {
    if ( ! is_user_logged_in() ) return;
    wp_register_style( 'bubbahub-myhub-stage10', false, array(), defined( 'BUBBAHUB_MYHUB_VERSION' ) ? BUBBAHUB_MYHUB_VERSION : '1.6.5' );
    wp_enqueue_style( 'bubbahub-myhub-stage10' );
    wp_add_inline_style( 'bubbahub-myhub-stage10', '.bh-stage10{max-width:760px;margin:0 auto}.bh-stage10-card{background:#fff;border:1px solid #e4ece8;border-radius:20px;padding:22px;box-shadow:0 8px 28px rgba(30,60,50,.06)}.bh-stage10-top{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:20px}.bh-stage10-eyebrow{text-transform:uppercase;letter-spacing:.08em;font-size:10px;font-weight:700;color:#71857f}.bh-stage10 h2{margin:5px 0 4px;font-size:25px;color:#263f39}.bh-stage10-ref{font-size:12px;color:#71857f}.bh-stage10-status{padding:7px 11px;border-radius:999px;background:#edf6f1;color:#315b4f;font-size:11px;font-weight:700;white-space:nowrap}.bh-stage10-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:18px 0}.bh-stage10-item{padding:13px;border:1px solid #e7eeeb;border-radius:13px;background:#fbfcfb}.bh-stage10-item strong{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:#71857f;margin-bottom:4px}.bh-stage10-item span{font-size:13px;color:#263f39}.bh-stage10-tickets{border-top:1px solid #e7eeeb;padding-top:16px;margin-top:4px}.bh-stage10-tickets h3{font-size:14px;margin:0 0 10px;color:#263f39}.bh-stage10-ticket{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eef2f0;font-size:12px;color:#536d67}.bh-stage10-total{display:flex;justify-content:space-between;padding-top:12px;font-weight:700;color:#263f39}.bh-stage10-actions{display:flex;flex-wrap:wrap;gap:9px;margin-top:20px}.bh-stage10-actions a{display:inline-block;padding:10px 14px;border-radius:11px;background:#f2f6f4;color:#315b4f;text-decoration:none;font-size:12px;font-weight:700}.bh-stage10-actions a.primary{background:#315b4f;color:#fff}.bh-stage10-note{margin-top:15px;padding:12px;border-radius:12px;background:#f7faf8;color:#647873;font-size:11px;line-height:1.5}.bh-stage10-empty{padding:24px;border:1px solid #e4ece8;border-radius:18px;background:#fff;text-align:center;color:#71857f}.bh-stage10-back{display:inline-block;margin-bottom:12px;font-size:12px;color:#315b4f;text-decoration:none}@media(max-width:600px){.bh-stage10-card{padding:17px;border-radius:16px}.bh-stage10-top{display:block}.bh-stage10-status{display:inline-block;margin-top:10px}.bh-stage10-grid{grid-template-columns:1fr}.bh-stage10-actions a{width:100%;text-align:center}}
' );
}

function bubbahub_stage10_owner( $booking_id ) {
    return is_user_logged_in() && 'bh_booking' === get_post_type( $booking_id ) && absint( get_post_meta( $booking_id, '_bh_user_id', true ) ) === get_current_user_id();
}

function bubbahub_stage10_get_booking( $booking_id ) {
    if ( ! $booking_id || ! bubbahub_stage10_owner( $booking_id ) ) return false;
    $session_id = absint( get_post_meta( $booking_id, '_bh_session_id', true ) );
    if ( ! $session_id || 'bh_session' !== get_post_type( $session_id ) ) return false;
    $date = sanitize_text_field( get_post_meta( $session_id, '_bh_date', true ) );
    $start = sanitize_text_field( get_post_meta( $session_id, '_bh_start_time', true ) );
    $stamp = strtotime( trim( $date . ' ' . $start ) );
    $group_id = absint( get_post_meta( $session_id, '_bh_group_id', true ) );
    $venue_id = absint( get_post_meta( $session_id, '_bh_venue_id', true ) );
    $tickets = get_post_meta( $booking_id, '_bh_ticket_breakdown', true );
    if ( ! is_array( $tickets ) ) $tickets = array();
    return array(
        'id'=>$booking_id,'session_id'=>$session_id,'title'=>get_the_title($session_id),'date'=>$date,'start'=>$start,'stamp'=>$stamp,
        'group_id'=>$group_id,'group'=>$group_id?get_the_title($group_id):'','group_url'=>$group_id?get_permalink($group_id):'',
        'venue'=>$venue_id?get_the_title($venue_id):'','status'=>sanitize_key(get_post_meta($booking_id,'_bh_status',true)),
        'places'=>absint(get_post_meta($booking_id,'_bh_places',true)),'total'=>(float)get_post_meta($booking_id,'_bh_total_price',true),
        'payment'=>sanitize_key(get_post_meta($booking_id,'_bh_payment_status',true)),'payment_method'=>sanitize_key(get_post_meta($booking_id,'_bh_payment_method',true)),
        'invoice_id'=>absint(get_post_meta($booking_id,'_bh_invoice_id',true)),'checkout'=>esc_url_raw(get_post_meta($booking_id,'_bh_stripe_checkout_url',true)),
        'tickets'=>$tickets
    );
}

function bubbahub_stage10_calendar_download() {
    if ( empty($_GET['bh_booking_calendar']) || ! is_user_logged_in() ) return;
    $booking_id = absint($_GET['bh_booking_calendar']);
    if ( ! bubbahub_stage10_owner($booking_id) ) wp_die('Booking not found.', 'Bubba Hub', array('response'=>404));
    $booking = bubbahub_stage10_get_booking($booking_id);
    if ( ! $booking || ! $booking['stamp'] ) wp_die('Booking date is unavailable.', 'Bubba Hub', array('response'=>404));
    $start = $booking['stamp'];
    $end = $start + 3600;
    $uid = 'bubbahub-booking-' . $booking_id . '@' . wp_parse_url(home_url(), PHP_URL_HOST);
    $summary = $booking['title'] ?: ($booking['group'] ?: 'Bubba Hub booking');
    $location = $booking['venue'];
    $description = 'Bubba Hub booking #' . $booking_id . ( $booking['group'] ? ' - ' . $booking['group'] : '' );
    $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Bubba Hub//My Bookings//EN\r\nBEGIN:VEVENT\r\nUID:" . esc_html($uid) . "\r\nDTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\nDTSTART:" . gmdate('Ymd\THis\Z',$start) . "\r\nDTEND:" . gmdate('Ymd\THis\Z',$end) . "\r\nSUMMARY:" . bubbahub_stage10_ics_escape($summary) . "\r\nLOCATION:" . bubbahub_stage10_ics_escape($location) . "\r\nDESCRIPTION:" . bubbahub_stage10_ics_escape($description) . "\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
    nocache_headers();
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="bubbahub-booking-' . $booking_id . '.ics"');
    echo $ics;
    exit;
}

function bubbahub_stage10_ics_escape( $value ) {
    return str_replace(array('\\',';',',',"\r","\n"),array('\\\\','\\;','\\,','','\\n'),sanitize_text_field($value));
}

function bubbahub_stage10_payment_label( $status ) {
    $labels=array('paid'=>'Paid','pending'=>'Payment pending','failed'=>'Payment failed','not_required'=>'No payment required',''=>'Payment status not set');
    return $labels[$status] ?? ucwords(str_replace('_',' ',$status));
}

function bubbahub_stage10_details_url( $booking_id ) {
    $page = get_page_by_path('my-booking');
    if ( ! $page ) $page = get_page_by_path('my-bookings');
    $url = $page ? get_permalink($page) : home_url('/my-booking/');
    return add_query_arg('booking_id',absint($booking_id),$url);
}

function bubbahub_stage10_back_url() {
    $page = get_page_by_path('my-bookings');
    return $page ? get_permalink($page) : home_url('/my-bookings/');
}

function bubbahub_stage10_contact_url( $booking ) {
    $subject = rawurlencode('Bubba Hub booking #' . $booking['id'] . ' query');
    $body = rawurlencode('Booking #' . $booking['id'] . "\nSession: " . $booking['title'] . "\nDate: " . $booking['date'] . ' ' . $booking['start']);
    return 'mailto:' . antispambot(get_option('admin_email')) . '?subject=' . $subject . '&body=' . $body;
}

function bubbahub_stage10_shortcode_output( $booking ) {
    $back = bubbahub_stage10_back_url();
    $calendar = add_query_arg('bh_booking_calendar',$booking['id'],home_url('/'));
    $can_pay = $booking['total'] > 0 && ! in_array($booking['payment'],array('paid','not_required'),true);
    ob_start(); ?>
    <section class="bh-stage10">
      <a class="bh-stage10-back" href="<?php echo esc_url($back); ?>">← Back to My Bookings</a>
      <article class="bh-stage10-card">
        <div class="bh-stage10-top"><div><div class="bh-stage10-eyebrow">Booking details</div><h2><?php echo esc_html($booking['title'] ?: $booking['group'] ?: 'Booked session'); ?></h2><div class="bh-stage10-ref">Booking #<?php echo esc_html($booking['id']); ?></div></div><span class="bh-stage10-status"><?php echo esc_html($booking['status']==='confirmed'?'Confirmed':'Reserved'); ?></span></div>
        <div class="bh-stage10-grid">
          <div class="bh-stage10-item"><strong>Date & time</strong><span><?php echo esc_html(function_exists('bubbahub_myhub_booking_date_label')?bubbahub_myhub_booking_date_label($booking['date'],$booking['start']):wp_date('D j M Y, g:i A',$booking['stamp'])); ?></span></div>
          <div class="bh-stage10-item"><strong>Venue</strong><span><?php echo esc_html($booking['venue']?:'Venue to be confirmed'); ?></span></div>
          <div class="bh-stage10-item"><strong>Group</strong><span><?php echo esc_html($booking['group']?:'—'); ?></span></div>
          <div class="bh-stage10-item"><strong>Places</strong><span><?php echo esc_html($booking['places']?:1); ?></span></div>
          <div class="bh-stage10-item"><strong>Payment</strong><span><?php echo esc_html(bubbahub_stage10_payment_label($booking['payment'])); ?></span></div>
          <div class="bh-stage10-item"><strong>Payment method</strong><span><?php echo esc_html($booking['payment_method']?ucwords(str_replace('_',' ',$booking['payment_method'])):'—'); ?></span></div>
        </div>
        <?php if(!empty($booking['tickets'])): ?><div class="bh-stage10-tickets"><h3>Tickets</h3><?php foreach($booking['tickets'] as $ticket){if(!is_array($ticket))continue;$name=sanitize_text_field($ticket['name']??'Ticket');$qty=absint($ticket['quantity']??0);if(!$qty)continue;$price=(float)($ticket['unit_price']??$ticket['price']??0);?><div class="bh-stage10-ticket"><span><?php echo esc_html($qty.' × '.$name); ?></span><span><?php echo $price>0?esc_html('£'.number_format($price*$qty,2)):''; ?></span></div><?php endforeach; ?><div class="bh-stage10-total"><span>Total</span><span>£<?php echo esc_html(number_format($booking['total'],2)); ?></span></div></div><?php endif; ?>
        <div class="bh-stage10-actions">
          <?php if($booking['group_url']): ?><a class="primary" href="<?php echo esc_url($booking['group_url']); ?>">View group</a><?php endif; ?>
          <a href="<?php echo esc_url($calendar); ?>">Add to calendar</a>
          <?php if($can_pay && $booking['checkout']): ?><a href="<?php echo esc_url($booking['checkout']); ?>">Continue payment</a><?php endif; ?>
          <a href="<?php echo esc_url(bubbahub_stage10_contact_url($booking)); ?>">Contact Bubba Hub</a>
          <?php if(function_exists('bubbahub_stage11_render_actions')): echo bubbahub_stage11_render_actions($booking['id']); endif; ?>
        </div>
        <div id="bubbahub-cancellation">
          <?php if(function_exists('bubbahub_stage11_cancellation_shortcode')) echo bubbahub_stage11_cancellation_shortcode(); ?>
        </div>
        <div class="bh-stage10-note">Keep this booking number for reference. If your booking is reserved rather than confirmed, payment or organiser confirmation may still be required.</div>
      </article>
    </section>
    <?php return ob_get_clean();
}

function bubbahub_stage10_booking_details_shortcode() {
    if ( ! is_user_logged_in() ) return '<div class="bh-stage10-empty"><strong>Please log in</strong><br>Log in to view booking details.</div>';
    $booking_id = absint($_GET['booking_id'] ?? 0);
    if ( ! $booking_id ) return '<div class="bh-stage10-empty"><strong>Select a booking</strong><br>Open a booking from My Bookings to see its details.</div>';
    $booking = bubbahub_stage10_get_booking($booking_id);
    if ( ! $booking ) return '<div class="bh-stage10-empty"><strong>Booking not found</strong><br>This booking is not available to your account.</div>';
    return bubbahub_stage10_shortcode_output($booking);
}
