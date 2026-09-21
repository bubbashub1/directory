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
// Register one central Bubba Hub Account Settings tab in Ultimate Member.
// All current settings and future leader-profile settings live inside this
// single destination. Individual settings use bh_settings_section internally.
add_filter( 'um_account_page_default_tabs_hook', 'bubbahub_account_settings_um_tabs', 160 );
add_filter( 'um_account_content_hook_bubbahub_account_settings', 'bubbahub_account_settings_um_section_content', 20, 2 );

add_action( 'wp_footer', 'bubbahub_myhub_account_settings_button', 30 );
add_action( 'wp_head', 'bubbahub_account_settings_dashboard_css', 30 );

function bubbahub_account_settings_um_url() {
    if ( function_exists( 'um_get_core_page' ) ) {
        $url = um_get_core_page( 'account' );
        if ( $url ) {
            return esc_url_raw( add_query_arg( 'um_tab', 'bubbahub_account_settings', $url ) );
        }
    }
    return esc_url_raw( add_query_arg( 'um_tab', 'bubbahub_account_settings', home_url( '/account/' ) ) );
}

function bubbahub_account_settings_um_tabs( $tabs ) {
    $labels = array(
        'bubbahub_settings_pro'                => array( 'icon' => 'um-faicon-star',     'title' => 'Manage my Pro Account' ),
        'bubbahub_settings_preferences'       => array( 'icon' => 'um-faicon-heart',    'title' => 'My Bubba Hub Directory Preferences' ),
        'bubbahub_settings_calendar'          => array( 'icon' => 'um-faicon-calendar', 'title' => 'Calendar Settings' ),
        'bubbahub_settings_notification_test' => array( 'icon' => 'um-faicon-flask',    'title' => 'Notification Test' ),
        'bubbahub_settings_privacy'           => array( 'icon' => 'um-faicon-lock',     'title' => 'Privacy & Security' ),
        'bubbahub_settings_notifications'     => array( 'icon' => 'um-faicon-bell',     'title' => 'Notification preferences' ),
        'bubbahub_settings_consent'           => array( 'icon' => 'um-faicon-shield',   'title' => 'Class Consent & Safety' ),
        'bubbahub_settings_payments'          => array( 'icon' => 'um-faicon-credit-card', 'title' => 'My Payments, Invoices & Wallet' ),
    );

    $position = 160;
    foreach ( $labels as $tab_key => $label ) {
        $tabs[ $position ][ $tab_key ] = array(
            'icon'         => $label['icon'],
            'title'        => __( $label['title'], 'bubbahub' ),
            'submit_title' => __( $label['title'], 'bubbahub' ),
            'custom'       => true,
        );
        $position++;
    }

    return $tabs;
}

