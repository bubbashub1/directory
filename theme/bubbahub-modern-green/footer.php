<footer class="bh-footer">
  <div class="bh-container bh-footer-grid">
    <div>
      <h2>Bubba Hub</h2>
      <p>Helping families discover pregnancy, baby, toddler and family groups across Devon &amp; Cornwall.</p>
    </div>
    <div>
      <h3>Explore</h3>
      <?php if ( has_nav_menu('footer') ) : wp_nav_menu(array('theme_location'=>'footer','container'=>false,'menu_class'=>'bh-footer-links','fallback_cb'=>false)); else : ?>
        <ul class="bh-footer-links"><li><a href="<?php echo esc_url(home_url('/groups/')); ?>">Find groups</a></li><li><a href="<?php echo esc_url(home_url('/my-hub/')); ?>">My Hub</a></li><li><a href="<?php echo esc_url(home_url('/list-your-group/')); ?>">List your group</a></li></ul>
      <?php endif; ?>
    </div>
    <div>
      <h3>For group leaders</h3>
      <ul class="bh-footer-links"><li><a href="<?php echo esc_url(home_url('/leader-portal/')); ?>">Leader Portal</a></li><li><a href="<?php echo esc_url(home_url('/support-us/')); ?>">Support Bubba Hub</a></li></ul>
    </div>
  </div>
  <div class="bh-container bh-footer-bottom"><span>&copy; <?php echo esc_html(date('Y')); ?> Bubba Hub</span><span>Built for families and local communities.</span></div>
</footer>
</div>
<?php wp_footer(); ?>
</body>
</html>
