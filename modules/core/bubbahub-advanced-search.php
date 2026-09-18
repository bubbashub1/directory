<?php
/**
 * BubbaHub Advanced Directory Search
 * Dynamically exposes supported ACF fields plus region, category, schedule day,
 * term-time and location/radius filters.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'pre_do_shortcode_tag', 'bubbahub_advanced_search_shortcode', 10, 4 );
add_action( 'wp_ajax_bubbahub_directory_filter', 'bubbahub_advanced_search_ajax', 1 );
add_action( 'wp_ajax_nopriv_bubbahub_directory_filter', 'bubbahub_advanced_search_ajax', 1 );

function bubbahub_advanced_search_acf_fields() {
    $fields = array();
    if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) return $fields;
    foreach ( acf_get_field_groups( array( 'post_type' => 'group' ) ) as $group ) {
        foreach ( (array) acf_get_fields( $group ) as $field ) {
            if ( empty( $field['name'] ) || in_array( $field['name'], array( 'term_time','age_range','business_hours','schedule','timetable','image','map','email','website','phone' ), true ) ) continue;
            if ( ! in_array( $field['type'], array( 'select','radio','checkbox','button_group','true_false' ), true ) ) continue;
            if ( empty( $field['choices'] ) && 'true_false' !== $field['type'] ) continue;
            $fields[ $field['name'] ] = $field;
        }
    }
    return $fields;
}
function bubbahub_advanced_search_category_tax() {
    $preferred = array( 'category','group_category','group-category','listing_category','listing-category' );
    $tax = get_object_taxonomies( 'group', 'objects' );
    foreach ( $preferred as $name ) if ( isset( $tax[ $name ] ) && $tax[ $name ]->public ) return $name;
    foreach ( $tax as $name => $obj ) if ( 'region' !== $name && 'post_tag' !== $name && $obj->public ) return $name;
    return '';
}
function bubbahub_advanced_search_location_keys() {
    return array( 'address','street','street_address','city','town','postcode','zip','location','venue','venue_name' );
}
function bubbahub_advanced_search_schedule_ids( $day = '', $term = '' ) {
    $ids = array();
    $sessions = get_posts( array( 'post_type'=>'bh_session','post_status'=>array('publish','draft','private'),'posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true ) );
    foreach ( $sessions as $sid ) {
        $gid = absint( get_post_meta( $sid, '_bh_group_id', true ) );
        if ( ! $gid ) continue;
        $ok = true;
        if ( $day ) {
            $weekday = strtolower( trim( (string) get_post_meta( $sid, '_bh_recurrence_weekday', true ) ) );
            $date = get_post_meta( $sid, '_bh_date', true );
            $date_day = $date && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? strtolower( wp_date( 'l', strtotime( $date ) ) ) : '';
            $ok = in_array( strtolower( $day ), array( $weekday, $date_day ), true );
        }
        if ( $ok && $term ) {
            $v = strtolower( trim( (string) get_post_meta( $sid, '_bh_term_time', true ) ) );
            $is_term = in_array( $v, array( '1','true','yes','on' ), true );
            $ok = 'yes' === $term ? $is_term : ! $is_term;
        }
        if ( $ok ) $ids[] = $gid;
    }
    return array_values( array_unique( $ids ) );
}
function bubbahub_advanced_search_nearby_ids( $lat, $lng, $radius ) {
    $ids = get_posts( array( 'post_type'=>'group','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true ) );
    $out = array(); $radius = max( 1, min( 100, (float) $radius ) );
    foreach ( $ids as $id ) {
        $map = function_exists( 'bubbahub_directory_get_field' ) ? bubbahub_directory_get_field( $id, 'map', array() ) : get_post_meta( $id, 'map', true );
        $coords = function_exists( 'bubbahub_directory_normalise_map' ) ? bubbahub_directory_normalise_map( $map ) : null;
        if ( ! $coords ) {
            $a = function_exists( 'bubbahub_directory_get_field' ) ? bubbahub_directory_get_field( $id, 'latitude', '' ) : get_post_meta( $id, 'latitude', true );
            $o = function_exists( 'bubbahub_directory_get_field' ) ? bubbahub_directory_get_field( $id, 'longitude', '' ) : get_post_meta( $id, 'longitude', true );
            if ( '' === $a ) $a = get_post_meta( $id, 'lat', true );
            if ( '' === $o ) $o = get_post_meta( $id, 'lng', true );
            if ( '' !== $a && '' !== $o ) $coords = array( 'lat'=>(float)$a, 'lng'=>(float)$o );
        }
        if ( ! $coords ) continue;
        $x = sin((deg2rad($coords['lat'])-deg2rad($lat))/2)**2 + cos(deg2rad($lat))*cos(deg2rad($coords['lat']))*sin(deg2rad($coords['lng']-$lng)/2)**2;
        $distance = 3958.7613 * 2 * atan2( sqrt($x), sqrt(max(0,1-$x)) );
        if ( $distance <= $radius ) $out[] = $id;
    }
    return array_values( array_unique( $out ) );
}
function bubbahub_advanced_search_query( $f, $pp = 12 ) {
    $args = array( 'post_type'=>'group','post_status'=>'publish','posts_per_page'=>max(1,min(100,(int)$pp)),'paged'=>max(1,(int)($f['paged']??1)),'s'=>sanitize_text_field($f['search']??''),'orderby'=>'date','order'=>'DESC' );
    $tax_query = array();
    if ( ! empty($f['region']) ) $tax_query[] = array('taxonomy'=>'region','field'=>'slug','terms'=>sanitize_title($f['region']));
    $cat_tax = bubbahub_advanced_search_category_tax();
    if ( ! empty($f['town']) ) { $tax_query[] = array('taxonomy'=>'region','field'=>'slug','terms'=>sanitize_title($f['town'])); }
    if ( ! empty($f['category']) && $cat_tax ) $tax_query[] = array('taxonomy'=>$cat_tax,'field'=>'slug','terms'=>sanitize_title($f['category']));
    if($tax_query){$tax_query['relation']='AND';$args['tax_query']=$tax_query;}
    $mq=array();
    if(!empty($f['age'])){ $ages=is_array($f['age'])?$f['age']:array($f['age']); $or=array('relation'=>'OR'); foreach($ages as $age)$or[]=array('key'=>'age_range','value'=>sanitize_text_field($age),'compare'=>'LIKE'); if(count($or)>1)$mq[]=$or; }
    if('free'===($f['price']??''))$mq[]=array('key'=>'price','value'=>'Free','compare'=>'LIKE');
    if('paid'===($f['price']??''))$mq[]=array('key'=>'price','value'=>'Free','compare'=>'NOT LIKE');
    if(!empty($f['location'])){$or=array('relation'=>'OR');foreach(bubbahub_advanced_search_location_keys() as $k)$or[]=array('key'=>$k,'value'=>sanitize_text_field($f['location']),'compare'=>'LIKE');$mq[]=$or;}
    $allowed=bubbahub_advanced_search_acf_fields();
    foreach((array)($f['acf']??array()) as $name=>$value){
        $name=sanitize_key($name);if(!isset($allowed[$name])||''===$value)continue;
        if(is_array($value)){ $or=array('relation'=>'OR');foreach($value as $v)if(''!==trim((string)$v))$or[]=array('key'=>$name,'value'=>sanitize_text_field($v),'compare'=>'LIKE');if(count($or)>1)$mq[]=$or; }
        elseif('true_false'===$allowed[$name]['type'])$mq[]=array('key'=>$name,'value'=>in_array(strtolower((string)$value),array('1','true','yes','on'),true)?'1':'0','compare'=>'=');
        else $mq[]=array('key'=>$name,'value'=>sanitize_text_field($value),'compare'=>'LIKE');
    }
    if($mq){$mq['relation']='AND';$args['meta_query']=$mq;}
    $sets=array();
    if(!empty($f['day']))$sets[]=bubbahub_advanced_search_schedule_ids($f['day']);
    if('yes'===($f['term_time']??'')){$term=get_posts(array('post_type'=>'group','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids','meta_key'=>'term_time','meta_value'=>'1','no_found_rows'=>true));$sets[]=array_unique(array_merge($term,bubbahub_advanced_search_schedule_ids('','yes')));}
    if('no'===($f['term_time']??'')){$term=get_posts(array('post_type'=>'group','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids','meta_key'=>'term_time','meta_value'=>'1','no_found_rows'=>true));$all=get_posts(array('post_type'=>'group','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true));$sets[]=array_diff($all,$term);}
    if(!empty($f['lat'])&&!empty($f['lng']))$sets[]=bubbahub_advanced_search_nearby_ids((float)$f['lat'],(float)$f['lng'],(float)($f['radius']??25));
    if($sets){$ids=array_shift($sets);foreach($sets as $set)$ids=array_values(array_intersect($ids,$set));$args['post__in']=$ids?$ids:array(0);}
    return new WP_Query($args);
}
function bubbahub_advanced_search_filters_from_request( $source ) {
    $acf=isset($source['acf'])&&is_array($source['acf'])?$source['acf']:(isset($source['bh_acf'])&&is_array($source['bh_acf'])?$source['bh_acf']:array());foreach($acf as $k=>$v)$acf[$k]=is_array($v)?array_map('sanitize_text_field',wp_unslash($v)):sanitize_text_field(wp_unslash($v));
    return array('search'=>sanitize_text_field(wp_unslash($source['search']??$source['bh_search']??'')),'region'=>sanitize_title(wp_unslash($source['region']??$source['bh_region']??'')),'town'=>sanitize_title(wp_unslash($source['town']??$source['bh_town']??'')),'age'=>isset($source['age'])?array_map('sanitize_text_field',(array)wp_unslash($source['age'])):(isset($source['bh_age'])?array_map('sanitize_text_field',(array)wp_unslash($source['bh_age'])):array()),'price'=>sanitize_text_field(wp_unslash($source['price']??$source['bh_price']??'')),'location'=>sanitize_text_field(wp_unslash($source['location']??$source['bh_location']??'')),'category'=>sanitize_title(wp_unslash($source['category']??$source['bh_category']??'')),'day'=>sanitize_key(wp_unslash($source['day']??$source['bh_day']??'')),'term_time'=>sanitize_key(wp_unslash($source['term_time']??$source['bh_term_time']??'')),'acf'=>$acf,'lat'=>(float)($source['lat']??$source['bh_lat']??0),'lng'=>(float)($source['lng']??$source['bh_lng']??0),'radius'=>max(1,min(100,(float)($source['radius']??$source['bh_radius']??25))),'paged'=>max(1,(int)($source['paged']??1)));
}
function bubbahub_advanced_search_ajax(){
    check_ajax_referer('bubbahub_directory','nonce');
    $f=bubbahub_advanced_search_filters_from_request($_POST);
    $q=bubbahub_advanced_search_query($f,isset($_POST['postsPerPage'])?$_POST['postsPerPage']:12);
    wp_send_json_success(array('html'=>bubbahub_directory_render_cards($q),'pagination'=>bubbahub_directory_render_pagination($q),'count'=>(int)$q->found_posts));
}
function bubbahub_advanced_search_shortcode($output,$tag,$attr,$m){
    if('bubbahub_directory'!==$tag)return false;
    $f=bubbahub_advanced_search_filters_from_request($_GET);
    $q=bubbahub_advanced_search_query($f,12);
    $regions=get_terms(array('taxonomy'=>'region','hide_empty'=>false,'parent'=>0));$towns=get_terms(array('taxonomy'=>'region','hide_empty'=>false,'parent'=>!empty($f['region']) ? (int)term_exists($f['region'],'region') : 0));$cat_tax=bubbahub_advanced_search_category_tax();$categories=$cat_tax?get_terms(array('taxonomy'=>$cat_tax,'hide_empty'=>false)):array();$acf=bubbahub_advanced_search_acf_fields();
    $days=array('monday'=>'Monday','tuesday'=>'Tuesday','wednesday'=>'Wednesday','thursday'=>'Thursday','friday'=>'Friday','saturday'=>'Saturday','sunday'=>'Sunday');
    wp_enqueue_style('bubbahub-directory');wp_enqueue_script('bubbahub-directory');wp_enqueue_style('leaflet');wp_enqueue_script('leaflet');$nonce=wp_create_nonce('bubbahub_directory');
    ob_start();?>
    <div class="bh-directory" data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php'));?>" data-nonce="<?php echo esc_attr($nonce);?>">
      <form class="bh-search-form" method="get">
        <div class="bh-search-main"><label for="bh-search">Search groups</label><input id="bh-search" name="bh_search" type="search" value="<?php echo esc_attr($f['search']);?>" placeholder="Search by group, class or area"></div>
        <div class="bh-search-location"><label for="bh-location">Location</label><div class="bh-location-control"><input id="bh-location" name="bh_location" type="search" value="<?php echo esc_attr($f['location']);?>" placeholder="Town, postcode or area"><button type="button" class="bh-use-location" aria-label="Use my location" title="Use my location">⌖</button></div></div>
        <div class="bh-search-actions"><button type="submit">Search</button><button type="button" class="bh-advanced-toggle" aria-expanded="false">Advanced search <span aria-hidden="true">⌄</span></button></div>
        <div class="bh-advanced-search" hidden>
          <div class="bh-filter-option"><span class="bh-filter-title">Region</span><label class="screen-reader-text" for="bh-region">Region</label><select id="bh-region" name="bh_region"><option value="">All regions</option><?php if(!is_wp_error($regions))foreach($regions as $t):?><option value="<?php echo esc_attr($t->slug);?>" <?php selected($f['region'],$t->slug);?>><?php echo esc_html($t->name);?></option><?php endforeach;?></select></div>
          <div class="bh-filter-option"><span class="bh-filter-title">Town</span><label class="screen-reader-text" for="bh-town">Town</label><select id="bh-town" name="bh_town"><option value="">All towns</option><?php if(!is_wp_error($towns))foreach($towns as $t):?><option value="<?php echo esc_attr($t->slug);?>" <?php selected($f['town']??'',$t->slug);?>><?php echo esc_html($t->name);?></option><?php endforeach;?></select></div>
          <div class="bh-filter-option"><span class="bh-filter-title">Choose a category</span><label class="screen-reader-text" for="bh-category">Choose a category</label><select id="bh-category" name="bh_category"><option value="">All categories</option><?php foreach((array)$categories as $t):?><option value="<?php echo esc_attr($t->slug);?>" <?php selected($f['category'],$t->slug);?>><?php echo esc_html($t->name);?></option><?php endforeach;?></select></div>
          <div class="bh-filter-option"><span class="bh-filter-title">Choose a day</span><label class="screen-reader-text" for="bh-day">Choose a day</label><select id="bh-day" name="bh_day"><option value="">Any day</option><?php foreach($days as $v=>$label):?><option value="<?php echo esc_attr($v);?>" <?php selected($f['day'],$v);?>><?php echo esc_html($label);?></option><?php endforeach;?></select></div>
          <div class="bh-filter-option"><span class="bh-filter-title">Choose when the group runs</span><label class="screen-reader-text" for="bh-term-time">Choose when the group runs</label><select id="bh-term-time" name="bh_term_time"><option value="">Any time of year</option><option value="yes" <?php selected($f['term_time'],'yes');?>>Term time only</option><option value="no" <?php selected($f['term_time'],'no');?>>Not term time only</option></select></div>
          <div class="bh-filter-option"><span class="bh-filter-title">Choose an age range</span><label class="screen-reader-text" for="bh-age">Choose an age range</label><select id="bh-age" name="bh_age"><option value="">All ages</option><?php foreach((array)bubbahub_directory_age_values() as $v):?><option value="<?php echo esc_attr($v);?>" <?php selected($f['age'],$v);?>><?php echo esc_html($v);?></option><?php endforeach;?></select></div>
          <div class="bh-filter-option"><span class="bh-filter-title">Choose a price</span><label class="screen-reader-text" for="bh-price">Choose a price</label><select id="bh-price" name="bh_price"><option value="">Any price</option><option value="free" <?php selected($f['price'],'free');?>>Free</option><option value="paid" <?php selected($f['price'],'paid');?>>Paid</option></select></div>
          <?php foreach($acf as $name=>$field):$choices=bubbahub_advanced_search_acf_fields()[$name]['choices']??array();if('true_false'===$field['type'])$choices=array('1'=>'Yes','0'=>'No');?><div class="bh-acf-filter"><span class="bh-filter-title"><?php echo esc_html($field['label']?:ucwords(str_replace('_',' ',$name)));?></span><label class="screen-reader-text" for="bh-acf-<?php echo esc_attr($name);?>"><?php echo esc_html($field['label']?:ucwords(str_replace('_',' ',$name)));?></label><select id="bh-acf-<?php echo esc_attr($name);?>" name="bh_acf[<?php echo esc_attr($name);?>]"><option value="">Any</option><?php foreach((array)$choices as $v=>$label):?><option value="<?php echo esc_attr($v);?>" <?php selected($f['acf'][$name]??'',$v);?>><?php echo esc_html($label);?></option><?php endforeach;?></select></div><?php endforeach;?>
          <div class="bh-filter-option"><span class="bh-filter-title">Choose your search radius</span><label class="screen-reader-text" for="bh-radius">Choose your search radius</label><select id="bh-radius" name="bh_radius"><option value="5">5 miles</option><option value="10">10 miles</option><option value="25" selected>25 miles</option><option value="50">50 miles</option></select></div>
        </div>
        <input type="hidden" name="bh_lat" value=""><input type="hidden" name="bh_lng" value="">
      </form>
      <div class="bh-directory-toolbar"><strong class="bh-result-count"><?php echo esc_html(number_format_i18n($q->found_posts));?> groups</strong><button type="button" class="bh-view-toggle" data-view="grid">Grid / Map</button></div>
      <div class="bh-directory-content"><div class="bh-directory-results"><?php echo bubbahub_directory_render_cards($q);?><?php echo bubbahub_directory_render_pagination($q);?></div><div class="bh-directory-map" aria-label="Group map"></div></div>
    </div>
    <?php return ob_get_clean();
}
