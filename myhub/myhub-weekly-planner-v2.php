<?php
/**
 * BubbaHub My Hub – session-based weekly planner.
 * Reads the ACF Business & Class Hours > Weekly Schedule field.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'bubbahub_weekly_planner_v2', 'bubbahub_myhub_weekly_planner_v2_shortcode' );

function bubbahub_myhub_planner_v2_weekly_schedule_rows( $post_id ) {
    $value = function_exists( 'get_field' ) ? get_field( 'weekly_schedule', $post_id, false ) : get_post_meta( $post_id, 'weekly_schedule', true );

    if ( is_string( $value ) ) {
        $unserialized = maybe_unserialize( $value );
        if ( is_array( $unserialized ) ) {
            $value = $unserialized;
        } else {
            $decoded = json_decode( $value, true );
            if ( JSON_ERROR_NONE === json_last_error() ) $value = $decoded;
        }
    }
    if ( ! is_array( $value ) ) return array();

    $day_names = array(
        'monday'=>'Monday','mon'=>'Monday','tuesday'=>'Tuesday','tue'=>'Tuesday',
        'wednesday'=>'Wednesday','wed'=>'Wednesday','thursday'=>'Thursday','thu'=>'Thursday',
        'friday'=>'Friday','fri'=>'Friday','saturday'=>'Saturday','sat'=>'Saturday',
        'sunday'=>'Sunday','sun'=>'Sunday',
    );
    $rows = array();

    foreach ( $value as $row ) {
        if ( ! is_array( $row ) ) continue;
        $day_key = strtolower( trim( (string) ( $row['day_name'] ?? '' ) ) );
        if ( ! isset( $day_names[$day_key] ) || ! empty( $row['is_closed'] ) ) continue;

        $sessions = $row['sessions'] ?? array();
        if ( ! is_array( $sessions ) ) $sessions = array( $sessions );

        foreach ( $sessions as $session ) {
            $start = ''; $end = ''; $label = '';
            if ( is_array( $session ) ) {
                $start = trim( (string) ( $session['start_time'] ?? $session['start'] ?? $session['from'] ?? '' ) );
                $end   = trim( (string) ( $session['end_time'] ?? $session['end'] ?? $session['to'] ?? '' ) );
                $label = trim( (string) ( $session['session_label'] ?? $session['label'] ?? '' ) );
            } elseif ( is_string( $session ) ) {
                if ( preg_match( '/(\d{1,2}:\d{2})\s*(?:[-–—]|to)\s*(\d{1,2}:\d{2})/i', $session, $m ) ) {
                    $start = $m[1]; $end = $m[2];
                } elseif ( preg_match( '/\d{1,2}:\d{2}/', $session, $m ) ) {
                    $start = $m[0];
                }
            }

            if ( ! preg_match( '/^\d{1,2}:\d{2}$/', $start ) ) continue;
            if ( $end && ! preg_match( '/^\d{1,2}:\d{2}$/', $end ) ) $end = '';

            $rows[$day_names[$day_key]][] = array(
                'start'=>$start,
                'end'=>$end,
                'label'=>$label,
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

                foreach($sessions as $session){
                    $rows[]=array(
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
                }
            }
        }
    }
    wp_reset_postdata();

    usort($rows,function($a,$b){return ($a['date'].' '.$a['start']) <=> ($b['date'].' '.$b['start']);});
    return $rows;
}

function bubbahub_myhub_weekly_planner_v2_shortcode() {
    if(!is_user_logged_in()) return '<div class="bh-planner-empty">Please log in to use your weekly planner.</div>';

    $rows=bubbahub_myhub_planner_v2_schedule_occurrences(7);
    $days=array('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday');
    $by=array_fill_keys($days,array());

    foreach($rows as $row){
        $ts=strtotime($row['date'].' '.$row['start']);
        if(!$ts) continue;
        $end_ts=$row['end'] ? strtotime($row['date'].' '.$row['end']) : $ts+3600;
        if($end_ts < $ts) $end_ts=strtotime('+1 day',$end_ts);

        $day=wp_date('l',$ts);
        $row['calendar_url']='https://calendar.google.com/calendar/render?action=TEMPLATE&text='.rawurlencode($row['title']).'&dates='.rawurlencode(wp_date('Ymd\THis',$ts)).'/'.rawurlencode(wp_date('Ymd\THis',$end_ts)).'&details='.rawurlencode('Bubba Hub: '.$row['url']).'&location='.rawurlencode($row['venue_address'] ?: $row['venue']);
        $by[$day][]=$row;
    }

    foreach($by as &$entries) usort($entries,function($a,$b){return ($a['date'].' '.$a['start']) <=> ($b['date'].' '.$b['start']);});
    unset($entries);

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
      <div class="bh-planner-simple-note">Choose a region to see the groups running on each day.</div>

      <div class="bh-planner-results">
      <?php foreach($days as $day): ?><div class="bh-planner-day">
        <div class="bh-planner-day-accordion">
          <div class="bh-planner-day-title"><?php echo esc_html($day); ?><span class="bh-planner-day-chevron" aria-hidden="true">⌄</span></div>
          <div class="bh-planner-day-items">
          <?php if(!empty($by[$day])): foreach($by[$day] as $item):
              $location_ids=bubbahub_myhub_planner_v2_region_ids((int)$item['group_id']);
          ?>
            <div class="bh-planner-item-wrap" data-planner-location-ids="<?php echo esc_attr(implode(',',array_map('absint',(array)$location_ids))); ?>">
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
          <?php endforeach; else: ?><div class="bh-planner-empty">No matching sessions.</div><?php endif; ?>
          </div>
        </div>
      </div><?php endforeach; ?>
      </div>

<style>
.bh-planner-simple-note{margin:0 0 20px;padding:14px 18px;border:1px solid #e6e6e6;border-radius:14px;background:#fff;font-size:14px}
.bh-planner-search{margin:0 0 20px;padding:18px;border:1px solid #e6e6e6;border-radius:18px;background:#fff}
.bh-planner-search-row{display:flex;gap:12px;align-items:end}
.bh-planner-search-location{display:flex;flex-direction:column;gap:6px;max-width:420px}
.bh-planner-search-row label span{font-weight:700;font-size:13px}
.bh-planner-search-row select{min-height:44px;padding:10px 12px;border:1px solid #ddd;border-radius:10px}
.bh-planner-item-main{display:flex;flex-direction:column}
.bh-planner-session-label{font-size:13px;opacity:.8}
.bh-planner-time{font-weight:700}
@media(max-width:700px){.bh-planner-search-row{display:block}.bh-planner-search-location{max-width:none}}
</style>

<script>
document.addEventListener('DOMContentLoaded',function(){
  document.querySelectorAll('.bh-weekly-planner-v2').forEach(function(planner){
    var location=planner.querySelector('.bh-planner-location-select');
    if(!location) return;

    function applyRegion(){
      var loc=location?location.value:'';
      planner.querySelectorAll('.bh-planner-item-wrap').forEach(function(item){
        var locs=(item.getAttribute('data-planner-location-ids')||'').split(',').filter(Boolean);
        var show = !loc || locs.indexOf(String(loc)) !== -1;
        item.hidden = !show;
        item.style.display = show ? '' : 'none';
      });
      planner.querySelectorAll('.bh-planner-day').forEach(function(day){
        var items=day.querySelectorAll('.bh-planner-item-wrap');
        var visible=Array.from(items).some(function(x){return !x.hidden;});
        var empty=day.querySelector('.bh-planner-live-empty');
        if(!empty){
          empty=document.createElement('div');
          empty.className='bh-planner-empty bh-planner-live-empty';
          empty.textContent='No groups running in this region.';
          day.querySelector('.bh-planner-day-items').appendChild(empty);
        }
        empty.hidden=visible;
      });
    }
    if(location) location.addEventListener('change',applyRegion);
    applyRegion();
  });
});
</script>
    </section>
    <?php return ob_get_clean();
}
