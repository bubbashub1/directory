<?php
/**
 * BubbaHub My Hub – session-based weekly planner.
 * Reads the ACF Business & Class Hours > Weekly Schedule field.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'bubbahub_weekly_planner_v2', 'bubbahub_myhub_weekly_planner_v2_shortcode' );

function bubbahub_myhub_planner_v2_weekly_schedule_rows( $post_id ) {
    $value = function_exists( 'get_field' ) ? get_field( 'weekly_schedule', $post_id ) : get_post_meta( $post_id, 'weekly_schedule', true );

    if ( is_string( $value ) ) {
        $unserialized = maybe_unserialize( $value );
        if ( is_array( $unserialized ) ) {
            $value = $unserialized;
        } else {
            $decoded = json_decode( $value, true );
            if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) $value = $decoded;
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
        $raw_day = $row['day_name'] ?? $row['day'] ?? $row['weekday'] ?? '';
        $day_key = strtolower( trim( (string) $raw_day ) );
        if ( ! isset( $day_names[$day_key] ) ) $day_key = strtolower( preg_replace( '/[^a-z]/', '', $day_key ) );
        if ( ! isset( $day_names[$day_key] ) ) continue;

        $closed = $row['is_closed'] ?? false;
        if ( $closed === true || $closed === 1 || $closed === '1' || in_array( strtolower((string)$closed), array('yes','true'), true ) ) continue;

        $frequency = strtolower( trim( (string) ( $row['frequency'] ?? $row['repeat_frequency'] ?? $row['recurrence'] ?? 'weekly' ) ) );
        $frequency_map = array(
            'week'=>'weekly','weekly'=>'weekly','every week'=>'weekly',
            'fortnight'=>'fortnightly','fortnightly'=>'fortnightly','biweekly'=>'fortnightly','every 2 weeks'=>'fortnightly',
            'month'=>'monthly','monthly'=>'monthly','every month'=>'monthly',
        );
        $frequency = $frequency_map[$frequency] ?? 'weekly';

        $term_time_only = $row['term_time_only'] ?? $row['term_time'] ?? false;
        $term_time_only = in_array( strtolower((string)$term_time_only), array('1','true','yes','on'), true ) || $term_time_only === true;

        $recurrence_start = trim( (string) ( $row['recurrence_start_date'] ?? $row['start_date'] ?? $row['from_date'] ?? '' ) );
        $recurrence_end = trim( (string) ( $row['recurrence_end_date'] ?? $row['end_date'] ?? $row['to_date'] ?? '' ) );

        $sessions = $row['sessions'] ?? array();
        if ( is_string( $sessions ) ) {
            $decoded = json_decode( $sessions, true );
            $sessions = ( JSON_ERROR_NONE === json_last_error() && is_array($decoded) ) ? $decoded : maybe_unserialize($sessions);
        }
        if ( ! is_array( $sessions ) ) $sessions = array( $sessions );

        foreach ( $sessions as $session ) {
            if ( ! is_array( $session ) ) continue;
            $start = trim( (string) ( $session['start_time'] ?? $session['start'] ?? $session['from'] ?? '' ) );
            $end   = trim( (string) ( $session['end_time'] ?? $session['end'] ?? $session['to'] ?? '' ) );
            $label = trim( (string) ( $session['session_label'] ?? $session['label'] ?? $session['name'] ?? '' ) );

            if ( preg_match( '/^(\d{1,2}:\d{2})(?::\d{2})?$/', $start, $m ) ) $start = $m[1]; else continue;
            if ( $end && preg_match( '/^(\d{1,2}:\d{2})(?::\d{2})?$/', $end, $m ) ) $end = $m[1];
            elseif ( $end ) $end = '';

            $rows[$day_names[$day_key]][] = array(
                'start'=>$start, 'end'=>$end, 'label'=>$label,
                'frequency'=>$frequency, 'term_time_only'=>$term_time_only,
                'recurrence_start_date'=>$recurrence_start, 'recurrence_end_date'=>$recurrence_end,
            );
        }
    }
    return $rows;
}

function bubbahub_myhub_planner_v2_get_school_holiday_dates() {
    $cached = get_transient( 'bubbahub_devon_school_holidays' );
    if ( is_array( $cached ) ) return $cached;

    $url = 'https://schoolholidays.org.uk/area/devon';
    $response = wp_remote_get( $url, array(
        'timeout'    => 12,
        'redirection'=> 3,
        'headers'   => array( 'User-Agent' => 'BubbaHub Calendar/1.0; ' . home_url('/') ),
    ) );

    $holidays = array();

    if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
        $html = wp_remote_retrieve_body( $response );

        if ( $html && class_exists( 'DOMDocument' ) ) {
            libxml_use_internal_errors( true );
            $dom = new DOMDocument();
            if ( @$dom->loadHTML( '<?xml encoding="UTF-8">' . $html ) ) {
                $xpath = new DOMXPath( $dom );
                $rows = $xpath->query( '//table//tr' );

                foreach ( $rows as $tr ) {
                    $cells = $xpath->query( './th|./td', $tr );
                    if ( $cells->length < 2 ) continue;

                    $label = trim( preg_replace( '/\\s+/', ' ', $cells->item(0)->textContent ) );
                    $start_text = trim( preg_replace( '/\\s+/', ' ', $cells->item(1)->textContent ) );
                    $end_text = $cells->length >= 3 ? trim( preg_replace( '/\\s+/', ' ', $cells->item(2)->textContent ) ) : $start_text;

                    if ( ! preg_match( '/^(\\d{1,2})(?:st|nd|rd|th)?\\s+([A-Za-z]+)\\s+(\\d{4})$/', $start_text, $m ) ) continue;
                    if ( ! preg_match( '/^(\\d{1,2})(?:st|nd|rd|th)?\\s+([A-Za-z]+)\\s+(\\d{4})$/', $end_text, $e ) ) $e = $m;

                    $start_date = date_create( $m[3] . '-' . $m[2] . '-' . $m[1] );
                    $end_date = date_create( $e[3] . '-' . $e[2] . '-' . $e[1] );
                    if ( ! $start_date || ! $end_date ) continue;

                    $start_iso = $start_date->format('Y-m-d');
                    $end_iso = $end_date->format('Y-m-d');

                    if ( $start_iso <= $end_iso ) {
                        $holidays[] = array(
                            'name'  => $label,
                            'start' => $start_iso,
                            'end'   => $end_iso,
                        );
                    }
                }
            }
            libxml_clear_errors();
        }
    }

    /*
     * If the external source is temporarily unavailable, retain the existing
     * administrator-configured dates rather than making term-time listings
     * appear during known holiday periods.
     */
    if ( empty( $holidays ) ) {
        $configured = get_option( 'bubbahub_school_holiday_dates', array() );
        if ( is_array( $configured ) ) $holidays = $configured;
    }

    /*
     * Cache the source for 12 hours. This avoids a remote request for every
     * calendar cell while still allowing the calendar to pick up changes.
     */
    set_transient( 'bubbahub_devon_school_holidays', $holidays, 12 * HOUR_IN_SECONDS );

    return $holidays;
}

