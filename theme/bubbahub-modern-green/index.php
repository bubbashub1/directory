<?php get_header(); ?>
<main id="main-content" class="bh-main">
  <div class="bh-container">
    <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
      <article class="bh-content-card">
        <h1 class="bh-page-title"><?php the_title(); ?></h1>
        <?php the_content(); ?>
      </article>
    <?php endwhile; else : ?>
      <div class="bh-content-card"><h1 class="bh-page-title">Nothing here yet</h1><p>Please check back soon.</p></div>
    <?php endif; ?>
  </div>
</main>
<?php get_footer(); ?>
