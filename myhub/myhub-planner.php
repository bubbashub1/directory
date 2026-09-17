<?php
/** BubbaHub My Hub - personalised weekly planner. */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_enqueue_scripts', 'bubbahub_myhub_planner_assets' );
add_action( 'wp_ajax_bubbahub_myhub_planner', 'bubbahub_myhub_planner_ajax' );
add_shortcode( 'bubbahub_weekly_planner', 'bubbahub_myhub_planner_shortcode' );

function bubbahub_myhub_planner_assets() {
    if ( ! is_user_logged_in() ) return;
    wp_register_style( 'bubbahub-myhub-planner', BUBBAHUB_MYHUB_URL . 'myhub-planner.css', array(), BUBBAHUB_MYHUB_VERSION );
    wp_register_script( 'bubbahub-myhub-planner', BUBBAHUB_MYHUB_URL . 'myhub-planner.js', array( 'jquery' ), BUBBAHUB_MYHUB_VERSION, true );
    wp_localize_script( 'bubbahub-myhub-planner', 'BubbaHubMyHubPlanner', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'bubbahub_myhub_planner' ),
    ) );
}

function bubbahub_myhub_planner_field( $id, $key, $default = '' ) {
    if ( function_exists( 'bubbahub_directory_get_field' ) ) return bubbahub_directory_get_field( $id, $key, $default );
    $v = get_post_meta( $id, $key, true );
    return ( '' !== $v && false !== $v ) ? $v : $default;
}

function bubbahub_myhub_planner_terms( $taxonomy ) {
    if ( ! taxonomy_exists( $taxonomy ) ) return array();
    $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
    return is_wp_error( $terms ) ? array() : $terms;
}

function bubbahub_myhub_planner_user_preferences() {
    $uid = get_current_user_id();
    $interests = get_user_meta( $uid, 'user_interests', true );
    $locations = get_user_meta( $uid, 'preferred_locations', true );
    if ( function_exists( 'get_field' ) ) {
        $i = get_field( 'user_interests', 'user_' . $uid, false );
        $l = get_field( 'preferred_locations', 'user_' . $uid, false );
        if ( null !== $i && false !== $i && '' !== $i ) $interests = $i;
        if ( null !== $l && false !== $l && '' !== $l ) $locations = $l;
    }
    $normalise = function( $value ) {
        if ( ! is_array( $value ) ) $value = '' === $value || null === $value ? array() : preg_split( '/\s*,\s*/', (string) $value );
        return array_values( array_filter( array_map( function( $v ) { return is_object( $v ) && isset( $v->term_id ) ? absint( $v->term_id ) : ( is_array( $v ) && isset( $v['term_id'] ) ? absint( $v['term_id'] ) : ( is_numeric( $v ) ? absint( $v ) : sanitize_key( $v ) ) ); }, $value ) ) );
    };
    return array( $normalise( $interests ), $normalise( $locations ) );
}

function bubbahub_myhub_planner_age_matches( $post_id, $child_ids ) {
    if ( ! $child_ids ) return 0;
    $tokens = array();
    foreach ( $child_ids as $child_id ) {
        $status = bubbahub_myhub_planner_field( $child_id, 'child_status' );
        if ( 'expecting' === sanitize_key( $status ) ) { $tokens[] = 'pregnancy'; $tokens[] = 'antenatal'; $tokens[] = 'postnatal'; continue; }
        $dob = bubbahub_myhub_planner_field( $child_id, 'child_date_of_birth' );
        if ( ! $dob ) continue;
        try { $birth = new DateTime( $dob ); $today = new DateTime( 'today' ); } catch ( Exception $e ) { continue; }
        if ( $birth > $today ) continue;
        $months = (int) $birth->diff( $today )->y * 12 + (int) $birth->diff( $today )->m;
        if ( $months < 3 ) $tokens[] = '0-3';
        elseif ( $months < 6 ) $tokens[] = '3-6';
        elseif ( $months < 9 ) $tokens[] = '6-9';
        elseif ( $months < 12 ) $tokens[] = '9-12';
        elseif ( $months < 24 ) $tokens[] = '1-3';
        elseif ( $months < 36 ) $tokens[] = '2-4';
        elseif ( $months < 60 ) $tokens[] = '3-5';
        else $tokens[] = '5-plus';
    }
    if ( ! $tokens ) return 0;
    $value = bubbahub_myhub_planner_field( $post_id, 'age_range' );
    $hay = strtolower( is_array( $value ) ? implode( ' ', array_map( 'strval', $value ) ) : (string) $value );
    foreach ( $tokens as $token ) if ( $hay && false !== strpos( $hay, strtolower( $token ) ) ) return 3;
    return 0;
}

