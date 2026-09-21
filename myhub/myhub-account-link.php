<?php
/**
 * Integrates Bubba Hub Account Settings into the existing Ultimate Member
 * Account dashboard and keeps the legacy My Hub settings URL compatible.
 * Also loads the customer booking lifecycle module.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * Legacy My Hub URLs using ?bh_account_settings=1 are now routed to the
 * Ultimate Member Account dashboard instead of replacing the whole My Hub
 * page. The Stage 2 renderer remains the single source of truth for the
 * existing settings sections and save handlers.
 */
add_action( 'template_redirect', 'bubbahub_account_settings_um_route', 25 );
add_action( 'init', 'bubbahub_account_settings_register_um_tab', 40 );
add_action( 'wp_footer', 'bubbahub_myhub_account_settings_button', 30 );
add_action( 'wp_head', 'bubbahub_account_settings_dashboard_css', 30 );

function bubbahub_account_settings_um_url() {
    if ( function_exists( 'um_get_core_page' ) ) {
        $url = um_get_core_page( 'account' );
        if ( $url ) {
            return esc_url_raw( add_query_arg( 'um_tab', 'bubbahub_settings', $url ) );
        }
    }

    return esc_url_raw( add_query_arg( 'um_tab', 'bubbahub_settings', home_url( '/account/' ) ) );
}

function bubbahub_account_settings_register_um_tab() {
    if ( ! function_exists( 'UM' ) ) return;

    add_filter( 'um_account_page_default_tabs_hook', 'bubbahub_account_settings_um_tab', 160 );
    add_filter( 'um_account_content_hook_bubbahub_settings', 'bubbahub_account_settings_um_content', 20, 2 );
}

function bubbahub_account_settings_um_tab( $tabs ) {
    $tabs[ 160 ]['bubbahub_settings'] = array(
        'icon'         => 'um-faicon-cog',
        'title'        => __( 'Account Settings', 'bubbahub' ),
        'submit_title' => __( 'Account Settings', 'bubbahub' ),
        'custom'       => true,
    );

    return $tabs;
}

function bubbahub_account_settings_um_content( $output = '', $shortcode_args = array() ) {
    if ( ! is_user_logged_in() ) return $output;

    if ( ! function_exists( 'bubbahub_account_settings_stage2_shortcode' ) ) {
        return $output;
    }

    /*
     * Stage 2 was originally rendered as a My Hub replacement when the
     * bh_account_settings query argument was present. Temporarily supplying
     * that flag lets the existing renderer work unchanged inside the UM tab.
     */
    $had_flag = array_key_exists( 'bh_account_settings', $_GET );
    $old_flag = $had_flag ? $_GET['bh_account_settings'] : null;

    $_GET['bh_account_settings'] = '1';

    $content = bubbahub_account_settings_stage2_shortcode();

    if ( $had_flag ) {
        $_GET['bh_account_settings'] = $old_flag;
    } else {
        unset( $_GET['bh_account_settings'] );
    }

    return '<div class="bh-account-settings-um-panel">' . $content . '</div>';
}

function bubbahub_account_settings_um_route() {
    if ( ! is_user_logged_in() || empty( $_GET['bh_account_settings'] ) ) return;

    /*
     * Do not interfere with the actual Ultimate Member Account page. Its
     * custom tab is responsible for rendering the settings.
     */
    if ( function_exists( 'um_get_core_page' ) ) {
        $account_url = um_get_core_page( 'account' );
        if ( $account_url ) {
            $account_id = url_to_postid( $account_url );
            if ( $account_id && is_page( $account_id ) ) return;
        }
    }

    /*
     * Preserve the existing Stage 2 profile redirect behaviour. It has a
     * higher-priority hook and will send the profile section to the native
     * Bubba Hub profile tab before this legacy route runs.
     */
    if ( is_page( 'my-hub' ) ) {
        wp_safe_redirect( bubbahub_account_settings_um_url() );
        exit;
    }
}

/*
 * Retain these filters for third-party/legacy shortcode calls. They now
 * redirect the actual My Hub page rather than replacing its content.
 */
add_filter( 'pre_do_shortcode_tag', 'bubbahub_myhub_account_settings_route', 5, 4 );
add_filter( 'the_content', 'bubbahub_myhub_account_settings_content_route', 1 );

function bubbahub_myhub_account_settings_route( $output, $tag, $attr, $m ) {
    if ( ! is_user_logged_in() || empty( $_GET['bh_account_settings'] ) ) return $output;
    if ( ! in_array( $tag, array( 'bubbahub_my_hub', 'bubbahub-my-hub' ), true ) ) return $output;

    return $output;
}

function bubbahub_myhub_account_settings_content_route( $content ) {
    /*
     * Routing is handled by template_redirect. Returning the original
     * content here prevents the old replacement-screen behaviour if a
     * plugin/template bypasses the redirect.
     */
    return $content;
}

function bubbahub_myhub_account_settings_button() {
    if ( ! is_user_logged_in() ) return;

    $url = bubbahub_account_settings_um_url();
    ?>
    <script>
    document.addEventListener('DOMContentLoaded',function(){
      var hub=document.querySelector('.bh-myhub-v3 .bh-myhub-hero');
      if(!hub || hub.querySelector('.bh-myhub-account-settings')) return;

      var a=document.createElement('a');
      a.className='bh-myhub-account-settings bh-myhub-button';
      a.href=<?php echo wp_json_encode( esc_url( $url ) ); ?>;
      a.textContent='⚙ Account Settings';

      var box=hub.querySelector('.bh-myhub-next');
      if(box) box.appendChild(a); else hub.appendChild(a);
    });
    </script>
    <?php
}

/*
 * The UM account page controls its own outer layout. This scoped rule gives
 * the Bubba Hub settings panel the requested 50px breathing room without
 * altering other Ultimate Member tabs or the rest of the site.
 */
function bubbahub_account_settings_dashboard_css() {
    if ( ! is_user_logged_in() ) return;
    ?>
    <style id="bubbahub-account-settings-dashboard-css">
      .um-account .bh-account-settings-um-panel {
        box-sizing: border-box;
        padding: 50px;
      }

      .um-account .bh-account-settings-um-panel #bh-account-settings-screen {
        width: 100%;
        max-width: none;
        box-sizing: border-box;
      }

      .um-account .bh-account-settings-um-panel #bh-account-settings-screen > .bh-profile-header,
      .um-account .bh-account-settings-um-panel #bh-account-settings-screen > .bh-profile-success,
      .um-account .bh-account-settings-um-panel #bh-account-settings-screen > .bh-settings-list,
      .um-account .bh-account-settings-um-panel #bh-account-settings-screen > .bh-profile-card,
      .um-account .bh-account-settings-um-panel #bh-account-settings-screen > .bh-payment-settings {
        box-sizing: border-box;
        width: 100%;
      }

      @media (max-width: 700px) {
        .um-account .bh-account-settings-um-panel {
          padding: 24px;
        }
      }
    </style>
    <?php
}

$bh_booking_lifecycle = dirname( __FILE__ ) . '/myhub-booking-lifecycle.php';
if ( file_exists( $bh_booking_lifecycle ) ) require_once $bh_booking_lifecycle;
