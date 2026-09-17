<?php
/**
 * BubbaHub unified customer booking flow.
 * Venue -> class -> session -> tickets -> customer details -> payment.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'bubbahub_booking_flow', 'bubbahub_booking_flow_shortcode' );
add_action( 'wp_enqueue_scripts', 'bubbahub_booking_flow_assets', 40 );

function bubbahub_booking_flow_assets() {
    if ( ! is_page( 'book' ) && ! isset( $_GET['bubbahub_booking_flow'] ) ) return;
    wp_register_style( 'bubbahub-booking-flow', BUBBAHUB_DIRECTORY_URL . 'assets/booking-flow.css', array(), BUBBAHUB_DIRECTORY_VERSION );
    wp_register_script( 'bubbahub-booking-flow', BUBBAHUB_DIRECTORY_URL . 'assets/booking-flow.js', array(), BUBBAHUB_DIRECTORY_VERSION, true );
    wp_enqueue_style( 'bubbahub-booking-flow' );
    wp_enqueue_script( 'bubbahub-booking-flow' );
}

function bubbahub_booking_flow_sessions( $venue_id = 0, $group_id = 0 ) {
    $meta = array();
    if ( $venue_id ) $meta[] = array( 'key' => '_bh_venue_id', 'value' => $venue_id );
    if ( $group_id ) $meta[] = array( 'key' => '_bh_group_id', 'value' => $group_id );
    $args = array( 'post_type' => 'bh_session', 'post_status' => 'publish', 'posts_per_page' => 100, 'orderby' => 'meta_value', 'meta_key' => '_bh_date', 'order' => 'ASC', 'no_found_rows' => true );
    if ( $meta ) $args['meta_query'] = $meta;
    $query = new WP_Query( $args );
    $out = array();
    while ( $query->have_posts() ) { $query->the_post(); $id = get_the_ID(); $date = get_post_meta( $id, '_bh_date', true ); $start = get_post_meta( $id, '_bh_start_time', true ); if ( ! $date || strtotime( $date . ' ' . $start ) < current_time( 'timestamp' ) ) continue; $gid = absint( get_post_meta( $id, '_bh_group_id', true ) ); $vid = absint( get_post_meta( $id, '_bh_venue_id', true ) ); if ( ! $gid || 'group' !== get_post_type( $gid ) ) continue; if ( $venue_id && $vid !== $venue_id ) continue; if ( $group_id && $gid !== $group_id ) continue; $stats = function_exists( 'bubbahub_booking_session_stats' ) ? bubbahub_booking_session_stats( $id ) : array(); if ( ! empty( $stats['full'] ) ) continue; $out[] = array( 'id'=>$id, 'group_id'=>$gid, 'group'=>get_the_title($gid), 'group_url'=>get_permalink($gid), 'venue_id'=>$vid, 'venue'=>$vid?get_the_title($vid):'', 'date'=>$date, 'start'=>$start, 'end'=>get_post_meta($id,'_bh_end_time',true), 'remaining'=>isset($stats['remaining'])?$stats['remaining']:null, 'ticket_types'=>isset($stats['ticket_types'])?$stats['ticket_types']:array() ); }
    wp_reset_postdata(); return $out;
}

function bubbahub_booking_flow_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'group_id'=>0, 'venue_id'=>0 ), $atts, 'bubbahub_booking_flow' );
    $group_id = absint( $atts['group_id'] ?: ( $_GET['group_id'] ?? 0 ) );
    $venue_id = absint( $atts['venue_id'] ?: ( $_GET['venue_id'] ?? 0 ) );
    $sessions = bubbahub_booking_flow_sessions( $venue_id, $group_id );
    $venues = array(); $seen=array();
    foreach ( $sessions as $s ) if ( $s['venue_id'] && empty($seen[$s['venue_id']]) ) { $seen[$s['venue_id']]=1; $venues[]=$s['venue_id']; }
    ob_start(); ?>
    <section class="bh-booking-flow" data-booking-flow>
      <div class="bh-booking-flow-head"><span>BOOK A CLASS</span><h2>Find and book your session</h2><p>Choose your venue, class and session, then complete your booking.</p></div>
      <div class="bh-booking-flow-steps"><span class="is-active">1 Venue</span><span>2 Class & session</span><span>3 Tickets</span><span>4 Details</span><span>5 Payment</span></div>
      <div class="bh-booking-flow-grid">
        <label><strong>Venue</strong><select data-booking-venue><option value="">All venues</option><?php foreach($venues as $v): ?><option value="<?php echo absint($v); ?>" <?php selected($venue_id,$v); ?>><?php echo esc_html(get_the_title($v)); ?></option><?php endforeach; ?></select></label>
        <label><strong>Class</strong><select data-booking-group><option value="">All classes</option><?php $groups=array(); foreach($sessions as $s)$groups[$s['group_id']]=$s['group']; foreach($groups as $id=>$name): ?><option value="<?php echo absint($id); ?>" <?php selected($group_id,$id); ?>><?php echo esc_html($name); ?></option><?php endforeach; ?></select></label>
      </div>
      <div class="bh-booking-session-list" data-booking-sessions>
      <?php if($sessions): foreach($sessions as $s): ?><button type="button" class="bh-booking-session" data-session-id="<?php echo absint($s['id']); ?>" data-venue-id="<?php echo absint($s['venue_id']); ?>" data-group-id="<?php echo absint($s['group_id']); ?>"><span class="bh-booking-session-title"><?php echo esc_html($s['group']); ?></span><span><?php echo esc_html(wp_date('D j M Y',strtotime($s['date']))); ?> · <?php echo esc_html($s['start']); ?><?php echo $s['end']?'–'.esc_html($s['end']):''; ?></span><small><?php echo esc_html($s['venue']); ?><?php echo null!==$s['remaining']?' · '.absint($s['remaining']).' spaces left':''; ?></small></button><?php endforeach; else: ?><p>No bookable sessions are currently available.</p><?php endif; ?>
      </div>
      <div class="bh-booking-flow-selected" data-booking-selected hidden></div>
    </section>
    <?php return ob_get_clean();
}
