<?php
/**
 * Plugin Name: BubbaHub Schedule Engine
 * Description: Recurring session generation and schedule utilities for BubbaHub Directory.
 * Version: 1.0.0
 * Author: BubbaHub
 * Requires PHP: 7.4
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$bh_schedule_module = __DIR__ . '/modules/core/bubbahub-schedule-engine.php';
if ( file_exists( $bh_schedule_module ) ) {
    require_once $bh_schedule_module;
}
