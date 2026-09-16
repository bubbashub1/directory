<?php
/**
 * Bubba Hub My Hub - Stage 9.
 * Expands My Bookings with ticket quantities, total, payment status and secure payment actions.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'bubbahub_my_bookings', 'bubbahub_stage9_my_bookings_shortcode' );
add_action( 'wp_enqueue_scripts', 'bubbahub_stage9_assets', 65 );
add_action( 'wp_ajax_bubbahub_stage9_pay_booking', 'bubbahub_stage9_pay_booking_ajax' );

function bubbahub_stage9_assets() {
    if ( ! is_user_logged_in() ) return;
    wp_register_style( 'bubbahub-myhub-stage9', false, array(), defined( 'BUBBAHUB_MYHUB_VERSION' ) ? BUBBAHUB_MYHUB_VERSION : '1.6.4' );
    wp_enqueue_style( 'bubbahub-myhub-stage9' );
    wp_add_inline_style( 'bubbahub-myhub-stage9', '.bh-stage9-extra{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-top:8px}.bh-stage9-extra>div{background:#fbfcfb;border:1px solid #e7eeeb;border-radius:11px;padding:8px 10px;font-size:11px;color:#5b706b}.bh-stage9-extra strong{display:block;font-size:9px;text-transform:uppercase;letter-spacing:.06em;color:#71857f;margin-bottom:2px}.bh-stage9-tickets{margin-top:12px;padding:11px 12px;border-radius:12px;background:#f7faf8;font-size:11px;color:#536d67}.bh-stage9-tickets strong{color:#263f39}.bh-stage9-actions .bh-pay{background:#315b4f;color:#fff}.bh-stage9-message{font-size:11px;margin-top:8px;color:#6a7d78}@media(max-width:600px){.bh-stage9-extra{grid-template-columns:1fr}}
    ' );
    wp_add_inline_script( 'jquery', 'window.BubbaHubStage9=' . wp_json_encode( array( 'ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('bubbahub_stage9') ) ) . ';', 'before' );
    wp_add_inline_script( 'jquery', '(function($){$(document).on("click",".bh-stage9-pay",function(e){e.preventDefault();var $b=$(this),id=parseInt($b.data("booking-id"),10);if(!id)return;$b.prop("disabled",true).text("Opening payment…");$.post(BubbaHubStage9.ajaxUrl,{action:"bubbahub_stage9_pay_booking",nonce:BubbaHubStage9.nonce,booking_id:id},function(r){if(r&&r.success&&r.data&&r.data.url){window.location.href=r.data.url;return;}$b.prop("disabled",false).text("Pay now");var msg=r&&r.data&&r.data.message?r.data.message:"Payment could not be started.";$b.after("<div class=\"bh-stage9-message\">"+msg+"</div>");},"json");});})(jQuery);', 'after' );
}

function bubbahub_stage9_booking_owner( $booking_id ) {
    return is_user_logged_in() && absint( get_post_meta( $booking_id, '_bh_user_id', true ) ) === get_current_user_id();
}

function bubbahub_stage9_money( $amount ) {
    $amount = (float) $amount;
    if ( function_exists( 'bubbahub_stripe_settings' ) ) {
        $settings = bubbahub_stripe_settings();
        $currency = strtoupper( $settings['currency'] ?? 'GBP' );
    } else { $currency = 'GBP'; }
    return esc_html( $currency . ' ' . number_format( $amount, 2 ) );
}

function bubbahub_stage9_payment_label( $status ) {
    $labels = array('paid'=>'Paid','pending'=>'Payment pending','failed'=>'Payment failed','not_required'=>'No payment required',''=>'Payment status not set');
    return $labels[ $status ] ?? ucwords( str_replace('_',' ', $status ) );
}

function bubbahub_stage9_get_bookings() {
    if ( ! is_user_logged_in() ) return array();
    $ids = get_posts(array('post_type'=>'bh_booking','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids','orderby'=>'date','order'=>'DESC','no_found_rows'=>true,'meta_query'=>array(array('key'=>'_bh_user_id','value'=>get_current_user_id(),'compare'=>'='),array('key'=>'_bh_status','value'=>array('confirmed','reserved'),'compare'=>'IN'))));
    $items=array(); $now=current_time('timestamp');
    foreach($ids as $booking_id){
        $session_id=absint(get_post_meta($booking_id,'_bh_session_id',true));
        if(!$session_id||'bh_session'!==get_post_type($session_id))continue;
        $date=sanitize_text_field(get_post_meta($session_id,'_bh_date',true)); $start=sanitize_text_field(get_post_meta($session_id,'_bh_start_time',true));
        $stamp=strtotime(trim($date.' '.$start)); if(!$stamp||$stamp<$now)continue;
        $group_id=absint(get_post_meta($session_id,'_bh_group_id',true)); $venue_id=absint(get_post_meta($session_id,'_bh_venue_id',true));
        $breakdown=get_post_meta($booking_id,'_bh_ticket_breakdown',true); if(!is_array($breakdown))$breakdown=array();
        $items[]=array('booking_id'=>$booking_id,'session_id'=>$session_id,'title'=>get_the_title($session_id),'group'=>$group_id?get_the_title($group_id):'','group_url'=>$group_id?get_permalink($group_id):'','venue'=>$venue_id?get_the_title($venue_id):'','date'=>$date,'start'=>$start,'stamp'=>$stamp,'status'=>sanitize_key(get_post_meta($booking_id,'_bh_status',true)),'places'=>absint(get_post_meta($booking_id,'_bh_places',true)),'total'=>(float)get_post_meta($booking_id,'_bh_total_price',true),'payment_status'=>sanitize_key(get_post_meta($booking_id,'_bh_payment_status',true)),'payment_method'=>sanitize_key(get_post_meta($booking_id,'_bh_payment_method',true)),'invoice_id'=>absint(get_post_meta($booking_id,'_bh_invoice_id',true)),'checkout_url'=>esc_url_raw(get_post_meta($booking_id,'_bh_stripe_checkout_url',true)),'tickets'=>$breakdown);
    }
    usort($items,function($a,$b){return $a['stamp']<=>$b['stamp'];}); return $items;
}

function bubbahub_stage9_pay_booking_ajax(){
    if(!is_user_logged_in())wp_send_json_error(array('message'=>'Please log in.'),401);
    check_ajax_referer('bubbahub_stage9','nonce'); $booking_id=absint($_POST['booking_id']??0);
    if(!$booking_id||'bh_booking'!==get_post_type($booking_id)||!bubbahub_stage9_booking_owner($booking_id))wp_send_json_error(array('message'=>'Booking not found.'),404);
    $status=sanitize_key(get_post_meta($booking_id,'_bh_payment_status',true)); $total=(float)get_post_meta($booking_id,'_bh_total_price',true);
    if('paid'===$status)wp_send_json_error(array('message'=>'This booking is already paid.'),409);
    if($total<=0||'not_required'===$status)wp_send_json_error(array('message'=>'Payment is not required for this booking.'),409);
    $existing=esc_url_raw(get_post_meta($booking_id,'_bh_stripe_checkout_url',true));
    if($existing){wp_send_json_success(array('url'=>$existing));}
    if(!function_exists('bubbahub_getpaid_create_checkout'))wp_send_json_error(array('message'=>'Payment integration is not available.'),500);
    $checkout=bubbahub_getpaid_create_checkout($booking_id);
    if(is_wp_error($checkout)||empty($checkout['url']))wp_send_json_error(array('message'=>is_wp_error($checkout)?$checkout->get_error_message():'Unable to create payment checkout.'),400);
    wp_send_json_success(array('url'=>esc_url_raw($checkout['url'])));
}

function bubbahub_stage9_my_bookings_shortcode(){
    if(!is_user_logged_in())return '<div class="bh-stage8-empty"><strong>Please log in</strong>Log in to view your bookings.</div>';
    $bookings=bubbahub_stage9_get_bookings(); ob_start(); ?>
    <section class="bh-stage8-bookings bh-stage9-bookings" aria-label="My bookings">
    <?php if(empty($bookings)): ?><div class="bh-stage8-empty"><strong>No upcoming bookings</strong>Your confirmed or reserved sessions will appear here.</div><?php else: foreach($bookings as $booking):
        $payment=$booking['payment_status']; $can_pay=$booking['total']>0 && !in_array($payment,array('paid','not_required'),true);
    ?>
    <article class="bh-stage8-booking">
      <div class="bh-stage8-booking-head"><div><div class="bh-stage8-eyebrow">My Booking #<?php echo esc_html($booking['booking_id']); ?></div><h3><?php echo esc_html($booking['title']?:$booking['group']?:'Booked session'); ?></h3><?php if($booking['group']): ?><div><?php echo esc_html($booking['group']); ?></div><?php endif; ?></div><span class="bh-stage8-status"><?php echo esc_html('confirmed'===$booking['status']?'Confirmed':'Reserved'); ?></span></div>
      <div class="bh-stage8-meta"><div><strong>Date & time</strong><?php echo esc_html(function_exists('bubbahub_myhub_booking_date_label')?bubbahub_myhub_booking_date_label($booking['date'],$booking['start']):wp_date('D j M Y, g:i A',$booking['stamp'])); ?></div><div><strong>Venue</strong><?php echo esc_html($booking['venue']?:'Venue to be confirmed'); ?></div></div>
      <div class="bh-stage9-extra"><div><strong>Places</strong><?php echo esc_html($booking['places']?:1); ?></div><div><strong>Total</strong><?php echo bubbahub_stage9_money($booking['total']); ?></div><div><strong>Payment</strong><?php echo esc_html(bubbahub_stage9_payment_label($payment)); ?></div></div>
      <?php if(!empty($booking['tickets'])): ?><div class="bh-stage9-tickets"><strong>Tickets</strong><?php foreach($booking['tickets'] as $ticket){if(!is_array($ticket))continue;$name=sanitize_text_field($ticket['name']??'Ticket');$qty=absint($ticket['quantity']??0);if($qty)echo '<div>'.esc_html($qty).' × '.esc_html($name).'</div>';} ?></div><?php endif; ?>
      <div class="bh-stage8-actions bh-stage9-actions"><?php if($booking['group_url']): ?><a href="<?php echo esc_url($booking['group_url']); ?>">View group</a><?php endif; ?><?php if($can_pay): ?><a href="#" class="bh-pay bh-stage9-pay" data-booking-id="<?php echo esc_attr($booking['booking_id']); ?>">Pay now</a><?php endif; ?><?php if($booking['checkout_url']&&!$can_pay&&'paid'!==$payment): ?><a href="<?php echo esc_url($booking['checkout_url']); ?>">Continue payment</a><?php endif; ?></div>
    </article>
    <?php endforeach; endif; ?></section>
    <?php return ob_get_clean();
}
