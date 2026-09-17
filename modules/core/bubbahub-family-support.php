<?php
/**
 * Bubba Hub Family Support Centre.
 * Shortcodes for parent/leader help, suggestions, listing reports and contact.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', function() {
    if ( ! post_type_exists( 'bh_support_request' ) ) register_post_type( 'bh_support_request', array(
        'labels' => array( 'name' => 'Bubba Hub Support Requests', 'singular_name' => 'Support Request' ),
        'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'supports' => array( 'title', 'editor', 'author' ), 'menu_icon' => 'dashicons-sos',
    ) );
} );

add_shortcode( 'bubbahub_support_centre', 'bubbahub_support_centre_shortcode' );
add_shortcode( 'bubbahub_suggest_group', 'bubbahub_suggest_group_shortcode' );
add_shortcode( 'bubbahub_report_listing', 'bubbahub_report_listing_shortcode' );

function bubbahub_support_centre_shortcode() {
    $cards = array(
        array('For Parents','Find groups, save favourites, manage children, bookings and your weekly planner.'),
        array('For Group Leaders','Create and claim listings, manage venues, sessions, bookings and your profile.'),
        array('Bookings & Payments','Learn how session bookings, tickets, payments, cancellations and refunds work.'),
        array('My Hub','Manage child profiles, interests, preferred locations, saved groups and activities.'),
        array('Accessibility & SEN','Tell us about accessibility information that would help families make informed choices.'),
        array('Listing Accuracy','Report a change, closed group, incorrect contact detail or new local group.'),
    );
    ob_start(); ?>
    <section class="bh-support-centre">
      <header><span>HELP & SUPPORT</span><h2>Bubba Hub Support Centre</h2><p>Everything you need to get the most from Bubba Hub — whether you're a family, group leader or community organisation.</p></header>
      <div class="bh-support-grid"><?php foreach($cards as $c): ?><article><h3><?php echo esc_html($c[0]); ?></h3><p><?php echo esc_html($c[1]); ?></p></article><?php endforeach; ?></div>
      <div class="bh-support-actions"><a href="#suggest-group">Suggest a group</a><a href="#report-listing">Report an incorrect listing</a><a href="mailto:contact@bubbahub.co.uk">Contact Bubba Hub</a></div>
      <details><summary>How do I find a group?</summary><p>Use the directory filters to search by area, age, price and other available listing information. Open a listing to see its details, schedule and booking options.</p></details>
      <details><summary>How do I claim my listing?</summary><p>Use the claim process on the listing and sign in with the email associated with your organisation. Claims are reviewed before access is granted.</p></details>
      <details><summary>How do bookings work?</summary><p>Choose a class and available session, select the required tickets and complete the booking journey. Your booking can then appear in My Hub when linked to your account.</p></details>
      <details><summary>What if a group has closed?</summary><p>Please report it using the form below so Bubba Hub can review the listing and update its status.</p></details>
    </section><?php return ob_get_clean();
}

function bubbahub_support_request_form( $type, $heading ) {
    if ( ! empty($_POST['bh_support_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bh_support_nonce'])),'bh_support') ) {
        $name = sanitize_text_field(wp_unslash($_POST['bh_name'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['bh_email'] ?? ''));
        $message = sanitize_textarea_field(wp_unslash($_POST['bh_message'] ?? ''));
        $listing = absint($_POST['bh_listing'] ?? 0);
        if ( $message && $email ) {
            $title = $heading . ' - ' . ($name ?: $email);
            $id = wp_insert_post(array('post_type'=>'bh_support_request','post_status'=>'private','post_title'=>$title,'post_content'=>$message,'post_author'=>get_current_user_id()), true);
            if ( ! is_wp_error($id) ) { update_post_meta($id,'_bh_request_type',$type); update_post_meta($id,'_bh_email',$email); update_post_meta($id,'_bh_name',$name); update_post_meta($id,'_bh_listing_id',$listing); echo '<div class="bh-support-success">Thanks — your request has been sent to Bubba Hub.</div>'; }
        } else echo '<div class="bh-support-error">Please provide your email and message.</div>';
    }
    ob_start(); ?><form class="bh-support-form" method="post"><h2><?php echo esc_html($heading); ?></h2><label>Name<input name="bh_name" required></label><label>Email<input type="email" name="bh_email" required></label><label>Listing ID (optional)<input type="number" name="bh_listing" min="0"></label><label>Message<textarea name="bh_message" rows="5" required></textarea></label><?php wp_nonce_field('bh_support','bh_support_nonce'); ?><button type="submit">Send to Bubba Hub</button></form><?php return ob_get_clean();
}
function bubbahub_suggest_group_shortcode(){ return bubbahub_support_request_form('suggest_group','Suggest a new group or service'); }
function bubbahub_report_listing_shortcode(){ return bubbahub_support_request_form('report_listing','Report an incorrect or closed listing'); }
