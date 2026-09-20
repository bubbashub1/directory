<?php
/**
 * BubbaHub My Hub – session-based weekly planner.
 * Reads the ACF Business & Class Hours > Weekly Schedule field.
 */
if ( ! defined( 'ABSPATH' ) ) exit;


function bubbahub_myhub_planner_v2_saved_calendars() {
    if ( ! is_user_logged_in() ) return array();
    $saved = get_user_meta( get_current_user_id(), 'bubbahub_saved_planner_calendars', true );
    return is_array( $saved ) ? $saved : array();
}

function bubbahub_myhub_planner_v2_sanitize_saved_value( $value ) {
    if ( is_array( $value ) ) {
        $clean = array();
        foreach ( $value as $key => $item ) {
            $clean[ sanitize_key( $key ) ] = bubbahub_myhub_planner_v2_sanitize_saved_value( $item );
        }
        return $clean;
    }
    return sanitize_text_field( wp_unslash( (string) $value ) );
}

function bubbahub_myhub_planner_v2_saved_params_from_request( $request ) {
    $allowed = array(
        'bh_search','bh_location','bh_region','bh_town','bh_category','bh_day',
        'bh_term_time','bh_age','bh_price','bh_radius','bh_lat','bh_lng','bh_acf'
    );
    $params = array();
    foreach ( $allowed as $key ) {
        if ( isset( $request[$key] ) && '' !== $request[$key] ) {
            $params[$key] = bubbahub_myhub_planner_v2_sanitize_saved_value( $request[$key] );
        }
    }
    return $params;
}

add_action( 'wp_ajax_bubbahub_save_custom_calendar', 'bubbahub_myhub_planner_v2_save_custom_calendar' );
function bubbahub_myhub_planner_v2_save_custom_calendar() {
    if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Please log in to save calendars.' ), 403 );
    check_ajax_referer( 'bubbahub_save_custom_calendar', 'nonce' );

    $name = isset( $_POST['calendar_name'] ) ? sanitize_text_field( wp_unslash( $_POST['calendar_name'] ) ) : '';
    $name = trim( $name );
    if ( '' === $name ) wp_send_json_error( array( 'message' => 'Please enter a name for this calendar.' ), 400 );
    if ( function_exists( 'mb_substr' ) ) $name = mb_substr( $name, 0, 80 );
    else $name = substr( $name, 0, 80 );

    $saved = bubbahub_myhub_planner_v2_saved_calendars();
    $edit_id = isset( $_POST['calendar_id'] ) ? sanitize_text_field( wp_unslash( $_POST['calendar_id'] ) ) : '';
    $view = isset( $_POST['bh_planner_mode'] ) ? sanitize_key( wp_unslash( $_POST['bh_planner_mode'] ) ) : 'week';
    $params = bubbahub_myhub_planner_v2_saved_params_from_request( $_POST );

    if ( $edit_id ) {
        $updated = false;
        foreach ( $saved as &$calendar ) {
            if ( ! empty( $calendar['id'] ) && hash_equals( (string) $calendar['id'], $edit_id ) ) {
                $calendar['name']   = $name;
                $calendar['view']   = $view;
                $calendar['params'] = $params;
                $calendar['saved']  = current_time( 'mysql' );
                $updated = true;
                break;
            }
        }
        unset( $calendar );
        if ( ! $updated ) wp_send_json_error( array( 'message' => 'That saved calendar could not be found.' ), 404 );
    } else {
        $saved[] = array(
            'id'     => wp_generate_uuid4(),
            'name'   => $name,
            'view'   => $view,
            'params' => $params,
            'saved'  => current_time( 'mysql' ),
        );
        if ( count( $saved ) > 20 ) $saved = array_slice( $saved, -20 );
    }

    update_user_meta( get_current_user_id(), 'bubbahub_saved_planner_calendars', $saved );

    wp_send_json_success( array( 'message' => $edit_id ? 'Calendar updated.' : 'Calendar saved.', 'id' => $edit_id ? $edit_id : end( $saved )['id'] ) );
}

add_action( 'wp_ajax_bubbahub_delete_custom_calendar', 'bubbahub_myhub_planner_v2_delete_custom_calendar' );
function bubbahub_myhub_planner_v2_delete_custom_calendar() {
    if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Please log in.' ), 403 );
    check_ajax_referer( 'bubbahub_save_custom_calendar', 'nonce' );

    $id = isset( $_POST['calendar_id'] ) ? sanitize_text_field( wp_unslash( $_POST['calendar_id'] ) ) : '';
    if ( '' === $id ) wp_send_json_error( array( 'message' => 'No calendar was selected.' ), 400 );

    $saved = bubbahub_myhub_planner_v2_saved_calendars();
    $kept = array();
    $found = false;
    foreach ( $saved as $calendar ) {
        if ( ! empty( $calendar['id'] ) && hash_equals( (string) $calendar['id'], $id ) ) {
            $found = true;
            continue;
        }
        $kept[] = $calendar;
    }
    if ( ! $found ) wp_send_json_error( array( 'message' => 'That saved calendar could not be found.' ), 404 );

    update_user_meta( get_current_user_id(), 'bubbahub_saved_planner_calendars', $kept );
    wp_send_json_success( array( 'message' => 'Calendar removed.' ) );
}

add_shortcode( 'bubbahub_weekly_planner_v2', 'bubbahub_myhub_weekly_planner_v2_shortcode' );

add_action( 'wp_ajax_bubbahub_calendar_filter', 'bubbahub_myhub_calendar_filter_ajax' );
function bubbahub_myhub_calendar_filter_ajax() {
    if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Please log in.' ), 403 );
    check_ajax_referer( 'bubbahub_calendar_filter', 'nonce' );

    $allowed = array(
        'bh_view','bh_planner_mode','bh_week','bh_search','bh_location','bh_region','bh_town','bh_edit_calendar',
        'bh_category','bh_day','bh_term_time','bh_age','bh_price','bh_radius','bh_lat','bh_lng','bh_acf'
    );
    $request = array();
    foreach ( $allowed as $key ) {
        if ( isset( $_POST[ $key ] ) ) {
            $request[ $key ] = is_array( $_POST[ $key ] )
                ? wp_unslash( $_POST[ $key ] )
                : sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
        }
    }
    $request['bh_view'] = 'planner';
    $request['bh_planner_mode'] = isset( $request['bh_planner_mode'] ) ? sanitize_key( $request['bh_planner_mode'] ) : 'week';

    $previous_get = $_GET;
    $_GET = $request;
    $html = bubbahub_myhub_weekly_planner_v2_shortcode();
    $_GET = $previous_get;

    wp_send_json_success( array( 'html' => $html ) );
}

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

function bubbahub_myhub_planner_v2_calendar_preferences() {
    if ( ! is_user_logged_in() ) return array(
        'view' => 'week',
        'hidden_days' => array(),
        'time_of_day' => array(),
        'price_filter' => 'all',
        'use_nap_schedule' => false,
    );

    $uid = get_current_user_id();
    $hidden = get_user_meta( $uid, 'bubbahub_calendar_hidden_days', true );
    $times = get_user_meta( $uid, 'bubbahub_calendar_time_of_day', true );
    $hidden = is_array($hidden) ? $hidden : array();
    $times = is_array($times) ? $times : array();

    $allowed_days = array('monday','tuesday','wednesday','thursday','friday','saturday','sunday');
    $allowed_times = array('morning','afternoon','evening');
    $hidden = array_values(array_intersect(array_map('sanitize_key',$hidden),$allowed_days));
    $times = array_values(array_intersect(array_map('sanitize_key',$times),$allowed_times));

    $view = sanitize_key((string)get_user_meta($uid,'bubbahub_calendar_default_view',true));
    if ( ! in_array($view,array('list','today','week','month'),true) ) $view='week';

    $price = sanitize_key((string)get_user_meta($uid,'bubbahub_calendar_price_filter',true));
    if ( ! in_array($price,array('all','free','paid'),true) ) $price='all';

    return array(
        'view'=>$view,
        'hidden_days'=>$hidden,
        'time_of_day'=>$times,
        'price_filter'=>$price,
        'use_nap_schedule'=>'1' === (string)get_user_meta($uid,'bubbahub_calendar_use_nap_schedule',true),
    );
}

function bubbahub_myhub_planner_v2_time_bucket( $time ) {
    if ( ! $time ) return '';
    $time = preg_replace('/[^0-9:]/','',str_replace('.',':',(string)$time));
    if ( ! preg_match('/^(\d{1,2})(?::(\d{2}))?/',$time,$m) ) return '';
    $minutes = ((int)$m[1]*60) + ( isset($m[2]) ? (int)$m[2] : 0 );
    if ( $minutes < 720 ) return 'morning';
    if ( $minutes < 1020 ) return 'afternoon';
    return 'evening';
}

function bubbahub_myhub_planner_v2_is_group_free( $group_id ) {
    $value = function_exists('bubbahub_directory_get_field') ? bubbahub_directory_get_field($group_id,'isFree','') : get_post_meta($group_id,'isFree',true);
    if ( function_exists('get_field') ) {
        $acf = get_field('isFree',$group_id,false);
        if ( null !== $acf && false !== $acf && '' !== $acf ) $value = $acf;
    }
    if ( is_array($value) ) $value = reset($value);
    if ( is_bool($value) ) return $value;
    if ( is_numeric($value) ) return (bool)$value;
    return in_array(strtolower(trim((string)$value)),array('1','true','yes','on','free'),true);
}

