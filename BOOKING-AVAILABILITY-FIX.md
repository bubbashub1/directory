# Booking date/session availability fix

## Problem checked

The Group page can show a valid date in the booking selector while the session area remains empty. The admin session shown in the supplied screenshots has a valid date, time, price, capacity, Reserve Spot action and Open status.

## Root cause addressed

The original AJAX lookup used strict WordPress meta queries for `_bh_group_id` and `_bh_session_status`. Legacy/ACF-created session metadata can be stored in slightly different representations even though the values displayed in the editor are correct.

The compatibility plugin replaces only the AJAX session lookup. It does not alter the session editor, booking creation, reservations, Ninja Forms or external booking links.

The replacement lookup:

- reads all published `bh_session` records;
- normalises the stored Group ID;
- accepts common Open status representations;
- normalises `YYYY-MM-DD`, `DD/MM/YYYY`, `DD-MM-YYYY`, `YYYYMMDD` and timestamp dates;
- matches the selected date after normalisation;
- honours capacity and existing confirmed/reserved bookings;
- returns the existing booking action, price, time and remaining spaces.

## Installation

This file is a standalone WordPress plugin:

`bubbahub-booking-availability-compat.php`

Upload it to `wp-content/plugins/` and activate it alongside the existing BubbaHub Booking Engine.

No existing session needs to be edited or recreated.

## Test case

Use:

- Group: Demo group
- Date: 21/09/2026
- Time: 10:00 AM–10:30 AM
- Price: £5
- Capacity: 20
- Action: Reserve Spot
- Status: Open

Then clear the WordPress/cache plugin cache and hard refresh the Group page.

The selected date should return the 10:00–10:30 session. Selecting it should expose the Reserve Spot action.
