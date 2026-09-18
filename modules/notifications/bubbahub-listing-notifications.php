<?php
/**
 * BubbaHub Directory - Listing change notifications.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'save_post_group', 'bubbahub_directory_listing_change_notification', 99, 3 );


add_action( 'user_register', 'bubbahub_directory_send_new_leader_welcome', 20, 1 );
add_filter( 'wp_send_new_user_notification_to_user', 'bubbahub_directory_suppress_generic_new_user_email', 10, 2 );

function bubbahub_directory_send_new_leader_welcome( $user_id ) {
    $user = get_userdata( (int) $user_id );
    if ( ! $user || ! is_email( $user->user_email ) || get_user_meta( $user_id, '_bubbahub_welcome_sent', true ) ) return;

    $reset_key = get_password_reset_key( $user );
    $reset_url = ! is_wp_error( $reset_key ) ? network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( $reset_key ) . '&login=' . rawurlencode( $user->user_login ), 'login' ) : wp_lostpassword_url();
    $support_url = home_url( '/support/' );
    $my_hub_url = home_url( '/my-hub/' );

    // Approved Bubba Hub leaders receive the leader onboarding email.
    $is_leader = array_intersect( array( 'leader', 'leaderpro' ), (array) $user->roles );
    if ( $is_leader ) {
        $portal_url = home_url( '/leader-portal/' );
        $subject = '🎉 Welcome to Bubba Hub! Your account is ready';
        $body = '<div style="font-family:Arial,sans-serif;max-width:680px;margin:0 auto;color:#333;line-height:1.6;">';
        $body .= '<div style="padding:24px;text-align:center;border-radius:14px 14px 0 0;background:#f8e8ef;"><h1 style="margin:0;">Bubba Hub 💛</h1></div><div style="padding:30px;">';
        $body .= '<p>Hey there! 👋</p><h2>Welcome to Bubba Hub! 🎉</h2><p>We’re so excited to have you on board. Your Bubba Hub account is ready, and you can now access your Leader Portal.</p>';
        $body .= '<p><strong>Username:</strong> ' . esc_html( $user->user_login ) . '</p>';
        $body .= '<p style="text-align:center;margin:28px 0;"><a href="' . esc_url( $reset_url ) . '" style="display:inline-block;padding:14px 24px;border-radius:8px;background:#8bc6c9;color:#fff;text-decoration:none;font-weight:bold;">Set Your Password & Access Your Account</a></p>';
        $body .= '<p style="text-align:center;margin:28px 0;"><a href="' . esc_url( $portal_url ) . '" style="display:inline-block;padding:14px 24px;border-radius:8px;background:#f3a6b8;color:#fff;text-decoration:none;font-weight:bold;">Open Your Leader Portal</a></p>';
        $body .= '<h3>🌟 What can you do?</h3><ul><li>Manage and update your listings</li><li>Keep classes and schedules up to date</li><li>Manage bookings and reservations</li><li>Keep venues and locations up to date</li><li>Connect with local families and the Bubba Hub community</li></ul>';
        $body .= '<p><a href="' . esc_url( $support_url ) . '">Visit the Bubba Hub Support Centre</a></p><p>If you need anything at all, just reply to this email — we’re always happy to help.</p><p>Warmly,<br><strong>The Bubba Hub Team</strong> 💛</p><p style="font-size:13px;color:#777;">Bubba Hub · bubbahub.co.uk · @bubbahubsw on Facebook & Instagram</p></div></div>';
    } else {
        // Parents/families get a separate My Hub onboarding email focused on discovering
        // activities, building child profiles and creating a personalised weekly planner.
        $subject = '💛 Welcome to Bubba Hub! Let’s find your family’s next adventure';
        $body = '<div style="font-family:Arial,sans-serif;max-width:680px;margin:0 auto;color:#333;line-height:1.6;">';
        $body .= '<div style="padding:24px;text-align:center;border-radius:14px 14px 0 0;background:#f8e8ef;"><h1 style="margin:0;">Bubba Hub 💛</h1><p style="margin:8px 0 0;">Your local family hub</p></div><div style="padding:30px;">';
        $body .= '<p>Hey there! 👋</p><h2>Welcome to Bubba Hub! 🎉</h2>';
        $body .= '<p>Bubba Hub is here to make finding things to do with your family a little easier. We bring together pregnancy, baby, toddler and family groups, classes, activities and local support across Devon & Cornwall, so you can discover what is happening near you without having to search lots of different places.</p>';
        $body .= '<p>Your account gives you access to <strong>My Hub</strong> — your own personalised space for finding and organising the things that matter to your family.</p>';
        $body .= '<p style="text-align:center;margin:28px 0;"><a href="' . esc_url( $my_hub_url ) . '" style="display:inline-block;padding:14px 24px;border-radius:8px;background:#f3a6b8;color:#fff;text-decoration:none;font-weight:bold;">Open My Hub</a></p>';
        $body .= '<h3>👨‍👩‍👧 Build your family profile</h3>';
        $body .= '<p>Add your children to My Hub and build profiles around their ages and interests. You can use this information to make Bubba Hub more useful for your family and help you focus on activities that are relevant to you.</p>';
        $body .= '<h3>📍 Tell us what you’re looking for</h3>';
        $body .= '<p>Set your <strong>preferred locations</strong> and <strong>interests</strong> so you can explore groups, classes and activities that match the areas and things your family enjoys.</p>';
        $body .= '<h3>📅 Plan your week</h3>';
        $body .= '<p>My Hub includes a <strong>weekly planner</strong> that can use your child profiles, interests and preferred locations alongside the days and times that local groups run. It is designed to help you see suitable activities together in one place and make planning your week easier.</p>';
        $body .= '<h3>🎟️ Keep track of bookings</h3>';
        $body .= '<p>When you book participating classes through Bubba Hub, your upcoming bookings can appear in My Hub, giving you a simple place to keep track of what you have coming up.</p>';
        $body .= '<h3>🔔 Stay in the loop</h3>';
        $body .= '<p>You can manage your Bubba Hub notification preferences so you can receive relevant updates such as class and booking alerts, email updates, SMS reminders where available, and community alerts.</p>';
        $body .= '<h3>🌈 More than a directory</h3>';
        $body .= '<p>Bubba Hub is growing into a community space connecting families with local group leaders, businesses and specialists. You can discover useful support, explore local resources and help us improve Bubba Hub by recommending resources you think other families would find helpful.</p>';
        $body .= '<p style="text-align:center;margin:28px 0;"><a href="' . esc_url( home_url( '/whats-on/' ) ) . '" style="display:inline-block;padding:14px 24px;border-radius:8px;background:#8bc6c9;color:#fff;text-decoration:none;font-weight:bold;">Explore What’s On</a></p>';
        $body .= '<p><a href="' . esc_url( $support_url ) . '">Visit the Bubba Hub Support Centre</a></p>';
        $body .= '<p>If you need anything or have an idea for Bubba Hub, we’d love to hear from you. 💛</p><p>Warmly,<br><strong>The Bubba Hub Team</strong></p>';
        $body .= '<p style="font-size:13px;color:#777;">Bubba Hub · bubbahub.co.uk · @bubbahubsw on Facebook & Instagram</p></div></div>';
    }

    $from_name = function() { return 'Bubba Hub'; };
    $from_email = function() { return 'contact@bubbahub.co.uk'; };
    add_filter( 'wp_mail_from_name', $from_name ); add_filter( 'wp_mail_from', $from_email );
    $sent = wp_mail( $user->user_email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
    remove_filter( 'wp_mail_from_name', $from_name ); remove_filter( 'wp_mail_from', $from_email );
    if ( $sent ) update_user_meta( $user_id, '_bubbahub_welcome_sent', current_time( 'mysql' ) );
}
function bubbahub_directory_suppress_generic_new_user_email( $send, $user ) {
    if ( $user instanceof WP_User && get_user_meta( $user->ID, '_bubbahub_welcome_sent', true ) ) return false;
    return $send;
}

function bubbahub_directory_notification_is_import() {
    return ! empty( $GLOBALS['bubbahub_directory_csv_internal_import'] );
}

function bubbahub_directory_notification_is_automatic() {
    return defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE;
}

function bubbahub_directory_send_listing_email( $to, $subject, $body_html ) {
    if ( ! $to || ! is_email( $to ) ) return false;
    $headers = array( 'Content-Type: text/html; charset=UTF-8' );
    return wp_mail( $to, $subject, $body_html, $headers );
}

function bubbahub_directory_listing_change_notification( $post_id, $post, $update ) {
    if ( ! $update || ! $post || 'group' !== $post->post_type ) return;
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || bubbahub_directory_notification_is_import() || bubbahub_directory_notification_is_automatic() ) return;
    if ( ! is_user_logged_in() ) return;

    $user = wp_get_current_user();
    $author_id = (int) $post->post_author;
    if ( ! $author_id || ! $user->ID ) return;

    $is_admin = user_can( $user, 'manage_options' );
    $is_leader = ( $user->ID === $author_id );
    if ( ! $is_admin && ! $is_leader ) return;

    $leader = get_userdata( $author_id );
    if ( ! $leader || ! is_email( $leader->user_email ) ) return;

    $listing_title = get_the_title( $post_id );
    $listing_url   = get_permalink( $post_id );
    $portal_url    = home_url( '/leader-portal/' );
    $support_url   = home_url( '/support/' );

    if ( $is_admin ) {
        $subject = '🔔 Your Bubba Hub listing was recently updated';
        $body = '<div style="font-family:Arial,sans-serif;max-width:680px;margin:0 auto;color:#333;line-height:1.6;">';
        $body .= '<div style="padding:24px;text-align:center;border-radius:14px 14px 0 0;background:#f8e8ef;"><h1 style="margin:0;">Bubba Hub 💛</h1></div>';
        $body .= '<div style="padding:30px;">';
        $body .= '<p>Hey there! 👋</p>';
        $body .= '<h2>Your listing was recently updated</h2>';
        $body .= '<p>Our Bubba Hub admin team has recently updated <strong>' . esc_html( $listing_title ) . '</strong>.</p>';
        $body .= '<p>We’d really appreciate it if you could take a quick look and check that all of the information is correct.</p>';
        $body .= '<p style="text-align:center;margin:28px 0;"><a href="' . esc_url( $listing_url ) . '" style="display:inline-block;padding:14px 24px;border-radius:8px;background:#f3a6b8;color:#fff;text-decoration:none;font-weight:bold;">View Your Listing</a></p>';
        $body .= '<p>Please check your <strong>times, prices, venue, contact details, description and other information</strong>. If anything needs changing, you can update your listing from your Leader Portal.</p>';
        $body .= '<p>If something doesn’t look right or you’re unsure about a change, just reply to this email and we’ll be happy to help.</p>';
        $body .= '<hr style="border:0;border-top:1px solid #eee;margin:30px 0;">';
        $body .= '<p><strong>Thanks for helping us keep Bubba Hub accurate for local families! 🌟</strong></p>';
        $body .= '<p><a href="' . esc_url( $portal_url ) . '">Leader Portal</a> · <a href="' . esc_url( $support_url ) . '">Support</a></p>';
        $body .= '<p>Warmly,<br><strong>The Bubba Hub Team</strong></p>';
        $body .= '</div></div>';
        bubbahub_directory_send_listing_email( $leader->user_email, $subject, $body );
    } else {
        $subject = '🎉 Your Bubba Hub listing has been updated';
        $status_text = ( 'publish' === $post->post_status ) ? 'is now live on Bubba Hub' : 'has been updated';
        $body = '<div style="font-family:Arial,sans-serif;max-width:680px;margin:0 auto;color:#333;line-height:1.6;">';
        $body .= '<div style="padding:24px;text-align:center;border-radius:14px 14px 0 0;background:#f8e8ef;"><h1 style="margin:0;">Bubba Hub 💛</h1></div>';
        $body .= '<div style="padding:30px;">';
        $body .= '<p>Hey there! 👋</p>';
        $body .= '<h2>Your listing has been updated 🎉</h2>';
        $body .= '<p><strong>' . esc_html( $listing_title ) . '</strong> ' . esc_html( $status_text ) . '.</p>';
        $body .= '<p>Thanks for keeping your Bubba Hub information up to date. Families rely on your listing to find the right groups, classes and activities.</p>';
        $body .= '<p style="text-align:center;margin:28px 0;"><a href="' . esc_url( $listing_url ) . '" style="display:inline-block;padding:14px 24px;border-radius:8px;background:#f3a6b8;color:#fff;text-decoration:none;font-weight:bold;">View Your Listing</a></p>';
        $body .= '<p>If you made a change, please check the live listing to make sure everything looks as you expected.</p>';
        $body .= '<p>Need a hand? Just reply to this email or visit our <a href="' . esc_url( $support_url ) . '">Support Page</a>.</p>';
        $body .= '<p>Warmly,<br><strong>The Bubba Hub Team</strong></p>';
        $body .= '<p style="font-size:13px;color:#777;">bubbahub.co.uk · @bubbahubsw on Facebook & Instagram</p>';
        $body .= '</div></div>';
        bubbahub_directory_send_listing_email( $leader->user_email, $subject, $body );
    }
}
