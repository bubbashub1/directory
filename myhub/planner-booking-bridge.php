<?php
/**
 * BubbaHub My Hub planner booking bridge.
 * Replaces the planner AJAX response with real upcoming bh_session records,
 * child-age matching, taxonomy matching and booking/availability links.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

remove_action( 'wp_ajax_bubbahub_myhub_planner', 'bubbahub_myhub_planner_ajax' );
add_action( 'wp_ajax_bubbahub_myhub_planner', 'bubbahub_myhub_planner_booking_ajax' );

function bubbahub_myhub_planner_booking_children() {
    if ( ! is_user_logged_in() ) return array();
    $ids = isset($_POST['children']) ? json_decode(wp_unslash($_POST['children']), true) : array();
    $ids = array_map('absint', is_array($ids) ? $ids : array());
    $owned = get_posts(array('post_type'=>'bh_child','post_status'=>'publish','author'=>get_current_user_id(),'posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true));
    return array_values(array_intersect($ids, array_map('absint',$owned)));
}

function bubbahub_myhub_planner_booking_user_terms($taxonomy, $fallbacks = array()) {
    $values = get_user_meta(get_current_user_id(), $taxonomy, true);
    if ( function_exists('get_field') ) {
        $acf = get_field($taxonomy, 'user_' . get_current_user_id(), false);
        if ( null !== $acf && false !== $acf && '' !== $acf ) $values = $acf;
    }
    if ( ! is_array($values) ) $values = ($values === '' || null === $values) ? array() : preg_split('/\s*,\s*/', (string)$values);
    $ids = array();
    foreach ( $values as $value ) {
        if ( is_object($value) && isset($value->term_id) ) $ids[] = absint($value->term_id);
        elseif ( is_array($value) && isset($value['term_id']) ) $ids[] = absint($value['term_id']);
        elseif ( is_numeric($value) ) $ids[] = absint($value);
        else {
            $term = get_term_by('slug', sanitize_title($value), $taxonomy);
            if ($term) $ids[] = (int)$term->term_id;
        }
    }
    return array_values(array_unique(array_filter($ids)));
}

function bubbahub_myhub_planner_booking_match_age($group_id, $children) {
    if ( ! $children || ! function_exists('bubbahub_myhub_planner_age_matches') ) return true;
    return bubbahub_myhub_planner_age_matches($group_id, $children) > 0;
}

function bubbahub_myhub_planner_booking_match_tax($group_id, $taxonomy, $wanted) {
    if ( ! $wanted || ! taxonomy_exists($taxonomy) ) return true;
    $terms = wp_get_post_terms($group_id, $taxonomy, array('fields'=>'ids'));
    return ! is_wp_error($terms) && (bool) array_intersect(array_map('absint',$terms), array_map('absint',$wanted));
}

function bubbahub_myhub_planner_booking_available($session_id) {
    if ( function_exists('bubbahub_booking_session_stats') ) {
        $stats = bubbahub_booking_session_stats($session_id);
        return array('available'=>empty($stats['full']),'remaining'=>isset($stats['remaining'])?$stats['remaining']:null);
    }
    $capacity=(int)get_post_meta($session_id,'_bh_capacity',true);
    if($capacity<=0)return array('available'=>true,'remaining'=>null);
    return array('available'=>true,'remaining'=>null);
}

