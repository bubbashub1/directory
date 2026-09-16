<?php
/**
 * Adds the Account Settings entry point to the existing My Hub v2 UI.
 * Stage 2 routes the settings screen through account-settings-stage2.php.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'pre_do_shortcode_tag', 'bubbahub_myhub_account_settings_route', 5, 4 );
add_action( 'wp_footer', 'bubbahub_myhub_account_settings_button', 30 );

function bubbahub_myhub_account_settings_route( $output, $tag, $attr, $m ) {
    if ( ! is_user_logged_in() || empty( $_GET['bh_account_settings'] ) ) return $output;
    if ( ! in_array( $tag, array( 'bubbahub_my_hub', 'bubbahub-my-hub' ), true ) ) return $output;
    if ( function_exists( 'bubbahub_account_settings_stage2_shortcode' ) ) {
        return bubbahub_account_settings_stage2_shortcode();
    }
    return function_exists( 'bubbahub_account_settings_shortcode' ) ? bubbahub_account_settings_shortcode() : $output;
}

function bubbahub_myhub_account_settings_button() {
    if ( ! is_user_logged_in() ) return;
    $url = add_query_arg( 'bh_account_settings', '1' );
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
