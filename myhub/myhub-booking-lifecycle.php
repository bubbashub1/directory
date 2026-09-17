<?php
/** BubbaHub My Hub customer booking lifecycle. */
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'bubbahub_customer_booking_dashboard', 'bubbahub_customer_booking_dashboard_shortcode' );
add_action( 'init', 'bubbahub_myhub_booking_lifecycle_actions', 20 );
add_action( 'added_post_meta', 'bubbahub_myhub_booking_status_email', 10, 4 );
add_action( 'wp_enqueue_scripts', 'bubbahub_myhub_booking_lifecycle_assets', 30 );

function bubbahub_myhub_booking_lifecycle_assets() {
    if ( ! is_user_logged_in() ) return;
    $file = plugin_dir_path( __FILE__ ) . 'myhub-booking.css';
    wp_enqueue_style( 'bubbahub-myhub-booking', plugin_dir_url( __FILE__ ) . 'myhub-booking.css', array(), file_exists( $file ) ? filemtime( $file ) : '1.0.0' );
}
function bubbahub_myhub_booking_owned( $id ) { return is_user_logged_in() && absint( get_post_meta( $id, '_bh_user_id', true ) ) === get_current_user_id() && 'bh_booking' === get_post_type( $id ); }
function bubbahub_myhub_booking_cancel_url( $id ) { return wp_nonce_url( add_query_arg( array( 'bh_booking_action' => 'cancel', 'booking_id' => absint( $id ) ) ), 'bh_cancel_booking_' . absint( $id ) ); }
function bubbahub_myhub_booking_lifecycle_actions() {
    if ( ! is_user_logged_in() || empty( $_GET['bh_booking_action'] ) || 'cancel' !== sanitize_key( wp_unslash( $_GET['bh_booking_action'] ) ) ) return;
    $id = isset( $_GET['booking_id'] ) ? absint( $_GET['booking_id'] ) : 0;
    $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    if ( ! $id || ! bubbahub_myhub_booking_owned( $id ) || ! wp_verify_nonce( $nonce, 'bh_cancel_booking_' . $id ) ) return;
    $status = get_post_meta( $id, '_bh_status', true );
    if ( in_array( $status, array( 'confirmed', 'reserved' ), true ) ) {
        update_post_meta( $id, '_bh_status', 'cancelled' );
        update_post_meta( $id, '_bh_cancelled_by', 'customer' );
        update_post_meta( $id, '_bh_cancelled_at', current_time( 'mysql' ) );
        $total = (float) get_post_meta( $id, '_bh_total_price', true );
        update_post_meta( $id, '_bh_payment_status', $total > 0 ? 'refund_requested' : 'cancelled' );
    }
    wp_safe_redirect( remove_query_arg( array( 'bh_booking_action', 'booking_id', '_wpnonce' ) ) );
    exit;
}
function bubbahub_myhub_booking_rows( $past = false ) {
    if ( ! is_user_logged_in() ) return array();
    $ids = get_posts( array( 'post_type' => 'bh_booking', 'post_status' => 'publish', 'posts_per_page' => 250, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_bh_user_id', 'value' => get_current_user_id() ) ), 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true ) );
    $now = current_time( 'timestamp' ); $rows = array();
    foreach ( $ids as $id ) {
        $sid = absint( get_post_meta( $id, '_bh_session_id', true ) ); if ( ! $sid || 'bh_session' !== get_post_type( $sid ) ) continue;
        $date = sanitize_text_field( get_post_meta( $sid, '_bh_date', true ) ); $start = sanitize_text_field( get_post_meta( $sid, '_bh_start_time', true ) ); $end = sanitize_text_field( get_post_meta( $sid, '_bh_end_time', true ) );
        $ts = strtotime( trim( $date . ' ' . $start ) ); if ( ! $past && $ts && $ts < $now ) continue;
        $gid = absint( get_post_meta( $sid, '_bh_group_id', true ) ); $vid = absint( get_post_meta( $sid, '_bh_venue_id', true ) ); $tickets = get_post_meta( $id, '_bh_ticket_breakdown', true );
        $rows[] = array( 'id'=>$id, 'group'=>$gid ? get_the_title($gid) : get_the_title($sid), 'group_url'=>$gid ? get_permalink($gid) : '', 'venue'=>$vid ? get_the_title($vid) : '', 'date'=>$date, 'start'=>$start, 'end'=>$end, 'timestamp'=>$ts ?: 0, 'places'=>max(1,absint(get_post_meta($id,'_bh_places',true))), 'total'=>(float)get_post_meta($id,'_bh_total_price',true), 'status'=>sanitize_key(get_post_meta($id,'_bh_status',true)), 'payment_status'=>sanitize_key(get_post_meta($id,'_bh_payment_status',true)), 'tickets'=>is_array($tickets)?$tickets:array() );
    }
    usort( $rows, function( $a, $b ) { return $a['timestamp'] <=> $b['timestamp']; } ); return $rows;
}
function bubbahub_myhub_booking_card( $r, $past = false ) {
    ob_start(); ?>
    <article class="bh-myhub-booking-card bh-myhub-booking-card-lifecycle"><div class="bh-myhub-booking-icon" aria-hidden="true">🎟️</div><div class="bh-myhub-booking-content"><div class="bh-myhub-eyebrow">Booking #<?php echo absint($r['id']); ?></div><h3><?php echo esc_html($r['group']); ?></h3><div class="bh-myhub-booking-date"><strong><?php echo esc_html($r['timestamp'] ? wp_date('D j M Y',$r['timestamp']) : $r['date']); ?></strong><?php echo $r['start'] ? ' · '.esc_html(wp_date('g:i A',strtotime($r['start']))) : ''; ?><?php echo $r['end'] ? ' – '.esc_html(wp_date('g:i A',strtotime($r['end']))) : ''; ?></div><?php if($r['venue']): ?><div class="bh-myhub-booking-venue">⌖ <?php echo esc_html($r['venue']); ?></div><?php endif; ?><div class="bh-myhub-booking-meta"><span><?php echo absint($r['places']); ?> place<?php echo 1 === $r['places'] ? '' : 's'; ?></span><span><?php echo $r['total'] > 0 ? '£'.number_format($r['total'],2) : 'Free / no payment'; ?></span><span class="bh-booking-status status-<?php echo esc_attr($r['status']); ?>"><?php echo esc_html(ucfirst($r['status'] ?: 'pending')); ?></span><?php if($r['payment_status']): ?><span><?php echo esc_html(ucfirst(str_replace('_',' ',$r['payment_status']))); ?></span><?php endif; ?></div><?php if($r['tickets']): ?><details class="bh-myhub-ticket-breakdown"><summary>Ticket breakdown</summary><ul><?php foreach($r['tickets'] as $ticket): ?><li><?php echo esc_html(isset($ticket['name'])?$ticket['name']:'Ticket'); ?> × <?php echo absint(isset($ticket['quantity'])?$ticket['quantity']:0); ?></li><?php endforeach; ?></ul></details><?php endif; ?><div class="bh-myhub-booking-actions"><?php if($r['group_url']): ?><a href="<?php echo esc_url($r['group_url']); ?>">View class</a><?php endif; ?><?php if(!$past && in_array($r['status'],array('confirmed','reserved'),true)): ?><a class="is-danger" href="<?php echo esc_url(bubbahub_myhub_booking_cancel_url($r['id'])); ?>" onclick="return window.confirm('Cancel this booking?');">Cancel booking</a><?php endif; ?></div></div></article>
    <?php return ob_get_clean();
}
function bubbahub_customer_booking_dashboard_shortcode() {
    if ( ! is_user_logged_in() ) return '<section class="bh-myhub-section"><div class="bh-myhub-login"><h2>My Bookings</h2><p>Please log in to view your bookings.</p></div></section>';
    $upcoming = bubbahub_myhub_booking_rows(false); $all = bubbahub_myhub_booking_rows(true); $up_ids = array_map('absint',wp_list_pluck($upcoming,'id')); $past = array_values(array_filter($all,function($r)use($up_ids){return !in_array(absint($r['id']),$up_ids,true);}));
    ob_start(); ?><section class="bh-myhub-section bh-myhub-bookings-section"><div class="bh-myhub-section-heading"><div><div class="bh-myhub-kicker">YOUR BOOKINGS</div><h2>My Bookings</h2><p>Keep track of your upcoming classes, tickets and booking status.</p></div></div><?php if($upcoming): ?><div class="bh-myhub-bookings-list"><?php foreach($upcoming as$r)echo bubbahub_myhub_booking_card($r); ?></div><?php else: ?><div class="bh-myhub-empty-family"><div class="bh-myhub-empty-icon">📅</div><div><h3>No upcoming bookings</h3><p>When you book a class, it will appear here automatically.</p></div></div><?php endif; ?><?php if($past): ?><details class="bh-myhub-past-bookings"><summary>Previous bookings (<?php echo count($past); ?>)</summary><div class="bh-myhub-bookings-list"><?php foreach(array_slice($past,0,50)as$r)echo bubbahub_myhub_booking_card($r,true); ?></div></details><?php endif; ?></section><?php return ob_get_clean();
}
function bubbahub_myhub_booking_created_email( $id ) {
    if ( !$id || get_post_meta($id,'_bh_confirmation_email_sent',true) ) return; $email=sanitize_email(get_post_meta($id,'_bh_customer_email',true)); if(!is_email($email))return; $sid=absint(get_post_meta($id,'_bh_session_id',true));$gid=absint(get_post_meta($id,'_bh_group_id',true));$date=get_post_meta($sid,'_bh_date',true);$start=get_post_meta($sid,'_bh_start_time',true);$name=sanitize_text_field(get_post_meta($id,'_bh_customer_name',true));$body="Hi {$name},\n\nYour Bubba Hub booking has been recorded.\n\nBooking: #{$id}\nClass: ".($gid?get_the_title($gid):get_the_title($sid))."\nDate: ".($date?wp_date('l, j F Y',strtotime($date)):'')."\nTime: {$start}\nPlaces: ".absint(get_post_meta($id,'_bh_places',true))."\n\nThanks,\nBubba Hub";if(wp_mail($email,'Bubba Hub booking confirmation #'.$id,$body))update_post_meta($id,'_bh_confirmation_email_sent',current_time('mysql'));
}
function bubbahub_myhub_booking_status_email($meta_id,$booking_id,$meta_key,$meta_value){if('_bh_status'===$meta_key&&'bh_booking'===get_post_type($booking_id))bubbahub_myhub_booking_created_email($booking_id);}
add_filter('the_content','bubbahub_myhub_append_booking_dashboard',30);
function bubbahub_myhub_append_booking_dashboard($content){if(is_admin()||!is_singular()||false===strpos($content,'[bubbahub_my_hub'))return$content;if(false!==strpos($content,'bubbahub_customer_booking_dashboard'))return$content;return $content . do_shortcode('[bubbahub_customer_booking_dashboard]');}