function bubbahub_myhub_planner_booking_render($children=array(), $interest=array(), $location=array()) {
    $now = current_time('timestamp');
    $from = wp_date('Y-m-d',$now); $to = wp_date('Y-m-d',strtotime('+7 days',$now));
    $q = new WP_Query(array('post_type'=>'bh_session','post_status'=>'publish','posts_per_page'=>250,
        'meta_query'=>array(array('key'=>'_bh_date','value'=>array($from,$to),'compare'=>'BETWEEN','type'=>'DATE')),
        'orderby'=>'meta_value','meta_key'=>'_bh_date','order'=>'ASC','no_found_rows'=>true));
    $days=array('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'); $by=array_fill_keys($days,array());
    while($q->have_posts()) { $q->the_post();
        $sid=get_the_ID(); $gid=absint(get_post_meta($sid,'_bh_group_id',true));
        if(!$gid || 'group'!==get_post_type($gid)) continue;
        if($children && !bubbahub_myhub_planner_booking_match_age($gid,$children)) continue;
        if($interest && !bubbahub_myhub_planner_booking_match_tax($gid,'user-interests',$interest)) continue;
        $location_match=true;
        if($location){$location_match=false;foreach(array('preferred-location','preferred_location','location','region') as $tax)if(bubbahub_myhub_planner_booking_match_tax($gid,$tax,$location)){$location_match=true;break;}}
        if(!$location_match)continue;
        $date=get_post_meta($sid,'_bh_date',true);$start=get_post_meta($sid,'_bh_start_time',true);$end=get_post_meta($sid,'_bh_end_time',true);$ts=strtotime($date.' '.$start);if(!$ts||$ts<$now)continue;
        $availability=bubbahub_myhub_planner_booking_available($sid);if(!$availability['available'])continue;
        $vid=absint(get_post_meta($sid,'_bh_venue_id',true));$group_title=get_the_title($gid);
        $book_url=add_query_arg(array('session_id'=>$sid,'group_id'=>$gid,'date'=>$date),home_url('/book/'));
        $by[wp_date('l',$ts)][]=array('session_id'=>$sid,'group_id'=>$gid,'title'=>$group_title,'url'=>get_permalink($gid),'book_url'=>$book_url,'image'=>get_the_post_thumbnail_url($gid,'thumbnail'),'date'=>$date,'start'=>$start,'end'=>$end,'venue'=>$vid?get_the_title($vid):'','remaining'=>$availability['remaining']);
    }
    wp_reset_postdata();foreach($by as &$items)usort($items,function($a,$b){return strcmp($a['date'].' '.$a['start'],$b['date'].' '.$b['start']);});unset($items);
    ob_start();
    foreach($days as $day): ?><div class="bh-planner-day"><div class="bh-planner-day-title"><?php echo esc_html($day); ?></div><div class="bh-planner-day-items">
    <?php if($by[$day]):foreach($by[$day] as $item): ?><article class="bh-planner-item bh-planner-session-item"><a class="bh-planner-session-main" href="<?php echo esc_url($item['url']); ?>"><span class="bh-planner-thumb"><?php if($item['image']):?><img src="<?php echo esc_url($item['image']); ?>" alt="" loading="lazy"><?php else:?><span class="bh-planner-placeholder" aria-hidden="true">♡</span><?php endif;?></span><span class="bh-planner-item-main"><strong><?php echo esc_html($item['title']); ?></strong><span class="bh-planner-hours"><?php echo esc_html(wp_date('g:i A',strtotime($item['date'].' '.$item['start']))); ?><?php if($item['end']):?> – <?php echo esc_html(wp_date('g:i A',strtotime($item['date'].' '.$item['end']))); ?><?php endif;?><?php if($item['venue']):?> · <?php echo esc_html($item['venue']); ?><?php endif;?></span></span></a><a class="bh-planner-book-button" href="<?php echo esc_url($item['book_url']); ?>">Book<?php if(null!==$item['remaining']):?> · <?php echo (int)$item['remaining']; ?> left<?php endif;?></a></article><?php endforeach;else:?><div class="bh-planner-empty">No available matching sessions.</div><?php endif;?></div></div><?php endforeach;
    return ob_get_clean();
}

function bubbahub_myhub_planner_booking_ajax() {
    if ( ! is_user_logged_in() ) wp_send_json_error(array('message'=>'Please log in.'),401);
    check_ajax_referer('bubbahub_myhub_planner','nonce');
    $children=bubbahub_myhub_planner_booking_children();$interest=bubbahub_myhub_planner_booking_user_terms('user-interests');$location=bubbahub_myhub_planner_booking_user_terms('preferred-location');
    $manual_interest=isset($_POST['interest'])?sanitize_text_field(wp_unslash($_POST['interest'])):'';$manual_location=isset($_POST['location'])?sanitize_text_field(wp_unslash($_POST['location'])):'';
    if($manual_interest&&taxonomy_exists('user-interests')){$term=get_term_by('slug',sanitize_title($manual_interest),'user-interests');$interest=$term?array((int)$term->term_id):array();}
    if($manual_location){$ids=array();foreach(array('preferred-location','preferred_location','location','region') as $tax){$term=taxonomy_exists($tax)?get_term_by('slug',sanitize_title($manual_location),$tax):false;if($term)$ids[]=(int)$term->term_id;}$location=$ids;}
    wp_send_json_success(array('html'=>bubbahub_myhub_planner_booking_render($children,$interest,$location)));
}
