<?php
/** BubbaHub Schedule Foundation */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'bubbahub_schedule_foundation_register_venue', 5 );
add_action( 'add_meta_boxes_bh_session', 'bubbahub_schedule_foundation_session_box', 20 );
add_action( 'save_post_bh_session', 'bubbahub_schedule_foundation_save_session', 20, 3 );

function bubbahub_schedule_foundation_register_venue() {
    if ( post_type_exists( 'venue' ) ) return;
    register_post_type( 'venue', array(
        'labels' => array( 'name'=>'Venues', 'singular_name'=>'Venue', 'add_new_item'=>'Add Venue', 'edit_item'=>'Edit Venue' ),
        'public'=>false, 'show_ui'=>true, 'show_in_menu'=>true, 'show_in_rest'=>true,
        'supports'=>array('title','editor','thumbnail','author'), 'menu_icon'=>'dashicons-location-alt',
        'capability_type'=>'post', 'map_meta_cap'=>true,
    ) );
}

function bubbahub_schedule_foundation_session_box() {
    add_meta_box('bubbahub_schedule_foundation','Schedule & Recurrence','bubbahub_schedule_foundation_render_box','bh_session','normal','default');
}
function bubbahub_schedule_foundation_meta($id,$key,$default='') {
    $v=get_post_meta($id,$key,true); return (''===$v||false===$v||null===$v)?$default:$v;
}
function bubbahub_schedule_foundation_render_box($post) {
    wp_nonce_field('bubbahub_schedule_foundation_save','bubbahub_schedule_foundation_nonce');
    $r=bubbahub_schedule_foundation_meta($post->ID,'_bh_recurrence','none');
    $i=max(1,absint(bubbahub_schedule_foundation_meta($post->ID,'_bh_recurrence_interval',1)));
    $w=bubbahub_schedule_foundation_meta($post->ID,'_bh_recurrence_weekday','');
    $s=bubbahub_schedule_foundation_meta($post->ID,'_bh_recurrence_start',bubbahub_schedule_foundation_meta($post->ID,'_bh_date',''));
    $e=bubbahub_schedule_foundation_meta($post->ID,'_bh_recurrence_end','');
    $t=(bool)bubbahub_schedule_foundation_meta($post->ID,'_bh_term_time',false);
    $x=bubbahub_schedule_foundation_meta($post->ID,'_bh_recurrence_exclusions',array()); if(!is_array($x))$x=array();
    ?>
    <p><strong>Recurring class schedule.</strong> Existing one-off booking sessions remain compatible.</p>
    <table class="form-table" role="presentation">
    <tr><th><label for="bh_recurrence">Repeat</label></th><td><select name="bh_recurrence" id="bh_recurrence"><option value="none" <?php selected($r,'none'); ?>>One-off</option><option value="weekly" <?php selected($r,'weekly'); ?>>Weekly</option><option value="fortnightly" <?php selected($r,'fortnightly'); ?>>Every 2 weeks</option><option value="monthly" <?php selected($r,'monthly'); ?>>Monthly</option></select></td></tr>
    <tr><th><label for="bh_recurrence_interval">Interval</label></th><td><input type="number" min="1" name="bh_recurrence_interval" id="bh_recurrence_interval" value="<?php echo esc_attr($i); ?>" class="small-text"> <span class="description">1 = every period, 2 = every second period.</span></td></tr>
    <tr><th><label for="bh_recurrence_weekday">Day</label></th><td><select name="bh_recurrence_weekday" id="bh_recurrence_weekday"><option value="">Use session date</option><?php foreach(array('monday'=>'Monday','tuesday'=>'Tuesday','wednesday'=>'Wednesday','thursday'=>'Thursday','friday'=>'Friday','saturday'=>'Saturday','sunday'=>'Sunday') as $v=>$l): ?><option value="<?php echo esc_attr($v); ?>" <?php selected($w,$v); ?>><?php echo esc_html($l); ?></option><?php endforeach; ?></select></td></tr>
    <tr><th><label for="bh_recurrence_start">Starts</label></th><td><input type="date" name="bh_recurrence_start" id="bh_recurrence_start" value="<?php echo esc_attr($s); ?>"></td></tr>
    <tr><th><label for="bh_recurrence_end">Ends</label></th><td><input type="date" name="bh_recurrence_end" id="bh_recurrence_end" value="<?php echo esc_attr($e); ?>"> <span class="description">Blank = no defined end.</span></td></tr>
    <tr><th>Term time</th><td><label><input type="checkbox" name="bh_term_time" value="1" <?php checked($t,true); ?>> Follows term-time dates</label></td></tr>
    <tr><th><label for="bh_recurrence_exclusions">Excluded dates</label></th><td><textarea name="bh_recurrence_exclusions" id="bh_recurrence_exclusions" rows="3" class="large-text" placeholder="2026-10-26, 2026-12-21"><?php echo esc_textarea(implode(', ',$x)); ?></textarea><p class="description">YYYY-MM-DD, comma or line separated.</p></td></tr>
    </table>
    <p class="description">Schedule definitions are stored separately from occurrence date/time fields so the booking engine is not disrupted.</p>
    <?php
}
function bubbahub_schedule_foundation_save_session($post_id,$post,$update) {
    if((defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)||wp_is_post_revision($post_id)||!isset($_POST['bubbahub_schedule_foundation_nonce']))return;
    if(!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bubbahub_schedule_foundation_nonce'])),'bubbahub_schedule_foundation_save')||!current_user_can('edit_post',$post_id))return;
    $r=isset($_POST['bh_recurrence'])?sanitize_key(wp_unslash($_POST['bh_recurrence'])):'none'; if(!in_array($r,array('none','weekly','fortnightly','monthly'),true))$r='none'; update_post_meta($post_id,'_bh_recurrence',$r);
    update_post_meta($post_id,'_bh_recurrence_interval',isset($_POST['bh_recurrence_interval'])?max(1,absint($_POST['bh_recurrence_interval'])):1);
    $w=isset($_POST['bh_recurrence_weekday'])?sanitize_key(wp_unslash($_POST['bh_recurrence_weekday'])):''; if(!in_array($w,array('','monday','tuesday','wednesday','thursday','friday','saturday','sunday'),true))$w=''; update_post_meta($post_id,'_bh_recurrence_weekday',$w);
    foreach(array('bh_recurrence_start'=>'_bh_recurrence_start','bh_recurrence_end'=>'_bh_recurrence_end') as $input=>$meta){$v=isset($_POST[$input])?sanitize_text_field(wp_unslash($_POST[$input])):'';if($v&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$v))$v='';if($v)update_post_meta($post_id,$meta,$v);else delete_post_meta($post_id,$meta);}
    update_post_meta($post_id,'_bh_term_time',!empty($_POST['bh_term_time'])?1:0);
    $raw=isset($_POST['bh_recurrence_exclusions'])?sanitize_textarea_field(wp_unslash($_POST['bh_recurrence_exclusions'])):'';$x=array();foreach(preg_split('/[,\r\n]+/',$raw) as $v){$v=trim($v);if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$v))$x[]=$v;}$x=array_values(array_unique($x));if($x)update_post_meta($post_id,'_bh_recurrence_exclusions',$x);else delete_post_meta($post_id,'_bh_recurrence_exclusions');
}
function bubbahub_schedule_get_definition($session_id){$id=absint($session_id);if(!$id||'bh_session'!==get_post_type($id))return array();return array('session_id'=>$id,'group_id'=>absint(bubbahub_schedule_foundation_meta($id,'_bh_group_id',0)),'venue_id'=>absint(bubbahub_schedule_foundation_meta($id,'_bh_venue_id',0)),'date'=>bubbahub_schedule_foundation_meta($id,'_bh_date',''),'start_time'=>bubbahub_schedule_foundation_meta($id,'_bh_start_time',''),'end_time'=>bubbahub_schedule_foundation_meta($id,'_bh_end_time',''),'recurrence'=>bubbahub_schedule_foundation_meta($id,'_bh_recurrence','none'),'interval'=>max(1,absint(bubbahub_schedule_foundation_meta($id,'_bh_recurrence_interval',1))),'weekday'=>bubbahub_schedule_foundation_meta($id,'_bh_recurrence_weekday',''),'start_date'=>bubbahub_schedule_foundation_meta($id,'_bh_recurrence_start',''),'end_date'=>bubbahub_schedule_foundation_meta($id,'_bh_recurrence_end',''),'term_time'=>(bool)bubbahub_schedule_foundation_meta($id,'_bh_term_time',false),'exclusions'=>(array)bubbahub_schedule_foundation_meta($id,'_bh_recurrence_exclusions',array()));}
