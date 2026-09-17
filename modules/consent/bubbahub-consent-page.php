<?php
/**
 * Creates the built-in Bubba Hub consent page when one does not exist.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', function() {
    if ( get_page_by_path( 'booking-consent' ) ) return;
    wp_insert_post( array(
        'post_title'   => 'Booking Consent',
        'post_name'    => 'booking-consent',
        'post_content' => '[bubbahub_consent_form]',
        'post_status'  => 'publish',
        'post_type'    => 'page',
    ) );
}, 20 );

add_shortcode( 'bubbahub_consent_form', function( $atts = array() ) {
    $atts = shortcode_atts( array( 'group_id' => 0 ), $atts, 'bubbahub_consent_form' );
    $group_id = absint( $atts['group_id'] );
    if ( ! $group_id && isset( $_GET['group_id'] ) ) $group_id = absint( $_GET['group_id'] );
    if ( ! $group_id || 'group' !== get_post_type( $group_id ) ) {
        return '<div class="bh-consent-page"><h1>Booking consent</h1><p>Please open the consent form from the relevant Bubba Hub booking.</p></div>';
    }
    $title = function_exists( 'bubbahub_consent_form_title' ) ? bubbahub_consent_form_title( $group_id ) : 'Booking consent form';
    $version = function_exists( 'bubbahub_consent_version' ) ? bubbahub_consent_version( $group_id ) : '1.0';
    $group_title = get_the_title( $group_id );
    ob_start(); ?>
    <main class="bh-consent-page">
        <div class="bh-consent-page-card">
            <span class="bh-consent-eyebrow">BUBBA HUB CONSENT</span>
            <h1><?php echo esc_html( $title ); ?></h1>
            <p><strong><?php echo esc_html( $group_title ); ?></strong></p>
            <p>Please review the consent information supplied by the organiser before completing your booking.</p>
            <p class="bh-consent-version">Consent version <?php echo esc_html( $version ); ?></p>
            <div class="bh-consent-page-note">This page records your acknowledgement at checkout. The booking record stores the consent version and time accepted so it remains linked to the reservation and any payment.</div>
        </div>
    </main>
    <style>
      .bh-consent-page{max-width:760px;margin:30px auto;padding:16px}.bh-consent-page-card{background:#fff;border:1px solid #d9dfe7;border-radius:16px;padding:28px;box-shadow:0 8px 30px rgba(0,0,0,.05)}.bh-consent-eyebrow{font-size:12px;font-weight:800;letter-spacing:.08em}.bh-consent-page h1{margin:8px 0 14px}.bh-consent-version{font-size:13px;color:#667085}.bh-consent-page-note{margin-top:20px;padding:14px;border-radius:10px;background:#f8fafc}@media(max-width:600px){.bh-consent-page{margin:10px auto;padding:10px}.bh-consent-page-card{padding:20px}}
    </style>
    <?php return ob_get_clean();
} );
