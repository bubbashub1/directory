# BubbaHub Group Page

This extension provides the custom single-page layout for the `group` post type.

## Activation

The file `bubbahub-group-template.php` is intentionally separate from the main directory plugin so the stable directory code is not modified. In WordPress, activate **BubbaHub Group Page** after uploading the repository/plugin files.

## ACF fields used

- `image` — Gallery; first image is used, with native Featured Image as fallback.
- `term_time` — true/false.
- `address` — fallback address when no Venue is selected.
- `venue` — Post Object/ID pointing to a `venue` post.
- `map` — OpenStreetMap iFrame; coordinates are read from the OSM embed URL.
- `age_range`
- `price`
- `session_length`
- `booking_required`
- `schedule`
- `schedule_notes`

## Venue behaviour

The group `venue` field is treated as the primary location. Its `address` field is used where available and the address links to the venue permalink. If no venue is selected, the group's own `address` field is used.

The alternative-venue selector looks for published `venue` posts authored by the same WordPress user as the group. Selecting a venue refreshes **Other Classes by this Organiser** for that venue.

## Organiser

Other classes are based on the Group post author. Related cards open in a new browser tab/window.

## Leader editing

**Edit Listing** is displayed only to logged-in users with the `leader` or `leaderpro` role who are the listing owner or otherwise have WordPress permission to edit that post.

## Booking

The **Ready to Join?** card is currently a placeholder. Its button points to `#booking` so the future booking form can be inserted without redesigning the sidebar.
