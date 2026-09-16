<?php
/**
 * BubbaHub My Hub profile + account settings layer.
 *
 * Adapted from the supplied Convert Web App to Mobile profile templates.
 * Shortcode: [bubbahub_account_settings]
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'bubbahub_profile_settings_register', 35 );
add_action( 'wp_enqueue_scripts', 'bubbahub_profile_settings_assets' );

function bubbahub_profile_settings_register() { add_shortcode( 'bubbahub_account_settings', 'bubbahub_account_settings_shortcode' ); }
function bubbahub_profile_settings_assets() {
    wp_register_style( 'bubbahub-profile-settings', BUBBAHUB_MYHUB_URL . 'profile-settings.css', array(), BUBBAHUB_MYHUB_VERSION );
    wp_register_script( 'bubbahub-profile-settings', BUBBAHUB_MYHUB_URL . 'profile-settings.js', array(), BUBBAHUB_MYHUB_VERSION, true );
}
function bubbahub_profile_field( $post_id, $field, $default = '' ) {
    $value = function_exists( 'get_field' ) ? get_field( $field, $post_id, false ) : '';
    if ( '' === $value || null === $value || false === $value ) $value = get_post_meta( $post_id, $field, true );
    return ( '' !== $value && false !== $value && null !== $value ) ? $value : $default;
}
function bubbahub_profile_update_field( $post_id, $field, $value ) {
    if ( function_exists( 'update_field' ) ) update_field( $field, $value, $post_id ); else update_post_meta( $post_id, $field, $value );
}
function bubbahub_profile_child_owned( $child_id ) {
    $child = get_post( absint( $child_id ) );
    return $child && 'bh_child' === $child->post_type && (int) $child->post_author === get_current_user_id();
}
function bubbahub_profile_um_url() {
    if ( function_exists( 'um_get_core_page' ) ) { $url = um_get_core_page( 'account' ); if ( $url ) return esc_url_raw( $url ); }
    return home_url( '/account/' );
}
function bubbahub_profile_payment_url() {
    $url = apply_filters( 'bubbahub_getpaid_account_url', '' );
    if ( $url ) return esc_url_raw( $url );
    return home_url( '/my-bookings/' );
}
function bubbahub_profile_child_form() {
    if ( ! is_user_logged_in() ) return '';
    $uid = get_current_user_id();
    $child_id = isset( $_GET['child_id'] ) ? absint( $_GET['child_id'] ) : 0;
    $editing = $child_id && bubbahub_profile_child_owned( $child_id );
    $message = '';
    if ( isset( $_POST['bh_profile_child_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bh_profile_child_nonce'] ) ), 'bh_profile_child_save' ) ) {
        $posted_id = isset( $_POST['child_id'] ) ? absint( $_POST['child_id'] ) : 0;
        if ( $posted_id && bubbahub_profile_child_owned( $posted_id ) ) { $child_id = $posted_id; $editing = true; }
        $name = isset( $_POST['child_name'] ) ? sanitize_text_field( wp_unslash( $_POST['child_name'] ) ) : '';
        $nickname = isset( $_POST['child_nickname'] ) ? sanitize_text_field( wp_unslash( $_POST['child_nickname'] ) ) : '';
        $status = isset( $_POST['child_status'] ) ? sanitize_key( wp_unslash( $_POST['child_status'] ) ) : 'born';
        $dob = isset( $_POST['child_date_of_birth'] ) ? sanitize_text_field( wp_unslash( $_POST['child_date_of_birth'] ) ) : '';
        $due = isset( $_POST['child_due_date'] ) ? sanitize_text_field( wp_unslash( $_POST['child_due_date'] ) ) : '';
        $avatar = isset( $_POST['avatar_url'] ) ? esc_url_raw( wp_unslash( $_POST['avatar_url'] ) ) : '';
        $allergies = isset( $_POST['allergies'] ) ? sanitize_text_field( wp_unslash( $_POST['allergies'] ) ) : '';
        $notes = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
        $nap = array();
        foreach ( array( 'Mon','Tue','Wed','Thu','Fri','Sat','Sun' ) as $day ) {
            $nap[ $day ] = array(
                'enabled' => ! empty( $_POST['nap'][ $day ]['enabled'] ),
                'napStart' => isset( $_POST['nap'][ $day ]['start'] ) ? sanitize_text_field( wp_unslash( $_POST['nap'][ $day ]['start'] ) ) : '13:00',
                'napEnd' => isset( $_POST['nap'][ $day ]['end'] ) ? sanitize_text_field( wp_unslash( $_POST['nap'][ $day ]['end'] ) ) : '15:00',
            );
        }
        if ( ! in_array( $status, array( 'born', 'expecting' ), true ) ) $status = 'born';
        if ( $editing ) wp_update_post( array( 'ID' => $child_id, 'post_title' => $name ? $name : 'Family Child' ) );
        else {
            $child_id = wp_insert_post( array( 'post_type' => 'bh_child', 'post_status' => 'publish', 'post_author' => $uid, 'post_title' => $name ? $name : 'Family Child' ) );
            $editing = ! is_wp_error( $child_id ) && $child_id;
        }
        if ( $editing ) {
            foreach ( array( 'child_name'=>$name,'child_nickname'=>$nickname,'child_status'=>$status,'child_date_of_birth'=>$dob,'child_due_date'=>$due,'avatar_url'=>$avatar,'allergies'=>$allergies,'notes'=>$notes,'nap_schedule'=>$nap ) as $field=>$value ) bubbahub_profile_update_field( $child_id, $field, $value );
            $message = 'Child profile saved successfully.';
        }
    }
    if ( $editing ) {
        $name=bubbahub_profile_field($child_id,'child_name',get_the_title($child_id)); $nickname=bubbahub_profile_field($child_id,'child_nickname'); $status=bubbahub_profile_field($child_id,'child_status','born'); $dob=bubbahub_profile_field($child_id,'child_date_of_birth'); $due=bubbahub_profile_field($child_id,'child_due_date'); $avatar=bubbahub_profile_field($child_id,'avatar_url'); $allergies=bubbahub_profile_field($child_id,'allergies'); $notes=bubbahub_profile_field($child_id,'notes'); $nap=bubbahub_profile_field($child_id,'nap_schedule',array());
    } else { $name=$nickname=$dob=$due=$avatar=$allergies=$notes=''; $status='born'; $nap=array(); }
    ob_start(); ?>
    <div class="bh-profile-shell">
      <div class="bh-profile-header"><div><span class="bh-profile-kicker">FAMILY PROFILE</span><h2><?php echo $editing?'Edit Child Profile':'Add a Child'; ?></h2><p>Use this profile to personalise group suggestions, school information and family bookings.</p></div><a class="bh-profile-back" href="<?php echo esc_url(remove_query_arg(array('bh_add_child','child_id','bh_account_settings'))); ?>">‹ Back to My Hub</a></div>
      <?php if($message): ?><div class="bh-profile-success">✓ <?php echo esc_html($message); ?></div><?php endif; ?>
      <form method="post" class="bh-profile-form"><?php wp_nonce_field('bh_profile_child_save','bh_profile_child_nonce'); ?><input type="hidden" name="child_id" value="<?php echo esc_attr($editing?$child_id:0); ?>">
        <div class="bh-profile-card"><div class="bh-profile-card-heading"><h3>About your child</h3><span>Core profile</span></div><div class="bh-profile-grid two">
          <label><span>Name</span><input name="child_name" value="<?php echo esc_attr($name); ?>" required></label><label><span>Nickname</span><input name="child_nickname" value="<?php echo esc_attr($nickname); ?>" placeholder="Optional"></label>
          <label><span>Profile photo URL</span><input name="avatar_url" type="url" value="<?php echo esc_attr($avatar); ?>" placeholder="Optional"></label><label><span>Profile type</span><select name="child_status"><option value="born" <?php selected($status,'born'); ?>>Child</option><option value="expecting" <?php selected($status,'expecting'); ?>>Expecting / Pregnancy</option></select></label>
          <label><span>Date of birth</span><input name="child_date_of_birth" type="date" value="<?php echo esc_attr($dob); ?>"></label><label><span>Expected due date</span><input name="child_due_date" type="date" value="<?php echo esc_attr($due); ?>"></label>
        </div></div>
        <div class="bh-profile-card"><div class="bh-profile-card-heading"><h3>Care information</h3><span>Private family notes</span></div><div class="bh-profile-grid one"><label><span>Allergies & dietary restrictions</span><input name="allergies" value="<?php echo esc_attr($allergies); ?>" placeholder="e.g. Peanuts, dairy"></label><label><span>Other relevant information</span><textarea name="notes" rows="4" placeholder="Routine notes, favourite toys, additional information…"><?php echo esc_textarea($notes); ?></textarea></label></div></div>
        <div class="bh-profile-card"><div class="bh-profile-card-heading"><h3>Nap schedule</h3><span>Saved per day</span></div><div class="bh-nap-grid"><?php foreach(array('Mon','Tue','Wed','Thu','Fri','Sat','Sun') as $day): $row=(isset($nap[$day])&&is_array($nap[$day]))?$nap[$day]:array(); ?><div class="bh-nap-row"><strong><?php echo esc_html($day); ?></strong><label class="bh-switch"><input type="checkbox" name="nap[<?php echo esc_attr($day); ?>][enabled]" value="1" <?php checked(!empty($row['enabled'])); ?>><span></span></label><input type="time" name="nap[<?php echo esc_attr($day); ?>][start]" value="<?php echo esc_attr($row['napStart']??'13:00'); ?>"><span>to</span><input type="time" name="nap[<?php echo esc_attr($day); ?>][end]" value="<?php echo esc_attr($row['napEnd']??'15:00'); ?>"></div><?php endforeach; ?></div></div>
        <div class="bh-profile-actions"><button type="submit">Save child profile</button></div>
      </form>
    </div><?php return ob_get_clean();
}
function bubbahub_account_settings_shortcode() {
    if ( ! is_user_logged_in() ) return '<p>Please log in to manage your account settings.</p>';
    wp_enqueue_style('bubbahub-profile-settings'); wp_enqueue_script('bubbahub-profile-settings');
    if(isset($_GET['bh_add_child'])) return bubbahub_profile_child_form();
    $user=wp_get_current_user(); $um_url=bubbahub_profile_um_url(); $payment_url=bubbahub_profile_payment_url();
    $children=get_posts(array('post_type'=>'bh_child','post_status'=>'publish','author'=>get_current_user_id(),'posts_per_page'=>-1,'orderby'=>'date','order'=>'ASC','no_found_rows'=>true));
    ob_start(); ?>
    <div class="bh-profile-shell">
      <div class="bh-profile-header dark"><div><span class="bh-profile-kicker">ACCOUNT SETTINGS</span><h1>Your Account Settings</h1><p>Manage your Bubba Hub profile, children, membership and payment details in one place.</p></div><a class="bh-profile-back light" href="<?php echo esc_url(remove_query_arg('bh_account_settings')); ?>">‹ Back to My Hub</a></div>
      <div class="bh-account-grid">
        <a class="bh-account-card" href="<?php echo esc_url($um_url); ?>"><span class="bh-account-icon">👤</span><div><h3>Ultimate Member Account</h3><p>Update your name, email, password, privacy and account details using Ultimate Member.</p></div><span>→</span></a>
        <a class="bh-account-card" href="<?php echo esc_url($payment_url); ?>"><span class="bh-account-icon">💳</span><div><h3>Payments & invoices</h3><p>View your Bubba Hub bookings and payment activity.</p></div><span>→</span></a>
        <a class="bh-account-card" href="<?php echo esc_url(add_query_arg('bh_add_child','1')); ?>"><span class="bh-account-icon">👶</span><div><h3>Child profiles</h3><p><?php echo esc_html(count($children)); ?> child profile<?php echo count($children)===1?'':'s'; ?> saved. Add, edit or update family information.</p></div><span>→</span></a>
      </div>
      <div class="bh-profile-card"><div class="bh-profile-card-heading"><h3>Connected account</h3><span>WordPress / Ultimate Member</span></div><div class="bh-connected-row"><div><strong><?php echo esc_html($user->display_name); ?></strong><small><?php echo esc_html($user->user_email); ?></small></div><a href="<?php echo esc_url($um_url); ?>">Open Ultimate Member →</a></div></div>
      <div class="bh-profile-card"><div class="bh-profile-card-heading"><h3>Child profiles</h3><a href="<?php echo esc_url(add_query_arg('bh_add_child','1')); ?>">＋ Add child</a></div>
      <?php if($children): ?><div class="bh-account-children-list"><?php foreach($children as $child): $name=bubbahub_profile_field($child->ID,'child_name',$child->post_title); $status=bubbahub_profile_field($child->ID,'child_status','born'); ?><div><span class="bh-mini-avatar"><?php echo esc_html(strtoupper(substr($name,0,1))); ?></span><div><strong><?php echo esc_html($name); ?></strong><small><?php echo 'expecting'===$status?'Expecting':'Child profile'; ?></small></div><a href="<?php echo esc_url(add_query_arg(array('bh_add_child'=>1,'child_id'=>$child->ID))); ?>">Edit</a></div><?php endforeach; ?></div><?php else: ?><p class="bh-muted">No child profiles have been added yet.</p><?php endif; ?></div>
    </div><?php return ob_get_clean();
}
