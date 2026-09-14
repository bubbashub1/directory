<?php
/** BubbaHub My Hub v2 dashboard layer. */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'bubbahub_myhub_v2_setup', 30 );
add_action( 'init', 'bubbahub_myhub_v2_save_preferences', 5 );
add_action( 'init', 'bubbahub_myhub_v2_save_child', 5 );

function bubbahub_myhub_v2_setup() {
    remove_shortcode( 'bubbahub_my_hub' );
    add_shortcode( 'bubbahub_my_hub', 'bubbahub_myhub_v2_shortcode' );
}

function bubbahub_myhub_v2_user_value( $name, $default = array() ) {
    $id = get_current_user_id();
    if ( function_exists( 'get_field' ) ) {
        $v = get_field( $name, 'user_' . $id, false );
        if ( $v !== null && $v !== false && $v !== '' ) return $v;
    }
    $v = get_user_meta( $id, $name, true );
    return ( $v !== '' && $v !== false ) ? $v : $default;
}

function bubbahub_myhub_v2_child_value( $id, $name, $default = '' ) {
    if ( function_exists( 'get_field' ) ) {
        $v = get_field( $name, $id, false );
        if ( $v !== null && $v !== false && $v !== '' ) return $v;
    }
    $v = get_post_meta( $id, $name, true );
    return ( $v !== '' && $v !== false ) ? $v : $default;
}

function bubbahub_myhub_v2_update_user_field( $name, $value, $key = '' ) {
    $uid = get_current_user_id();
    if ( function_exists( 'update_field' ) && $key ) update_field( $key, $value, 'user_' . $uid );
    else update_user_meta( $uid, $name, $value );
}

function bubbahub_myhub_v2_save_preferences() {
    if ( ! is_user_logged_in() || empty( $_POST['bh_myhub_preferences_nonce'] ) ) return;
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bh_myhub_preferences_nonce'] ) ), 'bh_myhub_preferences' ) ) return;
    $interests = isset( $_POST['user_interests'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['user_interests'] ) ) : array();
    $locations = isset( $_POST['preferred_locations'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['preferred_locations'] ) ) : array();
    $locations = array_values( array_filter( array_unique( $locations ) ) );
    if ( taxonomy_exists( 'location' ) ) {
        $locations = array_values( array_filter( $locations, function( $id ){ return term_exists( $id, 'location' ); } ) );
    }
    $locations = array_slice( $locations, 0, 3 );
    bubbahub_myhub_v2_update_user_field( 'user_interests', $interests, 'field_bh_user_interests' );
    bubbahub_myhub_v2_update_user_field( 'preferred_locations', $locations, 'field_bh_user_locations' );
    wp_safe_redirect( add_query_arg( 'bh_preferences_saved', '1', wp_get_referer() ?: home_url( '/my-hub/' ) ) );
    exit;
}

function bubbahub_myhub_v2_save_child() {
    if ( ! is_user_logged_in() || empty( $_POST['bh_myhub_child_nonce'] ) ) return;
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bh_myhub_child_nonce'] ) ), 'bh_myhub_child_v2' ) ) return;
    $id = absint( $_POST['child_id'] ?? 0 );
    if ( $id && ( get_post_type( $id ) !== 'bh_child' || (int) get_post_field( 'post_author', $id ) !== get_current_user_id() ) ) $id = 0;
    $name = sanitize_text_field( wp_unslash( $_POST['child_name'] ?? '' ) );
    $type = sanitize_key( wp_unslash( $_POST['child_status'] ?? 'born' ) );
    $dob = sanitize_text_field( wp_unslash( $_POST['child_date_of_birth'] ?? '' ) );
    $due = sanitize_text_field( wp_unslash( $_POST['child_due_date'] ?? '' ) );
    if ( ! $name ) return;
    $data = array( 'post_type'=>'bh_child', 'post_status'=>'publish', 'post_title'=>$name, 'post_author'=>get_current_user_id() );
    if ( $id ) { $data['ID']=$id; $saved=wp_update_post($data,true); } else $saved=wp_insert_post($data,true);
    if ( is_wp_error($saved) ) return;
    $fields=array('child_name'=>$name,'child_status'=>$type,'child_date_of_birth'=>$dob,'child_due_date'=>$due);
    foreach($fields as $k=>$v){ if(function_exists('update_field')) update_field($k,$v,$saved); else update_post_meta($saved,$k,$v); }
    wp_safe_redirect( add_query_arg('bh_child_saved','1',wp_get_referer() ?: home_url('/my-hub/')) ); exit;
}

