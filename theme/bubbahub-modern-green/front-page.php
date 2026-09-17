<?php get_header(); ?>
<main id="main-content">
<section class="bh-hero">
  <div class="bh-container bh-hero-grid">
    <div>
      <span class="bh-eyebrow">Local family life, made easier</span>
      <h1>Find your perfect group.</h1>
      <p>Discover pregnancy, baby, toddler and family groups, classes and activities near you across Devon &amp; Cornwall.</p>
      <div class="bh-hero-actions">
        <a class="bh-button" href="<?php echo esc_url(home_url('/groups/')); ?>">Find your next group</a>
        <a class="bh-button bh-button--ghost" href="<?php echo esc_url(home_url('/list-your-group/')); ?>">List your group</a>
      </div>
    </div>
    <div class="bh-hero-art" aria-hidden="true"><div class="bh-art-card"><div class="bh-art-image"></div><div class="bh-art-line"></div><div class="bh-art-line short"></div><div class="bh-art-line"></div></div></div>
  </div>
</section>
<section class="bh-main">
  <div class="bh-container">
    <div class="bh-section">
      <div class="bh-section-heading"><div><h2>Explore local groups</h2><p>Search by location, age and price using the directory.</p></div></div>
      <div class="bh-directory-shell"><?php echo bubbahub_theme_directory_shortcode(); ?></div>
    </div>
  </div>
</section>
</main>
<?php get_footer(); ?>