function bubbahub_myhub_planner_tax_match( $post_id, $taxonomies, $wanted ) {
    if ( ! $wanted ) return 0;
    foreach ( $taxonomies as $taxonomy ) {
        if ( ! taxonomy_exists( $taxonomy ) ) continue;
        $terms = get_the_terms( $post_id, $taxonomy );
        if ( is_wp_error( $terms ) || ! $terms ) continue;
        foreach ( $terms as $term ) {
            foreach ( $wanted as $want ) {
                $want = is_numeric( $want ) ? absint( $want ) : sanitize_key( $want );
                if ( ( is_int( $want ) && $want === (int) $term->term_id ) || ( ! is_int( $want ) && ( $want === $term->slug || strtolower( $want ) === strtolower( $term->name ) ) ) ) return 1;
            }
        }
    }
    return 0;
}

function bubbahub_myhub_planner_schedule_rows( $post_id ) {
    $value = bubbahub_myhub_planner_field( $post_id, 'business_hours', '' );
    if ( '' === $value ) $value = bubbahub_myhub_planner_field( $post_id, 'schedule', '' );
    if ( '' === $value ) $value = bubbahub_myhub_planner_field( $post_id, 'timetable', '' );
    if ( is_string( $value ) ) {
        $decoded = json_decode( $value, true );
        if ( JSON_ERROR_NONE === json_last_error() ) $value = $decoded;
    }
    $rows = array();
    $days = array( 'monday'=>'Monday','mon'=>'Monday','tuesday'=>'Tuesday','tue'=>'Tuesday','wednesday'=>'Wednesday','wed'=>'Wednesday','thursday'=>'Thursday','thu'=>'Thursday','friday'=>'Friday','fri'=>'Friday','saturday'=>'Saturday','sat'=>'Saturday','sunday'=>'Sunday','sun'=>'Sunday' );
    $add = function( $day, $hours ) use ( &$rows, $days ) {
        $key = strtolower( trim( (string) $day ) );
        if ( isset( $days[ $key ] ) ) $day = $days[ $key ];
        else return;
        if ( is_array( $hours ) ) {
            if ( isset( $hours['day'] ) ) { $day = $hours['day']; unset( $hours['day'] ); }
            foreach ( array( 'hours','time','times','opening','open' ) as $k ) if ( isset( $hours[ $k ] ) ) { $hours = $hours[ $k ]; break; }
            if ( is_array( $hours ) ) $hours = implode( ' – ', array_filter( array_map( 'strval', $hours ) ) );
        }
        $hours = trim( wp_strip_all_tags( (string) $hours ) );
        if ( $hours && ! preg_match( '/closed|close/i', $hours ) ) $rows[ $day ][] = $hours;
    };
    if ( is_array( $value ) ) {
        foreach ( $value as $key => $item ) {
            if ( is_string( $key ) && isset( $days[ strtolower( $key ) ] ) ) { $add( $key, $item ); continue; }
            if ( is_array( $item ) ) {
                $day = isset( $item['day'] ) ? $item['day'] : ( isset( $item['weekday'] ) ? $item['weekday'] : ( isset( $item['week_day'] ) ? $item['week_day'] : '' ) );
                if ( $day ) { $add( $day, $item ); continue; }
            }
            if ( is_string( $item ) ) {
                if ( preg_match( '/^(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday|Mon|Tue|Wed|Thu|Fri|Sat|Sun)\s*[:\-]\s*(.+)$/i', $item, $m ) ) $add( $m[1], $m[2] );
                elseif ( preg_match( '/^\[?(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday|Mon|Tue|Wed|Thu|Fri|Sat|Sun)\]?\s+(.+)$/i', $item, $m ) ) $add( $m[1], $m[2] );
            }
        }
    } elseif ( is_string( $value ) && $value ) {
        foreach ( preg_split( '/\r?\n|;/', $value ) as $line ) {
            if ( preg_match( '/^\s*(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday|Mon|Tue|Wed|Thu|Fri|Sat|Sun)\s*[:\-]\s*(.+)$/i', $line, $m ) ) $add( $m[1], $m[2] );
        }
    }
    return $rows;
}

function bubbahub_myhub_planner_items( $child_ids, $interest_filters, $location_filters ) {
    $q = new WP_Query( array( 'post_type'=>'group','post_status'=>'publish','posts_per_page'=>150,'orderby'=>'title','order'=>'ASC','no_found_rows'=>true ) );
    $items = array();
    while ( $q->have_posts() ) { $q->the_post(); $id = get_the_ID(); $schedule = bubbahub_myhub_planner_schedule_rows( $id ); if ( ! $schedule ) continue;
        $age_score = bubbahub_myhub_planner_age_matches( $id, $child_ids );
        $interest_score = bubbahub_myhub_planner_tax_match( $id, array( 'user-interests', 'interest', 'interests' ), $interest_filters );
        $location_score = bubbahub_myhub_planner_tax_match( $id, array( 'preferred-location', 'preferred_locations', 'location', 'region' ), $location_filters );
        if ( $interest_filters && ! $interest_score ) continue;
        if ( $location_filters && ! $location_score ) continue;
        if ( $child_ids && ! $age_score ) continue;
        $items[] = array( 'id'=>$id, 'title'=>get_the_title(), 'url'=>get_permalink(), 'image'=>function_exists('bubbahub_directory_image_url') ? bubbahub_directory_image_url($id) : get_the_post_thumbnail_url($id,'thumbnail'), 'schedule'=>$schedule, 'score'=>$age_score + ( $interest_score * 2 ) + ( $location_score * 2 ) );
    }
    wp_reset_postdata();
    usort( $items, function( $a, $b ) { return $b['score'] <=> $a['score']; } );
    return $items;
}

