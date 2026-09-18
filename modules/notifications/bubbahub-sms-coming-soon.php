<?php
/**
 * Bubba Hub SMS Coming Soon UI.
 *
 * Keeps SMS controls clearly marked as unavailable until a dedicated
 * Bubba Hub SMS number/device is configured and the service is enabled.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function bubbahub_sms_coming_soon_is_account_area() {
    if ( ! is_user_logged_in() ) return false;
    $path = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
    return (bool) preg_match( '#/(my-hub|myhub|account|leader|dashboard)(?:/|\?|$)#i', $path );
}

function bubbahub_sms_coming_soon_banner() {
    if ( ! bubbahub_sms_coming_soon_is_account_area() ) return;
    ?>
    <style id="bubbahub-sms-coming-soon-css">
        .bh-sms-coming-soon-banner{display:flex;align-items:center;gap:12px;margin:0 0 20px;padding:14px 18px;border:1px solid #d9e8df;border-radius:16px;background:#f4faf6;color:#23493d;box-shadow:0 4px 14px rgba(27,64,52,.05)}
        .bh-sms-coming-soon-banner .bh-sms-cs-icon{width:38px;height:38px;flex:0 0 38px;display:flex;align-items:center;justify-content:center;border-radius:12px;background:#e2f2e7;font-size:19px}
        .bh-sms-coming-soon-banner strong{display:block;font-size:14px;margin-bottom:2px}.bh-sms-coming-soon-banner span{display:block;font-size:12px;color:#687a72;line-height:1.45}
        .bh-sms-coming-soon-label{display:inline-flex!important;align-items:center;margin-left:8px;padding:4px 8px;border-radius:999px;background:#eef2ef;color:#66766f;font-size:10px!important;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
        .bh-notification-option[data-bh-sms-disabled]{cursor:default;opacity:.82}
        .bh-notification-option[data-bh-sms-disabled] input{display:none}
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var options = document.querySelectorAll('.bh-notification-option');
        options.forEach(function(option) {
            var strong = option.querySelector('.bh-notification-copy strong');
            if (!strong || !/SMS reminders/i.test(strong.textContent)) return;
            option.setAttribute('data-bh-sms-disabled','1');
            var badge = document.createElement('span');
            badge.className = 'bh-sms-coming-soon-label';
            badge.textContent = 'Coming soon';
            strong.appendChild(badge);
            var small = option.querySelector('.bh-notification-copy small');
            if (small) small.textContent = 'SMS alerts are coming soon. Email and My Hub notifications remain available.';
            var input = option.querySelector('input');
            if (input) { input.checked = false; input.disabled = true; }
        });
        document.querySelectorAll('.bh-notification-phone').forEach(function(phone) {
            phone.style.display = 'none';
        });
    });
    </script>
    <?php
}
add_action( 'wp_footer', 'bubbahub_sms_coming_soon_banner', 5 );

function bubbahub_sms_coming_soon_admin_notice() {
    if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['page'] ) || 'bubbahub-sms' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) return;
    echo '<div class="notice notice-info"><p><strong>SMS Alerts — Coming soon.</strong> Connect a dedicated Bubba Hub mobile number/SIM through TextBee when you are ready. Do not use a personal mobile number.</p></div>';
}
add_action( 'admin_notices', 'bubbahub_sms_coming_soon_admin_notice', 5 );
