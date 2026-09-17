<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="bh-skip" href="#main-content">Skip to content</a>
<div class="bh-site">
<header class="bh-header">
  <div class="bh-container bh-header-inner">
    <?php bubbahub_theme_brand(); ?>
    <button class="bh-nav-toggle" type="button" aria-controls="bh-primary-nav" aria-expanded="false" aria-label="Open menu">☰</button>
    <?php bubbahub_theme_menu(); ?>
  </div>
</header>
