<?php
/**
 * BubbaHub runtime protection layer.
 *
 * Prevents a non-fatal bootstrap exception in an optional platform module from
 * taking down the whole Directory/Leader Portal. Fatal PHP errors are logged
 * with enough context to identify the failing module on the next request.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'bubbahub_runtime_safe_require' ) ) {
    function bubbahub_runtime_safe_require( $file, $label = '' ) {
        if ( ! $file || ! file_exists( $file ) ) return false;
        try {
            require_once $file;
            return true;
        } catch ( Throwable $e ) {
            $message = sprintf(
                'BubbaHub module bootstrap failed%s: %s in %s:%d',
                $label ? ' [' . $label . ']' : '',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
            error_log( $message );
            $failed = get_option( 'bubbahub_runtime_failed_modules', array() );
            if ( ! is_array( $failed ) ) $failed = array();
            $failed[] = array( 'label' => $label, 'file' => $file, 'message' => $e->getMessage(), 'time' => current_time( 'mysql' ) );
            update_option( 'bubbahub_runtime_failed_modules', array_slice( $failed, -20 ), false );
            return false;
        }
    }
}

if ( ! function_exists( 'bubbahub_runtime_failed_modules' ) ) {
    function bubbahub_runtime_failed_modules() {
        $failed = get_option( 'bubbahub_runtime_failed_modules', array() );
        return is_array( $failed ) ? $failed : array();
    }
}

add_action( 'admin_notices', function() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $failed = bubbahub_runtime_failed_modules();
    if ( ! $failed ) return;
    $last = end( $failed );
    if ( ! is_array( $last ) ) return;
    echo '<div class="notice notice-warning"><p><strong>Bubba Hub:</strong> an optional module failed to load and was isolated so the rest of the site can continue. Check the PHP error log for <code>' . esc_html( $last['label'] ?? 'unknown module' ) . '</code>.</p></div>';
} );
