# BubbaHub Directory

Front-end directory for the WordPress `group` custom post type, designed to work with ACF.

## Current features

- Uses the `group` custom post type.
- Uses ACF when available, with WordPress post meta as a fallback.
- Listing card: image, ACF `badges`, title/permalink, `region` taxonomy, ACF `age_range`, ACF `price`, and View More.
- Search by WordPress title/content.
- Hidden Advanced Search panel with region, age range and price filters.
- Four-column initial grid.
- User-selectable 2, 3, 4, 5 or 6 columns on desktop.
- Responsive mobile layout.
- Map view using ACF `map` coordinates and Leaflet/OpenStreetMap.
- Map popup links back to the listing permalink.
- Shortcode: `[bubbahub_directory]`

## Install

1. Upload the repository contents into a plugin folder named `bubbahub-directory`.
2. Activate the plugin in WordPress.
3. Create a page and add `[bubbahub_directory]` to it.

## ACF field names expected

- `image` — optional image field; featured image is used as fallback.
- `badges` — optional text, textarea, select, repeater or array-compatible field.
- `age_range`
- `price`
- `map` — ACF Google Map-style array containing `lat` and `lng`, or a coordinate string.

## Taxonomy

The location displayed on cards and used by the advanced location filter is the `region` taxonomy attached to `group`.

## Next phase

The field names can be made fully configurable from a BubbaHub Directory settings screen once the final ACF field group is confirmed. This avoids hard-coding assumptions about future fields.
