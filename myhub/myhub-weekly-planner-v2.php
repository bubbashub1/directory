<?php
/**
 * BubbaHub My Hub – session-based weekly planner.
 * Reads the ACF Business & Class Hours > Weekly Schedule field.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'bubbahub_weekly_planner_v2', 'bubbahub_myhub_weekly_planner_v2_shortcode' );

function bubbahub_myhub_planner_v2_weekly_schedule_rows( $post_id ) {
    $value = function_exists( 'get_field' ) ? get_field( 'weekly_schedule', $post_id ) : get_post_meta( $post_id, 'weekly_schedule', true );

    // ACF repeater values can arrive as arrays, serialized strings, or JSON.
    if ( is_string( $value ) ) {
        $unserialized = maybe_unserialize( $value );
        if ( is_array( $unserialized ) ) {
            $value = $unserialized;
        } else {
            $decoded = json_decode( $value, true );
            if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
                $value = $decoded;
            }
        }
    }

    if ( ! is_array( $value ) ) return array();

    $day_names = array(
        'monday'=>'Monday','mon'=>'Monday',
        'tuesday'=>'Tuesday','tue'=>'Tuesday',
        'wednesday'=>'Wednesday','wed'=>'Wednesday',
        'thursday'=>'Thursday','thu'=>'Thursday',
        'friday'=>'Friday','fri'=>'Friday',
        'saturday'=>'Saturday','sat'=>'Saturday',
        'sunday'=>'Sunday','sun'=>'Sunday',
    );

    $rows = array();

    foreach ( $value as $row ) {
        if ( ! is_array( $row ) ) continue;

        $raw_day = $row['day_name'] ?? $row['day'] ?? $row['weekday'] ?? '';
        $day_key = strtolower( trim( (string) $raw_day ) );

        // Some ACF select values may contain labels such as "Thursday".
        if ( ! isset( $day_names[$day_key] ) ) {
            $day_key = strtolower( preg_replace( '/[^a-z]/', '', $day_key ) );
        }
        if ( ! isset( $day_names[$day_key] ) ) continue;

        $closed = $row['is_closed'] ?? false;
        if ( $closed === true || $closed === 1 || $closed === '1' || strtolower((string)$closed) === 'yes' ) continue;

        $sessions = $row['sessions'] ?? array();

        if ( is_string( $sessions ) ) {
            $decoded = json_decode( $sessions, true );
            if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
                $sessions = $decoded;
            } else {
                $sessions = maybe_unserialize( $sessions );
            }
        }

        if ( ! is_array( $sessions ) ) {
            $sessions = array( $sessions );
        }

        foreach ( $sessions as $session ) {
            if ( ! is_array( $session ) ) continue;

            $start = trim( (string) ( $session['start_time'] ?? $session['start'] ?? $session['from'] ?? '' ) );
            $end   = trim( (string) ( $session['end_time'] ?? $session['end'] ?? $session['to'] ?? '' ) );
            $label = trim( (string) ( $session['session_label'] ?? $session['label'] ?? $session['name'] ?? '' ) );

            // Accept both HH:MM and HH:MM:SS.
            if ( preg_match( '/^(\\d{1,2}:\\d{2})(?::\\d{2})?$/', $start, $m ) ) {
                $start = $m[1];
            } else {
                continue;
            }

            if ( $end && preg_match( '/^(\\d{1,2}:\\d{2})(?::\\d{2})?$/', $end, $m ) ) {
                $end = $m[1];
            } elseif ( $end ) {
                $end = '';
            }

            $rows[$day_names[$day_key]][] = array(
                'start' => $start,
                'end'   => $end,
                'label' => $label,
            );
        }
    }

    return $rows;
}
function bubbahub_myhub_planner_v2_region_ids( $group_id ) {
    $ids = array();
    if ( taxonomy_exists( 'region' ) ) {
        $terms = wp_get_post_terms( (int)$group_id, 'region', array('fields'=>'ids') );
        if ( ! is_wp_error($terms) ) $ids = array_merge($ids, array_map('absint',(array)$terms));
    }
    $venue_id = function_exists('bubbahub_group_venue_id') ? absint(bubbahub_group_venue_id($group_id)) : 0;
    if ( $venue_id && taxonomy_exists('region') ) {
        $terms = wp_get_post_terms($venue_id,'region',array('fields'=>'ids'));
        if ( ! is_wp_error($terms) ) $ids = array_merge($ids,array_map('absint',(array)$terms));
    }
    return array_values(array_unique(array_filter($ids)));
}

function bubbahub_myhub_planner_v2_schedule_occurrences( $days_ahead = 7 ) {
    $now = current_time('timestamp');
    $today_date = wp_date('Y-m-d',$now);
    $day_map = array('Sunday'=>0,'Monday'=>1,'Tuesday'=>2,'Wednesday'=>3,'Thursday'=>4,'Friday'=>5,'Saturday'=>6);
    $rows = array();
    $rows_by_group_day = array();

    $q = new WP_Query(array(
        'post_type'=>'group','post_status'=>'publish','posts_per_page'=>250,
        'orderby'=>'title','order'=>'ASC','no_found_rows'=>true,
    ));

    while($q->have_posts()){
        $q->the_post();
        $gid = get_the_ID();
        $schedule = bubbahub_myhub_planner_v2_weekly_schedule_rows($gid);
        if(!$schedule) continue;

        $venue_id = function_exists('bubbahub_group_venue_id') ? absint(bubbahub_group_venue_id($gid)) : 0;
        $venue_address = $venue_id ? (get_post_meta($venue_id,'address',true) ?: get_post_meta($venue_id,'street_address',true)) : '';
        $coords = function_exists('bubbahub_group_resolve_map') ? bubbahub_group_resolve_map($gid) : null;
        if(!$coords && $venue_id && function_exists('bubbahub_group_resolve_map')) $coords=bubbahub_group_resolve_map($venue_id);

        foreach($schedule as $weekday=>$sessions){
            if(!isset($day_map[$weekday])) continue;

            for($offset=0;$offset<=$days_ahead;$offset++){
                $candidate_ts=strtotime('+'.$offset.' days',$now);
                if((int)wp_date('w',$candidate_ts)!==$day_map[$weekday]) continue;
                $date=wp_date('Y-m-d',$candidate_ts);

                // Combine multiple sessions for the same group/day into one planner card.
                // The card uses the earliest start and latest end time.
                if ( ! isset( $rows_by_group_day[$gid . '|' . $date] ) ) {
                    $rows_by_group_day[$gid . '|' . $date] = array(
                        'session_id'=>0,
                        'group_id'=>$gid,
                        'venue_id'=>$venue_id,
                        'date'=>$date,
                        'start'=>$session['start'],
                        'end'=>$session['end'],
                        'label'=>$session['label'],
                        'title'=>get_the_title($gid),
                        'url'=>get_permalink($gid),
                        'image'=>function_exists('bubbahub_group_image') ? bubbahub_group_image($gid) : get_the_post_thumbnail_url($gid,'thumbnail'),
                        'venue'=>$venue_id ? get_the_title($venue_id) : '',
                        'venue_address'=>$venue_address,
                        'lat'=>($coords && isset($coords['lat']) ? $coords['lat'] : null),
                        'lng'=>($coords && isset($coords['lng']) ? $coords['lng'] : null),
                    );
                } else {
                    $key = $gid . '|' . $date;
                    if ( $session['start'] && $session['start'] < $rows_by_group_day[$key]['start'] ) {
                        $rows_by_group_day[$key]['start'] = $session['start'];
                    }
                    if ( $session['end'] && ( ! $rows_by_group_day[$key]['end'] || $session['end'] > $rows_by_group_day[$key]['end'] ) ) {
                        $rows_by_group_day[$key]['end'] = $session['end'];
                    }
                    if ( ! $rows_by_group_day[$key]['label'] && $session['label'] ) {
                        $rows_by_group_day[$key]['label'] = $session['label'];
                    }
                }
            }
        }
    }
    wp_reset_postdata();
    $rows = array_values($rows_by_group_day);

    usort($rows,function($a,$b){return ($a['date'].' '.$a['start']) <=> ($b['date'].' '.$b['start']);});
    return $rows;
}

function bubbahub_myhub_weekly_planner_v2_shortcode() {
    if(!is_user_logged_in()) return '<div class="bh-planner-empty">Please log in to use your weekly planner.</div>';

    $rows=bubbahub_myhub_planner_v2_schedule_occurrences(14);
    $days=array('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday');
    $now_ts = current_time('timestamp');
    $week_start_ts = strtotime('monday this week', $now_ts);
    $week_dates = array();
    foreach($days as $i=>$day){
        $week_dates[$day] = wp_date('Y-m-d', strtotime('+'.$i.' days', $week_start_ts));
    }

    // Keep the timetable focused on the current Monday-Sunday week.
    $week_by = array_fill_keys($days,array());
    foreach($rows as $row){
        $row_date = $row['date'];
        foreach($week_dates as $day=>$date){
            if($row_date === $date){
                $week_by[$day][] = $row;
                break;
            }
        }
    }

    // Work out a sensible vertical time range from the sessions.
    $min_minutes = 8 * 60;
    $max_minutes = 20 * 60;
    foreach($week_by as $entries){
        foreach($entries as $item){
            $st = strtotime($item['date'].' '.$item['start']);
            $en = $item['end'] ? strtotime($item['date'].' '.$item['end']) : $st + 3600;
            if($st) $min_minutes = min($min_minutes, ((int)wp_date('H',$st))*60 + (int)wp_date('i',$st));
            if($en) $max_minutes = max($max_minutes, ((int)wp_date('H',$en))*60 + (int)wp_date('i',$en));
        }
    }
    $grid_start = max(6 * 60, floor($min_minutes / 60) * 60);
    $grid_end = min(23 * 60, ceil($max_minutes / 60) * 60);
    if($grid_end <= $grid_start) $grid_end = $grid_start + 12 * 60;
    $grid_hours = max(1, (int)(($grid_end - $grid_start) / 60));

    // Add calendar URLs and simple collision lanes for desktop events.
    foreach($week_by as $day=>$entries){
        usort($week_by[$day],function($a,$b){return ($a['date'].' '.$a['start']) <=> ($b['date'].' '.$b['start']);});
        $lanes = array();
        foreach($week_by[$day] as $idx=>&$item){
            $ts=strtotime($item['date'].' '.$item['start']);
            $end_ts=$item['end'] ? strtotime($item['date'].' '.$item['end']) : $ts+3600;
            if($end_ts < $ts) $end_ts=strtotime('+1 day',$end_ts);
            $item['calendar_url']='https://calendar.google.com/calendar/render?action=TEMPLATE&text='.rawurlencode($item['title']).'&dates='.rawurlencode(wp_date('Ymd\\THis',$ts)).'/'.rawurlencode(wp_date('Ymd\\THis',$end_ts)).'&details='.rawurlencode('Bubba Hub: '.$item['url']).'&location='.rawurlencode($item['venue_address'] ?: $item['venue']);
            $start_m = ((int)wp_date('H',$ts))*60 + (int)wp_date('i',$ts);
            $end_m = ((int)wp_date('H',$end_ts))*60 + (int)wp_date('i',$end_ts);
            $lane = 0;
            while(isset($lanes[$lane]) && $lanes[$lane] > $start_m) $lane++;
            $lanes[$lane] = $end_m;
            $item['lane'] = $lane;
            $item['lane_count'] = count($lanes);
        }
        unset($item);
        // Make all events in an overlapping cluster use the same lane count.
        foreach($week_by[$day] as &$item){
            $item['lane_count'] = max(1, count($lanes));
        }
        unset($item);
    }

    $account_url=add_query_arg('bh_account_settings','1',home_url('/my-hub/'));

    ob_start(); ?>
    <section class="bh-myhub-section bh-weekly-planner bh-weekly-planner-v2">
      <div class="bh-myhub-section-heading">
        <div><div class="bh-myhub-kicker">YOUR WEEK</div><h2>Weekly Planner</h2></div>
        <a class="bh-weekly-planner-preferences" href="<?php echo esc_url($account_url); ?>">Update Preferences →</a>
      </div>

      <div class="bh-planner-search">
        <div class="bh-planner-search-row">
          <label class="bh-planner-search-location"><span>Choose Region</span>
            <select class="bh-planner-location-select">
              <option value="">All regions</option>
              <?php
              if(taxonomy_exists('region')){
                  $terms=get_terms(array('taxonomy'=>'region','hide_empty'=>false,'number'=>200,'orderby'=>'name','order'=>'ASC'));
                  if(!is_wp_error($terms)) foreach($terms as $term): ?>
                    <option value="<?php echo esc_attr($term->term_id); ?>"><?php echo esc_html($term->name); ?></option>
                  <?php endforeach;
              } ?>
            </select>
          </label>
        </div>
      </div>
      <div class="bh-planner-simple-note">Your groups are shown in a weekly timetable. On mobile, tap a day to open or close it.</div>

      <div class="bh-planner-timetable-desktop">
        <div class="bh-timetable-header">
          <div class="bh-timetable-time-head">TIME</div>
          <?php foreach($days as $day): ?>
            <div class="bh-timetable-day-head">
              <strong><?php echo esc_html(substr($day,0,3)); ?></strong>
              <span><?php echo esc_html(wp_date('j M', strtotime($week_dates[$day]))); ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="bh-timetable-body" style="--bh-grid-hours:<?php echo esc_attr($grid_hours); ?>;">
          <div class="bh-timetable-times">
            <?php for($h=$grid_start;$h<=$grid_end;$h+=60): ?>
              <span style="top:<?php echo esc_attr((($h-$grid_start)/60)*60); ?>px;"><?php echo esc_html(sprintf('%02d:00',floor($h/60))); ?></span>
            <?php endfor; ?>
          </div>
          <?php foreach($days as $day): ?>
            <div class="bh-timetable-column" data-day="<?php echo esc_attr($day); ?>">
              <?php for($h=$grid_start;$h<$grid_end;$h+=60): ?><div class="bh-timetable-hour-line" style="top:<?php echo esc_attr((($h-$grid_start)/60)*60); ?>px;"></div><?php endfor; ?>
              <?php if(!empty($week_by[$day])): foreach($week_by[$day] as $item):
                $start_ts=strtotime($item['date'].' '.$item['start']);
                $end_ts=$item['end'] ? strtotime($item['date'].' '.$item['end']) : $start_ts+3600;
                if($end_ts < $start_ts) $end_ts=strtotime('+1 day',$end_ts);
                $start_m=((int)wp_date('H',$start_ts))*60+(int)wp_date('i',$start_ts);
                $end_m=((int)wp_date('H',$end_ts))*60+(int)wp_date('i',$end_ts);
                $top=max(0,$start_m-$grid_start);
                $height=max(42,$end_m-$start_m);
                $lane_count=max(1,(int)$item['lane_count']);
                $lane=(int)$item['lane'];
                $left=($lane*100/$lane_count);
                $width=100/$lane_count;
                $location_ids=bubbahub_myhub_planner_v2_region_ids((int)$item['group_id']);
              ?>
                <div class="bh-timetable-event bh-planner-filter-item" data-planner-location-ids="<?php echo esc_attr(implode(',',array_map('absint',(array)$location_ids))); ?>" style="top:<?php echo esc_attr($top); ?>px;height:<?php echo esc_attr($height); ?>px;left:<?php echo esc_attr($left); ?>%;width:<?php echo esc_attr($width); ?>%;">
                  <a href="<?php echo esc_url($item['url']); ?>">
                    <strong><?php echo esc_html($item['title']); ?></strong>
                    <?php if($item['label']): ?><span><?php echo esc_html($item['label']); ?></span><?php endif; ?>
                    <b><?php echo esc_html($item['start'].($item['end']?'–'.$item['end']:'')); ?></b>
                    <small><?php echo esc_html($item['venue'] ?: 'Location to be confirmed'); ?></small>
                  </a>
                </div>
              <?php endforeach; else: ?>
                <div class="bh-timetable-empty">No groups</div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="bh-planner-mobile">
        <?php foreach($days as $day): ?>
          <details class="bh-planner-mobile-day">
            <summary>
              <span><strong><?php echo esc_html($day); ?></strong><small><?php echo esc_html(wp_date('j F',strtotime($week_dates[$day]))); ?></small></span>
              <span class="bh-planner-mobile-count"><?php echo esc_html(count($week_by[$day])); ?></span>
            </summary>
            <div class="bh-planner-mobile-items">
              <?php if(!empty($week_by[$day])): foreach($week_by[$day] as $item):
                $location_ids=bubbahub_myhub_planner_v2_region_ids((int)$item['group_id']);
              ?>
                <div class="bh-planner-item-wrap bh-planner-filter-item" data-planner-location-ids="<?php echo esc_attr(implode(',',array_map('absint',(array)$location_ids))); ?>">
                  <div class="bh-planner-item">
                    <a class="bh-planner-listing-link" href="<?php echo esc_url($item['url']); ?>">
                      <span class="bh-planner-thumb"><?php if($item['image']): ?><img src="<?php echo esc_url($item['image']); ?>" alt="" loading="lazy"><?php else: ?><span class="bh-planner-placeholder" aria-hidden="true">♡</span><?php endif; ?></span>
                      <span class="bh-planner-item-main">
                        <strong><?php echo esc_html($item['title']); ?></strong>
                        <?php if($item['label']): ?><span class="bh-planner-session-label"><?php echo esc_html($item['label']); ?></span><?php endif; ?>
                        <span class="bh-planner-time"><?php echo esc_html($item['start'].($item['end']?'–'.$item['end']:'')); ?></span>
                        <span class="bh-planner-location">📍 <?php echo esc_html($item['venue'] ?: 'Location to be confirmed'); ?></span>
                      </span>
                    </a>
                  </div>
                </div>
              <?php endforeach; else: ?><div class="bh-planner-empty">No groups</div><?php endif; ?>
            </div>
          </details>
        <?php endforeach; ?>
      </div>


<style>
.bh-planner-simple-note{margin:0 0 18px;padding:12px 16px;border:1px solid #e6e6e6;border-radius:12px;background:#fff;font-size:14px}
.bh-planner-search{margin:0 0 18px;padding:16px;border:1px solid #e6e6e6;border-radius:16px;background:#fff}
.bh-planner-search-row{display:flex;gap:12px;align-items:end}
.bh-planner-search-location{display:flex;flex-direction:column;gap:6px;max-width:420px}
.bh-planner-search-row label span{font-weight:700;font-size:13px}
.bh-planner-search-row select{min-height:44px;padding:10px 12px;border:1px solid #ddd;border-radius:10px;background:#fff}
.bh-planner-timetable-desktop{border:1px solid #d9d9d9;border-radius:12px;overflow:auto;background:#fff}
.bh-timetable-header{display:grid;grid-template-columns:58px repeat(7,minmax(120px,1fr));min-width:900px;background:#fafafa;border-bottom:1px solid #d9d9d9}
.bh-timetable-time-head{padding:9px 5px;font-size:10px;color:#777;border-right:1px solid #ddd;text-align:center}
.bh-timetable-day-head{padding:8px 5px;text-align:center;border-right:1px solid #ddd;display:flex;flex-direction:column;gap:2px}
.bh-timetable-day-head strong{font-size:13px}
.bh-timetable-day-head span{font-size:11px;color:#777}
.bh-timetable-body{display:grid;grid-template-columns:58px repeat(7,minmax(120px,1fr));min-width:900px;height:calc(var(--bh-grid-hours) * 60px);position:relative}
.bh-timetable-times{position:relative;border-right:1px solid #ddd;background:#fff}
.bh-timetable-times span{position:absolute;left:0;right:0;transform:translateY(-7px);font-size:10px;color:#777;text-align:center}
.bh-timetable-column{position:relative;border-right:1px solid #ddd;background:repeating-linear-gradient(to bottom,transparent 0,transparent 59px,#e9e9e9 59px,#e9e9e9 60px)}
.bh-timetable-hour-line{position:absolute;left:0;right:0;border-top:1px solid #ececec;pointer-events:none}
.bh-timetable-event{position:absolute;box-sizing:border-box;padding:2px;border-radius:6px;overflow:hidden;min-width:0}
.bh-timetable-event a{display:flex;flex-direction:column;height:100%;padding:7px 8px;background:#dceff1;border:1px solid #9bc8cc;border-radius:5px;color:inherit;text-decoration:none!important;overflow:hidden}
.bh-timetable-event strong{font-size:12px;line-height:1.2}
.bh-timetable-event span{font-size:10px;line-height:1.2;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bh-timetable-event b{font-size:10px;line-height:1.2;margin-top:3px}
.bh-timetable-event small{font-size:9px;line-height:1.2;margin-top:auto;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bh-timetable-empty{padding:12px;text-align:center;color:#aaa;font-size:11px}
.bh-planner-mobile{display:none}
.bh-planner-mobile-day{border:1px solid #e4e4e4;border-radius:12px;background:#fff;margin:0 0 9px;overflow:hidden}
.bh-planner-mobile-day summary{list-style:none;cursor:pointer;display:flex;align-items:center;justify-content:space-between;padding:13px 15px}
.bh-planner-mobile-day summary::-webkit-details-marker{display:none}
.bh-planner-mobile-day summary > span:first-child{display:flex;flex-direction:column;gap:2px}
.bh-planner-mobile-day summary strong{font-size:15px}
.bh-planner-mobile-day summary small{font-size:11px;color:#777}
.bh-planner-mobile-count{min-width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#f1f1f1;font-size:11px;font-weight:700}
.bh-planner-mobile-day[open] summary{border-bottom:1px solid #eee}
.bh-planner-mobile-items{padding:10px;display:grid;gap:9px}
.bh-planner-item-wrap{margin:0}
.bh-planner-item{height:100%;border:1px solid #e7e7e7;border-radius:12px;background:#fff;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.04)}
.bh-planner-listing-link{display:flex;align-items:center;gap:10px;padding:10px;text-decoration:none!important}
.bh-planner-thumb{width:58px;height:58px;min-width:58px;border-radius:9px;overflow:hidden;display:flex;align-items:center;justify-content:center;background:#f3f3f3}
.bh-planner-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.bh-planner-placeholder{font-size:22px;opacity:.45}
.bh-planner-item-main{display:flex;flex-direction:column;min-width:0;line-height:1.3}
.bh-planner-item-main strong{font-size:14px;line-height:1.25}
.bh-planner-session-label{font-size:12px;opacity:.75;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bh-planner-time{font-size:12px;font-weight:700;margin-top:2px}
.bh-planner-location{font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
@media(max-width:700px){
  .bh-planner-search-row{display:block}
  .bh-planner-search-location{max-width:none}
  .bh-timetable-header,.bh-timetable-body{min-width:0}
  .bh-planner-timetable-desktop{display:none}
  .bh-planner-mobile{display:block}
}
</style>

<script>
document.addEventListener('DOMContentLoaded',function(){
  document.querySelectorAll('.bh-weekly-planner-v2').forEach(function(planner){
    var location=planner.querySelector('.bh-planner-location-select');
    if(!location) return;

    function applyRegion(){
      var loc=location.value||'';
      planner.querySelectorAll('.bh-planner-filter-item').forEach(function(item){
        var locs=(item.getAttribute('data-planner-location-ids')||'').split(',').filter(Boolean);
        var show=!loc || locs.indexOf(String(loc))!==-1;
        item.hidden=!show;
        item.style.display=show?'':'none';
      });

      planner.querySelectorAll('.bh-timetable-column').forEach(function(column){
        var visible=Array.from(column.querySelectorAll('.bh-timetable-event')).some(function(x){return !x.hidden;});
        var empty=column.querySelector('.bh-timetable-live-empty');
        if(!empty){
          empty=document.createElement('div');
          empty.className='bh-timetable-empty bh-timetable-live-empty';
          empty.textContent='No groups';
          column.appendChild(empty);
        }
        empty.hidden=visible;
      });

      planner.querySelectorAll('.bh-planner-mobile-day').forEach(function(day){
        var visible=Array.from(day.querySelectorAll('.bh-planner-item-wrap')).some(function(x){return !x.hidden;});
        var empty=day.querySelector('.bh-planner-mobile-live-empty');
        if(!empty){
          empty=document.createElement('div');
          empty.className='bh-planner-empty bh-planner-mobile-live-empty';
          empty.textContent='No groups in this region.';
          day.querySelector('.bh-planner-mobile-items').appendChild(empty);
        }
        empty.hidden=visible;
      });
    }
    location.addEventListener('change',applyRegion);
    applyRegion();
  });
});
</script>
    </section>
    <?php return ob_get_clean();
}
