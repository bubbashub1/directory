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
    if ( is_string( $value ) ) {
        $decoded = json_decode( $value, true );
        if ( JSON_ERROR_NONE === json_last_error() ) $value = $decoded;
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
    if ( function_exists( 'bubbahub_myhub_planner_age_matches' ) ) {
        return (bool) bubbahub_myhub_planner_age_matches( $group_id, array( $child_id ) );
    }
    $tokens = bubbahub_myhub_planner_v2_child_age_token( $child_id ); if ( ! $tokens ) return false;
    $value = bubbahub_myhub_planner_v2_user_child_field( $group_id, 'age_range' );
    $hay = strtolower( is_array( $value ) ? implode( ' ', array_map( 'strval', $value ) ) : (string) $value );
    if ( ! $hay ) return false;
    foreach ( $tokens as $token ) if ( false !== strpos( $hay, strtolower( $token ) ) ) return true;
    return false;
}

function bubbahub_myhub_weekly_planner_v2_shortcode() {
    if ( ! is_user_logged_in() ) return '<div class="bh-planner-empty">Please log in to use your personalised weekly planner.</div>';

    $children = bubbahub_myhub_planner_v2_child_ids();
    $rows = bubbahub_myhub_planner_v2_schedule_occurrences( 7 );
    $prefs = bubbahub_myhub_planner_v2_user_preference_terms();
    $days = array( 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday' );
    $by = array_fill_keys( $days, array() );

    foreach ( $rows as $row ) {
        // Location is selected from the Group region taxonomy.
        // The planner search controls filter the generated timetable.
        $matching = array();
        foreach ( $children as $index => $child_id ) {
            // Location and keyword are filtered by the planner search UI.
            // Do not pre-filter this seven-day feed using older saved preferences.
            if ( ! bubbahub_myhub_planner_v2_child_matches_group( $row['group_id'], $child_id ) ) continue;
            $matching[] = $index + 1;
        }
        if ( ! $matching ) continue;
        $ts = strtotime( $row['date'] . ' ' . $row['start'] );
        if ( ! $ts ) continue;
        $end_ts = strtotime( $row['date'] . ' ' . ( $row['end'] ?: $row['start'] ) );
        $day = wp_date( 'l', $ts );
        $row['child_numbers'] = $matching;
        $row['is_all'] = count( $children ) > 1 && count( $matching ) === count( $children );
        $row['indicator'] = $row['is_all'] ? 'All' : ( count( $matching ) === 1 ? 'Child ' . $matching[0] : 'Children' );
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
          <label class="bh-planner-search-location"><span>Choose Location</span>
            <select class="bh-planner-location-select">
              <option value="">Any location</option>
              <?php
              if ( taxonomy_exists( 'region' ) ) {
                  $planner_location_terms = get_terms( array( 'taxonomy' => 'region', 'hide_empty' => false, 'number' => 200, 'orderby' => 'name', 'order' => 'ASC' ) );
                  if ( ! is_wp_error( $planner_location_terms ) ) foreach ( $planner_location_terms as $planner_location_term ) :
              ?>
                <option value="<?php echo esc_attr( $planner_location_term->term_id ); ?>"><?php echo esc_html( $planner_location_term->name ); ?></option>
              <?php endforeach; } ?>
            </select>
          </label>
          <label class="bh-planner-search-keywords"><span>Search</span>
            <input type="search" class="bh-planner-keyword-search" placeholder="Search groups, activities or tags..." autocomplete="off">
            <div class="bh-planner-search-suggestions" hidden></div>
          </label>
          <label class="bh-planner-search-filter"><span>Filter</span>
            <span class="bh-planner-toggle"><input type="checkbox" class="bh-planner-free-groups"> Free Groups</span>
          </label>
          <label class="bh-planner-search-filter"><span>&nbsp;</span>
            <span class="bh-planner-toggle"><input type="checkbox" class="bh-planner-term-time"> Term Time</span>
          </label>
          <button type="button" class="bh-planner-search-save">Save</button>
        </div>
        <p class="bh-planner-search-hint">Search by activity or tag, such as music, messy play, baby massage or Forest School.</p>
      </div>

      <?php if ( $children ) :
        ?>
        <details class="bh-planner-child-filter-mobile">
          <summary>Children <span>All · <?php echo esc_html( count( $children ) ); ?> child<?php echo count( $children ) === 1 ? '' : 'ren'; ?></span></summary>
          <div class="bh-planner-child-options">
            <button type="button" class="active" data-planner-child-filter="all">All</button>
            <?php foreach ( $children as $index => $child_id ) : ?><?php $child_name = bubbahub_myhub_planner_v2_user_child_field( $child_id, 'child_name' ); $child_label = $child_name ? $child_name : 'Child ' . ( $index + 1 ); ?><button type="button" data-planner-child-filter="<?php echo esc_attr( $index + 1 ); ?>"><?php echo esc_html( $child_label ); ?></button><?php endforeach; ?>
          </div>
        </details>
        <div class="bh-planner-child-filter-desktop">
          <strong>Show</strong>
          <button type="button" class="active" data-planner-child-filter="all">All</button>
          <?php foreach ( $children as $index => $child_id ) : ?><?php $child_name = bubbahub_myhub_planner_v2_user_child_field( $child_id, 'child_name' ); $child_label = $child_name ? $child_name : 'Child ' . ( $index + 1 ); ?><button type="button" data-planner-child-filter="<?php echo esc_attr( $index + 1 ); ?>"><?php echo esc_html( $child_label ); ?></button><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="bh-planner-results">
      <?php foreach ( $days as $day ) : ?><div class="bh-planner-day">
        <div class="bh-planner-day-accordion">
          <div class="bh-planner-day-title"><?php echo esc_html( $day ); ?><span class="bh-planner-day-chevron" aria-hidden="true">⌄</span></div>
          <div class="bh-planner-day-items">
        <?php if ( ! empty( $by[ $day ] ) ) : foreach ( $by[ $day ] as $item ) : ?>
          <?php
            $planner_location_ids = taxonomy_exists( 'region' ) ? wp_get_post_terms( (int) $item['group_id'], 'region', array( 'fields' => 'ids' ) ) : array();
            if ( is_wp_error( $planner_location_ids ) ) $planner_location_ids = array();
            $planner_location_ids = array_values( array_unique( array_map( 'absint', $planner_location_ids ) ) );
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
          <div class="bh-planner-item-wrap" data-planner-children="<?php echo esc_attr( implode( ',', $item['child_numbers'] ) ); ?>" data-planner-free="<?php
              $bh_free = false;
              foreach ( array( 'price', '_price', 'cost', 'session_price' ) as $bh_key ) {
                  $bh_val = strtolower( trim( wp_strip_all_tags( (string) get_post_meta( (int) $item['group_id'], $bh_key, true ) ) ) );
                  if ( '' === $bh_val || in_array( $bh_val, array( '0', '0.00', 'free', '£0', '£0.00', 'from £0' ), true ) ) { $bh_free = true; break; }
              }
              echo $bh_free ? '1' : '0';
          ?>" data-planner-term-time="<?php
              $bh_term = false;
              foreach ( array( 'term_time', '_term_time', 'term-time', 'school_term_time', 'term_time_only' ) as $bh_key ) {
                  $bh_val = get_post_meta( (int) $item['group_id'], $bh_key, true );
                  if ( is_array( $bh_val ) ) $bh_val = implode( ' ', $bh_val );
                  $bh_val = strtolower( trim( wp_strip_all_tags( (string) $bh_val ) ) );
                  if ( in_array( $bh_val, array( '1', 'yes', 'true', 'on', 'term time', 'term-time' ), true ) ) { $bh_term = true; break; }
              }
              echo $bh_term ? '1' : '0';
          ?>" data-planner-location-ids="<?php echo esc_attr( implode( ',', array_map( 'absint', (array) $planner_location_ids ) ) ); ?>" data-planner-tags="<?php
              $planner_tag_terms = array();
              foreach ( get_object_taxonomies( 'group' ) as $planner_tag_tax ) {
                  $planner_terms = wp_get_post_terms( (int) $item['group_id'], $planner_tag_tax, array( 'fields' => 'names' ) );
                  if ( ! is_wp_error( $planner_terms ) ) $planner_tag_terms = array_merge( $planner_tag_terms, $planner_terms );
              }
              echo esc_attr( implode( '|', array_values( array_unique( array_map( 'strval', $planner_tag_terms ) ) ) ) );
          ?>" data-planner-search="<?php echo esc_attr( $planner_search_text ); ?>">
            <div class="bh-planner-item <?php echo $item['is_all'] ? 'bh-planner-all' : ''; ?>">
              <a class="bh-planner-listing-link" href="<?php echo esc_url( $item['url'] ); ?>">
              <span class="bh-planner-thumb"><?php if ( $item['image'] ) : ?><img src="<?php echo esc_url( $item['image'] ); ?>" alt="" loading="lazy"><?php else : ?><span class="bh-planner-placeholder" aria-hidden="true">♡</span><?php endif; ?></span>
              <span class="bh-planner-item-main"><strong><?php echo esc_html( $item['title'] ); ?></strong><span class="bh-planner-location">📍 <?php echo esc_html( $item['venue'] ?: 'Location to be confirmed' ); ?></span></span>
              <span class="bh-planner-child-indicator"><?php echo esc_html( $item['indicator'] ); ?></span>
              </a>
              <span class="bh-planner-calendar-wrap"><a class="bh-planner-calendar" href="<?php echo esc_url( $item['calendar_url'] ); ?>" target="_blank" rel="noopener" aria-label="Add <?php echo esc_attr( $item['title'] ); ?> to calendar">＋ Add to calendar</a><span class="bh-planner-calendar-note">Add it to your calendar and share it with family.</span></span>
            </div>
          </div>
        <?php endforeach; else : ?><div class="bh-planner-empty">No matching sessions today.</div><?php endif; ?>
          </div>
        </div>
      </div><?php endforeach; ?>
      </div>


<style>
.bh-planner-search{margin:0 0 20px;padding:18px;border:1px solid #e6e6e6;border-radius:18px;background:#fff}.bh-planner-search-row{display:flex;gap:12px;align-items:end}.bh-planner-search-row label{flex:1;display:flex;flex-direction:column;gap:6px}.bh-planner-search-row label span{font-weight:700;font-size:13px}.bh-planner-search-row select,.bh-planner-search-row input{min-height:44px;padding:10px 12px;border:1px solid #ddd;border-radius:10px}.bh-planner-search-save{min-height:44px;padding:0 20px;border:0;border-radius:10px;cursor:pointer}.bh-planner-search-save.is-saved{opacity:.8}.bh-planner-search-suggestions{position:absolute;z-index:20;background:#fff;border:1px solid #ddd;border-radius:10px;margin-top:72px;box-shadow:0 5px 20px rgba(0,0,0,.08);overflow:hidden}.bh-planner-search-suggestions button{display:block;width:100%;text-align:left;padding:9px 12px;border:0;background:#fff;cursor:pointer}.bh-planner-search-hint{margin:8px 0 0;font-size:12px;opacity:.7}@media(max-width:700px){.bh-planner-search-row{display:block}.bh-planner-search-row label{margin-bottom:10px}.bh-planner-search-save{width:100%}.bh-planner-search-suggestions{position:relative;margin-top:-5px;width:100%}}
.bh-planner-search-filter{flex:0 0 auto;display:flex;flex-direction:column;gap:6px}.bh-planner-toggle{min-height:44px;display:flex;align-items:center;gap:8px;padding:0 12px;border:1px solid #ddd;border-radius:10px;font-weight:600;white-space:nowrap}.bh-planner-toggle input{width:18px;height:18px}.bh-planner-search-row select,.bh-planner-search-row input,.bh-planner-toggle,.bh-planner-search-save{touch-action:manipulation}@media(max-width:700px){.bh-planner-search-row label{width:100%}.bh-planner-search-filter{width:100%}.bh-planner-toggle{width:100%;box-sizing:border-box}.bh-planner-search-save{display:block;min-height:48px;font-size:16px}}</style>
      <script>
      document.addEventListener('DOMContentLoaded',function(){
        document.querySelectorAll('.bh-weekly-planner-v2').forEach(function(planner){
          var location=planner.querySelector('.bh-planner-location-select');
          var input=planner.querySelector('.bh-planner-keyword-search');
          var suggestions=planner.querySelector('.bh-planner-search-suggestions');
          var save=planner.querySelector('.bh-planner-search-save');
          var tags=[];
          planner.querySelectorAll('.bh-planner-item-wrap').forEach(function(el){
            (el.getAttribute('data-planner-tags')||'').split('|').forEach(function(t){ t=t.trim(); if(t) tags.push(t); });
          });
          var freeGroups=planner.querySelector('.bh-planner-free-groups'), termTime=planner.querySelector('.bh-planner-term-time');
          var selectedChild='all', savedKeyword='', savedLocation='';
          try { savedKeyword=localStorage.getItem('bh_planner_keyword')||''; savedLocation=localStorage.getItem('bh_planner_location')||''; } catch(e){}
          if(input) input.value=savedKeyword;
          if(location) location.value=savedLocation;

          function renderSuggestions(){
            if(!suggestions||!input)return;
            var q=input.value.toLowerCase().trim();
            if(!q){suggestions.hidden=true;suggestions.innerHTML='';return;}
            var seen={},matches=tags.filter(function(t){var k=t.toLowerCase();return k.indexOf(q)!==-1&&!seen[k]&&(seen[k]=true);}).slice(0,8);
            suggestions.innerHTML=matches.map(function(t){return '<button type="button" data-suggest="'+t.replace(/"/g,'&quot;')+'">'+t+'</button>';}).join('');
            suggestions.hidden=!matches.length;
          }

          function applyFilters(){
            var loc=location?location.value:'';
            var q=input?input.value.toLowerCase().trim():'';
            var words=q?q.split(/\s+/).filter(Boolean):[];
            var freeOnly=!!(freeGroups&&freeGroups.checked), termOnly=!!(termTime&&termTime.checked);
            planner.querySelectorAll('.bh-planner-item-wrap').forEach(function(item){
              var kids=(item.getAttribute('data-planner-children')||'').split(',').filter(Boolean);
              var locs=(item.getAttribute('data-planner-location-ids')||'').split(',').filter(Boolean);
              var text=item.getAttribute('data-planner-search')||'';
              var childOk=selectedChild==='all'||kids.indexOf(selectedChild)!==-1;
              var locationOk=!loc||locs.indexOf(String(loc))!==-1;
              var keywordOk=!words.length||words.every(function(word){return text.indexOf(word)!==-1;});
              var freeOk=!freeOnly||item.getAttribute('data-planner-free')==='1';
              var termOk=!termOnly||item.getAttribute('data-planner-term-time')==='1';
              item.hidden=!(childOk&&locationOk&&keywordOk&&freeOk&&termOk);
            });
            planner.querySelectorAll('.bh-planner-day').forEach(function(day){
              var items=day.querySelectorAll('.bh-planner-item-wrap');
              var visible=Array.from(items).some(function(x){return !x.hidden;});
              day.hidden=false;
              var empty=day.querySelector('.bh-planner-live-empty');
              if(!empty){
                empty=document.createElement('div');
                empty.className='bh-planner-empty bh-planner-live-empty';
                empty.textContent='No matching sessions today.';
                day.querySelector('.bh-planner-day-items').appendChild(empty);
              }
              empty.hidden=visible;
            });
          }

          function lockSearch(){
            if(input) input.disabled=false;
            if(location) location.disabled=false;
            if(save){save.textContent='Saved';save.classList.add('is-saved');}
          }
          function editSearch(){
            if(input) input.disabled=false;
            if(location) location.disabled=false;
            if(save){save.textContent='Save';save.classList.remove('is-saved');}
            if(input) input.focus();
          }

          if(input) input.addEventListener('input',function(){renderSuggestions();applyFilters();});
          if(location) location.addEventListener('change',applyFilters);
          if(freeGroups) freeGroups.addEventListener('change',applyFilters);
          if(termTime) termTime.addEventListener('change',applyFilters);
          if(suggestions) suggestions.addEventListener('click',function(e){
            var b=e.target.closest('[data-suggest]');
            if(!b)return;
            input.value=b.getAttribute('data-suggest');
            suggestions.hidden=true;
            applyFilters();
          });
          if(save) save.addEventListener('click',function(){
            if(save.classList.contains('is-saved')){editSearch();return;}
            try{
              localStorage.setItem('bh_planner_keyword',input?input.value:'');
              localStorage.setItem('bh_planner_location',location?location.value:'');
            }catch(e){}
            lockSearch();
            applyFilters();
          });

          planner.querySelectorAll('[data-planner-child-filter]').forEach(function(button){
            button.addEventListener('click',function(){
              selectedChild=button.getAttribute('data-planner-child-filter')||'all';
              planner.querySelectorAll('[data-planner-child-filter]').forEach(function(b){
                b.classList.toggle('active',b.getAttribute('data-planner-child-filter')===selectedChild);
              });
              applyFilters();
            });
          });

          applyFilters();
          if(savedKeyword||savedLocation) lockSearch();
        });
      });
      </script>
    </section>
    <?php return ob_get_clean();
}
