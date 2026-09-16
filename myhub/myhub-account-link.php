<?php
/**
 * Adds the Account Settings entry point to the existing My Hub v2 UI
 * without modifying the stable dashboard template.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'the_content', 'bubbahub_myhub_account_settings_route', 5 );
add_action( 'wp_footer', 'bubbahub_myhub_account_settings_button', 30 );

function bubbahub_myhub_account_settings_route( $content ) {
    if ( ! is_user_logged_in() || empty( $_GET['bh_account_settings'] ) ) return $content;
    if ( false === strpos( $content, '[bubbahub_my_hub]' ) && false === strpos( $content, '[bubbahub-my-hub]' ) ) return $content;
    return do_shortcode( '[bubbahub_account_settings]' );
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