function bubbahub_myhub_v2_month_year( $date ) { return $date ? wp_date('F Y',strtotime($date)) : ''; }
function bubbahub_myhub_v2_pregnancy( $due ) {
    if ( ! $due ) return array();
    $d = new DateTime($due); $today = new DateTime('today');
    $start = clone $d; $start->modify('-12 weeks'); $end = clone $d; $end->modify('-8 weeks');
    $left = $today <= $d ? $today->diff($d) : null;
    return array('start'=>$start->format('Y-m-d'),'end'=>$end->format('Y-m-d'),'weeks_left'=>$left ? $left->days : 0,'days_left'=>$left ? $left->d : 0,'past'=>$today>$d);
}
function bubbahub_myhub_v2_age_buckets( $dob ) {
    if(!$dob) return array(); $b=new DateTime($dob); $t=new DateTime('today'); if($b>$t)return array(); $a=$b->diff($t); $m=($a->y*12)+$a->m;
    $r=array();
    if($m<3)$r=array('0-3','0 - 3 months'); elseif($m<6)$r=array('3-6','3 - 6 months'); elseif($m<9)$r=array('6-9','6 - 9 months'); elseif($m<12)$r=array('9-12','9 - 12 months');
    elseif($m<24)$r=array('1-3','1 - 3 years'); elseif($m<36)$r=array('1-3','1 - 3 years','2-4','2 - 4 years'); elseif($m<48)$r=array('2-4','2 - 4 years','3-5','3 - 5 years'); elseif($m<60)$r=array('3-5','3 - 5 years','5-plus','5+'); else $r=array('5-plus','5+');
    return $r;
}
function bubbahub_myhub_v2_children() {
    $q=new WP_Query(array('post_type'=>'bh_child','post_status'=>'publish','author'=>get_current_user_id(),'posts_per_page'=>-1,'orderby'=>'date','order'=>'ASC','no_found_rows'=>true)); $out=array();
    while($q->have_posts()){ $q->the_post(); $id=get_the_ID(); $out[$id]=array('id'=>$id,'name'=>bubbahub_myhub_v2_child_value($id,'child_name',get_the_title()),'status'=>bubbahub_myhub_v2_child_value($id,'child_status','born'),'dob'=>bubbahub_myhub_v2_child_value($id,'child_date_of_birth'),'due'=>bubbahub_myhub_v2_child_value($id,'child_due_date'),'age'=>bubbahub_myhub_v2_age_buckets(bubbahub_myhub_v2_child_value($id,'child_date_of_birth'))); }
    wp_reset_postdata(); return $out;
}
function bubbahub_myhub_v2_location_names($ids){$r=array();foreach((array)$ids as $id){$t=get_term((int)$id,'location');if($t&&!is_wp_error($t))$r[]=$t->name;}return $r;}
function bubbahub_myhub_v2_interest_choices(){return array('music'=>'Music','sensory'=>'Sensory','baby_classes'=>'Baby classes','toddler'=>'Toddler activities','messy_play'=>'Messy play','soft_play'=>'Soft play','outdoor'=>'Outdoor play','swimming'=>'Swimming','dance'=>'Dance','fitness'=>'Parent & baby fitness','support'=>'Parent support','antenatal'=>'Antenatal','postnatal'=>'Postnatal','storytime'=>'Story time','craft'=>'Arts & crafts');}
function bubbahub_myhub_v2_local_authority($location_ids){foreach((array)$location_ids as $id){if(function_exists('get_field')){$deadline=get_field('school_primary_application_deadline',$id);$open=get_field('school_primary_application_open',$id);$url=get_field('school_admissions_url',$id);}else{$deadline=get_term_meta($id,'school_primary_application_deadline',true);$open=get_term_meta($id,'school_primary_application_open',true);$url=get_term_meta($id,'school_admissions_url',true);}if($deadline||$open||$url)return array('name'=>get_term($id,'location')->name,'deadline'=>$deadline,'open'=>$open,'url'=>$url);}return array();}
function bubbahub_myhub_v2_school_deadline($dob,$la){if(!$dob)return ''; $b=new DateTime($dob);$today=new DateTime('today');$entryYear=(int)$b->format('Y')+5;if((int)$b->format('m')>8||((int)$b->format('m')===8&&(int)$b->format('d')>31))$entryYear++;$default=$entryYear.'-01-15';return !empty($la['deadline']) ? $la['deadline'] : $default;}

