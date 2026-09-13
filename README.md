# BubbaHub Directory

Front-end directory for the WordPress `group` custom post type, designed to work with ACF Pro.

## Current features

- Uses the `group` custom post type.
- Uses ACF when available, with WordPress post meta as a fallback.
- Listing card uses the first image from the ACF Gallery field `image`; the native WordPress Featured Image is the fallback when the gallery is empty.
- Card title links to the native WordPress permalink.
- Card location comes from the `region` taxonomy.
- Card displays ACF `age_range` and ACF `price`.
- Card action badges: Fav Group, Compare and Visited.
- Term Time badge is displayed when ACF `term_time` is enabled.
- Favourite, Compare and Visited states persist in the visitor's browser using localStorage.
- Search by WordPress title/content.
- Hidden Advanced Search panel with region, age range and price filters.
- AJAX filtering and pagination without a full page reload.
- Four-column initial grid.
- User-selectable 2, 3, 4, 5 or 6 columns on desktop.
- Responsive mobile layout.
- Map view using the ACF `map` field coordinates and Leaflet/OpenStreetMap.
- Map popup links back to the listing permalink.
- Shortcode: `[bubbahub_directory]`

## ACF fields expected

- `image` — **Gallery** field. The first gallery image is used on the card. Native Featured Image is used if the gallery is empty.
- `term_time` — used to show the Term Time badge. Supports ACF true/false-style values and common yes/no values.
- `age_range` — displayed on the card and available to the age filter.
- `price` — displayed on the card and available to the Free/Paid filter.
- `map` — ACF Google Map-style field containing latitude/longitude, or a coordinate string.

## Taxonomy

The location displayed on cards and used by the advanced location filter is the `region` taxonomy attached to `group`.

## Badge behaviour

- **Fav Group** — visitor can toggle the heart icon for a listing.
- **Compare** — visitor can toggle a listing into their browser-side comparison set.
- **Visited** — visitor can mark a listing as visited.
- **Term Time** — read-only badge controlled by the ACF `term_time` field.

The first three states are currently browser-local. They are intentionally not written to WordPress yet; a later logged-in member stage can connect them to BuddyBoss/user accounts.

## Install

1. Upload the repository contents into a plugin folder named `bubbahub-directory`.
2. Activate the plugin in WordPress.
3. Create a page and add `[bubbahub_directory]` to it.
