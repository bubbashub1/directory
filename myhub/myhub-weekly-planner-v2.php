<?php
/**
 * BubbaHub My Hub – session-based weekly planner.
 * Uses child profiles to build the weekly planner; account preferences are managed separately.
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

function bubbahub_myhub_planner_v2_weekly_schedule_rows( $post_id ) {
    $value = function_exists( 'get_field' ) ? get_field( 'weekly_schedule', $post_id, false ) : get_post_meta( $post_id, 'weekly_schedule', true );

    // ACF repeater values can arrive as an array, JSON, or a serialized PHP array.
    // Normalise the value before reading the day/session rows.
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
        'monday' => 'Monday', 'mon' => 'Monday', 'tuesday' => 'Tuesday', 'tue' => 'Tuesday',
        'wednesday' => 'Wednesday', 'wed' => 'Wednesday', 'thursday' => 'Thursday', 'thu' => 'Thursday',
        'friday' => 'Friday', 'fri' => 'Friday', 'saturday' => 'Saturday', 'sat' => 'Saturday',
        'sunday' => 'Sunday', 'sun' => 'Sunday',
    );
    $rows = array();

    foreach ( $value as $row ) {
        if ( ! is_array( $row ) ) continue;
        $day_key = strtolower( trim( (string) ( isset( $row['day_name'] ) ? $row['day_name'] : '' ) ) );
        if ( ! isset( $day_names[ $day_key ] ) || ! empty( $row['is_closed'] ) ) continue;

        $sessions = isset( $row['sessions'] ) ? $row['sessions'] : array();
        if ( ! is_array( $sessions ) ) $sessions = array( $sessions );

        foreach ( $sessions as $session ) {
            $start = ''; $end = '';
            if ( is_string( $session ) ) {
                if ( preg_match( '/(\d{1,2}:\d{2})\s*(?:[-–—]|to)\s*(\d{1,2}:\d{2})/i', $session, $m ) ) {
                    $start = $m[1]; $end = $m[2];
                } elseif ( preg_match( '/(\d{1,2}:\d{2})/i', $session, $m ) ) $start = $m[1];
            } elseif ( is_array( $session ) ) {
                foreach ( array( 'start', 'start_time', 'from', 'time' ) as $key ) {
                    if ( isset( $session[ $key ] ) && '' !== trim( (string) $session[ $key ] ) ) { $start = trim( (string) $session[ $key ] ); break; }
                }
                foreach ( array( 'end', 'end_time', 'to' ) as $key ) {
                    if ( isset( $session[ $key ] ) && '' !== trim( (string) $session[ $key ] ) ) { $end = trim( (string) $session[ $key ] ); break; }
                }
                if ( ! preg_match( '/^\d{1,2}:\d{2}$/', $start ) && preg_match( '/(\d{1,2}:\d{2})\s*(?:[-–—]|to)\s*(\d{1,2}:\d{2})/i', $start, $m ) ) {
                    $start = $m[1]; if ( ! $end ) $end = $m[2];
                }
            }
            if ( ! preg_match( '/^\d{1,2}:\d{2}$/', $start ) ) continue;
            if ( $end && ! preg_match( '/^\d{1,2}:\d{2}$/', $end ) ) $end = '';
            $rows[ $day_names[ $day_key ] ][] = array( 'start' => $start, 'end' => $end );
        }
    }
    return $rows;
}

function bubbahub_myhub_planner_v2_schedule_occurrences( $days_ahead = 7 ) {
    $now = current_time( 'timestamp' );
    $day_map = array( 'Sunday'=>0, 'Monday'=>1, 'Tuesday'=>2, 'Wednesday'=>3, 'Thursday'=>4, 'Friday'=>5, 'Saturday'=>6 );
    $rows = array();

    $q = new WP_Query( array(
        'post_type' => 'group', 'post_status' => 'publish', 'posts_per_page' => 250,
        'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true,
    ) );

    while ( $q->have_posts() ) {
        $q->the_post();
        $gid = get_the_ID();
        $schedule = bubbahub_myhub_planner_v2_weekly_schedule_rows( $gid );
        if ( ! $schedule ) continue;

        $venue_id = function_exists( 'bubbahub_group_venue_id' ) ? absint( bubbahub_group_venue_id( $gid ) ) : 0;
        $venue_address = $venue_id ? ( get_post_meta( $venue_id, 'address', true ) ?: get_post_meta( $venue_id, 'street_address', true ) ) : '';
        $coords = function_exists( 'bubbahub_group_resolve_map' ) ? bubbahub_group_resolve_map( $gid ) : null;
        if ( ! $coords && $venue_id && function_exists( 'bubbahub_group_resolve_map' ) ) $coords = bubbahub_group_resolve_map( $venue_id );

        foreach ( $schedule as $weekday => $sessions ) {
            if ( ! isset( $day_map[ $weekday ] ) ) continue;
            for ( $offset = 0; $offset <= (int) $days_ahead; $offset++ ) {
                $candidate_ts = strtotime( '+' . $offset . ' days', $now );
                if ( (int) wp_date( 'w', $candidate_ts ) !== $day_map[ $weekday ] ) continue;
                $date = wp_date( 'Y-m-d', $candidate_ts );

                foreach ( $sessions as $session ) {
                    $start = $session['start']; $end = $session['end'];
                    $start_ts = strtotime( $date . ' ' . $start );
                    if ( ! $start_ts || $start_ts < $now ) continue;
                    if ( $end ) {
                        $end_ts = strtotime( $date . ' ' . $end );
                        if ( $end_ts && $end_ts < $start_ts ) $end_ts = strtotime( '+1 day', $end_ts );
                    }

                    $rows[] = array(
                        'session_id'=>0, 'group_id'=>$gid, 'venue_id'=>$venue_id, 'date'=>$date,
                        'start'=>$start, 'end'=>$end, 'title'=>get_the_title($gid), 'url'=>get_permalink($gid),
                        'image'=>function_exists('bubbahub_group_image') ? bubbahub_group_image($gid) : get_the_post_thumbnail_url($gid,'thumbnail'),
                        'venue'=>$venue_id ? get_the_title($venue_id) : '', 'venue_address'=>$venue_address,
                        'lat'=>($coords && isset($coords['lat']) ? $coords['lat'] : null),
                        'lng'=>($coords && isset($coords['lng']) ? $coords['lng'] : null),
                    );
                }
            }
        }
    }
    wp_reset_postdata();

    usort( $rows, function( $a, $b ) { return ( $a['date'].' '.$a['start'] ) <=> ( $b['date'].' '.$b['start'] ); } );
    return $rows;
}

function bubbahub_myhub_planner_v2_user_preference_terms() {
    $uid = get_current_user_id();
    $interest_ids = get_user_meta( $uid, 'bubbahub_interest_term_ids', true );
    return array(
        'ids' => is_array( $interest_ids ) ? array_values( array_filter( array_map( 'absint', $interest_ids ) ) ) : array(),
        'location_term_id' => absint( get_user_meta( $uid, 'bubbahub_planner_location_term_id', true ) ),
        'location_taxonomy' => sanitize_key( (string) get_user_meta( $uid, 'bubbahub_planner_location_taxonomy', true ) ),
        'keyword' => sanitize_text_field( (string) get_user_meta( $uid, 'bubbahub_planner_keyword', true ) ),
        'saved' => '1' === (string) get_user_meta( $uid, 'bubbahub_planner_preferences_saved', true ),
    );
}

function bubbahub_myhub_planner_v2_user_location_radius() {
    $uid = get_current_user_id();
    $town = trim( (string) get_user_meta( $uid, 'bubbahub_town', true ) );
    $county = trim( (string) get_user_meta( $uid, 'bubbahub_county', true ) );
    $radius = trim( (string) get_user_meta( $uid, 'bubbahub_search_radius', true ) );
    if ( ! $radius ) $radius = trim( (string) get_user_meta( $uid, 'bubbahub_search_radius', true ) );
    preg_match( '/(\\d+(?:\\.\\d+)?)/', $radius, $m );
    $radius_miles = ! empty( $m[1] ) ? (float) $m[1] : 10.0;

    $lat = get_user_meta( $uid, 'bubbahub_location_latitude', true );
    $lng = get_user_meta( $uid, 'bubbahub_location_longitude', true );

    if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
        $query = trim( $town . ( $county ? ', ' . $county : '' ) . ', UK' );
        if ( $query ) {
            $response = wp_remote_get(
                'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . rawurlencode( $query ),
                array( 'timeout' => 4, 'headers' => array( 'User-Agent' => 'BubbaHub/1.0 (+https://bubbahub.co.uk)' ) )
            );
            if ( ! is_wp_error( $response ) ) {
                $data = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( ! empty( $data[0]['lat'] ) && ! empty( $data[0]['lon'] ) ) {
                    $lat = (float) $data[0]['lat'];
                    $lng = (float) $data[0]['lon'];
                    update_user_meta( $uid, 'bubbahub_location_latitude', $lat );
                    update_user_meta( $uid, 'bubbahub_location_longitude', $lng );
                }
            }
        }
    }

    return array(
        'town' => $town,
        'radius_miles' => $radius_miles,
        'lat' => is_numeric( $lat ) ? (float) $lat : null,
        'lng' => is_numeric( $lng ) ? (float) $lng : null,
    );
}

function bubbahub_myhub_planner_v2_distance_miles( $lat1, $lng1, $lat2, $lng2 ) {
    $earth_miles = 3958.7613;
    $dlat = deg2rad( $lat2 - $lat1 );
    $dlng = deg2rad( $lng2 - $lng1 );
    $a = sin( $dlat / 2 ) * sin( $dlat / 2 ) + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $dlng / 2 ) * sin( $dlng / 2 );
    return $earth_miles * 2 * atan2( sqrt( $a ), sqrt( max( 0, 1 - $a ) ) );
}

function bubbahub_myhub_planner_v2_row_in_radius( $row, $location ) {
    if ( ! is_numeric( $location['lat'] ) || ! is_numeric( $location['lng'] ) ) return true;
    if ( ! isset( $row['lat'], $row['lng'] ) || ! is_numeric( $row['lat'] ) || ! is_numeric( $row['lng'] ) ) return false;
    return bubbahub_myhub_planner_v2_distance_miles( $location['lat'], $location['lng'], $row['lat'], $row['lng'] ) <= (float) $location['radius_miles'];
}

function bubbahub_myhub_planner_v2_user_location_values() {
    $uid = get_current_user_id();
    $values = array();
    foreach ( array( 'bubbahub_town', 'bubbahub_county', 'bubbahub_postcode', 'bubbahub_location', 'bubbahub_preferred_location', 'bubbahub_preferred_location_id' ) as $key ) {
        $value = get_user_meta( $uid, $key, true );
        if ( is_array( $value ) ) $values = array_merge( $values, $value );
        elseif ( '' !== trim( (string) $value ) ) $values[] = $value;
    }
    $out = array();
    foreach ( $values as $value ) {
        if ( is_object( $value ) && isset( $value->term_id ) ) $out[] = absint( $value->term_id );
        elseif ( is_array( $value ) && isset( $value['term_id'] ) ) $out[] = absint( $value['term_id'] );
        elseif ( is_numeric( $value ) ) $out[] = absint( $value );
        else $out[] = sanitize_text_field( (string) $value );
    }
    return array_values( array_unique( array_filter( $out, function( $v ) { return '' !== (string) $v; } ) ) );
}

function bubbahub_myhub_planner_v2_match( $group_id, $interest_ids = array(), $location_values = array(), $venue_id = 0 ) {
    $prefs = bubbahub_myhub_planner_v2_user_preference_terms();
    if ( ! empty( $prefs['saved'] ) ) {
        $location_id = absint( $prefs['location_term_id'] );
        $location_tax = $prefs['location_taxonomy'];
        if ( $location_id && $location_tax && taxonomy_exists( $location_tax ) ) {
            $terms = wp_get_post_terms( $group_id, $location_tax, array( 'fields' => 'ids' ) );
            $location_match = ! is_wp_error( $terms ) && in_array( $location_id, array_map( 'absint', $terms ), true );
            if ( ! $location_match && $venue_id ) {
                $terms = wp_get_post_terms( $venue_id, $location_tax, array( 'fields' => 'ids' ) );
                $location_match = ! is_wp_error( $terms ) && in_array( $location_id, array_map( 'absint', $terms ), true );
            }
            if ( ! $location_match ) return -1;
        }
        $keyword = strtolower( trim( (string) $prefs['keyword'] ) );
        if ( $keyword !== '' ) {
            $parts = array( get_the_title( $group_id ), wp_strip_all_tags( get_post_field( 'post_content', $group_id ) ), get_post_meta( $group_id, 'address', true ), get_post_meta( $group_id, 'city', true ), get_post_meta( $group_id, 'town', true ), get_post_meta( $group_id, 'region', true ), get_post_meta( $group_id, 'county', true ) );
            if ( $venue_id ) $parts = array_merge( $parts, array( get_the_title( $venue_id ), get_post_meta( $venue_id, 'address', true ), get_post_meta( $venue_id, 'city', true ), get_post_meta( $venue_id, 'town', true ) ) );
            foreach ( get_object_taxonomies( 'group' ) as $tax ) {
                $terms = wp_get_post_terms( $group_id, $tax, array( 'fields' => 'names' ) );
                if ( ! is_wp_error( $terms ) ) $parts = array_merge( $parts, $terms );
            }
            if ( false === strpos( strtolower( implode( ' ', array_map( 'strval', $parts ) ) ), $keyword ) ) return -1;
        }
    }
    if ( ! empty( $interest_ids ) ) {
        $matched = false;
        $interest_taxonomy = get_user_meta( get_current_user_id(), 'bubbahub_interest_taxonomy', true );
        if ( $interest_taxonomy && taxonomy_exists( $interest_taxonomy ) ) {
            $terms = wp_get_post_terms( $group_id, $interest_taxonomy, array( 'fields' => 'ids' ) );
            if ( ! is_wp_error( $terms ) && array_intersect( array_map( 'absint', $terms ), $interest_ids ) ) $matched = true;
        }
        if ( ! $matched ) foreach ( get_object_taxonomies( get_post_type( $group_id ) ) as $tax ) {
            $terms = wp_get_post_terms( $group_id, $tax, array( 'fields' => 'ids' ) );
            if ( ! is_wp_error( $terms ) && array_intersect( array_map( 'absint', $terms ), $interest_ids ) ) { $matched = true; break; }
        }
        if ( ! $matched ) return -1;
    }
    return 0;
}

function bubbahub_myhub_planner_v2_user_child_field( $id, $field ) {
    if ( function_exists( 'get_field' ) ) { $v = get_field( $field, $id, false ); if ( null !== $v && false !== $v && '' !== $v ) return $v; }
    return get_post_meta( $id, $field, true );
}

function bubbahub_myhub_planner_v2_child_age_token( $id ) {
    $status = bubbahub_myhub_planner_v2_user_child_field( $id, 'child_status' );
    if ( 'expecting' === sanitize_key( $status ) ) return array( 'pregnancy', 'antenatal', 'postnatal' );
    $dob = bubbahub_myhub_planner_v2_user_child_field( $id, 'child_date_of_birth' );
    if ( ! $dob ) return array();
    try { $birth = new DateTime( $dob ); $today = new DateTime( 'today' ); } catch ( Exception $e ) { return array(); }
    if ( $birth > $today ) return array();
    $months = (int) $birth->diff( $today )->y * 12 + (int) $birth->diff( $today )->m;
    if ( $months < 3 ) return array( '0-3' ); if ( $months < 6 ) return array( '3-6' ); if ( $months < 9 ) return array( '6-9' ); if ( $months < 12 ) return array( '9-12' ); if ( $months < 24 ) return array( '1-3' ); if ( $months < 36 ) return array( '2-4' ); if ( $months < 60 ) return array( '3-5' ); return array( '5-plus' );
}

function bubbahub_myhub_planner_v2_child_matches_group( $group_id, $child_id ) {
    // The legacy matcher expects exact tokens such as "1-3". Group age ranges are
    // entered in several perfectly valid formats, so use an overlap check here.
    $status = sanitize_key( (string) bubbahub_myhub_planner_v2_user_child_field( $child_id, 'child_status' ) );
    $value  = bubbahub_myhub_planner_v2_user_child_field( $group_id, 'age_range' );
    $hay    = strtolower( is_array( $value ) ? implode( ' ', array_map( 'strval', $value ) ) : (string) $value );
    $hay    = str_replace( array( '–', '—', '−' ), '-', $hay );
    $hay    = preg_replace( '/\\s+/', ' ', $hay );

    // If a listing has no age range, don't hide a valid scheduled session.
    if ( '' === trim( $hay ) ) return true;

    if ( 'expecting' === $status ) {
        return ( false !== strpos( $hay, 'pregnan' ) || false !== strpos( $hay, 'antenatal' ) || false !== strpos( $hay, 'postnatal' ) || false !== strpos( $hay, 'birth' ) || false !== strpos( $hay, '0-3' ) );
    }

    $dob = bubbahub_myhub_planner_v2_user_child_field( $child_id, 'child_date_of_birth' );
    if ( ! $dob ) return true;
    try { $birth = new DateTime( $dob ); $today = new DateTime( 'today' ); } catch ( Exception $e ) { return true; }
    if ( $birth > $today ) return true;
    $months = (int) $birth->diff( $today )->y * 12 + (int) $birth->diff( $today )->m;

    // Common explicit ranges: 0-3 months, 6-12 months, 1-3 years, 5+ years.
    if ( preg_match_all( '/(\\d+)\\s*-\\s*(\\d+)\\s*(month|months|year|years|yr|yrs)/i', $hay, $matches, PREG_SET_ORDER ) ) {
        foreach ( $matches as $m ) {
            $min = (int) $m[1] * ( preg_match( '/year/i', $m[3] ) ? 12 : 1 );
            $max = (int) $m[2] * ( preg_match( '/year/i', $m[3] ) ? 12 : 1 );
            if ( $months >= $min && $months <= $max ) return true;
        }
    }
    if ( preg_match_all( '/(\\d+)\\s*\\+/i', $hay, $matches ) ) {
        foreach ( $matches[1] as $n ) {
            $n = (int) $n;
            if ( $months >= $n * ( false !== strpos( $hay, 'year' ) ? 12 : 1 ) ) return true;
        }
    }

    // Fall back to the existing token matcher for values such as "1-3" or "5-plus".
    $tokens = bubbahub_myhub_planner_v2_child_age_token( $child_id );
    foreach ( $tokens as $token ) if ( false !== strpos( $hay, strtolower( $token ) ) ) return true;

    // If an age range is present but cannot be parsed, keep the session visible
    // rather than incorrectly producing an empty planner.
    return true;
}

function bubbahub_myhub_planner_v2_region_ids( $group_id ) {
    $ids = array();

    // Prefer the region assigned directly to the group.
    if ( taxonomy_exists( 'region' ) ) {
        $terms = wp_get_post_terms( (int) $group_id, 'region', array( 'fields' => 'ids' ) );
        if ( ! is_wp_error( $terms ) ) $ids = array_merge( $ids, array_map( 'absint', (array) $terms ) );
    }

    // Some listings inherit their region from the linked venue, so include that too.
    $venue_id = function_exists( 'bubbahub_group_venue_id' ) ? absint( bubbahub_group_venue_id( $group_id ) ) : 0;
    if ( $venue_id && taxonomy_exists( 'region' ) ) {
        $terms = wp_get_post_terms( $venue_id, 'region', array( 'fields' => 'ids' ) );
        if ( ! is_wp_error( $terms ) ) $ids = array_merge( $ids, array_map( 'absint', (array) $terms ) );
    }

    return array_values( array_unique( array_filter( $ids ) ) );
}

function bubbahub_myhub_weekly_planner_v2_shortcode() {
    if ( ! is_user_logged_in() ) return '<div class="bh-planner-empty">Please log in to use your personalised weekly planner.</div>';

    $rows = bubbahub_myhub_planner_v2_schedule_occurrences( 7 );
    $days = array( 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday' );
    $by = array_fill_keys( $days, array() );

    foreach ( $rows as $row ) {
        $ts = strtotime( $row['date'] . ' ' . $row['start'] );
        if ( ! $ts ) continue;
        $end_ts = strtotime( $row['date'] . ' ' . ( $row['end'] ?: $row['start'] ) );
        $day = wp_date( 'l', $ts );
        $row['calendar_url'] = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' . rawurlencode( $row['title'] ) . '&dates=' . rawurlencode( wp_date( 'Ymd\THis', $ts ) ) . '/' . rawurlencode( wp_date( 'Ymd\THis', $end_ts ) ) . '&details=' . rawurlencode( 'Bubba Hub: ' . $row['url'] ) . '&location=' . rawurlencode( $row['venue_address'] ?: $row['venue'] );
        $by[ $day ][] = $row;
    }

    foreach ( $by as &$entries ) {
        usort( $entries, function( $a, $b ) { return ( $a['date'] . ' ' . $a['start'] ) <=> ( $b['date'] . ' ' . $b['start'] ); } );
    }
    unset( $entries );

    $account_url = add_query_arg( 'bh_account_settings', '1', home_url( '/my-hub/' ) );

    ob_start(); ?>
    <section class="bh-myhub-section bh-weekly-planner bh-weekly-planner-v2">
      <div class="bh-myhub-section-heading">
        <div><div class="bh-myhub-kicker">YOUR WEEK</div><h2>Weekly Planner</h2></div>
        <a class="bh-weekly-planner-preferences" href="<?php echo esc_url( $account_url ); ?>">Update Preferences →</a>
      </div>

      <div class="bh-planner-search">
        <div class="bh-planner-search-row">
          <label class="bh-planner-search-location"><span>Choose Region</span>
            <select class="bh-planner-location-select">
              <option value="">All regions</option>
              <?php
              if ( taxonomy_exists( 'region' ) ) {
                  $planner_location_terms = get_terms( array( 'taxonomy' => 'region', 'hide_empty' => false, 'number' => 200, 'orderby' => 'name', 'order' => 'ASC' ) );
                  if ( ! is_wp_error( $planner_location_terms ) ) foreach ( $planner_location_terms as $planner_location_term ) :
              ?>
                <option value="<?php echo esc_attr( $planner_location_term->term_id ); ?>"><?php echo esc_html( $planner_location_term->name ); ?></option>
              <?php endforeach; } ?>
            </select>
          </label>
        </div>
      </div>
      <div class="bh-planner-simple-note">Choose a region to see the groups running on each day.</div>

      <div class="bh-planner-results">
      <?php foreach ( $days as $day ) : ?><div class="bh-planner-day">
        <div class="bh-planner-day-accordion">
          <div class="bh-planner-day-title"><?php echo esc_html( $day ); ?><span class="bh-planner-day-chevron" aria-hidden="true">⌄</span></div>
          <div class="bh-planner-day-items">
        <?php if ( ! empty( $by[ $day ] ) ) : foreach ( $by[ $day ] as $item ) : ?>
          <?php
            $planner_location_ids = bubbahub_myhub_planner_v2_region_ids( (int) $item['group_id'] );
            $planner_search_parts = array( $item['title'] );
            $planner_search_parts[] = get_post_field( 'post_content', (int) $item['group_id'] );
            foreach ( get_object_taxonomies( get_post_type( (int) $item['group_id'] ) ) as $planner_tax_name ) {
                $planner_term_names = wp_get_post_terms( (int) $item['group_id'], $planner_tax_name, array( 'fields' => 'names' ) );
                if ( ! is_wp_error( $planner_term_names ) ) $planner_search_parts = array_merge( $planner_search_parts, $planner_term_names );
            }
            if ( ! empty( $item['venue'] ) ) $planner_search_parts[] = $item['venue'];
            if ( ! empty( $item['venue_address'] ) ) $planner_search_parts[] = $item['venue_address'];
            $planner_search_text = strtolower( wp_strip_all_tags( implode( ' ', array_map( 'strval', $planner_search_parts ) ) ) );
          ?>
          <div class="bh-planner-item-wrap" data-planner-location-ids="<?php echo esc_attr( implode( ',', array_map( 'absint', (array) $planner_location_ids ) ) ); ?>">
            <div class="bh-planner-item <?php echo $item['is_all'] ? 'bh-planner-all' : ''; ?>">
              <a class="bh-planner-listing-link" href="<?php echo esc_url( $item['url'] ); ?>">
              <span class="bh-planner-thumb"><?php if ( $item['image'] ) : ?><img src="<?php echo esc_url( $item['image'] ); ?>" alt="" loading="lazy"><?php else : ?><span class="bh-planner-placeholder" aria-hidden="true">♡</span><?php endif; ?></span>
              <span class="bh-planner-item-main"><strong><?php echo esc_html( $item['title'] ); ?></strong><span class="bh-planner-location">📍 <?php echo esc_html( $item['venue'] ?: 'Location to be confirmed' ); ?></span></span>
                            </a>
              </span>
            </div>
          </div>
        <?php endforeach; else : ?><div class="bh-planner-empty">No matching sessions today.</div><?php endif; ?>
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
@media(max-width:700px){.bh-planner-search-row{display:block}.bh-planner-search-location{max-width:none}}
</style>

      <script>
      document.addEventListener('DOMContentLoaded',function(){
        document.querySelectorAll('.bh-weekly-planner-v2').forEach(function(planner){
          var location=planner.querySelector('.bh-planner-location-select');
          function applyRegion(){
            var loc=location?location.value:'';
            planner.querySelectorAll('.bh-planner-item-wrap').forEach(function(item){
              var locs=(item.getAttribute('data-planner-location-ids')||'').split(',').filter(Boolean);
              item.hidden=!!loc && locs.indexOf(String(loc))===-1;
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
