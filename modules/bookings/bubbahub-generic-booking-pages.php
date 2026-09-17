<?php
/**
 * Bubba Hub Generic Booking Pages
 * Leaders can create reusable booking pages that are not tied to one specific
 * session date. Examples: Autumn Term, Spring Term, Summer Holiday Programme.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( class_exists( 'BubbaHub_Generic_Booking_Pages' ) ) return;

final class BubbaHub_Generic_Booking_Pages {
    const CPT = 'bh_booking_page';
    const NONCE = 'bh_generic_booking_page';
    public function __construct() {
        add_action( 'init', [ $this, 'register_cpt' ] );
        add_shortcode( 'bubbahub_booking_page', [ $this, 'shortcode' ] );
        add_action( 'template_redirect', [ $this, 'handle_save' ], 1 );
        add_action( 'add_meta_boxes_' . self::CPT, [ $this, 'meta_box' ] );
        add_action( 'save_post_' . self::CPT, [ $this, 'save_meta' ], 10, 2 );
    }
    public function register_cpt() {
        register_post_type( self::CPT, array( 'labels'=>array('name'=>'Booking Pages','singular_name'=>'Booking Page','add_new_item'=>'Add Booking Page','edit_item'=>'Edit Booking Page'), 'public'=>true, 'show_ui'=>false, 'show_in_menu'=>false, 'supports'=>array('title','editor','author'), 'rewrite'=>array('slug'=>'booking-page','with_front'=>false), 'has_archive'=>false ) );
    }
    private function owned( $id ) { return $id && self::CPT === get_post_type($id) && (int)get_post_field('post_author',$id) === get_current_user_id(); }
    public function form( $id=0 ) {
        $meta=function($key,$default='')use($id){$v=$id?get_post_meta($id,$key,true):'';return ''!==$v?$v:$default;};
        $groups=get_posts(array('post_type'=>'group','post_status'=>array('publish','draft','pending','private'),'author'=>get_current_user_id(),'posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC','fields'=>'ids'));
        ?>
        <form class="bh-leader-form bh-generic-booking-form" method="post">
        <input type="hidden" name="bh_leader_action" value="save_generic_booking_page"><input type="hidden" name="booking_page_id" value="<?php echo esc_attr($id); ?>">
        <?php wp_nonce_field(self::NONCE,self::NONCE.'_nonce'); ?>
        <div class="bh-form-section"><h3>Booking page</h3><p class="description">Create one reusable page for a term, programme or block of bookings without having to choose a single session date.</p></div>
        <p><label>Page title <span aria-hidden="true">*</span></label><input type="text" name="page_title" value="<?php echo esc_attr($id?get_the_title($id):''); ?>" placeholder="Autumn Term" required></p>
        <p><label>Listing <span aria-hidden="true">*</span></label><select name="group_id" required><option value="">Select listing</option><?php foreach($groups as $gid): ?><option value="<?php echo esc_attr($gid); ?>" <?php selected($meta('_bh_group_id'),$gid); ?>><?php echo esc_html(get_the_title($gid)); ?></option><?php endforeach; ?></select></p>
        <p><label>What should the date display say?</label><select name="date_mode"><option value="label" <?php selected($meta('_bh_date_mode','label'),'label'); ?>>Custom label</option><option value="range" <?php selected($meta('_bh_date_mode'),'range'); ?>>Date range</option><option value="both" <?php selected($meta('_bh_date_mode'),'both'); ?>>Custom label + date range</option><option value="none" <?php selected($meta('_bh_date_mode'),'none'); ?>>No date shown</option></select></p>
        <p><label>Custom date/term label</label><input type="text" name="date_label" value="<?php echo esc_attr($meta('_bh_date_label')); ?>" placeholder="Autumn Term 2026"></p>
        <div class="bh-form-grid"><p><label>Start date</label><input type="date" name="start_date" value="<?php echo esc_attr($meta('_bh_start_date')); ?>"></p><p><label>End date</label><input type="date" name="end_date" value="<?php echo esc_attr($meta('_bh_end_date')); ?>"></p></div>
        <p><label>Booking page description</label><textarea name="page_description" rows="6" placeholder="Tell families what is included in this term or programme."><?php echo esc_textarea($id?get_post_field('post_content',$id):''); ?></textarea></p>
        <div class="bh-form-section"><h3>Booking settings</h3><p><label><input type="checkbox" name="show_on_listing" value="1" <?php checked($meta('_bh_show_on_listing',1),1); ?>> Show this booking option on the listing</label></p><p><label>Booking button text</label><input type="text" name="button_text" value="<?php echo esc_attr($meta('_bh_button_text','Book this term')); ?>"></p></div>
        <button class="bh-leader-button" type="submit"><?php echo $id?'Save booking page':'Create booking page'; ?></button>
        </form>
        <?php
    }
    public function handle_save() {
        if(empty($_POST['bh_leader_action'])||'save_generic_booking_page'!==$_POST['bh_leader_action']||!is_user_logged_in())return;
        if(empty($_POST[self::NONCE.'_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE.'_nonce'])),self::NONCE))return;
        $id=absint($_POST['booking_page_id']??0);if($id&&!$this->owned($id))wp_die('You cannot edit this booking page.');
        $group=absint($_POST['group_id']??0);if(!$group||!function_exists('bubbahub_leader_owned_post')||!bubbahub_leader_owned_post($group,'group'))wp_die('Please select one of your own listings.');
        $title=sanitize_text_field(wp_unslash($_POST['page_title']??''));if(!$title)wp_die('Please enter a booking page title.');
        $content=wp_kses_post(wp_unslash($_POST['page_description']??''));$post=array('post_type'=>self::CPT,'post_title'=>$title,'post_content'=>$content,'post_status'=>'publish');
        if($id){$post['ID']=$id;$saved=wp_update_post(wp_slash($post),true);}else{$post['post_author']=get_current_user_id();$saved=wp_insert_post(wp_slash($post),true);}
        if(is_wp_error($saved))wp_die(esc_html($saved->get_error_message()));
        $values=array('_bh_group_id'=>$group,'_bh_date_mode'=>sanitize_key($_POST['date_mode']??'label'),'_bh_date_label'=>sanitize_text_field(wp_unslash($_POST['date_label']??'')),'_bh_start_date'=>sanitize_text_field(wp_unslash($_POST['start_date']??'')),'_bh_end_date'=>sanitize_text_field(wp_unslash($_POST['end_date']??'')),'_bh_show_on_listing'=>empty($_POST['show_on_listing'])?0:1,'_bh_button_text'=>sanitize_text_field(wp_unslash($_POST['button_text']??'Book this term')));
        foreach($values as $k=>$v)update_post_meta($saved,$k,$v);
        wp_safe_redirect(add_query_arg(array('booking_page_saved'=>1,'edit'=>$saved),home_url('/leader/bookings/')));exit;
    }
    public function meta_box($post){echo '<p><strong>Generic booking page</strong></p><p>Listing: '.esc_html(get_the_title(get_post_meta($post->ID,'_bh_group_id',true))).'</p><p>Date display: '.esc_html($this->date_text($post->ID)).'</p>';}
    public function save_meta($id,$post){if(wp_is_post_revision($id))return;}
    private function date_text($id){
        $mode=get_post_meta($id,'_bh_date_mode',true)?:'label';$label=get_post_meta($id,'_bh_date_label',true);$start=get_post_meta($id,'_bh_start_date',true);$end=get_post_meta($id,'_bh_end_date',true);$range='';
        if($start&&$end)$range=wp_date(get_option('date_format'),strtotime($start)).' – '.wp_date(get_option('date_format'),strtotime($end));elseif($start)$range=wp_date(get_option('date_format'),strtotime($start));elseif($end)$range=wp_date(get_option('date_format'),strtotime($end));
        if('none'===$mode)return '';if('range'===$mode)return $range;if('both'===$mode)return trim($label.($range?' · '.$range:''));return $label?:$range;
    }
    public function shortcode($atts=array()){
        $atts=shortcode_atts(array('id'=>0),$atts,'bubbahub_booking_page');$id=absint($atts['id']);if(!$id&&get_query_var('post_type')===self::CPT)$id=get_the_ID();if(!$id||self::CPT!==get_post_type($id))return '';
        $group=absint(get_post_meta($id,'_bh_group_id',true));$date=$this->date_text($id);$button=get_post_meta($id,'_bh_button_text',true)?:'Book this term';$content=apply_filters('the_content',get_post_field('post_content',$id));
        ob_start();?><div class="bh-generic-booking-page"><div class="bh-generic-booking-header"><span class="bh-booking-eyebrow">BOOKING</span><h1><?php echo esc_html(get_the_title($id));?></h1><?php if($date):?><div class="bh-generic-booking-date"><?php echo esc_html($date);?></div><?php endif;?></div><div class="bh-generic-booking-content"><?php echo $content;?></div><div class="bh-generic-booking-action"><?php if($group&&shortcode_exists('bubbahub_booking')){echo do_shortcode('[bubbahub_booking group_id="'.absint($group).'"]');}else{?><a class="bh-leader-button" href="<?php echo esc_url(get_permalink($group));?>"><?php echo esc_html($button);?></a><?php }?></div></div><?php return ob_get_clean();
    }
}
new BubbaHub_Generic_Booking_Pages();