function bubbahub_myhub_v2_shortcode(){
    if(!is_user_logged_in())return '<div class="bh-myhub-login"><h2>Welcome to My Hub</h2><p>Please log in to see your family dashboard.</p></div>';
    wp_enqueue_style('bubbahub-myhub'); wp_enqueue_style('bubbahub-myhub-groups'); wp_enqueue_script('bubbahub-myhub-groups');
    $u=wp_get_current_user(); $children=bubbahub_myhub_v2_children(); $interests=(array)bubbahub_myhub_v2_user_value('user_interests',array()); $locations=array_map('absint',(array)bubbahub_myhub_v2_user_value('preferred_locations',array())); $locations=array_slice(array_values(array_filter($locations)),0,3); $choices=bubbahub_myhub_v2_interest_choices(); $la=bubbahub_myhub_v2_local_authority($locations); $selected=(array)get_user_meta(get_current_user_id(),'bh_myhub_selected_children',true); $selected=array_map('absint',$selected);
    $edit_id=absint($_GET['child_id']??0); if($edit_id&&!isset($children[$edit_id]))$edit_id=0;
    ob_start(); ?>
    <div class="bh-myhub bh-myhub-v2">
      <section class="bh-myhub-hero"><div><div class="bh-myhub-kicker">MY HUB</div><h1>Welcome back, <?php echo esc_html($u->first_name?:$u->display_name); ?></h1><p>Your family overview, interests, local activities and pregnancy journey — all in one place.</p></div><div class="bh-myhub-next"><div class="bh-myhub-eyebrow">YOUR FAMILY HUB</div><strong>Find activities that fit your family</strong><p>Tell us what you enjoy and where you want to go, then we will use those preferences to personalise your groups.</p></div></section>
      <?php if(isset($_GET['bh_preferences_saved'])||isset($_GET['bh_child_saved'])): ?><div class="bh-myhub-notice">Your My Hub details have been updated.</div><?php endif; ?>

      <section class="bh-myhub-section bh-myhub-preferences"><div class="bh-myhub-section-heading"><div><div class="bh-myhub-kicker">PERSONALISE YOUR HUB</div><h2>My Interests & Preferred Locations</h2><p>Choose the things your family enjoys and up to three locations. These power your suggested groups.</p></div></div>
      <form method="post" class="bh-myhub-pref-form"><input type="hidden" name="bh_myhub_preferences_nonce" value="<?php echo esc_attr(wp_create_nonce('bh_myhub_preferences')); ?>"><div class="bh-myhub-pref-grid"><div class="bh-myhub-pref-card"><h3>My Interests</h3><p>Pick as many as you like.</p><div class="bh-myhub-interest-grid"><?php foreach($choices as $key=>$label): ?><label><input type="checkbox" name="user_interests[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key,$interests,true)); ?>><span><?php echo esc_html($label); ?></span></label><?php endforeach; ?></div></div><div class="bh-myhub-pref-card"><h3>Preferred Locations</h3><p>Select up to three areas from your <strong>location</strong> taxonomy.</p><div class="bh-myhub-location-list"><?php if(taxonomy_exists('location')){ $terms=get_terms(array('taxonomy'=>'location','hide_empty'=>false,'orderby'=>'name','order'=>'ASC')); if(!is_wp_error($terms))foreach($terms as $term): ?><label><input type="checkbox" name="preferred_locations[]" value="<?php echo esc_attr($term->term_id); ?>" <?php checked(in_array((int)$term->term_id,$locations,true)); ?>><span><?php echo esc_html($term->name); ?></span></label><?php endforeach; }else: ?><p>The location taxonomy is not available yet.</p><?php endif; ?></div></div></div><button class="bh-myhub-button" type="submit">Save my preferences</button></form></section>

      <section class="bh-myhub-section"><div class="bh-myhub-section-heading"><div><div class="bh-myhub-kicker">YOUR FAMILY</div><h2>My Child Profiles</h2><p>Add your children, or mark a profile as expecting and add a due date.</p></div><a class="bh-myhub-button" href="<?php echo esc_url(add_query_arg('bh_add_child','1',get_permalink())); ?>">＋ Add child</a></div>
      <div class="bh-myhub-family-grid"><?php if($children): foreach($children as $c): $preg=$c['status']==='expecting'&&$c['due']; $p=$preg?bubbahub_myhub_v2_pregnancy($c['due']):array(); $school_deadline=($c['status']==='born'&&$c['dob'])?bubbahub_myhub_v2_school_deadline($c['dob'],$la):''; ?><article class="bh-myhub-child-card"><div class="bh-myhub-child-top"><div class="bh-myhub-avatar"><?php echo esc_html(strtoupper(mb_substr($c['name'],0,1))); ?></div><div><h3><?php echo esc_html($c['name']); ?></h3><?php if($preg): ?><p>Expecting · Due <?php echo esc_html(wp_date('j M Y',strtotime($c['due']))); ?></p><?php else: ?><p><?php echo esc_html($c['dob']?wp_date('j M Y',strtotime($c['dob'])):'Date of birth not added'); ?></p><?php endif; ?></div></div><?php if($preg): ?><div class="bh-myhub-tracker pregnancy"><div class="bh-myhub-tracker-title">🤰 Antenatal Groups Hub</div><strong>Don't forget to attend Antenatal classes between <?php echo esc_html(bubbahub_myhub_v2_month_year($p['start'])); ?> and <?php echo esc_html(bubbahub_myhub_v2_month_year($p['end'])); ?></strong><?php if(!$p['past']): ?><span>⏳ <?php echo esc_html(floor($p['weeks_left']/7)); ?> weeks and <?php echo esc_html($p['weeks_left']%7); ?> days remaining until baby is here</span><?php else: ?><span>Baby's due date has passed — congratulations!</span><?php endif; ?></div><?php elseif($school_deadline): ?><div class="bh-myhub-tracker active"><div class="bh-myhub-tracker-title">🏫 School Application</div><strong>Application deadline: <?php echo esc_html(wp_date('j M Y',strtotime($school_deadline))); ?></strong><?php if($la['name']): ?><span>Based on <?php echo esc_html($la['name']); ?> local authority dates.</span><?php endif; ?><?php if($la['url']): ?><a href="<?php echo esc_url($la['url']); ?>" target="_blank" rel="noopener">Check local authority admissions →</a><?php endif; ?></div><?php else: ?><div class="bh-myhub-tracker"><div class="bh-myhub-tracker-title">👶 Family profile</div><span>Choose this child below to personalise suggested groups.</span></div><?php endif; ?><label class="bh-myhub-child-select"><input type="checkbox" class="bh-myhub-selected-child" data-child-id="<?php echo esc_attr($c['id']); ?>" <?php checked(in_array($c['id'],$selected,true)); ?>> Use <?php echo esc_html($c['name']); ?> for group suggestions</label><div class="bh-myhub-child-actions"><a href="<?php echo esc_url(add_query_arg(array('bh_add_child'=>1,'child_id'=>$c['id']))); ?>">Edit profile</a></div></article><?php endforeach; else: ?><div class="bh-myhub-empty-family"><div class="bh-myhub-empty-icon">👋</div><div><h3>Start your family profile</h3><p>Add your first child so Bubba Hub can personalise activities for your family.</p></div></div><?php endif; ?></div></section>

      <section class="bh-myhub-section"><div class="bh-myhub-section-heading"><div><div class="bh-myhub-kicker">YOUR LOCAL ACTIVITIES</div><h2>Your Groups</h2><p>Recently viewed, favourites and visited groups.</p></div></div><div class="bh-myhub-groups-row"><div class="bh-myhub-group-column"><div class="bh-myhub-group-column-head"><h3>Recently Viewed</h3><a data-group-view-more href="<?php echo esc_url(home_url('/my-groups/?group_view=recently_viewed')); ?>">View more →</a></div><div class="bh-myhub-group-widget" data-myhub-group-widget data-group-type="recently_viewed" data-view-more="1"><div class="bh-myhub-groups-loading">Loading…</div></div></div><div class="bh-myhub-group-column"><div class="bh-myhub-group-column-head"><h3>♡ Fav Groups</h3><a data-group-view-more href="<?php echo esc_url(home_url('/my-groups/?group_view=favourite')); ?>">View more →</a></div><div class="bh-myhub-group-widget" data-myhub-group-widget data-group-type="favourite" data-view-more="1"><div class="bh-myhub-groups-loading">Loading…</div></div></div><div class="bh-myhub-group-column"><div class="bh-myhub-group-column-head"><h3>✓ Visited Groups</h3><a data-group-view-more href="<?php echo esc_url(home_url('/my-groups/?group_view=visited')); ?>">View more →</a></div><div class="bh-myhub-group-widget" data-myhub-group-widget data-group-type="visited" data-view-more="1"><div class="bh-myhub-groups-loading">Loading…</div></div></div></div></section>

      <section class="bh-myhub-section bh-myhub-suggested-section"><div class="bh-myhub-section-heading"><div><div class="bh-myhub-kicker">PERSONALISED FOR YOUR FAMILY</div><h2>Suggested Groups For Your Family</h2><p>Matched using <strong>interests, preferred locations and your selected children's age ranges</strong>. Expecting profiles also prioritise antenatal groups.</p></div></div><div class="bh-myhub-group-widget bh-myhub-suggested-widget" data-myhub-group-widget data-group-type="suggested" data-view-more="1"><div class="bh-myhub-groups-loading">Building your suggestions…</div></div><div class="bh-myhub-suggested-more"><a class="bh-myhub-button secondary" href="<?php echo esc_url(home_url('/my-groups/?group_view=suggested')); ?>">View all suggested groups →</a></div></section>

      <?php if(isset($_GET['bh_add_child'])): $ec=$edit_id?$children[$edit_id]:array('name'=>'','status'=>'born','dob'=>'','due'=>''); ?><section class="bh-myhub-form-section"><div class="bh-myhub-section-heading"><div><div class="bh-myhub-kicker">FAMILY PROFILE</div><h2><?php echo $edit_id?'Edit child profile':'Add a child'; ?></h2></div></div><form method="post" class="bh-myhub-child-form"><input type="hidden" name="bh_myhub_child_nonce" value="<?php echo esc_attr(wp_create_nonce('bh_myhub_child_v2')); ?>"><input type="hidden" name="child_id" value="<?php echo esc_attr($edit_id); ?>"><div class="bh-myhub-form-grid"><label>Child's name<input required type="text" name="child_name" value="<?php echo esc_attr($ec['name']); ?>"></label><label>Profile type<select name="child_status"><option value="born" <?php selected($ec['status'],'born'); ?>>Child born</option><option value="expecting" <?php selected($ec['status'],'expecting'); ?>>Expecting a baby</option></select></label><label>Date of birth<input type="date" name="child_date_of_birth" value="<?php echo esc_attr($ec['dob']); ?>"></label><label>Due date<input type="date" name="child_due_date" value="<?php echo esc_attr($ec['due']); ?>"></label></div><p class="bh-myhub-form-help">For an expecting baby, add the due date. Bubba Hub will calculate the 28–32 week antenatal class window automatically.</p><div class="bh-myhub-form-actions"><button class="bh-myhub-button" type="submit">Save child profile</button><a class="bh-myhub-button secondary" href="<?php echo esc_url(remove_query_arg(array('bh_add_child','child_id'))); ?>">Cancel</a></div></form></section><?php endif; ?>
    </div>
    <script>window.BubbaHubMyHubSelectedChildren=<?php echo wp_json_encode($selected); ?>;</script>
    <?php return ob_get_clean();
}

bubbahub_myhub_v2_setup();