function bubbahub_myhub_planner_v2_is_term_time_date( $date ) {
    $result = apply_filters( 'bubbahub_planner_is_term_time_date', null, $date );
    if ( null !== $result ) return (bool) $result;

    $holidays = bubbahub_myhub_planner_v2_get_school_holiday_dates();

    /*
     * "Term Time Only" means the session is hidden whenever the occurrence
     * date falls inside one of the Devon school holiday periods published by
     * SchoolHolidays.org.uk.
     */
    foreach ( $holidays as $holiday ) {
        if ( ! is_array( $holiday ) ) continue;

        $start = preg_replace( '/[^0-9-]/', '', (string) ( $holiday['start'] ?? $holiday['start_date'] ?? '' ) );
        $end   = preg_replace( '/[^0-9-]/', '', (string) ( $holiday['end'] ?? $holiday['end_date'] ?? '' ) );

        if ( $start && $end && $date >= $start && $date <= $end ) return false;
    }

    return true;
}

function bubbahub_myhub_planner_v2_date_matches_recurrence( $date, $weekday, $rule ) {
    $ts = strtotime($date);
    if ( ! $ts ) return false;
    if ( strtolower(wp_date('l',$ts)) !== strtolower($weekday) ) return false;

    $frequency = $rule['frequency'] ?? 'weekly';
    $anchor = $rule['recurrence_start_date'] ?? '';

    if ( 'weekly' === $frequency ) return true;
    if ( ! $anchor ) return false;

    $anchor_ts = strtotime($anchor);
    if ( ! $anchor_ts || $ts < strtotime(wp_date('Y-m-d',$anchor_ts)) ) return false;

    if ( 'fortnightly' === $frequency ) {
        $days = (int) floor( ($ts - strtotime(wp_date('Y-m-d',$anchor_ts))) / DAY_IN_SECONDS );
        return 0 === ($days % 14);
    }

    if ( 'monthly' === $frequency ) {
        return wp_date('j',$ts) === wp_date('j',$anchor_ts);
    }

    return true;
}

