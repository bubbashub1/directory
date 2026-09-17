# BubbaHub Modern Green

A lightweight custom WordPress theme designed specifically around the BubbaHub Directory plugin.

## Design

- Soft modern green palette with warm cream backgrounds.
- Elegant serif headings paired with friendly Nunito Sans body text.
- Rounded cards, subtle shadows and generous spacing.
- Accessible focus states and reduced-motion support.
- Responsive navigation with a mobile menu.

## BubbaHub Directory integration

The theme is designed around the existing `BubbaHub Directory` plugin and its `[bubbahub_directory]` shortcode. The homepage and group archive render the directory automatically. The theme also provides a styled `group` single template and colour variables that complement the plugin's existing `.bh-*` markup.

## Mobile-first behaviour

The layout collapses to a single-column experience at phone widths, keeps controls touch-friendly, prevents horizontal overflow, scales headings with `clamp()`, and preserves readable spacing on very small screens.

## Installation

This folder is a standalone WordPress theme. Deploy `theme/bubbahub-modern-green` into `wp-content/themes/bubbahub-modern-green`, then activate it in **Appearance → Themes**. Keep the BubbaHub Directory plugin active.

If using a Git deployment plugin, configure it to deploy this theme folder to the WordPress themes directory rather than the plugins directory.
