<?php
/**
 * BubbaHub Leader Schedule Manager
 * Front-end recurring-session editor for leaders.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'bubbahub_leader_schedule', 'bubbahub_leader_schedule_shortcode' );

function bubbahub_leader_schedule_owned( $id ) {
    $post = get_post( absint( $id ) );
    return $post && 'bh_session' === $post->post_type && (int) $post->post_author === get_current_user_id();
}

function bubbahub_leader_schedule_shortcode( $atts = array() ) {
    if ( ! function_exists( 'bubbahub_leader_dashboard_is_allowed' ) || ! bubbahub_leader_dashboard_is_allowed() ) {
        return '<div class="bh-form-warning">Please log in as an approved BubbaHub leader.</div>';
    }
    $user_id = get_current_user_id();
    $session_id = isset( $_GET['session'] ) ? absint( $_GET['session'] ) : 0;
    if ( $session_id && ! bubbahub_leader_schedule_owned( $session_id ) ) return '<div class="bh-form-warning">You cannot edit this session.</div>';

    $groups = get_posts( array( 'post_type'=>'group', 'post_status'=>array('publish','draft','pending','private'), 'author'=>$user_id, 'posts_per_page'=>-1, 'orderby'=>'title', 'order'=>'ASC', 'no_found_rows'=>true ) );
    $venues = post_type_exists('venue') ? get_posts( array( 'post_type'=>'venue', 'post_status'=>array('publish','draft','pending','private'), 'author'=>$user_id, 'posts_per_page'=>-1, 'orderby'=>'title', 'order'=>'ASC', 'no_found_rows'=>true ) ) : array();
    $values = array(
        'group_id'=>0,'venue_id'=>0,'date'=>'','start_time'=>'','end_time'=>'','capacity'=>'','price'=>'',
        'recurrence'=>'none','interval'=>1,'weekday'=>'','start_date'=>'','end_date'=>'','term_time'=>0,'exclusions'=>''
    );
    if ( $session_id ) {
        foreach ( array_keys($values) as $key ) {
            $meta_key = array('group_id'=>'_bh_group_id','venue_id'=>'_bh_venue_id','date'=>'_bh_date','start_time'=>'_bh_start_time','end_time'=>'_bh_end_time','capacity'=>'_bh_capacity','price'=>'_bh_price','recurrence'=>'_bh_recurrence','interval'=>'_bh_recurrence_interval','weekday'=>'_bh_recurrence_weekday','start_date'=>'_bh_recurrence_start','end_date'=>'_bh_recurrence_end','term_time'=>'_bh_term_time','exclusions'=>'_bh_recurrence_exclusions')[$key];
            $value = get_post_meta($session_id,$meta_key,true);
            if ( 'exclusions' === $key && is_array($value) ) $value = implode(', ',$value);
            if ( '' !== $value && false !== $value ) $values[$key]=$value;
        }
    }
    if ( ! $values['group_id'] && $groups ) $values['group_id'] = (int) $groups[0]->ID;
    ob_start(); ?>
    <div class="bh-leader-schedule-manager">
      <div class="bh-schedule-intro"><p class="bh-leader-eyebrow">Sessions</p><h2><?php echo $session_id ? 'Edit class schedule' : 'Add a class schedule'; ?></h2><p>Create a one-off class or a recurring timetable. Upcoming sessions are generated automatically.</p></div>
      <form class="bh-leader-form bh-schedule-form" method="post">
        <?php wp_nonce_field('bubbahub_save_leader_schedule','bh_schedule_nonce'); ?>
        <input type="hidden" name="bh_schedule_action" value="save">
        <input type="hidden" name="session_id" value="<?php echo esc_attr($session_id); ?>">
        <div class="bh-form-grid">
          <label>Listing<select name="group_id" required><?php foreach($groups as $g): ?><option value="<?php echo esc_attr($g->ID); ?>" <?php selected($values['group_id'],$g->ID); ?>><?php echo esc_html($g->post_title); ?></option><?php endforeach; ?></select></label>
          <label>Venue<select name="venue_id"><option value="0">Choose a venue</option><?php foreach($venues as $v): ?><option value="<?php echo esc_attr($v->ID); ?>" <?php selected($values['venue_id'],$v->ID); ?>><?php echo esc_html($v->post_title); ?></option><?php endforeach; ?></select></label>
          <label>First date<input type="date" name="date" value="<?php echo esc_attr($values['date']); ?>" required></label>
          <label>Start time<input type="time" name="start_time" value="<?php echo esc_attr($values['start_time']); ?>" required></label>
          <label>End time<input type="time" name="end_time" value="<?php echo esc_attr($values['end_time']); ?>"></label>
          <label>Capacity<input type="number" min="0" name="capacity" value="<?php echo esc_attr($values['capacity']); ?>"></label>
          <label>Price<input type="number" min="0" step="0.01" name="price" value="<?php echo esc_attr($values['price']); ?>"></label>
          <label>Repeats<select name="recurrence"><option value="none" <?php selected($values['recurrence'],'none'); ?>>Does not repeat</option><option value="weekly" <?php selected($values['recurrence'],'weekly'); ?>>Weekly</option><option value="fortnightly" <?php selected($values['recurrence'],'fortnightly'); ?>>Fortnightly</option><option value="monthly" <?php selected($values['recurrence'],'monthly'); ?>>Monthly</option></select></label>
          <label>Repeat interval<input type="number" min="1" max="52" name="interval" value="<?php echo esc_attr($values['interval']); ?>"></label>
          <label>Weekday<input type="text" name="weekday" placeholder="e.g. Monday" value="<?php echo esc_attr($values['weekday']); ?>"></label>
          <label>Repeat from<input type="date" name="start_date" value="<?php echo esc_attr($values['start_date']); ?>"></label>
          <label>Repeat until<input type="date" name="end_date" value="<?php echo esc_attr($values['end_date']); ?>"></label>
          <label class="bh-form-full"><span>Excluded dates</span><input type="text" name="exclusions" placeholder="2026-12-21, 2026-12-28" value="<?php echo esc_attr($values['exclusions']); ?>"><small>Use YYYY-MM-DD, separated by commas.</small></label>
          <label class="bh-checkbox"><input type="checkbox" name="term_time" value="1" <?php checked($values['term_time'],1); ?>> Term-time activity</label>
        </div>
        <button class="bh-leader-primary" type="submit"><?php echo $session_id ? 'Save schedule' : 'Create schedule'; ?></button>
      </form>
    </div>
    <?php return ob_get_clean();
}

add_action( 'init', 'bubbahub_leader_schedule_save', 30 );
function bubbahub_leader_schedule_save() {
    if ( empty($_POST['bh_schedule_action']) || 'save' !== $_POST['bh_schedule_action'] ) return;
    if ( ! is_user_logged_in() || ! function_exists('bubbahub_leader_dashboard_is_allowed') || ! bubbahub_leader_dashboard_is_allowed() ) return;
    if ( empty($_POST['bh_schedule_nonce']) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['bh_schedule_nonce'])), 'bubbahub_save_leader_schedule' ) ) return;
    $id = absint($_POST['session_id'] ?? 0);
    if ( $id && ! bubbahub_leader_schedule_owned($id) ) return;
    $group_id = absint($_POST['group_id'] ?? 0);
    if ( ! $group_id || ! bubbahub_leader_owned_post($group_id,'group') ) return;
    $date = sanitize_text_field(wp_unslash($_POST['date'] ?? ''));
    $start = sanitize_text_field(wp_unslash($_POST['start_time'] ?? ''));
    if ( ! preg_match('/^\d{4}-\d{2}-\d{2}$/',$date) || ! preg_match('/^\d{2}:\d{2}$/',$start) ) return;
    if ( ! $id ) $id = wp_insert_post(array('post_type'=>'bh_session','post_status'=>'publish','post_title'=>get_the_title($group_id).' – '.$date,'post_author'=>get_current_user_id()),true);
    if ( is_wp_error($id) ) return;
    $fields = array(
      '_bh_group_id'=>$group_id,'_bh_venue_id'=>absint($_POST['venue_id'] ?? 0),'_bh_date'=>$date,
      '_bh_start_time'=>$start,'_bh_end_time'=>sanitize_text_field(wp_unslash($_POST['end_time'] ?? '')),
      '_bh_capacity'=>absint($_POST['capacity'] ?? 0),'_bh_price'=>sanitize_text_field(wp_unslash($_POST['price'] ?? '')),
      '_bh_recurrence'=>in_array($_POST['recurrence'] ?? 'none',array('none','weekly','fortnightly','monthly'),true) ? sanitize_key($_POST['recurrence']) : 'none',
      '_bh_recurrence_interval'=>max(1,min(52,absint($_POST['interval'] ?? 1))),
      '_bh_recurrence_weekday'=>sanitize_text_field(wp_unslash($_POST['weekday'] ?? '')),
      '_bh_recurrence_start'=>sanitize_text_field(wp_unslash($_POST['start_date'] ?? '')),
      '_bh_recurrence_end'=>sanitize_text_field(wp_unslash($_POST['end_date'] ?? '')),
      '_bh_term_time'=>!empty($_POST['term_time']) ? 1 : 0,
    );
    foreach($fields as $key=>$value) update_post_meta($id,$key,$value);
    $raw = sanitize_text_field(wp_unslash($_POST['exclusions'] ?? ''));
    $exclusions=array(); foreach(preg_split('/\s*,\s*/',$raw) as $d){ if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$d)) $exclusions[]=$d; }
    update_post_meta($id,'_bh_recurrence_exclusions',array_values(array_unique($exclusions)));
    if ( function_exists('bubbahub_schedule_generate_for_session') && 'none' !== $fields['_bh_recurrence'] ) bubbahub_schedule_generate_for_session($id,90);
    wp_safe_redirect(add_query_arg(array('schedule_saved'=>1,'session'=>$id),home_url('/leader/'))); exit;
}
