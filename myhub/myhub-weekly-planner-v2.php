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

function bubbahub_myhub_planner_v2_session_rows( $days_ahead = 7 ) {
    $now=current_time('timestamp'); $from=wp_date('Y-m-d',$now); $to=wp_date('Y-m-d',strtotime('+'.max(1,(int)$days_ahead).' days',$now));
    $q=new WP_Query(array('post_type'=>'bh_session','post_status'=>'publish','posts_per_page'=>250,'meta_query'=>array(array('key'=>'_bh_date','value'=>array($from,$to),'compare'=>'BETWEEN','type'=>'DATE')),'orderby'=>'meta_value','meta_key'=>'_bh_date','order'=>'ASC','no_found_rows'=>true));
    $rows=array();
    while($q->have_posts()){$q->the_post();$sid=get_the_ID();$gid=absint(get_post_meta($sid,'_bh_group_id',true));if(!$gid||'group'!==get_post_type($gid))continue;$date=get_post_meta($sid,'_bh_date',true);$start=get_post_meta($sid,'_bh_start_time',true);$end=get_post_meta($sid,'_bh_end_time',true);$vid=absint(get_post_meta($sid,'_bh_venue_id',true));$venue_address=$vid?(get_post_meta($vid,'address',true)?:get_post_meta($vid,'street_address',true)):'';
$rows[]=array('session_id'=>$sid,'group_id'=>$gid,'venue_id'=>$vid,'date'=>$date,'start'=>$start,'end'=>$end,'title'=>get_the_title($gid),'url'=>get_permalink($gid),'image'=>get_the_post_thumbnail_url($gid,'thumbnail'),'venue'=>$vid?get_the_title($vid):'','venue_address'=>$venue_address);}
    wp_reset_postdata(); return $rows;
}

