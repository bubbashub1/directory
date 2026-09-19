<?php
/**
 * BubbaHub Calendar Sync
 * Public iCalendar feed and single-event .ics links for the Weekly Planner.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'template_redirect', 'bubbahub_calendar_sync_template_redirect', 1 );

function bubbahub_calendar_sync_escape( $value ) {
    $value = wp_strip_all_tags( (string) $value );
    $value = str_replace(
        array( "\\", ";", ",", "\n", "\r" ),
        array( "\\\\", "\\;", "\\,", "\\n", "" ),
        $value
    );
    return $value;
}

function bubbahub_calendar_sync_ics_datetime( $date, $time ) {
    $dt = new DateTime( $date . ' ' . $time, wp_timezone() );
    return $dt->format( 'Ymd\THis' );
}

function bubbahub_calendar_sync_event_uid( $group_id, $date, $start, $end = '' ) {
    return md5(
        absint( $group_id ) . '|' .
        sanitize_text_field( $date ) . '|' .
        sanitize_text_field( $start ) . '|' .
        sanitize_text_field( $end )
    ) . '@bubbahub.co.uk';
}

function bubbahub_calendar_sync_template_redirect() {
    if ( isset( $_GET['bubbahub_calendar_event'] ) ) {
        $group_id = isset( $_GET['group'] ) ? absint( $_GET['group'] ) : 0;
        $date     = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : '';
        $start    = isset( $_GET['start'] ) ? sanitize_text_field( wp_unslash( $_GET['start'] ) ) : '';
        $end      = isset( $_GET['end'] ) ? sanitize_text_field( wp_unslash( $_GET['end'] ) ) : '';

        if ( ! $group_id || ! $date || ! $start || get_post_type( $group_id ) !== 'group' ) {
            status_header( 400 );
            exit;
        }

        $rows = bubbahub_myhub_planner_v2_schedule_occurrences( $date, $date );
        $event = null;

        foreach ( $rows as $row ) {
            if (
                (int) $row['group_id'] === $group_id &&
                $row['date'] === $date &&
                $row['start'] === $start &&
                ( ! $end || $row['end'] === $end )
            ) {
                $event = $row;
                break;
            }
        }

        if ( ! $event ) {
            status_header( 404 );
            exit;
        }

        bubbahub_calendar_sync_output(
            array( $event ),
            'Bubba Hub - ' . $event['title']
        );
    }

    if ( isset( $_GET['bubbahub_calendar'] ) ) {
        $start_ts   = current_time( 'timestamp' );
        $range_start = wp_date( 'Y-m-d', $start_ts );
        $range_end   = wp_date( 'Y-m-d', strtotime( '+180 days', $start_ts ) );
        $region      = isset( $_GET['bh_region'] ) ? absint( $_GET['bh_region'] ) : 0;

        $events = bubbahub_myhub_planner_v2_schedule_occurrences(
            $range_start,
            $range_end
        );

        if ( $region ) {
            $events = array_values(
                array_filter(
                    $events,
                    function ( $event ) use ( $region ) {
                        return in_array(
                            $region,
                            bubbahub_myhub_planner_v2_region_ids( (int) $event['group_id'] ),
                            true
                        );
                    }
                )
            );
        }

        bubbahub_calendar_sync_output(
            $events,
            $region ? 'Bubba Hub - Region Calendar' : 'Bubba Hub - What’s On'
        );
    }
}

function bubbahub_calendar_sync_output( $events, $name ) {
    nocache_headers();
    header( 'Content-Type: text/calendar; charset=utf-8' );
    header( 'Content-Disposition: inline; filename="bubbahub-calendar.ics"' );

    $now = gmdate( 'Ymd\THis\Z' );
    $timezone = wp_timezone_string();

    $lines = array(
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Bubba Hub//Weekly Planner//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'X-WR-CALNAME:' . bubbahub_calendar_sync_escape( $name ),
        'X-WR-TIMEZONE:' . bubbahub_calendar_sync_escape( $timezone ),
    );

    foreach ( $events as $event ) {
        $start = $event['start'];
        $end   = $event['end'];

        if ( ! $end ) {
            $end = date( 'H:i', strtotime( $start ) + HOUR_IN_SECONDS );
        }

        $uid = bubbahub_calendar_sync_event_uid(
            $event['group_id'],
            $event['date'],
            $start,
            $end
        );

        $location = $event['venue_address'] ?: $event['venue'];
        $description = 'Bubba Hub: ' . $event['url'];

        if ( ! empty( $event['label'] ) ) {
            $description = $event['label'] . "\n" . $description;
        }

        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:' . $uid;
        $lines[] = 'DTSTAMP:' . $now;
        $lines[] = 'DTSTART;TZID=' . bubbahub_calendar_sync_escape( $timezone ) . ':' . bubbahub_calendar_sync_ics_datetime( $event['date'], $start );
        $lines[] = 'DTEND;TZID=' . bubbahub_calendar_sync_escape( $timezone ) . ':' . bubbahub_calendar_sync_ics_datetime( $event['date'], $end );
        $lines[] = 'SUMMARY:' . bubbahub_calendar_sync_escape( $event['title'] . ( ! empty( $event['label'] ) ? ' - ' . $event['label'] : '' ) );
        $lines[] = 'LOCATION:' . bubbahub_calendar_sync_escape( $location );
        $lines[] = 'DESCRIPTION:' . bubbahub_calendar_sync_escape( $description );
        $lines[] = 'URL:' . esc_url_raw( $event['url'] );
        $lines[] = 'END:VEVENT';
    }

    $lines[] = 'END:VCALENDAR';

    echo implode( "\r\n", $lines ) . "\r\n";
    exit;
}
