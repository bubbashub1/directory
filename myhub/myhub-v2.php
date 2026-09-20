<?php
/**
 * BubbaHub My Hub – stable dashboard layer.
 *
 * This file is intentionally self-contained and safe to load from myhub.php.
 * Shortcodes: [bubbahub_my_hub] and [bubbahub-my-hub]
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* Register once, after the base My Hub loader has loaded. */
add_action( 'init', 'bubbahub_myhub_v3_register', 30 );

if ( ! function_exists( 'bubbahub_myhub_v3_register' ) ) {
    function bubbahub_myhub_v3_register() {
        remove_shortcode( 'bubbahub_my_hub' );
        remove_shortcode( 'bubbahub-my-hub' );
        add_shortcode( 'bubbahub_my_hub', 'bubbahub_myhub_v3_render' );
        add_shortcode( 'bubbahub-my-hub', 'bubbahub_myhub_v3_render' );
    }
}

if ( ! function_exists( 'bubbahub_myhub_v3_child_field' ) ) {
    function bubbahub_myhub_v3_child_field( $post_id, $field, $default = '' ) {
        $post_id = absint( $post_id );
        $field   = sanitize_key( $field );
        if ( ! $post_id || ! $field ) {
            return $default;
        }

        if ( function_exists( 'get_field' ) ) {
            $value = get_field( $field, $post_id, false );
            if ( null !== $value && false !== $value && '' !== $value ) {
                return $value;
            }
        }

        $value = get_post_meta( $post_id, $field, true );
        return ( '' !== $value && false !== $value ) ? $value : $default;
    }
}

if ( ! function_exists( 'bubbahub_myhub_v3_date' ) ) {
    function bubbahub_myhub_v3_date( $date ) {
        if ( empty( $date ) ) {
            return '';
        }
        $date = sanitize_text_field( $date );
        $timestamp = strtotime( $date );
        return $timestamp ? wp_date( 'j M Y', $timestamp ) : '';
    }
}

if ( ! function_exists( 'bubbahub_myhub_v3_school_status_label' ) ) {
    function bubbahub_myhub_v3_school_status_label( $status ) {
        $labels = array(
            'active'       => 'Active',
            'closed'       => 'Closed',
            'now_in_school'=> 'Now in School',
        );
        $status = sanitize_key( $status );
        return isset( $labels[ $status ] ) ? $labels[ $status ] : 'Active';
    }
}

if ( ! function_exists( 'bubbahub_myhub_v3_school_countdown' ) ) {
    function bubbahub_myhub_v3_school_countdown( $date ) {
        if ( empty( $date ) ) {
            return '';
        }

        $date = sanitize_text_field( $date );
        $deadline = DateTime::createFromFormat( 'Y-m-d', $date );
        if ( ! $deadline || $deadline->format( 'Y-m-d' ) !== $date ) {
            return '';
        }

        $today = new DateTime( 'today' );
        if ( $deadline < $today ) {
            return 'Deadline passed';
        }

        $diff   = $today->diff( $deadline );
        $parts  = array();
        if ( $diff->y ) { $parts[] = $diff->y . 'y'; }
        if ( $diff->m ) { $parts[] = $diff->m . 'm'; }
        if ( $diff->d || ! $parts ) { $parts[] = $diff->d . 'd'; }

        return implode( ' ', $parts ) . ' left to apply';
    }
}

if ( ! function_exists( 'bubbahub_myhub_v3_auto_school_deadline' ) ) {
    function bubbahub_myhub_v3_auto_school_deadline( $dob ) {
        if ( empty( $dob ) ) {
            return '';
        }

        $dob = sanitize_text_field( $dob );
        $birth = DateTime::createFromFormat( 'Y-m-d', $dob );
        if ( ! $birth || $birth->format( 'Y-m-d' ) !== $dob ) {
            return '';
        }

        /* Keep the existing project rule, but avoid invalid dates. */
        $entry_year = (int) $birth->format( 'Y' ) + 5;
        if ( (int) $birth->format( 'm' ) > 8 || ( 8 === (int) $birth->format( 'm' ) && (int) $birth->format( 'd' ) > 31 ) ) {
            $entry_year++;
        }

        return $entry_year . '-01-15';
    }
}

