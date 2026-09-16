<?php
/**
 * BubbaHub My Hub
 *
 * Front-end family dashboard for logged-in parents.
 * Shortcode: [bubbahub_my_hub]
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'BUBBAHUB_MYHUB_VERSION' ) ) define( 'BUBBAHUB_MYHUB_VERSION', '1.4.0' );
if ( ! defined( 'BUBBAHUB_MYHUB_PATH' ) ) define( 'BUBBAHUB_MYHUB_PATH', plugin_dir_path( __FILE__ ) );
if ( ! defined( 'BUBBAHUB_MYHUB_URL' ) ) define( 'BUBBAHUB_MYHUB_URL', plugin_dir_url( __FILE__ ) );

add_action( 'init', 'bubbahub_myhub_register_child_post_type' );
add_action( 'wp_enqueue_scripts', 'bubbahub_myhub_register_assets' );
add_shortcode( 'bubbahub_my_hub', 'bubbahub_myhub_shortcode' );
add_action( 'init', 'bubbahub_myhub_handle_child_form' );

function bubbahub_myhub_register_child_post_type() {
    register_post_type( 'bh_child', array(
        'labels' => array('name' => 'Family Children','singular_name' => 'Family Child','add_new_item' => 'Add Child','edit_item' => 'Edit Child'),
        'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'menu_icon' => 'dashicons-groups',
        'supports' => array( 'title', 'author' ), 'capability_type' => 'post', 'map_meta_cap' => true,
    ) );
}
function bubbahub_myhub_register_assets() { wp_register_style( 'bubbahub-myhub', BUBBAHUB_MYHUB_URL . 'myhub.css', array(), BUBBAHUB_MYHUB_VERSION ); }
function bubbahub_myhub_field( $post_id, $field, $default = '' ) { if(function_exists('get_field')){$value=get_field($field,$post_id);if($value!==null&&$value!==false&&$value!=='')return $value;} $value=get_post_meta($post_id,$field,true);return ($value!==''&&$value!==false)?$value:$default; }
function bubbahub_myhub_child_query() { if(!is_user_logged_in())return new WP_Query(); return new WP_Query(array('post_type'=>'bh_child','post_status'=>'publish','author'=>get_current_user_id(),'posts_per_page'=>-1,'orderby'=>'date','order'=>'ASC','no_found_rows'=>true)); }
function bubbahub_myhub_age($dob){if(!$dob)return ''; $birth=DateTime::createFromFormat('Y-m-d',sanitize_text_field($dob));if(!$birth)return ''; $today=new DateTime('today');if($birth>$today)return ''; $age=$birth->diff($today);$parts=array();if($age->y)$parts[]=$age->y.'y';if($age->m||!$parts)$parts[]=$age->m.'m';return implode(' ',$parts);}
function bubbahub_myhub_countdown($date){if(!$date)return ''; $deadline=DateTime::createFromFormat('Y-m-d',sanitize_text_field($date));if(!$deadline)return ''; $today=new DateTime('today');if($deadline<=$today)return 'Deadline passed';$diff=$today->diff($deadline);$parts=array();if($diff->y)$parts[]=$diff->y.'y';if($diff->m)$parts[]=$diff->m.'m';if($diff->d||!$parts)$parts[]=$diff->d.'d';return implode(' ',$parts).' left to apply';}
function bubbahub_myhub_format_date($date){if(!$date)return ''; $timestamp=strtotime($date);return $timestamp?wp_date('j M Y',$timestamp):'';}
function bubbahub_myhub_bookings(){if(!is_user_logged_in())return array();$ids=get_posts(array('post_type'=>'bh_booking','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids','meta_query'=>array(array('key'=>'_bh_user_id','value'=>get_current_user_id(),'compare'=>'='),array('key'=>'_bh_status','value'=>array('confirmed','reserved'),'compare'=>'IN')),'no_found_rows'=>true));$items=array();foreach($ids as $booking_id){$session_id=absint(get_post_meta($booking_id,'_bh_session_id',true));if(!$session_id||get_post_type($session_id)!=='bh_session')continue;$date=get_post_meta($session_id,'_bh_date',true);$start=get_post_meta($session_id,'_bh_start_time',true);$timestamp=strtotime(trim($date.' '.$start));if(!$timestamp||$timestamp<current_time('timestamp'))continue;$group_id=absint(get_post_meta($session_id,'_bh_group_id',true));$venue_id=absint(get_post_meta($session_id,'_bh_venue_id',true));$items[]=array('id'=>$booking_id,'session_id'=>$session_id,'title'=>get_the_title($session_id),'date'=>$date,'start'=>$start,'timestamp'=>$timestamp,'group'=>$group_id?get_the_title($group_id):'','venue'=>$venue_id?get_the_title($venue_id):'','status'=>get_post_meta($booking_id,'_bh_status',true));}usort($items,function($a,$b){return $a['timestamp']<=>$b['timestamp'];});return $items;}
function bubbahub_myhub_booking_date_label($date,$time){$timestamp=strtotime(trim($date.' '.$time));if(!$timestamp)return bubbahub_myhub_format_date($date).($time?', '.$time:'');$today=current_time('timestamp');$tomorrow=strtotime('+1 day',strtotime(wp_date('Y-m-d',$today)));$day=wp_date('Y-m-d',$timestamp);if($day===wp_date('Y-m-d',$today))$prefix='Today';elseif($day===wp_date('Y-m-d',$tomorrow))$prefix='Tomorrow';else $prefix=wp_date('D j M',$timestamp);return $prefix.($time?', '.wp_date('g:i A',strtotime($time)):'');}
function bubbahub_myhub_handle_child_form(){if(!is_user_logged_in()||empty($_POST['bubbahub_myhub_child_nonce']))return;if(!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bubbahub_myhub_child_nonce'])),'bubbahub_myhub_child'))return;if(!current_user_can('read'))return;return;}
function bubbahub_myhub_render_booking_card($booking){ob_start();?><article class="bh-myhub-booking-card"><div class="bh-myhub-booking-icon">📅</div><div class="bh-myhub-booking-content"><div class="bh-myhub-eyebrow">Upcoming Booking</div><h2><?php echo esc_html($booking['title']?:$booking['group']);?></h2><div class="bh-myhub-booking-date"><?php echo esc_html(bubbahub_myhub_booking_date_label($booking['date'],$booking['start']));?></div><?php if($booking['venue']):?><div class="bh-myhub-booking-venue">⌖ <?php echo esc_html($booking['venue']);?></div><?php endif;?></div></article><?php return ob_get_clean();}
function bubbahub_myhub_shortcode(){return '<div class="bh-myhub-login"><h2>Loading My Hub…</h2></div>';}
require_once BUBBAHUB_MYHUB_PATH . 'myhub-groups.php';
if ( file_exists( BUBBAHUB_MYHUB_PATH . 'myhub-v2.php' ) ) require_once BUBBAHUB_MYHUB_PATH . 'myhub-v2.php';
if ( file_exists( BUBBAHUB_MYHUB_PATH . 'profile-settings.php' ) ) require_once BUBBAHUB_MYHUB_PATH . 'profile-settings.php';
if ( file_exists( BUBBAHUB_MYHUB_PATH . 'account-settings-stage2.php' ) ) require_once BUBBAHUB_MYHUB_PATH . 'account-settings-stage2.php';
if ( file_exists( BUBBAHUB_MYHUB_PATH . 'myhub-account-link.php' ) ) require_once BUBBAHUB_MYHUB_PATH . 'myhub-account-link.php';
