<?php
/** BubbaHub My Hub - child nap-time planner overlay. */
if ( ! defined( 'ABSPATH' ) ) exit;
add_action( 'wp_enqueue_scripts', 'bubbahub_myhub_nap_planner_assets', 40 );
function bubbahub_myhub_nap_planner_assets() {
    if ( ! is_user_logged_in() ) return;
    $children = get_posts( array( 'post_type'=>'bh_child','post_status'=>'publish','author'=>get_current_user_id(),'posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true ) );
    $days = array('Mon','Tue','Wed','Thu','Fri','Sat','Sun'); $nap=array_fill_keys($days,array());
    foreach($children as $child_id){
        $value=function_exists('get_field')?get_field('nap_schedule',$child_id,false):get_post_meta($child_id,'nap_schedule',true);
        if(!is_array($value))continue;
        foreach($days as $day){if(empty($value[$day])||!is_array($value[$day]))continue;$row=$value[$day];if(empty($row['enabled'])||empty($row['start'])||empty($row['end']))continue;$nap[$day][]=array('start'=>substr((string)$row['start'],0,5),'end'=>substr((string)$row['end'],0,5));}
    }
    wp_enqueue_style('bubbahub-myhub-planner');
    wp_enqueue_script('bubbahub-myhub-nap-planner',BUBBAHUB_MYHUB_URL.'myhub-nap-planner.js',array('jquery'),BUBBAHUB_MYHUB_VERSION,true);
    wp_localize_script('bubbahub-myhub-nap-planner','BubbaHubNapPlanner',array('napSchedule'=>$nap));
}