if ( ! function_exists( 'bubbahub_myhub_v3_antenatal_status' ) ) {
    function bubbahub_myhub_v3_antenatal_status( $due ) {
        $due = sanitize_text_field( $due );
        $date = DateTime::createFromFormat( 'Y-m-d', $due );
        if ( ! $date || $date->format( 'Y-m-d' ) !== $due ) {
            return array();
        }

        $today = new DateTime( 'today' );
        $class_start = clone $date;
        $class_start->modify( '-12 weeks' ); /* 28 weeks */
        $class_end = clone $date;
        $class_end->modify( '-8 weeks' ); /* 32 weeks */

        /* Only show the "Baby is Here" action from 38 weeks onwards. */
        $baby_here_start = clone $date;
        $baby_here_start->modify( '-2 weeks' ); /* 38 weeks */

        if ( $today >= $baby_here_start ) {
            return array( 'stage' => 'baby_here', 'class_start' => $class_start, 'class_end' => $class_end, 'baby_here_start' => $baby_here_start );
        }

        if ( $today >= $class_end ) {
            return array( 'stage' => 'nearly_time', 'class_start' => $class_start, 'class_end' => $class_end );
        }

        return array( 'stage' => 'classes', 'class_start' => $class_start, 'class_end' => $class_end );
    }
}

