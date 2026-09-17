# BubbaHub Directory

Front-end directory for the WordPress `group` custom post type, designed to work with ACF Pro, plus the BubbaHub Leader Portal and an admin drag-and-drop directory builder.

## Current features

- Uses the `group` custom post type.
- Uses ACF when available, with WordPress post meta as a fallback.
- Admin **Directory Builder** with drag-and-drop elements and persistent saved layout.
- Front-end directory cards consume the saved builder layout for title, description, location, schedule, price, booking, subscription, other classes, map, contact and social elements.
- Listing card uses the first image from the ACF Gallery field `image`; the native WordPress Featured Image is the fallback when the gallery is empty.
- Card title links to the native WordPress permalink.
- Card location comes from the `region` taxonomy and optional address/venue data.
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

## Directory Builder

Go to **WordPress Admin → Groups → Directory Builder**.

Drag elements from the library into the layout, reorder them, and click **Save Layout**. The saved layout is stored in the WordPress option `bubbahub_directory_builder_layout` and is used when directory cards are rendered, including AJAX-filtered results.

Available elements include:

- Search & Filters
- Image
- Title
- Description
- Location
- Schedule / Business Hours
- Price
- Booking
- Subscription
- Other Classes
- Map
- Contact
- Social Links

The Booking and Subscription elements use matching BubbaHub shortcodes when they are registered, with a safe fallback link when the relevant module is not active.

## ACF fields expected

- `image` — **Gallery** field. The first gallery image is used on the card. Native Featured Image is used if the gallery is empty.
- `term_time` — used to show the Term Time badge. Supports ACF true/false-style values and common yes/no values.
- `age_range` — displayed on the card and available to the age filter.
- `price` — displayed on the card and available to the Free/Paid filter.
- `map` — ACF Google Map-style field containing latitude/longitude, or a coordinate string.
- `business_hours`, `schedule` or `timetable` — used by the Schedule / Business Hours element.
- `address` — optional listing or venue address.
- `email`, `phone`, `website` — optional contact fields.
- `facebook`, `instagram`, `twitter`, `tiktok` or `social_url` — optional social fields.

## Taxonomy

The location displayed on cards and used by the advanced location filter is the `region` taxonomy attached to `group`.

## Badge behaviour

- **Fav Group** — visitor can toggle the heart icon for a listing.
- **Compare** — visitor can toggle a listing into their browser-side comparison set.
- **Visited** — visitor can mark a listing as visited.
- **Term Time** — read-only badge controlled by the ACF `term_time` field.

The first three states are currently browser-local. They are intentionally not written to WordPress yet; a later logged-in member stage can connect them to BuddyBoss/user accounts.

## Install / deployment

1. Deploy the repository contents into a WordPress plugin folder such as `directory-main`.
2. Activate **BubbaHub Directory** in WordPress.
3. Create a page and add `[bubbahub_directory]` to it.
4. Open **Groups → Directory Builder** to configure the card layout.
