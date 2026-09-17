<?php
/**
 * BubbaHub Leader Schedule Calendar
 * Adds a lightweight week calendar to the leader My Sessions page.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function bubbahub_leader_schedule_calendar() {
    if ( ! function_exists('bubbahub_leader_dashboard_is_allowed') || ! bubbahub_leader_dashboard_is_allowed() ) return '';
    $base = isset($_GET['bh_week']) ? sanitize_text_field(wp_unslash($_GET['bh_week'])) : current_time('Y-m-d');
    $ts = strtotime($base);
    if ( ! $ts ) $ts = current_time('timestamp');
    $monday = strtotime('monday this week', $ts);
    $prev = wp_date('Y-m-d', strtotime('-7 days', $monday));
    $next = wp_date('Y-m-d', strtotime('+7 days', $monday));
    $ids = get_posts(array('post_type'=>'bh_session','post_status'=>array('publish','draft','private'),'post_author'=>get_current_user_id(),'posts_per_page'=>-1,'meta_query'=>array(array('key'=>'_bh_date','value'=>array(wp_date('Y-m-d',$monday),wp_date('Y-m-d',strtotime('+6 days',$monday))),'compare'=>'BETWEEN','type'=>'DATE')),'meta_key'=>'_bh_date','orderby'=>'meta_value','order'=>'ASC','no_found_rows'=>true));
    $by_day=array(); foreach($ids as $s){$d=get_post_meta($s->ID,'_bh_date',true);$by_day[$d][]=$s;}
    ob_start(); ?>
    <section class="bh-leader-calendar" aria-label="Weekly session calendar">
      <div class="bh-calendar-head"><div><p class="bh-leader-eyebrow">Calendar</p><h2>Weekly timetable</h2><p>See your classes by day and jump straight into session management.</p></div><div class="bh-calendar-nav"><a href="<?php echo esc_url(add_query_arg('bh_week',$prev)); ?>">← Previous</a><strong><?php echo esc_html(wp_date('j M', $monday).' – '.wp_date('j M Y',strtotime('+6 days',$monday))); ?></strong><a href="<?php echo esc_url(add_query_arg('bh_week',$next)); ?>">Next →</a></div></div>
      <div class="bh-calendar-grid">
      <?php for($i=0;$i<7;$i++): $day_ts=strtotime('+'.$i.' days',$monday);$date=wp_date('Y-m-d',$day_ts);$day_sessions=$by_day[$date]??array(); ?>
        <div class="bh-calendar-day <?php echo $date===current_time('Y-m-d')?'is-today':''; ?>"><div class="bh-calendar-day-head"><strong><?php echo esc_html(wp_date('D',$day_ts)); ?></strong><span><?php echo esc_html(wp_date('j M',$day_ts)); ?></span></div>
        <?php if($day_sessions): foreach($day_sessions as $s): $gid=(int)get_post_meta($s->ID,'_bh_group_id',true);$vid=(int)get_post_meta($s->ID,'_bh_venue_id',true);$start=get_post_meta($s->ID,'_bh_start_time',true);$end=get_post_meta($s->ID,'_bh_end_time',true);$rec=get_post_meta($s->ID,'_bh_recurrence',true);$cancelled=get_post_meta($s->ID,'_bh_schedule_cancelled',true);$stats=function_exists('bubbahub_booking_session_stats')?bubbahub_booking_session_stats($s->ID):array('used'=>0,'remaining'=>null); ?>
          <article class="bh-calendar-event <?php echo $cancelled?'is-cancelled':''; ?>"><time><?php echo esc_html($start.($end?'–'.$end:'')); ?></time><a href="<?php echo esc_url(add_query_arg('session',$s->ID,bubbahub_leader_management_url('schedule'))); ?>"><?php echo esc_html(get_the_title($gid)?:$s->post_title); ?></a><?php if($vid): ?><span>⌖ <?php echo esc_html(get_the_title($vid)); ?></span><?php endif; ?><small><?php echo esc_html($cancelled?'Cancelled':($rec&&'none'!==$rec?ucfirst($rec):'One-off')); ?><?php if(function_exists('bubbahub_booking_session_stats')): ?> · <?php echo esc_html($stats['used']); ?> booked<?php if(null!==$stats['remaining']): ?> · <?php echo esc_html($stats['remaining']); ?> left<?php endif; ?><?php endif; ?></small></article>
        <?php endforeach; else: ?><div class="bh-calendar-empty">No sessions</div><?php endif; ?></div>
      <?php endfor; ?></div>
    </section>
    <?php return ob_get_clean();
}

add_filter('the_content','bubbahub_leader_schedule_append_calendar',30);
function bubbahub_leader_schedule_append_calendar($content){
    $id=(int)get_option('bubbahub_leader_schedule_page_id',0);
    if(is_admin()||!$id||!is_page($id)||!is_user_logged_in()||strpos($content,'bh-leader-schedule-manager')===false)return $content;
    return $content.bubbahub_leader_schedule_calendar();
}

add_action('wp_head','bubbahub_leader_schedule_calendar_css',100);
function bubbahub_leader_schedule_calendar_css(){
    $id=(int)get_option('bubbahub_leader_schedule_page_id',0); if(!$id||!is_page($id))return; ?>
    <style id="bh-leader-schedule-calendar-css">
    .bh-leader-calendar{max-width:1100px;margin:28px auto 0}.bh-calendar-head{display:flex;justify-content:space-between;align-items:end;gap:20px;margin-bottom:16px}.bh-calendar-head h2{margin:0 0 5px}.bh-calendar-head p:last-child{margin:0}.bh-calendar-nav{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.bh-calendar-nav a{padding:8px 12px;border:1px solid #ddd;border-radius:10px;text-decoration:none;background:#fff}.bh-calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(120px,1fr));gap:8px;overflow-x:auto}.bh-calendar-day{min-height:190px;border:1px solid #e5e5eb;border-radius:14px;background:#fafafd;padding:10px}.bh-calendar-day.is-today{box-shadow:inset 0 3px 0 currentColor}.bh-calendar-day-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:9px;font-size:13px}.bh-calendar-day-head span{opacity:.65}.bh-calendar-event{display:flex;flex-direction:column;gap:3px;padding:10px;margin-bottom:7px;border-radius:11px;background:#fff;border:1px solid #e2e2e8}.bh-calendar-event time{font-weight:800;font-size:12px}.bh-calendar-event a{font-weight:800;text-decoration:none;line-height:1.25}.bh-calendar-event span,.bh-calendar-event small{font-size:11px;opacity:.72}.bh-calendar-event.is-cancelled{opacity:.55}.bh-calendar-empty{font-size:12px;opacity:.5;text-align:center;padding:24px 4px}@media(max-width:900px){.bh-calendar-grid{grid-template-columns:repeat(7,145px)}.bh-calendar-head{display:block}.bh-calendar-nav{margin-top:12px}}@media(max-width:600px){.bh-calendar-grid{grid-template-columns:repeat(7,135px)}.bh-calendar-day{min-height:170px}}
    </style><?php }