function bubbahub_myhub_planner_v2_child_nap_ranges() {
    if ( ! is_user_logged_in() ) return array();
    $days = array('Monday'=>'Mon','Tuesday'=>'Tue','Wednesday'=>'Wed','Thursday'=>'Thu','Friday'=>'Fri','Saturday'=>'Sat','Sunday'=>'Sun');
    $ranges = array();
    $children = get_posts(array('post_type'=>'bh_child','post_status'=>'publish','author'=>get_current_user_id(),'posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true));
    foreach($children as $child_id){
        $value = function_exists('get_field') ? get_field('nap_schedule',$child_id,false) : get_post_meta($child_id,'nap_schedule',true);
        if(!is_array($value)) continue;
        foreach($days as $day=>$short){
            if(empty($value[$short]) || !is_array($value[$short])) continue;
            $row=$value[$short];
            if(empty($row['enabled']) || empty($row['start']) || empty($row['end'])) continue;
            $start=preg_replace('/[^0-9:]/','',(string)$row['start']);
            $end=preg_replace('/[^0-9:]/','',(string)$row['end']);
            if($start && $end) $ranges[$day][]=array('start'=>substr($start,0,5),'end'=>substr($end,0,5));
        }
    }
    return $ranges;
}

function bubbahub_myhub_planner_v2_event_has_nap_overlap( $row, $nap_ranges ) {
    $day = wp_date('l',strtotime($row['date']));
    if(empty($nap_ranges[$day])) return false;
    $start=strtotime($row['date'].' '.$row['start']);
    $end=$row['end']?strtotime($row['date'].' '.$row['end']):$start+HOUR_IN_SECONDS;
    foreach($nap_ranges[$day] as $nap){
        $ns=strtotime($row['date'].' '.$nap['start']);
        $ne=strtotime($row['date'].' '.$nap['end']);
        if($start<$ne && $end>$ns) return true;
    }
    return false;
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

    $saved_calendars = bubbahub_myhub_planner_v2_saved_calendars();
    $saved_calendar_id = isset( $_GET['bh_saved_calendar'] ) ? sanitize_text_field( wp_unslash( $_GET['bh_saved_calendar'] ) ) : '';
    $edit_calendar_id = isset( $_GET['bh_edit_calendar'] ) ? sanitize_text_field( wp_unslash( $_GET['bh_edit_calendar'] ) ) : '';
    $edit_calendar_name = '';
    if ( $edit_calendar_id ) {
        foreach ( $saved_calendars as $saved_calendar ) {
            if ( ! empty( $saved_calendar['id'] ) && hash_equals( (string) $saved_calendar['id'], $edit_calendar_id ) ) {
                $edit_calendar_name = ! empty( $saved_calendar['name'] ) ? $saved_calendar['name'] : '';
                break;
            }
        }
    }
    if ( $saved_calendar_id ) {
        foreach ( $saved_calendars as $saved_calendar ) {
            if ( ! empty( $saved_calendar['id'] ) && hash_equals( (string) $saved_calendar['id'], $saved_calendar_id ) ) {
                foreach ( array('bh_search','bh_location','bh_region','bh_town','bh_category','bh_day','bh_term_time','bh_age','bh_price','bh_radius','bh_lat','bh_lng','bh_acf') as $saved_key ) unset( $_GET[$saved_key] );
                if ( ! empty( $saved_calendar['params'] ) && is_array( $saved_calendar['params'] ) ) {
                    foreach ( $saved_calendar['params'] as $key => $value ) $_GET[ $key ] = $value;
                }
                if ( ! empty( $saved_calendar['view'] ) ) $_GET['bh_planner_mode'] = sanitize_key( $saved_calendar['view'] );
                break;
            }
        }
    }

    $calendar_preferences = bubbahub_myhub_planner_v2_calendar_preferences();
    $view = isset($_GET['bh_planner_mode']) ? sanitize_key(wp_unslash($_GET['bh_planner_mode'])) : ( isset($_GET['bh_view']) ? sanitize_key(wp_unslash($_GET['bh_view'])) : $calendar_preferences['view'] );
    if ( ! in_array($view,array('list','today','week','month'),true) ) $view=$calendar_preferences['view'];
    $requested = isset($_GET['bh_week']) ? sanitize_text_field(wp_unslash($_GET['bh_week'])) : '';
    $requested_date = $requested ?: wp_date('Y-m-d',current_time('timestamp'));
    $week_start = bubbahub_myhub_planner_v2_get_week_start($requested_date);
    $week_start_ts = strtotime($week_start);
    $week_end = wp_date('Y-m-d',strtotime('+6 days',$week_start_ts));
    $today_date = wp_date('Y-m-d',current_time('timestamp'));
    $view_start=$week_start; $view_end=$week_end;
    if('today'===$view){$view_start=$requested_date;$view_end=$requested_date;}
    elseif('month'===$view){$month_tmp=strtotime(wp_date('Y-m-01',strtotime($requested_date)));$view_start=bubbahub_myhub_planner_v2_get_week_start(wp_date('Y-m-d',$month_tmp));$view_end=wp_date('Y-m-d',strtotime('+6 days',strtotime(bubbahub_myhub_planner_v2_get_week_start(wp_date('Y-m-t',$month_tmp)))));}
    $rows = bubbahub_myhub_planner_v2_schedule_occurrences($view_start,$view_end);

    // Use the same Advanced Search filter set as the Directory so the two
    // views stay in sync when families move between Directory and Calendar.
    $calendar_filters = function_exists('bubbahub_advanced_search_filters_from_request')
        ? bubbahub_advanced_search_filters_from_request($_GET)
        : array('search'=>'','region'=>'','town'=>'','age'=>array(),'price'=>'','location'=>'','category'=>'','day'=>'','term_time'=>'','acf'=>array(),'lat'=>0,'lng'=>0,'radius'=>25,'paged'=>1);

    $calendar_filter_active = ! empty($calendar_filters['search'])
        || ! empty($calendar_filters['region'])
        || ! empty($calendar_filters['town'])
        || ! empty($calendar_filters['age'])
        || ! empty($calendar_filters['price'])
        || ! empty($calendar_filters['location'])
        || ! empty($calendar_filters['category'])
        || ! empty($calendar_filters['day'])
        || ! empty($calendar_filters['term_time'])
        || ! empty($calendar_filters['acf'])
        || ( ! empty($calendar_filters['lat']) && ! empty($calendar_filters['lng']) );

    /*
     * Apply saved Calendar Settings as defaults. Explicit Advanced Search
     * price filters still take precedence over the saved Free/Paid preference.
     */
    $saved_price_filter = $calendar_preferences['price_filter'];
    if ( empty($calendar_filters['price']) && 'all' !== $saved_price_filter ) {
        $rows = array_values(array_filter($rows,function($row) use ($saved_price_filter) {
            $is_free = bubbahub_myhub_planner_v2_is_group_free((int)$row['group_id']);
            return 'free' === $saved_price_filter ? $is_free : ! $is_free;
        }));
    }

    if ( ! empty($calendar_preferences['time_of_day']) ) {
        $rows = array_values(array_filter($rows,function($row) use ($calendar_preferences) {
            return in_array(bubbahub_myhub_planner_v2_time_bucket($row['start']),$calendar_preferences['time_of_day'],true);
        }));
    }

    if ( ! empty($calendar_preferences['hidden_days']) ) {
        $rows = array_values(array_filter($rows,function($row) use ($calendar_preferences) {
            $day = strtolower(wp_date('l',strtotime($row['date'])));
            return ! in_array($day,$calendar_preferences['hidden_days'],true);
        }));
    }

    $nap_ranges = $calendar_preferences['use_nap_schedule'] ? bubbahub_myhub_planner_v2_child_nap_ranges() : array();
    foreach($rows as &$calendar_row) $calendar_row['nap_conflict'] = !empty($nap_ranges) ? bubbahub_myhub_planner_v2_event_has_nap_overlap($calendar_row,$nap_ranges) : false;
    unset($calendar_row);

    if ( $calendar_filter_active && function_exists('bubbahub_advanced_search_query') ) {
        $calendar_query = bubbahub_advanced_search_query($calendar_filters, 250);
        $calendar_ids = $calendar_query instanceof WP_Query ? array_map('absint', wp_list_pluck((array) $calendar_query->posts, 'ID')) : array();

        $rows = array_values(array_filter($rows, function($row) use ($calendar_ids, $calendar_filters) {
            if ( ! in_array(absint($row['group_id'] ?? 0), $calendar_ids, true) ) return false;

            if ( ! empty($calendar_filters['day']) ) {
                $row_day = strtolower(wp_date('l', strtotime($row['date'] ?? '')));
                if ( $row_day !== strtolower($calendar_filters['day']) ) return false;
            }

            if ( ! empty($calendar_filters['term_time']) ) {
                $is_term = ! empty($row['term_time_only']);
                if ( 'yes' === $calendar_filters['term_time'] && ! $is_term ) return false;
                if ( 'no' === $calendar_filters['term_time'] && $is_term ) return false;
            }

            return true;
        }));
    }

    $days=array('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday');
    $week_dates=array();
    foreach($days as $i=>$day) $week_dates[$day]=wp_date('Y-m-d',strtotime('+'.$i.' days',$week_start_ts));

    $display_days = ('today' === $view) ? array( wp_date('l', strtotime($requested_date)) ) : $days;
    if ( 'today' !== $view && ! empty($calendar_preferences['hidden_days']) ) {
        $display_days = array_values(array_filter($display_days,function($day) use ($calendar_preferences) {
            return ! in_array(strtolower($day),$calendar_preferences['hidden_days'],true);
        }));
    }
    $display_dates = array();
    foreach($display_days as $i=>$day) {
        $display_dates[$day] = ('today' === $view)
            ? $requested_date
            : $week_dates[$day];
    }

    /* Add calendar links before any alternate view consumes the rows. */
    foreach($rows as &$calendar_row) {
        $calendar_row['calendar_url'] = add_query_arg(
            array(
                'bubbahub_calendar_event'=>1,
                'group'=>(int)$calendar_row['group_id'],
                'date'=>$calendar_row['date'],
                'start'=>$calendar_row['start'],
                'end'=>$calendar_row['end']
            ),
            home_url('/')
        );
    }
    unset($calendar_row);

    $week_by=array_fill_keys($days,array());
    foreach($rows as $row) {
        foreach($display_dates as $day=>$date) {
            if($row['date']===$date) {
                $week_by[$day][]=$row;
                break;
            }
        }
    }

    $min_minutes=7*60;$max_minutes=22*60;
    foreach($week_by as $entries) foreach($entries as $item){
        $st=strtotime($item['date'].' '.$item['start']);
        $en=$item['end']?strtotime($item['date'].' '.$item['end']):$st+3600;
        if($st)$min_minutes=min($min_minutes,(int)wp_date('H',$st)*60+(int)wp_date('i',$st));
        if($en)$max_minutes=max($max_minutes,(int)wp_date('H',$en)*60+(int)wp_date('i',$en));
    }
    $grid_start=7*60;
    $grid_end=22*60;
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

    $planner_base_url = remove_query_arg( array( 'bh_view', 'bh_week', 'bh_planner_mode' ), get_permalink() );
    if ( ! $planner_base_url ) $planner_base_url = home_url( '/my-hub/' );

    // Keep all shared search parameters on Calendar navigation.
    $calendar_query_args = array(
        'bh_search'     => ! empty($calendar_filters['search']) ? $calendar_filters['search'] : false,
        'bh_location'   => ! empty($calendar_filters['location']) ? $calendar_filters['location'] : false,
        'bh_region'     => ! empty($calendar_filters['region']) ? $calendar_filters['region'] : false,
        'bh_town'       => ! empty($calendar_filters['town']) ? $calendar_filters['town'] : false,
        'bh_category'   => ! empty($calendar_filters['category']) ? $calendar_filters['category'] : false,
        'bh_day'        => ! empty($calendar_filters['day']) ? $calendar_filters['day'] : false,
        'bh_term_time'  => ! empty($calendar_filters['term_time']) ? $calendar_filters['term_time'] : false,
        'bh_age'        => ! empty($calendar_filters['age']) ? $calendar_filters['age'] : false,
        'bh_price'      => ! empty($calendar_filters['price']) ? $calendar_filters['price'] : false,
        'bh_radius'     => ! empty($calendar_filters['radius']) ? $calendar_filters['radius'] : false,
        'bh_lat'        => ! empty($calendar_filters['lat']) ? $calendar_filters['lat'] : false,
        'bh_lng'        => ! empty($calendar_filters['lng']) ? $calendar_filters['lng'] : false,
        'bh_acf'        => ! empty($calendar_filters['acf']) ? $calendar_filters['acf'] : false,
    );
    $planner_base_url = add_query_arg($calendar_query_args, $planner_base_url);

    $prev_date='month'===$view?wp_date('Y-m-d',strtotime('-1 month',strtotime($requested_date))):wp_date('Y-m-d',strtotime('-7 days',strtotime($requested_date)));
    $next_date='month'===$view?wp_date('Y-m-d',strtotime('+1 month',strtotime($requested_date))):wp_date('Y-m-d',strtotime('+7 days',strtotime($requested_date)));
    $prev=add_query_arg(array('bh_week'=>$prev_date,'bh_view'=>'planner','bh_planner_mode'=>$view),$planner_base_url);
    $next=add_query_arg(array('bh_week'=>$next_date,'bh_view'=>'planner','bh_planner_mode'=>$view),$planner_base_url);
    $today=add_query_arg(array('bh_week'=>$today_date,'bh_view'=>'planner','bh_planner_mode'=>'today'),$planner_base_url);
    $view_urls=array();foreach(array('list','today','week','month') as $v)$view_urls[$v]=add_query_arg(array('bh_week'=>$requested_date,'bh_view'=>'planner','bh_planner_mode'=>$v),$planner_base_url);
    $saved_view_urls = array();
    foreach ( $saved_calendars as $saved_calendar ) {
        if ( empty($saved_calendar['id']) || empty($saved_calendar['name']) ) continue;
        $saved_view_urls[ $saved_calendar['id'] ] = add_query_arg( 'bh_saved_calendar', sanitize_text_field($saved_calendar['id']), $planner_base_url );
    }
    $month_ts=strtotime(wp_date('Y-m-01',strtotime($requested_date)));$month_label=wp_date('F Y',$month_ts);
    $list_rows=$rows;usort($list_rows,function($a,$b){return ($a['date'].' '.$a['start'])<=>($b['date'].' '.$b['start']);});

    ob_start(); ?>
    <section class="bh-myhub-section bh-weekly-planner bh-weekly-planner-v2" data-calendar-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-calendar-nonce="<?php echo esc_attr( wp_create_nonce( 'bubbahub_calendar_filter' ) ); ?>" data-save-nonce="<?php echo esc_attr( wp_create_nonce( 'bubbahub_save_custom_calendar' ) ); ?>" data-edit-calendar="<?php echo esc_attr($edit_calendar_id); ?>">
      <div class="bh-myhub-section-heading">
        <div><div class="bh-myhub-kicker">YOUR CALENDAR</div><h2>Calendar</h2></div>
        <a class="bh-weekly-planner-preferences" href="<?php echo esc_url( add_query_arg( array( 'bh_account_settings' => '1', 'bh_settings_section' => 'calendar' ), get_permalink() ) ); ?>">Change Calendar Preferences →</a>
      </div>

      <form class="bh-calendar-search-form" method="get">
        <input type="hidden" name="bh_view" value="planner">
        <input type="hidden" name="bh_planner_mode" value="<?php echo esc_attr($view); ?>">
        <input type="hidden" name="bh_week" value="<?php echo esc_attr($requested_date); ?>">
        <div class="bh-calendar-search-main">
          <label class="screen-reader-text" for="bh-calendar-search">Search groups</label>
          <input id="bh-calendar-search" name="bh_search" type="search" value="<?php echo esc_attr($calendar_filters['search']); ?>" placeholder="Search by group, class or area">
        </div>
        <div class="bh-calendar-search-location">
          <label class="screen-reader-text" for="bh-calendar-location">Location</label>
          <div class="bh-calendar-location-control">
            <input id="bh-calendar-location" name="bh_location" type="search" value="<?php echo esc_attr($calendar_filters['location']); ?>" placeholder="Town, postcode or area">
            <button type="button" class="bh-calendar-use-location" aria-label="Use my location" title="Use my location">⌖</button>
          </div>
        </div>
        <div class="bh-calendar-search-actions">
          <button type="submit" class="bh-calendar-search-button">Search</button>
          <button type="button" class="bh-calendar-advanced-toggle" aria-expanded="false">Advanced search <span aria-hidden="true">⌄</span></button>
          <button type="button" class="bh-calendar-save-button" data-save-calendar><?php echo $edit_calendar_id ? 'Edit calendar' : 'Save calendar'; ?></button>
        </div>
        <div class="bh-calendar-save-panel" data-save-panel hidden>
          <label for="bh-calendar-save-name"><?php echo $edit_calendar_id ? 'Edit custom calendar name' : 'Name this custom calendar'; ?></label>
          <div><input id="bh-calendar-save-name" type="text" maxlength="80" value="<?php echo esc_attr($edit_calendar_name); ?>" placeholder="e.g. Baby groups near Exeter"><button type="button" data-confirm-save><?php echo $edit_calendar_id ? 'Update' : 'Save'; ?></button><button type="button" data-cancel-save>Cancel</button></div>
        </div>
        <div class="bh-calendar-advanced-search" hidden aria-hidden="true">
          <?php
          $calendar_regions = get_terms(array('taxonomy'=>'region','hide_empty'=>false,'parent'=>0));
          $calendar_towns = get_terms(array('taxonomy'=>'region','hide_empty'=>false));
          $calendar_cat_tax = function_exists('bubbahub_advanced_search_category_tax') ? bubbahub_advanced_search_category_tax() : '';
          $calendar_categories = $calendar_cat_tax ? get_terms(array('taxonomy'=>$calendar_cat_tax,'hide_empty'=>false)) : array();
          $calendar_acf = function_exists('bubbahub_advanced_search_acf_fields') ? bubbahub_advanced_search_acf_fields() : array();
          $calendar_days = array('monday'=>'Monday','tuesday'=>'Tuesday','wednesday'=>'Wednesday','thursday'=>'Thursday','friday'=>'Friday','saturday'=>'Saturday','sunday'=>'Sunday');
          ?>
          <div class="bh-calendar-filter-option"><label for="bh-calendar-region">Region</label><select id="bh-calendar-region" name="bh_region"><option value="">All regions</option><?php if(!is_wp_error($calendar_regions)) foreach($calendar_regions as $t): ?><option value="<?php echo esc_attr($t->slug); ?>" data-term-id="<?php echo esc_attr((int)$t->term_id); ?>" <?php selected($calendar_filters['region'],$t->slug); ?>><?php echo esc_html($t->name); ?></option><?php endforeach; ?></select></div>
          <div class="bh-calendar-filter-option"><label for="bh-calendar-town">Town</label><select id="bh-calendar-town" name="bh_town"><option value="">All towns</option><?php if(!is_wp_error($calendar_towns)) foreach($calendar_towns as $t): if((int)$t->parent<=0) continue; ?><option value="<?php echo esc_attr($t->slug); ?>" data-parent-id="<?php echo esc_attr((int)$t->parent); ?>" <?php selected($calendar_filters['town']??'',$t->slug); ?>><?php echo esc_html($t->name); ?></option><?php endforeach; ?></select></div>
          <div class="bh-calendar-filter-option"><label for="bh-calendar-category">Category</label><select id="bh-calendar-category" name="bh_category"><option value="">All categories</option><?php foreach((array)$calendar_categories as $t): ?><option value="<?php echo esc_attr($t->slug); ?>" <?php selected($calendar_filters['category'],$t->slug); ?>><?php echo esc_html($t->name); ?></option><?php endforeach; ?></select></div>
          <div class="bh-calendar-filter-option"><label for="bh-calendar-day">Day</label><select id="bh-calendar-day" name="bh_day"><option value="">Any day</option><?php foreach($calendar_days as $v=>$label): ?><option value="<?php echo esc_attr($v); ?>" <?php selected($calendar_filters['day'],$v); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></div>
          <div class="bh-calendar-filter-option"><label for="bh-calendar-term">Term Time</label><select id="bh-calendar-term" name="bh_term_time"><option value="">Any term</option><option value="yes" <?php selected($calendar_filters['term_time'],'yes'); ?>>Term time only</option><option value="no" <?php selected($calendar_filters['term_time'],'no'); ?>>Not term time only</option></select></div>
          <div class="bh-calendar-filter-option"><label for="bh-calendar-age">Age Range</label><select id="bh-calendar-age" name="bh_age"><option value="">All ages</option><?php if(function_exists('bubbahub_directory_age_values')) foreach((array)bubbahub_directory_age_values() as $v): ?><option value="<?php echo esc_attr($v); ?>" <?php selected($calendar_filters['age'],$v); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?></select></div>
          <div class="bh-calendar-filter-option"><label for="bh-calendar-price">Price</label><select id="bh-calendar-price" name="bh_price"><option value="">Any price</option><option value="free" <?php selected($calendar_filters['price'],'free'); ?>>Free</option><option value="paid" <?php selected($calendar_filters['price'],'paid'); ?>>Paid</option></select></div>
          <?php foreach((array)$calendar_acf as $name=>$field): $choices=$field['choices']??array(); if('true_false'===$field['type']) $choices=array('1'=>'Yes','0'=>'No'); ?>
            <div class="bh-calendar-filter-option"><label for="bh-calendar-acf-<?php echo esc_attr($name); ?>"><?php echo esc_html($field['label']?:ucwords(str_replace('_',' ',$name))); ?></label><select id="bh-calendar-acf-<?php echo esc_attr($name); ?>" name="bh_acf[<?php echo esc_attr($name); ?>]"><option value="">Any</option><?php foreach((array)$choices as $v=>$label): ?><option value="<?php echo esc_attr($v); ?>" <?php selected($calendar_filters['acf'][$name]??'',$v); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></div>
          <?php endforeach; ?>
          <div class="bh-calendar-filter-option"><label for="bh-calendar-radius">Search radius</label><select id="bh-calendar-radius" name="bh_radius"><option value="5" <?php selected((string)$calendar_filters['radius'],'5'); ?>>5 miles</option><option value="10" <?php selected((string)$calendar_filters['radius'],'10'); ?>>10 miles</option><option value="25" <?php selected((string)$calendar_filters['radius'],'25'); ?>>25 miles</option><option value="50" <?php selected((string)$calendar_filters['radius'],'50'); ?>>50 miles</option></select></div>
        </div>
        <input type="hidden" name="bh_lat" value="<?php echo esc_attr($calendar_filters['lat']); ?>">
        <input type="hidden" name="bh_lng" value="<?php echo esc_attr($calendar_filters['lng']); ?>">
      </form>

      <div class="bh-planner-calendar-toolbar">
        <div class="bh-planner-view-switcher">
          <?php foreach(array('list'=>'List','today'=>'Today','week'=>'Week','month'=>'Monthly') as $v=>$label): ?><a class="bh-planner-view-link<?php echo $view===$v?' is-active':''; ?>" href="<?php echo esc_url($view_urls[$v]); ?>"><?php echo esc_html($label); ?></a><?php endforeach; ?>
        </div>
        <label class="bh-planner-mobile-view-select">
          <span class="screen-reader-text">Calendar view</span>
          <select aria-label="Calendar view" data-planner-view-select>
            <?php foreach(array('list'=>'List','today'=>'Today','week'=>'Week','month'=>'Monthly') as $v=>$label): ?><option value="<?php echo esc_url($view_urls[$v]); ?>" <?php selected($view,$v); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
            
          </select>
        </label>
        <div class="bh-planner-calendar-nav">
          <a href="<?php echo esc_url($prev); ?>">← Previous</a>
          <strong><?php echo esc_html('month'===$view?$month_label:('today'===$view?wp_date('l, j F Y',strtotime($requested_date)):wp_date('j M',strtotime($week_dates['Monday'])).' – '.wp_date('j M Y',strtotime($week_dates['Sunday'])))); ?></strong>
          <a href="<?php echo esc_url($next); ?>">Next →</a>
        </div>
      </div>

      <?php if ( ! empty( $saved_calendars ) ) : ?>
      <section class="bh-saved-calendars" aria-label="Your custom calendars">
        <div class="bh-saved-calendars-heading">
          <div>
            <span class="bh-saved-calendars-kicker">MY HUB</span>
            <h3>Your custom calendars</h3>
          </div>
          <div class="bh-saved-calendars-controls" aria-label="Custom calendar carousel controls">
            <button type="button" class="bh-saved-calendar-scroll" data-scroll-saved-calendars="-1" aria-label="Previous custom calendar">‹</button>
            <button type="button" class="bh-saved-calendar-scroll" data-scroll-saved-calendars="1" aria-label="Next custom calendar">›</button>
          </div>
        </div>
        <div class="bh-saved-calendars-track" data-saved-calendars-track tabindex="0">
          <?php foreach ( $saved_calendars as $saved_calendar ) :
              if ( empty( $saved_calendar['id'] ) || empty( $saved_calendar['name'] ) || empty( $saved_view_urls[ $saved_calendar['id'] ] ) ) continue;
              $saved_id = sanitize_text_field( $saved_calendar['id'] );
              $saved_name = sanitize_text_field( $saved_calendar['name'] );
              $saved_edit_url = add_query_arg( 'bh_edit_calendar', $saved_id, $planner_base_url );
              $saved_is_active = ! empty( $saved_calendar_id ) && hash_equals( $saved_id, $saved_calendar_id );
          ?>
            <article class="bh-saved-calendar-card<?php echo $saved_is_active ? ' is-active' : ''; ?>" data-calendar-id="<?php echo esc_attr( $saved_id ); ?>">
              <div class="bh-saved-calendar-card-top">
                <span class="bh-saved-calendar-icon" aria-hidden="true">★</span>
                <span class="bh-saved-calendar-type">Custom calendar</span>
              </div>
              <h4><?php echo esc_html( $saved_name ); ?></h4>
              <div class="bh-saved-calendar-meta"><?php echo esc_html( ! empty( $saved_calendar['view'] ) ? ucfirst( $saved_calendar['view'] ) . ' view' : 'Calendar view' ); ?></div>
              <div class="bh-saved-calendar-actions">
                <a class="bh-saved-calendar-open" href="<?php echo esc_url( $saved_view_urls[ $saved_id ] ); ?>">Open calendar</a>
                <a class="bh-saved-calendar-edit" href="<?php echo esc_url( $saved_edit_url ); ?>">Edit</a>
                <button type="button" class="bh-saved-calendar-delete" data-delete-saved-calendar data-calendar-id="<?php echo esc_attr( $saved_id ); ?>">Remove</button>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <div class="bh-planner-print-header"><img src="https://staging.bubbahub.co.uk/wp-content/uploads/2026/09/logobubbhub-removebg-preview-150x150.png" alt="Bubba Hub"><div><strong>Bubba Hub</strong><span>Created by Bubba Hub SW</span><span>www.bubbahub.co.uk</span><span>Printed: <?php echo esc_html( wp_date( 'j F Y' ) ); ?></span></div></div>

      <?php if(in_array($view,array('week','today'),true)): ?>
      <div class="bh-planner-timetable-desktop bh-planner-days-<?php echo esc_attr(count($display_days)); ?>">
        <div class="bh-timetable-header"><div class="bh-timetable-time-head">TIME</div>
          <?php foreach($display_days as $day): ?><div class="bh-timetable-day-head<?php echo $display_dates[$day] === wp_date('Y-m-d', current_time('timestamp' )) ? ' bh-timetable-day-head-today' : ''; ?>"><strong><?php echo esc_html(substr($day,0,3)); ?></strong><span><?php echo esc_html(wp_date('j',strtotime($display_dates[$day]))); ?></span></div><?php endforeach; ?>
        </div>
        <div class="bh-timetable-body" style="--bh-grid-hours:<?php echo esc_attr($grid_hours); ?>;--bh-hour-px:60px;">
          <div class="bh-timetable-times"><?php for($h=$grid_start;$h<=$grid_end;$h+=60): ?><span style="top:calc(<?php echo esc_attr(($h-$grid_start)/60); ?> * var(--bh-hour-px));"><?php echo esc_html(sprintf('%02d:00',floor($h/60))); ?></span><?php endfor; ?></div>
          <?php foreach($display_days as $day): $day_lane_count=0; foreach($week_by[$day] as $day_item) $day_lane_count=max($day_lane_count,(int)($day_item['lane_count']??1)); ?><div class="bh-timetable-column<?php echo $day_lane_count>2?' bh-timetable-column-many':''; ?>" data-day="<?php echo esc_attr($day); ?>" data-lane-count="<?php echo esc_attr($day_lane_count); ?>">
            <?php for($h=$grid_start;$h<$grid_end;$h+=60): ?><div class="bh-timetable-hour-line" style="top:calc(<?php echo esc_attr(($h-$grid_start)/60); ?> * var(--bh-hour-px));"></div><?php endfor; ?>
            <?php if(!empty($week_by[$day])): foreach($week_by[$day] as $item):
              $start_ts=strtotime($item['date'].' '.$item['start']);$end_ts=$item['end']?strtotime($item['date'].' '.$item['end']):$start_ts+3600;if($end_ts<$start_ts)$end_ts=strtotime('+1 day',$end_ts);
              $start_m=(int)wp_date('H',$start_ts)*60+(int)wp_date('i',$start_ts);$end_m=(int)wp_date('H',$end_ts)*60+(int)wp_date('i',$end_ts);
              $top=max(0,$start_m-$grid_start);$height=max(42,$end_m-$start_m);$lane_count=1;$lane=0;$left=0;$width=100;
              $location_ids=bubbahub_myhub_planner_v2_region_ids((int)$item['group_id']);
              $category_terms = array();
              foreach ( array('category','group_category','listing_category') as $tax ) {
                  if ( taxonomy_exists($tax) ) {
                      $terms = wp_get_post_terms((int)$item['group_id'],$tax,array('fields'=>'names'));
                      if ( ! is_wp_error($terms) && ! empty($terms) ) { $category_terms = $terms; break; }
                  }
              }
              $category_key = sanitize_title( !empty($category_terms) ? $category_terms[0] : 'other' );
            ?>
              <div class="bh-timetable-event bh-planner-filter-item bh-category-<?php echo esc_attr($category_key); ?>" data-planner-category="<?php echo esc_attr($category_key); ?>" data-planner-location-ids="<?php echo esc_attr(implode(',',array_map('absint',(array)$location_ids))); ?>" style="top:calc(<?php echo esc_attr($top); ?> * var(--bh-hour-px) / 60);height:calc(<?php echo esc_attr($height); ?> * var(--bh-hour-px) / 60);left:<?php echo esc_attr($left); ?>%;width:<?php echo esc_attr($width); ?>%;">
                <a href="<?php echo esc_url($item['url']); ?>"><strong><?php echo esc_html($item['title']); ?></strong><?php if(!empty($item['nap_conflict'])): ?><span class="bh-planner-nap-badge">😴 Nap overlap</span><?php endif; ?><?php if($item['label']): ?><span><?php echo esc_html($item['label']); ?></span><?php endif; ?><b><?php echo esc_html($item['start'].($item['end']?'–'.$item['end']:'')); ?></b><small><?php echo esc_html($item['venue']?:'Location to be confirmed'); ?></small></a>
                <a class="bh-planner-calendar-link" href="<?php echo esc_url($item['calendar_url']); ?>" title="Add this session to your calendar">+ Add to Calendar</a>
              </div>
            <?php endforeach; endif; ?>
          </div><?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if('list'===$view): ?>
      <div class="bh-planner-list-view"><?php if($list_rows): foreach($list_rows as $item): ?><article class="bh-planner-list-row"><div class="bh-planner-list-date"><strong><?php echo esc_html(wp_date('D',strtotime($item['date']))); ?></strong><span><?php echo esc_html(wp_date('j M',strtotime($item['date']))); ?></span></div><div class="bh-planner-list-time"><?php echo esc_html($item['start'].($item['end']?'–'.$item['end']:'')); ?></div><a class="bh-planner-list-name" href="<?php echo esc_url($item['url']); ?>"><strong><?php echo esc_html($item['title']); ?></strong><?php if(!empty($item['nap_conflict'])): ?><small class="bh-planner-nap-badge">😴 Nap overlap</small><?php endif; ?><?php if($item['label']): ?><small><?php echo esc_html($item['label']); ?></small><?php endif; ?></a><div class="bh-planner-list-venue"><?php echo esc_html($item['venue']?:'Location to be confirmed'); ?></div><a class="bh-planner-list-add" href="<?php echo esc_url($item['calendar_url']); ?>">+ Add to Calendar</a></article><?php endforeach; else: ?><div class="bh-planner-empty">No sessions scheduled.</div><?php endif; ?></div>
      <?php elseif('month'===$view): ?>
      <div class="bh-planner-month-view"><?php $cursor=strtotime($view_start);for($mi=0;$mi<42;$mi++):$md=wp_date('Y-m-d',strtotime('+'.$mi.' days',$cursor));$mitems=array();foreach($rows as $mr)if($mr['date']===$md)$mitems[]=$mr; ?><div class="bh-planner-month-day<?php echo wp_date('m',strtotime($md))===wp_date('m',$month_ts)?'':' bh-planner-month-outside'; ?><?php echo $md===$today_date?' bh-planner-month-today':''; ?>"><div class="bh-planner-month-date"><?php echo esc_html(wp_date('j',strtotime($md))); ?></div><div class="bh-planner-month-items"><?php foreach($mitems as $miitem): ?><a href="<?php echo esc_url($miitem['url']); ?>" class="bh-planner-month-event"><strong><?php echo esc_html($miitem['start']); ?></strong> <?php echo esc_html($miitem['title']); ?><?php if(!empty($miitem['nap_conflict'])): ?> · 😴<?php endif; ?></a><?php endforeach; ?></div></div><?php endfor; ?></div>
      <?php endif; ?>

      <?php if(in_array($view,array('week','today'),true)): ?>
      <div class="bh-planner-mobile">
        <?php foreach($display_days as $day): ?><div class="bh-planner-mobile-day"><div class="bh-planner-mobile-day-heading"><span><strong><?php echo esc_html($day); ?></strong><small><?php echo esc_html(wp_date('j F',strtotime($display_dates[$day]))); ?></small></span><span class="bh-planner-mobile-count"><?php echo esc_html(count($week_by[$day])); ?></span></div>
          <div class="bh-planner-mobile-items"><?php if(!empty($week_by[$day])): foreach($week_by[$day] as $item): $location_ids=bubbahub_myhub_planner_v2_region_ids((int)$item['group_id']); ?>
            <div class="bh-planner-item-wrap bh-planner-filter-item" data-planner-location-ids="<?php echo esc_attr(implode(',',array_map('absint',(array)$location_ids))); ?>"><div class="bh-planner-item"><a class="bh-planner-listing-link" href="<?php echo esc_url($item['url']); ?>"><span class="bh-planner-thumb"><?php if($item['image']): ?><img src="<?php echo esc_url($item['image']); ?>" alt="" loading="lazy"><?php else: ?><span class="bh-planner-placeholder" aria-hidden="true">♡</span><?php endif; ?></span><span class="bh-planner-item-main"><strong><?php echo esc_html($item['title']); ?></strong><?php if($item['label']): ?><span class="bh-planner-session-label"><?php echo esc_html($item['label']); ?></span><?php endif; ?><span class="bh-planner-time"><?php echo esc_html($item['start'].($item['end']?'–'.$item['end']:'')); ?></span><span class="bh-planner-location">📍 <?php echo esc_html($item['venue']?:'Location to be confirmed'); ?></span></span></a><a class="bh-planner-mobile-calendar-link" href="<?php echo esc_url($item['calendar_url']); ?>">+ Add to Calendar</a></div></div>
          <?php endforeach; endif; ?></div>
        </div><?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if(in_array($view,array('week','today'),true)): ?>
<div class="bh-planner-actions">
        <a class="bh-planner-subscribe-button" href="<?php echo esc_url( 'webcal://' . preg_replace( '#^https?://#', '', home_url('/?bubbahub_calendar=1') ) ); ?>">📅 Subscribe to Calendar</a>
        <button type="button" class="bh-planner-print-button" onclick="window.print()">🖨 Print Calendar</button>
      </div>
      <?php endif; ?>
<style>
.bh-planner-print-header{display:none!important}
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
.bh-timetable-body{display:grid;grid-template-columns:58px repeat(7,minmax(120px,1fr));min-width:900px;height:calc(var(--bh-grid-hours) * var(--bh-hour-px,60px));position:relative}
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
.bh-planner-mobile{display:none}.bh-planner-nap-badge{display:inline-flex!important;align-items:center;align-self:flex-start;margin-top:3px!important;padding:3px 6px!important;border-radius:999px!important;background:#fff3dc!important;color:#7b5b21!important;font-size:9px!important;font-weight:800!important;line-height:1.2!important}.bh-weekly-planner-v2{padding-bottom:50px}.bh-planner-mobile-day{border:1px solid #e4e4e4;border-radius:12px;background:#fff;margin:0 0 9px;overflow:hidden}.bh-planner-mobile-day-heading{display:flex;align-items:center;justify-content:space-between;padding:13px 15px;border-bottom:1px solid #eee}.bh-planner-mobile-day-heading>span:first-child{display:flex;flex-direction:column;gap:2px}.bh-planner-mobile-day-heading strong{font-size:15px}.bh-planner-mobile-day-heading small{font-size:11px;color:#777}.bh-planner-mobile-count{min-width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#f1f1f1;font-size:11px;font-weight:700}.bh-planner-mobile-items{padding:10px;display:grid;gap:9px}.bh-planner-item-wrap{margin:0}.bh-planner-item{height:100%;border:1px solid #e7e7e7;border-radius:12px;background:#fff;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.04)}.bh-planner-mobile-calendar-link{display:block;margin:0 10px 10px;padding:7px 10px;border:1px solid #ddd;border-radius:8px;text-align:center;text-decoration:none!important;font-size:12px;font-weight:700}.bh-planner-listing-link{display:flex;align-items:center;gap:10px;padding:10px;text-decoration:none!important}.bh-planner-thumb{width:58px;height:58px;min-width:58px;border-radius:9px;overflow:hidden;display:flex;align-items:center;justify-content:center;background:#f3f3f3}.bh-planner-thumb img{width:100%;height:100%;object-fit:cover;display:block}.bh-planner-placeholder{font-size:22px;opacity:.45}.bh-planner-item-main{display:flex;flex-direction:column;min-width:0;line-height:1.3}.bh-planner-item-main strong{font-size:14px;line-height:1.25}.bh-planner-session-label{font-size:12px;opacity:.75;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.bh-planner-time{font-size:12px;font-weight:700;margin-top:2px}.bh-planner-location{font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
@media print{
  @page{size:A4 landscape;margin:3mm 5mm}
  html,body{margin:0!important;padding:0!important;width:auto!important;height:auto!important;min-height:0!important;overflow:hidden!important}
  body *{visibility:hidden!important}
  .bh-weekly-planner-v2{
    visibility:visible!important;
    position:relative!important;
    top:auto!important;
    left:auto!important;
    width:100%!important;
    max-width:none!important;
    height:auto!important;
    box-sizing:border-box!important;
    min-height:0!important;
    margin:0!important;
    padding:0!important;
    background:#fff!important;
    box-shadow:none!important;
    overflow:hidden!important;
    transform:none!important;
    box-sizing:border-box!important;
    transform-origin:top left!important;
  }
  .bh-weekly-planner-v2 .bh-planner-timetable-desktop,
  .bh-weekly-planner-v2 .bh-planner-timetable-desktop *{visibility:visible!important}
  .bh-weekly-planner-v2>.bh-myhub-section-heading,
  .bh-planner-calendar-toolbar,
  .bh-planner-search,
  .bh-planner-actions,
  .bh-weekly-planner-preferences,
  .bh-planner-mobile{display:none!important}
  .bh-weekly-planner-v2 .bh-planner-print-header{
    display:flex!important;
    visibility:visible!important;
    align-items:center;
    justify-content:flex-start;
    gap:10px;
    margin:0 0 4px!important;
    padding:3px 7px!important;
    border:1px solid #ddd;
    border-radius:7px;
    background:#fff;
    text-align:left;
    font-size:9px;
    line-height:1.2;
  }
  .bh-weekly-planner-v2 .bh-planner-print-header img{
    display:block!important;
    visibility:visible!important;
    width:28px!important;height:28px!important;
    max-width:28px!important;max-height:28px!important;
    object-fit:contain!important;
  }
  .bh-weekly-planner-v2 .bh-planner-print-header strong{
    display:block!important;font-size:12px!important;line-height:1.05!important;margin-bottom:1px!important
  }
  .bh-weekly-planner-v2 .bh-planner-print-header span{display:block!important}
  .bh-weekly-planner-v2 .bh-planner-print-header,
  .bh-weekly-planner-v2 .bh-planner-print-header *{visibility:visible!important}
  .bh-planner-timetable-desktop{
    margin:0!important;
    display:block!important;
    width:100%!important;
    max-width:none!important;
    height:auto!important;
    max-height:none!important;
    overflow:hidden!important;
    border:1px solid #999!important;
    break-inside:avoid!important;
    page-break-inside:avoid!important;
  }
  .bh-planner-timetable-desktop .bh-timetable-body{
    --bh-hour-px:42px!important;
    height:calc(var(--bh-grid-hours) * var(--bh-hour-px))!important;
  }
  .bh-timetable-header,.bh-timetable-body{min-width:0!important;width:100%!important;grid-template-columns:42px repeat(7,minmax(0,1fr))!important}
  .bh-timetable-event{break-inside:avoid!important;page-break-inside:avoid!important}
  .bh-weekly-planner-v2:after{content:none!important;display:none!important}
  .bh-planner-print-page-end{display:none!important}
}

/* Alternate view grid sizing */
.bh-planner-days-1 .bh-timetable-header,
.bh-planner-days-1 .bh-timetable-body{grid-template-columns:58px minmax(220px,1fr)!important;min-width:0!important}
.bh-planner-days-1 .bh-timetable-column{min-width:0!important}
.bh-planner-days-7 .bh-timetable-header,
.bh-planner-days-7 .bh-timetable-body{grid-template-columns:58px repeat(7,minmax(120px,1fr))}
/* Category colour coding */
.bh-timetable-event.bh-category-baby,.bh-timetable-event.bh-category-babies{--bh-cat:#4285f4}.bh-timetable-event.bh-category-toddler,.bh-timetable-event.bh-category-toddlers{--bh-cat:#34a853}.bh-timetable-event.bh-category-pregnancy,.bh-timetable-event.bh-category-antenatal,.bh-timetable-event.bh-category-postnatal{--bh-cat:#a142f4}.bh-timetable-event.bh-category-sensory,.bh-timetable-event.bh-category-music,.bh-timetable-event.bh-category-dance{--bh-cat:#fbbc04}.bh-timetable-event.bh-category-forest-school,.bh-timetable-event.bh-category-forest-schools{--bh-cat:#0f9d58}.bh-timetable-event.bh-category-support,.bh-timetable-event.bh-category-wellbeing{--bh-cat:#00acc1}.bh-timetable-event{--bh-cat:#1a73e8}.bh-timetable-event>a:first-child{background:var(--bh-cat)!important;border-left-color:color-mix(in srgb,var(--bh-cat),#000 22%)!important}
@media print{.bh-weekly-planner-v2{break-after:avoid!important;page-break-after:avoid!important}.bh-planner-timetable-desktop{break-before:avoid!important;break-after:avoid!important;page-break-before:avoid!important;page-break-after:avoid!important}.bh-timetable-column-many{min-width:0!important}.bh-timetable-event{font-size:9px!important}.bh-timetable-event>a:first-child{padding:3px 4px!important}.bh-timetable-event>a:first-child strong{font-size:9px!important}.bh-timetable-event>a:first-child span,.bh-timetable-event>a:first-child b{font-size:8px!important}.bh-timetable-event>a:first-child small{font-size:7px!important}}
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
  .bh-planner-print-header{display:none!important}
  .bh-timetable{display:none!important}
}
@media(max-width:700px){.bh-planner-actions{flex-direction:row;flex-wrap:wrap}.bh-planner-subscribe-button,.bh-planner-print-button{flex:1 1 0;min-width:0}.bh-planner-calendar-toolbar{grid-template-columns:1fr 1fr;}.bh-planner-calendar-toolbar strong{grid-column:1/-1;grid-row:1;order:-1;margin-bottom:4px}.bh-planner-calendar-toolbar a{ text-align:center }.bh-planner-search-location{max-width:none}.bh-timetable-header,.bh-timetable-body{min-width:0}.bh-planner-timetable-desktop{display:none}.bh-planner-mobile{display:block}}

/* Google Calendar-inspired weekly view: clean grid, compact controls and dense event cards. */
.bh-weekly-planner-v2{
  --bh-gcal-blue:#1a73e8;
  --bh-gcal-blue-soft:#e8f0fe;
  --bh-gcal-border:#dadce0;
  --bh-gcal-grid:#e8eaed;
  --bh-gcal-muted:#70757a;
  --bh-gcal-text:#3c4043;
  background:transparent;
}
.bh-planner-calendar-toolbar{
  grid-template-columns:auto auto 1fr auto!important;
  gap:8px!important;
  align-items:center!important;
  margin:0 0 12px!important;
}
.bh-planner-calendar-toolbar a{
  min-height:36px;
  box-sizing:border-box;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  padding:8px 13px!important;
  border:1px solid var(--bh-gcal-border)!important;
  border-radius:4px!important;
  background:#fff!important;
  color:var(--bh-gcal-text)!important;
  font-size:14px!important;
  font-weight:500!important;
  box-shadow:none!important;
  transition:background .15s ease,border-color .15s ease;
}
.bh-planner-calendar-toolbar a:hover{
  background:#f8f9fa!important;
  border-color:#c7c9cc!important;
}
.bh-planner-calendar-toolbar strong{
  color:var(--bh-gcal-text)!important;
  font-size:18px!important;
  font-weight:400!important;
}
.bh-planner-today{
  border-color:var(--bh-gcal-blue)!important;
  color:var(--bh-gcal-blue)!important;
  font-weight:600!important;
}
.bh-planner-search{
  margin:0 0 12px!important;
  padding:10px 12px!important;
  border:1px solid var(--bh-gcal-border)!important;
  border-radius:8px!important;
  background:#fff!important;
}
.bh-planner-search-location{
  max-width:320px!important;
  gap:5px!important;
}
.bh-planner-search-location>span{
  color:var(--bh-gcal-muted)!important;
  font-size:11px!important;
  font-weight:500!important;
}
.bh-planner-search select{
  min-height:38px!important;
  padding:8px 10px!important;
  border:1px solid var(--bh-gcal-border)!important;
  border-radius:4px!important;
  color:var(--bh-gcal-text)!important;
  background:#fff!important;
}
.bh-planner-timetable-desktop{
  border:1px solid var(--bh-gcal-border)!important;
  border-radius:8px!important;
  overflow:auto!important;
  background:#fff!important;
  box-shadow:none!important;
}
.bh-timetable-header{
  background:#fff!important;
  border-bottom:1px solid var(--bh-gcal-border)!important;
}
.bh-timetable-time-head{
  padding:10px 4px!important;
  color:var(--bh-gcal-muted)!important;
  background:#fff!important;
  border-right:1px solid var(--bh-gcal-border)!important;
  font-size:10px!important;
  font-weight:500!important;
}
.bh-timetable-day-head{
  position:relative;
  padding:8px 5px 10px!important;
  background:#fff!important;
  border-right:1px solid var(--bh-gcal-border)!important;
}
.bh-timetable-day-head strong{
  color:var(--bh-gcal-muted)!important;
  font-size:11px!important;
  font-weight:500!important;
  text-transform:uppercase;
}
.bh-timetable-day-head span{
  display:flex!important;
  width:30px;
  height:30px;
  margin:4px auto 0;
  align-items:center;
  justify-content:center;
  border-radius:50%;
  color:var(--bh-gcal-text)!important;
  font-size:16px!important;
  font-weight:400!important;
}
.bh-timetable-day-head.bh-timetable-day-head-today span{
  background:var(--bh-gcal-blue)!important;
  color:#fff!important;
  font-weight:500!important;
}
.bh-timetable-day-head.bh-timetable-day-head-today{
  background:#fff!important;
}
.bh-timetable-body{
  background:#fff!important;
  height:calc(var(--bh-grid-hours) * var(--bh-hour-px,60px));
}
.bh-timetable-times{
  background:#fff!important;
  border-right:1px solid var(--bh-gcal-border)!important;
}
.bh-timetable-times span{
  color:var(--bh-gcal-muted)!important;
  font-size:10px!important;
  font-weight:400!important;
}
.bh-timetable-column{
  background:repeating-linear-gradient(
    to bottom,
    #fff 0,
    #fff 59px,
    var(--bh-gcal-grid) 59px,
    var(--bh-gcal-grid) 60px
  )!important;
  border-right:1px solid var(--bh-gcal-border)!important;
}
.bh-timetable-hour-line{
  border-top:0!important;
}
.bh-timetable-column:after{
  content:"";
  position:absolute;
  left:0;
  right:0;
  top:0;
  border-top:1px solid var(--bh-gcal-grid);
  pointer-events:none;
}
.bh-timetable-event{
  padding:1px!important;
  border-radius:4px!important;
  z-index:2;
}
.bh-timetable-event>a:first-child{
  box-sizing:border-box;
  display:flex!important;
  flex-direction:column!important;
  height:100%!important;
  min-height:0!important;
  padding:4px 6px!important;
  background:var(--bh-gcal-blue)!important;
  border:0!important;
  border-left:3px solid #0b57d0!important;
  border-radius:4px!important;
  color:#fff!important;
  box-shadow:none!important;
  overflow:hidden!important;
}
.bh-timetable-event>a:first-child strong{
  color:#fff!important;
  font-size:12px!important;
  line-height:1.18!important;
  font-weight:500!important;
  white-space:normal!important;
  overflow-wrap:anywhere;
}
.bh-timetable-event>a:first-child span{
  color:rgba(255,255,255,.92)!important;
  font-size:10px!important;
  line-height:1.15!important;
  margin-top:2px!important;
  white-space:nowrap!important;
  overflow:hidden!important;
  text-overflow:ellipsis;
}
.bh-timetable-event>a:first-child b{
  color:#fff!important;
  font-size:10px!important;
  line-height:1.15!important;
  margin-top:3px!important;
  font-weight:600!important;
}
.bh-timetable-event>a:first-child small{
  color:rgba(255,255,255,.88)!important;
  font-size:9px!important;
  line-height:1.15!important;
  margin-top:auto!important;
  white-space:nowrap!important;
  overflow:hidden!important;
  text-overflow:ellipsis;
}
.bh-timetable-event .bh-planner-calendar-link{
  display:none!important;
}
.bh-timetable-event:hover>a:first-child{
  background:#185abc!important;
}
.bh-timetable-empty{
  color:var(--bh-gcal-muted)!important;
  font-size:11px!important;
}
.bh-planner-actions{
  margin-top:12px!important;
  padding:10px!important;
  border:0!important;
  background:transparent!important;
}
.bh-planner-print-button,.bh-planner-subscribe-button{
  min-height:36px!important;
  padding:8px 13px!important;
  border:1px solid var(--bh-gcal-border)!important;
  border-radius:4px!important;
  background:#fff!important;
  color:var(--bh-gcal-text)!important;
  font-size:13px!important;
  font-weight:500!important;
  box-shadow:none!important;
}
.bh-planner-print-button:hover,.bh-planner-subscribe-button:hover{
  background:#f8f9fa!important;
}
.bh-timetable-column-many{min-width:0;}
.bh-timetable-event{left:0!important;width:100%!important;}
.bh-planner-print-page-end{display:none;}
.bh-timetable-column-many .bh-timetable-event>a:first-child{
  padding:4px 5px!important;
}
.bh-timetable-column-many .bh-timetable-event strong{
  font-size:11px!important;
}
@media(max-width:700px){
  .bh-planner-calendar-toolbar{
    grid-template-columns:1fr 1fr!important;
    gap:6px!important;
  }
  .bh-planner-calendar-toolbar strong{
    grid-column:1/-1!important;
    grid-row:1!important;
    order:-1!important;
    font-size:17px!important;
    margin-bottom:2px!important;
  }
  .bh-planner-calendar-toolbar a{
    min-height:40px!important;
    border-radius:4px!important;
    font-size:13px!important;
  }
  .bh-planner-search{
    border-radius:8px!important;
  }
}

</style>


    <?php return ob_get_clean();
}


/* Keep planner override CSS out of plugin activation output. */
add_action( 'wp_head', function() {
?>
<style>
/* Final planner layout overrides */
.bh-timetable-event{left:0!important;width:100%!important;}
.bh-timetable-column-many{min-width:0!important;}
@media print{
  .bh-timetable-event{left:0!important;width:100%!important;}
  .bh-planner-print-page-end{display:none!important;}
}
</style>
<style>
.bh-planner-mobile-view-select{display:none}
.bh-planner-view-switcher{display:inline-flex;border:1px solid #d9e7e2;border-radius:10px;overflow:hidden;background:#fff;box-shadow:0 2px 10px rgba(39,48,58,.04)}.bh-planner-view-link{padding:8px 14px!important;border:0!important;border-right:1px solid #dadce0!important;border-radius:0!important;color:#3c4043!important;background:#fff!important;text-decoration:none!important;font-size:13px!important;font-weight:500!important}.bh-planner-view-link:last-child{border-right:0!important}.bh-planner-view-link.is-active{background:#e8f0fe!important;color:#1a73e8!important}.bh-planner-calendar-nav{display:grid;grid-template-columns:auto 1fr auto;gap:8px;align-items:center;width:100%}.bh-planner-calendar-nav a{min-height:36px;display:inline-flex;align-items:center;justify-content:center;padding:8px 13px!important;border:1px solid #dadce0!important;border-radius:4px!important;background:#fff!important;color:#3c4043!important;text-decoration:none!important}.bh-planner-calendar-nav strong{text-align:center;font-size:18px!important;font-weight:400!important}.bh-planner-list-view{border:1px solid #dadce0;border-radius:8px;overflow:hidden;background:#fff}.bh-planner-list-row{display:grid;grid-template-columns:80px 90px minmax(180px,1.5fr) minmax(140px,1fr) auto;gap:12px;align-items:center;padding:12px 14px;border-bottom:1px solid #e8eaed}.bh-planner-list-row:last-child{border-bottom:0}.bh-planner-list-date{display:flex;flex-direction:column;line-height:1.1}.bh-planner-list-date strong{font-size:11px;text-transform:uppercase;color:#70757a}.bh-planner-list-date span{font-size:15px;font-weight:600}.bh-planner-list-time{font-size:13px;font-weight:600}.bh-planner-list-name{text-decoration:none!important;color:#3c4043!important}.bh-planner-list-name strong{display:block}.bh-planner-list-name small{display:block;color:#70757a}.bh-planner-list-venue{font-size:12px;color:#5f6368;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.bh-planner-list-add{font-size:11px;padding:6px 8px;border:1px solid #dadce0;border-radius:5px;color:#3c4043;text-decoration:none!important}.bh-planner-month-view{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));border:1px solid #dadce0;border-radius:8px;overflow:hidden;background:#fff}.bh-planner-month-day{min-height:125px;padding:7px;border-right:1px solid #e8eaed;border-bottom:1px solid #e8eaed;box-sizing:border-box}.bh-planner-month-day:nth-child(7n){border-right:0}.bh-planner-month-date{font-size:12px;font-weight:600;margin-bottom:5px}.bh-planner-month-today .bh-planner-month-date{display:flex;width:25px;height:25px;border-radius:50%;align-items:center;justify-content:center;background:#1a73e8;color:#fff}.bh-planner-month-outside{background:#f8f9fa}.bh-planner-month-outside .bh-planner-month-date{color:#9aa0a6}.bh-planner-month-items{display:grid;gap:3px}.bh-planner-month-event{display:block;padding:3px 5px;border-radius:3px;background:#1a73e8;color:#fff!important;text-decoration:none!important;font-size:10px;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}@media(max-width:700px){.bh-planner-view-switcher{width:100%;display:flex}.bh-planner-view-link{flex:1;text-align:center;padding:8px 4px!important}.bh-planner-calendar-nav{grid-template-columns:1fr 1fr}.bh-planner-calendar-nav strong{grid-column:1/-1;grid-row:1}.bh-planner-calendar-nav a{grid-row:2}.bh-planner-list-row{grid-template-columns:60px 1fr;gap:6px 10px}.bh-planner-list-name,.bh-planner-list-venue,.bh-planner-list-add{grid-column:1/-1}.bh-planner-list-add{text-align:center}.bh-planner-month-view{grid-template-columns:repeat(7,minmax(90px,1fr));overflow-x:auto}.bh-planner-month-day{min-width:90px;min-height:105px}}@media print{.bh-planner-view-switcher,.bh-planner-calendar-nav,.bh-planner-list-view,.bh-planner-month-view{display:none!important}}

/* Calendar directory/search controls */
.bh-weekly-planner-v2 .bh-calendar-view-switcher{
  display:flex;
  align-items:center;
  gap:0;
  width:max-content;
  max-width:100%;
  margin:0 0 16px;
  border:1px solid #d9e7e2;
  border-radius:14px;
  overflow:hidden;
  background:#fff;
  box-shadow:0 3px 12px rgba(39,48,58,.05);
}
.bh-weekly-planner-v2 .bh-calendar-view-link{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-height:44px;
  min-width:132px;
  padding:10px 18px;
  border:0;
  border-right:1px solid #d9e7e2;
  background:#fff;
  color:#3c4043;
  text-decoration:none!important;
  font-size:14px;
  font-weight:800;
}
.bh-weekly-planner-v2 .bh-calendar-view-link:last-child{border-right:0}
.bh-weekly-planner-v2 .bh-calendar-view-link:hover{background:#f2f8f5}
.bh-weekly-planner-v2 .bh-calendar-view-link.is-active{background:#5f9183;color:#fff!important}

.bh-weekly-planner-v2 .bh-calendar-search-form{
  display:grid;
  grid-template-columns:minmax(240px,1.5fr) minmax(220px,1fr) auto;
  gap:10px;
  align-items:end;
  margin:0 0 18px;
  padding:16px;
  border:1px solid #e1ebe8;
  border-radius:18px;
  background:linear-gradient(180deg,#f8fbfa 0%,#f5f9f7 100%);
  box-shadow:0 4px 18px rgba(39,48,58,.045);
}
.bh-weekly-planner-v2 .bh-calendar-search-form input,
.bh-weekly-planner-v2 .bh-calendar-search-form select{
  width:100%;
  min-height:48px;
  padding:10px 13px;
  border:1px solid #dfe8e5;
  border-radius:13px;
  background:#fff;
  color:#27303a;
  font:inherit;
  box-shadow:0 2px 7px rgba(39,48,58,.025);
}
.bh-weekly-planner-v2 .bh-calendar-search-form input:focus,
.bh-weekly-planner-v2 .bh-calendar-search-form select:focus{
  outline:0;
  border-color:#9bc8bf;
  box-shadow:0 0 0 3px rgba(105,171,160,.14);
}
.bh-weekly-planner-v2 .bh-calendar-location-control{display:flex;gap:6px}
.bh-weekly-planner-v2 .bh-calendar-location-control input{flex:1}
.bh-weekly-planner-v2 .bh-calendar-use-location{
  flex:0 0 48px;
  min-width:48px!important;
  padding:0!important;
  border:1px solid #dfe8e5!important;
  border-radius:13px!important;
  background:#fff!important;
  color:#27303a!important;
  cursor:pointer;
}
.bh-weekly-planner-v2 .bh-calendar-search-actions{display:flex;gap:8px}
.bh-weekly-planner-v2 .bh-calendar-save-button{
  border:1px solid #9bc8bf;
  background:#fff;
  color:#4f8175;
}
.bh-weekly-planner-v2 .bh-calendar-save-panel{
  grid-column:1/-1;
  display:flex;
  align-items:center;
  gap:10px;
  padding:10px 12px;
  border:1px solid #dfe8e5;
  border-radius:14px;
  background:#fff;
}
.bh-weekly-planner-v2 .bh-calendar-save-panel label{font-size:12px;font-weight:800;color:#52615c;white-space:nowrap}
.bh-weekly-planner-v2 .bh-calendar-save-panel>div{display:flex;gap:7px;flex:1}
.bh-weekly-planner-v2 .bh-calendar-save-panel input{min-height:42px!important;flex:1}
.bh-weekly-planner-v2 .bh-calendar-save-panel button{min-height:42px;padding:0 13px;border:1px solid #d9e7e2;border-radius:10px;background:#eef5f2;color:#35423f;font-weight:800;cursor:pointer}
.bh-weekly-planner-v2 .bh-calendar-save-panel button[data-confirm-save]{background:#5f9183;color:#fff;border-color:#5f9183}
.bh-weekly-planner-v2 .bh-planner-saved-calendar-link{border-top:2px solid #f2c6b8!important}
.bh-weekly-planner-v2 .bh-saved-calendars{
  margin:0 0 18px;
  padding:16px;
  border:1px solid #e1ebe8;
  border-radius:18px;
  background:#f8fbfa;
  box-shadow:0 4px 18px rgba(39,48,58,.045);
}
.bh-weekly-planner-v2 .bh-saved-calendars-heading{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
  margin:0 0 12px;
}
.bh-weekly-planner-v2 .bh-saved-calendars-kicker{
  display:block;
  margin:0 0 2px;
  color:#71807b;
  font-size:10px;
  font-weight:800;
  letter-spacing:.08em;
}
.bh-weekly-planner-v2 .bh-saved-calendars-heading h3{
  margin:0;
  color:#35423f;
  font-size:18px;
  line-height:1.2;
}
.bh-weekly-planner-v2 .bh-saved-calendars-controls{display:flex;gap:6px;flex:0 0 auto}
.bh-weekly-planner-v2 .bh-saved-calendar-scroll{
  width:38px;
  height:38px;
  padding:0;
  border:1px solid #d9e7e2;
  border-radius:10px;
  background:#fff;
  color:#4f8175;
  font-size:24px;
  line-height:1;
  cursor:pointer;
}
.bh-weekly-planner-v2 .bh-saved-calendar-scroll:hover{background:#eef5f2}
.bh-weekly-planner-v2 .bh-saved-calendars-track{
  display:grid;
  grid-auto-flow:column;
  grid-auto-columns:minmax(270px,340px);
  gap:12px;
  overflow-x:auto;
  overflow-y:hidden;
  padding:2px 2px 8px;
  scroll-snap-type:x mandatory;
  scroll-behavior:smooth;
  scrollbar-width:thin;
  -webkit-overflow-scrolling:touch;
}
.bh-weekly-planner-v2 .bh-saved-calendars-track::-webkit-scrollbar{height:7px}
.bh-weekly-planner-v2 .bh-saved-calendar-card{
  display:flex;
  flex-direction:column;
  min-height:150px;
  padding:15px;
  border:1px solid #dfe8e5;
  border-radius:15px;
  background:#fff;
  box-shadow:0 2px 9px rgba(39,48,58,.04);
  scroll-snap-align:start;
  box-sizing:border-box;
}
.bh-weekly-planner-v2 .bh-saved-calendar-card.is-active{
  border-color:#9bc8bf;
  box-shadow:0 0 0 2px rgba(155,200,191,.18),0 3px 12px rgba(39,48,58,.06);
}
.bh-weekly-planner-v2 .bh-saved-calendar-card-top{display:flex;align-items:center;gap:7px;margin-bottom:8px}
.bh-weekly-planner-v2 .bh-saved-calendar-icon{color:#e5a07f;font-size:15px}
.bh-weekly-planner-v2 .bh-saved-calendar-type{color:#71807b;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
.bh-weekly-planner-v2 .bh-saved-calendar-card h4{
  margin:0;
  color:#35423f;
  font-size:16px;
  line-height:1.3;
  overflow-wrap:anywhere;
}
.bh-weekly-planner-v2 .bh-saved-calendar-meta{margin-top:5px;color:#71807b;font-size:12px}
.bh-weekly-planner-v2 .bh-saved-calendar-actions{
  display:flex;
  gap:7px;
  flex-wrap:wrap;
  margin-top:auto;
  padding-top:13px;
}
.bh-weekly-planner-v2 .bh-saved-calendar-actions a,
.bh-weekly-planner-v2 .bh-saved-calendar-actions button{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-height:36px;
  padding:7px 10px;
  border:1px solid #d9e7e2;
  border-radius:9px;
  background:#fff;
  color:#4f8175;
  font:inherit;
  font-size:12px;
  font-weight:800;
  text-decoration:none!important;
  cursor:pointer;
  box-sizing:border-box;
}
.bh-weekly-planner-v2 .bh-saved-calendar-actions .bh-saved-calendar-open{
  flex:1 1 130px;
  background:#eef5f2;
}
.bh-weekly-planner-v2 .bh-saved-calendar-actions .bh-saved-calendar-delete{
  color:#a35e53;
  border-color:#ead3ce;
}
@media(max-width:620px){
  .bh-weekly-planner-v2 .bh-saved-calendars{padding:12px;border-radius:16px}
  .bh-weekly-planner-v2 .bh-saved-calendars-heading h3{font-size:16px}
  .bh-weekly-planner-v2 .bh-saved-calendars-track{
    grid-auto-columns:minmax(250px,calc(100vw - 48px));
    margin-right:-2px;
  }
  .bh-weekly-planner-v2 .bh-saved-calendar-card{min-height:145px}
}

@media(max-width:620px){
  .bh-weekly-planner-v2 .bh-calendar-save-panel{display:block}
  .bh-weekly-planner-v2 .bh-calendar-save-panel label{display:block;margin-bottom:6px}
  .bh-weekly-planner-v2 .bh-calendar-save-panel>div{display:grid;grid-template-columns:1fr 1fr 1fr}
  .bh-weekly-planner-v2 .bh-calendar-save-panel input{grid-column:1/-1}
}
.bh-weekly-planner-v2 .bh-calendar-search-actions button{
  min-height:48px;
  padding:0 16px;
  border-radius:13px;
  font:inherit;
  font-weight:800;
  cursor:pointer;
}
.bh-weekly-planner-v2 .bh-calendar-search-button{
  border:0;
  background:#f28b7b;
  color:#fff;
  box-shadow:0 3px 10px rgba(39,48,58,.07);
}
.bh-weekly-planner-v2 .bh-calendar-advanced-toggle{
  border:1px solid #d9e7e2;
  background:#eef5f2;
  color:#27303a;
}
.bh-weekly-planner-v2 .bh-calendar-advanced-search{
  grid-column:1/-1;
  display:grid;
  grid-template-columns:repeat(4,minmax(0,1fr));
  gap:10px;
  padding:14px;
  border:1px solid #dfe8e5;
  border-radius:15px;
  background:rgba(255,255,255,.72);
}
.bh-weekly-planner-v2 .bh-calendar-advanced-search[hidden]{display:none}
.bh-weekly-planner-v2 .bh-calendar-filter-option{min-width:0}
.bh-weekly-planner-v2 .bh-calendar-filter-option label{
  display:block;
  margin:0 0 6px;
  color:#52615c;
  font-size:11px;
  font-weight:800;
}
.bh-weekly-planner-v2 .screen-reader-text{
  position:absolute!important;
  width:1px!important;
  height:1px!important;
  padding:0!important;
  margin:-1px!important;
  overflow:hidden!important;
  clip:rect(0,0,0,0)!important;
  white-space:nowrap!important;
  border:0!important;
}
@media(max-width:900px){
  .bh-weekly-planner-v2 .bh-calendar-search-form{grid-template-columns:1fr 1fr}
  .bh-weekly-planner-v2 .bh-calendar-search-actions{grid-column:1/-1}
  .bh-weekly-planner-v2 .bh-calendar-advanced-search{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:620px){
  .bh-weekly-planner-v2 .bh-calendar-view-switcher{width:100%}
  .bh-weekly-planner-v2 .bh-calendar-view-link{flex:1;min-width:0;padding:10px 8px;font-size:13px}
  .bh-weekly-planner-v2 .bh-calendar-search-form{grid-template-columns:1fr;padding:12px;border-radius:16px}
  .bh-weekly-planner-v2 .bh-calendar-search-actions{grid-column:1;display:grid;grid-template-columns:1fr 1fr}
  .bh-weekly-planner-v2 .bh-calendar-search-actions button{width:100%;padding:0 10px}
  .bh-weekly-planner-v2 .bh-calendar-advanced-search{grid-template-columns:1fr;padding:10px}
}

/* Print PDF: remove the excessive top whitespace and keep the header/logo compact. */
@media print{
  @page{size:A4;margin:10mm 9mm 10mm 9mm}
  html,body{margin:0!important;padding:0!important}
  .bh-weekly-planner-v2{margin:0!important;padding:0!important}
  .bh-planner-print-header{
    margin:0!important;
    padding:0 0 8px!important;
    min-height:0!important;
    page-break-after:avoid!important;
    break-after:avoid-page!important;
  }
  .bh-planner-print-header img{
    display:block!important;
    max-height:42px!important;
    width:auto!important;
    margin:0!important;
  }
  .bh-planner-print-header h1,
  .bh-planner-print-header h2,
  .bh-planner-print-header p{margin-top:0!important}
  .bh-planner-calendar,
  .bh-planner-grid{margin-top:0!important}
}/* Softer Bubba Hub calendar palette */
.bh-weekly-planner-v2{
  --bh-gcal-blue:#78a99d;
  --bh-gcal-blue-soft:#edf6f3;
  --bh-gcal-border:#d9e7e2;
  --bh-gcal-grid:#edf1f0;
  --bh-gcal-muted:#71807b;
  --bh-gcal-text:#35423f;
}
.bh-weekly-planner-v2 .bh-timetable-event>a:first-child{
  background:#dfeeea!important;
  border-left-color:#9bc8bf!important;
  color:#35423f!important;
}
.bh-weekly-planner-v2 .bh-timetable-event>a:first-child strong,
.bh-weekly-planner-v2 .bh-timetable-event>a:first-child b{color:#35423f!important}
.bh-weekly-planner-v2 .bh-timetable-event>a:first-child span,
.bh-weekly-planner-v2 .bh-timetable-event>a:first-child small{color:#5c6d68!important}
.bh-weekly-planner-v2 .bh-timetable-event:hover>a:first-child{background:#d4e8e2!important}
.bh-weekly-planner-v2 .bh-planner-month-event{background:#78a99d!important}
.bh-weekly-planner-v2 .bh-planner-month-today .bh-planner-month-date{background:#78a99d!important}
.bh-weekly-planner-v2 .bh-planner-view-link.is-active{background:#e7f2ef!important;color:#4f8175!important}
.bh-weekly-planner-v2 .bh-planner-calendar-toolbar a:hover{background:#f5faf8!important}
.bh-weekly-planner-v2 .bh-planner-calendar-search-form,
.bh-weekly-planner-v2 .bh-planner-search{box-shadow:0 2px 12px rgba(39,48,58,.035)!important}
@media(max-width:700px){
  .bh-weekly-planner-v2 .bh-planner-view-switcher{display:none!important}
  .bh-weekly-planner-v2 .bh-planner-mobile-view-select{
    display:block!important;
    min-width:0;
    margin:0;
  }
  .bh-weekly-planner-v2 .bh-planner-mobile-view-select select{
    width:100%;
    min-height:44px;
    padding:9px 38px 9px 12px;
    border:1px solid #d9e7e2;
    border-radius:10px;
    background:#f8fbfa;
    color:#35423f;
    font:inherit;
    font-size:14px;
    font-weight:700;
    box-sizing:border-box;
  }
  .bh-weekly-planner-v2 .bh-planner-calendar-toolbar{
    grid-template-columns:1fr 1fr!important;
    gap:8px!important;
    margin-bottom:12px!important;
  }
  .bh-weekly-planner-v2 .bh-planner-mobile-view-select{
    grid-column:1/-1;
    grid-row:1;
  }
  .bh-weekly-planner-v2 .bh-planner-calendar-nav{
    grid-column:1/-1;
    grid-row:2;
  }
  .bh-weekly-planner-v2 .bh-planner-calendar-nav a{
    min-height:42px!important;
    border-radius:9px!important;
    background:#f8fbfa!important;
    border-color:#d9e7e2!important;
  }
}
@media(max-width:480px){.bh-planner-subscribe-button,.bh-planner-print-button{flex:0 0 44px!important;width:44px!important;min-width:44px!important;max-width:44px!important;height:44px!important;min-height:44px!important;padding:0!important;font-size:0!important;line-height:1!important;white-space:nowrap!important;overflow:hidden!important}.bh-planner-subscribe-button::before{content:'📅';font-size:20px!important;line-height:1!important}.bh-planner-print-button::before{content:'🖨';font-size:20px!important;line-height:1!important}.bh-planner-actions{justify-content:center!important}}
</style>

<?php
} );