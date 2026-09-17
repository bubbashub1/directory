<?php
/**
 * BubbaHub My Hub – session-based weekly planner.
 * Uses child profiles plus the user-interests and preferred-location taxonomies.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'bubbahub_weekly_planner_v2', 'bubbahub_myhub_weekly_planner_v2_shortcode' );

function bubbahub_myhub_planner_v2_tax_terms( $taxonomy, $ids = array() ) {
    if ( ! taxonomy_exists( $taxonomy ) ) return array();
    $args = array( 'taxonomy'=>$taxonomy, 'hide_empty'=>false, 'fields'=>'ids' );
    if ( $ids ) $args['include'] = array_map('absint',$ids);
    $terms = get_terms($args);
    return is_wp_error($terms) ? array() : array_map('absint',$terms);
}

function bubbahub_myhub_planner_v2_user_terms( $taxonomy ) {
    $uid=get_current_user_id(); $values=get_user_meta($uid,$taxonomy,true);
    if(function_exists('get_field')) { $acf=get_field($taxonomy,'user_'.$uid,false); if(null!==$acf&&false!==$acf&&''!==$acf)$values=$acf; }
    if(!is_array($values)) $values=($values===''||null=== $values)?array():preg_split('/\s*,\s*/',(string)$values);
    $ids=array(); foreach($values as $v){ if(is_object($v)&&isset($v->term_id))$ids[]=absint($v->term_id); elseif(is_array($v)&&isset($v['term_id']))$ids[]=absint($v['term_id']); elseif(is_numeric($v))$ids[]=absint($v); else { $term=get_term_by('slug',sanitize_title($v),$taxonomy); if($term)$ids[]=(int)$term->term_id; } }
    return array_values(array_unique(array_filter($ids)));
}

function bubbahub_myhub_planner_v2_child_ids() {
    if(!is_user_logged_in())return array();
    return array_map('absint',get_posts(array('post_type'=>'bh_child','post_status'=>'publish','author'=>get_current_user_id(),'posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true)));
}

function bubbahub_myhub_planner_v2_session_rows( $days_ahead = 7 ) {
    $now=current_time('timestamp'); $from=wp_date('Y-m-d',$now); $to=wp_date('Y-m-d',strtotime('+'.max(1,(int)$days_ahead).' days',$now));
    $q=new WP_Query(array('post_type'=>'bh_session','post_status'=>'publish','posts_per_page'=>250,'meta_query'=>array(array('key'=>'_bh_date','value'=>array($from,$to),'compare'=>'BETWEEN','type'=>'DATE')),'orderby'=>'meta_value','meta_key'=>'_bh_date','order'=>'ASC','no_found_rows'=>true));
    $rows=array();
    while($q->have_posts()){$q->the_post();$sid=get_the_ID();$gid=absint(get_post_meta($sid,'_bh_group_id',true));if(!$gid||'group'!==get_post_type($gid))continue;$date=get_post_meta($sid,'_bh_date',true);$start=get_post_meta($sid,'_bh_start_time',true);$end=get_post_meta($sid,'_bh_end_time',true);$vid=absint(get_post_meta($sid,'_bh_venue_id',true));$rows[]=array('session_id'=>$sid,'group_id'=>$gid,'venue_id'=>$vid,'date'=>$date,'start'=>$start,'end'=>$end,'title'=>get_the_title($gid),'url'=>get_permalink($gid),'image'=>get_the_post_thumbnail_url($gid,'thumbnail'),'venue'=>$vid?get_the_title($vid):'');}
    wp_reset_postdata(); return $rows;
}

function bubbahub_myhub_planner_v2_match( $group_id, $interest_ids, $location_ids ) {
    $score=0;
    if($interest_ids&&taxonomy_exists('user-interests')) { $terms=wp_get_post_terms($group_id,'user-interests',array('fields'=>'ids')); if(!is_wp_error($terms)&&array_intersect(array_map('absint',$terms),$interest_ids))$score+=4; else return -1; }
    if($location_ids) { $matched=false; foreach(array('preferred-location','preferred_location','location','region') as $tax){if(!taxonomy_exists($tax))continue;$terms=wp_get_post_terms($group_id,$tax,array('fields'=>'ids'));if(!is_wp_error($terms)&&array_intersect(array_map('absint',$terms),$location_ids)){$matched=true;$score+=3;break;}} if(!$matched)return -1; }
    return $score;
}

function bubbahub_myhub_weekly_planner_v2_shortcode() {
    if(!is_user_logged_in())return '<div class="bh-planner-empty">Please log in to use your personalised weekly planner.</div>';
    $interest_ids=bubbahub_myhub_planner_v2_user_terms('user-interests'); $location_ids=bubbahub_myhub_planner_v2_user_terms('preferred-location');
    $children=bubbahub_myhub_planner_v2_child_ids(); $rows=bubbahub_myhub_planner_v2_session_rows(7); $days=array('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'); $by=array_fill_keys($days,array());
    foreach($rows as $row){$score=bubbahub_myhub_planner_v2_match($row['group_id'],$interest_ids,$location_ids);if($score<0)continue;$ts=strtotime($row['date'].' '.$row['start']);if(!$ts)continue;$day=wp_date('l',$ts);$row['score']=$score;$row['time_label']=wp_date('g:i A',$ts).($row['end']?' – '.wp_date('g:i A',strtotime($row['date'].' '.$row['end'])):'');$by[$day][]=$row;}
    foreach($by as &$entries)usort($entries,function($a,$b){$x=$a['date'].' '.$a['start'];$y=$b['date'].' '.$b['start'];return $x<=>$y;});unset($entries);
    ob_start(); ?>
    <section class="bh-myhub-section bh-weekly-planner bh-weekly-planner-v2">
      <div class="bh-myhub-section-heading"><div><div class="bh-myhub-kicker">YOUR WEEK</div><h2>Weekly Planner</h2><p>Your saved children, interests and preferred locations shape the activities shown here.</p></div></div>
      <div class="bh-planner-summary"><span><?php echo esc_html(count($children)); ?> child profile<?php echo count($children)===1?'':'s'; ?></span><span><?php echo esc_html(count($interest_ids)); ?> saved interests</span><span><?php echo esc_html(count($location_ids)); ?> preferred locations</span></div>
      <div class="bh-planner-results">
      <?php foreach($days as $day): ?><div class="bh-planner-day"><div class="bh-planner-day-title"><?php echo esc_html($day); ?></div><div class="bh-planner-day-items">
        <?php if(!empty($by[$day])): foreach($by[$day] as $item): ?><a class="bh-planner-item" href="<?php echo esc_url($item['url']); ?>"><span class="bh-planner-thumb"><?php if($item['image']):?><img src="<?php echo esc_url($item['image']); ?>" alt="" loading="lazy"><?php else:?><span class="bh-planner-placeholder" aria-hidden="true">♡</span><?php endif;?></span><span class="bh-planner-item-main"><strong><?php echo esc_html($item['title']); ?></strong><span class="bh-planner-hours"><?php echo esc_html($item['time_label']); ?><?php if($item['venue']): ?> · <?php echo esc_html($item['venue']); ?><?php endif; ?></span></span><span class="bh-planner-arrow" aria-hidden="true">→</span></a><?php endforeach; else:?><div class="bh-planner-empty">No matching sessions today.</div><?php endif; ?>
      </div></div><?php endforeach; ?>
      </div>
    </section>
    <?php return ob_get_clean();
}