function bubbahub_myhub_planner_v2_user_preference_terms() {
    $uid = get_current_user_id();
    $taxonomy = get_user_meta( $uid, 'bubbahub_interest_taxonomy', true );
    $ids = get_user_meta( $uid, 'bubbahub_interest_term_ids', true );
    return array(
        'taxonomy' => is_string( $taxonomy ) ? sanitize_key( $taxonomy ) : '',
        'ids'      => is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : array(),
    );
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
    $score = 0;
    $has_interest_preference = ! empty( $interest_ids );
    $has_location_preference = ! empty( $location_values );

    if ( $has_interest_preference ) {
        $interest_taxonomy = get_user_meta( get_current_user_id(), 'bubbahub_interest_taxonomy', true );
        $matched = false;
        if ( $interest_taxonomy && taxonomy_exists( $interest_taxonomy ) ) {
            $terms = wp_get_post_terms( $group_id, $interest_taxonomy, array( 'fields' => 'ids' ) );
            if ( ! is_wp_error( $terms ) && array_intersect( array_map( 'absint', $terms ), $interest_ids ) ) {
                $matched = true;
            }
        }
        if ( ! $matched ) {
            foreach ( get_object_taxonomies( get_post_type( $group_id ) ) as $tax ) {
                $terms = wp_get_post_terms( $group_id, $tax, array( 'fields' => 'ids' ) );
                if ( ! is_wp_error( $terms ) && array_intersect( array_map( 'absint', $terms ), $interest_ids ) ) {
                    $matched = true;
                    break;
                }
            }
        }
        if ( ! $matched ) return -1;
        $score += 4;
    }

    if ( $has_location_preference ) {
        $matched = false;
        $location_text = strtolower( trim( implode( ' ', array_map( 'strval', $location_values ) ) ) );
        foreach ( array( 'preferred-location', 'preferred_location', 'location', 'region', 'area', 'town', 'county' ) as $tax ) {
            if ( ! taxonomy_exists( $tax ) ) continue;
            $terms = wp_get_post_terms( $group_id, $tax, array( 'fields' => 'all' ) );
            if ( is_wp_error( $terms ) ) continue;
            foreach ( $terms as $term ) {
                $term_ids = array_map( 'absint', array( $term->term_id ) );
                if ( array_intersect( $term_ids, array_filter( array_map( 'absint', $location_values ) ) ) ) { $matched = true; break 2; }
                if ( $location_text && ( false !== strpos( $location_text, strtolower( $term->name ) ) || false !== strpos( strtolower( $term->name ), $location_text ) ) ) { $matched = true; break 2; }
            }
        }
        if ( ! $matched ) {
            $haystack = strtolower( implode( ' ', array_filter( array(
                get_the_title( $group_id ),
                get_post_meta( $group_id, 'address', true ),
                get_post_meta( $group_id, 'street_address', true ),
                get_post_meta( $group_id, 'city', true ),
                get_post_meta( $group_id, 'town', true ),
                get_post_meta( $group_id, 'region', true ),
                get_post_meta( $group_id, 'county', true ),
                $venue_id ? get_the_title( $venue_id ) : '',
                $venue_id ? get_post_meta( $venue_id, 'address', true ) : '',
                $venue_id ? get_post_meta( $venue_id, 'street_address', true ) : '',
                $venue_id ? get_post_meta( $venue_id, 'city', true ) : '',
                $venue_id ? get_post_meta( $venue_id, 'region', true ) : '',
            ) ) ) );
            foreach ( $location_values as $location ) {
                $location = strtolower( trim( (string) $location ) );
                if ( $location && false !== strpos( $haystack, $location ) ) { $matched = true; break; }
            }
        }
        if ( ! $matched ) return -1;
        $score += 3;
    }

    return $score;
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
    $rows = bubbahub_myhub_planner_v2_session_rows( 7 );
    /* If no session records are available, use the existing group timetable planner as a fallback. */
    if ( ! $rows && function_exists( 'bubbahub_myhub_planner_items' ) ) {
        $legacy_items = bubbahub_myhub_planner_items( $children, array(), array() );
        foreach ( $legacy_items as $legacy ) {
            foreach ( $legacy['schedule'] as $legacy_day => $hours ) {
                foreach ( $hours as $hour ) {
                    $start = ''; $end = '';
                    if ( preg_match( '/(\\d{1,2}:\\d{2})\\s*(?:[-–—to]+)\\s*(\\d{1,2}:\\d{2})/i', $hour, $m ) ) { $start = $m[1]; $end = $m[2]; }
                    elseif ( preg_match( '/(\\d{1,2}:\\d{2})/i', $hour, $m ) ) { $start = $m[1]; }
                    if ( ! $start ) continue;
                    $rows[] = array('session_id'=>0,'group_id'=>$legacy['id'],'venue_id'=>0,'date'=>wp_date('Y-m-d',current_time('timestamp')),'start'=>$start,'end'=>$end,'title'=>$legacy['title'],'url'=>$legacy['url'],'image'=>$legacy['image'],'venue'=>'','venue_address'=>'');
                }
            }
        }
    }
    $prefs = bubbahub_myhub_planner_v2_user_preference_terms();
    $interest_ids = $prefs['ids'];
    $location_values = bubbahub_myhub_planner_v2_user_location_values();
    $days = array( 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday' );
    $by = array_fill_keys( $days, array() );

    foreach ( $rows as $row ) {
        $matching = array();
        foreach ( $children as $index => $child_id ) {
            if ( ! bubbahub_myhub_planner_v2_child_matches_group( $row['group_id'], $child_id ) ) continue;
            if ( -1 === bubbahub_myhub_planner_v2_match( $row['group_id'], $interest_ids, $location_values, $row['venue_id'] ) ) continue;
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

      <?php if ( $children ) : ?>
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
          <div class="bh-planner-item-wrap" data-planner-children="<?php echo esc_attr( implode( ',', $item['child_numbers'] ) ); ?>">
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

      <script>
      document.addEventListener('DOMContentLoaded',function(){
        document.querySelectorAll('.bh-weekly-planner-v2').forEach(function(planner){
          planner.querySelectorAll('[data-planner-child-filter]').forEach(function(button){
            button.addEventListener('click',function(){
              var filter=button.getAttribute('data-planner-child-filter');
              planner.querySelectorAll('[data-planner-child-filter]').forEach(function(b){b.classList.toggle('active',b.getAttribute('data-planner-child-filter')===filter);});
              planner.querySelectorAll('.bh-planner-item-wrap').forEach(function(item){
                var kids=(item.getAttribute('data-planner-children')||'').split(',');
                item.hidden=filter!=='all'&&kids.indexOf(filter)===-1;
              });
            });
          });
        });
      });
      </script>
    </section>
    <?php return ob_get_clean();
}