if ( ! function_exists( 'bubbahub_myhub_v3_school_tracker' ) ) {
    function bubbahub_myhub_v3_school_tracker( $child_id, $status, $dob ) {
        if ( 'expecting' === sanitize_key( $status ) ) {
            return '';
        }

        $child_id    = absint( $child_id );
        $school_name = bubbahub_myhub_v3_child_field( $child_id, 'school_name' );
        $school_status = bubbahub_myhub_v3_child_field( $child_id, 'school_application_status', 'active' );
        $deadline    = bubbahub_myhub_v3_child_field( $child_id, 'school_application_deadline' );
        $school_year = bubbahub_myhub_v3_child_field( $child_id, 'school_year' );
        $ofsted      = bubbahub_myhub_v3_child_field( $child_id, 'ofsted_rating' );

        if ( ! $deadline && $dob ) {
            $deadline = bubbahub_myhub_v3_auto_school_deadline( $dob );
        }

        $status_label = bubbahub_myhub_v3_school_status_label( $school_status );
        $countdown    = bubbahub_myhub_v3_school_countdown( $deadline );
        $has_school   = ! empty( $school_name );
        $tracker_class = $has_school ? 'school-hub' : 'school-application';
        $title = $has_school ? 'School & Childcare Hub' : 'School Application';

        ob_start();
        ?>
        <div class="bh-myhub-tracker bh-myhub-school-tracker <?php echo esc_attr( $tracker_class ); ?>">
            <div class="bh-myhub-school-tracker-head">
                <div class="bh-myhub-tracker-title"><?php echo $has_school ? '🏫<span>' : '🏫<span>'; ?><?php echo esc_html( $title ); ?></span></div>
                <span class="bh-myhub-school-status <?php echo esc_attr( sanitize_html_class( $school_status ) ); ?>"><?php echo esc_html( $status_label ); ?></span>
            </div>
            <?php if ( $has_school ) : ?>
                <strong><?php echo esc_html( $school_name ); ?></strong>
                <?php if ( $ofsted || $school_year ) : ?>
                    <div class="bh-myhub-school-meta">
                        <?php if ( $ofsted ) : ?><span>Ofsted: <?php echo esc_html( $ofsted ); ?></span><?php endif; ?>
                        <?php if ( $school_year ) : ?><span><?php echo esc_html( $school_year ); ?></span><?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php elseif ( $deadline ) : ?>
                <strong>Application deadline: <?php echo esc_html( bubbahub_myhub_v3_date( $deadline ) ); ?></strong>
            <?php else : ?>
                <strong>School application details not added yet</strong>
            <?php endif; ?>
            <?php if ( $countdown && ! $has_school && 'now_in_school' !== $school_status && 'closed' !== $school_status ) : ?>
                <span class="bh-myhub-school-countdown">⏳ <?php echo esc_html( $countdown ); ?></span>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

if ( ! function_exists( 'bubbahub_myhub_v3_support_reply_handler' ) ) {
    function bubbahub_myhub_v3_support_reply_handler() {
        if ( empty( $_POST['bh_myhub_support_reply_action'] ) || ! is_user_logged_in() ) {
            return;
        }
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bh_myhub_support_reply_nonce'] ?? '' ) ), 'bh_myhub_support_reply' ) ) {
            return;
        }

        $uid     = get_current_user_id();
        $id      = absint( $_POST['support_request_id'] ?? 0 );
        $reply   = trim( sanitize_textarea_field( wp_unslash( $_POST['support_reply'] ?? '' ) ) );
        $request = $id ? get_post( $id ) : false;

        if ( ! $id || ! $request || 'bh_support_request' !== $request->post_type || (int) $request->post_author !== $uid || '' === $reply ) {
            return;
        }

        $history   = get_post_meta( $id, '_bh_support_replies', true );
        $history   = is_array( $history ) ? $history : array();
        $user      = wp_get_current_user();
        $history[] = array(
            'user'  => $uid,
            'name'  => $user->display_name,
            'reply' => $reply,
            'time'  => current_time( 'mysql' ),
        );
        update_post_meta( $id, '_bh_support_replies', $history );
        update_post_meta( $id, '_bh_support_status', 'user_replied' );

        $topic  = sanitize_text_field( get_post_meta( $id, '_bh_support_topic', true ) );
        $email  = sanitize_email( get_post_meta( $id, '_bh_support_email', true ) );
        $matches = array_map( 'absint', (array) get_post_meta( $id, '_bh_support_matches', true ) );
        $subject = 'BubbaHub support follow-up' . ( $topic ? ' – ' . $topic : '' );
        $body = "A family has replied to a BubbaHub support conversation.\\n\\n";
        $body .= "Topic: " . $topic . "\\n";
        $body .= "Reply from: " . $user->display_name . "\\n\\n";
        $body .= "Reply:\\n" . $reply . "\\n\\n";
        $body .= "Please log in to the Leader Portal to continue the conversation.";

        foreach ( $matches as $leader_id ) {
            $leader = get_userdata( $leader_id );
            if ( $leader && is_email( $leader->user_email ) ) {
                wp_mail( $leader->user_email, $subject, $body );
            }
        }
        if ( function_exists( 'wp_mail' ) && is_email( $email ) ) {
            wp_mail( $email, 'BubbaHub support reply received', "Your follow-up has been recorded.\\n\\n" . $reply );
        }

        wp_safe_redirect( add_query_arg( 'bh_support_reply_sent', '1', wp_get_referer() ? wp_get_referer() : get_permalink() ) );
        exit;
    }
}
add_action( 'template_redirect', 'bubbahub_myhub_v3_support_reply_handler' );

if ( ! function_exists( 'bubbahub_myhub_v3_support_requests' ) ) {
    function bubbahub_myhub_v3_support_requests( $uid ) {
        $requests = get_posts( array(
            'post_type'      => 'bh_support_request',
            'post_status'    => 'publish',
            'author'         => absint( $uid ),
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ) );

        ob_start();
        ?>
        <section class="bh-myhub-section bh-myhub-support-section">
            <div class="bh-myhub-section-heading">
                <div>
                    <div class="bh-myhub-kicker">SUPPORT &amp; GUIDANCE</div>
                    <h2>My Support Requests</h2>
                    <p>Your questions and specialist replies, all in one place.</p>
                </div>
                <a class="bh-myhub-button" href="<?php echo esc_url( home_url( '/support/' ) . '#ask-specialist' ); ?>">＋ Ask A Specialist</a>
            </div>
            <?php if ( ! empty( $_GET['bh_support_reply_sent'] ) ) : ?>
                <div class="bh-myhub-support-notice">Your reply has been sent.</div>
            <?php endif; ?>
            <?php if ( ! $requests ) : ?>
                <div class="bh-myhub-support-empty">
                    <strong>No support requests yet.</strong>
                    <span>Use <em>Ask A Specialist</em> in Support &amp; Guidance to send your first question.</span>
                </div>
            <?php else : ?>
                <div class="bh-myhub-support-list">
                <?php foreach ( $requests as $request ) :
                    $topic    = get_post_meta( $request->ID, '_bh_support_topic', true );
                    $replies  = get_post_meta( $request->ID, '_bh_support_replies', true );
                    $replies  = is_array( $replies ) ? $replies : array();
                    ?>
                    <article class="bh-myhub-support-thread">
                        <div class="bh-myhub-support-thread-head">
                            <div>
                                <div class="bh-myhub-support-topic"><?php echo esc_html( $topic ? $topic : 'Support question' ); ?></div>
                                <h3><?php echo esc_html( get_the_title( $request ) ); ?></h3>
                            </div>
                            <time datetime="<?php echo esc_attr( get_post_time( 'c', true, $request ) ); ?>"><?php echo esc_html( wp_date( 'j M Y, H:i', get_post_time( 'U', true, $request ) ) ); ?></time>
                        </div>
                        <div class="bh-myhub-support-email-meta"><strong>From:</strong> You <span>•</span> <strong>To:</strong> Bubba Hub Support</div>
                        <div class="bh-myhub-support-message bh-myhub-support-message-user">
                            <div class="bh-myhub-support-message-label">Your message</div>
                            <div><?php echo nl2br( esc_html( get_post_field( 'post_content', $request ) ) ); ?></div>
                        </div>
                        <?php foreach ( $replies as $reply ) :
                            $name = ! empty( $reply['name'] ) ? $reply['name'] : 'Bubba Hub Specialist';
                            $body = isset( $reply['reply'] ) ? $reply['reply'] : '';
                            if ( '' === trim( $body ) ) { continue; }
                            ?>
                            <div class="bh-myhub-support-message bh-myhub-support-message-specialist">
                                <div class="bh-myhub-support-message-label"><?php echo esc_html( $name ); ?> replied</div>
                                <div><?php echo nl2br( esc_html( $body ) ); ?></div>
                                <?php if ( ! empty( $reply['time'] ) ) : ?><time><?php echo esc_html( wp_date( 'j M Y, H:i', strtotime( $reply['time'] ) ) ); ?></time><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <form class="bh-myhub-support-reply-form" method="post">
                            <?php wp_nonce_field( 'bh_myhub_support_reply', 'bh_myhub_support_reply_nonce' ); ?>
                            <input type="hidden" name="bh_myhub_support_reply_action" value="1">
                            <input type="hidden" name="support_request_id" value="<?php echo esc_attr( $request->ID ); ?>">
                            <label for="bh-support-reply-<?php echo esc_attr( $request->ID ); ?>">Reply to this conversation</label>
                            <textarea id="bh-support-reply-<?php echo esc_attr( $request->ID ); ?>" name="support_reply" rows="4" placeholder="Write your reply..." required></textarea>
                            <button type="submit" class="bh-myhub-button">Send reply</button>
                        </form>
                    </article>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }
}

if ( ! function_exists( 'bubbahub_myhub_v3_family_snapshot' ) ) {
    function bubbahub_myhub_v3_family_snapshot( $uid, $children, $bookings, $saved_calendars ) {
        $upcoming = array_slice( (array) $bookings, 0, 3 );
        $stats = array(
            'children' => count( (array) $children ),
            'bookings' => count( (array) $bookings ),
            'calendars' => count( (array) $saved_calendars ),
        );
        ob_start(); ?>
        <section class="bh-myhub-snapshot" aria-label="Family snapshot">
            <div class="bh-myhub-snapshot-main">
                <div class="bh-myhub-kicker">YOUR FAMILY AT A GLANCE</div>
                <h2>Everything your family needs, in one place.</h2>
                <p>Keep your children, activities, plans and bookings connected through Bubba Hub.</p>
                <div class="bh-myhub-quick-actions">
                    <a class="bh-myhub-quick-action primary" href="<?php echo esc_url( home_url( '/find-a-group/' ) ); ?>"><span>🔎</span><strong>Find a Group</strong></a>
                    <a class="bh-myhub-quick-action" href="<?php echo esc_url( add_query_arg( 'bh_account_settings', '1', get_permalink() ) ); ?>"><span>⚙️</span><strong>Account Settings</strong></a>
                    <a class="bh-myhub-quick-action" href="<?php echo esc_url( home_url( '/my-groups/' ) ); ?>"><span>♡</span><strong>My Groups</strong></a>
                </div>
            </div>
            <div class="bh-myhub-snapshot-stats">
                <div><strong><?php echo esc_html( $stats['children'] ); ?></strong><span>Family profiles</span></div>
                <div><strong><?php echo esc_html( $stats['bookings'] ); ?></strong><span>Upcoming bookings</span></div>
                <div><strong><?php echo esc_html( $stats['calendars'] ); ?></strong><span>Saved calendars</span></div>
            </div>
            <?php if ( $upcoming ) : ?>
            <div class="bh-myhub-snapshot-upcoming">
                <div class="bh-myhub-snapshot-upcoming-head"><strong>Coming up</strong><a href="<?php echo esc_url( home_url( '/my-bookings/' ) ); ?>">View bookings →</a></div>
                <?php foreach ( $upcoming as $booking ) : ?>
                    <a class="bh-myhub-upcoming-item" href="<?php echo esc_url( home_url( '/my-bookings/' ) ); ?>">
                        <span class="bh-myhub-upcoming-date"><?php echo esc_html( wp_date( 'D j M · H:i', $booking['stamp'] ) ); ?></span>
                        <span><strong><?php echo esc_html( $booking['title'] ? $booking['title'] : $booking['group'] ); ?></strong><?php if ( ! empty( $booking['venue'] ) ) : ?><small>⌖ <?php echo esc_html( $booking['venue'] ); ?></small><?php endif; ?></span>
                        <span aria-hidden="true">→</span>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }
}

if ( ! function_exists( 'bubbahub_myhub_v3_render' ) ) {
    function bubbahub_myhub_v3_render() {
        if ( ! is_user_logged_in() ) {
            return '<div class="bh-myhub-login"><h2>Welcome to My Hub</h2><p>Please log in to see your family dashboard.</p></div>';
        }

        // Opening/editing a saved calendar from My Hub should render the planner itself,
        // even when the planner shortcode is on a page that also contains My Hub.
        if ( ( isset( $_GET['bh_saved_calendar'] ) || isset( $_GET['bh_edit_calendar'] ) )
            && function_exists( 'bubbahub_myhub_weekly_planner_v2_shortcode' ) ) {
            return bubbahub_myhub_weekly_planner_v2_shortcode();
        }

        // Saved calendars belong to the logged-in user and must be prepared in the My Hub render scope.
        $saved_calendars = function_exists( 'bubbahub_myhub_planner_v2_saved_calendars' ) ? bubbahub_myhub_planner_v2_saved_calendars() : array();
        $planner_page_url = '';
        $planner_pages = get_pages( array( 'post_status' => 'publish', 'number' => 100 ) );
        foreach ( $planner_pages as $planner_page ) {
            if ( has_shortcode( $planner_page->post_content, 'bubbahub_weekly_planner_v2' ) ) {
                $planner_page_url = get_permalink( $planner_page->ID );
                break;
            }
        }
        if ( ! $planner_page_url ) {
            $planner_page_url = home_url( '/my-hub/' );
        }

        /* Account Settings uses the same My Hub page. Handle the route directly here
         * so it does not depend on the shortcode interception layer. */
        if ( isset( $_GET['bh_account_settings'] ) ) {
            if ( function_exists( 'bubbahub_account_settings_stage2_shortcode' ) ) {
                return bubbahub_account_settings_stage2_shortcode();
            }
            if ( function_exists( 'bubbahub_account_settings_shortcode' ) ) {
                return bubbahub_account_settings_shortcode();
            }
        }

        /* The Add Child button on My Hub uses the same page with bh_add_child=1.
         * Route that request to the real child editor supplied by profile-settings.php,
         * so the inline loader receives a form and the POST is handled by the same save code. */
        if ( isset( $_GET['bh_add_child'] ) && function_exists( 'bubbahub_profile_child_form' ) ) {
            return bubbahub_profile_child_form( isset( $_GET['child_id'] ) ? absint( $_GET['child_id'] ) : 0 );
        }

        $uid  = get_current_user_id();
        $user = wp_get_current_user();

        wp_enqueue_style( 'bubbahub-myhub' );
        wp_enqueue_style( 'bubbahub-myhub-groups' );
        wp_enqueue_script( 'bubbahub-myhub-groups' );
        if ( function_exists( 'wp_enqueue_style' ) ) {
            wp_enqueue_style( 'bubbahub-profile-settings' );
            wp_enqueue_script( 'bubbahub-profile-settings' );
        }

        $children = get_posts( array(
            'post_type'      => 'bh_child',
            'post_status'    => 'publish',
            'author'         => $uid,
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ) );

        $selected_children = get_user_meta( $uid, 'bh_myhub_selected_children', true );
        if ( ! is_array( $selected_children ) ) {
            $selected_children = array();
        }
        $selected_children = array_map( 'absint', $selected_children );

        ob_start();
        ?>
        <div class="bh-myhub bh-myhub-v3" data-bh-myhub="1">
            <section class="bh-myhub-hero">
                <div>
                    <div class="bh-myhub-kicker">MY HUB</div>
                    <h1>Welcome back, <?php echo esc_html( $user->first_name ? $user->first_name : $user->display_name ); ?></h1>
                    <p>Your family overview, interests, local activities and pregnancy journey — all in one place.</p>
                </div>
                <div class="bh-myhub-next">
                    <div class="bh-myhub-eyebrow">YOUR FAMILY HUB</div>
                    <strong>Find activities that fit your family</strong>
                    <p>Your saved preferences help Bubba Hub personalise your group suggestions.</p>
                </div>
            </section>

            <?php echo bubbahub_myhub_v3_family_snapshot( $uid, $children, function_exists( 'bubbahub_stage9_get_bookings' ) ? bubbahub_stage9_get_bookings() : array(), $saved_calendars ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <section class="bh-myhub-section">
                <div class="bh-myhub-section-heading">
                    <div>
                        <div class="bh-myhub-kicker">YOUR FAMILY</div>
                        <h2>My Child Profiles</h2>
                        <p>Manage the children used to personalise your group suggestions.</p>
                    </div>
                    <div class="bh-myhub-family-add-buttons"><a class="bh-profile-add-child" href="<?php echo esc_url( add_query_arg( array( 'bh_add_child' => '1', 'bh_profile_type' => 'born' ), get_permalink() ) ); ?>">＋ Add Child</a><a class="bh-profile-add-bump" href="<?php echo esc_url( add_query_arg( array( 'bh_add_child' => '1', 'bh_profile_type' => 'expecting' ), get_permalink() ) ); ?>">＋ Add Bump</a></div>
                </div>

                <div class="bh-myhub-family-carousel">
                    <div class="bh-myhub-family-viewport">
                    <div class="bh-myhub-family-grid" id="bh-myhub-children">
                    <?php if ( $children ) : ?>
                        <?php foreach ( $children as $child ) :
                            $name   = bubbahub_myhub_v3_child_field( $child->ID, 'child_name', $child->post_title );
                            $status = bubbahub_myhub_v3_child_field( $child->ID, 'child_status', 'born' );
                            $dob    = bubbahub_myhub_v3_child_field( $child->ID, 'child_date_of_birth' );
                            $due    = bubbahub_myhub_v3_child_field( $child->ID, 'child_due_date' );
                            $avatar  = bubbahub_myhub_v3_child_field( $child->ID, 'avatar_url' );
                            $gender  = bubbahub_myhub_v3_child_field( $child->ID, 'child_gender' );
                            $is_expecting = ( 'expecting' === sanitize_key( $status ) && ! empty( $due ) );
                            $initial = function_exists( 'mb_substr' ) ? mb_substr( (string) $name, 0, 1 ) : substr( (string) $name, 0, 1 );
                            ?>
                            <article class="bh-myhub-child-card">
                                <div class="bh-myhub-child-top">
                                    <div class="bh-myhub-avatar"><?php if ( $avatar ) : ?><img src="<?php echo esc_url( $avatar ); ?>" alt="<?php echo esc_attr( $name ); ?>"><?php else : ?><?php echo esc_html( strtoupper( $initial ) ); ?><?php endif; ?></div>
                                    <div>
                                        <h3><?php echo esc_html( $name ); ?></h3>
                                        <?php if ( $is_expecting ) : ?>
                                            <p>Expecting · Due <?php echo esc_html( bubbahub_myhub_v3_date( $due ) ); ?></p>
                                        <?php elseif ( $dob ) : ?>
                                            <p><?php echo esc_html( bubbahub_myhub_v3_date( $dob ) ); ?></p>
                                        <?php else : ?>
                                            <p>Date of birth not added</p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ( $is_expecting ) :
                                    $antenatal = bubbahub_myhub_v3_antenatal_status( $due );
                                    ?>
                                    <div class="bh-myhub-tracker pregnancy bh-myhub-antenatal-tracker">
                                        <div class="bh-myhub-tracker-title">🤰 Antenatal Groups Hub</div>
                                        <strong>Antenatal Support &amp; Classes</strong>
                                        <?php if ( 'classes' === $antenatal['stage'] ) : ?>
                                            <span>Attend antenatal classes between <?php echo esc_html( wp_date( 'F Y', $antenatal['class_start']->getTimestamp() ) ); ?> and <?php echo esc_html( wp_date( 'F Y', $antenatal['class_end']->getTimestamp() ) ); ?>.</span>
                                        <?php elseif ( 'nearly_time' === $antenatal['stage'] ) : ?>
                                            <span>Nearly time! Is your hospital bag packed?</span>
                                        <?php else : ?>
                                            <span>You’re 38 weeks or more. Ready to tell My Hub your baby is here?</span>
                                            <button type="button" class="bh-myhub-button bh-myhub-baby-here" data-bh-baby-here="<?php echo esc_attr( $child->ID ); ?>">👶 Baby is Here</button>
                                        <?php endif; ?>
                                        <span>Due <?php echo esc_html( bubbahub_myhub_v3_date( $due ) ); ?></span>
                                    </div>

                                    <?php if ( 'baby_here' === $antenatal['stage'] && function_exists( 'bubbahub_profile_child_form' ) ) : ?>
                                        <div class="bh-myhub-baby-modal" id="bh-baby-modal-<?php echo esc_attr( $child->ID ); ?>" aria-hidden="true" hidden>
                                            <div class="bh-myhub-baby-modal-backdrop" data-bh-close-baby-modal></div>
                                            <div class="bh-myhub-baby-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="bh-baby-modal-title-<?php echo esc_attr( $child->ID ); ?>">
                                                <button type="button" class="bh-myhub-baby-modal-close" data-bh-close-baby-modal aria-label="Close">×</button>
                                                <div class="bh-myhub-baby-modal-intro">
                                                    <span class="bh-myhub-kicker">BABY IS HERE 👶</span>
                                                    <h2 id="bh-baby-modal-title-<?php echo esc_attr( $child->ID ); ?>">Any changes to make?</h2>
                                                    <p>Update your baby's <strong>date of birth</strong> and <strong>name</strong> here. We'll convert this pregnancy profile into your child's profile.</p>
                                                </div>
                                                <div class="bh-myhub-baby-modal-form">
                                                    <?php echo bubbahub_profile_child_form( $child->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <?php echo bubbahub_myhub_v3_school_tracker( $child->ID, $status, $dob ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <?php endif; ?>

                                <label class="bh-myhub-child-select">
                                    <input type="checkbox" class="bh-myhub-selected-child" data-child-id="<?php echo esc_attr( $child->ID ); ?>" <?php checked( in_array( $child->ID, $selected_children, true ) ); ?>>
                                    Use <?php echo esc_html( $name ); ?> for group suggestions
                                </label>

                                <div class="bh-myhub-child-actions">
                                    <a class="bh-myhub-child-specialist" href="<?php echo esc_url( add_query_arg( array( 'child_id' => $child->ID ), home_url( '/support/' ) ) . '#ask-specialist' ); ?>">Ask A Specialist about <?php echo esc_html( $name ); ?></a>
                                    <a class="bh-myhub-edit-profile" href="<?php echo esc_url( add_query_arg( array( 'bh_add_child' => 1, 'child_id' => $child->ID ), get_permalink() ) ); ?>"><span aria-hidden="true">✎</span> Edit profile</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <div class="bh-myhub-empty-family">
                            <div class="bh-myhub-empty-icon">👋</div>
                            <div><h3>Start your family profile</h3><p>Add your first child so Bubba Hub can personalise activities for your family.</p></div>
                        </div>
                    <?php endif; ?>
                </div>
                    </div>
                    <div class="bh-myhub-family-carousel-controls">
                        <button type="button" class="bh-myhub-carousel-arrow bh-myhub-carousel-prev" aria-label="Previous child" aria-controls="bh-myhub-children">‹</button>
                        <button type="button" class="bh-myhub-carousel-arrow bh-myhub-carousel-next" aria-label="Next child" aria-controls="bh-myhub-children">›</button>
                    </div>
                </div>
            </section>

            <?php if ( ! empty( $saved_calendars ) ) : ?>
            <section class="bh-myhub-section bh-myhub-custom-calendars-section">
                <div class="bh-myhub-section-heading">
                    <div>
                        <div class="bh-myhub-kicker">YOUR CALENDAR</div>
                        <h2>Custom Saved Calendars</h2>
                        <p>Quickly return to the calendars you created from Advanced Search.</p>
                    </div>
                    <a class="bh-myhub-button" href="<?php echo esc_url( $planner_page_url ); ?>">Open Calendar</a>
                </div>
                <div class="bh-myhub-custom-calendars-carousel">
                    <div class="bh-myhub-custom-calendars-viewport">
                        <div class="bh-myhub-custom-calendars-grid" data-custom-calendars-track tabindex="0">
                            <?php foreach ( $saved_calendars as $saved_calendar ) :
                                if ( empty( $saved_calendar['id'] ) || empty( $saved_calendar['name'] ) ) continue;
                                $calendar_id = sanitize_text_field( $saved_calendar['id'] );
                                $calendar_url = add_query_arg( 'bh_saved_calendar', $calendar_id, $planner_page_url );
                                $edit_url = add_query_arg( array( 'bh_saved_calendar' => $calendar_id, 'bh_edit_calendar' => $calendar_id ), $planner_page_url );
                            ?>
                                <article class="bh-myhub-custom-calendar-card" data-saved-calendar-id="<?php echo esc_attr( $calendar_id ); ?>">
                                    <a class="bh-myhub-custom-calendar-main" href="<?php echo esc_url( $calendar_url ); ?>">
                                        <span class="bh-myhub-custom-calendar-icon" aria-hidden="true">★</span>
                                        <span class="bh-myhub-custom-calendar-content">
                                            <strong><?php echo esc_html( $saved_calendar['name'] ); ?></strong>
                                            <span>Open this custom calendar →</span>
                                        </span>
                                    </a>
                                    <div class="bh-myhub-custom-calendar-actions">
                                        <a class="bh-myhub-calendar-edit" href="<?php echo esc_url( $edit_url ); ?>">Edit</a>
                                        <button type="button" class="bh-myhub-calendar-remove" data-remove-calendar="<?php echo esc_attr( $calendar_id ); ?>">Remove</button>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="bh-myhub-custom-calendars-carousel-controls">
                        <button type="button" class="bh-myhub-carousel-arrow bh-myhub-custom-calendar-prev" aria-label="Previous custom calendar">‹</button>
                        <button type="button" class="bh-myhub-carousel-arrow bh-myhub-custom-calendar-next" aria-label="Next custom calendar">›</button>
                    </div>
                </div>
                <script>
                (function(){
                    var section=document.querySelector('.bh-myhub-custom-calendars-section');
                    if(!section)return;
                    var buttons=section.querySelectorAll('[data-remove-calendar]');
                    buttons.forEach(function(button){
                        button.addEventListener('click',function(){
                            if(!window.confirm('Remove this saved calendar from My Hub?'))return;
                            var data=new FormData();
                            data.append('action','bubbahub_delete_custom_calendar');
                            data.append('calendar_id',button.getAttribute('data-remove-calendar')||'');
                            data.append('nonce','<?php echo esc_js( wp_create_nonce( 'bubbahub_save_custom_calendar' ) ); ?>');
                            button.disabled=true;
                            fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',{method:'POST',credentials:'same-origin',body:data})
                                .then(function(response){return response.json();})
                                .then(function(result){
                                    if(result&&result.success){
                                        var card=button.closest('.bh-myhub-custom-calendar-card');
                                        if(card)card.remove();
                                        if(!section.querySelector('.bh-myhub-custom-calendar-card'))window.location.reload();
                                    }else{
                                        button.disabled=false;
                                        window.alert(result&&result.data&&result.data.message?result.data.message:'Unable to remove this calendar.');
                                    }
                                })
                                .catch(function(){
                                    button.disabled=false;
                                    window.alert('Unable to remove this calendar. Please try again.');
                                });
                        });
                    });
                })();
                </script>
            </section>
            <?php else : ?>
            <section class="bh-myhub-section bh-myhub-custom-calendars-section bh-myhub-custom-calendars-empty">
                <div class="bh-myhub-section-heading">
                    <div>
                        <div class="bh-myhub-kicker">YOUR CALENDAR</div>
                        <h2>Custom Saved Calendars</h2>
                        <p>Create a calendar from Advanced Search and it will appear here for quick access.</p>
                    </div>
                    <a class="bh-myhub-button" href="<?php echo esc_url( $planner_page_url ); ?>">Create a Calendar</a>
                </div>
            </section>
            <?php endif; ?>

            <section class="bh-myhub-section bh-myhub-your-groups-section">
                <div class="bh-myhub-section-heading">
                    <div><div class="bh-myhub-kicker">YOUR LOCAL ACTIVITIES</div><h2>Your Groups</h2><p>Saved, recently viewed, visited and suggested groups — all together in one place.</p></div>
                </div>
                <div class="bh-myhub-groups-row bh-myhub-your-groups-row">
                    <?php foreach ( array( 'favourite' => '♡ Saved Groups', 'recently_viewed' => 'Recently Viewed', 'visited' => '✓ Visited Groups', 'suggested' => '✨ Suggested Groups' ) as $type => $title ) : ?>
                        <div class="bh-myhub-group-column">
                            <div class="bh-myhub-group-column-head">
                                <h3><?php echo esc_html( $title ); ?></h3>
                                <a data-group-view-more href="<?php echo esc_url( home_url( '/my-groups/?group_view=' . rawurlencode( $type ) ) ); ?>">View more →</a>
                            </div>
                            <div class="bh-myhub-group-widget" data-myhub-group-widget data-group-type="<?php echo esc_attr( $type ); ?>" data-view-more="1"><div class="bh-myhub-groups-loading">Loading…</div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <?php echo bubbahub_myhub_v3_support_requests( $uid ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>


        </div>
        <script>
document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('[data-bh-baby-here]').forEach(function(button){
        button.addEventListener('click',function(){
            var id=button.getAttribute('data-bh-baby-here');
            var modal=document.getElementById('bh-baby-modal-'+id);
            if(!modal)return;
            modal.hidden=false;
            modal.setAttribute('aria-hidden','false');
            document.body.classList.add('bh-baby-modal-open');
            var status=modal.querySelector('select[name="child_status"]');
            if(status){
                status.value='born';
                status.dispatchEvent(new Event('change',{bubbles:true}));
            }
            var dob=modal.querySelector('input[name="child_date_of_birth"]');
            if(dob) setTimeout(function(){dob.focus();},50);
        });
    });
    document.querySelectorAll('[data-bh-close-baby-modal]').forEach(function(button){
        button.addEventListener('click',function(){
            var modal=button.closest('.bh-myhub-baby-modal');
            if(!modal)return;
            modal.hidden=true;
            modal.setAttribute('aria-hidden','true');
            document.body.classList.remove('bh-baby-modal-open');
        });
    });
});
</script>
        <script>window.BubbaHubMyHubSelectedChildren=<?php echo wp_json_encode( $selected_children ); ?>;</script>
        <?php
        return ob_get_clean();
    }
}