function bubbahub_myhub_planner_v2_get_week_start( $date ) {
    $ts = strtotime($date);
    if ( ! $ts ) $ts = current_time('timestamp');
    $day = (int) wp_date('N',$ts);
    return wp_date('Y-m-d', strtotime('-'.($day-1).' days', $ts));
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

function bubbahub_myhub_planner_v2_schedule_occurrences( $range_start, $range_end ) {
    $range_start_ts = strtotime($range_start);
    $range_end_ts = strtotime($range_end);
    if ( ! $range_start_ts || ! $range_end_ts || $range_end_ts < $range_start_ts ) return array();

    $day_map = array('Sunday'=>0,'Monday'=>1,'Tuesday'=>2,'Wednesday'=>3,'Thursday'=>4,'Friday'=>5,'Saturday'=>6);
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

            foreach($sessions as $session){
                for($ts=$range_start_ts; $ts <= $range_end_ts; $ts += DAY_IN_SECONDS){
                    $date=wp_date('Y-m-d',$ts);
                    if(!bubbahub_myhub_planner_v2_date_matches_recurrence($date,$weekday,$session)) continue;

                    /*
                     * Recurrence End Date takes precedence when supplied.
                     * When no End Date is supplied, Term Time Only controls
                     * whether Devon school holiday dates are used to stop
                     * occurrences being shown.
                     */
                    if ( ! empty($session['recurrence_start_date']) && $date < $session['recurrence_start_date'] ) continue;

                    if ( ! empty($session['recurrence_end_date']) ) {
                        if ( $date > $session['recurrence_end_date'] ) continue;
                    } elseif ( ! empty($session['term_time_only']) && !bubbahub_myhub_planner_v2_is_term_time_date($date) ) {
                        continue;
                    }

                    $key = $gid . '|' . $date;
                    if ( ! isset($rows_by_group_day[$key]) ) {
                        $rows_by_group_day[$key] = array(
                            'session_id'=>0,'group_id'=>$gid,'venue_id'=>$venue_id,'date'=>$date,
                            'start'=>$session['start'],'end'=>$session['end'],'label'=>$session['label'],
                            'frequency'=>$session['frequency'],'term_time_only'=>$session['term_time_only'],
                            'title'=>get_the_title($gid),'url'=>get_permalink($gid),
                            'image'=>function_exists('bubbahub_group_image') ? bubbahub_group_image($gid) : get_the_post_thumbnail_url($gid,'thumbnail'),
                            'venue'=>$venue_id ? get_the_title($venue_id) : '',
                            'venue_address'=>$venue_address,
                            'lat'=>($coords && isset($coords['lat']) ? $coords['lat'] : null),
                            'lng'=>($coords && isset($coords['lng']) ? $coords['lng'] : null),
                        );
                    } else {
                        if ( $session['start'] && $session['start'] < $rows_by_group_day[$key]['start'] ) $rows_by_group_day[$key]['start']=$session['start'];
                        if ( $session['end'] && (!$rows_by_group_day[$key]['end'] || $session['end'] > $rows_by_group_day[$key]['end']) ) $rows_by_group_day[$key]['end']=$session['end'];
                        if ( !$rows_by_group_day[$key]['label'] && $session['label'] ) $rows_by_group_day[$key]['label']=$session['label'];
                    }
                }
            }
        }
    }
    wp_reset_postdata();

    $rows = array_values($rows_by_group_day);
    usort($rows,function($a,$b){ return ($a['date'].' '.$a['start']) <=> ($b['date'].' '.$b['start']); });
    return $rows;
}

