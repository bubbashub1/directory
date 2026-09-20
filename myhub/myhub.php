<?php
/** BubbaHub My Hub */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! defined( 'BUBBAHUB_MYHUB_VERSION' ) ) define( 'BUBBAHUB_MYHUB_VERSION', '1.6.30' );
if ( ! defined( 'BUBBAHUB_MYHUB_PATH' ) ) define( 'BUBBAHUB_MYHUB_PATH', plugin_dir_path( __FILE__ ) );
if ( ! defined( 'BUBBAHUB_MYHUB_URL' ) ) define( 'BUBBAHUB_MYHUB_URL', plugin_dir_url( __FILE__ ) );
add_action( 'init', 'bubbahub_myhub_register_child_post_type' );
add_action( 'wp_enqueue_scripts', 'bubbahub_myhub_register_assets' );
add_shortcode( 'bubbahub_my_hub', 'bubbahub_myhub_shortcode' );
add_action( 'init', 'bubbahub_myhub_handle_child_form' );
function bubbahub_myhub_register_child_post_type() { register_post_type( 'bh_child', array( 'labels'=>array('name'=>'Family Children','singular_name'=>'Family Child','add_new_item'=>'Add Child','edit_item'=>'Edit Child'), 'public'=>false,'show_ui'=>true,'show_in_menu'=>true,'menu_icon'=>'dashicons-groups','supports'=>array('title','author'),'capability_type'=>'post','map_meta_cap'=>true ) ); }
function bubbahub_myhub_register_assets() { wp_register_style( 'bubbahub-myhub', BUBBAHUB_MYHUB_URL . 'myhub.css', array(), BUBBAHUB_MYHUB_VERSION ); wp_enqueue_script( 'bubbahub-myhub-carousel', BUBBAHUB_MYHUB_URL . 'myhub-carousel.js', array(), BUBBAHUB_MYHUB_VERSION, true ); }
function bubbahub_myhub_field( $post_id, $field, $default='' ) { if(function_exists('get_field')){$value=get_field($field,$post_id);if($value!==null&&$value!==false&&$value!=='')return $value;} $value=get_post_meta($post_id,$field,true);return ($value!==''&&$value!==false)?$value:$default; }
function bubbahub_myhub_child_query() { if(!is_user_logged_in())return new WP_Query(); return new WP_Query(array('post_type'=>'bh_child','post_status'=>'publish','author'=>get_current_user_id(),'posts_per_page'=>-1,'orderby'=>'date','order'=>'ASC','no_found_rows'=>true)); }
function bubbahub_myhub_age($dob){if(!$dob)return ''; $birth=DateTime::createFromFormat('Y-m-d',sanitize_text_field($dob));if(!$birth)return ''; $today=new DateTime('today');if($birth>$today)return ''; $age=$birth->diff($today);$parts=array();if($age->y)$parts[]=$age->y.'y';if($age->m||!$parts)$parts[]=$age->m.'m';return implode(' ',$parts);}
function bubbahub_myhub_countdown($date){if(!$date)return ''; $deadline=DateTime::createFromFormat('Y-m-d',sanitize_text_field($date));if(!$deadline)return ''; $today=new DateTime('today');if($deadline<=$today)return 'Deadline passed';$diff=$today->diff($deadline);$parts=array();if($diff->y)$parts[]=$diff->y.'y';if($diff->m)$parts[]=$diff->m.'m';if($diff->d||!$parts)$parts[]=$diff->d.'d';return implode(' ',$parts).' left to apply';}
function bubbahub_myhub_format_date($date){if(!$date)return ''; $timestamp=strtotime($date);return $timestamp?wp_date('j M Y',$timestamp):'';}
function bubbahub_myhub_bookings(){if(!is_user_logged_in())return array();$ids=get_posts(array('post_type'=>'bh_booking','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids','meta_query'=>array(array('key'=>'_bh_user_id','value'=>get_current_user_id(),'compare'=>'='),array('key'=>'_bh_status','value'=>array('confirmed','reserved'),'compare'=>'IN')),'no_found_rows'=>true));$items=array();foreach($ids as$booking_id){$session_id=absint(get_post_meta($booking_id,'_bh_session_id',true));if(!$session_id||get_post_type($session_id)!=='bh_session')continue;$date=get_post_meta($session_id,'_bh_date',true);$start=get_post_meta($session_id,'_bh_start_time',true);$timestamp=strtotime(trim($date.' '.$start));if(!$timestamp||$timestamp<current_time('timestamp'))continue;$group_id=absint(get_post_meta($session_id,'_bh_group_id',true));$venue_id=absint(get_post_meta($session_id,'_bh_venue_id',true));$items[]=array('id'=>$booking_id,'session_id'=>$session_id,'title'=>get_the_title($session_id),'date'=>$date,'start'=>$start,'timestamp'=>$timestamp,'group'=>$group_id?get_the_title($group_id):'','venue'=>$venue_id?get_the_title($venue_id):'','status'=>get_post_meta($booking_id,'_bh_status',true));}usort($items,function($a,$b){return$a['timestamp']<=>$b['timestamp'];});return$items;}
function bubbahub_myhub_booking_date_label($date,$time){$timestamp=strtotime(trim($date.' '.$time));if(!$timestamp)return bubbahub_myhub_format_date($date).($time?', '.$time:'');$today=current_time('timestamp');$tomorrow=strtotime('+1 day',strtotime(wp_date('Y-m-d',$today)));$day=wp_date('Y-m-d',$timestamp);if($day===wp_date('Y-m-d',$today))$prefix='Today';elseif($day===wp_date('Y-m-d',$tomorrow))$prefix='Tomorrow';else$prefix=wp_date('D j M',$timestamp);return$prefix.($time?', '.wp_date('g:i A',strtotime($time)):'');}
function bubbahub_myhub_handle_child_form(){if(!is_user_logged_in()||empty($_POST['bubbahub_myhub_child_nonce']))return;if(!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bubbahub_myhub_child_nonce'])),'bubbahub_myhub_child'))return;if(!current_user_can('read'))return;return;}
/* Child profile saves are handled centrally by profile-settings.php so the My Hub inline form and Account Settings use the same data store. */
function bubbahub_myhub_render_booking_card($booking){ob_start();?><article class="bh-myhub-booking-card"><div class="bh-myhub-booking-icon">📅</div><div class="bh-myhub-booking-content"><div class="bh-myhub-eyebrow">Upcoming Booking</div><h2><?php echo esc_html($booking['title']?:$booking['group']);?></h2><div class="bh-myhub-booking-date"><?php echo esc_html(bubbahub_myhub_booking_date_label($booking['date'],$booking['start']));?></div><?php if($booking['venue']):?><div class="bh-myhub-booking-venue">⌖ <?php echo esc_html($booking['venue']);?></div><?php endif;?></div></article><?php return ob_get_clean();}
function bubbahub_myhub_shortcode(){return '<div class="bh-myhub-login"><h2>Loading My Hub…</h2></div>';}
add_filter( 'acf/settings/load_json', function( $paths ) { $paths[] = BUBBAHUB_MYHUB_PATH . 'acf-json'; return array_values( array_unique( $paths ) ); } );
add_filter( 'acf/settings/save_json', function( $path ) { return BUBBAHUB_MYHUB_PATH . 'acf-json'; } );

/* Optional My Hub modules are isolated so one broken module cannot stop the Directory shortcode bootstrap. */
if ( ! function_exists( 'bubbahub_myhub_safe_require' ) ) {
    function bubbahub_myhub_safe_require( $file, $label = '' ) {
        if ( ! $file || ! file_exists( $file ) ) return false;
        try { require_once $file; return true; }
        catch ( Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) error_log( 'BubbaHub My Hub module skipped [' . $label . ']: ' . $e->getMessage() );
            return false;
        }
    }
}

bubbahub_myhub_safe_require( BUBBAHUB_MYHUB_PATH . 'myhub-groups.php', 'groups' );
bubbahub_myhub_safe_require( BUBBAHUB_MYHUB_PATH . 'myhub-planner.php', 'planner' );
if ( file_exists( BUBBAHUB_MYHUB_PATH . 'myhub-weekly-planner-v2.php' ) ) bubbahub_myhub_safe_require( BUBBAHUB_MYHUB_PATH . 'myhub-weekly-planner-v2.php', 'weekly planner v2' );
if ( file_exists( BUBBAHUB_MYHUB_PATH . 'myhub-calendar-sync.php' ) ) bubbahub_myhub_safe_require( BUBBAHUB_MYHUB_PATH . 'myhub-calendar-sync.php', 'calendar sync' );
if ( file_exists( BUBBAHUB_MYHUB_PATH . 'myhub-weekly-planner-v2-assets.php' ) ) bubbahub_myhub_safe_require( BUBBAHUB_MYHUB_PATH . 'myhub-weekly-planner-v2-assets.php', 'weekly planner assets' );
if ( file_exists( BUBBAHUB_MYHUB_PATH . 'myhub-v2.php' ) ) bubbahub_myhub_safe_require( BUBBAHUB_MYHUB_PATH . 'myhub-v2.php', 'my hub v2' );
if ( file_exists( BUBBAHUB_MYHUB_PATH . 'profile-settings.php' ) ) bubbahub_myhub_safe_require( BUBBAHUB_MYHUB_PATH . 'profile-settings.php', 'profile settings' );
if ( file_exists( BUBBAHUB_MYHUB_PATH . 'account-settings-stage2.php' ) ) bubbahub_myhub_safe_require( BUBBAHUB_MYHUB_PATH . 'account-settings-stage2.php', 'account settings stage 2' );
if ( file_exists( BUBBAHUB_MYHUB_PATH . 'account-settings-stage3.php' ) ) bubbahub_myhub_safe_require( BUBBAHUB_MYHUB_PATH . 'account-settings-stage3.php', 'account settings stage 3' );
if ( file_exists( BUBBAHUB_MYHUB_PATH . 'account-settings-stage4.php' ) ) bubbahub_myhub_safe_require( BUBBAHUB_MYHUB_PATH . 'account-settings-stage4.php', 'account settings stage 4' );
if ( file_exists( BUBBAHUB_MYHUB_PATH . 'account-settings-stage5.php' ) ) bubbahub_myhub_safe_require( BUBBAHUB_MYHUB_PATH . 'account-settings-stage5.php', 'account settings stage 5' );
if ( file_exists( BUBBAHUB_MYHUB_PATH . 'account-settings-stage6.php' ) ) bubbahub_myhub_safe_require( BUBBAHUB_MYHUB_PATH . 'account-settings-stage6.php', 'account settings stage 6' );
if ( file_exists( BUBBAHUB_MYHUB_PATH . 'account-settings-stage7.php' ) ) bubbahub_myhub_safe_require( BUBBAHUB_MYHUB_PATH . 'account-settings-stage7.php', 'account settings stage 7' );
if ( file_exists( BUBBAHUB_MYHUB_PATH . 'account-settings-stage8.php' ) ) bubbahub_myhub_safe_require( BUBBAHUB_MYHUB_PATH . 'account-settings-stage8.php', 'account settings stage 8' );
if ( file_exists( BUBBAHUB_MYHUB_PATH . 'account-settings-stage9.php' ) ) bubbahub_myhub_safe_require( BUBBAHUB_MYHUB_PATH . 'account-settings-stage9.php', 'account settings stage 9' );
if ( file_exists( BUBBAHUB_MYHUB_PATH . 'account-settings-stage10.php' ) ) bubbahub_myhub_safe_require( BUBBAHUB_MYHUB_PATH . 'account-settings-stage10.php', 'account settings stage 10' );
if ( file_exists( BUBBAHUB_MYHUB_PATH . 'account-settings-stage11.php' ) ) bubbahub_myhub_safe_require( BUBBAHUB_MYHUB_PATH . 'account-settings-stage11.php', 'account settings stage 11' );
if ( file_exists( BUBBAHUB_MYHUB_PATH . 'myhub-account-link.php' ) ) bubbahub_myhub_safe_require( BUBBAHUB_MYHUB_PATH . 'myhub-account-link.php', 'account link' );
if ( file_exists( BUBBAHUB_MYHUB_PATH . 'myhub-ui-overrides.php' ) ) bubbahub_myhub_safe_require( BUBBAHUB_MYHUB_PATH . 'myhub-ui-overrides.php', 'UI overrides' );
if ( file_exists( BUBBAHUB_MYHUB_PATH . 'myhub-nap-planner.php' ) ) bubbahub_myhub_safe_require( BUBBAHUB_MYHUB_PATH . 'myhub-nap-planner.php', 'nap planner' );
/* Legacy myhub-bookings.php deliberately not loaded; booking lifecycle supersedes it. */