function bubbahub_myhub_planner_render( $child_ids = array(), $interest_filters = array(), $location_filters = array() ) {
    $items = bubbahub_myhub_planner_items( $child_ids, $interest_filters, $location_filters );
    $days = array( 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday' );
    $by_day = array_fill_keys( $days, array() );
    foreach ( $items as $item ) foreach ( $item['schedule'] as $day => $hours ) foreach ( $hours as $hour ) $by_day[ $day ][] = array( 'item'=>$item, 'hours'=>$hour );
    ob_start();
    foreach ( $days as $day ) : $entries = isset( $by_day[ $day ] ) ? $by_day[ $day ] : array(); ?>
        <div class="bh-planner-day"><div class="bh-planner-day-title"><?php echo esc_html( $day ); ?></div><div class="bh-planner-day-items">
        <?php if ( $entries ) : foreach ( $entries as $entry ) : $item=$entry['item']; ?>
            <a class="bh-planner-item" href="<?php echo esc_url( $item['url'] ); ?>">
                <span class="bh-planner-thumb"><?php if ( $item['image'] ) : ?><img src="<?php echo esc_url( $item['image'] ); ?>" alt="" loading="lazy"><?php else : ?><span class="bh-planner-placeholder" aria-hidden="true">♡</span><?php endif; ?></span>
                <span class="bh-planner-item-main"><strong><?php echo esc_html( $item['title'] ); ?></strong><span class="bh-planner-hours"><?php echo esc_html( $entry['hours'] ); ?></span></span><span class="bh-planner-arrow" aria-hidden="true">→</span>
            </a>
        <?php endforeach; else : ?><div class="bh-planner-empty">No matching activities with published hours.</div><?php endif; ?></div></div>
    <?php endforeach; return ob_get_clean();
}

function bubbahub_myhub_planner_shortcode() {
    if ( ! is_user_logged_in() ) return '';
    wp_enqueue_style( 'bubbahub-myhub-planner' ); wp_enqueue_script( 'bubbahub-myhub-planner' );
    list( $interests, $locations ) = bubbahub_myhub_planner_user_preferences();
    $children = get_posts( array( 'post_type'=>'bh_child','post_status'=>'publish','author'=>get_current_user_id(),'posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true ) );
    ob_start(); ?><section class="bh-myhub-section bh-weekly-planner" data-bh-weekly-planner><div class="bh-myhub-section-heading"><div><div class="bh-myhub-kicker">YOUR WEEK</div><h2>Weekly Planner</h2><p>A simple week view built around your children, interests and preferred locations.</p></div></div><div class="bh-planner-controls"><div><strong>Children</strong><div class="bh-planner-pills"><?php foreach($children as $id):?><label><input type="checkbox" data-planner-child value="<?php echo esc_attr($id); ?>" checked><span><?php echo esc_html(get_the_title($id));?></span></label><?php endforeach;?></div></div><div><strong>Interests</strong><input type="text" data-planner-interest placeholder="Use your saved interests or add a filter"></div><div><strong>Preferred location</strong><input type="text" data-planner-location placeholder="Use your saved preferred locations or add a filter"></div><button type="button" class="bh-myhub-button" data-planner-refresh>Update planner</button></div><div class="bh-planner-results" data-planner-results><?php echo bubbahub_myhub_planner_render(array_map('absint',$children),$interests,$locations);?></div></section><?php return ob_get_clean();
}

function bubbahub_myhub_planner_ajax() {
    if ( ! is_user_logged_in() ) wp_send_json_error( array('message'=>'Please log in.'), 401 );
    check_ajax_referer( 'bubbahub_myhub_planner', 'nonce' );
    $children = isset($_POST['children']) ? json_decode(wp_unslash($_POST['children']),true) : array();
    $children = array_map('absint',(array)$children); $owned = get_posts(array('post_type'=>'bh_child','post_status'=>'publish','author'=>get_current_user_id(),'posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true)); $children=array_values(array_intersect($children,array_map('absint',$owned)));
    $interest = isset($_POST['interest']) ? sanitize_text_field(wp_unslash($_POST['interest'])) : '';
    $location = isset($_POST['location']) ? sanitize_text_field(wp_unslash($_POST['location'])) : '';
    list($saved_i,$saved_l)=bubbahub_myhub_planner_user_preferences();
    if($interest) $saved_i=array($interest); if($location) $saved_l=array($location);
    wp_send_json_success(array('html'=>bubbahub_myhub_planner_render($children,$saved_i,$saved_l)));
}
