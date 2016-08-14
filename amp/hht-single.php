<!--
     This is a AMP custom post template example to show the relative 
     placement of the Related Posts hook.
-->
<!doctype html>
<html amp>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1,user-scalable=no">
    <?php do_action( 'amp_post_template_head', $this ); ?>

    <style amp-custom>
    <?php $this->load_parts( array( 'style' ) ); ?>
    <?php do_action( 'amp_post_template_css', $this ); ?>
    </style>
  </head>
  <body>    
    <header class="siteHeader">
      <a href="https://www.handymanhowto.com/"> </a>
    </header>

    <div class="amp-wp-content">
      <h1 class="amp-wp-title"><?php echo wp_kses_data( $this->get( 'post_title' ) ); ?></h1>
      <ul class="amp-wp-meta">
        <?php $this->load_parts( apply_filters( 'amp_post_template_meta_parts', array( 'meta-author', 'meta-time', 'meta-taxonomy' ) ) ); ?>
      </ul>
      <?php echo $this->get( 'post_amp_content' ); ?>

      <!-- ************************************************ -->
      <!-- Display the Related Posts and Interesting Posts  -->
      <!-- ************************************************ -->
      <?php do_action( 'amp_get_contextly_related_posts' ); ?>
      
    </div>

    <?php do_action( 'amp_post_template_footer', $this ); ?>

  </body>
</html>