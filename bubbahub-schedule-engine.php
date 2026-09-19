<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$bh_schedule_module = __DIR__ . '/modules/core/bubbahub-schedule-engine.php';
if ( file_exists( $bh_schedule_module ) ) {
    require_once $bh_schedule_module;
}
