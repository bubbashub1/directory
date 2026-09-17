<?php
/**
 * BubbaHub Leader Dashboard loader – stable shortcode registration and theme integration.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
require_once __DIR__ . '/leader-listings.php';
require_once __DIR__ . '/leader-venues.php';
require_once __DIR__ . '/leader-bookings.php';
require_once __DIR__ . '/leader-management-pages.php';
$bh_schedule_foundation = dirname( __DIR__ ) . '/modules/core/bubbahub-schedule-foundation.php';
if ( file_exists( $bh_schedule_foundation ) ) require_once $bh_schedule_foundation;
$bh_schedule_engine = dirname( __DIR__ ) . '/modules/core/bubbahub-schedule-engine.php';
if ( file_exists( $bh_schedule_engine ) ) require_once $bh_schedule_engine;
$bh_schedule_manager = __DIR__ . '/leader-schedule-manager.php';
if ( file_exists( $bh_schedule_manager ) ) require_once $bh_schedule_manager;
$bh_schedule_booking_tools = __DIR__ . '/leader-schedule-booking-tools.php';
if ( file_exists( $bh_schedule_booking_tools ) ) require_once $bh_schedule_booking_tools;
$bh_schedule_calendar = __DIR__ . '/leader-schedule-calendar.php';
if ( file_exists( $bh_schedule_calendar ) ) require_once $bh_schedule_calendar;
$bh_generic_booking_file = dirname( __DIR__ ) . '/modules/bookings/bubbahub-generic-booking-pages.php';
if ( file_exists( $bh_generic_booking_file ) ) require_once $bh_generic_booking_file;
$bh_monitor_file = dirname( __DIR__ ) . '/modules/internet-monitor/bubbahub-internet-monitor.php';
if ( file_exists( $bh_monitor_file ) ) { require_once $bh_monitor_file; if ( class_exists( 'BubbaHub_Internet_Group_Monitor' ) ) { register_activation_hook( dirname( __DIR__ ) . '/bubbahub-directory.php', [ 'BubbaHub_Internet_Group_Monitor', 'activate' ] ); register_deactivation_hook( dirname( __DIR__ ) . '/bubbahub-directory.php', [ 'BubbaHub_Internet_Group_Monitor', 'deactivate' ] ); } }
$bh_monitor_alerts_file = dirname( __DIR__ ) . '/modules/internet-monitor/bubbahub-monitor-alerts.php';
if ( file_exists( $bh_monitor_alerts_file ) ) require_once $bh_monitor_alerts_file;
add_action( 'init', 'bubbahub_leader_dashboard_register', 99 );
function bubbahub_leader_dashboard_register() { $renderer=''; if(function_exists('bubbahub_leader_dashboard_shortcode'))$renderer='bubbahub_leader_dashboard_shortcode';elseif(function_exists('bubbahub_leader_dashboard_render'))$renderer='bubbahub_leader_dashboard_render';if(!$renderer)return;remove_shortcode('bubbahub_leader_dashboard');remove_shortcode('bubbahub-leader-dashboard');add_shortcode('bubbahub_leader_dashboard',$renderer);add_shortcode('bubbahub-leader-dashboard',$renderer); }
add_filter( 'the_content', 'bubbahub_leader_dashboard_schedule_nav', 20 );
function bubbahub_leader_dashboard_schedule_nav( $content ) { if(is_admin()||!is_page('leader')||!is_user_logged_in()||strpos($content,'bh-leader-nav')===false||strpos($content,'bh-schedule-dashboard-nav')!==false)return $content;if(!function_exists('bubbahub_leader_dashboard_is_allowed')||!bubbahub_leader_dashboard_is_allowed()||!function_exists('bubbahub_leader_management_url'))return $content;$url=esc_url(bubbahub_leader_management_url('schedule'));$link='<a class="bh-schedule-dashboard-nav" href="'.$url.'"><span>◷</span> My sessions</a>';$needle='<a href="'.esc_url(bubbahub_leader_management_url('bookings')).'"><span>▣</span> Bookings';if(strpos($content,$needle)!==false)return str_replace($needle,$link.$needle,$content);return $content; }
add_action( 'wp_enqueue_scripts', 'bubbahub_leader_theme_overrides', 20 );
function bubbahub_leader_theme_overrides() { if(!defined('BUBBAHUB_LEADER_DASHBOARD_URL')||!defined('BUBBAHUB_LEADER_DASHBOARD_VERSION'))return;$is_leader_page=is_page('leader');$management_ids=function_exists('bubbahub_leader_management_page_ids')?bubbahub_leader_management_page_ids():array();$is_management_page=$management_ids&&is_page(array_values($management_ids));if(!$is_leader_page&&!$is_management_page)return;$css=BUBBAHUB_LEADER_DASHBOARD_DIR.'leader-theme-overrides.css';if(file_exists($css))wp_enqueue_style('bubbahub-leader-theme-overrides',BUBBAHUB_LEADER_DASHBOARD_URL.'leader-theme-overrides.css',array('bubbahub-leader-dashboard'),BUBBAHUB_LEADER_DASHBOARD_VERSION.'.1');$schedule_css=__DIR__.'/leader-schedule.css';if($is_management_page&&file_exists($schedule_css))wp_enqueue_style('bubbahub-leader-schedule',plugins_url('leader-schedule.css',__FILE__),array('bubbahub-leader-theme-overrides'),BUBBAHUB_LEADER_DASHBOARD_VERSION.'.2'); }
add_action( 'admin_head', 'bubbahub_internet_monitor_admin_css' );
function bubbahub_internet_monitor_admin_css() { if(!isset($_GET['page'])||'bh-igm'!==sanitize_key(wp_unslash($_GET['page'])))return; ?><style>@media(max-width:782px){.bh-igm-wrap{margin-right:10px}.bh-igm-grid{grid-template-columns:1fr!important}.bh-igm-table{display:block;overflow-x:auto}.bh-igm-actions a,.bh-igm-actions button{margin-bottom:6px}}@media(max-width:480px){.bh-igm-wrap{margin-left:-10px}.bh-igm-card{padding:14px!important}.bh-igm-table{font-size:13px}}</style><?php }