function bubbahub_account_settings_um_section_content( $output = '', $shortcode_args = array() ) {
    if ( ! is_user_logged_in() || ! function_exists( 'bubbahub_account_settings_stage2_shortcode' ) ) {
        return $output;
    }

    // The central UM tab is the Account Settings home. A bh_settings_section
    // query parameter selects a section while keeping the user on this tab.
    $section = '';
    if ( isset( $shortcode_args['bh_settings_section'] ) ) {
        $section = sanitize_key( wp_unslash( $shortcode_args['bh_settings_section'] ) );
    } elseif ( isset( $_GET['bh_settings_section'] ) ) {
        $section = sanitize_key( wp_unslash( $_GET['bh_settings_section'] ) );
    }

    $old_flag    = array_key_exists( 'bh_account_settings', $_GET ) ? $_GET['bh_account_settings'] : null;
    $had_flag    = array_key_exists( 'bh_account_settings', $_GET );
    $old_section = array_key_exists( 'bh_settings_section', $_GET ) ? $_GET['bh_settings_section'] : null;
    $had_section = array_key_exists( 'bh_settings_section', $_GET );

    $_GET['bh_account_settings'] = '1';
    if ( $section !== '' ) {
        $_GET['bh_settings_section'] = $section;
    } else {
        unset( $_GET['bh_settings_section'] );
    }

    $content = '<div class="bh-account-settings-um-panel">' . bubbahub_account_settings_stage2_shortcode() . '</div>';

    if ( $had_flag ) {
        $_GET['bh_account_settings'] = $old_flag;
    } else {
        unset( $_GET['bh_account_settings'] );
    }
    if ( $had_section ) {
        $_GET['bh_settings_section'] = $old_section;
    } else {
        unset( $_GET['bh_settings_section'] );
    }

    return $content;
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
    // Account Settings is registered natively with Ultimate Member, so no
    // JavaScript side-menu injection is required.
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
if ( file_exists( $bh_booking_lifecycle ) ) require_once $bh_booking_lifecycle;function bubbahub_account_settings_um_sections() {
    return array(
        'bubbahub_account_settings' => 'home',
    );
}

function bubbahub_account_settings_um_tabs( $tabs ) {
    $tabs[160]['bubbahub_account_settings'] = array(
        'icon'         => 'um-faicon-cog',
        'title'        => __( 'Account Settings', 'bubbahub' ),
        'submit_title' => __( 'Account Settings', 'bubbahub' ),
        'custom'       => true,
    );
    return $tabs;
}

function bubbahub_account_settings_um_section_content( $output = '', $shortcode_args = array() ) {
    if ( ! is_user_logged_in() || ! function_exists( 'bubbahub_account_settings_stage2_shortcode' ) ) {
        return $output;
    }

    $section = '';
    if ( isset( $shortcode_args['bh_settings_section'] ) ) {
        $section = sanitize_key( wp_unslash( $shortcode_args['bh_settings_section'] ) );
    } elseif ( isset( $_GET['bh_settings_section'] ) ) {
        $section = sanitize_key( wp_unslash( $_GET['bh_settings_section'] ) );
    }

    $had_flag = array_key_exists( 'bh_account_settings', $_GET );
    $old_flag = $had_flag ? $_GET['bh_account_settings'] : null;
    $had_section = array_key_exists( 'bh_settings_section', $_GET );
    $old_section = $had_section ? $_GET['bh_settings_section'] : null;

    $_GET['bh_account_settings'] = '1';
    if ( $section !== '' ) {
        $_GET['bh_settings_section'] = $section;
    } else {
        unset( $_GET['bh_settings_section'] );
    }

    $content = '<div class="bh-account-settings-um-panel">' . bubbahub_account_settings_stage2_shortcode() . '</div>';

    if ( $had_flag ) {
        $_GET['bh_account_settings'] = $old_flag;
    } else {
        unset( $_GET['bh_account_settings'] );
    }
    if ( $had_section ) {
        $_GET['bh_settings_section'] = $old_section;
    } else {
        unset( $_GET['bh_settings_section'] );
    }

    return $content;
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

    // Build the base Account URL without an existing um_tab parameter;
    // each side-menu item must have exactly one canonical tab URL.
    $account_url = remove_query_arg( 'um_tab', bubbahub_account_settings_um_url() );
    $menu_items = array(
        array( 'key' => 'bubbahub_settings_pro', 'title' => 'Manage my Pro Account', 'icon' => '⭐' ),
        array( 'key' => 'bubbahub_settings_preferences', 'title' => 'My Bubba Hub Directory Preferences', 'icon' => '❤️' ),
        array( 'key' => 'bubbahub_settings_calendar', 'title' => 'Calendar Settings', 'icon' => '🗓️' ),
        array( 'key' => 'bubbahub_settings_notification_test', 'title' => 'Notification Test', 'icon' => '🧪' ),
        array( 'key' => 'bubbahub_settings_privacy', 'title' => 'Privacy & Security', 'icon' => '🔐' ),
        array( 'key' => 'bubbahub_settings_notifications', 'title' => 'Notification preferences', 'icon' => '🔔' ),
        array( 'key' => 'bubbahub_settings_consent', 'title' => 'Class Consent & Safety', 'icon' => '🛡️' ),
        array( 'key' => 'bubbahub_settings_payments', 'title' => 'My Payments, Invoices & Wallet', 'icon' => '💳' ),
    );
    ?>
    <script>
    document.addEventListener('DOMContentLoaded',function(){
      var accountUrl=<?php echo wp_json_encode( esc_url( $account_url ) ); ?>;
      var items=<?php echo wp_json_encode( $menu_items ); ?>;

      function addBubbaSettingsMenu(){
        var side=document.querySelector('.um-account-side');
        if(!side) return false;

        var list=side.querySelector('.um-account-side-menu');
        if(!list){
          list=side.querySelector('ul');
        }
        if(!list) return false;

        items.forEach(function(item){
          var selector='[data-bh-account-tab="'+item.key+'"]';
          if(list.querySelector(selector)) return;

          var li=document.createElement('li');
          li.className='um-account-link';
          li.setAttribute('data-bh-account-tab',item.key);

          var a=document.createElement('a');
          a.href=accountUrl+(accountUrl.indexOf('?')===-1?'?':'&')+'um_tab='+encodeURIComponent(item.key);
          a.innerHTML='<span class="um-account-icon">'+item.icon+'</span><span class="um-account-title">'+item.title+'</span>';
          li.appendChild(a);
          list.appendChild(li);
        });
        return true;
      }

      if(!addBubbaSettingsMenu()){
        var observer=new MutationObserver(function(){
          if(addBubbaSettingsMenu()) observer.disconnect();
        });
        observer.observe(document.body,{childList:true,subtree:true});
        setTimeout(function(){observer.disconnect();},10000);
      }
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