function bubbahub_myhub_weekly_planner_v2_shortcode() {
    if(!is_user_logged_in()) return '<div class="bh-planner-empty">Please log in to use your weekly planner.</div>';

    $requested = isset($_GET['bh_week']) ? sanitize_text_field(wp_unslash($_GET['bh_week'])) : '';
    $week_start = bubbahub_myhub_planner_v2_get_week_start($requested ?: wp_date('Y-m-d',current_time('timestamp')));
    $week_start_ts = strtotime($week_start);
    $week_end = wp_date('Y-m-d',strtotime('+6 days',$week_start_ts));

    $rows = bubbahub_myhub_planner_v2_schedule_occurrences($week_start,$week_end);
    $days=array('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday');
    $week_dates=array();
    foreach($days as $i=>$day) $week_dates[$day]=wp_date('Y-m-d',strtotime('+'.$i.' days',$week_start_ts));

    $week_by=array_fill_keys($days,array());
    foreach($rows as $row) foreach($week_dates as $day=>$date) if($row['date']===$date){$week_by[$day][]=$row;break;}

    $min_minutes=8*60;$max_minutes=20*60;
    foreach($week_by as $entries) foreach($entries as $item){
        $st=strtotime($item['date'].' '.$item['start']);
        $en=$item['end']?strtotime($item['date'].' '.$item['end']):$st+3600;
        if($st)$min_minutes=min($min_minutes,(int)wp_date('H',$st)*60+(int)wp_date('i',$st));
        if($en)$max_minutes=max($max_minutes,(int)wp_date('H',$en)*60+(int)wp_date('i',$en));
    }
    $grid_start=max(6*60,floor($min_minutes/60)*60);
    $grid_end=min(23*60,ceil($max_minutes/60)*60);
    if($grid_end<=$grid_start)$grid_end=$grid_start+12*60;
    $grid_hours=max(1,(int)(($grid_end-$grid_start)/60));

    foreach($week_by as $day=>$entries){
        usort($week_by[$day],function($a,$b){return ($a['date'].' '.$a['start'])<=>($b['date'].' '.$b['start']);});
        $lanes=array();
        foreach($week_by[$day] as &$item){
            $ts=strtotime($item['date'].' '.$item['start']);
            $end_ts=$item['end']?strtotime($item['date'].' '.$item['end']):$ts+3600;
            if($end_ts<$ts)$end_ts=strtotime('+1 day',$end_ts);
            $item['calendar_url']=add_query_arg(array('bubbahub_calendar_event'=>1,'group'=>(int)$item['group_id'],'date'=>$item['date'],'start'=>$item['start'],'end'=>$item['end']),home_url('/'));
            $start_m=(int)wp_date('H',$ts)*60+(int)wp_date('i',$ts);$end_m=(int)wp_date('H',$end_ts)*60+(int)wp_date('i',$end_ts);
            $lane=0;while(isset($lanes[$lane])&&$lanes[$lane]>$start_m)$lane++;$lanes[$lane]=$end_m;$item['lane']=$lane;
        }
        unset($item);
        foreach($week_by[$day] as &$item)$item['lane_count']=max(1,count($lanes));
        unset($item);
    }

    $account_url=add_query_arg('bh_account_settings','1',home_url('/my-hub/'));
    $prev=add_query_arg('bh_week',wp_date('Y-m-d',strtotime('-7 days',$week_start_ts)));
    $next=add_query_arg('bh_week',wp_date('Y-m-d',strtotime('+7 days',$week_start_ts)));
    $today=add_query_arg('bh_week',bubbahub_myhub_planner_v2_get_week_start(wp_date('Y-m-d',current_time('timestamp'))));

    ob_start(); ?>
    <section class="bh-myhub-section bh-weekly-planner bh-weekly-planner-v2">
      <div class="bh-myhub-section-heading">
        <div><div class="bh-myhub-kicker">YOUR CALENDAR</div><h2>Weekly Planner</h2></div>
        <a class="bh-weekly-planner-preferences" href="<?php echo esc_url($account_url); ?>">Update Preferences →</a>
      </div>

      <div class="bh-planner-calendar-toolbar">
        <a href="<?php echo esc_url($prev); ?>">← Previous</a>
        <a class="bh-planner-today" href="<?php echo esc_url($today); ?>">Today</a>
        <strong><?php echo esc_html(wp_date('j M',strtotime($week_dates['Monday'])).' – '.wp_date('j M Y',strtotime($week_dates['Sunday']))); ?></strong>
        <a href="<?php echo esc_url($next); ?>">Next →</a>
      </div>

      <div class="bh-planner-search">
        <label class="bh-planner-search-location"><span>Choose Region</span>
          <select class="bh-planner-location-select">
            <option value="">All regions</option>
            <?php if(taxonomy_exists('region')){ $terms=get_terms(array('taxonomy'=>'region','hide_empty'=>false,'number'=>200,'orderby'=>'name','order'=>'ASC')); if(!is_wp_error($terms)) foreach($terms as $term): ?>
              <option value="<?php echo esc_attr($term->term_id); ?>"><?php echo esc_html($term->name); ?></option>
            <?php endforeach; } ?>
          </select>
        </label>
      </div>

      <div class="bh-planner-timetable-desktop">
        <div class="bh-timetable-header"><div class="bh-timetable-time-head">TIME</div>
          <?php foreach($days as $day): ?><div class="bh-timetable-day-head"><strong><?php echo esc_html(substr($day,0,3)); ?></strong><span><?php echo esc_html(wp_date('j M',strtotime($week_dates[$day]))); ?></span></div><?php endforeach; ?>
        </div>
        <div class="bh-timetable-body" style="--bh-grid-hours:<?php echo esc_attr($grid_hours); ?>;">
          <div class="bh-timetable-times"><?php for($h=$grid_start;$h<=$grid_end;$h+=60): ?><span style="top:<?php echo esc_attr((($h-$grid_start)/60)*60); ?>px;"><?php echo esc_html(sprintf('%02d:00',floor($h/60))); ?></span><?php endfor; ?></div>
          <?php foreach($days as $day): $day_lane_count=0; foreach($week_by[$day] as $day_item) $day_lane_count=max($day_lane_count,(int)($day_item['lane_count']??1)); ?><div class="bh-timetable-column<?php echo $day_lane_count>2?' bh-timetable-column-many':''; ?>" data-day="<?php echo esc_attr($day); ?>" data-lane-count="<?php echo esc_attr($day_lane_count); ?>">
            <?php for($h=$grid_start;$h<$grid_end;$h+=60): ?><div class="bh-timetable-hour-line" style="top:<?php echo esc_attr((($h-$grid_start)/60)*60); ?>px;"></div><?php endfor; ?>
            <?php if(!empty($week_by[$day])): foreach($week_by[$day] as $item):
              $start_ts=strtotime($item['date'].' '.$item['start']);$end_ts=$item['end']?strtotime($item['date'].' '.$item['end']):$start_ts+3600;if($end_ts<$start_ts)$end_ts=strtotime('+1 day',$end_ts);
              $start_m=(int)wp_date('H',$start_ts)*60+(int)wp_date('i',$start_ts);$end_m=(int)wp_date('H',$end_ts)*60+(int)wp_date('i',$end_ts);
              $top=max(0,$start_m-$grid_start);$height=max(42,$end_m-$start_m);$lane_count=max(1,(int)$item['lane_count']);$lane=(int)$item['lane'];$left=$lane*100/$lane_count;$width=100/$lane_count;
              $location_ids=bubbahub_myhub_planner_v2_region_ids((int)$item['group_id']);
            ?>
              <div class="bh-timetable-event bh-planner-filter-item" data-planner-location-ids="<?php echo esc_attr(implode(',',array_map('absint',(array)$location_ids))); ?>" style="top:<?php echo esc_attr($top); ?>px;height:<?php echo esc_attr($height); ?>px;left:<?php echo esc_attr($left); ?>%;width:<?php echo esc_attr($width); ?>%;">
                <a href="<?php echo esc_url($item['url']); ?>"><strong><?php echo esc_html($item['title']); ?></strong><?php if($item['label']): ?><span><?php echo esc_html($item['label']); ?></span><?php endif; ?><b><?php echo esc_html($item['start'].($item['end']?'–'.$item['end']:'')); ?></b><small><?php echo esc_html($item['venue']?:'Location to be confirmed'); ?></small></a>
                <a class="bh-planner-calendar-link" href="<?php echo esc_url($item['calendar_url']); ?>" title="Add this session to your calendar">+ Add to Calendar</a>
              </div>
            <?php endforeach; else: ?><div class="bh-timetable-empty">No groups</div><?php endif; ?>
          </div><?php endforeach; ?>
        </div>
      </div>

      <div class="bh-planner-mobile">
        <?php foreach($days as $day): ?><div class="bh-planner-mobile-day"><div class="bh-planner-mobile-day-heading"><span><strong><?php echo esc_html($day); ?></strong><small><?php echo esc_html(wp_date('j F',strtotime($week_dates[$day]))); ?></small></span><span class="bh-planner-mobile-count"><?php echo esc_html(count($week_by[$day])); ?></span></div>
          <div class="bh-planner-mobile-items"><?php if(!empty($week_by[$day])): foreach($week_by[$day] as $item): $location_ids=bubbahub_myhub_planner_v2_region_ids((int)$item['group_id']); ?>
            <div class="bh-planner-item-wrap bh-planner-filter-item" data-planner-location-ids="<?php echo esc_attr(implode(',',array_map('absint',(array)$location_ids))); ?>"><div class="bh-planner-item"><a class="bh-planner-listing-link" href="<?php echo esc_url($item['url']); ?>"><span class="bh-planner-thumb"><?php if($item['image']): ?><img src="<?php echo esc_url($item['image']); ?>" alt="" loading="lazy"><?php else: ?><span class="bh-planner-placeholder" aria-hidden="true">♡</span><?php endif; ?></span><span class="bh-planner-item-main"><strong><?php echo esc_html($item['title']); ?></strong><?php if($item['label']): ?><span class="bh-planner-session-label"><?php echo esc_html($item['label']); ?></span><?php endif; ?><span class="bh-planner-time"><?php echo esc_html($item['start'].($item['end']?'–'.$item['end']:'')); ?></span><span class="bh-planner-location">📍 <?php echo esc_html($item['venue']?:'Location to be confirmed'); ?></span></span></a><a class="bh-planner-mobile-calendar-link" href="<?php echo esc_url($item['calendar_url']); ?>">+ Add to Calendar</a></div></div>
          <?php endforeach; else: ?><div class="bh-planner-empty">No groups</div><?php endif; ?></div>
        </div><?php endforeach; ?>
      </div>

      <div class="bh-planner-print-footer"><img src="https://staging.bubbahub.co.uk/wp-content/uploads/2026/09/logobubbhub-removebg-preview-150x150.png" alt="Bubba Hub"><div><strong>Created by Bubba Hub SW</strong><br>www.bubbahub.co.uk<br>Printed: <?php echo esc_html( wp_date( 'j F Y' ) ); ?></div></div>

<div class="bh-planner-actions">
        <a class="bh-planner-subscribe-button" href="<?php echo esc_url( 'webcal://' . preg_replace( '#^https?://#', '', home_url('/?bubbahub_calendar=1') ) ); ?>">📅 Subscribe to Calendar</a>
        <button type="button" class="bh-planner-print-button" onclick="window.print()">🖨 Print Planner</button>
      </div>
<style>
.bh-planner-print-footer{display:none!important}
.bh-planner-search{margin:0 0 18px;padding:16px;border:1px solid #e6e6e6;border-radius:16px;background:#fff}
.bh-planner-search-row{display:flex;gap:12px;align-items:end}
.bh-planner-search-location{display:flex;flex-direction:column;gap:6px;max-width:420px}
.bh-planner-search-row label span{font-weight:700;font-size:13px}
.bh-planner-search-row select{min-height:44px;padding:10px 12px;border:1px solid #ddd;border-radius:10px;background:#fff}
.bh-planner-timetable-desktop{border:1px solid #d9d9d9;border-radius:12px;overflow:auto;background:#fff}
.bh-planner-calendar-toolbar{display:grid;grid-template-columns:auto auto 1fr auto;gap:10px;align-items:center;margin:0 0 14px}
.bh-planner-calendar-toolbar a{padding:8px 12px;border:1px solid #ddd;border-radius:9px;background:#fff;text-decoration:none!important;font-size:13px}
.bh-planner-calendar-toolbar strong{text-align:center;font-size:15px}
.bh-planner-today{font-weight:700}
.bh-planner-actions{display:flex;align-items:center;justify-content:center;gap:12px;margin:18px 0 0;padding:16px;border:1px solid #e6e6e6;border-radius:14px;background:#fff}.bh-planner-print-button,.bh-planner-subscribe-button{display:inline-flex;align-items:center;justify-content:center;padding:10px 16px;border:1px solid #ddd;border-radius:9px;background:#fff;font-size:14px;line-height:1.2;font-weight:700;cursor:pointer;text-decoration:none!important;white-space:nowrap;font-family:inherit}.bh-planner-search{margin:0 0 18px;padding:14px 16px;border:1px solid #e6e6e6;border-radius:14px;background:#fff}
.bh-planner-search-location{display:flex;flex-direction:column;gap:6px;max-width:420px}.bh-planner-search-row label span,.bh-planner-search-location>span{font-weight:700;font-size:13px}
.bh-planner-search-row select,.bh-planner-search select{min-height:42px;padding:9px 12px;border:1px solid #ddd;border-radius:9px;background:#fff}
.bh-planner-timetable-desktop{border:1px solid #d9d9d9;border-radius:12px;overflow:auto;background:#fff}
.bh-timetable-header{display:grid;grid-template-columns:58px repeat(7,minmax(120px,1fr));min-width:900px;background:#fafafa;border-bottom:1px solid #d9d9d9}
.bh-timetable-time-head{padding:9px 5px;font-size:10px;color:#777;border-right:1px solid #ddd;text-align:center}
.bh-timetable-day-head{padding:8px 5px;text-align:center;border-right:1px solid #ddd;display:flex;flex-direction:column;gap:2px}.bh-timetable-day-head strong{font-size:13px}.bh-timetable-day-head span{font-size:11px;color:#777}
.bh-timetable-body{display:grid;grid-template-columns:58px repeat(7,minmax(120px,1fr));min-width:900px;height:calc(var(--bh-grid-hours) * 60px);position:relative}
.bh-timetable-times{position:relative;border-right:1px solid #ddd;background:#fff}.bh-timetable-times span{position:absolute;left:0;right:0;transform:translateY(-7px);font-size:10px;color:#777;text-align:center}
.bh-timetable-column{position:relative;border-right:1px solid #ddd;background:repeating-linear-gradient(to bottom,transparent 0,transparent 59px,#e9e9e9 59px,#e9e9e9 60px)}
.bh-timetable-column-many{min-width:390px}
.bh-timetable-column-many .bh-timetable-event{padding:3px}
.bh-timetable-column-many .bh-timetable-event a{padding:8px 9px}
.bh-timetable-column-many .bh-timetable-event strong{font-size:12px;line-height:1.25}
.bh-timetable-column-many .bh-timetable-event span,.bh-timetable-column-many .bh-timetable-event b,.bh-timetable-column-many .bh-timetable-event small{font-size:10px}
.bh-timetable-column-many .bh-planner-calendar-link{font-size:9px!important;padding:5px 6px!important;flex:0 0 auto}
.bh-timetable-hour-line{position:absolute;left:0;right:0;border-top:1px solid #ececec;pointer-events:none}
.bh-timetable-event{position:absolute;box-sizing:border-box;padding:2px;border-radius:7px;overflow:hidden;min-width:0}.bh-timetable-event a{display:flex;flex-direction:column;height:100%;padding:7px 8px;background:#dceff1;border:1px solid #9bc8cc;border-radius:5px;color:inherit;text-decoration:none!important;overflow:hidden}
.bh-timetable-event strong{font-size:12px;line-height:1.2}.bh-timetable-event span{font-size:10px;line-height:1.2;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.bh-timetable-event b{font-size:10px;line-height:1.2;margin-top:3px}.bh-timetable-event small{font-size:9px;line-height:1.2;margin-top:auto;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.bh-planner-calendar-link{display:block!important;margin:3px 0 0!important;padding:4px 5px!important;border-radius:5px!important;background:#fff!important;border:1px solid #ddd!important;font-size:9px!important;line-height:1.1!important;text-align:center;text-decoration:none!important;color:inherit!important}
.bh-timetable-empty{padding:12px;text-align:center;color:#aaa;font-size:11px}
.bh-planner-mobile{display:none}.bh-weekly-planner-v2{padding-bottom:50px}.bh-planner-mobile-day{border:1px solid #e4e4e4;border-radius:12px;background:#fff;margin:0 0 9px;overflow:hidden}.bh-planner-mobile-day-heading{display:flex;align-items:center;justify-content:space-between;padding:13px 15px;border-bottom:1px solid #eee}.bh-planner-mobile-day-heading>span:first-child{display:flex;flex-direction:column;gap:2px}.bh-planner-mobile-day-heading strong{font-size:15px}.bh-planner-mobile-day-heading small{font-size:11px;color:#777}.bh-planner-mobile-count{min-width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#f1f1f1;font-size:11px;font-weight:700}.bh-planner-mobile-items{padding:10px;display:grid;gap:9px}.bh-planner-item-wrap{margin:0}.bh-planner-item{height:100%;border:1px solid #e7e7e7;border-radius:12px;background:#fff;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.04)}.bh-planner-mobile-calendar-link{display:block;margin:0 10px 10px;padding:7px 10px;border:1px solid #ddd;border-radius:8px;text-align:center;text-decoration:none!important;font-size:12px;font-weight:700}.bh-planner-listing-link{display:flex;align-items:center;gap:10px;padding:10px;text-decoration:none!important}.bh-planner-thumb{width:58px;height:58px;min-width:58px;border-radius:9px;overflow:hidden;display:flex;align-items:center;justify-content:center;background:#f3f3f3}.bh-planner-thumb img{width:100%;height:100%;object-fit:cover;display:block}.bh-planner-placeholder{font-size:22px;opacity:.45}.bh-planner-item-main{display:flex;flex-direction:column;min-width:0;line-height:1.3}.bh-planner-item-main strong{font-size:14px;line-height:1.25}.bh-planner-session-label{font-size:12px;opacity:.75;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.bh-planner-time{font-size:12px;font-weight:700;margin-top:2px}.bh-planner-location{font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
@media print{ @page{size:landscape;margin:8mm} body{margin:0!important;padding:0!important} body *{visibility:hidden!important}.bh-weekly-planner-v2{visibility:visible!important;position:absolute!important;left:0!important;top:0!important;width:100%!important;max-width:none!important;margin:0!important;padding:0!important;background:#fff!important;box-shadow:none!important}.bh-weekly-planner-v2 .bh-planner-timetable-desktop,.bh-weekly-planner-v2 .bh-planner-timetable-desktop *{visibility:visible!important}.bh-weekly-planner-v2>.bh-myhub-section-heading,.bh-planner-calendar-toolbar,.bh-planner-search,.bh-planner-actions,.bh-weekly-planner-preferences,.bh-planner-mobile{display:none!important}.bh-planner-timetable-desktop{display:block!important;width:100%!important;max-width:none!important;overflow:visible!important;border:1px solid #999!important}
     .bh-weekly-planner-v2 .bh-planner-print-footer{display:flex!important;visibility:visible!important;align-items:center;justify-content:center;gap:12px;margin:12px 0 0;padding:8px 0 0;border-top:1px solid #ddd;text-align:center;font-size:10px;line-height:1.35}
    .bh-weekly-planner-v2 .bh-planner-print-footer img{display:block;width:42px;height:42px;object-fit:contain}
.bh-timetable-body{min-width:0!important}.bh-timetable-header{min-width:0!important}.bh-timetable-event{break-inside:avoid}.bh-planner-mobile{display:none!important}}
/* Mobile calendar improvements */
@media(max-width:700px){
  .bh-weekly-planner-v2{padding-left:10px!important;padding-right:10px!important;overflow-x:hidden}
  .bh-planner-calendar-toolbar{display:grid!important;grid-template-columns:auto 1fr auto;gap:6px;align-items:center}
  .bh-planner-calendar-toolbar button{min-height:42px;padding:8px 10px!important;font-size:13px!important}
  .bh-planner-calendar-title{font-size:17px!important;text-align:center;line-height:1.2}
  .bh-planner-search{display:flex!important;flex-direction:column;gap:8px}
  .bh-planner-search input,.bh-planner-search select{width:100%!important;min-height:42px!important;font-size:16px!important;box-sizing:border-box}
  .bh-planner-mobile{display:block!important}
  .bh-planner-mobile-day{margin-bottom:10px!important;border-radius:12px!important;overflow:hidden}
  .bh-planner-mobile-day-header{padding:11px 12px!important;font-size:15px!important}
  .bh-planner-mobile-event{margin:8px!important;padding:10px!important;border-radius:10px!important}
  .bh-planner-mobile-event strong{font-size:14px!important;line-height:1.3!important;overflow-wrap:anywhere;word-break:break-word}
  .bh-planner-mobile-event,.bh-planner-mobile-event a{white-space:normal!important;overflow-wrap:anywhere;word-break:break-word}
  .bh-planner-mobile-event span,.bh-planner-mobile-event b,.bh-planner-mobile-event small{font-size:12px!important;line-height:1.35!important}
  .bh-planner-mobile-event a{display:block!important;min-height:42px;padding:10px!important;box-sizing:border-box}
  .bh-planner-actions{gap:8px!important;margin-top:14px!important;padding:12px!important}
  .bh-planner-subscribe-button,.bh-planner-print-button{min-height:44px!important;padding:10px 9px!important;font-size:13px!important;line-height:1.2!important}
  .bh-planner-print-footer{display:none!important}
  .bh-timetable{display:none!important}
}
@media(max-width:700px){.bh-planner-actions{flex-direction:row;flex-wrap:wrap}.bh-planner-subscribe-button,.bh-planner-print-button{flex:1 1 0;min-width:0}.bh-planner-calendar-toolbar{grid-template-columns:1fr 1fr;}.bh-planner-calendar-toolbar strong{grid-column:1/-1;grid-row:1;order:-1;margin-bottom:4px}.bh-planner-calendar-toolbar a{ text-align:center }.bh-planner-search-location{max-width:none}.bh-timetable-header,.bh-timetable-body{min-width:0}.bh-planner-timetable-desktop{display:none}.bh-planner-mobile{display:block}}
</style>
<script>
document.addEventListener('DOMContentLoaded',function(){
  document.querySelectorAll('.bh-weekly-planner-v2').forEach(function(planner){
    var location=planner.querySelector('.bh-planner-location-select'); if(!location)return;
    function applyRegion(){
      var loc=location.value||'';
      planner.querySelectorAll('.bh-planner-filter-item').forEach(function(item){
        var locs=(item.getAttribute('data-planner-location-ids')||'').split(',').filter(Boolean);
        var show=!loc||locs.indexOf(String(loc))!==-1;
        item.hidden=!show; item.style.display=show?'':'none';
      });
      planner.querySelectorAll('.bh-timetable-column').forEach(function(column){
        var events=Array.from(column.querySelectorAll('.bh-timetable-event'));
        var visible=events.filter(function(x){return !x.hidden});
        var empty=column.querySelector('.bh-timetable-live-empty');
        if(!empty){empty=document.createElement('div');empty.className='bh-timetable-empty bh-timetable-live-empty';empty.textContent='No groups';column.appendChild(empty)}
        empty.hidden=visible.length>0;
        if(!visible.length)return;

        // Re-pack the visible events after a location change so they use the
        // available cell width instead of retaining lanes from hidden groups.
        var lanes=[];
        visible.sort(function(a,b){return (parseFloat(a.style.top)||0)-(parseFloat(b.style.top)||0);});
        visible.forEach(function(item){
          var top=parseFloat(item.style.top)||0;
          var height=parseFloat(item.style.height)||0;
          var bottom=top+height;
          var lane=0;
          while(lanes[lane]!==undefined && lanes[lane]>top+0.5)lane++;
          lanes[lane]=bottom;
          item.dataset.liveLane=lane;
        });
        var laneCount=Math.max(1,lanes.length);
        visible.forEach(function(item){
          var lane=parseInt(item.dataset.liveLane||'0',10);
          item.style.left=(lane*100/laneCount)+'%';
          item.style.width=(100/laneCount)+'%';
        });
      });
    }
    location.addEventListener('change',applyRegion);applyRegion();
  });
});
</script>

    <?php return ob_get_clean();
}
